<?php
/**
 * Tests for RP_Normalizer (duplicate detection on import).
 *
 * @package RekryteringsportalenCore
 */

use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase {

	/**
	 * @return array<string, array{0:string, 1:string}>
	 */
	public static function orgnr_provider(): array {
		return array(
			'dash'          => array( '556677-8899', '556677-8899' ),
			'no dash'       => array( '5566778899', '556677-8899' ),
			'spaces'        => array( '556677 8899', '556677-8899' ),
			'with century'  => array( '16556677-8899', '556677-8899' ),
			'too short'     => array( '55667788', '' ),
			'empty'         => array( '', '' ),
		);
	}

	/**
	 * @dataProvider orgnr_provider
	 */
	public function test_orgnr( string $in, string $out ): void {
		$this->assertSame( $out, RP_Normalizer::orgnr( $in ) );
	}

	/**
	 * @return array<string, array{0:string, 1:string}>
	 */
	public static function domain_provider(): array {
		return array(
			'full url'      => array( 'https://www.Exempel.se/om-oss?x=1', 'exempel.se' ),
			'bare'          => array( 'exempel.se', 'exempel.se' ),
			'www only'      => array( 'www.exempel.se', 'exempel.se' ),
			'subdomain'     => array( 'https://jobb.exempel.se', 'jobb.exempel.se' ),
			'http'          => array( 'http://exempel.se/', 'exempel.se' ),
			'not a domain'  => array( 'exempel', '' ),
			'empty'         => array( '', '' ),
		);
	}

	/**
	 * @dataProvider domain_provider
	 */
	public function test_domain( string $in, string $out ): void {
		$this->assertSame( $out, RP_Normalizer::domain( $in ) );
	}

	public function test_name_ignores_case_suffix_and_punctuation(): void {
		$key = RP_Normalizer::name( 'Nordljus Rekrytering AB' );
		$this->assertSame( $key, RP_Normalizer::name( 'nordljus rekrytering' ) );
		$this->assertSame( $key, RP_Normalizer::name( 'Nordljus Rekrytering Aktiebolag' ) );
		$this->assertSame( $key, RP_Normalizer::name( 'NORDLJUS REKRYTERING AB (publ)' ) );
		$this->assertSame( $key, RP_Normalizer::name( 'Nordljus-Rekrytering AB' ) );
	}

	public function test_name_folds_swedish_characters(): void {
		$this->assertSame( RP_Normalizer::name( 'Västkustens Rekrytering' ), RP_Normalizer::name( 'Vastkustens Rekrytering' ) );
		$this->assertSame( RP_Normalizer::name( 'Smålands Bemanning' ), RP_Normalizer::name( 'Smalands bemanning AB' ) );
	}

	public function test_name_keeps_different_companies_apart(): void {
		$this->assertNotSame( RP_Normalizer::name( 'Kodkraft Talent AB' ), RP_Normalizer::name( 'Kodkraft AB' ) );
	}

	public function test_list(): void {
		$this->assertSame( array( 'IT', 'Ekonomi', 'Teknik', 'Vård' ), RP_Normalizer::list( 'IT, Ekonomi; Teknik|Vård, IT' ) );
		$this->assertSame( array(), RP_Normalizer::list( '' ) );
		$this->assertSame( array( 'Teknik & Ingenjör', 'IT & Tech' ), RP_Normalizer::list( 'Teknik &amp; Ingenjör, IT &amp; Tech' ), 'HTML entities are not split at ";"' );
		$this->assertSame( array( 'A', 'B' ), RP_Normalizer::list( array( ' A ', 'B', '' ) ) );
	}

	public function test_package(): void {
		$this->assertSame( 'paket1', RP_Normalizer::package( 'Paket 1' ) );
		$this->assertSame( 'paket1', RP_Normalizer::package( '1' ) );
		$this->assertSame( 'paket2', RP_Normalizer::package( 'paket2' ) );
		$this->assertSame( 'paket2', RP_Normalizer::package( 'Leads' ) );
		$this->assertSame( 'inget', RP_Normalizer::package( '' ) );
		$this->assertSame( 'inget', RP_Normalizer::package( 'guld' ) );
	}

	public function test_date(): void {
		$this->assertSame( '2027-01-31', RP_Normalizer::date( '2027-01-31' ) );
		$this->assertSame( '', RP_Normalizer::date( 'inte ett datum' ) );
		$this->assertSame( '', RP_Normalizer::date( '' ) );
		$this->assertSame( '', RP_Normalizer::date( '2010' ) );
		$this->assertSame( '2026-12-31', RP_Normalizer::date( '46387' ), 'Excel serial date' );
		$this->assertSame( '2027-01-31', RP_Normalizer::date( '31 January 2027' ) );
	}
}
