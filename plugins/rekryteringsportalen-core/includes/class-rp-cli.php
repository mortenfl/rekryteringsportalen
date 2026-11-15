<?php
/**
 * WP-CLI commands: `wp rp ...`.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage Rekryteringsportalen.
 */
class RP_CLI {

	/**
	 * Import companies from a CSV or JSON file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a .csv or .json file. Column names: see data/import-mall.csv.
	 *
	 * [--status=<status>]
	 * : Status for new companies.
	 * ---
	 * default: draft
	 * options:
	 *   - draft
	 *   - publish
	 *   - pending
	 * ---
	 *
	 * [--overwrite]
	 * : Overwrite existing values (default: only fill empty fields).
	 *
	 * [--dry-run]
	 * : Show what would happen without saving.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rp import foretag.csv --status=draft --dry-run
	 *     wp rp import foretag.json --status=publish --overwrite
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string,string> $assoc_args Named.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( "Kan inte läsa filen: $file" );
		}
		$parsed  = RP_Importer::parse_file( $file );
		$mapping = RP_Importer::guess_mapping( $parsed['headers'] );
		$ignored = array_keys( array_filter( $mapping, static fn( $v ) => '' === $v ) );
		if ( $ignored ) {
			WP_CLI::warning( 'Ignorerade kolumner: ' . implode( ', ', $ignored ) );
		}
		if ( ! in_array( 'namn', $mapping, true ) ) {
			WP_CLI::error( 'Filen saknar en kolumn för företagsnamn (namn).' );
		}
		$rows = array_map( static fn( $r ) => RP_Importer::map_row( $r, $mapping ), $parsed['rows'] );

		$summary = RP_Importer::import_rows(
			$rows,
			array(
				'status'    => $assoc_args['status'] ?? 'draft',
				'overwrite' => isset( $assoc_args['overwrite'] ),
				'dry_run'   => isset( $assoc_args['dry-run'] ),
			),
			'cli'
		);

		WP_CLI\Utils\format_items( 'table', $summary['rows'], array( 'row', 'namn', 'action', 'id', 'message' ) );
		$prefix = isset( $assoc_args['dry-run'] ) ? 'Torrkörning: ' : '';
		WP_CLI::success( sprintf( '%s%d skapade, %d uppdaterade, %d hoppade över, %d fel.', $prefix, $summary['created'], $summary['updated'], $summary['skipped'], $summary['errors'] ) );
	}

	/**
	 * Set a company's package.
	 *
	 * ## OPTIONS
	 *
	 * <company>
	 * : Company ID or organisation number.
	 *
	 * <package>
	 * : inget, paket1 or paket2.
	 *
	 * [--till=<date>]
	 * : End date (YYYY-MM-DD). The package resets automatically after this date.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rp set-paket 123 paket1
	 *     wp rp set-paket 556677-8899 paket2 --till=2027-01-31
	 *
	 * @subcommand set-paket
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string,string> $assoc_args Named.
	 */
	public function set_paket( array $args, array $assoc_args ): void {
		[ $company, $paket ] = $args;
		$id                  = ctype_digit( $company ) && RP_Post_Types::COMPANY === get_post_type( (int) $company ) ? (int) $company : RP_Importer::find_existing( array( 'orgnr' => $company ) )['id'];
		if ( ! $id ) {
			WP_CLI::error( "Hittade inget företag: $company" );
		}
		$paket = RP_Normalizer::package( $paket );
		RP_Packages::set( $id, $paket, $assoc_args['till'] ?? '' );
		WP_CLI::success( sprintf( '%s har nu %s%s.', get_the_title( $id ), RP_Packages::labels()[ $paket ], ! empty( $assoc_args['till'] ) ? ' till ' . $assoc_args['till'] : '' ) );
	}

	/**
	 * List companies with their package and rating.
	 *
	 * ## OPTIONS
	 *
	 * [--paket=<package>]
	 * : Filter by package.
	 *
	 * [--format=<format>]
	 * : table, csv, json.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string,string> $assoc_args Named.
	 */
	public function list_( array $args, array $assoc_args ): void {
		$query = array(
			'post_type'      => RP_Post_Types::COMPANY,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! empty( $assoc_args['paket'] ) ) {
			$query['meta_key']   = 'rp_paket'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query['meta_value'] = RP_Normalizer::package( $assoc_args['paket'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}
		$items = array();
		foreach ( get_posts( $query ) as $p ) {
			$items[] = array(
				'id'      => $p->ID,
				'namn'    => $p->post_title,
				'status'  => $p->post_status,
				'orgnr'   => get_post_meta( $p->ID, 'rp_orgnr', true ),
				'paket'   => RP_Packages::get( $p->ID ),
				'till'    => get_post_meta( $p->ID, 'rp_paket_giltigt_till', true ),
				'betyg'   => get_post_meta( $p->ID, 'rp_snittbetyg', true ),
				'omdomen' => get_post_meta( $p->ID, 'rp_antal_betyg', true ),
				'jobb'    => get_post_meta( $p->ID, 'rp_antal_jobb', true ),
			);
		}
		WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $items, array_keys( $items[0] ?? array( 'id' => 0 ) ) );
	}

	/**
	 * Recalculate all ratings and weighted (Bayesian) scores.
	 */
	public function recalc(): void {
		RP_Ratings::recalc_all();
		WP_CLI::success( 'Betygen är omräknade. Sajtens snitt: ' . get_option( 'rp_global_avg' ) );
	}

	/**
	 * Import jobs from all company feeds now.
	 *
	 * @subcommand import-jobb
	 */
	public function import_jobb(): void {
		foreach ( RP_Jobs::import_all_feeds() as $id => $res ) {
			$line = sprintf( '%s: %d nya, %d uppdaterade', get_the_title( $id ), $res['created'], $res['updated'] );
			'' !== $res['error'] ? WP_CLI::warning( $line . ' – ' . $res['error'] ) : WP_CLI::log( $line );
		}
		WP_CLI::success( 'Klart.' );
	}

	/**
	 * Create demo data (20 fictional companies, 30 jobs, reviews). Use --delete to remove it.
	 *
	 * ## OPTIONS
	 *
	 * [--delete]
	 * : Remove demo data instead.
	 *
	 * @subcommand seed-demo
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string,string> $assoc_args Named.
	 */
	public function seed_demo( array $args, array $assoc_args ): void {
		if ( isset( $assoc_args['delete'] ) ) {
			WP_CLI::success( sprintf( '%d demoföretag borttagna.', RP_Seeder::delete() ) );
			return;
		}
		RP_Installer::seed_terms();
		RP_Installer::seed_portals();
		$res = RP_Seeder::seed();
		WP_CLI::success( sprintf( 'Skapade %d företag, %d jobb och %d omdömen.', $res['companies'], $res['jobs'], $res['reviews'] ) );
	}
}
