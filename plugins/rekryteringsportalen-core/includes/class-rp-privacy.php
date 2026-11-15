<?php
/**
 * GDPR: personal data export/erasure for leads and package orders.
 *
 * Reviews are WordPress comments, so the core comment exporter/eraser already covers them.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy tools.
 */
final class RP_Privacy {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( self::class, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( self::class, 'erasers' ) );
		add_action( 'admin_init', array( self::class, 'policy_text' ) );
		add_action( 'rp_daily', array( self::class, 'purge_old_leads' ) );
	}

	/**
	 * Map of post type => e-mail meta key + exportable fields.
	 *
	 * @return array<string, array{email:string, label:string, fields:array<string,string>}>
	 */
	private static function sources(): array {
		$order_fields = array(
			'rp_order_foretag'    => 'Företag',
			'rp_order_orgnr'      => 'Org.nr',
			'rp_order_kontakt'    => 'Kontaktperson',
			'rp_order_epost'      => 'E-post',
			'rp_order_telefon'    => 'Telefon',
			'rp_order_meddelande' => 'Meddelande',
		);
		return array(
			RP_Post_Types::LEAD  => array(
				'email'  => 'rp_lead_epost',
				'label'  => 'Rekryteringsförfrågningar',
				'fields' => RP_Leads::fields(),
			),
			RP_Post_Types::ORDER => array(
				'email'  => 'rp_order_epost',
				'label'  => 'Paketbeställningar',
				'fields' => $order_fields,
			),
		);
	}

	/**
	 * Register exporters.
	 *
	 * @param array<string, array<string,mixed>> $exporters Exporters.
	 * @return array<string, array<string,mixed>>
	 */
	public static function exporters( $exporters ): array {
		$exporters['rekryteringsportalen'] = array(
			'exporter_friendly_name' => 'Rekryteringsportalen',
			'callback'               => array( self::class, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register erasers.
	 *
	 * @param array<string, array<string,mixed>> $erasers Erasers.
	 * @return array<string, array<string,mixed>>
	 */
	public static function erasers( $erasers ): array {
		$erasers['rekryteringsportalen'] = array(
			'eraser_friendly_name' => 'Rekryteringsportalen',
			'callback'             => array( self::class, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Posts of all sources belonging to an e-mail.
	 *
	 * @param string $email E-mail.
	 * @return array<int, array{post:WP_Post, source:array{email:string, label:string, fields:array<string,string>}, type:string}>
	 */
	private static function find( string $email ): array {
		$out = array();
		foreach ( self::sources() as $type => $source ) {
			$posts = get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => 'any',
					'posts_per_page' => 200,
					'meta_key'       => $source['email'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			foreach ( $posts as $p ) {
				$out[] = array(
					'post'   => $p,
					'source' => $source,
					'type'   => $type,
				);
			}
		}
		return $out;
	}

	/**
	 * Export.
	 *
	 * @param string $email E-mail.
	 * @return array{data:array<int,mixed>, done:bool}
	 */
	public static function export( $email ): array {
		$data = array();
		foreach ( self::find( (string) $email ) as $item ) {
			$fields = array();
			foreach ( $item['source']['fields'] as $key => $label ) {
				$fields[] = array(
					'name'  => $label,
					'value' => (string) get_post_meta( $item['post']->ID, $key, true ),
				);
			}
			$data[] = array(
				'group_id'    => 'rp-' . $item['type'],
				'group_label' => $item['source']['label'],
				'item_id'     => $item['type'] . '-' . $item['post']->ID,
				'data'        => $fields,
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase (deletes the lead/order posts).
	 *
	 * @param string $email E-mail.
	 * @return array{items_removed:bool, items_retained:bool, messages:string[], done:bool}
	 */
	public static function erase( $email ): array {
		$removed = false;
		foreach ( self::find( (string) $email ) as $item ) {
			$removed = (bool) wp_delete_post( $item['post']->ID, true ) || $removed;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Delete leads older than 24 months (filterable).
	 */
	public static function purge_old_leads(): void {
		$months = (int) apply_filters( 'rp_lead_retention_months', 24 );
		if ( $months <= 0 ) {
			return;
		}
		$old = get_posts(
			array(
				'post_type'      => RP_Post_Types::LEAD,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array( array( 'before' => $months . ' months ago' ) ),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy_text(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<h2>Omdömen</h2><p>När du lämnar ett omdöme sparar vi ditt betyg, din kommentar, det namn du anger, din roll och din e-postadress. E-postadressen visas aldrig publikt och används för att bekräfta omdömet och förhindra flera omdömen från samma person. Vi sparar inte din IP-adress.</p>'
			. '<h2>Rekryteringsförfrågningar (leads)</h2><p>När du skickar en förfrågan via "Hitta rekryterare" delas dina uppgifter med upp till tre rekryteringsföretag som matchar din bransch och region. Förfrågningar raderas automatiskt efter 24 månader.</p>'
			. '<h2>Paketbeställningar</h2><p>Uppgifter som lämnas vid beställning av annonspaket används för att hantera beställningen och fakturering.</p>';
		wp_add_privacy_policy_content( 'Rekryteringsportalen', wp_kses_post( $text ) );
	}
}
