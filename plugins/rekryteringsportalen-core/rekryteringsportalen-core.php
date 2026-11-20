<?php
/**
 * Plugin Name:       Rekryteringsportalen Core
 * Plugin URI:        https://www.rekryteringsportalen.se
 * Description:       Data model and features for Rekryteringsportalen: recruitment companies, reviews, jobs, job portals, packages, leads and import.
 * Version:           1.3.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Rekryteringsportalen
 * License:           GPL-2.0-or-later
 * Text Domain:       rekryteringsportalen-core
 * Domain Path:       /languages
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_CORE_VERSION', '1.3.1' );
define( 'RP_CORE_FILE', __FILE__ );
define( 'RP_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RP_CORE_URL', plugin_dir_url( __FILE__ ) );

// Pure logic (no WordPress dependencies) – also loaded by the unit tests.
require_once RP_CORE_DIR . 'includes/class-rp-rating-math.php';
require_once RP_CORE_DIR . 'includes/class-rp-normalizer.php';
require_once RP_CORE_DIR . 'includes/class-rp-lead-matcher.php';

require_once RP_CORE_DIR . 'includes/class-rp-settings.php';
require_once RP_CORE_DIR . 'includes/class-rp-post-types.php';
require_once RP_CORE_DIR . 'includes/class-rp-packages.php';
require_once RP_CORE_DIR . 'includes/class-rp-forms.php';
require_once RP_CORE_DIR . 'includes/class-rp-ratings.php';
require_once RP_CORE_DIR . 'includes/class-rp-importer.php';
require_once RP_CORE_DIR . 'includes/class-rp-import-undo.php';
require_once RP_CORE_DIR . 'includes/class-rp-jobs.php';
require_once RP_CORE_DIR . 'includes/class-rp-leads.php';
require_once RP_CORE_DIR . 'includes/class-rp-orders.php';
require_once RP_CORE_DIR . 'includes/class-rp-query.php';
require_once RP_CORE_DIR . 'includes/class-rp-rest.php';
require_once RP_CORE_DIR . 'includes/class-rp-seo.php';
require_once RP_CORE_DIR . 'includes/class-rp-privacy.php';
require_once RP_CORE_DIR . 'includes/class-rp-installer.php';
require_once RP_CORE_DIR . 'includes/class-rp-seeder.php';
require_once RP_CORE_DIR . 'includes/template-functions.php';

if ( is_admin() ) {
	require_once RP_CORE_DIR . 'includes/admin/class-rp-admin-companies.php';
	require_once RP_CORE_DIR . 'includes/admin/class-rp-admin-import.php';
	require_once RP_CORE_DIR . 'includes/admin/class-rp-admin-reviews.php';
	require_once RP_CORE_DIR . 'includes/admin/class-rp-admin-settings.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RP_CORE_DIR . 'includes/class-rp-cli.php';
}

register_activation_hook( __FILE__, array( 'RP_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RP_Installer', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'rekryteringsportalen-core', false, dirname( plugin_basename( RP_CORE_FILE ) ) . '/languages' );

		RP_Post_Types::init();
		RP_Packages::init();
		RP_Ratings::init();
		RP_Jobs::init();
		RP_Leads::init();
		RP_Orders::init();
		RP_Query::init();
		RP_Rest::init();
		RP_SEO::init();
		RP_Privacy::init();
		RP_Installer::init();
		RP_Forms::init();

		if ( is_admin() ) {
			RP_Admin_Companies::init();
			RP_Admin_Import::init();
			RP_Admin_Reviews::init();
			RP_Admin_Settings::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'rp', 'RP_CLI' );
		}
	}
);
