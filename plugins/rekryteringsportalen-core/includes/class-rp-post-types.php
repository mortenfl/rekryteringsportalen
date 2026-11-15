<?php
/**
 * Custom post types, taxonomies, statuses and meta.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the data model.
 */
final class RP_Post_Types {

	public const COMPANY = 'foretag';
	public const JOB     = 'jobb';
	public const PORTAL  = 'jobbportal';
	public const LEAD    = 'lead';
	public const ORDER   = 'paketorder';

	public const TAX_SERVICE  = 'tjanst';
	public const TAX_INDUSTRY = 'bransch';
	public const TAX_REGION   = 'region';

	public const STATUS_EXPIRED = 'utgangen';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 5 );
		add_action( 'save_post_' . self::COMPANY, array( self::class, 'ensure_company_defaults' ), 99 );
	}

	/**
	 * Register everything.
	 */
	public static function register(): void {
		self::register_post_types();
		self::register_taxonomies();
		self::register_meta();

		register_post_status(
			self::STATUS_EXPIRED,
			array(
				'label'                     => _x( 'Utgången', 'post status', 'rekryteringsportalen-core' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of posts. */
				'label_count'               => _n_noop( 'Utgångna <span class="count">(%s)</span>', 'Utgångna <span class="count">(%s)</span>', 'rekryteringsportalen-core' ),
			)
		);
	}

	/**
	 * Post types.
	 */
	private static function register_post_types(): void {
		register_post_type(
			self::COMPANY,
			array(
				'labels'        => self::labels( 'Rekryteringsföretag', 'Rekryteringsföretag', 'företag' ),
				'public'        => true,
				'has_archive'   => 'foretag',
				'rewrite'       => array(
					'slug'       => 'foretag',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-businessperson',
				'menu_position' => 20,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'custom-fields' ),
				'show_in_rest'  => true,
				'rest_base'     => 'foretag',
				'taxonomies'    => array( self::TAX_SERVICE, self::TAX_INDUSTRY, self::TAX_REGION ),
			)
		);

		register_post_type(
			self::JOB,
			array(
				'labels'        => self::labels( 'Lediga jobb', 'Jobb', 'jobb' ),
				'public'        => true,
				'has_archive'   => 'jobb',
				'rewrite'       => array(
					'slug'       => 'jobb',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-id-alt',
				'menu_position' => 21,
				'supports'      => array( 'title', 'editor', 'custom-fields' ),
				'show_in_rest'  => true,
				'taxonomies'    => array( self::TAX_INDUSTRY, self::TAX_REGION ),
			)
		);

		register_post_type(
			self::PORTAL,
			array(
				'labels'        => self::labels( 'Jobbportaler', 'Jobbportal', 'jobbportal' ),
				'public'        => true,
				'has_archive'   => 'jobbportaler',
				'rewrite'       => array(
					'slug'       => 'jobbportaler',
					'with_front' => false,
				),
				'menu_icon'     => 'dashicons-admin-links',
				'menu_position' => 22,
				'supports'      => array( 'title', 'thumbnail', 'page-attributes', 'custom-fields' ),
				'show_in_rest'  => true,
			)
		);

		register_post_type(
			self::LEAD,
			array(
				'labels'          => self::labels( 'Leads', 'Lead', 'lead' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::COMPANY,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'    => true,
			)
		);

		register_post_type(
			self::ORDER,
			array(
				'labels'          => self::labels( 'Paketbeställningar', 'Paketbeställning', 'beställning' ),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'edit.php?post_type=' . self::COMPANY,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Taxonomies.
	 */
	private static function register_taxonomies(): void {
		$common = array(
			'public'            => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => true,
		);

		register_taxonomy(
			self::TAX_SERVICE,
			array( self::COMPANY ),
			$common + array(
				'labels'       => self::tax_labels( 'Tjänster', 'Tjänst' ),
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'       => 'tjanst',
					'with_front' => false,
				),
				'query_var'    => 'tjanst',
			)
		);

		register_taxonomy(
			self::TAX_INDUSTRY,
			array( self::COMPANY, self::JOB ),
			$common + array(
				'labels'       => self::tax_labels( 'Branscher', 'Bransch' ),
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'       => 'bransch',
					'with_front' => false,
				),
				'query_var'    => 'bransch',
			)
		);

		register_taxonomy(
			self::TAX_REGION,
			array( self::COMPANY, self::JOB ),
			$common + array(
				'labels'       => self::tax_labels( 'Regioner', 'Region' ),
				'hierarchical' => true,
				'rewrite'      => array(
					'slug'       => 'region',
					'with_front' => false,
				),
				'query_var'    => 'region',
			)
		);
	}

	/**
	 * Company field definitions shared by the admin meta box, importer and REST.
	 *
	 * Key = import/CSV column name. `meta` = post meta key.
	 *
	 * @return array<string, array{label:string, type:string, meta:string, group:string, help?:string}>
	 */
	public static function company_fields(): array {
		return array(
			'orgnr'            => array(
				'label' => 'Organisationsnummer',
				'type'  => 'text',
				'meta'  => 'rp_orgnr',
				'group' => 'bas',
			),
			'webbplats'        => array(
				'label' => 'Webbplats',
				'type'  => 'url',
				'meta'  => 'rp_webbplats',
				'group' => 'bas',
			),
			'kort_beskrivning' => array(
				'label' => 'Kort beskrivning (max 300 tecken)',
				'type'  => 'textarea',
				'meta'  => 'rp_kort_beskrivning',
				'group' => 'bas',
			),
			'epost'            => array(
				'label' => 'E-post',
				'type'  => 'email',
				'meta'  => 'rp_epost',
				'group' => 'kontakt',
			),
			'telefon'          => array(
				'label' => 'Telefon',
				'type'  => 'text',
				'meta'  => 'rp_telefon',
				'group' => 'kontakt',
			),
			'adress'           => array(
				'label' => 'Adress',
				'type'  => 'text',
				'meta'  => 'rp_adress',
				'group' => 'kontakt',
			),
			'ort'              => array(
				'label' => 'Ort',
				'type'  => 'text',
				'meta'  => 'rp_ort',
				'group' => 'kontakt',
			),
			'linkedin'         => array(
				'label' => 'LinkedIn',
				'type'  => 'url',
				'meta'  => 'rp_linkedin',
				'group' => 'kontakt',
			),
			'antal_anstallda'  => array(
				'label' => 'Antal anställda',
				'type'  => 'text',
				'meta'  => 'rp_antal_anstallda',
				'group' => 'fakta',
				'help'  => 'Intervall, t.ex. 10–49',
			),
			'grundat'          => array(
				'label' => 'Grundat (år)',
				'type'  => 'int',
				'meta'  => 'rp_grundat',
				'group' => 'fakta',
			),
			'karriarsida_url'  => array(
				'label' => 'Karriärsida (URL)',
				'type'  => 'url',
				'meta'  => 'rp_karriarsida_url',
				'group' => 'jobb',
			),
			'jobbfeed_url'     => array(
				'label' => 'Jobbfeed (RSS/Atom/JSON)',
				'type'  => 'url',
				'meta'  => 'rp_jobbfeed_url',
				'group' => 'jobb',
				'help'  => 'Importeras automatiskt varannan timme.',
			),
			'leads_epost'      => array(
				'label' => 'E-post för leads',
				'type'  => 'email',
				'meta'  => 'rp_leads_epost',
				'group' => 'paket',
				'help'  => 'Används för paket 2. Tomt = företagets e-post.',
			),
			'video_url'        => array(
				'label' => 'Video (YouTube/Vimeo-URL)',
				'type'  => 'url',
				'meta'  => 'rp_video_url',
				'group' => 'utokad',
			),
			'hero'             => array(
				'label' => 'Omslagsbild',
				'type'  => 'attachment',
				'meta'  => 'rp_hero',
				'group' => 'utokad',
			),
			'galleri'          => array(
				'label' => 'Bildgalleri',
				'type'  => 'gallery',
				'meta'  => 'rp_galleri',
				'group' => 'utokad',
			),
			'team'             => array(
				'label' => 'Team / kontaktpersoner',
				'type'  => 'lines',
				'meta'  => 'rp_team',
				'group' => 'utokad',
				'help'  => 'En person per rad: Namn | Roll | E-post | Bild-ID eller bild-URL',
			),
			'kundcase'         => array(
				'label' => 'Kundcase / referenser',
				'type'  => 'lines',
				'meta'  => 'rp_kundcase',
				'group' => 'utokad',
				'help'  => 'Ett case per rad: Rubrik | Text | Kund (valfritt)',
			),
			'kalla'            => array(
				'label' => 'Källa',
				'type'  => 'text',
				'meta'  => 'rp_kalla',
				'group' => 'intern',
				'help'  => 'Var företaget upptäcktes (import, tips, manuellt ...)',
			),
		);
	}

	/**
	 * Register post meta (exposed in REST).
	 */
	private static function register_meta(): void {
		$auth = static function () {
			return current_user_can( 'edit_posts' );
		};

		foreach ( self::company_fields() as $field ) {
			$type = match ( $field['type'] ) {
				'int', 'attachment' => 'integer',
				default             => 'string',
			};
			register_post_meta(
				self::COMPANY,
				$field['meta'],
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => 'intern' !== $field['group'],
					'auth_callback' => $auth,
				)
			);
		}

		$computed = array(
			'rp_logo'               => 'integer',
			'rp_paket'              => 'string',
			'rp_paket_giltigt_till' => 'string',
			'rp_snittbetyg'         => 'number',
			'rp_antal_betyg'        => 'integer',
			'rp_bayes_betyg'        => 'number',
			'rp_antal_jobb'         => 'integer',
			'rp_utvald'             => 'integer',
			'rp_verifierad'         => 'boolean',
		);
		foreach ( $computed as $key => $type ) {
			register_post_meta(
				self::COMPANY,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}

		$job_meta = array(
			'rp_foretag_id'          => 'integer',
			'rp_ort'                 => 'string',
			'rp_anstallningsform'    => 'string',
			'rp_sista_ansokningsdag' => 'string',
			'rp_ansok_url'           => 'string',
			'rp_extern_id'           => 'string',
		);
		foreach ( $job_meta as $key => $type ) {
			register_post_meta(
				self::JOB,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}

		foreach ( array(
			'rp_url'         => 'string',
			'rp_beskrivning' => 'string',
			'rp_kategori'    => 'string',
			'rp_klick'       => 'integer',
		) as $key => $type ) {
			register_post_meta(
				self::PORTAL,
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
	}

	/**
	 * Make sure computed meta exists so meta-based ordering never drops a company.
	 *
	 * @param int $post_id Company ID.
	 */
	public static function ensure_company_defaults( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$defaults = array(
			'rp_paket'       => 'inget',
			'rp_snittbetyg'  => 0,
			'rp_antal_betyg' => 0,
			'rp_bayes_betyg' => 0,
			'rp_antal_jobb'  => 0,
			'rp_utvald'      => 0,
		);
		foreach ( $defaults as $key => $value ) {
			if ( ! metadata_exists( 'post', $post_id, $key ) ) {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}

	/**
	 * Job portal categories.
	 *
	 * @return string[]
	 */
	public static function portal_categories(): array {
		return array( 'Allmän', 'Tech', 'Chef', 'Student', 'Offentlig sektor', 'Frilans' );
	}

	/**
	 * Employment types.
	 *
	 * @return string[]
	 */
	public static function employment_types(): array {
		return array( 'Tillsvidare', 'Visstid', 'Konsultuppdrag', 'Interim', 'Deltid', 'Sommarjobb' );
	}

	/**
	 * Post type labels.
	 *
	 * @param string $plural   Plural.
	 * @param string $singular Singular.
	 * @param string $lower    Lowercase singular for sentences.
	 * @return array<string, string>
	 */
	private static function labels( string $plural, string $singular, string $lower ): array {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'add_new'            => 'Lägg till',
			/* translators: %s: item name. */
			'add_new_item'       => sprintf( 'Lägg till %s', $lower ),
			/* translators: %s: item name. */
			'edit_item'          => sprintf( 'Redigera %s', $lower ),
			/* translators: %s: item name. */
			'new_item'           => sprintf( 'Ny %s', $lower ),
			/* translators: %s: item name. */
			'view_item'          => sprintf( 'Visa %s', $lower ),
			'search_items'       => 'Sök',
			'not_found'          => 'Inga hittades',
			'not_found_in_trash' => 'Inga i papperskorgen',
			'all_items'          => $plural,
		);
	}

	/**
	 * Taxonomy labels.
	 *
	 * @param string $plural   Plural.
	 * @param string $singular Singular.
	 * @return array<string, string>
	 */
	private static function tax_labels( string $plural, string $singular ): array {
		return array(
			'name'          => $plural,
			'singular_name' => $singular,
			'search_items'  => 'Sök',
			'all_items'     => 'Alla',
			'edit_item'     => 'Redigera',
			'add_new_item'  => 'Lägg till ny',
			'not_found'     => 'Inga hittades',
		);
	}
}
