<?php
/**
 * Settings accessor.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in the `rp_settings` option.
 */
final class RP_Settings {

	public const OPTION = 'rp_settings';

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'min_omdomen'        => 5,
			'max_lead_mottagare' => 3,
			'pris_paket1'        => '2 495',
			'pris_paket2'        => '4 995',
			'beskrivning_paket1' => 'En utökad företagspresentation med bilder, video, team, kundcase, kontaktformulär och möjlighet att svara på omdömen.',
			'beskrivning_paket2' => 'Allt i paket 1, plus förfrågningar (leads) från företag som ska rekrytera inom er bransch och region.',
			'notis_epost'        => '',
			'krav_bekraftelse'   => 1,
			'lyft_sponsrade'     => 1,
			'omdomen_per_timme'  => 3,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	/**
	 * Address that receives admin notifications.
	 */
	public static function notify_email(): string {
		$email = (string) self::get( 'notis_epost' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}
}
