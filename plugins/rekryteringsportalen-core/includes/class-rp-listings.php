<?php
/**
 * Free listing: recruitment companies that are missing from the site can submit themselves.
 *
 * Submissions become companies with status "pending" (Väntar på granskning) and source "anmälan".
 * When an admin publishes one, the contact person is e-mailed a link to the new page.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Listing form.
 */
final class RP_Listings {

	public const SOURCE = 'anmälan';
	public const PAGE   = 'lagg-till-foretag';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_shortcode( 'rp_anmal_foretag', array( self::class, 'shortcode' ) );
		add_action( 'admin_post_rp_submit_listing', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_rp_submit_listing', array( self::class, 'handle_submit' ) );
		add_action( 'transition_post_status', array( self::class, 'on_publish' ), 10, 3 );
		add_action( 'rp_daily', array( self::class, 'purge_contacts' ) );
		add_action( 'add_meta_boxes_' . RP_Post_Types::COMPANY, array( self::class, 'meta_box' ) );
	}

	/**
	 * Admin: show who submitted the company.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function meta_box( $post ): void {
		if ( $post instanceof WP_Post && is_array( get_post_meta( $post->ID, 'rp_anmalan', true ) ) ) {
			add_meta_box( 'rp-anmalan', 'Anmälan', array( self::class, 'render_meta_box' ), RP_Post_Types::COMPANY, 'side', 'high' );
		}
	}

	/**
	 * Meta box content.
	 *
	 * @param WP_Post $post Company.
	 */
	public static function render_meta_box( WP_Post $post ): void {
		$c = (array) get_post_meta( $post->ID, 'rp_anmalan', true );
		echo '<p>Anmält via formuläret ' . esc_html( (string) ( $c['tid'] ?? '' ) ) . ' av:</p><p><strong>' . esc_html( (string) ( $c['kontakt'] ?? '' ) ) . '</strong><br><a href="mailto:' . esc_attr( (string) ( $c['epost'] ?? '' ) ) . '">' . esc_html( (string) ( $c['epost'] ?? '' ) ) . '</a></p>';
		echo '<p class="description">När du publicerar företaget får kontaktpersonen ett mejl med länk till sidan. Därefter raderas kontaktuppgifterna.</p>';
	}

	/**
	 * Remove submitter contact data that is older than 90 days (submissions that were never published).
	 */
	public static function purge_contacts(): void {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_key'       => 'rp_anmalan', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'date_query'     => array( array( 'before' => '90 days ago' ) ),
			)
		);
		foreach ( $ids as $id ) {
			delete_post_meta( (int) $id, 'rp_anmalan' );
		}
	}

	/**
	 * Form.
	 */
	public static function shortcode(): string {
		$services   = get_terms(
			array(
				'taxonomy'   => RP_Post_Types::TAX_SERVICE,
				'hide_empty' => false,
				'parent'     => 0,
			)
		);
		$industries = get_terms(
			array(
				'taxonomy'   => RP_Post_Types::TAX_INDUSTRY,
				'hide_empty' => false,
				'parent'     => 0,
			)
		);
		$regions    = get_terms(
			array(
				'taxonomy'   => RP_Post_Types::TAX_REGION,
				'hide_empty' => false,
				'parent'     => 0,
			)
		);

		$html = RP_Forms::notice( 'listing' );

		// When the company already exists and is published, link to it.
		$found = isset( $_GET['rp_finns'] ) ? absint( $_GET['rp_finns'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $found && RP_Post_Types::COMPANY === get_post_type( $found ) && 'publish' === get_post_status( $found ) ) {
			$html .= '<p class="rp-notice rp-notice--success"><a href="' . esc_url( (string) get_permalink( $found ) ) . '">' . esc_html( get_the_title( $found ) ) . '</a> finns redan på Rekryteringsportalen. Vill ni ha en utökad presentation? <a href="' . esc_url( add_query_arg( 'uppgradera', $found, rp_page_url( 'for-foretag' ) ) . '#bestall' ) . '">Se annonspaketen</a>.</p>';
		}

		$checkboxes = static function ( $terms, string $name ): string {
			if ( ! is_array( $terms ) || ! $terms ) {
				return '';
			}
			$out = '<div class="rp-checkgrid">';
			foreach ( $terms as $t ) {
				$out .= '<label><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $t->slug ) . '"> ' . esc_html( $t->name ) . '</label>';
			}
			return $out . '</div>';
		};

		$html .= '<form class="rp-form rp-listing-form" id="anmal" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. RP_Forms::hidden_fields( 'rp_submit_listing' )
			. '<h3 class="rp-form__section">Om företaget</h3><div class="rp-form__grid">'
			. '<p class="rp-field"><label for="rp-l-namn">Företagsnamn *</label><input id="rp-l-namn" name="rp_l_namn" type="text" required maxlength="120" autocomplete="organization"></p>'
			. '<p class="rp-field"><label for="rp-l-orgnr">Organisationsnummer *</label><input id="rp-l-orgnr" name="rp_l_orgnr" type="text" required inputmode="numeric" placeholder="NNNNNN-NNNN" pattern="[0-9]{6}-?[0-9]{4}" title="10 siffror, t.ex. 556677-8899"></p>'
			. '<p class="rp-field"><label for="rp-l-webbplats">Webbplats *</label><input id="rp-l-webbplats" name="rp_l_webbplats" type="text" required placeholder="www.foretaget.se" inputmode="url"></p>'
			. '<p class="rp-field"><label for="rp-l-ort">Ort (huvudkontor)</label><input id="rp-l-ort" name="rp_l_ort" type="text" maxlength="80"></p>'
			. '<p class="rp-field"><label for="rp-l-epost-f">E-post till företaget</label><input id="rp-l-epost-f" name="rp_l_foretag_epost" type="email" placeholder="info@foretaget.se"><span class="rp-field__help">Visas på företagssidan.</span></p>'
			. '<p class="rp-field"><label for="rp-l-telefon">Telefon</label><input id="rp-l-telefon" name="rp_l_telefon" type="tel"><span class="rp-field__help">Visas på företagssidan.</span></p>'
			. '<p class="rp-field rp-field--wide"><label for="rp-l-beskrivning">Kort beskrivning *</label><textarea id="rp-l-beskrivning" name="rp_l_beskrivning" rows="3" required minlength="30" maxlength="300" placeholder="Vad ni är specialiserade på och vilka ni hjälper (max 300 tecken)."></textarea></p>'
			. '</div>'
			. '<fieldset class="rp-field rp-field--wide"><legend>Tjänster</legend>' . $checkboxes( $services, 'rp_l_tjanster' ) . '</fieldset>'
			. '<fieldset class="rp-field rp-field--wide"><legend>Branscher ni rekryterar inom</legend>' . $checkboxes( $industries, 'rp_l_branscher' ) . '</fieldset>'
			. '<fieldset class="rp-field rp-field--wide"><legend>Regioner ni är verksamma i</legend>' . $checkboxes( $regions, 'rp_l_regioner' ) . '</fieldset>'
			. '<h3 class="rp-form__section">Kontaktperson</h3><p class="rp-form__note">Visas inte publikt. Vi hör av oss om vi har frågor och meddelar när sidan är publicerad.</p><div class="rp-form__grid">'
			. '<p class="rp-field"><label for="rp-l-kontakt">Namn *</label><input id="rp-l-kontakt" name="rp_l_kontakt" type="text" required autocomplete="name"></p>'
			. '<p class="rp-field"><label for="rp-l-epost">E-post *</label><input id="rp-l-epost" name="rp_l_epost" type="email" required autocomplete="email"></p>'
			. '</div>'
			. RP_Forms::consent_field( 'Jag intygar att jag företräder företaget och godkänner att uppgifterna publiceras på Rekryteringsportalen och behandlas enligt {integritet}. *' )
			. '<p><button type="submit" class="rp-button rp-button--primary rp-button--large">Skicka – lägg till gratis</button></p>'
			. '<p class="rp-form__note">Grundpresentationen är alltid gratis. Vi granskar alla anmälningar innan de publiceras, normalt inom två arbetsdagar.</p>'
			. '</form>';
		return $html;
	}

	/**
	 * Read and validate the posted form.
	 *
	 * @return array<string, mixed>|null Data for create(), or null when invalid.
	 */
	public static function read_post(): ?array {
		$list  = static function ( string $key ): array {
			$raw = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in guard(); sanitized below.
			return array_values( array_filter( array_map( 'sanitize_title', array_map( 'strval', $raw ) ) ) );
		};
		$data  = array(
			'namn'             => RP_Forms::post( 'rp_l_namn' ),
			'orgnr'            => RP_Normalizer::orgnr( RP_Forms::post( 'rp_l_orgnr' ) ),
			'webbplats'        => RP_Forms::post( 'rp_l_webbplats' ),
			'ort'              => RP_Forms::post( 'rp_l_ort' ),
			'epost'            => sanitize_email( RP_Forms::post( 'rp_l_foretag_epost' ) ),
			'telefon'          => RP_Forms::post( 'rp_l_telefon' ),
			'kort_beskrivning' => RP_Forms::post( 'rp_l_beskrivning', true ),
			'tjanster'         => $list( 'rp_l_tjanster' ),
			'branscher'        => $list( 'rp_l_branscher' ),
			'regioner'         => $list( 'rp_l_regioner' ),
			'kontakt'          => RP_Forms::post( 'rp_l_kontakt' ),
			'kontakt_epost'    => sanitize_email( RP_Forms::post( 'rp_l_epost' ) ),
		);
		$valid = '' !== $data['namn']
			&& '' !== $data['orgnr']
			&& '' !== RP_Normalizer::domain( $data['webbplats'] )
			&& mb_strlen( $data['kort_beskrivning'] ) >= 30
			&& '' !== $data['kontakt']
			&& is_email( $data['kontakt_epost'] )
			&& '1' === RP_Forms::post( 'rp_samtycke' );
		return $valid ? $data : null;
	}

	/**
	 * Create a pending company from a submission, unless it already exists.
	 *
	 * @param array<string, mixed> $data Validated data from read_post().
	 * @return array{status:string, id:int} status: created | exists (published) | pending (already submitted/draft)
	 */
	public static function create( array $data ): array {
		$found = RP_Importer::find_existing(
			array(
				'orgnr'     => (string) $data['orgnr'],
				'webbplats' => (string) $data['webbplats'],
				'namn'      => (string) $data['namn'],
			)
		);
		if ( $found['id'] ) {
			return array(
				'status' => 'publish' === get_post_status( $found['id'] ) ? 'exists' : 'pending',
				'id'     => $found['id'],
			);
		}

		// Terms are chosen from existing slugs only (the public form never creates terms) and set by ID.
		$term_ids = static function ( array $slugs, string $tax ): array {
			$out = array();
			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', (string) $slug, $tax );
				if ( $term ) {
					$out[] = (int) $term->term_id;
				}
			}
			return $out;
		};

		$result = RP_Importer::upsert_company(
			array(
				'namn'             => (string) $data['namn'],
				'orgnr'            => (string) $data['orgnr'],
				'webbplats'        => (string) $data['webbplats'],
				'ort'              => (string) $data['ort'],
				'epost'            => (string) $data['epost'],
				'telefon'          => (string) $data['telefon'],
				'kort_beskrivning' => (string) $data['kort_beskrivning'],
				'kalla'            => self::SOURCE,
			),
			array( 'status' => 'pending' )
		);
		if ( ! $result['id'] || 'created' !== $result['action'] ) {
			return array(
				'status' => 'error',
				'id'     => 0,
			);
		}

		foreach ( array(
			'tjanster'  => RP_Post_Types::TAX_SERVICE,
			'branscher' => RP_Post_Types::TAX_INDUSTRY,
			'regioner'  => RP_Post_Types::TAX_REGION,
		) as $key => $tax ) {
			$ids = $term_ids( (array) $data[ $key ], $tax );
			if ( $ids ) {
				wp_set_object_terms( $result['id'], $ids, $tax );
			}
		}

		update_post_meta(
			$result['id'],
			'rp_anmalan',
			array(
				'kontakt' => (string) $data['kontakt'],
				'epost'   => (string) $data['kontakt_epost'],
				'tid'     => current_time( 'mysql' ),
			)
		);

		/**
		 * Fires when a company has been submitted through the free listing form.
		 *
		 * @param int                  $id   Company (status pending).
		 * @param array<string, mixed> $data Submitted data.
		 */
		do_action( 'rp_listing_submitted', $result['id'], $data );

		return array(
			'status' => 'created',
			'id'     => $result['id'],
		);
	}

	/**
	 * Handle the form.
	 */
	public static function handle_submit(): void {
		RP_Forms::guard( 'rp_submit_listing', 5 );
		$data = self::read_post();
		$back = rp_page_url( self::PAGE );
		if ( null === $data ) {
			RP_Forms::redirect( 'listing_invalid', '', 'anmal' );
		}

		$res = self::create( $data );
		if ( 'exists' === $res['status'] ) {
			RP_Forms::redirect( 'listing_exists', add_query_arg( 'rp_finns', $res['id'], $back ), 'anmal' );
		}
		if ( 'pending' === $res['status'] ) {
			RP_Forms::redirect( 'listing_pending', $back, 'anmal' );
		}
		if ( 'created' !== $res['status'] ) {
			RP_Forms::redirect( 'spam', $back, 'anmal' );
		}

		$id = $res['id'];
		RP_Forms::mail(
			RP_Settings::notify_email(),
			'[Rekryteringsportalen] Nytt företag att granska: ' . get_the_title( $id ),
			'<p><strong>' . esc_html( get_the_title( $id ) ) . '</strong> (' . esc_html( (string) $data['orgnr'] ) . ') har anmält sig för att komma med på listan.</p>'
			. '<p>Kontakt: ' . esc_html( (string) $data['kontakt'] ) . ', ' . esc_html( (string) $data['kontakt_epost'] ) . '<br>Webbplats: ' . esc_html( (string) $data['webbplats'] ) . '</p>'
			. '<p>' . nl2br( esc_html( (string) $data['kort_beskrivning'] ) ) . '</p>'
			. '<p><a href="' . esc_url( admin_url( 'post.php?action=edit&post=' . $id ) ) . '">Granska och publicera</a></p>',
			(string) $data['kontakt_epost']
		);
		RP_Forms::mail(
			(string) $data['kontakt_epost'],
			'Tack för er anmälan till ' . get_bloginfo( 'name' ),
			'<p>Hej ' . esc_html( (string) $data['kontakt'] ) . '!</p><p>Tack för att ni anmält <strong>' . esc_html( get_the_title( $id ) ) . '</strong>. Vi granskar uppgifterna och publicerar företagssidan normalt inom två arbetsdagar. Ni får ett mejl när den är publicerad.</p>'
		);
		RP_Forms::redirect( 'listing_sent', $back, 'anmal' );
	}

	/**
	 * When a submitted company is published: e-mail the contact person once, then drop the contact data.
	 *
	 * @param string  $new_status New.
	 * @param string  $old_status Old.
	 * @param WP_Post $post       Post.
	 */
	public static function on_publish( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || RP_Post_Types::COMPANY !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		$contact = get_post_meta( $post->ID, 'rp_anmalan', true );
		if ( ! is_array( $contact ) || ! is_email( $contact['epost'] ?? '' ) ) {
			return;
		}
		RP_Forms::mail(
			(string) $contact['epost'],
			get_the_title( $post ) . ' finns nu på ' . get_bloginfo( 'name' ),
			'<p>Hej ' . esc_html( (string) $contact['kontakt'] ) . '!</p><p>Nu är <strong>' . esc_html( get_the_title( $post ) ) . '</strong> publicerat på Rekryteringsportalen:</p>'
			. '<p><a href="' . esc_url( (string) get_permalink( $post ) ) . '" style="display:inline-block;background:#1E4E8C;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">Se er företagssida</a></p>'
			. '<p>Vill ni synas mer, med bilder, team, kundcase och möjlighet att svara på omdömen, eller få förfrågningar från företag som ska rekrytera? <a href="' . esc_url( add_query_arg( 'uppgradera', $post->ID, rp_page_url( 'for-foretag' ) ) . '#bestall' ) . '">Se annonspaketen</a>.</p>'
		);
		// The contact details are only needed for this e-mail (data minimisation).
		delete_post_meta( $post->ID, 'rp_anmalan' );
	}
}
