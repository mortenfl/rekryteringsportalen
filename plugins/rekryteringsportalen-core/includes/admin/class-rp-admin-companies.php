<?php
/**
 * Admin for companies: meta boxes, list columns, filters, quick/bulk edit of packages, dashboard widget.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Company admin.
 */
final class RP_Admin_Companies {

	private const NONCE = 'rp_company_save';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		$type = RP_Post_Types::COMPANY;
		add_action( 'add_meta_boxes_' . $type, array( self::class, 'meta_boxes' ) );
		add_action( 'add_meta_boxes_' . RP_Post_Types::JOB, array( self::class, 'job_meta_boxes' ) );
		add_action( 'add_meta_boxes_' . RP_Post_Types::PORTAL, array( self::class, 'portal_meta_boxes' ) );
		add_action( 'save_post_' . $type, array( self::class, 'save' ), 10, 2 );
		add_action( 'save_post_' . RP_Post_Types::JOB, array( self::class, 'save_job' ), 10, 2 );
		add_action( 'save_post_' . RP_Post_Types::PORTAL, array( self::class, 'save_portal' ), 10, 2 );

		add_filter( "manage_{$type}_posts_columns", array( self::class, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( self::class, 'column' ), 10, 2 );
		add_filter( "manage_edit-{$type}_sortable_columns", array( self::class, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'filters' ) );
		add_action( 'pre_get_posts', array( self::class, 'admin_query' ) );
		add_action( 'quick_edit_custom_box', array( self::class, 'quick_edit_box' ), 10, 2 );
		add_action( 'bulk_edit_custom_box', array( self::class, 'bulk_edit_box' ), 10, 2 );
		add_filter( 'post_row_actions', array( self::class, 'row_actions' ), 10, 2 );

		add_filter( 'manage_' . RP_Post_Types::JOB . '_posts_columns', array( self::class, 'job_columns' ) );
		add_action( 'manage_' . RP_Post_Types::JOB . '_posts_custom_column', array( self::class, 'job_column' ), 10, 2 );
		add_filter( 'manage_' . RP_Post_Types::PORTAL . '_posts_columns', array( self::class, 'portal_columns' ) );
		add_action( 'manage_' . RP_Post_Types::PORTAL . '_posts_custom_column', array( self::class, 'portal_column' ), 10, 2 );

		add_action( 'wp_dashboard_setup', array( self::class, 'dashboard' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_post_rp_import_feed_now', array( self::class, 'import_feed_now' ) );
	}

	/**
	 * Admin CSS/JS.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		$ours   = $screen && ( in_array( $screen->post_type, array( RP_Post_Types::COMPANY, RP_Post_Types::JOB, RP_Post_Types::PORTAL, RP_Post_Types::LEAD, RP_Post_Types::ORDER ), true ) || 'dashboard' === $screen->id || str_contains( $hook, 'rp-' ) );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'rp-admin', RP_CORE_URL . 'assets/css/admin.css', array(), RP_CORE_VERSION );
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
		}
		wp_enqueue_script( 'rp-admin', RP_CORE_URL . 'assets/js/admin.js', array( 'jquery' ), RP_CORE_VERSION, true );
	}

	/**
	 * Company meta boxes.
	 */
	public static function meta_boxes(): void {
		add_meta_box( 'rp-paket', 'Paket', array( self::class, 'box_package' ), RP_Post_Types::COMPANY, 'side', 'high' );
		add_meta_box( 'rp-logo', 'Logotyp', array( self::class, 'box_logo' ), RP_Post_Types::COMPANY, 'side' );
		add_meta_box( 'rp-betyg', 'Betyg', array( self::class, 'box_rating' ), RP_Post_Types::COMPANY, 'side' );
		add_meta_box( 'rp-uppgifter', 'Företagsuppgifter', array( self::class, 'box_details' ), RP_Post_Types::COMPANY, 'normal', 'high' );
		add_meta_box( 'rp-utokad', 'Utökad presentation (Paket 1 och 2)', array( self::class, 'box_extended' ), RP_Post_Types::COMPANY, 'normal' );
	}

	/**
	 * Package box.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function box_package( WP_Post $post ): void {
		wp_nonce_field( self::NONCE, 'rp_company_nonce' );
		$paket = (string) get_post_meta( $post->ID, 'rp_paket', true );
		$paket = '' === $paket ? RP_Packages::NONE : $paket;
		$until = (string) get_post_meta( $post->ID, 'rp_paket_giltigt_till', true );
		echo '<fieldset class="rp-paket-radios">';
		foreach ( RP_Packages::labels() as $key => $label ) {
			printf(
				'<label><input type="radio" name="rp_paket" value="%1$s" %2$s> <span class="rp-badge rp-badge--%1$s">%3$s</span></label>',
				esc_attr( $key ),
				checked( $paket, $key, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
		printf(
			'<p><label for="rp-paket-till"><strong>Giltigt till</strong> (valfritt)</label><br><input type="date" id="rp-paket-till" name="rp_paket_giltigt_till" value="%s"></p><p class="description">Efter detta datum återgår företaget automatiskt till Inget paket.</p>',
			esc_attr( $until )
		);
		$leads = (int) get_post_meta( $post->ID, 'rp_antal_leads', true );
		if ( $leads ) {
			/* translators: %d: lead count. */
			echo '<p>' . esc_html( sprintf( 'Har fått %d leads.', $leads ) ) . '</p>';
		}
		if ( RP_Packages::get( $post->ID ) !== $paket ) {
			echo '<p class="rp-warning">Paketet har gått ut och visas som Inget paket.</p>';
		}
	}

	/**
	 * Logo picker.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function box_logo( WP_Post $post ): void {
		self::media_field( 'rp_logo', (int) get_post_meta( $post->ID, 'rp_logo', true ), false );
	}

	/**
	 * Rating summary.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function box_rating( WP_Post $post ): void {
		$count = (int) get_post_meta( $post->ID, 'rp_antal_betyg', true );
		echo '<p>' . rp_rating_badge( $post->ID, 'sm' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<p>Viktat betyg (topplistor): <strong>' . esc_html( number_format_i18n( (float) get_post_meta( $post->ID, 'rp_bayes_betyg', true ), 2 ) ) . '</strong></p>';
		if ( $count < (int) RP_Settings::get( 'min_omdomen' ) ) {
			echo '<p class="description">Behöver minst ' . (int) RP_Settings::get( 'min_omdomen' ) . ' omdömen för att komma med i topplistorna.</p>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'edit-comments.php?comment_type=' . RP_Ratings::TYPE . '&p=' . $post->ID ) ) . '">Visa omdömen</a></p>';
	}

	/**
	 * Detail fields grouped.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function box_details( WP_Post $post ): void {
		$groups = array(
			'bas'     => 'Grunduppgifter',
			'kontakt' => 'Kontakt',
			'fakta'   => 'Fakta',
			'jobb'    => 'Lediga jobb',
			'paket'   => 'Leads',
			'intern'  => 'Internt',
		);
		foreach ( $groups as $group => $title ) {
			echo '<h3 class="rp-group-title">' . esc_html( $title ) . '</h3><table class="form-table rp-fields"><tbody>';
			foreach ( RP_Post_Types::company_fields() as $key => $field ) {
				if ( $field['group'] === $group ) {
					self::field_row( $post->ID, $key, $field );
				}
			}
			if ( 'jobb' === $group ) {
				$error = (string) get_post_meta( $post->ID, 'rp_jobbfeed_fel', true );
				$last  = (string) get_post_meta( $post->ID, 'rp_jobbfeed_senast', true );
				echo '<tr><th></th><td>';
				if ( $last ) {
					echo '<p class="description">Senast importerad: ' . esc_html( $last ) . '</p>';
				}
				if ( $error ) {
					echo '<p class="rp-warning">Fel vid senaste import: ' . esc_html( $error ) . '</p>';
				}
				if ( get_post_meta( $post->ID, 'rp_jobbfeed_url', true ) ) {
					$url = wp_nonce_url( admin_url( 'admin-post.php?action=rp_import_feed_now&post=' . $post->ID ), 'rp_import_feed_' . $post->ID );
					echo '<a class="button" href="' . esc_url( $url ) . '">Importera jobb nu</a>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
	}

	/**
	 * Extended presentation fields.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function box_extended( WP_Post $post ): void {
		if ( ! RP_Packages::has_extended( $post->ID ) ) {
			echo '<p class="rp-notice-inline">Dessa fält visas bara publikt när företaget har Paket 1 eller 2. Den långa beskrivningen skrivs i redigeraren ovan.</p>';
		}
		echo '<table class="form-table rp-fields"><tbody>';
		foreach ( RP_Post_Types::company_fields() as $key => $field ) {
			if ( 'utokad' === $field['group'] ) {
				self::field_row( $post->ID, $key, $field );
			}
		}
		echo '</tbody></table>';
	}

	/**
	 * One field row.
	 *
	 * @param int                  $post_id Post.
	 * @param string               $key     Field key.
	 * @param array<string,string> $field   Definition.
	 */
	private static function field_row( int $post_id, string $key, array $field ): void {
		$name  = 'rp_field[' . $key . ']';
		$id    = 'rp-field-' . $key;
		$value = get_post_meta( $post_id, $field['meta'], true );
		echo '<tr><th><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		switch ( $field['type'] ) {
			case 'textarea':
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . ( 'kort_beskrivning' === $key ? ' maxlength="300"' : '' ) . '>' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'lines':
				echo '<textarea class="large-text code" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
				break;
			case 'attachment':
				self::media_field( $name, (int) $value, false );
				break;
			case 'gallery':
				self::media_field( $name, (string) $value, true );
				break;
			default:
				$type = match ( $field['type'] ) {
					'url'   => 'url',
					'email' => 'email',
					'int'   => 'number',
					default => 'text',
				};
				echo '<input class="regular-text" type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Media picker field (single image or gallery of IDs).
	 *
	 * @param string     $name     Field name.
	 * @param int|string $value    Attachment ID or comma separated IDs.
	 * @param bool       $multiple Gallery.
	 */
	private static function media_field( string $name, $value, bool $multiple ): void {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		echo '<div class="rp-media" data-multiple="' . ( $multiple ? '1' : '0' ) . '">';
		echo '<input type="hidden" class="rp-media__input" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '">';
		echo '<div class="rp-media__preview">';
		foreach ( $ids as $id ) {
			echo wp_get_attachment_image( $id, 'thumbnail' );
		}
		echo '</div><button type="button" class="button rp-media__pick">' . esc_html( $multiple ? 'Välj bilder' : 'Välj bild' ) . '</button> ';
		echo '<button type="button" class="button-link rp-media__clear"' . ( $ids ? '' : ' hidden' ) . '>Ta bort</button></div>';
	}

	/**
	 * Save company (full edit, quick edit and bulk edit).
	 *
	 * @param int     $post_id Company.
	 * @param WP_Post $post    Post.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- verified below per context.
		$nonce = isset( $_REQUEST['rp_company_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['rp_company_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			RP_Importer::update_keys( $post_id );
			return;
		}

		// Bulk edit: only change the package when a value was chosen.
		if ( isset( $_REQUEST['rp_paket_bulk'] ) ) {
			$paket = sanitize_key( wp_unslash( $_REQUEST['rp_paket_bulk'] ) );
			$until = isset( $_REQUEST['rp_paket_till_bulk'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['rp_paket_till_bulk'] ) ) : '';
			if ( '' !== $paket ) {
				RP_Packages::set( $post_id, $paket, $until );
			}
			return;
		}

		if ( isset( $_POST['rp_paket'] ) ) {
			RP_Packages::set(
				$post_id,
				sanitize_key( wp_unslash( $_POST['rp_paket'] ) ),
				isset( $_POST['rp_paket_giltigt_till'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_paket_giltigt_till'] ) ) : ''
			);
		}

		if ( isset( $_POST['rp_logo'] ) ) {
			update_post_meta( $post_id, 'rp_logo', absint( $_POST['rp_logo'] ) );
		}

		if ( isset( $_POST['rp_field'] ) && is_array( $_POST['rp_field'] ) ) {
			$input = wp_unslash( $_POST['rp_field'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field type below.
			foreach ( RP_Post_Types::company_fields() as $key => $field ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}
				$raw = (string) $input[ $key ];
				if ( 'attachment' === $field['type'] ) {
					$value = (string) absint( $raw );
				} elseif ( 'gallery' === $field['type'] ) {
					$value = implode( ',', array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
				} else {
					$value = RP_Importer::sanitize_field( $field['type'], $raw );
				}
				if ( 'kort_beskrivning' === $key ) {
					$value = mb_substr( $value, 0, 300 );
				}
				update_post_meta( $post_id, $field['meta'], $value );
			}
		}
		// phpcs:enable

		RP_Importer::update_keys( $post_id );
	}

	/**
	 * Manual feed import button.
	 */
	public static function import_feed_now(): void {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'rp_import_feed_' . $id );
		if ( ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$res = RP_Jobs::import_feed( $id );
		wp_safe_redirect( add_query_arg( array( 'rp_feed' => $res['created'] . '-' . $res['updated'] ), (string) get_edit_post_link( $id, 'url' ) ) );
		exit;
	}

	/**
	 * List columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( $cols ): array {
		$new = array();
		foreach ( $cols as $key => $label ) {
			if ( 'title' === $key ) {
				$new['rp_logo'] = '<span class="screen-reader-text">Logotyp</span>';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['rp_paket']   = 'Paket';
				$new['rp_betyg']   = 'Betyg';
				$new['rp_omdomen'] = 'Omdömen';
				$new['rp_jobb']    = 'Jobb';
				$new['rp_ort']     = 'Ort';
			}
		}
		unset( $new['comments'] );
		return $new;
	}

	/**
	 * Column content. Includes hidden inline data for quick edit.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Company.
	 */
	public static function column( $col, $post_id ): void {
		switch ( $col ) {
			case 'rp_logo':
				echo rp_company_logo( (int) $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'rp_paket':
				$stored = (string) get_post_meta( $post_id, 'rp_paket', true );
				$paket  = RP_Packages::get( (int) $post_id );
				$until  = (string) get_post_meta( $post_id, 'rp_paket_giltigt_till', true );
				echo '<span class="rp-badge rp-badge--' . esc_attr( $paket ) . '">' . esc_html( RP_Packages::short_labels()[ $paket ] ) . '</span>';
				if ( $until && RP_Packages::NONE !== $paket ) {
					echo '<br><small>t.o.m. ' . esc_html( $until ) . '</small>';
				}
				printf(
					'<div class="hidden rp-inline" data-paket="%s" data-till="%s"></div>',
					esc_attr( '' === $stored ? RP_Packages::NONE : $stored ),
					esc_attr( $until )
				);
				break;
			case 'rp_betyg':
				$count = (int) get_post_meta( $post_id, 'rp_antal_betyg', true );
				echo $count ? rp_stars( (float) get_post_meta( $post_id, 'rp_snittbetyg', true ), 'sm' ) . ' ' . esc_html( number_format_i18n( (float) get_post_meta( $post_id, 'rp_snittbetyg', true ), 1 ) ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'rp_omdomen':
				$count = (int) get_post_meta( $post_id, 'rp_antal_betyg', true );
				echo '<a href="' . esc_url( admin_url( 'edit-comments.php?comment_type=' . RP_Ratings::TYPE . '&p=' . $post_id ) ) . '">' . (int) $count . '</a>';
				$pending = get_comments(
					array(
						'post_id' => $post_id,
						'type'    => RP_Ratings::TYPE,
						'status'  => 'hold',
						'count'   => true,
					)
				);
				if ( $pending ) {
					echo ' <span class="rp-pending" title="Väntar på granskning">+' . (int) $pending . '</span>';
				}
				break;
			case 'rp_jobb':
				echo (int) get_post_meta( $post_id, 'rp_antal_jobb', true );
				break;
			case 'rp_ort':
				echo esc_html( (string) get_post_meta( $post_id, 'rp_ort', true ) );
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function sortable( $cols ): array {
		$cols['rp_betyg']   = 'rp_betyg';
		$cols['rp_omdomen'] = 'rp_omdomen';
		$cols['rp_jobb']    = 'rp_jobb';
		$cols['rp_paket']   = 'rp_paket';
		return $cols;
	}

	/**
	 * Filter dropdowns above the list.
	 *
	 * @param string $post_type Post type.
	 */
	public static function filters( $post_type ): void {
		if ( RP_Post_Types::COMPANY !== $post_type ) {
			return;
		}
		$current = isset( $_GET['rp_paket'] ) ? sanitize_key( wp_unslash( $_GET['rp_paket'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label class="screen-reader-text" for="rp-filter-paket">Filtrera på paket</label><select name="rp_paket" id="rp-filter-paket"><option value="">Alla paket</option>';
		foreach ( RP_Packages::labels() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $current, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		$current = isset( $_GET['rp_kalla'] ) ? sanitize_text_field( wp_unslash( $_GET['rp_kalla'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<label class="screen-reader-text" for="rp-filter-kalla">Filtrera på källa</label><input type="search" name="rp_kalla" id="rp-filter-kalla" placeholder="Källa" value="' . esc_attr( $current ) . '" style="width:100px">';
	}

	/**
	 * Apply admin filters/sorting.
	 *
	 * @param WP_Query $q Query.
	 */
	public static function admin_query( WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || RP_Post_Types::COMPANY !== $q->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$meta = array();
		if ( ! empty( $_GET['rp_paket'] ) ) {
			$paket = sanitize_key( wp_unslash( $_GET['rp_paket'] ) );
			if ( RP_Packages::NONE === $paket ) {
				$meta[] = array(
					'relation' => 'OR',
					array(
						'key'   => 'rp_paket',
						'value' => RP_Packages::NONE,
					),
					array(
						'key'     => 'rp_paket',
						'compare' => 'NOT EXISTS',
					),
				);
			} else {
				$meta[] = array(
					'key'   => 'rp_paket',
					'value' => $paket,
				);
			}
		}
		if ( ! empty( $_GET['rp_kalla'] ) ) {
			$meta[] = array(
				'key'     => 'rp_kalla',
				'value'   => sanitize_text_field( wp_unslash( $_GET['rp_kalla'] ) ),
				'compare' => 'LIKE',
			);
		}
		// phpcs:enable
		if ( $meta ) {
			$q->set( 'meta_query', $meta );
		}
		$map     = array(
			'rp_betyg'   => array( 'rp_snittbetyg', 'meta_value_num' ),
			'rp_omdomen' => array( 'rp_antal_betyg', 'meta_value_num' ),
			'rp_jobb'    => array( 'rp_antal_jobb', 'meta_value_num' ),
			'rp_paket'   => array( 'rp_paket', 'meta_value' ),
		);
		$orderby = (string) $q->get( 'orderby' );
		if ( isset( $map[ $orderby ] ) ) {
			$q->set( 'meta_key', $map[ $orderby ][0] );
			$q->set( 'orderby', $map[ $orderby ][1] );
		}
	}

	/**
	 * Quick edit: package + end date.
	 *
	 * @param string $column    Column.
	 * @param string $post_type Post type.
	 */
	public static function quick_edit_box( $column, $post_type ): void {
		if ( RP_Post_Types::COMPANY !== $post_type || 'rp_paket' !== $column ) {
			return;
		}
		wp_nonce_field( self::NONCE, 'rp_company_nonce' );
		?>
		<fieldset class="inline-edit-col-right rp-quick-edit">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title">Paket</span>
					<select name="rp_paket">
						<?php foreach ( RP_Packages::labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="inline-edit-group">
					<span class="title">Giltigt till</span>
					<input type="date" name="rp_paket_giltigt_till" value="">
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Bulk edit: package + end date for many companies at once.
	 *
	 * @param string $column    Column.
	 * @param string $post_type Post type.
	 */
	public static function bulk_edit_box( $column, $post_type ): void {
		if ( RP_Post_Types::COMPANY !== $post_type || 'rp_paket' !== $column ) {
			return;
		}
		wp_nonce_field( self::NONCE, 'rp_company_nonce' );
		?>
		<fieldset class="inline-edit-col-right rp-bulk-edit">
			<div class="inline-edit-col">
				<label class="inline-edit-group">
					<span class="title">Paket</span>
					<select name="rp_paket_bulk">
						<option value="">— Ingen ändring —</option>
						<?php foreach ( RP_Packages::labels() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="inline-edit-group">
					<span class="title">Giltigt till</span>
					<input type="date" name="rp_paket_till_bulk" value="">
				</label>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Row action: view reviews.
	 *
	 * @param array<string,string> $actions Actions.
	 * @param WP_Post              $post    Post.
	 * @return array<string,string>
	 */
	public static function row_actions( $actions, $post ): array {
		if ( $post instanceof WP_Post && RP_Post_Types::COMPANY === $post->post_type ) {
			$actions['rp_omdomen'] = '<a href="' . esc_url( admin_url( 'edit-comments.php?comment_type=' . RP_Ratings::TYPE . '&p=' . $post->ID ) ) . '">Omdömen</a>';
		}
		return $actions;
	}

	/**
	 * Dashboard widget: companies to review, pending reviews, new leads/orders.
	 */
	public static function dashboard(): void {
		if ( current_user_can( 'edit_posts' ) ) {
			wp_add_dashboard_widget( 'rp_dashboard', 'Rekryteringsportalen', array( self::class, 'dashboard_widget' ) );
		}
	}

	/**
	 * Dashboard widget content.
	 */
	public static function dashboard_widget(): void {
		$drafts  = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => array( 'draft', 'pending' ),
				'posts_per_page' => 8,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$ndrafts = (int) wp_count_posts( RP_Post_Types::COMPANY )->draft + (int) wp_count_posts( RP_Post_Types::COMPANY )->pending;
		$pending = (int) get_comments(
			array(
				'type'   => RP_Ratings::TYPE,
				'status' => 'hold',
				'count'  => true,
			)
		);
		$orders  = get_posts(
			array(
				'post_type'      => RP_Post_Types::ORDER,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'rp_order_status',
				'meta_value'     => 'ny',
			)
		); // phpcs:ignore WordPress.DB.SlowDBQuery

		echo '<ul class="rp-dash-stats">';
		echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=foretag&post_status=draft' ) ) . '"><strong>' . (int) $ndrafts . '</strong> nya företag att granska</a></li>';
		echo '<li><a href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated&comment_type=' . RP_Ratings::TYPE ) ) . '"><strong>' . (int) $pending . '</strong> omdömen väntar</a></li>';
		echo '<li><a href="' . esc_url( admin_url( 'edit.php?post_type=paketorder' ) ) . '"><strong>' . count( $orders ) . '</strong> nya beställningar</a></li>';
		echo '</ul>';
		if ( $drafts ) {
			echo '<h3>Nya företag att granska (' . (int) $ndrafts . ')</h3><ul>';
			foreach ( $drafts as $d ) {
				$source = (string) get_post_meta( $d->ID, 'rp_kalla', true );
				echo '<li><a href="' . esc_url( (string) get_edit_post_link( $d->ID ) ) . '">' . esc_html( $d->post_title ) . '</a>' . ( $source ? ' <span class="description">(' . esc_html( $source ) . ')</span>' : '' ) . '</li>';
			}
			echo '</ul>';
		}
		echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'edit.php?post_type=foretag&page=rp-import' ) ) . '">Importera företag</a></p>';
	}

	/**
	 * Job meta boxes.
	 */
	public static function job_meta_boxes(): void {
		add_meta_box( 'rp-jobb', 'Jobbuppgifter', array( self::class, 'box_job' ), RP_Post_Types::JOB, 'normal', 'high' );
	}

	/**
	 * Job box.
	 *
	 * @param WP_Post $post Job.
	 */
	public static function box_job( WP_Post $post ): void {
		wp_nonce_field( 'rp_job_save', 'rp_job_nonce' );
		$company = (int) get_post_meta( $post->ID, 'rp_foretag_id', true );
		$type    = (string) get_post_meta( $post->ID, 'rp_anstallningsform', true );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="rp-job-company">Rekryteringsföretag</label></th><td>' . RP_Orders::company_dropdown( 'rp_foretag_id', $company, 'rp-job-company' ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<tr><th><label for="rp-job-ort">Ort</label></th><td><input class="regular-text" id="rp-job-ort" name="rp_ort" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_ort', true ) ) . '"></td></tr>';
		echo '<tr><th><label for="rp-job-typ">Anställningsform</label></th><td><select id="rp-job-typ" name="rp_anstallningsform"><option value="">—</option>';
		foreach ( RP_Post_Types::employment_types() as $t ) {
			echo '<option ' . selected( $type, $t, false ) . '>' . esc_html( $t ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="rp-job-deadline">Sista ansökningsdag</label></th><td><input type="date" id="rp-job-deadline" name="rp_sista_ansokningsdag" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_sista_ansokningsdag', true ) ) . '"><p class="description">Jobbet sätts automatiskt till Utgången efter detta datum.</p></td></tr>';
		echo '<tr><th><label for="rp-job-url">Ansökningslänk</label></th><td><input class="large-text" type="url" id="rp-job-url" name="rp_ansok_url" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_ansok_url', true ) ) . '"></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Save job.
	 *
	 * @param int     $post_id Job.
	 * @param WP_Post $post    Post.
	 */
	public static function save_job( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['rp_job_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rp_job_nonce'] ) ), 'rp_job_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$old = (int) get_post_meta( $post_id, 'rp_foretag_id', true );
		$new = isset( $_POST['rp_foretag_id'] ) ? absint( $_POST['rp_foretag_id'] ) : 0;
		update_post_meta( $post_id, 'rp_foretag_id', $new );
		update_post_meta( $post_id, 'rp_ort', isset( $_POST['rp_ort'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_ort'] ) ) : '' );
		update_post_meta( $post_id, 'rp_anstallningsform', isset( $_POST['rp_anstallningsform'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_anstallningsform'] ) ) : '' );
		update_post_meta( $post_id, 'rp_sista_ansokningsdag', isset( $_POST['rp_sista_ansokningsdag'] ) ? RP_Normalizer::date( sanitize_text_field( wp_unslash( $_POST['rp_sista_ansokningsdag'] ) ) ) : '' );
		update_post_meta( $post_id, 'rp_ansok_url', isset( $_POST['rp_ansok_url'] ) ? esc_url_raw( wp_unslash( $_POST['rp_ansok_url'] ) ) : '' );
		foreach ( array_unique( array_filter( array( $old, $new ) ) ) as $company ) {
			RP_Jobs::update_count( $company );
		}
	}

	/**
	 * Job columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function job_columns( $cols ): array {
		$date = $cols['date'] ?? 'Datum';
		unset( $cols['date'] );
		$cols['rp_foretag']  = 'Företag';
		$cols['rp_deadline'] = 'Sista ansökningsdag';
		$cols['date']        = $date;
		return $cols;
	}

	/**
	 * Job column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Job.
	 */
	public static function job_column( $col, $post_id ): void {
		if ( 'rp_foretag' === $col ) {
			$c = (int) get_post_meta( $post_id, 'rp_foretag_id', true );
			echo $c ? '<a href="' . esc_url( (string) get_edit_post_link( $c ) ) . '">' . esc_html( get_the_title( $c ) ) . '</a>' : '—';
		} elseif ( 'rp_deadline' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, 'rp_sista_ansokningsdag', true ) );
		}
	}

	/**
	 * Portal meta boxes.
	 */
	public static function portal_meta_boxes(): void {
		add_meta_box( 'rp-portal', 'Jobbportal', array( self::class, 'box_portal' ), RP_Post_Types::PORTAL, 'normal', 'high' );
	}

	/**
	 * Portal box.
	 *
	 * @param WP_Post $post Portal.
	 */
	public static function box_portal( WP_Post $post ): void {
		wp_nonce_field( 'rp_portal_save', 'rp_portal_nonce' );
		$cat = (string) get_post_meta( $post->ID, 'rp_kategori', true );
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="rp-portal-url">URL</label></th><td><input class="large-text" type="url" id="rp-portal-url" name="rp_url" value="' . esc_attr( (string) get_post_meta( $post->ID, 'rp_url', true ) ) . '" required></td></tr>';
		echo '<tr><th><label for="rp-portal-desc">Beskrivning</label></th><td><textarea class="large-text" rows="3" id="rp-portal-desc" name="rp_beskrivning">' . esc_textarea( (string) get_post_meta( $post->ID, 'rp_beskrivning', true ) ) . '</textarea></td></tr>';
		echo '<tr><th><label for="rp-portal-cat">Kategori</label></th><td><select id="rp-portal-cat" name="rp_kategori">';
		foreach ( RP_Post_Types::portal_categories() as $c ) {
			echo '<option ' . selected( $cat, $c, false ) . '>' . esc_html( $c ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th>Klick</th><td>' . (int) get_post_meta( $post->ID, 'rp_klick', true ) . '</td></tr>';
		echo '</tbody></table><p class="description">Logotyp sätts som utvald bild. Ordningen styrs av fältet Ordning under Sidattribut.</p>';
	}

	/**
	 * Save portal.
	 *
	 * @param int     $post_id Portal.
	 * @param WP_Post $post    Post.
	 */
	public static function save_portal( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['rp_portal_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rp_portal_nonce'] ) ), 'rp_portal_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, 'rp_url', isset( $_POST['rp_url'] ) ? esc_url_raw( wp_unslash( $_POST['rp_url'] ) ) : '' );
		update_post_meta( $post_id, 'rp_beskrivning', isset( $_POST['rp_beskrivning'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rp_beskrivning'] ) ) : '' );
		$cat = isset( $_POST['rp_kategori'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_kategori'] ) ) : '';
		update_post_meta( $post_id, 'rp_kategori', in_array( $cat, RP_Post_Types::portal_categories(), true ) ? $cat : 'Allmän' );
	}

	/**
	 * Portal columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function portal_columns( $cols ): array {
		unset( $cols['date'] );
		$cols['rp_kategori'] = 'Kategori';
		$cols['rp_klick']    = 'Klick';
		$cols['menu_order']  = 'Ordning';
		return $cols;
	}

	/**
	 * Portal column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Portal.
	 */
	public static function portal_column( $col, $post_id ): void {
		if ( 'rp_kategori' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, 'rp_kategori', true ) );
		} elseif ( 'rp_klick' === $col ) {
			echo (int) get_post_meta( $post_id, 'rp_klick', true );
		} elseif ( 'menu_order' === $col ) {
			echo (int) get_post_field( 'menu_order', $post_id );
		}
	}
}
