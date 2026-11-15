<?php
/**
 * Spreadsheet facade: picks the right reader, selects a sheet and turns cells into {headers, rows}.
 * Pure PHP, no WordPress dependencies (unit tested).
 *
 * @package RekryteringsportalenXlsImport
 */

/**
 * Spreadsheet helpers.
 */
final class RP_Spreadsheet {

	/**
	 * Supported extensions.
	 *
	 * @var string[]
	 */
	public const EXTENSIONS = array( 'xlsx', 'xlsm', 'xls' );

	/**
	 * Read a workbook and return the chosen sheet as an import table.
	 *
	 * @param string $path  File path.
	 * @param string $ext   Lowercase extension.
	 * @param string $sheet Sheet name or 1-based number. '' = first sheet that has data.
	 * @return array{headers:string[], rows:array<int, array<string,string>>, info:string, sheets:string[]}
	 * @throws RuntimeException When the file or sheet cannot be read.
	 */
	public static function read( string $path, string $ext, string $sheet = '' ): array {
		$head = (string) file_get_contents( $path, false, null, 0, 8 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		// Trust the file's signature over its extension (".xls" files are sometimes really .xlsx, or vice versa).
		if ( str_starts_with( $head, "PK\x03\x04" ) ) {
			$sheets = ( new RP_Xlsx_Reader() )->read( $path );
		} elseif ( str_starts_with( $head, "\xD0\xCF\x11\xE0" ) ) {
			$sheets = ( new RP_Xls_Reader() )->read( $path );
		} elseif ( 'xls' === $ext && ( str_contains( strtolower( (string) file_get_contents( $path, false, null, 0, 512 ) ), '<table' ) || str_contains( strtolower( (string) file_get_contents( $path, false, null, 0, 512 ) ), '<html' ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			throw new RuntimeException( 'Filen heter .xls men är egentligen en HTML-tabell (vanligt vid export från webbsystem). Öppna den i Excel och spara som .xlsx.' );
		} else {
			throw new RuntimeException( 'Filen är inte en Excel-fil. Kontrollera att den är sparad som .xlsx eller .xls.' );
		}

		$names = array_keys( $sheets );
		$name  = self::pick_sheet( $sheets, $sheet );
		$table = self::to_table( $sheets[ $name ] );

		$position        = array_search( $name, $names, true ) + 1;
		$table['info']   = count( $names ) > 1
			? sprintf( 'flik "%s" (%d av %d)', $name, $position, count( $names ) )
			: sprintf( 'flik "%s"', $name );
		$table['sheets'] = $names;
		return $table;
	}

	/**
	 * Choose a sheet.
	 *
	 * @param array<string, array<int, array<int, string>>> $sheets Sheets.
	 * @param string                                        $want   Name or 1-based number.
	 * @throws RuntimeException When the requested sheet does not exist.
	 */
	public static function pick_sheet( array $sheets, string $want ): string {
		$names = array_keys( $sheets );
		$want  = trim( $want );
		if ( '' !== $want ) {
			if ( ctype_digit( $want ) && isset( $names[ (int) $want - 1 ] ) ) {
				return (string) $names[ (int) $want - 1 ];
			}
			foreach ( $names as $n ) {
				if ( mb_strtolower( (string) $n ) === mb_strtolower( $want ) ) {
					return (string) $n;
				}
			}
			throw new RuntimeException( sprintf( 'Hittade ingen flik som heter "%s". Flikar i filen: %s.', $want, implode( ', ', $names ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- shown via esc_html() by the caller.
		}
		foreach ( $sheets as $n => $rows ) {
			if ( count( $rows ) >= 2 ) {
				return (string) $n;
			}
		}
		return (string) $names[0];
	}

	/**
	 * Turn sparse cells into headers + associative rows. The first non-empty row is the header row.
	 *
	 * @param array<int, array<int, string>> $cells Row index => column index => value.
	 * @return array{headers:string[], rows:array<int, array<string,string>>}
	 */
	public static function to_table( array $cells ): array {
		ksort( $cells );
		$cells = array_values( array_filter( $cells ) );
		if ( ! $cells ) {
			return array(
				'headers' => array(),
				'rows'    => array(),
			);
		}

		$header_cells = array_shift( $cells );
		$max          = max( array_keys( $header_cells ) );
		foreach ( $cells as $row ) {
			$max = max( $max, max( array_keys( $row ) ) );
		}

		$headers = array();
		$seen    = array();
		$used    = array();
		for ( $c = 0; $c <= $max; $c++ ) {
			$h = trim( preg_replace( '/\s+/u', ' ', (string) ( $header_cells[ $c ] ?? '' ) ) );
			if ( '' === $h ) {
				$has_data = false;
				foreach ( $cells as $row ) {
					if ( isset( $row[ $c ] ) ) {
						$has_data = true;
						break;
					}
				}
				if ( ! $has_data ) {
					continue; // Empty column without header: drop.
				}
				$h = 'Kolumn ' . self::column_letter( $c );
			}
			$key           = mb_strtolower( $h );
			$seen[ $key ]  = ( $seen[ $key ] ?? 0 ) + 1;
			$headers[ $c ] = $seen[ $key ] > 1 ? $h . ' (' . $seen[ $key ] . ')' : $h;
			$used[]        = $c;
		}

		$rows = array();
		foreach ( $cells as $row ) {
			$assoc = array();
			foreach ( $used as $c ) {
				$assoc[ $headers[ $c ] ] = (string) ( $row[ $c ] ?? '' );
			}
			if ( '' !== implode( '', $assoc ) ) {
				$rows[] = $assoc;
			}
		}

		return array(
			'headers' => array_values( $headers ),
			'rows'    => $rows,
		);
	}

	/**
	 * "AB12" → 27 (0-based column index).
	 *
	 * @param string $ref Cell reference.
	 */
	public static function column_index( string $ref ): int {
		$n = 0;
		$l = strlen( $ref );
		for ( $i = 0; $i < $l; $i++ ) {
			$ch = ord( strtoupper( $ref[ $i ] ) );
			if ( $ch < 65 || $ch > 90 ) {
				break;
			}
			$n = $n * 26 + ( $ch - 64 );
		}
		return $n - 1;
	}

	/**
	 * 0 → "A", 27 → "AB".
	 *
	 * @param int $index 0-based column index.
	 */
	public static function column_letter( int $index ): string {
		$s = '';
		for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$s = chr( 65 + ( $n - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	/**
	 * Number to string without float noise; integers without decimals (org.nr, phone, year).
	 *
	 * @param float $value Value.
	 */
	public static function format_number( float $value ): string {
		if ( floor( $value ) === $value && abs( $value ) < 1e15 ) {
			return sprintf( '%.0f', $value );
		}
		return rtrim( rtrim( sprintf( '%.10F', $value ), '0' ), '.' );
	}

	/**
	 * Excel serial date to Y-m-d (or Y-m-d H:i when it has a time part, H:i for pure times).
	 *
	 * @param float $serial   Serial.
	 * @param bool  $date1904 1904 date system.
	 */
	public static function serial_to_date( float $serial, bool $date1904 = false ): string {
		if ( $serial < 0 ) {
			return self::format_number( $serial );
		}
		if ( $date1904 ) {
			$serial += 1462;
		}
		$seconds = (int) round( ( $serial - 25569 ) * 86400 );
		if ( $serial < 1 ) {
			return gmdate( 'H:i', (int) round( $serial * 86400 ) );
		}
		$has_time = abs( $serial - floor( $serial ) ) > 1e-9;
		return gmdate( $has_time ? 'Y-m-d H:i' : 'Y-m-d', $seconds );
	}

	/**
	 * Whether a number format shows a date.
	 *
	 * @param int    $id   Number format id.
	 * @param string $code Format code for custom formats.
	 */
	public static function is_date_format( int $id, string $code = '' ): bool {
		if ( ( $id >= 14 && $id <= 22 ) || ( $id >= 27 && $id <= 36 ) || ( $id >= 45 && $id <= 47 ) || ( $id >= 50 && $id <= 58 ) ) {
			return true;
		}
		if ( $id < 164 || '' === $code ) {
			return false;
		}
		// Remove quoted text, escaped characters and [colour]/[$-locale] sections. What remains of a
		// date/time format always contains d, m, y, h or s; number formats ("0.00", "#,##0") never do.
		$clean = preg_replace( array( '/"[^"]*"/', '/\\\\./', '/\[[^\]]*\]/' ), '', $code );
		$clean = strtolower( explode( ';', (string) $clean )[0] );
		return 'general' !== $clean && (bool) preg_match( '/[dmyhs]/', $clean );
	}
}
