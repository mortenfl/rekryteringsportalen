<?php
/**
 * Package orders (paketorder) and the company contact form (package 1+).
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orders.
 */
final class RP_Orders {

	/**
	 * Order statuses.
	 *
	 * @return array<string,string>
	 */
	public static function statuses(): array {
		return array(
			'ny'        => 'Ny',
			'kontaktad' => 'Kontaktad',
			'aktiv'     => 'Aktiv',
			'avslutad'  => 'Avslutad',
		);
	}

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_shortcode( 'rp_paketformular', array( self::class, 'shortcode' ) );
		add_action( 'admin_post_rp_submit_order', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_rp_submit_order', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_rp_contact_company', array( self::class, 'handle_contact' ) );
		add_action( 'admin_post_nopriv_rp_contact_company', array( self::class, 'handle_contact' ) );
		add_action( 'add_meta_boxes_' . RP_Post_Types::ORDER, array( self::class, 'meta_boxes' ) );
		add_action( 'save_post_' . RP_Post_Types::ORDER, array( self::class, 'save' ), 10, 2 );
		add_filter( 'manage_' . RP_Post_Types::ORDER . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . RP_Post_Types::ORDER . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
	}

	/**
	 * Order form.
	 *
	 * @param array<string,string>|string $atts Attributes: paket (preselected).
	 */
	public static function shortcode( $atts = array() ): string {
		$atts     = shortcode_atts( array( 'paket' => '' ), $atts );
		$selected = isset( $_GET['paket'] ) ? sanitize_key( wp_unslash( $_GET['paket'] ) ) : $atts['paket']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		// "uppgradera" (not "foretag": that is the company post type's query var and would make WordPress look for a company and return 404).
		$claim = isset( $_GET['uppgradera'] ) ? absint( $_GET['uppgradera'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$claim = $claim && RP_Post_Types::COMPANY === get_post_type( $claim ) ? $claim : 0;

		ob_start();
		echo RP_Forms::notice( 'order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<form class="rp-form rp-order-form" id="bestall" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo RP_Forms::hidden_fields( 'rp_submit_order' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="rp_order_foretag_id" value="<?php echo (int) $claim; ?>">
			<fieldset class="rp-field rp-field--wide rp-package-choice">
				<legend>Välj paket *</legend>
				<?php foreach ( array( RP_Packages::EXTENDED, RP_Packages::LEADS ) as $p ) : ?>
					<label class="rp-package-choice__option">
						<input type="radio" name="rp_order_paket" value="<?php echo esc_attr( $p ); ?>" <?php checked( $selected, $p ); ?> required>
						<span><strong><?php echo esc_html( RP_Packages::labels()[ $p ] ); ?></strong>
						<?php echo esc_html( RP_Settings::get( RP_Packages::EXTENDED === $p ? 'pris_paket1' : 'pris_paket2' ) ); ?> kr/mån</span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<div class="rp-form__grid">
				<p class="rp-field"><label for="rp-order-foretag">Företag *</label><input id="rp-order-foretag" name="rp_order_foretag" type="text" required autocomplete="organization" value="<?php echo esc_attr( $claim ? get_the_title( $claim ) : '' ); ?>"></p>
				<p class="rp-field"><label for="rp-order-orgnr">Organisationsnummer</label><input id="rp-order-orgnr" name="rp_order_orgnr" type="text" inputmode="numeric" placeholder="NNNNNN-NNNN" value="<?php echo esc_attr( $claim ? (string) get_post_meta( $claim, 'rp_orgnr', true ) : '' ); ?>"></p>
				<p class="rp-field"><label for="rp-order-kontakt">Kontaktperson *</label><input id="rp-order-kontakt" name="rp_order_kontakt" type="text" required autocomplete="name"></p>
				<p class="rp-field"><label for="rp-order-epost">E-post *</label><input id="rp-order-epost" name="rp_order_epost" type="email" required autocomplete="email"></p>
				<p class="rp-field"><label for="rp-order-telefon">Telefon</label><input id="rp-order-telefon" name="rp_order_telefon" type="tel" autocomplete="tel"></p>
				<p class="rp-field rp-field--wide"><label for="rp-order-meddelande">Meddelande</label><textarea id="rp-order-meddelande" name="rp_order_meddelande" rows="4"></textarea></p>
			</div>
			<?php echo RP_Forms::consent_field( 'Jag godkänner att mina uppgifter behandlas enligt {integritet}. *' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p><button type="submit" class="rp-button rp-button--primary rp-button--large">Skicka beställning</button></p>
			<p class="rp-form__note">Ingen betalning sker nu. Vi bekräftar beställningen och skickar faktura.</p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle order.
	 */
	public static function handle_submit(): void {
		RP_Forms::guard( 'rp_submit_order', 5 );
		$data = array(
			'rp_order_foretag'    => RP_Forms::post( 'rp_order_foretag' ),
			'rp_order_orgnr'      => RP_Forms::post( 'rp_order_orgnr' ),
			'rp_order_kontakt'    => RP_Forms::post( 'rp_order_kontakt' ),
			'rp_order_epost'      => sanitize_email( RP_Forms::post( 'rp_order_epost' ) ),
			'rp_order_telefon'    => RP_Forms::post( 'rp_order_telefon' ),
			'rp_order_paket'      => sanitize_key( RP_Forms::post( 'rp_order_paket' ) ),
			'rp_order_meddelande' => RP_Forms::post( 'rp_order_meddelande', true ),
		);
		if ( '' === $data['rp_order_foretag'] || '' === $data['rp_order_kontakt'] || ! is_email( $data['rp_order_epost'] ) || ! in_array( $data['rp_order_paket'], array( RP_Packages::EXTENDED, RP_Packages::LEADS ), true ) || '1' !== RP_Forms::post( 'rp_samtycke' ) ) {
			RP_Forms::redirect( 'order_invalid', '', 'bestall' );
		}

		$order_id = wp_insert_post(
			array(
				'post_type'   => RP_Post_Types::ORDER,
				'post_status' => 'publish',
				'post_title'  => $data['rp_order_foretag'] . ' – ' . RP_Packages::short_labels()[ $data['rp_order_paket'] ],
			)
		);
		if ( ! $order_id || is_wp_error( $order_id ) ) {
			RP_Forms::redirect( 'spam' );
		}
		foreach ( $data as $key => $value ) {
			update_post_meta( $order_id, $key, $value );
		}
		update_post_meta( $order_id, 'rp_order_status', 'ny' );

		// Link to an existing company: explicit claim, else org.nr/name match.
		$company = absint( RP_Forms::post( 'rp_order_foretag_id' ) );
		if ( ! $company || RP_Post_Types::COMPANY !== get_post_type( $company ) ) {
			$found   = RP_Importer::find_existing(
				array(
					'orgnr' => $data['rp_order_orgnr'],
					'namn'  => $data['rp_order_foretag'],
				)
			);
			$company = $found['id'];
		}
		update_post_meta( $order_id, 'rp_order_foretag_id', $company );

		RP_Forms::mail(
			RP_Settings::notify_email(),
			'[Rekryteringsportalen] Ny paketbeställning: ' . get_the_title( $order_id ),
			'<p>En ny beställning har kommit in.</p><p><strong>' . esc_html( RP_Packages::labels()[ $data['rp_order_paket'] ] ) . '</strong><br>' . esc_html( $data['rp_order_foretag'] ) . ' (' . esc_html( $data['rp_order_orgnr'] ) . ')<br>' . esc_html( $data['rp_order_kontakt'] ) . ', ' . esc_html( $data['rp_order_epost'] ) . ', ' . esc_html( $data['rp_order_telefon'] ) . '</p>'
			. '<p>' . nl2br( esc_html( $data['rp_order_meddelande'] ) ) . '</p><p><a href="' . esc_url( admin_url( 'post.php?action=edit&post=' . $order_id ) ) . '">Öppna beställningen</a></p>',
			$data['rp_order_epost']
		);
		RP_Forms::mail(
			$data['rp_order_epost'],
			'Tack för din beställning – ' . get_bloginfo( 'name' ),
			'<p>Hej ' . esc_html( $data['rp_order_kontakt'] ) . '!</p><p>Tack för att du beställt <strong>' . esc_html( RP_Packages::labels()[ $data['rp_order_paket'] ] ) . '</strong> för ' . esc_html( $data['rp_order_foretag'] ) . '. Vi återkommer inom en arbetsdag med bekräftelse och nästa steg.</p>'
		);

		/**
		 * Fires when a package order is created. Hook payment providers (WooCommerce, Stripe ...) here.
		 *
		 * @param int $order_id Order ID.
		 */
		do_action( 'rp_paketorder_created', $order_id );

		RP_Forms::redirect( 'order_sent', '', 'bestall' );
	}

	/**
	 * Company contact form (package 1+).
	 */
	public static function handle_contact(): void {
		RP_Forms::guard( 'rp_contact_company', 5 );
		$company = absint( RP_Forms::post( 'rp_foretag' ) );
		$name    = RP_Forms::post( 'rp_namn' );
		$email   = sanitize_email( RP_Forms::post( 'rp_epost' ) );
		$message = RP_Forms::post( 'rp_meddelande', true );
		$back    = $company ? get_permalink( $company ) : '';

		if ( ! $company || ! RP_Packages::has_extended( $company ) || '' === $name || ! is_email( $email ) || mb_strlen( $message ) < 10 || '1' !== RP_Forms::post( 'rp_samtycke' ) ) {
			RP_Forms::redirect( 'contact_invalid', (string) $back, 'kontakt' );
		}
		$to = (string) get_post_meta( $company, 'rp_epost', true );
		if ( ! is_email( $to ) ) {
			$to = RP_Leads::recipient_email( $company );
		}
		if ( $to ) {
			RP_Forms::mail(
				$to,
				'Nytt meddelande via ' . get_bloginfo( 'name' ),
				'<p><strong>' . esc_html( $name ) . '</strong> (' . esc_html( $email ) . ') har skickat ett meddelande via er företagspresentation:</p><blockquote style="border-left:3px solid #e4e8ee;margin:0;padding-left:12px">' . nl2br( esc_html( $message ) ) . '</blockquote>',
				$email
			);
		}
		RP_Forms::redirect( 'contact_sent', (string) $back, 'kontakt' );
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes(): void {
		add_meta_box( 'rp-order', 'Beställning', array( self::class, 'box' ), RP_Post_Types::ORDER, 'normal', 'high' );
	}

	/**
	 * Order box.
	 *
	 * @param WP_Post $post Order.
	 */
	public static function box( WP_Post $post ): void {
		wp_nonce_field( 'rp_order_save', 'rp_order_nonce' );
		$status  = (string) get_post_meta( $post->ID, 'rp_order_status', true );
		$paket   = (string) get_post_meta( $post->ID, 'rp_order_paket', true );
		$company = (int) get_post_meta( $post->ID, 'rp_order_foretag_id', true );
		$until   = (string) get_post_meta( $post->ID, 'rp_order_giltigt_till', true );
		$labels  = array(
			'rp_order_foretag'    => 'Företag',
			'rp_order_orgnr'      => 'Org.nr',
			'rp_order_kontakt'    => 'Kontaktperson',
			'rp_order_epost'      => 'E-post',
			'rp_order_telefon'    => 'Telefon',
			'rp_order_meddelande' => 'Meddelande',
		);
		echo '<table class="form-table"><tbody>';
		foreach ( $labels as $key => $label ) {
			echo '<tr><th>' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) get_post_meta( $post->ID, $key, true ) ) ) . '</td></tr>';
		}
		echo '<tr><th><label for="rp-order-paket">Paket</label></th><td><select id="rp-order-paket" name="rp_order_paket">';
		foreach ( array( RP_Packages::EXTENDED, RP_Packages::LEADS ) as $p ) {
			echo '<option value="' . esc_attr( $p ) . '" ' . selected( $paket, $p, false ) . '>' . esc_html( RP_Packages::labels()[ $p ] ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="rp-order-status">Status</label></th><td><select id="rp-order-status" name="rp_order_status">';
		foreach ( self::statuses() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><p class="description">När status sätts till <strong>Aktiv</strong> aktiveras paketet automatiskt på det kopplade företaget. <strong>Avslutad</strong> återställer till Inget paket.</p></td></tr>';
		echo '<tr><th><label for="rp-order-company">Kopplat företag</label></th><td>';
		echo self::company_dropdown( 'rp_order_foretag_id', $company, 'rp-order-company' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</td></tr>';
		echo '<tr><th><label for="rp-order-until">Giltigt till</label></th><td><input type="date" id="rp-order-until" name="rp_order_giltigt_till" value="' . esc_attr( $until ) . '"></td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Company <select> (get_pages()/wp_dropdown_pages() only work for hierarchical types).
	 *
	 * @param string $name     Field name.
	 * @param int    $selected Selected ID.
	 * @param string $id       Element id.
	 */
	public static function company_dropdown( string $name, int $selected, string $id ): string {
		$companies = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => array( 'publish', 'draft', 'pending' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$html      = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"><option value="0">— Inget (skapa företaget först) —</option>';
		foreach ( $companies as $c ) {
			$html .= '<option value="' . (int) $c->ID . '" ' . selected( $selected, $c->ID, false ) . '>' . esc_html( $c->post_title ) . ( 'publish' !== $c->post_status ? ' (utkast)' : '' ) . '</option>';
		}
		return $html . '</select>';
	}

	/**
	 * Save order; activates the package on the company when status is "aktiv".
	 *
	 * @param int     $post_id Order.
	 * @param WP_Post $post    Post.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST['rp_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rp_order_nonce'] ) ), 'rp_order_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status  = isset( $_POST['rp_order_status'] ) ? sanitize_key( wp_unslash( $_POST['rp_order_status'] ) ) : 'ny';
		$paket   = isset( $_POST['rp_order_paket'] ) ? sanitize_key( wp_unslash( $_POST['rp_order_paket'] ) ) : '';
		$company = isset( $_POST['rp_order_foretag_id'] ) ? absint( $_POST['rp_order_foretag_id'] ) : 0;
		$until   = isset( $_POST['rp_order_giltigt_till'] ) ? RP_Normalizer::date( sanitize_text_field( wp_unslash( $_POST['rp_order_giltigt_till'] ) ) ) : '';
		$old     = (string) get_post_meta( $post_id, 'rp_order_status', true );

		if ( isset( self::statuses()[ $status ] ) ) {
			update_post_meta( $post_id, 'rp_order_status', $status );
		}
		if ( in_array( $paket, array( RP_Packages::EXTENDED, RP_Packages::LEADS ), true ) ) {
			update_post_meta( $post_id, 'rp_order_paket', $paket );
		}
		update_post_meta( $post_id, 'rp_order_foretag_id', $company );
		update_post_meta( $post_id, 'rp_order_giltigt_till', $until );

		if ( $company && 'aktiv' === $status ) {
			RP_Packages::set( $company, $paket, $until );
		} elseif ( $company && 'avslutad' === $status && 'avslutad' !== $old ) {
			RP_Packages::set( $company, RP_Packages::NONE );
		}
	}

	/**
	 * Columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( $cols ): array {
		return array(
			'cb'         => $cols['cb'] ?? '',
			'title'      => 'Beställning',
			'rp_status'  => 'Status',
			'rp_kontakt' => 'Kontakt',
			'date'       => 'Datum',
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Order.
	 */
	public static function column( $col, $post_id ): void {
		if ( 'rp_status' === $col ) {
			$s = (string) get_post_meta( $post_id, 'rp_order_status', true );
			echo '<span class="rp-badge rp-badge--' . esc_attr( $s ) . '">' . esc_html( self::statuses()[ $s ] ?? $s ) . '</span>';
		} elseif ( 'rp_kontakt' === $col ) {
			echo esc_html( get_post_meta( $post_id, 'rp_order_kontakt', true ) . ', ' . get_post_meta( $post_id, 'rp_order_epost', true ) );
		}
	}
}
