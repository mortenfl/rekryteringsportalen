<?php
/**
 * Activation: terms, pages, job portals, cron.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class RP_Installer {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 20 );
	}

	/**
	 * Activation.
	 */
	public static function activate(): void {
		RP_Post_Types::register();
		RP_Query::rewrite();
		self::seed_terms();
		self::create_pages();
		self::seed_portals();
		self::schedule();
		self::backfill_company_meta();
		update_option( 'rp_core_version', RP_CORE_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Make sure every company has the computed meta used for sorting (e.g. after re-activation).
	 */
	public static function backfill_company_meta(): void {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			RP_Post_Types::ensure_company_defaults( (int) $id );
		}
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate(): void {
		foreach ( array( 'rp_daily', 'rp_import_jobs', 'rp_recalc_bayes' ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		flush_rewrite_rules();
	}

	/**
	 * Re-run idempotent setup after plugin updates.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'rp_core_version' ) !== RP_CORE_VERSION ) {
			self::seed_terms();
			self::create_pages();
			self::schedule();
			update_option( 'rp_core_version', RP_CORE_VERSION );
			flush_rewrite_rules();
			self::purge_page_cache();
		}
		self::schedule();
	}

	/**
	 * Empty full-page caches so visitors see the new forms/markup right after an update.
	 * Supports LiteSpeed Cache, WP Rocket, W3 Total Cache and WP Super Cache when present.
	 */
	public static function purge_page_cache(): void {
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}

	/**
	 * Cron events.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( 'rp_daily' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:15' ), 'daily', 'rp_daily' );
		}
		if ( ! wp_next_scheduled( 'rp_import_jobs' ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'rp_two_hours', 'rp_import_jobs' );
		}
	}

	/**
	 * Default terms.
	 */
	public static function seed_terms(): void {
		$services   = array( 'Rekrytering', 'Bemanning', 'Executive search', 'Headhunting', 'Interimslösningar', 'RPO', 'Tester & assessment', 'Konsultuthyrning', 'Outplacement', 'Employer branding' );
		$industries = array( 'IT & Tech', 'Ekonomi & Finans', 'Teknik & Ingenjör', 'Vård & Omsorg', 'Bygg & Fastighet', 'Försäljning & Marknad', 'HR', 'Juridik', 'Industri & Produktion', 'Logistik & Transport', 'Offentlig sektor', 'Life Science', 'Handel & Service', 'Kontor & Administration', 'Chef & Ledning' );
		$regions    = array(
			'Stockholms län'       => array( 'Stockholm' ),
			'Uppsala län'          => array( 'Uppsala' ),
			'Södermanlands län'    => array( 'Eskilstuna' ),
			'Östergötlands län'    => array( 'Linköping', 'Norrköping' ),
			'Jönköpings län'       => array( 'Jönköping' ),
			'Kronobergs län'       => array( 'Växjö' ),
			'Kalmar län'           => array( 'Kalmar' ),
			'Gotlands län'         => array( 'Visby' ),
			'Blekinge län'         => array( 'Karlskrona' ),
			'Skåne län'            => array( 'Malmö', 'Helsingborg', 'Lund' ),
			'Hallands län'         => array( 'Halmstad' ),
			'Västra Götalands län' => array( 'Göteborg', 'Borås' ),
			'Värmlands län'        => array( 'Karlstad' ),
			'Örebro län'           => array( 'Örebro' ),
			'Västmanlands län'     => array( 'Västerås' ),
			'Dalarnas län'         => array( 'Falun' ),
			'Gävleborgs län'       => array( 'Gävle' ),
			'Västernorrlands län'  => array( 'Sundsvall' ),
			'Jämtlands län'        => array( 'Östersund' ),
			'Västerbottens län'    => array( 'Umeå' ),
			'Norrbottens län'      => array( 'Luleå' ),
			'Hela Sverige'         => array(),
			'Distans'              => array(),
		);

		foreach ( $services as $name ) {
			if ( ! term_exists( $name, RP_Post_Types::TAX_SERVICE ) ) {
				wp_insert_term( $name, RP_Post_Types::TAX_SERVICE );
			}
		}
		foreach ( $industries as $name ) {
			if ( ! term_exists( $name, RP_Post_Types::TAX_INDUSTRY ) ) {
				wp_insert_term( $name, RP_Post_Types::TAX_INDUSTRY );
			}
		}
		foreach ( $regions as $county => $cities ) {
			$parent = term_exists( $county, RP_Post_Types::TAX_REGION );
			if ( ! $parent ) {
				$parent = wp_insert_term( $county, RP_Post_Types::TAX_REGION );
			}
			if ( is_wp_error( $parent ) ) {
				continue;
			}
			foreach ( $cities as $city ) {
				if ( ! term_exists( $city, RP_Post_Types::TAX_REGION ) ) {
					wp_insert_term( $city, RP_Post_Types::TAX_REGION, array( 'parent' => (int) $parent['term_id'] ) );
				}
			}
		}
	}

	/**
	 * Pages the theme expects. Stored in option rp_pages (slug => ID).
	 *
	 * @return array<string, array{title:string, content:string}>
	 */
	public static function pages(): array {
		return array(
			'topplistor'          => array(
				'title'   => 'Topplistor',
				'content' => '<!-- wp:paragraph --><p>Här ser du de rekryteringsföretag som fått bäst betyg av kandidater och kunder. Listan uppdateras automatiskt när nya omdömen godkänns.</p><!-- /wp:paragraph -->',
			),
			'hitta-rekryterare'   => array(
				'title'   => 'Hitta rekryterare',
				'content' => '<!-- wp:paragraph --><p>Berätta vem ni behöver rekrytera så kopplar vi ihop er med upp till tre rekryteringsföretag som är specialiserade på er bransch och region.</p><!-- /wp:paragraph -->',
			),
			'for-foretag'         => array(
				'title'   => 'För rekryteringsföretag',
				'content' => '<!-- wp:paragraph --><p>Syns där kandidater och kunder jämför rekryteringsföretag. Välj det paket som passar er.</p><!-- /wp:paragraph -->',
			),
			'lagg-till-foretag'   => array(
				'title'   => 'Lägg till ditt företag',
				'content' => '<!-- wp:paragraph --><p>Saknas ert rekryterings- eller bemanningsföretag på Rekryteringsportalen? Fyll i formuläret så lägger vi till er – grundpresentationen är alltid gratis.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[rp_anmal_foretag]<!-- /wp:shortcode -->',
			),
			'riktlinjer-omdomen'  => array(
				'title'   => 'Riktlinjer för omdömen',
				'content' => '<!-- wp:heading --><h2>Så skriver du ett bra omdöme</h2><!-- /wp:heading --><!-- wp:list --><ul><li>Berätta om din egen upplevelse – som kandidat, kund eller anställd.</li><li>Var saklig och konkret. Beskriv vad som var bra och vad som kunde varit bättre.</li><li>Nämn inte privatpersoner vid namn och skriv inga personangrepp.</li><li>Ett omdöme per person och företag.</li><li>Lämna inte omdömen om ditt eget företag eller en konkurrent.</li></ul><!-- /wp:list --><!-- wp:paragraph --><p>Alla omdömen granskas innan de publiceras. Omdömen som bryter mot riktlinjerna publiceras inte. Ser du ett omdöme som bryter mot riktlinjerna kan du rapportera det.</p><!-- /wp:paragraph -->',
			),
			'sa-fungerar-betygen' => array(
				'title'   => 'Så fungerar betygen',
				'content' => '<!-- wp:paragraph --><p>Betyget sätts på en skala från 1 till 5 stjärnor, där 1 är sämst och 5 är bäst. Varje omdöme granskas manuellt innan det publiceras, och samma besökare kan bara lämna ett omdöme per företag och kvartal.</p><!-- /wp:paragraph --><!-- wp:heading --><h2>Topplistorna</h2><!-- /wp:heading --><!-- wp:paragraph --><p>För att ett företag med ett enda omdöme inte ska hamna överst använder vi ett viktat betyg. Det väger samman företagets snittbetyg med snittet för alla företag, och ju fler omdömen ett företag har desto mer räknas dess eget snitt. Bara företag med tillräckligt många omdömen kommer med i listorna.</p><!-- /wp:paragraph --><!-- wp:heading --><h2>Annonser påverkar inte betygen</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Rekryteringsföretag kan köpa annonspaket för en utökad presentation eller för att få förfrågningar. Sådana företag märks tydligt som annonsörer. Placering i topplistorna och betyg kan aldrig köpas.</p><!-- /wp:paragraph -->',
			),
		);
	}

	/**
	 * Create pages that do not exist yet.
	 */
	public static function create_pages(): void {
		$ids = get_option( 'rp_pages', array() );
		$ids = is_array( $ids ) ? $ids : array();
		foreach ( self::pages() as $slug => $page ) {
			if ( ! empty( $ids[ $slug ] ) && get_post( (int) $ids[ $slug ] ) ) {
				continue;
			}
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$ids[ $slug ] = $existing->ID;
				continue;
			}
			$ids[ $slug ] = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $page['title'],
					'post_name'    => $slug,
					'post_content' => $page['content'],
				)
			);
		}
		update_option( 'rp_pages', $ids );
	}

	/**
	 * Default job portals.
	 *
	 * @return array<int, array{0:string, 1:string, 2:string, 3:string}> name, url, category, description
	 */
	public static function portals(): array {
		return array(
			array( 'LinkedIn Jobs', 'https://www.linkedin.com/jobs/', 'Allmän', 'Världens största professionella nätverk med jobbannonser inom alla branscher.' ),
			array( 'Platsbanken (Arbetsförmedlingen)', 'https://arbetsformedlingen.se/platsbanken/', 'Allmän', 'Sveriges största samling av lediga jobb, från Arbetsförmedlingen.' ),
			array( 'Jobbsafari', 'https://jobbsafari.se/', 'Allmän', 'Svensk jobbsökmotor som samlar annonser från många källor.' ),
			array( 'Indeed', 'https://se.indeed.com/', 'Allmän', 'Global jobbsökmotor med ett stort utbud av svenska annonser.' ),
			array( 'StepStone', 'https://www.stepstone.se/', 'Chef', 'Jobb för tjänstemän, specialister och chefer.' ),
			array( 'Monster', 'https://www.monster.se/', 'Allmän', 'Internationell jobbsajt med svenska annonser.' ),
			array( 'Academic Work', 'https://www.academicwork.se/jobb', 'Student', 'Jobb för studenter och nyexaminerade akademiker.' ),
			array( 'Jobbland', 'https://www.jobbland.se/', 'Allmän', 'Lediga jobb i hela Sverige.' ),
			array( 'Ingenjörsjobb', 'https://www.ingenjorsjobb.se/', 'Tech', 'Jobb för ingenjörer och tekniker.' ),
			array( 'Offentliga Jobb', 'https://www.offentligajobb.se/', 'Offentlig sektor', 'Lediga jobb i kommuner, regioner och myndigheter.' ),
			array( 'Glassdoor', 'https://www.glassdoor.se/', 'Allmän', 'Jobb, löner och arbetsgivarrecensioner.' ),
		);
	}

	/**
	 * Create job portals if none exist.
	 */
	public static function seed_portals(): void {
		$existing = get_posts(
			array(
				'post_type'      => RP_Post_Types::PORTAL,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $existing ) {
			return;
		}
		foreach ( self::portals() as $i => [ $name, $url, $cat, $desc ] ) {
			$id = wp_insert_post(
				array(
					'post_type'   => RP_Post_Types::PORTAL,
					'post_status' => 'publish',
					'post_title'  => $name,
					'menu_order'  => $i,
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, 'rp_url', $url );
				update_post_meta( $id, 'rp_kategori', $cat );
				update_post_meta( $id, 'rp_beskrivning', $desc );
			}
		}
	}
}
