<?php
/**
 * Archive filtering/sorting, top lists and rewrite rules.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query helpers.
 */
final class RP_Query {

	public const TOPLIST_PAGE = 'topplistor';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'pre_get_posts', array( self::class, 'pre_get_posts' ) );
		add_action( 'template_redirect', array( self::class, 'redirect_portal_singles' ) );
		add_filter( 'request', array( self::class, 'legacy_upgrade_links' ) );
	}

	/**
	 * Old "Uppgradera" links used ?foretag=<id> on a page. "foretag" is the company post type's
	 * query var, so WordPress looked for a company with that slug and returned 404. Send such
	 * requests to the page with ?uppgradera=<id> instead.
	 *
	 * @param array<string, mixed> $vars Query vars.
	 * @return array<string, mixed>
	 */
	public static function legacy_upgrade_links( $vars ): array {
		$vars = (array) $vars;
		if ( ! empty( $vars['pagename'] ) && isset( $vars[ RP_Post_Types::COMPANY ] ) && ctype_digit( (string) $vars[ RP_Post_Types::COMPANY ] ) && ! is_admin() ) {
			$id   = (int) $vars[ RP_Post_Types::COMPANY ];
			$page = get_page_by_path( (string) $vars['pagename'] );
			if ( $page ) {
				wp_safe_redirect( add_query_arg( 'uppgradera', $id, (string) get_permalink( $page ) ) . '#bestall', 301 );
				exit;
			}
		}
		return $vars;
	}

	/**
	 * Job portals have no page of their own – send visitors to the portal list.
	 */
	public static function redirect_portal_singles(): void {
		if ( is_singular( RP_Post_Types::PORTAL ) ) {
			wp_safe_redirect( (string) get_post_type_archive_link( RP_Post_Types::PORTAL ), 301 );
			exit;
		}
	}

	/**
	 * Sort options for the company list.
	 *
	 * @return array<string,string>
	 */
	public static function sort_options(): array {
		return array(
			'rekommenderat' => 'Rekommenderat',
			'betyg'         => 'Bäst betyg',
			'omdomen'       => 'Flest omdömen',
			'namn'          => 'A–Ö',
			'nyast'         => 'Nyast tillagda',
			'jobb'          => 'Flest lediga jobb',
		);
	}

	/**
	 * Indexable URLs for filtered top lists: /topplistor/{tjanst|bransch|region}/{term}/.
	 */
	public static function rewrite(): void {
		add_rewrite_rule(
			'^' . self::TOPLIST_PAGE . '/(tjanst|bransch|region)/([^/]+)/?$',
			'index.php?pagename=' . self::TOPLIST_PAGE . '&rp_lista_tax=$matches[1]&rp_lista_term=$matches[2]',
			'top'
		);
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ): array {
		return array_merge( $vars, array( 'sortera', 'min_betyg', 'q', 'anstallning', 'foretag_id', 'rp_lista_tax', 'rp_lista_term' ) );
	}

	/**
	 * Apply filters and sorting to the main query on company/job archives.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function pre_get_posts( WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}

		$is_company_list = $q->is_post_type_archive( RP_Post_Types::COMPANY ) || $q->is_tax( array( RP_Post_Types::TAX_SERVICE ) );
		$is_job_list     = $q->is_post_type_archive( RP_Post_Types::JOB );

		// Industry/region archives are shared by companies and jobs – show companies there.
		if ( $q->is_tax( array( RP_Post_Types::TAX_INDUSTRY, RP_Post_Types::TAX_REGION ) ) && ! $q->get( 'post_type' ) ) {
			$q->set( 'post_type', RP_Post_Types::COMPANY );
			$is_company_list = true;
		}

		if ( $is_company_list ) {
			self::apply_company_filters( $q );
		} elseif ( $is_job_list ) {
			self::apply_job_filters( $q );
		} elseif ( $q->is_post_type_archive( RP_Post_Types::PORTAL ) ) {
			$q->set( 'posts_per_page', -1 );
			$q->set(
				'orderby',
				array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				)
			);
		} elseif ( $q->is_search() && ! $q->get( 'post_type' ) ) {
			$q->set( 'post_type', array( RP_Post_Types::COMPANY, RP_Post_Types::JOB, 'page', 'post' ) );
			$q->set( 'posts_per_page', 50 );
		}
	}

	/**
	 * Company list filters. Used by the archive and by the REST endpoint.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function apply_company_filters( WP_Query $q ): void {
		if ( $q->is_main_query() ) {
			$q->set( 'posts_per_page', 24 );
		}

		$search = trim( (string) $q->get( 'q' ) );
		if ( '' !== $search ) {
			$q->set( 's', $search );
			$q->set( 'search_columns', array( 'post_title' ) );
		}

		$meta_query = array(
			'relation'      => 'AND',
			'utvald_clause' => array(
				'key'  => 'rp_utvald',
				'type' => 'NUMERIC',
			),
			'bayes_clause'  => array(
				'key'  => 'rp_bayes_betyg',
				'type' => 'DECIMAL(6,4)',
			),
		);

		$min = (float) $q->get( 'min_betyg' );
		if ( $min > 0 ) {
			$meta_query['min_clause'] = array(
				'key'     => 'rp_snittbetyg',
				'value'   => $min,
				'compare' => '>=',
				'type'    => 'DECIMAL(4,2)',
			);
		}

		$sort = (string) $q->get( 'sortera' );
		switch ( $sort ) {
			case 'betyg':
				$orderby = array(
					'bayes_clause' => 'DESC',
					'title'        => 'ASC',
				);
				break;
			case 'omdomen':
				$meta_query['count_clause'] = array(
					'key'  => 'rp_antal_betyg',
					'type' => 'NUMERIC',
				);
				$orderby                    = array(
					'count_clause' => 'DESC',
					'title'        => 'ASC',
				);
				break;
			case 'namn':
				$orderby = array( 'title' => 'ASC' );
				break;
			case 'nyast':
				$orderby = array( 'date' => 'DESC' );
				break;
			case 'jobb':
				$meta_query['jobs_clause'] = array(
					'key'  => 'rp_antal_jobb',
					'type' => 'NUMERIC',
				);
				$orderby                   = array(
					'jobs_clause' => 'DESC',
					'title'       => 'ASC',
				);
				break;
			default:
				$orderby = RP_Settings::get( 'lyft_sponsrade' )
					? array(
						'utvald_clause' => 'DESC',
						'bayes_clause'  => 'DESC',
						'title'         => 'ASC',
					)
					: array(
						'bayes_clause' => 'DESC',
						'title'        => 'ASC',
					);
		}

		$q->set( 'meta_query', $meta_query );
		$q->set( 'orderby', $orderby );
	}

	/**
	 * Job list filters.
	 *
	 * @param WP_Query $q Query.
	 */
	private static function apply_job_filters( WP_Query $q ): void {
		$q->set( 'posts_per_page', 30 );
		$meta = array();
		$type = sanitize_text_field( (string) $q->get( 'anstallning' ) );
		if ( '' !== $type ) {
			$meta[] = array(
				'key'   => 'rp_anstallningsform',
				'value' => $type,
			);
		}
		$company = absint( $q->get( 'foretag_id' ) );
		if ( $company ) {
			$meta[] = array(
				'key'   => 'rp_foretag_id',
				'value' => $company,
				'type'  => 'NUMERIC',
			);
		}
		$search = trim( (string) $q->get( 'q' ) );
		if ( '' !== $search ) {
			$q->set( 's', $search );
		}
		if ( $meta ) {
			$q->set( 'meta_query', $meta );
		}
	}

	/**
	 * Top list using the Bayesian weighted rating. Sponsorship never affects placement.
	 *
	 * @param int    $limit    Number of companies.
	 * @param string $taxonomy Optional taxonomy filter.
	 * @param string $term     Optional term slug.
	 * @return WP_Post[]
	 */
	public static function toplist( int $limit = 10, string $taxonomy = '', string $term = '' ): array {
		$args = array(
			'post_type'      => RP_Post_Types::COMPANY,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'no_found_rows'  => true,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'count_clause' => array(
					'key'     => 'rp_antal_betyg',
					'value'   => max( 1, (int) RP_Settings::get( 'min_omdomen' ) ),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
				'bayes_clause' => array(
					'key'  => 'rp_bayes_betyg',
					'type' => 'DECIMAL(6,4)',
				),
			),
			'orderby'        => array(
				'bayes_clause' => 'DESC',
				'count_clause' => 'DESC',
			),
		);
		if ( '' !== $taxonomy && '' !== $term && in_array( $taxonomy, array( RP_Post_Types::TAX_SERVICE, RP_Post_Types::TAX_INDUSTRY, RP_Post_Types::TAX_REGION ), true ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term,
				),
			);
		}
		return get_posts( $args );
	}

	/**
	 * URL to a filtered top list.
	 *
	 * @param string $taxonomy Taxonomy ('' for the main list).
	 * @param string $term     Term slug.
	 */
	public static function toplist_url( string $taxonomy = '', string $term = '' ): string {
		$base = home_url( '/' . self::TOPLIST_PAGE . '/' );
		if ( '' === $taxonomy || '' === $term ) {
			return $base;
		}
		return trailingslashit( $base . $taxonomy . '/' . $term );
	}

	/**
	 * Number of companies published in the last N days.
	 *
	 * @param int $days Days.
	 */
	public static function new_companies_count( int $days = 30 ): int {
		$q = new WP_Query(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'date_query'     => array( array( 'after' => $days . ' days ago' ) ),
			)
		);
		return (int) $q->found_posts;
	}
}
