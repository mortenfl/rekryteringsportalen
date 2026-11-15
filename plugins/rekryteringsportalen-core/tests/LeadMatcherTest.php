<?php
/**
 * Tests for RP_Lead_Matcher.
 *
 * @package RekryteringsportalenCore
 */

use PHPUnit\Framework\TestCase;

final class LeadMatcherTest extends TestCase {

	/**
	 * Company fixture.
	 *
	 * @param int      $id   ID.
	 * @param string[] $b    Industries.
	 * @param string[] $r    Regions.
	 * @param int      $last Last lead timestamp.
	 * @return array{id:int, branscher:string[], regioner:string[], senaste_lead:int}
	 */
	private function company( int $id, array $b, array $r, int $last = 0 ): array {
		return array(
			'id'           => $id,
			'branscher'    => $b,
			'regioner'     => $r,
			'senaste_lead' => $last,
		);
	}

	public function test_exact_match_ranks_first(): void {
		$candidates = array(
			$this->company( 1, array( 'it' ), array( 'goteborg' ) ),
			$this->company( 2, array( 'it' ), array( 'stockholm' ) ),
			$this->company( 3, array( 'ekonomi' ), array( 'stockholm' ) ),
		);
		$this->assertSame( array( 2, 1, 3 ), RP_Lead_Matcher::select( $candidates, 'it', 'stockholm', 3 ) );
	}

	public function test_no_match_is_excluded(): void {
		$candidates = array(
			$this->company( 1, array( 'vard' ), array( 'malmo' ) ),
			$this->company( 2, array( 'it' ), array( 'stockholm' ) ),
		);
		$this->assertSame( array( 2 ), RP_Lead_Matcher::select( $candidates, 'it', 'stockholm', 3 ) );
	}

	public function test_company_without_terms_covers_everything(): void {
		$candidates = array(
			$this->company( 1, array(), array() ),
			$this->company( 2, array( 'vard' ), array( 'malmo' ) ),
		);
		$this->assertSame( array( 1 ), RP_Lead_Matcher::select( $candidates, 'it', 'stockholm', 3 ) );
	}

	public function test_specialist_beats_generalist(): void {
		$candidates = array(
			$this->company( 1, array(), array() ),
			$this->company( 2, array( 'it' ), array( 'stockholm' ) ),
		);
		$this->assertSame( array( 2, 1 ), RP_Lead_Matcher::select( $candidates, 'it', 'stockholm', 3 ) );
	}

	public function test_round_robin_on_ties(): void {
		$candidates = array(
			$this->company( 1, array( 'it' ), array( 'stockholm' ), 300 ),
			$this->company( 2, array( 'it' ), array( 'stockholm' ), 100 ),
			$this->company( 3, array( 'it' ), array( 'stockholm' ), 200 ),
		);
		$this->assertSame( array( 2, 3 ), RP_Lead_Matcher::select( $candidates, 'it', 'stockholm', 2 ) );
	}

	public function test_max_recipients(): void {
		$candidates = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$candidates[] = $this->company( $i, array( 'it' ), array() );
		}
		$this->assertCount( 3, RP_Lead_Matcher::select( $candidates, 'it', '', 3 ) );
		$this->assertSame( array(), RP_Lead_Matcher::select( $candidates, 'it', '', 0 ) );
	}

	public function test_lead_without_industry_or_region_goes_to_everyone(): void {
		$candidates = array(
			$this->company( 1, array( 'it' ), array( 'stockholm' ) ),
			$this->company( 2, array( 'vard' ), array( 'malmo' ) ),
		);
		$this->assertSame( array( 1, 2 ), RP_Lead_Matcher::select( $candidates, '', '', 3 ) );
	}
}
