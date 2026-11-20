<?php
/**
 * Settings page: Inställningar → Rekryteringsportalen.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings admin.
 */
final class RP_Admin_Settings {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'update_option_' . RP_Settings::OPTION, array( self::class, 'after_save' ), 10, 2 );
	}

	/**
	 * Menu.
	 */
	public static function menu(): void {
		add_options_page( 'Rekryteringsportalen', 'Rekryteringsportalen', 'manage_options', 'rp-settings', array( self::class, 'render' ) );
	}

	/**
	 * Register setting.
	 */
	public static function register(): void {
		register_setting(
			'rp_settings_group',
			RP_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => RP_Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize.
	 *
	 * @param mixed $input Input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$d     = RP_Settings::defaults();
		return array(
			'min_omdomen'        => max( 1, min( 100, absint( $input['min_omdomen'] ?? $d['min_omdomen'] ) ) ),
			'max_lead_mottagare' => max( 1, min( 20, absint( $input['max_lead_mottagare'] ?? $d['max_lead_mottagare'] ) ) ),
			'pris_paket1'        => sanitize_text_field( (string) ( $input['pris_paket1'] ?? $d['pris_paket1'] ) ),
			'pris_paket2'        => sanitize_text_field( (string) ( $input['pris_paket2'] ?? $d['pris_paket2'] ) ),
			'beskrivning_paket1' => sanitize_textarea_field( (string) ( $input['beskrivning_paket1'] ?? $d['beskrivning_paket1'] ) ),
			'beskrivning_paket2' => sanitize_textarea_field( (string) ( $input['beskrivning_paket2'] ?? $d['beskrivning_paket2'] ) ),
			'notis_epost'        => sanitize_email( (string) ( $input['notis_epost'] ?? '' ) ),
			'krav_epost'         => empty( $input['krav_epost'] ) ? 0 : 1,
			'lyft_sponsrade'     => empty( $input['lyft_sponsrade'] ) ? 0 : 1,
			'omdomen_per_timme'  => max( 1, min( 100, absint( $input['omdomen_per_timme'] ?? $d['omdomen_per_timme'] ) ) ),
		);
	}

	/**
	 * Recalculate weighted ratings when m changes.
	 *
	 * @param mixed $old_value Old.
	 * @param mixed $new_value New.
	 */
	public static function after_save( $old_value, $new_value ): void {
		RP_Installer::purge_page_cache(); // Forms depend on these settings (e.g. e-mail field).
		if ( ( $old_value['min_omdomen'] ?? null ) !== ( $new_value['min_omdomen'] ?? null ) ) {
			RP_Ratings::recalc_all();
		}
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		$s    = RP_Settings::all();
		$name = static fn( string $k ): string => RP_Settings::OPTION . '[' . $k . ']';
		?>
		<div class="wrap">
			<h1>Rekryteringsportalen</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'rp_settings_group' ); ?>
				<h2>Betyg och topplistor</h2>
				<table class="form-table"><tbody>
					<tr><th><label for="rp-min">Minsta antal omdömen (m)</label></th><td><input id="rp-min" type="number" min="1" max="100" name="<?php echo esc_attr( $name( 'min_omdomen' ) ); ?>" value="<?php echo (int) $s['min_omdomen']; ?>" class="small-text">
						<p class="description">Krävs för att komma med i topp 10 och används som vikt i det Bayesianska betyget. Nuvarande snitt för alla företag (C): <?php echo esc_html( (string) get_option( 'rp_global_avg', '–' ) ); ?></p></td></tr>
					<tr><th>E-post för omdömen</th><td><label><input type="checkbox" name="<?php echo esc_attr( $name( 'krav_epost' ) ); ?>" value="1" <?php checked( $s['krav_epost'] ); ?>> Kräv e-post och bekräftelse via e-postlänk</label><p class="description">Avstängt (standard): e-post är valfritt och omdömet går direkt till granskning. Samma besökare kan inte betygsätta samma företag igen inom <?php echo (int) RP_Ratings::REPEAT_DAYS; ?> dagar.</p></td></tr>
					<tr><th><label for="rp-rate">Max omdömen per besökare och timme</label></th><td><input id="rp-rate" type="number" min="1" max="100" name="<?php echo esc_attr( $name( 'omdomen_per_timme' ) ); ?>" value="<?php echo (int) $s['omdomen_per_timme']; ?>" class="small-text"></td></tr>
				</tbody></table>
				<h2>Paket</h2>
				<table class="form-table"><tbody>
					<tr><th><label for="rp-p1">Pris Paket 1 (kr/mån)</label></th><td><input id="rp-p1" name="<?php echo esc_attr( $name( 'pris_paket1' ) ); ?>" value="<?php echo esc_attr( $s['pris_paket1'] ); ?>"></td></tr>
					<tr><th><label for="rp-d1">Beskrivning Paket 1</label></th><td><textarea id="rp-d1" class="large-text" rows="2" name="<?php echo esc_attr( $name( 'beskrivning_paket1' ) ); ?>"><?php echo esc_textarea( $s['beskrivning_paket1'] ); ?></textarea></td></tr>
					<tr><th><label for="rp-p2">Pris Paket 2 (kr/mån)</label></th><td><input id="rp-p2" name="<?php echo esc_attr( $name( 'pris_paket2' ) ); ?>" value="<?php echo esc_attr( $s['pris_paket2'] ); ?>"></td></tr>
					<tr><th><label for="rp-d2">Beskrivning Paket 2</label></th><td><textarea id="rp-d2" class="large-text" rows="2" name="<?php echo esc_attr( $name( 'beskrivning_paket2' ) ); ?>"><?php echo esc_textarea( $s['beskrivning_paket2'] ); ?></textarea></td></tr>
					<tr><th>Företagslistan</th><td><label><input type="checkbox" name="<?php echo esc_attr( $name( 'lyft_sponsrade' ) ); ?>" value="1" <?php checked( $s['lyft_sponsrade'] ); ?>> Visa företag med paket först i standardsorteringen (märks "Annons"). Påverkar aldrig topplistorna.</label></td></tr>
				</tbody></table>
				<h2>Leads och notiser</h2>
				<table class="form-table"><tbody>
					<tr><th><label for="rp-max">Max mottagare per lead</label></th><td><input id="rp-max" type="number" min="1" max="20" name="<?php echo esc_attr( $name( 'max_lead_mottagare' ) ); ?>" value="<?php echo (int) $s['max_lead_mottagare']; ?>" class="small-text"></td></tr>
					<tr><th><label for="rp-mail">E-post för notiser</label></th><td><input id="rp-mail" type="email" class="regular-text" name="<?php echo esc_attr( $name( 'notis_epost' ) ); ?>" value="<?php echo esc_attr( $s['notis_epost'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"><p class="description">Nya omdömen, rapporter, leads och beställningar. Tomt = webbplatsens admin-e-post.</p></td></tr>
				</tbody></table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
