<?php
/**
 * Pure rating math. No WordPress dependencies so it can be unit tested.
 *
 * @package RekryteringsportalenCore
 */

/**
 * Rating calculations.
 */
final class RP_Rating_Math {

	public const MIN_STARS = 1;
	public const MAX_STARS = 5;

	/**
	 * Arithmetic mean of a list of ratings, rounded to two decimals. Invalid values are ignored.
	 *
	 * @param array<int|float|string> $ratings Ratings.
	 */
	public static function average( array $ratings ): float {
		$valid = array_filter( array_map( 'intval', $ratings ), array( self::class, 'is_valid' ) );
		if ( ! $valid ) {
			return 0.0;
		}
		return round( array_sum( $valid ) / count( $valid ), 2 );
	}

	/**
	 * Bayesian weighted rating (IMDb formula).
	 *
	 * Formula: bayes = (v / (v + m)) * R + (m / (v + m)) * C
	 *
	 * @param float $r Company average (R).
	 * @param int   $v Number of ratings for the company (v).
	 * @param float $c Global average across the site (C).
	 * @param int   $m Minimum number of ratings / prior weight (m).
	 */
	public static function bayes( float $r, int $v, float $c, int $m ): float {
		$v = max( 0, $v );
		$m = max( 0, $m );
		if ( 0 === $v + $m ) {
			return 0.0;
		}
		if ( 0 === $v ) {
			return round( $c, 4 );
		}
		return round( ( $v / ( $v + $m ) ) * $r + ( $m / ( $v + $m ) ) * $c, 4 );
	}

	/**
	 * Count per star (5..1) and percent share.
	 *
	 * @param array<int|string> $ratings Ratings.
	 * @return array<int, array{count:int, percent:int}>
	 */
	public static function distribution( array $ratings ): array {
		$out   = array();
		$valid = array_filter( array_map( 'intval', $ratings ), array( self::class, 'is_valid' ) );
		$total = count( $valid );
		$count = array_count_values( $valid );
		for ( $star = self::MAX_STARS; $star >= self::MIN_STARS; $star-- ) {
			$n            = $count[ $star ] ?? 0;
			$out[ $star ] = array(
				'count'   => $n,
				'percent' => $total ? (int) round( 100 * $n / $total ) : 0,
			);
		}
		return $out;
	}

	/**
	 * Whether a value is an allowed star rating.
	 *
	 * @param mixed $value Value.
	 */
	public static function is_valid( $value ): bool {
		return is_numeric( $value ) && (int) $value == $value && $value >= self::MIN_STARS && $value <= self::MAX_STARS; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}
}
