<?php
/**
 * Reviews (1–5 stars + mandatory comment) stored as WordPress comments of type `rp_omdome`.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reviews and rating aggregates.
 */
final class RP_Ratings {

	public const TYPE        = 'rp_omdome';
	public const UNCONFIRMED = 'rp_obekraftad';
	public const MIN_LENGTH  = 20;
	public const MAX_LENGTH  = 3000;

	/**
	 * Days during which the same visitor cannot review the same company again.
	 */
	public const REPEAT_DAYS = 90;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_rp_submit_review', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_rp_submit_review', array( self::class, 'handle_submit' ) );
		add_action( 'admin_post_rp_report_review', array( self::class, 'handle_report' ) );
		add_action( 'admin_post_nopriv_rp_report_review', array( self::class, 'handle_report' ) );
		add_action( 'template_redirect', array( self::class, 'handle_confirm' ) );

		add_action( 'transition_comment_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'wp_insert_comment', array( self::class, 'on_insert' ), 10, 2 );
		add_action( 'deleted_comment', array( self::class, 'on_deleted' ), 10, 2 );
		add_action( 'rp_recalc_bayes', array( self::class, 'recalc_all' ) );
		add_action( 'rp_daily', array( self::class, 'purge_unconfirmed' ) );
		add_action( 'rp_daily', array( self::class, 'purge_fingerprints' ) );

		// Block the generic comment form on companies – reviews must go through our validated handler.
		add_filter( 'comments_open', array( self::class, 'close_generic_comments' ), 10, 2 );
		add_filter( 'pings_open', array( self::class, 'close_generic_comments' ), 10, 2 );
		add_filter( 'admin_comment_types_dropdown', array( self::class, 'comment_types_dropdown' ) );
	}

	/**
	 * Reviewer roles.
	 *
	 * @return array<string, string>
	 */
	public static function roles(): array {
		return array(
			'kandidat' => 'Kandidat',
			'kund'     => 'Kund/uppdragsgivare',
			'anstalld' => 'Anställd',
		);
	}

	/**
	 * Generic comments are closed on companies.
	 *
	 * @param bool $open    Open.
	 * @param int  $post_id Post.
	 */
	public static function close_generic_comments( $open, $post_id ): bool {
		return RP_Post_Types::COMPANY === get_post_type( $post_id ) ? false : (bool) $open;
	}

	/**
	 * Add "Omdömen" to the comment type filter in admin.
	 *
	 * @param array<string, string> $types Types.
	 * @return array<string, string>
	 */
	public static function comment_types_dropdown( $types ): array {
		$types[ self::TYPE ] = 'Omdömen';
		return $types;
	}

	/**
	 * Handle review submission.
	 */
	public static function handle_submit(): void {
		RP_Forms::guard( 'rp_submit_review', (int) RP_Settings::get( 'omdomen_per_timme' ) );

		$post_id = absint( RP_Forms::post( 'rp_foretag' ) );
		$rating  = (int) RP_Forms::post( 'rp_betyg' );
		$text    = trim( RP_Forms::post( 'rp_kommentar', true ) );
		$name    = RP_Forms::post( 'rp_namn' );
		$email   = sanitize_email( RP_Forms::post( 'rp_epost' ) );
		$role    = sanitize_key( RP_Forms::post( 'rp_roll' ) );
		$consent = '1' === RP_Forms::post( 'rp_samtycke' );

		$company = get_post( $post_id );
		if ( ! $company || RP_Post_Types::COMPANY !== $company->post_type || 'publish' !== $company->post_status ) {
			RP_Forms::redirect( 'review_invalid' );
		}
		$back = get_permalink( $post_id );

		// E-mail is optional unless the admin requires it (then a confirmation link is sent).
		$confirm   = (bool) RP_Settings::get( 'krav_epost' );
		$raw_email = trim( RP_Forms::post( 'rp_epost' ) );
		$email_ok  = '' === $raw_email ? ! $confirm : (bool) is_email( $email );

		$length = mb_strlen( $text );
		if ( ! RP_Rating_Math::is_valid( $rating ) || $length < self::MIN_LENGTH || $length > self::MAX_LENGTH || ! $email_ok || ! $consent ) {
			RP_Forms::redirect( 'review_invalid', $back, 'betygsatt' );
		}

		$fingerprint = RP_Forms::client_fingerprint();
		if ( ( '' !== $email && self::has_reviewed( $post_id, $email ) ) || self::has_reviewed_recently( $post_id, $fingerprint ) ) {
			RP_Forms::redirect( 'review_duplicate', $back, 'betygsatt' );
		}
		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => '' !== $name ? mb_substr( $name, 0, 60 ) : 'Anonym',
			'comment_author_email' => $email,
			'comment_author_url'   => '',
			'comment_author_IP'    => '', // We never store raw IP addresses.
			'comment_agent'        => '',
			'comment_content'      => $text,
			'comment_type'         => self::TYPE,
			'comment_approved'     => $confirm ? self::UNCONFIRMED : '0',
			'user_id'              => get_current_user_id(),
		);

		// Let Akismet (or other spam filters) inspect the review when active.
		$checked = apply_filters( 'preprocess_comment', $data );
		if ( is_array( $checked ) ) {
			$data = array_merge( $data, array_intersect_key( $checked, array_flip( array( 'comment_content', 'comment_author' ) ) ) );
		}
		if ( 'spam' === apply_filters( 'pre_comment_approved', $data['comment_approved'], $data ) ) {
			$data['comment_approved'] = 'spam';
		}
		if ( function_exists( 'wp_check_comment_disallowed_list' ) && wp_check_comment_disallowed_list( $data['comment_author'], $email, '', $text, '', '' ) ) {
			$data['comment_approved'] = 'spam';
		}

		$comment_id = wp_insert_comment( wp_slash( $data ) );
		if ( ! $comment_id ) {
			RP_Forms::redirect( 'spam', $back );
		}
		add_comment_meta( $comment_id, 'rp_betyg', $rating, true );
		add_comment_meta( $comment_id, 'rp_fp', $fingerprint, true ); // Salted hash, deleted after REPEAT_DAYS.
		if ( isset( self::roles()[ $role ] ) ) {
			add_comment_meta( $comment_id, 'rp_roll', $role, true );
		}

		if ( 'spam' === $data['comment_approved'] ) {
			RP_Forms::redirect( 'review_pending', $back, 'betygsatt' );
		}

		if ( $confirm ) {
			self::send_confirmation( $comment_id, $email, $post_id );
			RP_Forms::redirect( 'review_sent', $back, 'betygsatt' );
		}

		self::notify_moderator( $comment_id );
		RP_Forms::redirect( 'review_pending', $back, 'betygsatt' );
	}

	/**
	 * Whether this e-mail already reviewed the company (any non-trashed status).
	 *
	 * @param int    $post_id Company.
	 * @param string $email   E-mail.
	 */
	public static function has_reviewed( int $post_id, string $email ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = %s AND LOWER(comment_author_email) = %s AND comment_approved NOT IN ('trash', 'spam')",
				$post_id,
				self::TYPE,
				strtolower( $email )
			)
		);
		return $count > 0;
	}

	/**
	 * Whether the same visitor (salted IP hash) reviewed the company within REPEAT_DAYS.
	 *
	 * @param int    $post_id     Company.
	 * @param string $fingerprint Fingerprint from RP_Forms::client_fingerprint().
	 */
	public static function has_reviewed_recently( int $post_id, string $fingerprint ): bool {
		global $wpdb;
		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rp_fp'
				WHERE c.comment_post_ID = %d AND c.comment_type = %s AND cm.meta_value = %s AND c.comment_approved NOT IN ('trash', 'spam') AND c.comment_date_gmt > %s",
				$post_id,
				self::TYPE,
				$fingerprint,
				gmdate( 'Y-m-d H:i:s', time() - self::REPEAT_DAYS * DAY_IN_SECONDS )
			)
		);
		return $count > 0;
	}

	/**
	 * Delete visitor fingerprints older than REPEAT_DAYS (they are only needed for the repeat check).
	 */
	public static function purge_fingerprints(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE cm FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
				WHERE cm.meta_key = 'rp_fp' AND c.comment_date_gmt < %s",
				gmdate( 'Y-m-d H:i:s', time() - self::REPEAT_DAYS * DAY_IN_SECONDS )
			)
		);
	}

	/**
	 * Send double opt-in e-mail.
	 *
	 * @param int    $comment_id Review.
	 * @param string $email      Recipient.
	 * @param int    $post_id    Company.
	 */
	private static function send_confirmation( int $comment_id, string $email, int $post_id ): void {
		$token = wp_generate_password( 32, false );
		update_comment_meta( $comment_id, 'rp_token', hash( 'sha256', $token ) );

		$url  = add_query_arg(
			array(
				'rp_bekrafta' => $comment_id,
				'token'       => $token,
			),
			get_permalink( $post_id )
		);
		$body = '<p>Hej!</p><p>Tack för ditt omdöme om <strong>' . esc_html( get_the_title( $post_id ) ) . '</strong>. Bekräfta att det var du som skrev det genom att klicka på knappen nedan:</p>'
			. '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;background:#1E4E8C;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">Bekräfta mitt omdöme</a></p>'
			. '<p style="color:#6b7785;font-size:13px">Länken gäller i 7 dagar. Om du inte har skrivit något omdöme kan du ignorera det här mejlet.</p>';

		RP_Forms::mail( $email, 'Bekräfta ditt omdöme på ' . get_bloginfo( 'name' ), $body );
	}

	/**
	 * Confirmation link handler.
	 */
	public static function handle_confirm(): void {
		if ( ! isset( $_GET['rp_bekrafta'], $_GET['token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- token is the proof.
			return;
		}
		$comment_id = absint( $_GET['rp_bekrafta'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token      = sanitize_text_field( wp_unslash( $_GET['token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$comment    = get_comment( $comment_id );
		$back       = $comment ? get_permalink( (int) $comment->comment_post_ID ) : home_url( '/' );

		$stored = $comment ? (string) get_comment_meta( $comment_id, 'rp_token', true ) : '';
		if ( ! $comment || self::UNCONFIRMED !== $comment->comment_approved || '' === $stored || ! hash_equals( $stored, hash( 'sha256', $token ) ) ) {
			RP_Forms::redirect( 'link_invalid', remove_query_arg( array( 'rp_bekrafta', 'token' ), $back ) );
		}

		global $wpdb;
		$wpdb->update( $wpdb->comments, array( 'comment_approved' => '0' ), array( 'comment_ID' => $comment_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_comment_cache( $comment_id );
		delete_comment_meta( $comment_id, 'rp_token' );
		update_comment_meta( $comment_id, 'rp_bekraftad', current_time( 'mysql' ) );

		self::notify_moderator( $comment_id );
		RP_Forms::redirect( 'review_confirmed', $back, 'omdomen' );
	}

	/**
	 * Tell moderators a review awaits approval.
	 *
	 * @param int $comment_id Review.
	 */
	private static function notify_moderator( int $comment_id ): void {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}
		$rating = (int) get_comment_meta( $comment_id, 'rp_betyg', true );
		$body   = '<p>Ett nytt omdöme väntar på granskning.</p>'
			. '<p><strong>Företag:</strong> ' . esc_html( get_the_title( (int) $comment->comment_post_ID ) ) . '<br>'
			. '<strong>Betyg:</strong> ' . esc_html( str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ) ) . '<br>'
			. '<strong>Av:</strong> ' . esc_html( $comment->comment_author ) . '</p>'
			. '<blockquote style="border-left:3px solid #e4e8ee;margin:0;padding-left:12px">' . nl2br( esc_html( $comment->comment_content ) ) . '</blockquote>'
			. '<p><a href="' . esc_url( admin_url( 'edit-comments.php?comment_status=moderated&comment_type=' . self::TYPE ) ) . '">Granska omdömen</a></p>';
		RP_Forms::mail( RP_Settings::notify_email(), '[Rekryteringsportalen] Nytt omdöme att granska', $body );
	}

	/**
	 * Handle "Rapportera omdöme".
	 */
	public static function handle_report(): void {
		RP_Forms::guard( 'rp_report_review', 10 );
		$comment_id = absint( RP_Forms::post( 'rp_omdome' ) );
		$reason     = RP_Forms::post( 'rp_anledning', true );
		$comment    = get_comment( $comment_id );
		if ( ! $comment || self::TYPE !== $comment->comment_type ) {
			RP_Forms::redirect( 'spam' );
		}

		$count = (int) get_comment_meta( $comment_id, 'rp_rapporter', true ) + 1;
		update_comment_meta( $comment_id, 'rp_rapporter', $count );
		if ( '' !== $reason ) {
			add_comment_meta( $comment_id, 'rp_rapport_anledning', mb_substr( $reason, 0, 500 ) );
		}

		$body = '<p>Ett omdöme om <strong>' . esc_html( get_the_title( (int) $comment->comment_post_ID ) ) . '</strong> har rapporterats (' . (int) $count . ' gånger totalt).</p>'
			. ( '' !== $reason ? '<p><strong>Anledning:</strong> ' . esc_html( $reason ) . '</p>' : '' )
			. '<blockquote style="border-left:3px solid #e4e8ee;margin:0;padding-left:12px">' . nl2br( esc_html( $comment->comment_content ) ) . '</blockquote>'
			. '<p><a href="' . esc_url( admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) ) . '">Öppna omdömet</a></p>';
		RP_Forms::mail( RP_Settings::notify_email(), '[Rekryteringsportalen] Rapporterat omdöme', $body );

		RP_Forms::redirect( 'reported', get_permalink( (int) $comment->comment_post_ID ), 'omdomen' );
	}

	/**
	 * Recalculate on status change.
	 *
	 * @param string     $new_status New.
	 * @param string     $old_status Old.
	 * @param WP_Comment $comment    Comment.
	 */
	public static function on_transition( $new_status, $old_status, $comment ): void {
		if ( $comment instanceof WP_Comment && self::TYPE === $comment->comment_type ) {
			self::recalc_company( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * Recalculate when an approved review is inserted directly (import, seeding).
	 *
	 * @param int        $id      Comment ID.
	 * @param WP_Comment $comment Comment.
	 */
	public static function on_insert( $id, $comment ): void {
		if ( $comment instanceof WP_Comment && self::TYPE === $comment->comment_type && '1' === (string) $comment->comment_approved ) {
			// Rating meta is added right after insert; defer to shutdown so it is present.
			$post_id = (int) $comment->comment_post_ID;
			add_action(
				'shutdown',
				static function () use ( $post_id ) {
					RP_Ratings::recalc_company( $post_id );
				}
			);
		}
	}

	/**
	 * Recalculate after delete.
	 *
	 * @param int        $id      Comment ID.
	 * @param WP_Comment $comment Comment.
	 */
	public static function on_deleted( $id, $comment = null ): void {
		if ( $comment instanceof WP_Comment && self::TYPE === $comment->comment_type ) {
			self::recalc_company( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * All approved ratings for a company.
	 *
	 * @param int $post_id Company.
	 * @return int[]
	 */
	public static function ratings( int $post_id ): array {
		global $wpdb;
		$values = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT cm.meta_value FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rp_betyg'
				WHERE c.comment_post_ID = %d AND c.comment_type = %s AND c.comment_approved = '1'",
				$post_id,
				self::TYPE
			)
		);
		return array_map( 'intval', $values );
	}

	/**
	 * Site-wide average of all approved ratings (C in the Bayes formula).
	 */
	public static function global_average(): float {
		global $wpdb;
		$avg = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT AVG(CAST(cm.meta_value AS UNSIGNED)) FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rp_betyg'
				WHERE c.comment_type = %s AND c.comment_approved = '1'",
				self::TYPE
			)
		);
		return null === $avg ? 3.0 : round( (float) $avg, 4 );
	}

	/**
	 * Update cached aggregates for one company and schedule a site-wide Bayes refresh.
	 *
	 * @param int $post_id Company.
	 */
	public static function recalc_company( int $post_id ): void {
		$ratings = self::ratings( $post_id );
		$avg     = RP_Rating_Math::average( $ratings );
		$count   = count( $ratings );
		$global  = self::global_average();
		update_option( 'rp_global_avg', $global, false );

		update_post_meta( $post_id, 'rp_snittbetyg', $avg );
		update_post_meta( $post_id, 'rp_antal_betyg', $count );
		update_post_meta( $post_id, 'rp_bayes_betyg', $count ? RP_Rating_Math::bayes( $avg, $count, $global, (int) RP_Settings::get( 'min_omdomen' ) ) : 0 );

		// C changed, so every company's weighted score shifts slightly. Refresh in the background (debounced).
		if ( ! wp_next_scheduled( 'rp_recalc_bayes' ) ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'rp_recalc_bayes' );
		}
	}

	/**
	 * Recalculate aggregates for every company (cron, CLI, settings change).
	 */
	public static function recalc_all(): void {
		global $wpdb;
		$global = self::global_average();
		$m      = (int) RP_Settings::get( 'min_omdomen' );
		update_option( 'rp_global_avg', $global, false );

		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT c.comment_post_ID AS id, AVG(CAST(cm.meta_value AS UNSIGNED)) AS avg_rating, COUNT(*) AS n
				FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} cm ON cm.comment_id = c.comment_ID AND cm.meta_key = 'rp_betyg'
				WHERE c.comment_type = %s AND c.comment_approved = '1' GROUP BY c.comment_post_ID",
				self::TYPE
			)
		);
		$rated = array();
		foreach ( $rows as $row ) {
			$id           = (int) $row->id;
			$rated[ $id ] = true;
			$avg          = round( (float) $row->avg_rating, 2 );
			update_post_meta( $id, 'rp_snittbetyg', $avg );
			update_post_meta( $id, 'rp_antal_betyg', (int) $row->n );
			update_post_meta( $id, 'rp_bayes_betyg', RP_Rating_Math::bayes( $avg, (int) $row->n, $global, $m ) );
		}

		// Companies whose last review was removed.
		$stale = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'rp_antal_betyg',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
		foreach ( $stale as $id ) {
			if ( ! isset( $rated[ $id ] ) ) {
				update_post_meta( $id, 'rp_snittbetyg', 0 );
				update_post_meta( $id, 'rp_antal_betyg', 0 );
				update_post_meta( $id, 'rp_bayes_betyg', 0 );
			}
		}
	}

	/**
	 * Delete reviews never confirmed within 7 days.
	 */
	public static function purge_unconfirmed(): void {
		$old = get_comments(
			array(
				'type'       => self::TYPE,
				'status'     => self::UNCONFIRMED,
				'date_query' => array( array( 'before' => '7 days ago' ) ),
				'fields'     => 'ids',
				'number'     => 500,
			)
		);
		foreach ( $old as $id ) {
			wp_delete_comment( (int) $id, true );
		}
	}

	/**
	 * Approved reviews for a company.
	 *
	 * @param int $post_id  Company.
	 * @param int $per_page Per page.
	 * @param int $page     Page (1-based).
	 * @return WP_Comment[]
	 */
	public static function get_reviews( int $post_id, int $per_page = 10, int $page = 1 ): array {
		return get_comments(
			array(
				'post_id' => $post_id,
				'type'    => self::TYPE,
				'status'  => 'approve',
				'number'  => $per_page,
				'offset'  => max( 0, ( $page - 1 ) * $per_page ),
				'orderby' => 'comment_date_gmt',
				'order'   => 'DESC',
			)
		);
	}

	/**
	 * Latest approved reviews site-wide.
	 *
	 * @param int $number Count.
	 * @return WP_Comment[]
	 */
	public static function latest( int $number = 6 ): array {
		return get_comments(
			array(
				'type'        => self::TYPE,
				'status'      => 'approve',
				'number'      => $number,
				'post_type'   => RP_Post_Types::COMPANY,
				'post_status' => 'publish',
			)
		);
	}
}
