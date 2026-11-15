<?php
/**
 * Leads: companies that need to recruit are matched with package-2 recruitment companies.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lead form, matching, distribution and admin UI.
 */
final class RP_Leads {

	/**
	 * Lead fields (meta key => label).
	 *
	 * @return array<string, string>
	 */
	public static function fields(): array {
		return array(
			'rp_lead_foretag'    => 'Företag',
			'rp_lead_kontakt'    => 'Kontaktperson',
			'rp_lead_epost'      => 'E-post',
			'rp_lead_telefon'    => 'Telefon',
			'rp_lead_roll'       => 'Roll som ska rekryteras',
			'rp_lead_bransch'    => 'Bransch',
			'rp_lead_region'     => 'Region',
			'rp_lead_antal'      => 'Antal tjänster',
			'rp_lead_start'      => 'Önskad start',
			'rp_lead_meddelande' => 'Övrigt',
		);
	}

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_shortcode( 'rp_lead_formular', array( self::class, 'shortcode' ) );
		add_action( 'admin_post_rp_submit_lead', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_rp_submit_lead', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_rp_resend_lead', array( self::class, 'handle_resend' ) );
		add_action( 'add_meta_boxes_' . RP_Post_Types::LEAD, array( self::class, 'meta_boxes' ) );
		add_filter( 'manage_' . RP_Post_Types::LEAD . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . RP_Post_Types::LEAD . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
	}

	/**
	 * Lead form.
	 */
	public static function shortcode(): string {
		$branscher = get_terms(
			array(
				'taxonomy'   => RP_Post_Types::TAX_INDUSTRY,
				'hide_empty' => false,
			)
		);
		$regioner  = get_terms(
			array(
				'taxonomy'   => RP_Post_Types::TAX_REGION,
				'hide_empty' => false,
				'parent'     => 0,
			)
		);

		ob_start();
		echo RP_Forms::notice( 'lead' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<form class="rp-form rp-lead-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php echo RP_Forms::hidden_fields( 'rp_submit_lead' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="rp-form__grid">
				<p class="rp-field"><label for="rp-lead-foretag">Företag *</label><input id="rp-lead-foretag" name="rp_lead_foretag" type="text" required autocomplete="organization"></p>
				<p class="rp-field"><label for="rp-lead-kontakt">Kontaktperson *</label><input id="rp-lead-kontakt" name="rp_lead_kontakt" type="text" required autocomplete="name"></p>
				<p class="rp-field"><label for="rp-lead-epost">E-post *</label><input id="rp-lead-epost" name="rp_lead_epost" type="email" required autocomplete="email"></p>
				<p class="rp-field"><label for="rp-lead-telefon">Telefon</label><input id="rp-lead-telefon" name="rp_lead_telefon" type="tel" autocomplete="tel"></p>
				<p class="rp-field rp-field--wide"><label for="rp-lead-roll">Vilken roll ska ni rekrytera? *</label><input id="rp-lead-roll" name="rp_lead_roll" type="text" required placeholder="T.ex. Ekonomichef, Backend-utvecklare"></p>
				<p class="rp-field"><label for="rp-lead-bransch">Bransch</label>
					<select id="rp-lead-bransch" name="rp_lead_bransch">
						<option value="">Välj bransch</option>
						<?php foreach ( is_array( $branscher ) ? $branscher : array() as $t ) : ?>
							<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="rp-field"><label for="rp-lead-region">Region</label>
					<select id="rp-lead-region" name="rp_lead_region">
						<option value="">Välj region</option>
						<?php foreach ( is_array( $regioner ) ? $regioner : array() as $t ) : ?>
							<option value="<?php echo esc_attr( $t->slug ); ?>"><?php echo esc_html( $t->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="rp-field"><label for="rp-lead-antal">Antal tjänster</label><input id="rp-lead-antal" name="rp_lead_antal" type="number" min="1" max="999" value="1"></p>
				<p class="rp-field"><label for="rp-lead-start">Önskad start</label>
					<select id="rp-lead-start" name="rp_lead_start">
						<option>Så snart som möjligt</option>
						<option>Inom 1–3 månader</option>
						<option>Inom 3–6 månader</option>
						<option>Längre fram / undersöker</option>
					</select>
				</p>
				<p class="rp-field rp-field--wide"><label for="rp-lead-meddelande">Berätta mer om behovet</label><textarea id="rp-lead-meddelande" name="rp_lead_meddelande" rows="5"></textarea></p>
			</div>
			<?php echo RP_Forms::consent_field( 'Jag godkänner att uppgifterna delas med upp till ' . (int) RP_Settings::get( 'max_lead_mottagare' ) . ' matchande rekryteringsföretag enligt {integritet}. *' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p><button type="submit" class="rp-button rp-button--primary rp-button--large">Skicka förfrågan</button></p>
			<p class="rp-form__note">Kostnadsfritt och utan förpliktelser. Max <?php echo (int) RP_Settings::get( 'max_lead_mottagare' ); ?> rekryteringsföretag kontaktar dig.</p>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle submission.
	 */
	public static function handle_submit(): void {
		RP_Forms::guard( 'rp_submit_lead', 5 );

		$values = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$values[ $key ] = RP_Forms::post( $key, 'rp_lead_meddelande' === $key );
		}
		$values['rp_lead_epost'] = sanitize_email( $values['rp_lead_epost'] );

		if ( '' === $values['rp_lead_foretag'] || '' === $values['rp_lead_kontakt'] || ! is_email( $values['rp_lead_epost'] ) || '' === $values['rp_lead_roll'] || '1' !== RP_Forms::post( 'rp_samtycke' ) ) {
			RP_Forms::redirect( 'lead_invalid' );
		}

		$lead_id = wp_insert_post(
			array(
				'post_type'   => RP_Post_Types::LEAD,
				'post_status' => 'publish',
				'post_title'  => $values['rp_lead_foretag'] . ' – ' . $values['rp_lead_roll'],
			)
		);
		if ( ! $lead_id || is_wp_error( $lead_id ) ) {
			RP_Forms::redirect( 'spam' );
		}
		foreach ( $values as $key => $value ) {
			update_post_meta( $lead_id, $key, $value );
		}
		update_post_meta( $lead_id, 'rp_lead_samtycke', current_time( 'mysql' ) );

		$recipients = self::match( $lead_id );
		self::distribute( $lead_id, $recipients );

		RP_Forms::mail(
			RP_Settings::notify_email(),
			'[Rekryteringsportalen] Nytt lead: ' . get_the_title( $lead_id ),
			'<p>Ett nytt lead har kommit in och skickats till ' . count( $recipients ) . ' företag.</p><p><a href="' . esc_url( admin_url( 'post.php?action=edit&post=' . $lead_id ) ) . '">Öppna leadet</a></p>'
		);

		/**
		 * Fires after a lead has been created and distributed.
		 *
		 * @param int   $lead_id    Lead.
		 * @param int[] $recipients Company IDs.
		 */
		do_action( 'rp_lead_created', $lead_id, $recipients );

		RP_Forms::redirect( 'lead_sent' );
	}

	/**
	 * Candidate companies with an active package 2.
	 *
	 * @return array<int, array{id:int, branscher:string[], regioner:string[], senaste_lead:int}>
	 */
	public static function candidates(): array {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'rp_paket', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => RP_Packages::LEADS, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			if ( ! RP_Packages::receives_leads( $id ) || '' === self::recipient_email( $id ) ) {
				continue;
			}
			$b     = wp_get_object_terms( $id, RP_Post_Types::TAX_INDUSTRY, array( 'fields' => 'slugs' ) );
			$r     = wp_get_object_terms( $id, RP_Post_Types::TAX_REGION, array( 'fields' => 'slugs' ) );
			$out[] = array(
				'id'           => (int) $id,
				'branscher'    => is_wp_error( $b ) ? array() : $b,
				'regioner'     => is_wp_error( $r ) ? array() : $r,
				'senaste_lead' => (int) get_post_meta( $id, 'rp_senaste_lead', true ),
			);
		}
		return $out;
	}

	/**
	 * Matching recipients for a lead.
	 *
	 * @param int $lead_id Lead.
	 * @return int[]
	 */
	public static function match( int $lead_id ): array {
		return RP_Lead_Matcher::select(
			self::candidates(),
			(string) get_post_meta( $lead_id, 'rp_lead_bransch', true ),
			(string) get_post_meta( $lead_id, 'rp_lead_region', true ),
			(int) RP_Settings::get( 'max_lead_mottagare' )
		);
	}

	/**
	 * Where a company receives leads.
	 *
	 * @param int $company_id Company.
	 */
	public static function recipient_email( int $company_id ): string {
		$email = (string) get_post_meta( $company_id, 'rp_leads_epost', true );
		if ( ! is_email( $email ) ) {
			$email = (string) get_post_meta( $company_id, 'rp_epost', true );
		}
		return is_email( $email ) ? $email : '';
	}

	/**
	 * E-mail the lead to companies and log it.
	 *
	 * @param int   $lead_id    Lead.
	 * @param int[] $recipients Company IDs.
	 */
	public static function distribute( int $lead_id, array $recipients ): void {
		$log  = get_post_meta( $lead_id, 'rp_lead_logg', true );
		$log  = is_array( $log ) ? $log : array();
		$rows = '';
		foreach ( self::fields() as $key => $label ) {
			$value = (string) get_post_meta( $lead_id, $key, true );
			if ( 'rp_lead_bransch' === $key || 'rp_lead_region' === $key ) {
				$tax   = 'rp_lead_bransch' === $key ? RP_Post_Types::TAX_INDUSTRY : RP_Post_Types::TAX_REGION;
				$term  = '' !== $value ? get_term_by( 'slug', $value, $tax ) : false;
				$value = $term ? $term->name : $value;
			}
			if ( '' === $value ) {
				continue;
			}
			$rows .= '<tr><th align="left" style="padding:6px 12px 6px 0;vertical-align:top;color:#6b7785">' . esc_html( $label ) . '</th><td style="padding:6px 0">' . nl2br( esc_html( $value ) ) . '</td></tr>';
		}

		foreach ( $recipients as $company_id ) {
			$email = self::recipient_email( $company_id );
			if ( '' === $email ) {
				continue;
			}
			$body  = '<p>Hej ' . esc_html( get_the_title( $company_id ) ) . '!</p>'
				. '<p>Ett företag som ska rekrytera har skickat en förfrågan via ' . esc_html( get_bloginfo( 'name' ) ) . ' och matchar er profil. Kontakta dem gärna direkt.</p>'
				. '<table style="border-collapse:collapse;margin:16px 0">' . $rows . '</table>'
				. '<p style="color:#6b7785;font-size:13px">Förfrågan har skickats till max ' . (int) RP_Settings::get( 'max_lead_mottagare' ) . ' rekryteringsföretag. Ni får leads eftersom ni har Paket 2.</p>';
			$sent  = RP_Forms::mail( $email, 'Nytt rekryteringsuppdrag: ' . get_the_title( $lead_id ), $body, (string) get_post_meta( $lead_id, 'rp_lead_epost', true ) );
			$log[] = array(
				'foretag' => $company_id,
				'epost'   => $email,
				'tid'     => current_time( 'mysql' ),
				'status'  => $sent ? 'skickat' : 'misslyckades',
			);
			update_post_meta( $company_id, 'rp_senaste_lead', time() );
			$count = (int) get_post_meta( $company_id, 'rp_antal_leads', true );
			update_post_meta( $company_id, 'rp_antal_leads', $count + 1 );
		}
		update_post_meta( $lead_id, 'rp_lead_logg', $log );
	}

	/**
	 * Admin: resend to automatic matches or chosen companies.
	 */
	public static function handle_resend(): void {
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		check_admin_referer( 'rp_resend_lead_' . $lead_id );
		if ( ! current_user_can( 'edit_post', $lead_id ) ) {
			wp_die( 'Behörighet saknas.' );
		}
		$chosen = isset( $_POST['rp_mottagare'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['rp_mottagare'] ) ) : array();
		$chosen = array_filter( $chosen, array( RP_Packages::class, 'receives_leads' ) );
		self::distribute( $lead_id, $chosen ? $chosen : self::match( $lead_id ) );
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $lead_id . '&rp_resent=1' ) );
		exit;
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes(): void {
		add_meta_box( 'rp-lead-data', 'Förfrågan', array( self::class, 'box_data' ), RP_Post_Types::LEAD, 'normal', 'high' );
		add_meta_box( 'rp-lead-log', 'Skickat till', array( self::class, 'box_log' ), RP_Post_Types::LEAD, 'normal' );
		add_meta_box( 'rp-lead-resend', 'Skicka om', array( self::class, 'box_resend' ), RP_Post_Types::LEAD, 'side' );
	}

	/**
	 * Lead data.
	 *
	 * @param WP_Post $post Lead.
	 */
	public static function box_data( WP_Post $post ): void {
		echo '<table class="widefat striped"><tbody>';
		foreach ( self::fields() as $key => $label ) {
			echo '<tr><th style="width:200px">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) get_post_meta( $post->ID, $key, true ) ) ) . '</td></tr>';
		}
		echo '<tr><th>Samtycke</th><td>' . esc_html( (string) get_post_meta( $post->ID, 'rp_lead_samtycke', true ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	/**
	 * Distribution log.
	 *
	 * @param WP_Post $post Lead.
	 */
	public static function box_log( WP_Post $post ): void {
		$log = get_post_meta( $post->ID, 'rp_lead_logg', true );
		if ( ! is_array( $log ) || ! $log ) {
			echo '<p>Inte skickat till något företag ännu (inga matchande företag med Paket 2).</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Företag</th><th>E-post</th><th>Tid</th><th>Status</th></tr></thead><tbody>';
		foreach ( $log as $row ) {
			printf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_url( (string) get_edit_post_link( (int) $row['foretag'] ) ),
				esc_html( get_the_title( (int) $row['foretag'] ) ),
				esc_html( $row['epost'] ),
				esc_html( $row['tid'] ),
				esc_html( $row['status'] )
			);
		}
		echo '</tbody></table>';
	}

	/**
	 * Resend form. Rendered as a separate form via the `form` attribute to avoid nesting in the post form.
	 *
	 * @param WP_Post $post Lead.
	 */
	public static function box_resend( WP_Post $post ): void {
		$form = 'rp-resend-form';
		echo '<p>Välj mottagare manuellt, eller lämna tomt för automatisk matchning.</p>';
		foreach ( self::candidates() as $c ) {
			printf(
				'<label style="display:block"><input type="checkbox" form="%1$s" name="rp_mottagare[]" value="%2$d"> %3$s</label>',
				esc_attr( $form ),
				(int) $c['id'],
				esc_html( get_the_title( $c['id'] ) )
			);
		}
		printf(
			'<p><button type="submit" form="%s" class="button">Skicka lead</button></p>',
			esc_attr( $form )
		);
		add_action(
			'admin_footer',
			static function () use ( $form, $post ) {
				printf(
					'<form id="%1$s" method="post" action="%2$s"><input type="hidden" name="action" value="rp_resend_lead"><input type="hidden" name="lead_id" value="%3$d">%4$s</form>',
					esc_attr( $form ),
					esc_url( admin_url( 'admin-post.php' ) ),
					(int) $post->ID,
					wp_nonce_field( 'rp_resend_lead_' . $post->ID, '_wpnonce', true, false ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
		);
	}

	/**
	 * List columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( $cols ): array {
		return array(
			'cb'           => $cols['cb'] ?? '',
			'title'        => 'Lead',
			'rp_kontakt'   => 'Kontakt',
			'rp_mottagare' => 'Skickat till',
			'date'         => 'Datum',
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $col     Column.
	 * @param int    $post_id Lead.
	 */
	public static function column( $col, $post_id ): void {
		if ( 'rp_kontakt' === $col ) {
			echo esc_html( get_post_meta( $post_id, 'rp_lead_kontakt', true ) . ' <' . get_post_meta( $post_id, 'rp_lead_epost', true ) . '>' );
		} elseif ( 'rp_mottagare' === $col ) {
			$log = get_post_meta( $post_id, 'rp_lead_logg', true );
			echo esc_html( (string) ( is_array( $log ) ? count( $log ) : 0 ) );
		}
	}
}
