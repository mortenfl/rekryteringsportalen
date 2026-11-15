<?php
/**
 * Demo data: 20 fictional companies, 30 jobs and ~50 reviews. Used by `wp rp seed-demo`.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seeder.
 */
final class RP_Seeder {

	/**
	 * Review texts by star rating.
	 *
	 * @var array<int, string[]>
	 */
	private const TEXTS = array(
		5 => array(
			'Mycket professionellt bemötande genom hela processen. Rekryteraren återkopplade alltid inom ett par dagar och var ärlig om lönenivån.',
			'Hittade rätt kandidat till oss på tre veckor. De förstod verkligen vår kultur och vilka egenskaper vi letade efter.',
			'Bästa rekryteringsupplevelsen jag haft. Tydlig information om tjänsten och bra förberedelser inför intervjun.',
		),
		4 => array(
			'Bra kontakt och relevanta uppdrag. Lite långsam återkoppling efter sista intervjun men i övrigt mycket nöjd.',
			'Kunniga konsulter som kan sin bransch. Processen tog något längre tid än utlovat men resultatet blev bra.',
		),
		3 => array(
			'Helt okej upplevelse. Trevliga personer men tjänsten matchade inte riktigt det som stod i annonsen.',
			'Medelbra. Fick en tjänst till slut men kommunikationen kunde varit tydligare under processen.',
		),
		2 => array(
			'Fick vänta länge på besked och fick till slut veta via LinkedIn att tjänsten var tillsatt. Inte bra.',
			'Rekryteraren verkade inte ha läst mitt CV ordentligt. Föreslog roller som inte alls passade min profil.',
		),
		1 => array(
			'Ingen återkoppling alls efter två intervjuer trots flera påminnelser. Mycket oprofessionellt.',
			'Lovade mycket men levererade inga kandidater på två månader. Vi avslutade samarbetet.',
		),
	);

	/**
	 * Job titles.
	 *
	 * @var string[]
	 */
	private const JOBS = array( 'Controller', 'Redovisningsekonom', 'Backend-utvecklare', 'Projektledare bygg', 'Sjuksköterska', 'Key Account Manager', 'Lagerarbetare', 'HR-generalist', 'Bolagsjurist', 'Ekonomichef', 'Frontend-utvecklare', 'Platschef', 'Säljare innesälj', 'Lönespecialist', 'Kvalitetsingenjör' );

	/**
	 * Create demo data.
	 *
	 * @return array{companies:int, jobs:int, reviews:int}
	 */
	public static function seed(): array {
		mt_srand( 42 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- deterministic demo data.

		$parsed  = RP_Importer::parse_file( RP_CORE_DIR . 'data/demo-foretag.csv' );
		$mapping = RP_Importer::guess_mapping( $parsed['headers'] );
		$ids     = array();
		foreach ( $parsed['rows'] as $row ) {
			$data          = RP_Importer::map_row( $row, $mapping );
			$data['kalla'] = 'demo';
			$res           = RP_Importer::upsert_company(
				$data,
				array(
					'status'    => 'publish',
					'overwrite' => true,
				)
			);
			if ( $res['id'] ) {
				$ids[] = $res['id'];
				wp_update_post(
					array(
						'ID'           => $res['id'],
						'post_content' => '<p>' . esc_html( (string) get_post_meta( $res['id'], 'rp_kort_beskrivning', true ) ) . '</p><p>Detta är ett fiktivt demoföretag som skapats för att visa hur Rekryteringsportalen fungerar.</p>',
					)
				);
			}
		}

		$job_count = 0;
		$types     = RP_Post_Types::employment_types();
		for ( $i = 0; $i < 30; $i++ ) {
			$company = $ids[ $i % count( $ids ) ];
			$regions = wp_get_object_terms( $company, RP_Post_Types::TAX_REGION, array( 'fields' => 'names' ) );
			$title   = self::JOBS[ mt_rand( 0, count( self::JOBS ) - 1 ) ]; // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$saved   = RP_Jobs::upsert_job(
				$company,
				array(
					'id'          => 'demo-' . $i,
					'title'       => $title,
					'url'         => (string) get_post_meta( $company, 'rp_webbplats', true ),
					'description' => '<p>Vi söker en ' . strtolower( $title ) . ' till vår kund. Du är driven, strukturerad och trivs i en miljö med högt tempo.</p><p>Detta är en fiktiv demoannons.</p>',
					'location'    => ( is_array( $regions ) && $regions ) ? $regions[0] : 'Stockholm',
					'type'        => $types[ mt_rand( 0, 3 ) ], // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					'deadline'    => gmdate( 'Y-m-d', strtotime( '+' . mt_rand( 7, 60 ) . ' days' ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				)
			);
			if ( 'created' === $saved ) {
				++$job_count;
			}
		}
		foreach ( $ids as $id ) {
			RP_Jobs::update_count( $id );
		}

		// Reviews: each company gets a "true quality" so top/bottom lists differ meaningfully.
		$review_count = 0;
		$names        = array( 'Anna', 'Erik', 'Maria', 'Johan', 'Sara', 'Karl', 'Emma', 'Lars', 'Lina', 'Oskar', 'Anonym' );
		$roles        = array_keys( RP_Ratings::roles() );
		foreach ( $ids as $n => $id ) {
			$quality = 1.5 + ( ( $n * 7 ) % 20 ) / 6; // 1.5–4.7
			$reviews = 1 + ( $n * 3 ) % 5; // 1–5 per company
			if ( $n < 6 ) {
				$reviews = 6; // Make sure a few companies qualify for the top lists.
			}
			if ( $n >= 14 && $n < 18 ) {
				$reviews = 6;
			}
			for ( $r = 0; $r < $reviews && $review_count < 80; $r++ ) {
				$stars = (int) max( 1, min( 5, round( $quality + mt_rand( -10, 10 ) / 10 ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
				$texts = self::TEXTS[ $stars ];
				$cid   = wp_insert_comment(
					array(
						'comment_post_ID'      => $id,
						'comment_author'       => $names[ mt_rand( 0, count( $names ) - 1 ) ], // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
						'comment_author_email' => 'demo' . $id . '-' . $r . '@exempel.example',
						'comment_content'      => $texts[ mt_rand( 0, count( $texts ) - 1 ) ], // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
						'comment_type'         => RP_Ratings::TYPE,
						'comment_approved'     => 0,
						'comment_date'         => gmdate( 'Y-m-d H:i:s', strtotime( '-' . mt_rand( 1, 300 ) . ' days' ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
					)
				);
				if ( $cid ) {
					add_comment_meta( $cid, 'rp_betyg', $stars, true );
					add_comment_meta( $cid, 'rp_roll', $roles[ $r % count( $roles ) ], true );
					add_comment_meta( $cid, 'rp_demo', 1, true );
					wp_set_comment_status( $cid, 'approve' );
					++$review_count;
				}
			}
		}

		$first = $ids[0] ?? 0;
		if ( $first ) {
			add_comment_meta(
				(int) get_comments(
					array(
						'post_id' => $first,
						'type'    => RP_Ratings::TYPE,
						'number'  => 1,
						'fields'  => 'ids',
					)
				)[0],
				'rp_foretagssvar',
				'Tack för ditt omdöme! Vi är glada att du var nöjd och hoppas att vi hörs igen.',
				true
			);
		}

		RP_Ratings::recalc_all();

		return array(
			'companies' => count( $ids ),
			'jobs'      => $job_count,
			'reviews'   => $review_count,
		);
	}

	/**
	 * Remove demo data.
	 *
	 * @return int Number of companies removed.
	 */
	public static function delete(): int {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'rp_kalla', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $ids as $id ) {
			foreach ( get_posts(
				array(
					'post_type'      => RP_Post_Types::JOB,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'meta_key'       => 'rp_foretag_id',
					'meta_value'     => $id,
				)
			) as $job ) { // phpcs:ignore WordPress.DB.SlowDBQuery
				wp_delete_post( $job, true );
			}
			wp_delete_post( $id, true ); // Also deletes the company's comments (reviews).
		}
		RP_Ratings::recalc_all();
		return count( $ids );
	}
}
