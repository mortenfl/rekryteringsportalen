<?php
/**
 * Admin for reviews: rating column, edit box with rating, company reply and reports.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Review admin.
 */
final class RP_Admin_Reviews {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'manage_edit-comments_columns', array( self::class, 'columns' ) );
		add_action( 'manage_comments_custom_column', array( self::class, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_comment', array( self::class, 'meta_box' ) );
		add_action( 'edit_comment', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Columns.
	 *
	 * @param array<string,string> $cols Columns.
	 * @return array<string,string>
	 */
	public static function columns( $cols ): array {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'author' === $k ) {
				$new['rp_betyg'] = 'Betyg';
			}
		}
		return $new;
	}

	/**
	 * Column content.
	 *
	 * @param string $col        Column.
	 * @param int    $comment_id Comment.
	 */
	public static function column( $col, $comment_id ): void {
		if ( 'rp_betyg' !== $col ) {
			return;
		}
		$rating = (int) get_comment_meta( $comment_id, 'rp_betyg', true );
		if ( ! $rating ) {
			return;
		}
		echo rp_stars( (float) $rating, 'sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$reports = (int) get_comment_meta( $comment_id, 'rp_rapporter', true );
		if ( $reports ) {
			echo '<br><span class="rp-pending">Rapporterad ' . (int) $reports . '×</span>';
		}
		$comment = get_comment( $comment_id );
		if ( $comment && RP_Ratings::UNCONFIRMED === $comment->comment_approved ) {
			echo '<br><em>Ej bekräftad via e-post</em>';
		}
	}

	/**
	 * Meta box on the comment edit screen.
	 *
	 * @param WP_Comment $comment Comment.
	 */
	public static function meta_box( $comment ): void {
		if ( $comment instanceof WP_Comment && RP_Ratings::TYPE === $comment->comment_type ) {
			add_meta_box( 'rp-review', 'Omdöme', array( self::class, 'render' ), 'comment', 'normal', 'high' );
		}
	}

	/**
	 * Box content.
	 *
	 * @param WP_Comment $comment Comment.
	 */
	public static function render( $comment ): void {
		wp_nonce_field( 'rp_review_edit', 'rp_review_nonce' );
		$rating  = (int) get_comment_meta( $comment->comment_ID, 'rp_betyg', true );
		$role    = (string) get_comment_meta( $comment->comment_ID, 'rp_roll', true );
		$reply   = (string) get_comment_meta( $comment->comment_ID, 'rp_foretagssvar', true );
		$reports = get_comment_meta( $comment->comment_ID, 'rp_rapport_anledning', false );
		$company = (int) $comment->comment_post_ID;
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="rp-betyg">Betyg</label></th><td><select id="rp-betyg" name="rp_betyg">';
		for ( $i = 1; $i <= 5; $i++ ) {
			echo '<option value="' . (int) $i . '" ' . selected( $rating, $i, false ) . '>' . esc_html( str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="rp-roll">Roll</label></th><td><select id="rp-roll" name="rp_roll"><option value="">—</option>';
		foreach ( RP_Ratings::roles() as $k => $label ) {
			echo '<option value="' . esc_attr( $k ) . '" ' . selected( $role, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="rp-svar">Svar från företaget</label></th><td><textarea id="rp-svar" name="rp_foretagssvar" rows="4" class="large-text">' . esc_textarea( $reply ) . '</textarea>';
		echo '<p class="description">' . ( RP_Packages::has_extended( $company ) ? 'Visas publikt under omdömet.' : 'Visas bara publikt om företaget har Paket 1 eller 2.' ) . ' Klistra in svaret som företaget skickat till er.</p></td></tr>';
		if ( $reports ) {
			echo '<tr><th>Rapporter</th><td><ul>';
			foreach ( $reports as $r ) {
				echo '<li>' . esc_html( (string) $r ) . '</li>';
			}
			echo '</ul><label><input type="checkbox" name="rp_clear_reports" value="1"> Nollställ rapporter</label></td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Save meta from the comment edit screen.
	 *
	 * @param int                 $comment_id Comment.
	 * @param array<string,mixed> $data       Data.
	 */
	public static function save( $comment_id, $data = array() ): void {
		if ( ! isset( $_POST['rp_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rp_review_nonce'] ) ), 'rp_review_edit' ) || ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}
		$rating = isset( $_POST['rp_betyg'] ) ? absint( $_POST['rp_betyg'] ) : 0;
		if ( RP_Rating_Math::is_valid( $rating ) ) {
			update_comment_meta( $comment_id, 'rp_betyg', $rating );
		}
		$role = isset( $_POST['rp_roll'] ) ? sanitize_key( wp_unslash( $_POST['rp_roll'] ) ) : '';
		isset( RP_Ratings::roles()[ $role ] ) ? update_comment_meta( $comment_id, 'rp_roll', $role ) : delete_comment_meta( $comment_id, 'rp_roll' );
		$reply = isset( $_POST['rp_foretagssvar'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rp_foretagssvar'] ) ) : '';
		'' !== $reply ? update_comment_meta( $comment_id, 'rp_foretagssvar', $reply ) : delete_comment_meta( $comment_id, 'rp_foretagssvar' );
		if ( ! empty( $_POST['rp_clear_reports'] ) ) {
			delete_comment_meta( $comment_id, 'rp_rapporter' );
			delete_comment_meta( $comment_id, 'rp_rapport_anledning' );
		}
		$comment = get_comment( $comment_id );
		if ( $comment ) {
			RP_Ratings::recalc_company( (int) $comment->comment_post_ID );
		}
	}
}
