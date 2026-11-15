<?php
/**
 * Shared helpers for public forms: nonce, honeypot, rate limiting, redirects and notices.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Form helpers.
 */
final class RP_Forms {

	/**
	 * Messages shown after redirect, keyed by code.
	 *
	 * @var array<string, array{0:string, 1:string}> code => [type, text]
	 */
	private const MESSAGES = array(
		'review_sent'      => array( 'success', 'Tack! Vi har skickat ett mejl till dig. Klicka på länken i mejlet för att bekräfta ditt omdöme.' ),
		'review_pending'   => array( 'success', 'Tack för ditt omdöme! Det publiceras så snart det har granskats.' ),
		'review_confirmed' => array( 'success', 'Tack! Ditt omdöme är bekräftat och publiceras så snart det har granskats.' ),
		'review_duplicate' => array( 'error', 'Du har redan lämnat ett omdöme om det här företaget.' ),
		'review_invalid'   => array( 'error', 'Kontrollera formuläret: välj 1–5 stjärnor, skriv minst 20 tecken, ange en giltig e-post och godkänn villkoren.' ),
		'link_invalid'     => array( 'error', 'Länken är ogiltig eller har gått ut.' ),
		'reported'         => array( 'success', 'Tack! Omdömet har rapporterats och granskas av oss.' ),
		'lead_sent'        => array( 'success', 'Tack! Din förfrågan är skickad. Matchande rekryteringsföretag kontaktar dig inom kort.' ),
		'lead_invalid'     => array( 'error', 'Kontrollera formuläret: företag, kontaktperson, giltig e-post, roll och samtycke krävs.' ),
		'order_sent'       => array( 'success', 'Tack för din beställning! Vi kontaktar dig inom en arbetsdag.' ),
		'order_invalid'    => array( 'error', 'Kontrollera formuläret: företag, kontaktperson, giltig e-post, paket och samtycke krävs.' ),
		'contact_sent'     => array( 'success', 'Tack! Ditt meddelande är skickat till företaget.' ),
		'contact_invalid'  => array( 'error', 'Kontrollera formuläret: namn, giltig e-post och meddelande krävs.' ),
		'rate_limited'     => array( 'error', 'Du har skickat för många formulär på kort tid. Försök igen om en stund.' ),
		'spam'             => array( 'error', 'Formuläret kunde inte skickas. Ladda om sidan och försök igen.' ),
	);

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Nothing global yet; kept for symmetry and future hooks.
	}

	/**
	 * Hidden fields every public form needs.
	 *
	 * @param string $action admin-post action.
	 */
	public static function hidden_fields( string $action ): string {
		$html  = '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		$html .= wp_nonce_field( $action, '_rp_nonce', true, false );
		$html .= '<input type="hidden" name="rp_ts" value="' . esc_attr( (string) time() ) . '">';
		// Honeypot: humans never see or fill this field.
		$html .= '<div class="rp-hp" aria-hidden="true"><label>Lämna tomt <input type="text" name="rp_webbadress" value="" tabindex="-1" autocomplete="off"></label></div>';
		return $html;
	}

	/**
	 * Validate nonce, honeypot, minimum fill time and rate limit. Redirects with an error on failure.
	 *
	 * @param string $action      Action / nonce name.
	 * @param int    $per_hour    Max submissions per IP per hour.
	 */
	public static function guard( string $action, int $per_hour = 5 ): void {
		$nonce = isset( $_POST['_rp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_rp_nonce'] ) ) : '';
		$hp    = isset( $_POST['rp_webbadress'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_webbadress'] ) ) : '';
		$ts    = isset( $_POST['rp_ts'] ) ? absint( $_POST['rp_ts'] ) : 0;

		if ( ! wp_verify_nonce( $nonce, $action ) || '' !== $hp || ( $ts && time() - $ts < 3 ) ) {
			self::redirect( 'spam' );
		}

		$key   = 'rp_rl_' . md5( $action . '|' . self::client_fingerprint() );
		$count = (int) get_transient( $key );
		if ( $per_hour > 0 && $count >= $per_hour ) {
			self::redirect( 'rate_limited' );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Hashed client fingerprint (we never store raw IP addresses).
	 */
	public static function client_fingerprint(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) );
	}

	/**
	 * Redirect back to the referring page with a message code and exit.
	 *
	 * @param string $code   Message code.
	 * @param string $url    Target URL (defaults to referer).
	 * @param string $anchor Fragment without '#'.
	 */
	public static function redirect( string $code, string $url = '', string $anchor = '' ): void {
		if ( '' === $url ) {
			$url = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		}
		$url = remove_query_arg( 'rp_msg', $url );
		$url = add_query_arg( 'rp_msg', $code, $url );
		if ( '' !== $anchor ) {
			$url .= '#' . $anchor;
		}
		wp_safe_redirect( $url, 303 );
		exit;
	}

	/**
	 * Notice HTML for the current request's message code, if it belongs to the given prefix.
	 *
	 * @param string $prefix Message prefix, e.g. 'review' or '' for all.
	 */
	public static function notice( string $prefix = '' ): string {
		$code = isset( $_GET['rp_msg'] ) ? sanitize_key( wp_unslash( $_GET['rp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $code || ! isset( self::MESSAGES[ $code ] ) ) {
			return '';
		}
		$shared = array( 'rate_limited', 'spam', 'link_invalid', 'reported' );
		if ( '' !== $prefix && ! str_starts_with( $code, $prefix ) && ! in_array( $code, $shared, true ) ) {
			return '';
		}
		[ $type, $text ] = self::MESSAGES[ $code ];
		$role            = 'error' === $type ? 'alert' : 'status';
		return sprintf( '<div class="rp-notice rp-notice--%1$s" role="%2$s">%3$s</div>', esc_attr( $type ), esc_attr( $role ), esc_html( $text ) );
	}

	/**
	 * Read a POSTed text field.
	 *
	 * @param string $key Field.
	 * @param bool   $multiline Textarea.
	 */
	public static function post( string $key, bool $multiline = false ): string {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
			return '';
		}
		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_array( $value ) ) {
			return '';
		}
		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Consent checkbox HTML.
	 *
	 * @param string $text Label text (may contain a privacy link placeholder {integritet}).
	 */
	public static function consent_field( string $text ): string {
		$privacy = get_privacy_policy_url();
		$link    = $privacy ? '<a href="' . esc_url( $privacy ) . '" target="_blank">integritetspolicy</a>' : 'integritetspolicy';
		$label   = str_replace( '{integritet}', $link, esc_html( $text ) );
		return '<p class="rp-field rp-field--checkbox"><label><input type="checkbox" name="rp_samtycke" value="1" required> <span>' . $label . '</span></label></p>';
	}

	/**
	 * Send an HTML e-mail wrapped in a simple template.
	 *
	 * @param string|string[] $to       Recipient(s).
	 * @param string          $subject  Subject.
	 * @param string          $body     HTML body (already escaped).
	 * @param string          $reply_to Optional Reply-To address.
	 */
	public static function mail( $to, string $subject, string $body, string $reply_to = '' ): bool {
		$site    = get_bloginfo( 'name' );
		$html    = '<!doctype html><html lang="sv"><body style="margin:0;background:#f4f6f9;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1f2933">';
		$html   .= '<div style="max-width:600px;margin:24px auto;background:#fff;border-radius:8px;overflow:hidden">';
		$html   .= '<div style="background:#1E4E8C;color:#fff;padding:16px 24px;font-size:18px;font-weight:700">' . esc_html( $site ) . '</div>';
		$html   .= '<div style="padding:24px;line-height:1.6;font-size:15px">' . $body . '</div>';
		$html   .= '<div style="padding:16px 24px;font-size:12px;color:#6b7785;border-top:1px solid #e4e8ee">' . esc_html( $site ) . ' · ' . esc_html( home_url( '/' ) ) . '</div>';
		$html   .= '</div></body></html>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return wp_mail( $to, $subject, $html, $headers );
	}
}
