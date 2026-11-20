<?php
/**
 * Pure normalization helpers used for duplicate detection on import.
 *
 * @package RekryteringsportalenCore
 */

/**
 * Normalizer.
 */
final class RP_Normalizer {

	/**
	 * Company suffixes ignored when comparing names.
	 *
	 * @var string[]
	 */
	private const NAME_SUFFIXES = array( 'aktiebolag', 'ab', 'publ', 'hb', 'kb', 'sverige', 'sweden', 'group', 'i sverige', 'nordic' );

	/**
	 * Swedish organisation number to "NNNNNN-NNNN". Accepts 10 or 12 digits (with century). Returns '' when invalid.
	 *
	 * @param string $orgnr Raw value.
	 */
	public static function orgnr( string $orgnr ): string {
		$digits = preg_replace( '/\D+/', '', $orgnr );
		if ( 12 === strlen( $digits ) && in_array( substr( $digits, 0, 2 ), array( '16', '18', '19', '20' ), true ) ) {
			$digits = substr( $digits, 2 );
		}
		if ( 10 !== strlen( $digits ) ) {
			return '';
		}
		return substr( $digits, 0, 6 ) . '-' . substr( $digits, 6 );
	}

	/**
	 * Registrable host of a URL or domain: lowercase, without scheme, "www." and path.
	 *
	 * @param string $url URL or bare domain.
	 */
	public static function domain( string $url ): string {
		$url = trim( strtolower( $url ) );
		if ( '' === $url ) {
			return '';
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#', $url ) ) {
			$url = 'http://' . $url;
		}
		$host = parse_url( $url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WP.
		if ( ! is_string( $host ) || ! str_contains( $host, '.' ) ) {
			return '';
		}
		return preg_replace( '/^www\d*\./', '', rtrim( $host, '.' ) );
	}

	/**
	 * Name key for fuzzy duplicate detection: lowercase, åäö folded, punctuation and company suffixes removed.
	 *
	 * @param string $name Company name.
	 */
	public static function name( string $name ): string {
		$name  = mb_strtolower( trim( $name ), 'UTF-8' );
		$name  = strtr(
			$name,
			array(
				'å' => 'a',
				'ä' => 'a',
				'ö' => 'o',
				'é' => 'e',
				'ü' => 'u',
				'&' => ' och ',
				'+' => ' och ',
			)
		);
		$name  = preg_replace( '/\(.*?\)/', ' ', $name );
		$name  = preg_replace( '/[^a-z0-9 ]+/', ' ', $name );
		$words = array_values( array_filter( explode( ' ', $name ) ) );
		while ( $words && in_array( end( $words ), self::NAME_SUFFIXES, true ) ) {
			array_pop( $words );
		}
		return implode( '', $words );
	}

	/**
	 * Split a list cell ("IT, Ekonomi; Teknik|Vård") into trimmed unique values.
	 *
	 * @param string|array<string> $value Raw value.
	 * @return string[]
	 */
	public static function list( $value ): array {
		if ( is_array( $value ) ) {
			$parts = $value;
		} else {
			// Decode entities first: "Teknik &amp; Ingenjör" must not be split at the ";".
			$parts = preg_split( '/\s*[,;|]\s*/', html_entity_decode( (string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		}
		$parts = array_map( 'trim', array_map( 'strval', $parts ) );
		return array_values( array_unique( array_filter( $parts, 'strlen' ) ) );
	}

	/**
	 * Package value from free text ("Paket 1", "1", "utökad" ...). Unknown values give 'inget'.
	 *
	 * @param string $value Raw value.
	 */
	public static function package( string $value ): string {
		$value = mb_strtolower( trim( $value ), 'UTF-8' );
		$value = str_replace( array( ' ', '-', '_' ), '', $value );
		if ( in_array( $value, array( 'paket1', '1', 'p1', 'utokad', 'utökad', 'presentation' ), true ) ) {
			return 'paket1';
		}
		if ( in_array( $value, array( 'paket2', '2', 'p2', 'leads', 'lead' ), true ) ) {
			return 'paket2';
		}
		return 'inget';
	}

	/**
	 * Date to Y-m-d, or '' when it cannot be parsed. Accepts Excel serial dates (1954–2119).
	 *
	 * @param string $value Raw date.
	 */
	public static function date( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		// Excel serial date (days since 1899-12-30), e.g. "46387" or "46387.5".
		if ( preg_match( '/^\d{4,5}(\.\d+)?$/', $value ) && (float) $value >= 20000 && (float) $value <= 80000 ) {
			return gmdate( 'Y-m-d', (int) round( ( (float) $value - 25569 ) * 86400 ) );
		}
		if ( ctype_digit( $value ) ) {
			return ''; // A bare number such as "2010" is not a date (strtotime would read it as 20:10 today).
		}
		$ts = strtotime( $value );
		return false === $ts ? '' : gmdate( 'Y-m-d', $ts );
	}
}
