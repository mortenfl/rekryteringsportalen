<?php
/**
 * Integration check of import + undo against a real WordPress install with this plugin active.
 * Run on a throwaway site only (it creates and trashes companies):
 *   php import-undo-check.php /path/to/wordpress
 *
 * @package RekryteringsportalenCore
 */
$_SERVER['HTTP_HOST'] = 'localhost'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['SERVER_NAME'] = 'localhost';
require $argv[1] . '/wp-load.php';
wp_set_current_user( 1 );

$fail = 0;
function check( $cond, $label ) { global $fail; echo ( $cond ? "  ok   " : "  FAIL " ), $label, "\n"; if ( ! $cond ) { $fail++; } }
function company( $name ) { $q = get_posts( array( 'post_type' => 'foretag', 'post_status' => 'any', 'title' => $name, 'fields' => 'ids', 'posts_per_page' => 1 ) ); return $q ? (int) $q[0] : 0; }

// 1. An existing company with a review.
$existing = wp_insert_post( array( 'post_type' => 'foretag', 'post_status' => 'publish', 'post_title' => 'Befintlig Rekrytering AB', 'post_content' => '<p>Original text</p>' ) );
RP_Importer::save_fields( $existing, array( 'orgnr' => '556677-1111', 'ort' => 'Malmö', 'kort_beskrivning' => 'Originalbeskrivning', 'webbplats' => 'befintlig.example' ), true );
wp_set_object_terms( $existing, RP_Importer::ensure_terms( array( 'Bemanning' ), 'tjanst' ), 'tjanst' );
RP_Packages::set( $existing, 'paket1', '2027-06-30' );
$c = wp_insert_comment( array( 'comment_post_ID' => $existing, 'comment_type' => 'rp_omdome', 'comment_content' => 'Bra bemötande hela vägen igenom.', 'comment_approved' => 1, 'comment_author' => 'A' ) );
add_comment_meta( $c, 'rp_betyg', 4 );
$before_existing = RP_Import_Undo::snapshot( $existing );

// 2. Import: 3 new companies + an update (overwrite) of the existing one, which also appears twice.
$rows = array(
	array( 'namn' => 'Ny Alfa AB', 'orgnr' => '559000-0001', 'ort' => 'Stockholm', 'tjanster' => 'Rekrytering', 'paket' => 'paket1' ),
	array( 'namn' => 'Ny Beta AB', 'orgnr' => '559000-0002', 'ort' => 'Göteborg' ),
	array( 'namn' => 'Ny Gamma AB', 'orgnr' => '559000-0003', 'ort' => 'Umeå' ),
	array( 'namn' => 'Befintlig Rekrytering AB', 'orgnr' => '5566771111', 'ort' => 'Lund', 'kort_beskrivning' => 'Ny beskrivning', 'tjanster' => 'Headhunting', 'paket' => 'inget' ),
	array( 'namn' => 'Befintlig Rekrytering', 'webbplats' => 'https://www.befintlig.example', 'ort' => 'Ystad' ), // same company via domain
);
$sum = RP_Importer::import_rows( $rows, array( 'status' => 'publish', 'overwrite' => true ), 'test' );
echo "Import: {$sum['created']} skapade, {$sum['updated']} uppdaterade, batch {$sum['batch']}\n";
check( RP_Import_Undo::is_valid_batch( $sum['batch'] ), 'import gets a valid batch id' );
check( 3 === $sum['created'] && 2 === $sum['updated'], '3 created, 2 updates' );
check( 'Ystad' === get_post_meta( $existing, 'rp_ort', true ), 'existing company was updated (ort = Ystad)' );
check( 'inget' === RP_Packages::get( $existing ), 'import removed package from existing company' );
$log = get_option( 'rp_import_log' );
check( $log[0]['batch'] === $sum['batch'], 'log entry stores batch' );

$alfa = company( 'Ny Alfa AB' ); $beta = company( 'Ny Beta AB' ); $gamma = company( 'Ny Gamma AB' );
check( 'paket1' === RP_Packages::get( $alfa ), 'Alfa got paket1 from the import' );

// 3. After the import: Beta gets a review, Gamma is edited by hand, Alfa gets a job.
$c2 = wp_insert_comment( array( 'comment_post_ID' => $beta, 'comment_type' => 'rp_omdome', 'comment_content' => 'Mycket bra service, rekommenderas.', 'comment_approved' => 0, 'comment_author' => 'B' ) );
add_comment_meta( $c2, 'rp_betyg', 5 );
update_post_meta( $gamma, 'rp_ort', 'Luleå' );
RP_Jobs::upsert_job( $alfa, array( 'id' => 'j1', 'title' => 'Controller', 'url' => 'https://alfa.example/j1', 'description' => 'Jobb', 'deadline' => gmdate( 'Y-m-d', strtotime( '+30 days' ) ) ) );
$job = get_posts( array( 'post_type' => 'jobb', 'fields' => 'ids', 'meta_key' => 'rp_foretag_id', 'meta_value' => $alfa ) )[0] ?? 0;
check( $job > 0, 'job created for Alfa' );

// 4. Dry run.
$preview = RP_Import_Undo::undo( $sum['batch'], true );
$by = array(); foreach ( $preview['rows'] as $r ) { $by[ $r['namn'] ] = $r; $by_id[ $r['id'] ] = $r; }
echo "Dry run: {$preview['trashed']} tas bort, {$preview['restored']} återställs, {$preview['skipped']} hoppas över\n";
foreach ( $preview['rows'] as $r ) { echo "    - {$r['namn']}: {$r['action']} – {$r['message']}\n"; }
check( 'trashed' === $by['Ny Alfa AB']['action'], 'Alfa will be trashed (package came from the import itself)' );
check( 'skipped' === $by['Ny Beta AB']['action'] && str_contains( $by['Ny Beta AB']['message'], 'omdöme' ), 'Beta skipped: has a review' );
check( 'skipped' === $by['Ny Gamma AB']['action'] && str_contains( $by['Ny Gamma AB']['message'], 'ändrats' ), 'Gamma skipped: edited after import' );
check( 'restored' === $by_id[ $existing ]['action'], 'Existing company will be restored' );
check( 'Befintlig Rekrytering' === get_the_title( $existing ), 'import renamed it (overwrite on)' );
check( 'publish' === get_post_status( $alfa ), 'dry run changed nothing' );

// 5. Undo.
$res = RP_Import_Undo::undo( $sum['batch'] );
clean_post_cache( $existing ); wp_cache_flush();
check( 'trash' === get_post_status( $alfa ), 'Alfa is in the trash' );
check( 'trash' === get_post_status( $job ), "Alfa's job is in the trash" );
check( 'publish' === get_post_status( $beta ) && 'publish' === get_post_status( $gamma ), 'Beta and Gamma untouched' );
check( 'Luleå' === get_post_meta( $gamma, 'rp_ort', true ), "Gamma keeps the manual edit" );
$after_existing = RP_Import_Undo::snapshot( $existing );
check( $after_existing === $before_existing, 'Existing company restored exactly (title, text, fields, terms, package)' );
if ( $after_existing !== $before_existing ) { echo json_encode( array( 'before' => $before_existing, 'after' => $after_existing ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), "\n"; }
check( 'Malmö' === get_post_meta( $existing, 'rp_ort', true ) && 'paket1' === RP_Packages::get( $existing ), 'ort = Malmö, paket1 back' );
check( '1' === (string) get_post_meta( $existing, 'rp_utvald', true ), 'derived rp_utvald consistent with restored package' );
check( 1 === (int) get_comments( array( 'post_id' => $existing, 'type' => 'rp_omdome', 'count' => true ) ), 'existing review kept' );
$log = get_option( 'rp_import_log' );
check( ! empty( $log[0]['undone'] ) && 1 === $log[0]['undone']['trashed'] && 1 === $log[0]['undone']['restored'], 'log marked as undone with counts' );

// 6. Undoing again does nothing to already handled companies.
$again = RP_Import_Undo::undo( $sum['batch'], true );
check( 0 === $again['trashed'] && 0 === $again['restored'], 'second undo has nothing left to do' );

// 7. Dry-run imports do not create batches or log entries.
$count = count( get_option( 'rp_import_log' ) );
$dry   = RP_Importer::import_rows( array( array( 'namn' => 'Torr AB' ) ), array( 'dry_run' => true ), 'test' );
check( '' === $dry['batch'] && count( get_option( 'rp_import_log' ) ) === $count && ! company( 'Torr AB' ), 'dry run: no batch, no log, nothing created' );

// 8. Invalid batch ids are rejected.
check( array() === RP_Import_Undo::companies( "x' OR 1=1" ), 'invalid batch id rejected' );

echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit( $fail ? 1 : 0 );
