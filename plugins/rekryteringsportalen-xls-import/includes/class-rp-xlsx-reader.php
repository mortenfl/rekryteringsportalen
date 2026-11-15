<?php
/**
 * Reader for Excel 2007+ workbooks (.xlsx / .xlsm). Pure PHP: ZipArchive + XMLReader, no libraries.
 *
 * @package RekryteringsportalenXlsImport
 */

// XMLReader and SimpleXML expose camelCase properties (nodeType, localName, cellXfs ...).
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/**
 * XLSX reader.
 */
final class RP_Xlsx_Reader {

	private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	/**
	 * Shared strings.
	 *
	 * @var string[]
	 */
	private array $strings = array();

	/**
	 * Per style index (cellXfs position): true when it is a date format.
	 *
	 * @var bool[]
	 */
	private array $date_styles = array();

	/**
	 * Whether the workbook uses the 1904 date system (old Mac Excel).
	 *
	 * @var bool
	 */
	private bool $date1904 = false;

	/**
	 * Open archive.
	 *
	 * @var ZipArchive
	 */
	private ZipArchive $zip;

	/**
	 * Read all sheets.
	 *
	 * @param string $path File path.
	 * @return array<string, array<int, array<int, string>>> Sheet name => rows => columns (0-based) => value.
	 * @throws RuntimeException When the file cannot be read.
	 */
	public function read( string $path ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'PHP-tillägget "zip" saknas på servern. Be ditt webbhotell aktivera det, eller spara filen som CSV.' );
		}
		$this->zip = new ZipArchive();
		if ( true !== $this->zip->open( $path, ZipArchive::RDONLY ) ) {
			throw new RuntimeException( 'Filen kunde inte öppnas som en Excel-fil (.xlsx).' );
		}

		try {
			$sheets = $this->sheet_paths();
			if ( ! $sheets ) {
				throw new RuntimeException( 'Excel-filen innehåller inga flikar.' );
			}
			$this->strings     = $this->read_shared_strings();
			$this->date_styles = $this->read_date_styles();

			$out = array();
			foreach ( $sheets as $name => $file ) {
				$out[ $name ] = $this->read_sheet( $file );
			}
			return $out;
		} finally {
			$this->zip->close();
		}
	}

	/**
	 * Sheet name => path inside the archive, in workbook order.
	 *
	 * @return array<string, string>
	 */
	private function sheet_paths(): array {
		$workbook = $this->xml( 'xl/workbook.xml' );
		$rels     = $this->xml( 'xl/_rels/workbook.xml.rels' );
		if ( ! $workbook ) {
			return array();
		}

		$targets = array();
		if ( $rels ) {
			foreach ( $rels->children() as $rel ) {
				$target                         = (string) $rel['Target'];
				$target                         = str_starts_with( $target, '/' ) ? ltrim( $target, '/' ) : 'xl/' . $target;
				$targets[ (string) $rel['Id'] ] = $this->normalize_path( $target );
			}
		}

		$pr = $workbook->workbookPr ?? null;
		if ( $pr && in_array( strtolower( (string) $pr['date1904'] ), array( '1', 'true' ), true ) ) {
			$this->date1904 = true;
		}

		$out = array();
		$i   = 1;
		foreach ( $workbook->sheets->sheet ?? array() as $sheet ) {
			$rid  = (string) $sheet->attributes( self::NS_REL )['id'];
			$file = $targets[ $rid ] ?? 'xl/worksheets/sheet' . $i . '.xml';
			if ( false !== $this->zip->locateName( $file ) ) {
				$out[ (string) $sheet['name'] ] = $file;
			}
			++$i;
		}
		return $out;
	}

	/**
	 * Resolve "xl/worksheets/../x.xml" style paths.
	 *
	 * @param string $path Path.
	 */
	private function normalize_path( string $path ): string {
		$parts = array();
		foreach ( explode( '/', $path ) as $part ) {
			if ( '..' === $part ) {
				array_pop( $parts );
			} elseif ( '' !== $part && '.' !== $part ) {
				$parts[] = $part;
			}
		}
		return implode( '/', $parts );
	}

	/**
	 * Parse a small XML part with SimpleXML.
	 *
	 * @param string $name Part name.
	 */
	private function xml( string $name ): ?SimpleXMLElement {
		$data = $this->zip->getFromName( $name );
		if ( false === $data ) {
			return null;
		}
		$xml = simplexml_load_string( $data, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT );
		return false === $xml ? null : $xml;
	}

	/**
	 * Shared string table (rich text runs are concatenated, phonetic runs skipped).
	 *
	 * @return string[]
	 */
	private function read_shared_strings(): array {
		$strings = array();
		$stream  = $this->zip->getStream( 'xl/sharedStrings.xml' );
		if ( ! $stream ) {
			return $strings;
		}
		$reader = new XMLReader();
		$reader->XML( (string) stream_get_contents( $stream ), null, LIBXML_NONET | LIBXML_COMPACT );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$current = null;
		$skip    = 0;
		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT === $reader->nodeType ) {
				if ( 'si' === $reader->localName ) {
					$current = '';
					if ( $reader->isEmptyElement ) {
						$strings[] = '';
						$current   = null;
					}
				} elseif ( 'rPh' === $reader->localName && ! $reader->isEmptyElement ) {
					++$skip;
				} elseif ( 't' === $reader->localName && null !== $current && 0 === $skip ) {
					$current .= $reader->readString();
				}
			} elseif ( XMLReader::END_ELEMENT === $reader->nodeType ) {
				if ( 'si' === $reader->localName && null !== $current ) {
					$strings[] = $current;
					$current   = null;
				} elseif ( 'rPh' === $reader->localName ) {
					$skip = max( 0, $skip - 1 );
				}
			}
		}
		$reader->close();
		return $strings;
	}

	/**
	 * Which cell styles are date formats.
	 *
	 * @return bool[]
	 */
	private function read_date_styles(): array {
		$styles = $this->xml( 'xl/styles.xml' );
		if ( ! $styles ) {
			return array();
		}
		$custom = array();
		foreach ( $styles->numFmts->numFmt ?? array() as $fmt ) {
			$custom[ (int) $fmt['numFmtId'] ] = (string) $fmt['formatCode'];
		}
		$out = array();
		foreach ( $styles->cellXfs->xf ?? array() as $xf ) {
			$id    = (int) $xf['numFmtId'];
			$out[] = RP_Spreadsheet::is_date_format( $id, $custom[ $id ] ?? '' );
		}
		return $out;
	}

	/**
	 * Read one worksheet with a streaming parser (large sheets use little memory).
	 *
	 * @param string $file Part name.
	 * @return array<int, array<int, string>>
	 */
	private function read_sheet( string $file ): array {
		$stream = $this->zip->getStream( $file );
		if ( ! $stream ) {
			return array();
		}
		$tmp = tmpfile();
		stream_copy_to_stream( $stream, $tmp );
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$meta = stream_get_meta_data( $tmp );

		$reader = new XMLReader();
		$reader->open( $meta['uri'], null, LIBXML_NONET | LIBXML_COMPACT );

		$rows    = array();
		$row_idx = -1;
		$col_idx = -1;
		$cell    = null;
		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT !== $reader->nodeType ) {
				if ( XMLReader::END_ELEMENT === $reader->nodeType && 'c' === $reader->localName && null !== $cell ) {
					$this->store( $rows, $row_idx, $col_idx, $cell );
					$cell = null;
				}
				continue;
			}
			switch ( $reader->localName ) {
				case 'row':
					$r       = $reader->getAttribute( 'r' );
					$row_idx = null !== $r ? (int) $r - 1 : $row_idx + 1;
					$col_idx = -1;
					break;
				case 'c':
					$ref     = (string) $reader->getAttribute( 'r' );
					$col_idx = '' !== $ref ? RP_Spreadsheet::column_index( $ref ) : $col_idx + 1;
					$cell    = array(
						't' => (string) $reader->getAttribute( 't' ),
						's' => (int) $reader->getAttribute( 's' ),
						'v' => '',
					);
					if ( $reader->isEmptyElement ) {
						$cell = null;
					}
					break;
				case 'v':
					if ( null !== $cell ) {
						$cell['v'] = $reader->readString();
					}
					break;
				case 'is':
					if ( null !== $cell ) {
						$cell['v'] = $this->inline_string( $reader );
					}
					break;
			}
		}
		$reader->close();
		fclose( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $rows;
	}

	/**
	 * Concatenate <t> elements of an inline string (excluding phonetic runs).
	 *
	 * @param XMLReader $reader Reader positioned on <is>.
	 */
	private function inline_string( XMLReader $reader ): string {
		$text  = '';
		$depth = $reader->depth;
		$skip  = 0;
		while ( $reader->read() && $reader->depth > $depth ) {
			if ( XMLReader::ELEMENT === $reader->nodeType ) {
				if ( 'rPh' === $reader->localName && ! $reader->isEmptyElement ) {
					++$skip;
				} elseif ( 't' === $reader->localName && 0 === $skip ) {
					$text .= $reader->readString();
				}
			} elseif ( XMLReader::END_ELEMENT === $reader->nodeType && 'rPh' === $reader->localName ) {
				--$skip;
			}
		}
		return $text;
	}

	/**
	 * Convert a raw cell to a string and store it.
	 *
	 * @param array<int, array<int, string>>   $rows Rows.
	 * @param int                              $r    Row index.
	 * @param int                              $c    Column index.
	 * @param array{t:string, s:int, v:string} $cell Cell.
	 */
	private function store( array &$rows, int $r, int $c, array $cell ): void {
		$v = $cell['v'];
		switch ( $cell['t'] ) {
			case 's':
				$value = $this->strings[ (int) $v ] ?? '';
				break;
			case 'b':
				$value = '1' === $v ? 'JA' : 'NEJ';
				break;
			case 'e':
				$value = '';
				break;
			case 'str':
			case 'inlineStr':
				$value = $v;
				break;
			case 'd':
				$value = substr( $v, 0, 10 );
				break;
			default:
				if ( '' === $v ) {
					return;
				}
				$value = ( $this->date_styles[ $cell['s'] ] ?? false )
					? RP_Spreadsheet::serial_to_date( (float) $v, $this->date1904 )
					: RP_Spreadsheet::format_number( (float) $v );
		}
		$value = trim( $value );
		if ( '' !== $value && $r >= 0 && $c >= 0 ) {
			$rows[ $r ][ $c ] = $value;
		}
	}
}
