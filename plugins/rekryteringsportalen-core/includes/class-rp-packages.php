<?php
/**
 * Advertising packages: none / paket1 (extended presentation) / paket2 (leads).
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Package logic.
 */
final class RP_Packages {

	public const NONE     = 'inget';
	public const EXTENDED = 'paket1';
	public const LEADS    = 'paket2';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rp_daily', array( self::class, 'expire_packages' ) );
	}

	/**
	 * Package labels.
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::NONE     => 'Inget paket',
			self::EXTENDED => 'Paket 1 – Utökad presentation',
			self::LEADS    => 'Paket 2 – Leads',
		);
	}

	/**
	 * Short labels for badges.
	 *
	 * @return array<string, string>
	 */
	public static function short_labels(): array {
		return array(
			self::NONE     => 'Inget',
			self::EXTENDED => 'Paket 1',
			self::LEADS    => 'Paket 2',
		);
	}

	/**
	 * Effective package for a company (respects the end date even before cron has run).
	 *
	 * @param int $company_id Company ID.
	 */
	public static function get( int $company_id ): string {
		$paket = (string) get_post_meta( $company_id, 'rp_paket', true );
		if ( ! isset( self::labels()[ $paket ] ) ) {
			return self::NONE;
		}
		$until = (string) get_post_meta( $company_id, 'rp_paket_giltigt_till', true );
		if ( self::NONE !== $paket && '' !== $until && $until < current_time( 'Y-m-d' ) ) {
			return self::NONE;
		}
		return $paket;
	}

	/**
	 * Whether the company has the extended presentation (package 1 or 2).
	 *
	 * Package 2 includes everything in package 1. Filter `rp_package_includes_extended` to change.
	 *
	 * @param int $company_id Company ID.
	 */
	public static function has_extended( int $company_id ): bool {
		$paket = self::get( $company_id );
		if ( self::EXTENDED === $paket ) {
			return true;
		}
		return self::LEADS === $paket && (bool) apply_filters( 'rp_package_includes_extended', true, $company_id );
	}

	/**
	 * Whether the company receives leads.
	 *
	 * @param int $company_id Company ID.
	 */
	public static function receives_leads( int $company_id ): bool {
		return self::LEADS === self::get( $company_id );
	}

	/**
	 * Set package. The single entry point used by admin, quick/bulk edit, orders, import, CLI and REST.
	 *
	 * @param int    $company_id Company ID.
	 * @param string $paket      inget|paket1|paket2.
	 * @param string $until      Optional end date (Y-m-d). '' = no end date.
	 */
	public static function set( int $company_id, string $paket, string $until = '' ): bool {
		if ( RP_Post_Types::COMPANY !== get_post_type( $company_id ) || ! isset( self::labels()[ $paket ] ) ) {
			return false;
		}
		$old   = self::get( $company_id );
		$until = RP_Normalizer::date( $until );

		update_post_meta( $company_id, 'rp_paket', $paket );
		update_post_meta( $company_id, 'rp_paket_giltigt_till', self::NONE === $paket ? '' : $until );
		update_post_meta( $company_id, 'rp_utvald', self::NONE === $paket ? 0 : 1 );
		delete_post_meta( $company_id, 'rp_paket_varnad' );

		if ( $old !== $paket ) {
			/**
			 * Fires when a company's package changes.
			 *
			 * @param int    $company_id Company.
			 * @param string $paket      New package.
			 * @param string $old        Previous package.
			 */
			do_action( 'rp_package_changed', $company_id, $paket, $old );
		}
		return true;
	}

	/**
	 * Daily: reset expired packages and warn admin 7 days before expiry.
	 */
	public static function expire_packages(): void {
		$today = current_time( 'Y-m-d' );
		$warn  = gmdate( 'Y-m-d', strtotime( $today . ' +7 days' ) );

		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => 'rp_paket',
						'value'   => self::NONE,
						'compare' => '!=',
					),
					array(
						'key'     => 'rp_paket_giltigt_till',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);

		$warnings = array();
		foreach ( $ids as $id ) {
			$until = (string) get_post_meta( $id, 'rp_paket_giltigt_till', true );
			if ( $until < $today ) {
				self::set( $id, self::NONE );
			} elseif ( $until <= $warn && ! get_post_meta( $id, 'rp_paket_varnad', true ) ) {
				$warnings[] = sprintf( '%s – %s går ut %s', get_the_title( $id ), self::short_labels()[ self::get( $id ) ], $until );
				update_post_meta( $id, 'rp_paket_varnad', 1 );
			}
		}

		if ( $warnings ) {
			wp_mail(
				RP_Settings::notify_email(),
				'[Rekryteringsportalen] Paket som går ut inom 7 dagar',
				"Följande paket går snart ut:\n\n" . implode( "\n", $warnings ) . "\n\n" . admin_url( 'edit.php?post_type=foretag&rp_paket=paket1' )
			);
		}
	}
}
