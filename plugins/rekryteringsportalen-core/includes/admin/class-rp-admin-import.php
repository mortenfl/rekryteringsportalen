<?php
/**
 * Admin page "Importera företag": upload → map columns → preview → import → log.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import admin page.
 */
final class RP_Admin_Import {

	private const SLUG = 'rp-import';
	private const CAP  = 'edit_others_posts';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_rp_import_upload', array( self::class, 'handle_upload' ) );
		add_action( 'admin_post_rp_import_map', array( self::class, 'handle_map' ) );
		add_action( 'admin_post_rp_import_run', array( self::class, 'handle_run' ) );
		add_action( 'admin_post_rp_import_undo', array( self::class, 'handle_undo' ) );
	}

	/**
	 * Menu.
	 */
	public static function menu(): void {
		add_submenu_page( 'edit.php?post_type=' . RP_Post_Types::COMPANY, 'Importera företag', 'Importera företag', self::CAP, self::SLUG, array( self::class, 'render' ) );
	}

	/**
	 * Per-user working state.
	 */
	private static function key(): string {
		return 'rp_import_state_' . get_current_user_id();
	}

	/**
	 * State.
	 *
	 * @return array<string,mixed>
	 */
	private static function state(): array {
		$s = get_transient( self::key() );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Save state.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private static function save_state( array $state ): void {
		set_transient( self::key(), $state, 2 * HOUR_IN_SECONDS );
	}

	/**
	 * Page URL.
	 *
	 * @param string               $step  Step.
	 * @param array<string,string> $extra Extra args.
	 */
	private static function url( string $step = '', array $extra = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => RP_Post_Types::COMPANY,
					'page'      => self::SLUG,
					'step'      => $step,
				),
				$extra
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Step 1: upload / URL / paste.
	 */
	public static function handle_upload(): void {
		check_admin_referer( 'rp_import_upload' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$path    = '';
		$name    = '';
		$cleanup = '';
		$sheet   = isset( $_POST['rp_sheet'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_sheet'] ) ) : '';
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- raw file content is parsed and every value sanitized at import.
		if ( ! empty( $_FILES['rp_file']['tmp_name'] ) && is_uploaded_file( $_FILES['rp_file']['tmp_name'] ) ) {
			$name = sanitize_file_name( (string) $_FILES['rp_file']['name'] );
			$path = (string) $_FILES['rp_file']['tmp_name'];
		} elseif ( ! empty( $_POST['rp_url'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$url  = esc_url_raw( wp_unslash( $_POST['rp_url'] ) );
			$name = sanitize_file_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
			$tmp  = download_url( $url, 60 );
			if ( is_wp_error( $tmp ) ) {
				wp_safe_redirect( self::url( '', array( 'rp_err' => 'url' ) ) );
				exit;
			}
			$path    = $tmp;
			$cleanup = $tmp;
		} elseif ( ! empty( $_POST['rp_paste'] ) ) {
			$path    = wp_tempnam( 'rp-import' );
			$cleanup = $path;
			$name    = 'inklistrat.csv';
			file_put_contents( $path, (string) wp_unslash( $_POST['rp_paste'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
		// phpcs:enable

		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( '' === $path || ( '' !== $ext && ! in_array( $ext, RP_Importer::extensions(), true ) ) ) {
			if ( $cleanup ) {
				wp_delete_file( $cleanup );
			}
			wp_safe_redirect( self::url( '', array( 'rp_err' => '' === $path ? 'empty' : 'type' ) ) );
			exit;
		}

		$parsed = RP_Importer::parse_file( $path, $name, array( 'sheet' => $sheet ) );
		if ( $cleanup ) {
			wp_delete_file( $cleanup );
		}
		if ( ! empty( $parsed['error'] ) ) {
			set_transient( self::key() . '_error', $parsed['error'], 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( self::url( '', array( 'rp_err' => 'parse' ) ) );
			exit;
		}
		if ( ! $parsed['rows'] ) {
			wp_safe_redirect( self::url( '', array( 'rp_err' => 'empty' ) ) );
			exit;
		}
		if ( ! empty( $parsed['info'] ) ) {
			$name .= ' – ' . $parsed['info'];
		}
		self::save_state(
			array(
				'file'      => $name,
				'headers'   => $parsed['headers'],
				'rows'      => $parsed['rows'],
				'mapping'   => RP_Importer::guess_mapping( $parsed['headers'] ),
				'status'    => 'draft',
				'overwrite' => false,
			)
		);
		wp_safe_redirect( self::url( 'map' ) );
		exit;
	}

	/**
	 * Step 2: save mapping + options, optionally as a template.
	 */
	public static function handle_map(): void {
		check_admin_referer( 'rp_import_map' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$state   = self::state();
		$columns = RP_Importer::columns();
		$mapping = array();
		$posted  = isset( $_POST['rp_map'] ) && is_array( $_POST['rp_map'] ) ? wp_unslash( $_POST['rp_map'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		foreach ( (array) ( $state['headers'] ?? array() ) as $i => $header ) {
			$value              = isset( $posted[ $i ] ) ? sanitize_key( (string) $posted[ $i ] ) : '';
			$mapping[ $header ] = isset( $columns[ $value ] ) ? $value : '';
		}
		$state['mapping']   = $mapping;
		$state['status']    = isset( $_POST['rp_status'] ) && in_array( $_POST['rp_status'], array( 'draft', 'publish', 'pending' ), true ) ? sanitize_key( $_POST['rp_status'] ) : 'draft';
		$state['overwrite'] = ! empty( $_POST['rp_overwrite'] );
		self::save_state( $state );

		$template = isset( $_POST['rp_template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_template_name'] ) ) : '';
		if ( '' !== $template ) {
			$templates              = get_option( 'rp_import_mappings', array() );
			$templates              = is_array( $templates ) ? $templates : array();
			$templates[ $template ] = $mapping;
			update_option( 'rp_import_mappings', $templates, false );
		}

		if ( ! in_array( 'namn', $mapping, true ) ) {
			wp_safe_redirect( self::url( 'map', array( 'rp_err' => 'namn' ) ) );
			exit;
		}
		wp_safe_redirect( self::url( 'preview' ) );
		exit;
	}

	/**
	 * Step 3: run the import.
	 */
	public static function handle_run(): void {
		check_admin_referer( 'rp_import_run' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$state = self::state();
		if ( empty( $state['rows'] ) ) {
			wp_safe_redirect( self::url() );
			exit;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large imports.
		}
		$summary = RP_Importer::import_rows(
			self::mapped_rows( $state ),
			array(
				'status'    => $state['status'],
				'overwrite' => $state['overwrite'],
			),
			'admin'
		);
		delete_transient( self::key() );
		set_transient( self::key() . '_done', $summary, HOUR_IN_SECONDS );
		wp_safe_redirect( self::url( 'done' ) );
		exit;
	}

	/**
	 * Rows with mapping applied.
	 *
	 * @param array<string,mixed> $state State.
	 * @return array<int, array<string,string>>
	 */
	private static function mapped_rows( array $state ): array {
		return array_map(
			static function ( $row ) use ( $state ) {
				return RP_Importer::map_row( $row, $state['mapping'] );
			},
			$state['rows']
		);
	}

	/**
	 * Render page.
	 */
	public static function render(): void {
		$step   = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$err    = isset( $_GET['rp_err'] ) ? sanitize_key( wp_unslash( $_GET['rp_err'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors = array(
			'type'  => 'Filformatet stöds inte. Tillåtna format: ' . implode( ', ', RP_Importer::extensions() ) . '.',
			'url'   => 'Kunde inte hämta filen från URL:en.',
			'empty' => 'Hittade inga rader i filen. Kontrollera att första raden innehåller kolumnnamn.',
			'namn'  => 'Någon kolumn måste kopplas till fältet Namn.',
			'batch' => 'Importen hittades inte eller kan inte ångras.',
		);
		$steps  = array(
			''        => '1. Ladda upp',
			'map'     => '2. Koppla kolumner',
			'preview' => '3. Förhandsgranska',
			'done'    => '4. Klart',
		);

		$undo_steps = in_array( $step, array( 'undo', 'undone' ), true );
		echo '<div class="wrap"><h1>' . esc_html( $undo_steps ? 'Ångra import' : 'Importera rekryteringsföretag' ) . '</h1>';
		if ( ! $undo_steps ) {
			echo '<ol class="rp-import-steps">';
			foreach ( $steps as $k => $label ) {
				echo '<li class="' . ( $k === $step ? 'is-current' : '' ) . '">' . esc_html( $label ) . '</li>';
			}
			echo '</ol>';
		}
		if ( 'parse' === $err ) {
			$errors['parse'] = (string) get_transient( self::key() . '_error' );
		}
		if ( ! empty( $errors[ $err ] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $errors[ $err ] ) . '</p></div>';
		}

		$state = self::state();
		if ( in_array( $step, array( 'map', 'preview' ), true ) && empty( $state['rows'] ) ) {
			$step = '';
		}
		match ( $step ) {
			'map'     => self::render_map( $state ),
			'preview' => self::render_preview( $state ),
			'done'    => self::render_done(),
			'undo'    => self::render_undo(),
			'undone'  => self::render_undone(),
			default   => self::render_upload(),
		};
		echo '</div>';
	}

	/**
	 * Upload step.
	 */
	private static function render_upload(): void {
		?>
		<div class="rp-card">
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rp_import_upload">
				<?php wp_nonce_field( 'rp_import_upload' ); ?>
				<table class="form-table"><tbody>
					<tr><th><label for="rp-file">Ladda upp fil</label></th><td><input type="file" id="rp-file" name="rp_file" accept="<?php echo esc_attr( '.' . implode( ',.', RP_Importer::extensions() ) ); ?>"><p class="description"><?php echo esc_html( apply_filters( 'rp_import_file_help', 'CSV (semikolon, komma eller tab) eller JSON. Excel: Spara som → CSV UTF-8.' ) ); ?></p></td></tr>
					<?php
					/**
					 * Extra fields on the upload step (e.g. sheet selection for Excel files).
					 */
					do_action( 'rp_import_upload_fields' );
					?>
					<tr><th><label for="rp-url">…eller hämta från URL</label></th><td><input type="url" id="rp-url" name="rp_url" class="large-text" placeholder="https://exempel.se/foretag.csv"></td></tr>
					<tr><th><label for="rp-paste">…eller klistra in</label></th><td><textarea id="rp-paste" name="rp_paste" rows="6" class="large-text code" placeholder="namn;orgnr;webbplats&#10;Exempel AB;556677-8899;exempel.se"></textarea></td></tr>
				</tbody></table>
				<?php submit_button( 'Läs in och fortsätt' ); ?>
			</form>
			<p><a href="<?php echo esc_url( RP_CORE_URL . 'data/import-mall.csv' ); ?>" download>Ladda ner CSV-mall</a> med alla kolumner och exempelrader.</p>
		</div>

		<div class="rp-card">
			<h2>Andra sätt att importera</h2>
			<p><strong>WP-CLI:</strong> <code>wp rp import foretag.csv --status=draft --dry-run</code></p>
			<p><strong>REST API</strong> (för crawlers, kräver Application Password för en administratör):<br>
			<code>POST <?php echo esc_html( rest_url( RP_Rest::NS . '/import?status=draft' ) ); ?></code> med JSON-body <code>[{"namn":"…","orgnr":"…","webbplats":"…"}]</code></p>
		</div>
		<?php
		self::render_log();
	}

	/**
	 * Mapping step.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private static function render_map( array $state ): void {
		$columns   = RP_Importer::columns();
		$templates = get_option( 'rp_import_mappings', array() );
		$first     = $state['rows'][0] ?? array();
		?>
		<div class="rp-card">
			<p><strong><?php echo esc_html( (string) $state['file'] ); ?></strong> – <?php echo (int) count( $state['rows'] ); ?> rader. Koppla varje kolumn i filen till ett fält. Kolumner som lämnas på "Ignorera" importeras inte.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rp_import_map">
				<?php wp_nonce_field( 'rp_import_map' ); ?>
				<?php if ( is_array( $templates ) && $templates ) : ?>
					<p><label for="rp-mapping-template">Använd sparad mappning: </label>
					<select id="rp-mapping-template"><option value="">—</option>
						<?php foreach ( $templates as $name => $map ) : ?>
							<option data-mapping="<?php echo esc_attr( (string) wp_json_encode( $map ) ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select></p>
				<?php endif; ?>
				<table class="widefat striped"><thead><tr><th>Kolumn i filen</th><th>Exempel (rad 1)</th><th>Importera till fält</th></tr></thead><tbody>
				<?php foreach ( $state['headers'] as $i => $header ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $header ); ?></strong></td>
						<td><div class="rp-sample"><?php echo esc_html( (string) ( $first[ $header ] ?? '' ) ); ?></div></td>
						<td><select class="rp-map-select" name="rp_map[<?php echo (int) $i; ?>]" data-header="<?php echo esc_attr( $header ); ?>">
							<option value="">— Ignorera —</option>
							<?php foreach ( $columns as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state['mapping'][ $header ] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<table class="form-table"><tbody>
					<tr><th>Status för nya företag</th><td>
						<label><input type="radio" name="rp_status" value="draft" <?php checked( $state['status'], 'draft' ); ?>> Utkast – granska innan publicering (rekommenderas)</label><br>
						<label><input type="radio" name="rp_status" value="publish" <?php checked( $state['status'], 'publish' ); ?>> Publicera direkt</label>
					</td></tr>
					<tr><th>Befintliga företag</th><td>
						<label><input type="checkbox" name="rp_overwrite" value="1" <?php checked( $state['overwrite'] ); ?>> Skriv över befintliga värden</label>
						<p class="description">Utan bock fylls bara tomma fält i. Dubbletter hittas på org.nr, sedan webbplatsens domän, sedan namn.</p>
					</td></tr>
					<tr><th><label for="rp-template-name">Spara mappningen som</label></th><td><input id="rp-template-name" name="rp_template_name" class="regular-text" placeholder="T.ex. Crawler-export"></td></tr>
				</tbody></table>
				<?php submit_button( 'Förhandsgranska' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Preview step.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private static function render_preview( array $state ): void {
		$preview = RP_Importer::preview( self::mapped_rows( $state ), (bool) $state['overwrite'] );
		$counts  = array_count_values( array_column( $preview, 'action' ) );
		$labels  = array(
			'created' => 'Nytt',
			'updated' => 'Uppdateras',
			'skipped' => 'Hoppas över',
		);
		?>
		<div class="rp-card">
			<p><strong><?php echo (int) ( $counts['created'] ?? 0 ); ?></strong> nya företag (som <?php echo 'publish' === $state['status'] ? 'publicerade' : 'utkast'; ?>),
			<strong><?php echo (int) ( $counts['updated'] ?? 0 ); ?></strong> uppdateras,
			<strong><?php echo (int) ( $counts['skipped'] ?? 0 ); ?></strong> hoppas över.</p>
			<table class="widefat striped rp-preview-table"><thead><tr><th>Rad</th><th>Namn</th><th>Åtgärd</th><th>Detaljer</th></tr></thead><tbody>
			<?php foreach ( array_slice( $preview, 0, 500 ) as $row ) : ?>
				<tr>
					<td><?php echo (int) $row['row']; ?></td>
					<td><?php echo $row['id'] ? '<a href="' . esc_url( (string) get_edit_post_link( $row['id'] ) ) . '">' . esc_html( $row['namn'] ) . '</a>' : esc_html( $row['namn'] ); ?></td>
					<td class="rp-action-<?php echo esc_attr( $row['action'] ); ?>"><?php echo esc_html( $labels[ $row['action'] ] ?? $row['action'] ); ?></td>
					<td><?php echo esc_html( $row['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php if ( count( $preview ) > 500 ) : ?>
				<p class="description">Visar de första 500 raderna.</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px">
				<input type="hidden" name="action" value="rp_import_run">
				<?php wp_nonce_field( 'rp_import_run' ); ?>
				<a class="button" href="<?php echo esc_url( self::url( 'map' ) ); ?>">← Ändra mappning</a>
				<button type="submit" class="button button-primary">Importera <?php echo (int) ( ( $counts['created'] ?? 0 ) + ( $counts['updated'] ?? 0 ) ); ?> företag</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Result step.
	 */
	private static function render_done(): void {
		$summary = get_transient( self::key() . '_done' );
		if ( ! is_array( $summary ) ) {
			self::render_upload();
			return;
		}
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Importen är klar: %d skapade, %d uppdaterade, %d hoppade över, %d fel.', $summary['created'], $summary['updated'], $summary['skipped'], $summary['errors'] ) ) . '</p></div>';
		self::rows_table( $summary['rows'] );
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=foretag&post_status=draft' ) ) . '">Granska nya utkast</a> <a class="button" href="' . esc_url( self::url() ) . '">Ny import</a>';
		if ( ! empty( $summary['batch'] ) && ( $summary['created'] + $summary['updated'] ) > 0 ) {
			echo ' <a class="button button-link-delete" href="' . esc_url( self::url( 'undo', array( 'batch' => $summary['batch'] ) ) ) . '">Ångra den här importen…</a>';
		}
		echo '</p><p class="description">Import-ID: <code>' . esc_html( (string) ( $summary['batch'] ?? '' ) ) . '</code></p>';
	}

	/**
	 * Import history.
	 */
	private static function render_log(): void {
		$log = get_option( 'rp_import_log', array() );
		if ( ! is_array( $log ) || ! $log ) {
			return;
		}
		echo '<div class="rp-card"><h2>Importlogg</h2><table class="widefat striped"><thead><tr><th>Tid</th><th>Källa</th><th>Användare</th><th>Skapade</th><th>Uppdaterade</th><th>Hoppade över</th><th>Fel</th><th>Ångra</th></tr></thead><tbody>';
		foreach ( $log as $i => $entry ) {
			$user = get_userdata( (int) $entry['user'] );
			printf(
				'<tr><td><details><summary>%s</summary>%s</details></td><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%d</td><td>%d</td><td>%s</td></tr>',
				esc_html( $entry['time'] ),
				self::rows_table( $entry['rows'], true ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $entry['source'] ),
				esc_html( $user ? $user->display_name : '—' ),
				(int) $entry['created'],
				(int) $entry['updated'],
				(int) $entry['skipped'],
				(int) $entry['errors'],
				self::undo_cell( $entry ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
		echo '</tbody></table></div>';
	}

	/**
	 * "Ångra" cell in the import log.
	 *
	 * @param array<string, mixed> $entry Log entry.
	 */
	private static function undo_cell( array $entry ): string {
		if ( ! empty( $entry['undone'] ) ) {
			$u = $entry['undone'];
			return '<span class="rp-badge">Ångrad</span><br><small>' . esc_html( (string) $u['time'] ) . '<br>' . esc_html( sprintf( '%d borttagna, %d återställda, %d överhoppade', $u['trashed'], $u['restored'], $u['skipped'] ) ) . '</small>';
		}
		if ( empty( $entry['batch'] ) ) {
			return '<span class="description" title="Importerad före version 1.3.0 – saknar import-ID.">–</span>';
		}
		if ( ( (int) $entry['created'] + (int) $entry['updated'] ) < 1 ) {
			return '–';
		}
		return '<a class="button button-small" href="' . esc_url( self::url( 'undo', array( 'batch' => (string) $entry['batch'] ) ) ) . '">Ångra…</a>';
	}

	/**
	 * Batch from the query string, validated.
	 */
	private static function requested_batch(): string {
		$batch = isset( $_REQUEST['batch'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['batch'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- validated format; actions verify nonces.
		return RP_Import_Undo::is_valid_batch( $batch ) ? $batch : '';
	}

	/**
	 * Undo preview + confirmation.
	 */
	private static function render_undo(): void {
		$batch = self::requested_batch();
		$entry = '' !== $batch ? RP_Import_Undo::log_entry( $batch ) : null;
		if ( '' === $batch || ( $entry && ! empty( $entry['undone'] ) ) ) {
			echo '<div class="notice notice-error"><p>Importen hittades inte eller har redan ångrats.</p></div>';
			self::render_log();
			return;
		}
		$preview = RP_Import_Undo::undo( $batch, true );
		$todo    = $preview['trashed'] + $preview['restored'];

		echo '<div class="rp-card">';
		if ( $entry ) {
			echo '<p>Import <code>' . esc_html( $batch ) . '</code> från <strong>' . esc_html( (string) $entry['time'] ) . '</strong> (' . esc_html( (string) $entry['source'] ) . ').</p>';
		}
		echo '<p><strong>' . (int) $preview['trashed'] . '</strong> företag som importen skapade flyttas till papperskorgen (tillsammans med deras jobb), <strong>' . (int) $preview['restored'] . '</strong> företag som importen uppdaterade återställs till värdena före importen, och <strong>' . (int) $preview['skipped'] . '</strong> hoppas över.</p>';
		echo '<p class="description">Företag som har ändrats efter importen, eller som har fått omdömen, rörs inte. Borttagna företag kan återställas från papperskorgen.</p>';
		$labels = array(
			'trashed'  => 'Tas bort',
			'restored' => 'Återställs',
			'skipped'  => 'Hoppas över',
		);
		echo '<table class="widefat striped rp-preview-table"><thead><tr><th>Företag</th><th>Åtgärd</th><th>Detaljer</th></tr></thead><tbody>';
		foreach ( $preview['rows'] as $row ) {
			$css = 'skipped' === $row['action'] ? 'skipped' : ( 'trashed' === $row['action'] ? 'error' : 'updated' );
			echo '<tr><td><a href="' . esc_url( (string) get_edit_post_link( $row['id'] ) ) . '">' . esc_html( $row['namn'] ) . '</a></td><td class="rp-action-' . esc_attr( $css ) . '">' . esc_html( $labels[ $row['action'] ] ) . '</td><td>' . esc_html( $row['message'] ) . '</td></tr>';
		}
		if ( ! $preview['rows'] ) {
			echo '<tr><td colspan="3">Inga företag från den här importen finns kvar att ångra.</td></tr>';
		}
		echo '</tbody></table>';

		if ( $todo > 0 ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:16px" onsubmit="return confirm(\'Ångra importen? ' . (int) $todo . ' företag påverkas.\');">';
			echo '<input type="hidden" name="action" value="rp_import_undo"><input type="hidden" name="batch" value="' . esc_attr( $batch ) . '">';
			wp_nonce_field( 'rp_import_undo_' . $batch );
			echo '<a class="button" href="' . esc_url( self::url() ) . '">Avbryt</a> ';
			echo '<button type="submit" class="button button-primary">Ångra importen (' . (int) $todo . ' företag)</button></form>';
		} else {
			echo '<p><a class="button" href="' . esc_url( self::url() ) . '">Tillbaka</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Run the undo.
	 */
	public static function handle_undo(): void {
		$batch = self::requested_batch();
		check_admin_referer( 'rp_import_undo_' . $batch );
		if ( '' === $batch || ! current_user_can( self::CAP ) || ! current_user_can( 'delete_others_posts' ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large imports.
		}
		$result = RP_Import_Undo::undo( $batch );
		set_transient( self::key() . '_undone', $result, HOUR_IN_SECONDS );
		wp_safe_redirect( self::url( 'undone' ) );
		exit;
	}

	/**
	 * Undo result.
	 */
	private static function render_undone(): void {
		$result = get_transient( self::key() . '_undone' );
		if ( ! is_array( $result ) ) {
			self::render_upload();
			return;
		}
		echo '<div class="notice notice-success"><p>' . esc_html( sprintf( 'Importen är ångrad: %d företag flyttades till papperskorgen, %d återställdes och %d hoppades över.', $result['trashed'], $result['restored'], $result['skipped'] ) ) . '</p></div>';
		$labels = array(
			'trashed'  => 'Borttaget',
			'restored' => 'Återställt',
			'skipped'  => 'Överhoppat',
		);
		echo '<table class="widefat striped rp-preview-table"><thead><tr><th>Företag</th><th>Resultat</th><th>Detaljer</th></tr></thead><tbody>';
		foreach ( $result['rows'] as $row ) {
			echo '<tr><td>' . esc_html( $row['namn'] ) . '</td><td>' . esc_html( $labels[ $row['action'] ] ) . '</td><td>' . esc_html( str_replace( array( 'Flyttas', 'Återställs', 'Hoppas' ), array( 'Flyttades', 'Återställdes', 'Hoppades' ), $row['message'] ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'edit.php?post_status=trash&post_type=' . RP_Post_Types::COMPANY ) ) . '">Visa papperskorgen</a> <a class="button" href="' . esc_url( self::url() ) . '">Till importen</a></p>';
	}

	/**
	 * Per-row result table.
	 *
	 * @param array<int, array<string,mixed>> $rows   Rows.
	 * @param bool                            $as_string Return instead of echo.
	 */
	private static function rows_table( array $rows, bool $as_string = false ): string {
		$labels = array(
			'created' => 'Skapad',
			'updated' => 'Uppdaterad',
			'skipped' => 'Hoppades över',
			'error'   => 'Fel',
		);
		$html   = '<table class="widefat striped rp-preview-table"><thead><tr><th>Rad</th><th>Namn</th><th>Resultat</th><th>Detaljer</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$name  = $row['id'] ? '<a href="' . esc_url( (string) get_edit_post_link( (int) $row['id'] ) ) . '">' . esc_html( (string) $row['namn'] ) . '</a>' : esc_html( (string) $row['namn'] );
			$html .= '<tr><td>' . (int) $row['row'] . '</td><td>' . $name . '</td><td class="rp-action-' . esc_attr( (string) $row['action'] ) . '">' . esc_html( $labels[ $row['action'] ] ?? (string) $row['action'] ) . '</td><td>' . esc_html( (string) $row['message'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( ! $as_string ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return $html;
	}
}
