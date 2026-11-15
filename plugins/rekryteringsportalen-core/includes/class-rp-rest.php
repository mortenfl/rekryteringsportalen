<?php
/**
 * REST API: namespace rp/v1.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST routes.
 */
final class RP_Rest {

	public const NS = 'rp/v1';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public static function routes(): void {
		register_rest_route(
			self::NS,
			'/foretag',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'list_companies' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'tjanst'    => array( 'type' => 'string' ),
					'bransch'   => array( 'type' => 'string' ),
					'region'    => array( 'type' => 'string' ),
					'min_betyg' => array(
						'type'    => 'number',
						'minimum' => 0,
						'maximum' => 5,
					),
					'q'         => array( 'type' => 'string' ),
					'sortera'   => array(
						'type' => 'string',
						'enum' => array_keys( RP_Query::sort_options() ),
					),
					'page'      => array(
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					),
					'per_page'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
						'default' => 24,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/topplista',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'toplist' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'antal'    => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 50,
						'default' => 10,
					),
					'taxonomi' => array(
						'type'    => 'string',
						'enum'    => array( '', 'tjanst', 'bransch', 'region' ),
						'default' => '',
					),
					'term'     => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'import' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'status'    => array(
						'type'    => 'string',
						'enum'    => array( 'draft', 'publish', 'pending' ),
						'default' => 'draft',
					),
					'overwrite' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'dry_run'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/foretag/(?P<id>\d+)/paket',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( self::class, 'set_package' ),
				'permission_callback' => static function ( WP_REST_Request $r ) {
					return current_user_can( 'edit_post', (int) $r['id'] );
				},
				'args'                => array(
					'paket' => array(
						'type'     => 'string',
						'enum'     => array_keys( RP_Packages::labels() ),
						'required' => true,
					),
					'till'  => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/klick/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'click' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Public company list.
	 *
	 * @param WP_REST_Request $r Request.
	 */
	public static function list_companies( WP_REST_Request $r ): WP_REST_Response {
		$args = array(
			'post_type'      => RP_Post_Types::COMPANY,
			'post_status'    => 'publish',
			'paged'          => (int) $r['page'],
			'posts_per_page' => (int) $r['per_page'],
		);
		foreach ( array( 'tjanst', 'bransch', 'region' ) as $tax ) {
			if ( ! empty( $r[ $tax ] ) ) {
				$args['tax_query'][] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'taxonomy' => $tax,
					'field'    => 'slug',
					'terms'    => sanitize_title( $r[ $tax ] ),
				);
			}
		}
		// Re-use the archive logic so the REST list and the HTML list always agree.
		$query             = new WP_Query();
		$query->query_vars = $args;
		foreach ( array( 'min_betyg', 'q', 'sortera' ) as $var ) {
			if ( isset( $r[ $var ] ) ) {
				$query->set( $var, $r[ $var ] );
			}
		}
		RP_Query::apply_company_filters( $query );
		$result = new WP_Query( $query->query_vars );

		$items = array_map( array( self::class, 'company_data' ), $result->posts );
		$res   = new WP_REST_Response( $items );
		$res->header( 'X-WP-Total', (string) $result->found_posts );
		$res->header( 'X-WP-TotalPages', (string) $result->max_num_pages );
		return $res;
	}

	/**
	 * Public representation of a company.
	 *
	 * @param WP_Post $p Company.
	 * @return array<string,mixed>
	 */
	public static function company_data( WP_Post $p ): array {
		$terms = static function ( string $tax ) use ( $p ): array {
			$t = get_the_terms( $p, $tax );
			return is_array( $t ) ? wp_list_pluck( $t, 'name' ) : array();
		};
		$logo  = (int) get_post_meta( $p->ID, 'rp_logo', true );
		return array(
			'id'               => $p->ID,
			'namn'             => get_the_title( $p ),
			'url'              => get_permalink( $p ),
			'logo'             => $logo ? wp_get_attachment_image_url( $logo, 'medium' ) : null,
			'ort'              => (string) get_post_meta( $p->ID, 'rp_ort', true ),
			'kort_beskrivning' => (string) get_post_meta( $p->ID, 'rp_kort_beskrivning', true ),
			'betyg'            => (float) get_post_meta( $p->ID, 'rp_snittbetyg', true ),
			'antal_omdomen'    => (int) get_post_meta( $p->ID, 'rp_antal_betyg', true ),
			'antal_jobb'       => (int) get_post_meta( $p->ID, 'rp_antal_jobb', true ),
			'utvald'           => RP_Packages::NONE !== RP_Packages::get( $p->ID ),
			'tjanster'         => $terms( RP_Post_Types::TAX_SERVICE ),
			'branscher'        => $terms( RP_Post_Types::TAX_INDUSTRY ),
			'regioner'         => $terms( RP_Post_Types::TAX_REGION ),
		);
	}

	/**
	 * Top/bottom list.
	 *
	 * @param WP_REST_Request $r Request.
	 */
	public static function toplist( WP_REST_Request $r ): WP_REST_Response {
		$posts = RP_Query::toplist( (int) $r['antal'], (string) $r['taxonomi'], sanitize_title( (string) $r['term'] ) );
		$out   = array();
		foreach ( $posts as $i => $p ) {
			$out[] = array(
				'placering'    => $i + 1,
				'viktat_betyg' => (float) get_post_meta( $p->ID, 'rp_bayes_betyg', true ),
			) + self::company_data( $p );
		}
		return new WP_REST_Response( $out );
	}

	/**
	 * Import companies. Body: a company object, an array of them, or {"companies":[...]}.
	 *
	 * @param WP_REST_Request $r Request.
	 */
	public static function import( WP_REST_Request $r ): WP_REST_Response {
		$parsed = RP_Importer::parse_json( (string) $r->get_body() );
		if ( ! $parsed['rows'] ) {
			return new WP_REST_Response( array( 'message' => 'Inga företag i anropet.' ), 400 );
		}
		$mapping = RP_Importer::guess_mapping( $parsed['headers'] );
		$rows    = array_map(
			static function ( $row ) use ( $mapping ) {
				return RP_Importer::map_row( $row, $mapping );
			},
			$parsed['rows']
		);
		$summary = RP_Importer::import_rows(
			$rows,
			array(
				'status'    => (string) $r['status'],
				'overwrite' => (bool) $r['overwrite'],
				'dry_run'   => (bool) $r['dry_run'],
			),
			'rest'
		);
		return new WP_REST_Response( $summary, 200 );
	}

	/**
	 * Set package.
	 *
	 * @param WP_REST_Request $r Request.
	 */
	public static function set_package( WP_REST_Request $r ): WP_REST_Response {
		$ok = RP_Packages::set( (int) $r['id'], (string) $r['paket'], (string) $r['till'] );
		return new WP_REST_Response(
			array(
				'ok'    => $ok,
				'paket' => RP_Packages::get( (int) $r['id'] ),
			),
			$ok ? 200 : 400
		);
	}

	/**
	 * Count an outbound click on a job portal (rate limited per visitor).
	 *
	 * @param WP_REST_Request $r Request.
	 */
	public static function click( WP_REST_Request $r ): WP_REST_Response {
		$id = (int) $r['id'];
		if ( RP_Post_Types::PORTAL !== get_post_type( $id ) ) {
			return new WP_REST_Response( null, 404 );
		}
		$key = 'rp_klick_' . md5( $id . RP_Forms::client_fingerprint() );
		if ( ! get_transient( $key ) ) {
			set_transient( $key, 1, 10 * MINUTE_IN_SECONDS );
			update_post_meta( $id, 'rp_klick', (int) get_post_meta( $id, 'rp_klick', true ) + 1 );
		}
		return new WP_REST_Response( null, 204 );
	}
}
