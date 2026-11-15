<?php
/**
 * Pure lead matching. No WordPress dependencies so it can be unit tested.
 *
 * @package RekryteringsportalenCore
 */

/**
 * Chooses which package-2 companies receive a lead.
 */
final class RP_Lead_Matcher {

	/**
	 * Select recipients.
	 *
	 * Each dimension (industry, region) scores 2 for an exact match, 1 when either side
	 * is unspecified (a company without industries covers all industries) and 0 otherwise.
	 * A company needs a total of at least 2, i.e. industry and/or region must match.
	 * Ties are broken round-robin: the company that received a lead longest ago goes first.
	 *
	 * @param array<int, array{id:int, branscher:string[], regioner:string[], senaste_lead:int}> $candidates Companies with active package 2.
	 * @param string                                                                             $bransch    Lead industry slug ('' = any).
	 * @param string                                                                             $region     Lead region slug ('' = any).
	 * @param int                                                                                $max        Max recipients.
	 * @return int[] Company IDs in priority order.
	 */
	public static function select( array $candidates, string $bransch, string $region, int $max ): array {
		$scored = array();
		foreach ( $candidates as $c ) {
			$score = self::score( $c['branscher'] ?? array(), $bransch ) + self::score( $c['regioner'] ?? array(), $region );
			if ( $score < 2 ) {
				continue;
			}
			$scored[] = array(
				'id'    => (int) $c['id'],
				'score' => $score,
				'last'  => (int) ( $c['senaste_lead'] ?? 0 ),
			);
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return array( $b['score'], $a['last'], $a['id'] ) <=> array( $a['score'], $b['last'], $b['id'] );
			}
		);

		return array_slice( array_column( $scored, 'id' ), 0, max( 0, $max ) );
	}

	/**
	 * Score one dimension.
	 *
	 * @param string[] $terms  Company terms.
	 * @param string   $wanted Wanted term ('' = any).
	 */
	private static function score( array $terms, string $wanted ): int {
		if ( '' === $wanted || ! $terms ) {
			return 1;
		}
		return in_array( $wanted, $terms, true ) ? 2 : 0;
	}
}
