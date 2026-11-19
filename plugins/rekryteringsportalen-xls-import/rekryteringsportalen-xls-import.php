<?php
/**
 * Plugin Name:       Rekryteringsportalen XLS-import
 * Plugin URI:        https://www.rekryteringsportalen.se
 * Description:       Importera rekryteringsföretag från Excel-filer (.xlsx, .xlsm och .xls). Använder samma kolumnkoppling, förhandsgranskning och dubblettkontroll som den vanliga importen.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Requires Plugins:  rekryteringsportalen-core
 * Author:            Rekryteringsportalen
 * License:           GPL-2.0-or-later
 * Text Domain:       rekryteringsportalen-xls-import
 *
 * @package RekryteringsportalenXlsImport
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_XLS_VERSION', '1.1.0' );
define( 'RP_XLS_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-rp-spreadsheet.php';
require_once __DIR__ . '/includes/class-rp-xlsx-reader.php';
require_once __DIR__ . '/includes/class-rp-xls-reader.php';

/**
 * WordPress integration.
 */
final class RP_Xls_Import {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		if ( ! class_exists( 'RP_Importer' ) ) {
			add_action( 'admin_notices', array( self::class, 'missing_core_notice' ) );
			return;
		}
		add_filter( 'rp_import_extensions', array( self::class, 'extensions' ) );
		add_filter( 'rp_import_parse_file', array( self::class, 'parse' ), 10, 4 );
		add_filter( 'rp_import_file_help', array( self::class, 'help' ) );
		add_action( 'rp_import_upload_fields', array( self::class, 'upload_fields' ) );
		add_filter( 'upload_mimes', array( self::class, 'mimes' ) );
	}

	/**
	 * Notice when the core plugin is not active.
	 */
	public static function missing_core_notice(): void {
		if ( current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>Rekryteringsportalen XLS-import</strong> kräver pluginet <strong>Rekryteringsportalen Core</strong>. Aktivera det först.</p></div>';
		}
	}

	/**
	 * Add Excel extensions to the importer.
	 *
	 * @param string[] $ext Extensions.
	 * @return string[]
	 */
	public static function extensions( $ext ): array {
		return array_merge( (array) $ext, RP_Spreadsheet::EXTENSIONS );
	}

	/**
	 * Allow Excel files where WordPress checks MIME types.
	 *
	 * @param array<string,string> $mimes MIME types.
	 * @return array<string,string>
	 */
	public static function mimes( $mimes ): array {
		$mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
		$mimes['xlsm'] = 'application/vnd.ms-excel.sheet.macroEnabled.12';
		$mimes['xls']  = 'application/vnd.ms-excel';
		return $mimes;
	}

	/**
	 * Parse Excel files for the importer.
	 *
	 * @param mixed                $parsed  Previous result (null = unhandled).
	 * @param string               $path    File path.
	 * @param string               $ext     Extension.
	 * @param array<string, mixed> $options Options (sheet).
	 * @return mixed
	 */
	public static function parse( $parsed, $path, $ext, $options = array() ) {
		if ( null !== $parsed || ! in_array( $ext, RP_Spreadsheet::EXTENSIONS, true ) ) {
			return $parsed;
		}
		try {
			$table = RP_Spreadsheet::read( (string) $path, (string) $ext, (string) ( $options['sheet'] ?? '' ) );
		} catch ( Throwable $e ) {
			return new WP_Error( 'rp_xls', $e->getMessage() );
		}
		if ( ! $table['rows'] ) {
			return new WP_Error( 'rp_xls_empty', sprintf( 'Hittade inga rader under rubrikraden i %s. Första raden med innehåll används som kolumnrubriker.', $table['info'] ) );
		}
		return $table;
	}

	/**
	 * Help text under the file field.
	 */
	public static function help(): string {
		return 'Excel (.xlsx, .xls), CSV (semikolon, komma eller tab) eller JSON. Första raden med innehåll används som kolumnrubriker.';
	}

	/**
	 * Sheet field on the upload step.
	 */
	public static function upload_fields(): void {
		?>
		<tr>
			<th><label for="rp-sheet">Excel-flik</label></th>
			<td>
				<input type="text" id="rp-sheet" name="rp_sheet" class="regular-text" placeholder="T.ex. Blad1 eller 2">
				<p class="description">Valfritt. Namn eller nummer på fliken. Tomt = första fliken som innehåller data.
				Ladda ner mall: <a href="<?php echo esc_url( RP_XLS_URL . 'data/import-mall.xlsx' ); ?>" download>Excel (.xlsx)</a> eller <a href="<?php echo esc_url( RP_XLS_URL . 'data/import-mall.xls' ); ?>" download>Excel 97–2003 (.xls)</a>.</p>
			</td>
		</tr>
		<?php
	}
}

add_action( 'plugins_loaded', array( 'RP_Xls_Import', 'init' ), 20 );
