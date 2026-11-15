<?php
/**
 * Tests for RP_Rating_Math.
 *
 * @package RekryteringsportalenCore
 */

use PHPUnit\Framework\TestCase;

final class RatingMathTest extends TestCase {

	public function test_average_rounds_to_two_decimals(): void {
		$this->assertSame( 4.33, RP_Rating_Math::average( array( 5, 4, 4 ) ) );
	}

	public function test_average_ignores_invalid_values(): void {
		$this->assertSame( 3.0, RP_Rating_Math::average( array( 3, 0, 6, -1, 'x', 3 ) ) );
	}

	public function test_average_of_nothing_is_zero(): void {
		$this->assertSame( 0.0, RP_Rating_Math::average( array() ) );
	}

	public function test_bayes_pulls_few_reviews_towards_global_average(): void {
		// One 5-star review, site average 3.5, m = 5: (1/6)*5 + (5/6)*3.5 = 3.75.
		$this->assertEqualsWithDelta( 3.75, RP_Rating_Math::bayes( 5.0, 1, 3.5, 5 ), 0.0001 );
	}

	public function test_many_reviews_beat_a_single_perfect_review(): void {
		$single = RP_Rating_Math::bayes( 5.0, 1, 3.5, 5 );
		$many   = RP_Rating_Math::bayes( 4.8, 100, 3.5, 5 );
		$this->assertGreaterThan( $single, $many );
	}

	public function test_bayes_without_reviews_is_global_average(): void {
		$this->assertSame( 3.5, RP_Rating_Math::bayes( 0.0, 0, 3.5, 5 ) );
	}

	public function test_bayes_with_zero_prior_is_plain_average(): void {
		$this->assertSame( 4.2, RP_Rating_Math::bayes( 4.2, 10, 3.0, 0 ) );
	}

	public function test_bottom_list_ordering(): void {
		$bad_many = RP_Rating_Math::bayes( 1.5, 40, 3.5, 5 );
		$bad_one  = RP_Rating_Math::bayes( 1.0, 1, 3.5, 5 );
		$this->assertLessThan( $bad_one, $bad_many, 'Consistently bad companies should rank lower than one with a single bad review.' );
	}

	public function test_distribution(): void {
		$dist = RP_Rating_Math::distribution( array( 5, 5, 4, 1 ) );
		$this->assertSame( array( 5, 4, 3, 2, 1 ), array_keys( $dist ) );
		$this->assertSame( 2, $dist[5]['count'] );
		$this->assertSame( 50, $dist[5]['percent'] );
		$this->assertSame( 0, $dist[3]['count'] );
		$this->assertSame( 25, $dist[1]['percent'] );
	}

	public function test_is_valid(): void {
		$this->assertTrue( RP_Rating_Math::is_valid( 1 ) );
		$this->assertTrue( RP_Rating_Math::is_valid( '5' ) );
		$this->assertFalse( RP_Rating_Math::is_valid( 0 ) );
		$this->assertFalse( RP_Rating_Math::is_valid( 6 ) );
		$this->assertFalse( RP_Rating_Math::is_valid( 2.5 ) );
		$this->assertFalse( RP_Rating_Math::is_valid( 'fem' ) );
	}
}
