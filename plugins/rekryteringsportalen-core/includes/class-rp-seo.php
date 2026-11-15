<?php
/**
 * Structured data (JSON-LD), breadcrumbs and fallback meta descriptions.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO.
 */
final class RP_SEO {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'head' ), 5 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'sitemap_post_types' ) );
	}

	/**
	 * Whether a dedicated SEO plugin handles meta descriptions.
	 */
	public static function seo_plugin_active(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
	}

	/**
	 * Keep private types out of sitemaps.
	 *
	 * @param array<string, WP_Post_Type> $types Types.
	 * @return array<string, WP_Post_Type>
	 */
	public static function sitemap_post_types( $types ): array {
		unset( $types[ RP_Post_Types::LEAD ], $types[ RP_Post_Types::ORDER ] );
		return $types;
	}

	/**
	 * Output meta description and JSON-LD.
	 */
	public static function head(): void {
		if ( ! self::seo_plugin_active() ) {
			$desc = self::description();
			if ( '' !== $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
		}

		/**
		 * Whether to print Rekryteringsportalen JSON-LD.
		 *
		 * @param bool $enabled Enabled.
		 */
		if ( ! apply_filters( 'rp_output_schema', true ) ) {
			return;
		}

		$graph = array();
		if ( is_singular( RP_Post_Types::COMPANY ) ) {
			$graph[] = self::company_schema( (int) get_queried_object_id() );
		} elseif ( is_singular( RP_Post_Types::JOB ) ) {
			$job = self::job_schema( (int) get_queried_object_id() );
			if ( $job ) {
				$graph[] = $job;
			}
		}
		$crumbs = self::breadcrumbs();
		if ( count( $crumbs ) > 1 ) {
			$items = array();
			foreach ( $crumbs as $i => $c ) {
				$items[] = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $c['name'],
					'item'     => $c['url'],
				);
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $items,
			);
		}
		if ( $graph ) {
			echo '<script type="application/ld+json">' . wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			) . '</script>' . "\n";
		}
	}

	/**
	 * Fallback meta description.
	 */
	public static function description(): string {
		if ( is_singular( RP_Post_Types::COMPANY ) ) {
			$id    = (int) get_queried_object_id();
			$avg   = (float) get_post_meta( $id, 'rp_snittbetyg', true );
			$count = (int) get_post_meta( $id, 'rp_antal_betyg', true );
			$short = (string) get_post_meta( $id, 'rp_kort_beskrivning', true );
			$text  = get_the_title( $id ) . ': ' . ( $count ? sprintf( 'betyg %s av 5 baserat på %d omdömen. ', number_format_i18n( $avg, 1 ), $count ) : 'läs och lämna omdömen. ' ) . $short;
			return wp_trim_words( $text, 30, '…' );
		}
		if ( is_singular( RP_Post_Types::JOB ) ) {
			$id      = (int) get_queried_object_id();
			$company = (int) get_post_meta( $id, 'rp_foretag_id', true );
			return wp_trim_words( get_the_title( $id ) . ( $company ? ' hos ' . get_the_title( $company ) : '' ) . '. ' . wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) ), 30, '…' );
		}
		if ( is_post_type_archive( RP_Post_Types::COMPANY ) ) {
			return 'Jämför rekryteringsföretag i Sverige. Se betyg och omdömen från kandidater och kunder, vilka tjänster de erbjuder och vilka jobb de har ute.';
		}
		if ( is_post_type_archive( RP_Post_Types::JOB ) ) {
			return 'Lediga jobb hos Sveriges rekryteringsföretag. Filtrera på bransch, region och anställningsform.';
		}
		if ( is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				return $term->description ? wp_trim_words( wp_strip_all_tags( $term->description ), 30, '…' ) : sprintf( 'Rekryteringsföretag inom %s – jämför betyg, omdömen och lediga jobb.', $term->name );
			}
		}
		if ( is_front_page() ) {
			return (string) get_bloginfo( 'description' );
		}
		return '';
	}

	/**
	 * Company JSON-LD.
	 *
	 * @param int $id Company.
	 * @return array<string,mixed>
	 */
	public static function company_schema( int $id ): array {
		$data = array(
			'@type' => 'EmploymentAgency',
			'@id'   => get_permalink( $id ) . '#foretag',
			'name'  => get_the_title( $id ),
			'url'   => get_permalink( $id ),
		);
		$site = (string) get_post_meta( $id, 'rp_webbplats', true );
		if ( $site ) {
			$data['sameAs'] = array_values( array_filter( array( $site, (string) get_post_meta( $id, 'rp_linkedin', true ) ) ) );
		}
		$desc = (string) get_post_meta( $id, 'rp_kort_beskrivning', true );
		if ( $desc ) {
			$data['description'] = $desc;
		}
		$logo = (int) get_post_meta( $id, 'rp_logo', true );
		if ( $logo ) {
			$data['logo'] = wp_get_attachment_image_url( $logo, 'medium' );
		}
		$phone = (string) get_post_meta( $id, 'rp_telefon', true );
		if ( $phone ) {
			$data['telephone'] = $phone;
		}
		$city = (string) get_post_meta( $id, 'rp_ort', true );
		if ( $city ) {
			$data['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => (string) get_post_meta( $id, 'rp_adress', true ),
					'addressLocality' => $city,
					'addressCountry'  => 'SE',
				)
			);
		}
		$count = (int) get_post_meta( $id, 'rp_antal_betyg', true );
		if ( $count > 0 ) {
			$data['aggregateRating'] = array(
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) get_post_meta( $id, 'rp_snittbetyg', true ),
				'reviewCount' => $count,
				'bestRating'  => 5,
				'worstRating' => 1,
			);
			$data['review']          = array();
			foreach ( RP_Ratings::get_reviews( $id, 5 ) as $review ) {
				$data['review'][] = array(
					'@type'         => 'Review',
					'author'        => array(
						'@type' => 'Person',
						'name'  => $review->comment_author,
					),
					'datePublished' => mysql2date( 'Y-m-d', $review->comment_date ),
					'reviewBody'    => wp_trim_words( $review->comment_content, 60, '…' ),
					'reviewRating'  => array(
						'@type'       => 'Rating',
						'ratingValue' => (int) get_comment_meta( $review->comment_ID, 'rp_betyg', true ),
						'bestRating'  => 5,
						'worstRating' => 1,
					),
				);
			}
		}
		return $data;
	}

	/**
	 * JobPosting JSON-LD.
	 *
	 * @param int $id Job.
	 * @return array<string,mixed>|null
	 */
	public static function job_schema( int $id ): ?array {
		$company = (int) get_post_meta( $id, 'rp_foretag_id', true );
		$content = (string) get_post_field( 'post_content', $id );
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return null; // Google requires a description.
		}
		$type_map = array(
			'Tillsvidare'    => 'FULL_TIME',
			'Visstid'        => 'TEMPORARY',
			'Konsultuppdrag' => 'CONTRACTOR',
			'Interim'        => 'CONTRACTOR',
			'Deltid'         => 'PART_TIME',
			'Sommarjobb'     => 'TEMPORARY',
		);
		$data     = array(
			'@type'              => 'JobPosting',
			'title'              => get_the_title( $id ),
			'description'        => wpautop( $content ),
			'datePosted'         => get_the_date( 'Y-m-d', $id ),
			'hiringOrganization' => array(
				'@type'  => 'Organization',
				'name'   => $company ? get_the_title( $company ) : get_bloginfo( 'name' ),
				'sameAs' => $company ? (string) get_post_meta( $company, 'rp_webbplats', true ) : home_url( '/' ),
			),
			'jobLocation'        => array(
				'@type'   => 'Place',
				'address' => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => (string) get_post_meta( $id, 'rp_ort', true ),
					'addressCountry'  => 'SE',
				),
			),
		);
		$deadline = (string) get_post_meta( $id, 'rp_sista_ansokningsdag', true );
		if ( $deadline ) {
			$data['validThrough'] = $deadline . 'T23:59:59';
		}
		$type = (string) get_post_meta( $id, 'rp_anstallningsform', true );
		if ( isset( $type_map[ $type ] ) ) {
			$data['employmentType'] = $type_map[ $type ];
		}
		return $data;
	}

	/**
	 * Breadcrumb trail for the current request.
	 *
	 * @return array<int, array{name:string, url:string}>
	 */
	public static function breadcrumbs(): array {
		$trail = array(
			array(
				'name' => 'Start',
				'url'  => home_url( '/' ),
			),
		);
		if ( is_front_page() ) {
			return $trail;
		}

		$archive = static function ( string $type ) use ( &$trail ): void {
			$obj = get_post_type_object( $type );
			if ( $obj ) {
				$trail[] = array(
					'name' => $obj->labels->name,
					'url'  => (string) get_post_type_archive_link( $type ),
				);
			}
		};

		if ( is_singular( array( RP_Post_Types::COMPANY, RP_Post_Types::JOB ) ) ) {
			$id = (int) get_queried_object_id();
			$archive( (string) get_post_type( $id ) );
			if ( RP_Post_Types::JOB === get_post_type( $id ) ) {
				$company = (int) get_post_meta( $id, 'rp_foretag_id', true );
				if ( $company ) {
					$trail[] = array(
						'name' => get_the_title( $company ),
						'url'  => (string) get_permalink( $company ),
					);
				}
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => (string) get_permalink( $id ),
			);
		} elseif ( is_post_type_archive() ) {
			$archive( (string) get_query_var( 'post_type' ) );
		} elseif ( is_tax() ) {
			$term = get_queried_object();
			$archive( RP_Post_Types::COMPANY );
			if ( $term instanceof WP_Term ) {
				$trail[] = array(
					'name' => $term->name,
					'url'  => (string) get_term_link( $term ),
				);
			}
		} elseif ( is_page() ) {
			$id = (int) get_queried_object_id();
			foreach ( array_reverse( get_post_ancestors( $id ) ) as $ancestor ) {
				$trail[] = array(
					'name' => get_the_title( $ancestor ),
					'url'  => (string) get_permalink( $ancestor ),
				);
			}
			$trail[] = array(
				'name' => get_the_title( $id ),
				'url'  => (string) get_permalink( $id ),
			);
			$tax     = (string) get_query_var( 'rp_lista_tax' );
			$term    = $tax ? get_term_by( 'slug', (string) get_query_var( 'rp_lista_term' ), $tax ) : false;
			if ( $term ) {
				$trail[] = array(
					'name' => $term->name,
					'url'  => RP_Query::toplist_url( $tax, $term->slug ),
				);
			}
		} elseif ( is_search() ) {
			$trail[] = array(
				'name' => 'Sökresultat',
				'url'  => get_search_link(),
			);
		}
		return $trail;
	}
}
