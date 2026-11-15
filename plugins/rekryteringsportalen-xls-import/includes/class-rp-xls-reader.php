<?php
/**
 * Reader for legacy Excel 97–2003 workbooks (.xls, BIFF8 inside an OLE2 compound file).
 * Pure PHP, no libraries. Reads cell values only (no formatting beyond date detection).
 *
 * @package RekryteringsportalenXlsImport
 */

/**
 * XLS (BIFF8) reader.
 */
final class RP_Xls_Reader {

	private const END_OF_CHAIN = 0xFFFFFFFE;
	private const FREE_SECT    = 0xFFFFFFFF;

	/**
	 * Raw file.
	 *
	 * @var string
	 */
	private string $data = '';

	/**
	 * Sector size.
	 *
	 * @var int
	 */
	private int $sector_size = 512;

	/**
	 * File allocation table.
	 *
	 * @var int[]
	 */
	private array $fat = array();

	/**
	 * Shared strings.
	 *
	 * @var string[]
	 */
	private array $sst = array();

	/**
	 * XF index => is date format.
	 *
	 * @var bool[]
	 */
	private array $xf_date = array();

	/**
	 * 1904 date system.
	 *
	 * @var bool
	 */
	private bool $date1904 = false;

	/**
	 * Read all worksheets.
	 *
	 * @param string $path File path.
	 * @return array<string, array<int, array<int, string>>> Sheet name => rows => columns => value.
	 * @throws RuntimeException When the file cannot be read.
	 */
	public function read( string $path ): array {
		$this->data = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" !== substr( $this->data, 0, 8 ) ) {
			throw new RuntimeException( 'Filen är inte en giltig Excel 97–2003-fil (.xls). Spara den som .xlsx eller CSV och försök igen.' );
		}
		$stream = $this->workbook_stream();
		if ( '' === $stream ) {
			throw new RuntimeException( 'Hittade inget kalkylblad i .xls-filen.' );
		}
		return $this->parse_workbook( $stream );
	}

	// ---- OLE2 compound file ----

	/**
	 * Little-endian unsigned 16-bit.
	 *
	 * @param string $s   Data.
	 * @param int    $pos Offset.
	 */
	private static function u16( string $s, int $pos ): int {
		return ord( $s[ $pos ] ?? "\0" ) | ( ord( $s[ $pos + 1 ] ?? "\0" ) << 8 );
	}

	/**
	 * Little-endian unsigned 32-bit.
	 *
	 * @param string $s   Data.
	 * @param int    $pos Offset.
	 */
	private static function u32( string $s, int $pos ): int {
		return self::u16( $s, $pos ) | ( self::u16( $s, $pos + 2 ) << 16 );
	}

	/**
	 * One sector.
	 *
	 * @param int $id Sector id.
	 */
	private function sector( int $id ): string {
		return (string) substr( $this->data, ( $id + 1 ) * $this->sector_size, $this->sector_size );
	}

	/**
	 * Follow a FAT chain.
	 *
	 * @param int   $start Start sector.
	 * @param int[] $fat   Allocation table.
	 * @return int[]
	 */
	private static function chain( int $start, array $fat ): array {
		$out  = array();
		$seen = array();
		while ( $start >= 0 && $start < self::END_OF_CHAIN - 3 && isset( $fat[ $start ] ) && ! isset( $seen[ $start ] ) ) {
			$seen[ $start ] = true;
			$out[]          = $start;
			$start          = $fat[ $start ];
		}
		return $out;
	}

	/**
	 * Read a stream stored in regular sectors.
	 *
	 * @param int $start Start sector.
	 * @param int $size  Size in bytes (-1 = whole chain).
	 */
	private function read_chain( int $start, int $size = -1 ): string {
		$buf = '';
		foreach ( self::chain( $start, $this->fat ) as $id ) {
			$buf .= $this->sector( $id );
		}
		return $size >= 0 ? (string) substr( $buf, 0, $size ) : $buf;
	}

	/**
	 * Locate and return the "Workbook" (or BIFF5 "Book") stream.
	 */
	private function workbook_stream(): string {
		$h                 = $this->data;
		$this->sector_size = 1 << self::u16( $h, 0x1E );
		$mini_size         = 1 << self::u16( $h, 0x20 );
		$dir_start         = self::u32( $h, 0x30 );
		$cutoff            = self::u32( $h, 0x38 );
		$minifat_start     = self::u32( $h, 0x3C );
		$dif_start         = self::u32( $h, 0x44 );
		$dif_count         = self::u32( $h, 0x48 );

		if ( $this->sector_size < 512 || $this->sector_size > 4096 ) {
			return '';
		}

		// DIFAT: 109 entries in the header, then extra DIFAT sectors.
		$fat_sectors = array();
		for ( $i = 0; $i < 109; $i++ ) {
			$id = self::u32( $h, 0x4C + $i * 4 );
			if ( $id < self::END_OF_CHAIN - 3 ) {
				$fat_sectors[] = $id;
			}
		}
		$per = intdiv( $this->sector_size, 4 ) - 1;
		for ( $n = 0, $id = $dif_start; $n < $dif_count && $id < self::END_OF_CHAIN - 3; $n++ ) {
			$sec = $this->sector( $id );
			for ( $i = 0; $i < $per; $i++ ) {
				$fid = self::u32( $sec, $i * 4 );
				if ( $fid < self::END_OF_CHAIN - 3 ) {
					$fat_sectors[] = $fid;
				}
			}
			$id = self::u32( $sec, $per * 4 );
		}

		$fat = array();
		foreach ( $fat_sectors as $id ) {
			$sec = $this->sector( $id );
			foreach ( array_values( unpack( 'V*', $sec ) ? unpack( 'V*', $sec ) : array() ) as $v ) {
				$fat[] = $v;
			}
		}
		$this->fat = $fat;

		// Directory.
		$dir      = $this->read_chain( $dir_start );
		$root     = null;
		$workbook = null;
		$dir_len  = strlen( $dir );
		for ( $off = 0; $off + 128 <= $dir_len; $off += 128 ) {
			$len  = self::u16( $dir, $off + 64 );
			$type = ord( $dir[ $off + 66 ] );
			if ( $len < 2 || $len > 64 ) {
				continue;
			}
			$name  = mb_convert_encoding( substr( $dir, $off, $len - 2 ), 'UTF-8', 'UTF-16LE' );
			$entry = array(
				'start' => self::u32( $dir, $off + 116 ),
				'size'  => self::u32( $dir, $off + 120 ),
			);
			if ( 5 === $type ) {
				$root = $entry;
			} elseif ( 2 === $type && in_array( strtolower( $name ), array( 'workbook', 'book' ), true ) && null === $workbook ) {
				$workbook = $entry;
			}
		}
		if ( ! $workbook ) {
			return '';
		}

		if ( $workbook['size'] >= $cutoff || ! $root ) {
			return $this->read_chain( $workbook['start'], $workbook['size'] );
		}

		// Small stream: stored in the mini stream.
		$minifat = array();
		$raw     = $this->read_chain( $minifat_start );
		if ( '' !== $raw ) {
			$minifat = array_values( (array) unpack( 'V*', $raw ) );
		}
		$ministream = $this->read_chain( $root['start'] );
		$buf        = '';
		foreach ( self::chain( $workbook['start'], $minifat ) as $id ) {
			$buf .= substr( $ministream, $id * $mini_size, $mini_size );
		}
		return (string) substr( $buf, 0, $workbook['size'] );
	}

	// ---- BIFF8 ----

	/**
	 * Iterate records from an offset.
	 *
	 * @param string $s     Stream.
	 * @param int    $start Offset.
	 * @return Generator<int, array{type:int, data:string}>
	 */
	private static function records( string $s, int $start ): Generator {
		$len = strlen( $s );
		$pos = $start;
		while ( $pos + 4 <= $len ) {
			$type = self::u16( $s, $pos );
			$size = self::u16( $s, $pos + 2 );
			yield array(
				'type' => $type,
				'data' => (string) substr( $s, $pos + 4, $size ),
			);
			$pos += 4 + $size;
		}
	}

	/**
	 * Workbook globals, then each worksheet.
	 *
	 * @param string $s Workbook stream.
	 * @return array<string, array<int, array<int, string>>>
	 * @throws RuntimeException On unsupported versions.
	 */
	private function parse_workbook( string $s ): array {
		$bof = self::u16( $s, 0 );
		if ( 0x0809 !== $bof || 0x0600 !== self::u16( $s, 4 ) ) {
			throw new RuntimeException( 'Den här .xls-filen är från en för gammal Excel-version (före Excel 97). Spara den som .xlsx och försök igen.' );
		}

		$sheets   = array();
		$formats  = array();
		$xf_fmt   = array();
		$sst_data = null;

		foreach ( self::records( $s, 0 ) as $rec ) {
			[ 'type' => $type, 'data' => $d ] = $rec;
			if ( null !== $sst_data ) {
				if ( 0x003C === $type ) {
					$sst_data[] = $d;
					continue;
				}
				$this->sst = $this->parse_sst( $sst_data );
				$sst_data  = null;
			}
			switch ( $type ) {
				case 0x0085: // BOUNDSHEET.
					if ( 0 === ord( $d[5] ?? "\0" ) ) { // Worksheet (not chart/macro).
						$sheets[] = array(
							'offset' => self::u32( $d, 0 ),
							'name'   => self::short_string( $d, 6 ),
						);
					}
					break;
				case 0x00FC: // SST.
					$sst_data = array( $d );
					break;
				case 0x041E: // FORMAT.
					$formats[ self::u16( $d, 0 ) ] = self::unicode_string( $d, 2 );
					break;
				case 0x00E0: // XF.
					$xf_fmt[] = self::u16( $d, 2 );
					break;
				case 0x0022: // DATEMODE.
					$this->date1904 = 1 === self::u16( $d, 0 );
					break;
				case 0x000A: // EOF of globals.
					break 2;
			}
		}
		if ( null !== $sst_data ) {
			$this->sst = $this->parse_sst( $sst_data );
		}
		foreach ( $xf_fmt as $i => $fmt ) {
			$this->xf_date[ $i ] = RP_Spreadsheet::is_date_format( $fmt, $formats[ $fmt ] ?? '' );
		}

		$out = array();
		foreach ( $sheets as $sheet ) {
			$out[ $sheet['name'] ] = $this->parse_sheet( $s, $sheet['offset'] );
		}
		return $out;
	}

	/**
	 * Cells of one worksheet.
	 *
	 * @param string $s      Stream.
	 * @param int    $offset Sheet BOF offset.
	 * @return array<int, array<int, string>>
	 */
	private function parse_sheet( string $s, int $offset ): array {
		$rows           = array();
		$pending_string = null;
		$depth          = 0;

		foreach ( self::records( $s, $offset ) as $rec ) {
			[ 'type' => $type, 'data' => $d ] = $rec;

			if ( 0x0809 === $type ) {
				++$depth; // Embedded substreams (charts) have their own BOF/EOF.
				continue;
			}
			if ( 0x000A === $type ) {
				if ( --$depth <= 0 ) {
					break;
				}
				continue;
			}
			if ( $depth > 1 ) {
				continue;
			}

			switch ( $type ) {
				case 0x00FD: // LABELSST.
					$this->put( $rows, self::u16( $d, 0 ), self::u16( $d, 2 ), $this->sst[ self::u32( $d, 6 ) ] ?? '' );
					break;
				case 0x0204: // LABEL.
				case 0x00D6: // RSTRING.
					$this->put( $rows, self::u16( $d, 0 ), self::u16( $d, 2 ), self::unicode_string( $d, 6 ) );
					break;
				case 0x0203: // NUMBER.
					$this->put( $rows, self::u16( $d, 0 ), self::u16( $d, 2 ), $this->number( unpack( 'e', substr( $d, 6, 8 ) )[1], self::u16( $d, 4 ) ) );
					break;
				case 0x027E: // RK.
					$this->put( $rows, self::u16( $d, 0 ), self::u16( $d, 2 ), $this->number( self::rk( self::u32( $d, 6 ) ), self::u16( $d, 4 ) ) );
					break;
				case 0x00BD: // MULRK.
					$row   = self::u16( $d, 0 );
					$first = self::u16( $d, 2 );
					$count = intdiv( strlen( $d ) - 6, 6 );
					for ( $i = 0; $i < $count; $i++ ) {
						$base = 4 + $i * 6;
						$this->put( $rows, $row, $first + $i, $this->number( self::rk( self::u32( $d, $base + 2 ) ), self::u16( $d, $base ) ) );
					}
					break;
				case 0x0205: // BOOLERR.
					if ( 0 === ord( $d[7] ?? "\0" ) ) {
						$this->put( $rows, self::u16( $d, 0 ), self::u16( $d, 2 ), ord( $d[6] ) ? 'JA' : 'NEJ' );
					}
					break;
				case 0x0006: // FORMULA: cached result.
					$row = self::u16( $d, 0 );
					$col = self::u16( $d, 2 );
					if ( 0xFFFF === self::u16( $d, 12 ) ) {
						$kind = ord( $d[6] );
						if ( 0 === $kind ) {
							$pending_string = array( $row, $col ); // Value follows in a STRING record.
						} elseif ( 1 === $kind ) {
							$this->put( $rows, $row, $col, ord( $d[8] ) ? 'JA' : 'NEJ' );
						}
					} else {
						$this->put( $rows, $row, $col, $this->number( unpack( 'e', substr( $d, 6, 8 ) )[1], self::u16( $d, 4 ) ) );
					}
					break;
				case 0x0207: // STRING (formula result).
					if ( $pending_string ) {
						$this->put( $rows, $pending_string[0], $pending_string[1], self::unicode_string( $d, 0 ) );
						$pending_string = null;
					}
					break;
			}
		}
		return $rows;
	}

	/**
	 * Store a non-empty value.
	 *
	 * @param array<int, array<int, string>> $rows  Rows.
	 * @param int                            $row   Row.
	 * @param int                            $col   Column.
	 * @param string                         $value Value.
	 */
	private function put( array &$rows, int $row, int $col, string $value ): void {
		$value = trim( $value );
		if ( '' !== $value ) {
			$rows[ $row ][ $col ] = $value;
		}
	}

	/**
	 * Number or date, depending on the cell's XF.
	 *
	 * @param float $value Value.
	 * @param int   $xf    XF index.
	 */
	private function number( float $value, int $xf ): string {
		return ( $this->xf_date[ $xf ] ?? false )
			? RP_Spreadsheet::serial_to_date( $value, $this->date1904 )
			: RP_Spreadsheet::format_number( $value );
	}

	/**
	 * Decode an RK number.
	 *
	 * @param int $rk Raw value.
	 */
	private static function rk( int $rk ): float {
		if ( $rk & 0x02 ) {
			$v = $rk >> 2;
			if ( $rk & 0x80000000 ) {
				$v -= 0x40000000;
			}
			$v = (float) $v;
		} else {
			$v = unpack( 'e', pack( 'V', 0 ) . pack( 'V', $rk & 0xFFFFFFFC ) )[1];
		}
		return ( $rk & 0x01 ) ? $v / 100 : $v;
	}

	/**
	 * Characters to UTF-8.
	 *
	 * @param string $raw  Raw bytes.
	 * @param bool   $wide UTF-16LE (true) or compressed Latin-1 (false).
	 */
	private static function decode( string $raw, bool $wide ): string {
		return mb_convert_encoding( $raw, 'UTF-8', $wide ? 'UTF-16LE' : 'ISO-8859-1' );
	}

	/**
	 * ShortXLUnicodeString (8-bit length), used in BOUNDSHEET.
	 *
	 * @param string $d   Data.
	 * @param int    $pos Offset.
	 */
	private static function short_string( string $d, int $pos ): string {
		$cch  = ord( $d[ $pos ] ?? "\0" );
		$wide = (bool) ( ord( $d[ $pos + 1 ] ?? "\0" ) & 0x01 );
		return self::decode( (string) substr( $d, $pos + 2, $wide ? $cch * 2 : $cch ), $wide );
	}

	/**
	 * XLUnicodeString (16-bit length) within a single record.
	 *
	 * @param string $d   Data.
	 * @param int    $pos Offset.
	 */
	private static function unicode_string( string $d, int $pos ): string {
		$cch   = self::u16( $d, $pos );
		$flags = ord( $d[ $pos + 2 ] ?? "\0" );
		$wide  = (bool) ( $flags & 0x01 );
		$skip  = 3 + ( $flags & 0x08 ? 2 : 0 ) + ( $flags & 0x04 ? 4 : 0 );
		return self::decode( (string) substr( $d, $pos + $skip, $wide ? $cch * 2 : $cch ), $wide );
	}

	/**
	 * Shared string table, which may be split over CONTINUE records. When characters of a
	 * string continue in the next record, that record starts with a new option byte.
	 *
	 * @param string[] $chunks SST record followed by its CONTINUE records.
	 * @return string[]
	 */
	private function parse_sst( array $chunks ): array {
		$ci  = 0;
		$pos = 8; // Skip cstTotal and cstUnique.
		$n   = self::u32( $chunks[0], 4 );
		$out = array();

		// Read raw bytes, crossing record boundaries transparently.
		$bytes = static function ( int $len ) use ( &$ci, &$pos, $chunks ): string {
			$buf = '';
			while ( $len > 0 && isset( $chunks[ $ci ] ) ) {
				$avail = strlen( $chunks[ $ci ] ) - $pos;
				if ( $avail <= 0 ) {
					++$ci;
					$pos = 0;
					continue;
				}
				$take = min( $avail, $len );
				$buf .= substr( $chunks[ $ci ], $pos, $take );
				$pos += $take;
				$len -= $take;
			}
			return $buf;
		};

		for ( $i = 0; $i < $n && isset( $chunks[ $ci ] ); $i++ ) {
			$head = $bytes( 3 );
			if ( strlen( $head ) < 3 ) {
				break;
			}
			$cch   = self::u16( $head, 0 );
			$flags = ord( $head[2] );
			$wide  = (bool) ( $flags & 0x01 );
			$runs  = ( $flags & 0x08 ) ? self::u16( $bytes( 2 ), 0 ) : 0;
			$ext   = ( $flags & 0x04 ) ? self::u32( $bytes( 4 ), 0 ) : 0;

			$text = '';
			$left = $cch;
			while ( $left > 0 && isset( $chunks[ $ci ] ) ) {
				$avail = strlen( $chunks[ $ci ] ) - $pos;
				if ( $avail <= 0 ) {
					// Characters continue in the next record, which begins with a new option byte.
					++$ci;
					$pos = 0;
					if ( ! isset( $chunks[ $ci ] ) ) {
						break;
					}
					$wide = (bool) ( ord( $chunks[ $ci ][0] ) & 0x01 );
					$pos  = 1;
					continue;
				}
				$width = $wide ? 2 : 1;
				$take  = min( $left, intdiv( $avail, $width ) );
				if ( 0 === $take ) {
					$pos = strlen( $chunks[ $ci ] ); // Odd byte left over; force the switch above.
					continue;
				}
				$text .= self::decode( substr( $chunks[ $ci ], $pos, $take * $width ), $wide );
				$pos  += $take * $width;
				$left -= $take;
			}
			$bytes( $runs * 4 + $ext );
			$out[] = $text;
		}
		return $out;
	}
}
