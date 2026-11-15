<?php
/**
 * Company importer shared by the admin page, WP-CLI and REST.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import companies from CSV/JSON with duplicate detection.
 */
final class RP_Importer {

	/**
	 * Importable columns (canonical keys) with labels.
	 *
	 * @return array<string, string>
	 */
	public static function columns(): array {
		$cols = array(
			'namn'               => 'Namn *',
			'beskrivning'        => 'Lång beskrivning (HTML tillåten)',
			'logo_url'           => 'Logotyp (URL)',
			'tjanster'           => 'Tjänster (kommaseparerade)',
			'branscher'          => 'Branscher (kommaseparerade)',
			'regioner'           => 'Regioner (kommaseparerade)',
			'paket'              => 'Paket (inget/paket1/paket2)',
			'paket_giltigt_till' => 'Paket giltigt till (ÅÅÅÅ-MM-DD)',
		);
		foreach ( RP_Post_Types::company_fields() as $key => $field ) {
			if ( in_array( $field['type'], array( 'attachment', 'gallery' ), true ) ) {
				continue;
			}
			$cols[ $key ] = $field['label'];
		}
		return $cols;
	}

	/**
	 * Header aliases used for automatic column mapping.
	 *
	 * @return array<string, string> normalized header => column key
	 */
	private static function aliases(): array {
		return array(
			'namn'                => 'namn',
			'name'                => 'namn',
			'foretag'             => 'namn',
			'foretagsnamn'        => 'namn',
			'company'             => 'namn',
			'orgnr'               => 'orgnr',
			'organisationsnummer' => 'orgnr',
			'orgnummer'           => 'orgnr',
			'webbplats'           => 'webbplats',
			'hemsida'             => 'webbplats',
			'website'             => 'webbplats',
			'url'                 => 'webbplats',
			'logo'                => 'logo_url',
			'logotyp'             => 'logo_url',
			'logourl'             => 'logo_url',
			'epost'               => 'epost',
			'email'               => 'epost',
			'mejl'                => 'epost',
			'telefon'             => 'telefon',
			'phone'               => 'telefon',
			'tel'                 => 'telefon',
			'stad'                => 'ort',
			'city'                => 'ort',
			'beskrivning'         => 'beskrivning',
			'description'         => 'beskrivning',
			'kortbeskrivning'     => 'kort_beskrivning',
			'sammanfattning'      => 'kort_beskrivning',
			'tjanst'              => 'tjanster',
			'services'            => 'tjanster',
			'bransch'             => 'branscher',
			'industries'          => 'branscher',
			'region'              => 'regioner',
			'lan'                 => 'regioner',
			'anstallda'           => 'antal_anstallda',
			'employees'           => 'antal_anstallda',
			'founded'             => 'grundat',
			'karriarsida'         => 'karriarsida_url',
			'jobbfeed'            => 'jobbfeed_url',
			'feed'                => 'jobbfeed_url',
			'leadsepost'          => 'leads_epost',
			'giltigttill'         => 'paket_giltigt_till',
			'source'              => 'kalla',
		);
	}

	/**
	 * Guess mapping from headers.
	 *
	 * @param string[] $headers File headers.
	 * @return array<string, string> header => column key ('' = ignore)
	 */
	public static function guess_mapping( array $headers ): array {
		$columns = self::columns();
		$aliases = self::aliases();
		$used    = array();
		$map     = array();
		foreach ( $headers as $header ) {
			$norm = str_replace( array( '_', '-', ' ' ), '', RP_Normalizer::name( (string) $header ) );
			$key  = '';
			foreach ( array_keys( $columns ) as $col ) {
				if ( str_replace( '_', '', $col ) === $norm ) {
					$key = $col;
					break;
				}
			}
			if ( '' === $key && isset( $aliases[ $norm ] ) ) {
				$key = $aliases[ $norm ];
			}
			if ( '' !== $key && isset( $used[ $key ] ) ) {
				$key = '';
			}
			if ( '' !== $key ) {
				$used[ $key ] = true;
			}
			$map[ (string) $header ] = $key;
		}
		return $map;
	}

	/**
	 * Parse CSV text into associative rows. Detects ; , or tab delimiter and strips a UTF-8 BOM.
	 * Latin-1/Windows-1252 files (common Excel export) are converted to UTF-8.
	 *
	 * @param string $csv CSV text.
	 * @return array{headers:string[], rows:array<int, array<string,string>>}
	 */
	public static function parse_csv( string $csv ): array {
		$csv = preg_replace( '/^\xEF\xBB\xBF/', '', $csv );
		if ( ! mb_check_encoding( $csv, 'UTF-8' ) ) {
			$csv = mb_convert_encoding( $csv, 'UTF-8', 'Windows-1252' );
		}
		$first     = strtok( $csv, "\n" );
		$delimiter = ',';
		$best      = 0;
		foreach ( array( ';', ',', "\t" ) as $d ) {
			$n = substr_count( (string) $first, $d );
			if ( $n > $best ) {
				$best      = $n;
				$delimiter = $d;
			}
		}

		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $handle, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $handle );

		$headers = array();
		$rows    = array();
		while ( false !== ( $line = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( array( null ) === $line || ( 1 === count( $line ) && '' === trim( (string) $line[0] ) ) ) {
				continue;
			}
			if ( ! $headers ) {
				$headers = array_map( 'trim', array_map( 'strval', $line ) );
				continue;
			}
			$row = array();
			foreach ( $headers as $i => $h ) {
				$row[ $h ] = isset( $line[ $i ] ) ? trim( (string) $line[ $i ] ) : '';
			}
			$rows[] = $row;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return array(
			'headers' => $headers,
			'rows'    => $rows,
		);
	}

	/**
	 * Parse JSON: an array of objects, or {"companies":[...]}.
	 *
	 * @param string $json JSON text.
	 * @return array{headers:string[], rows:array<int, array<string,string>>}
	 */
	public static function parse_json( string $json ): array {
		$data = json_decode( $json, true );
		if ( isset( $data['companies'] ) && is_array( $data['companies'] ) ) {
			$data = $data['companies'];
		} elseif ( isset( $data['foretag'] ) && is_array( $data['foretag'] ) ) {
			$data = $data['foretag'];
		}
		if ( ! is_array( $data ) ) {
			return array(
				'headers' => array(),
				'rows'    => array(),
			);
		}
		if ( ! array_is_list( $data ) ) {
			$data = array( $data );
		}
		$headers = array();
		$rows    = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$row = array();
			foreach ( $item as $k => $v ) {
				$row[ (string) $k ]     = is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
				$headers[ (string) $k ] = true;
			}
			$rows[] = $row;
		}
		return array(
			'headers' => array_keys( $headers ),
			'rows'    => $rows,
		);
	}

	/**
	 * File extensions the importer accepts. Add-ons (e.g. Excel import) extend this list.
	 *
	 * @return string[]
	 */
	public static function extensions(): array {
		return array_values( array_unique( (array) apply_filters( 'rp_import_extensions', array( 'csv', 'json', 'txt' ) ) ) );
	}

	/**
	 * Parse a file by extension.
	 *
	 * Formats other than CSV/JSON are handled by the `rp_import_parse_file` filter, which receives
	 * null, the path, the lowercase extension and the options, and returns {headers, rows} or a WP_Error.
	 *
	 * @param string               $path    File path.
	 * @param string               $name    Original file name (for extension detection).
	 * @param array<string, mixed> $options Parser options, e.g. ['sheet' => 'Blad1'].
	 * @return array{headers:string[], rows:array<int, array<string,string>>, info?:string, error?:string}
	 */
	public static function parse_file( string $path, string $name = '', array $options = array() ): array {
		$ext = strtolower( pathinfo( '' !== $name ? $name : $path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'csv', 'json', 'txt', '' ), true ) ) {
			/**
			 * Parse a non-CSV/JSON import file.
			 *
			 * @param array|WP_Error|null  $parsed  Null when nobody handled the file.
			 * @param string               $path    Local file path.
			 * @param string               $ext     Lowercase extension.
			 * @param array<string, mixed> $options Options.
			 */
			$parsed = apply_filters( 'rp_import_parse_file', null, $path, $ext, $options );
			if ( is_wp_error( $parsed ) ) {
				return array(
					'headers' => array(),
					'rows'    => array(),
					'error'   => $parsed->get_error_message(),
				);
			}
			if ( is_array( $parsed ) ) {
				return $parsed;
			}
			return array(
				'headers' => array(),
				'rows'    => array(),
				/* translators: %s: file extension. */
				'error'   => sprintf( 'Filformatet .%s stöds inte.', $ext ),
			);
		}

		$content = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( 'json' === $ext || str_starts_with( ltrim( $content ), '[' ) || str_starts_with( ltrim( $content ), '{' ) ) {
			return self::parse_json( $content );
		}
		return self::parse_csv( $content );
	}

	/**
	 * Apply a header=>column mapping to a raw row.
	 *
	 * @param array<string,string> $row     Raw row.
	 * @param array<string,string> $mapping header => column key.
	 * @return array<string,string>
	 */
	public static function map_row( array $row, array $mapping ): array {
		$out = array();
		foreach ( $mapping as $header => $key ) {
			if ( '' !== $key && isset( $row[ $header ] ) && '' !== $row[ $header ] ) {
				$out[ $key ] = $row[ $header ];
			}
		}
		return $out;
	}

	/**
	 * Find an existing company: org.nr first, then website domain, then normalized name.
	 *
	 * @param array<string,string> $data Mapped data.
	 * @return array{id:int, by:string}
	 */
	public static function find_existing( array $data ): array {
		$orgnr = RP_Normalizer::orgnr( $data['orgnr'] ?? '' );
		if ( '' !== $orgnr ) {
			$id = self::find_by_meta( 'rp_orgnr', $orgnr );
			if ( $id ) {
				return array(
					'id' => $id,
					'by' => 'orgnr',
				);
			}
		}

		$domain = RP_Normalizer::domain( $data['webbplats'] ?? '' );
		if ( '' !== $domain ) {
			$id = self::find_by_meta( 'rp_domain', $domain );
			if ( $id ) {
				return array(
					'id' => $id,
					'by' => 'domän',
				);
			}
		}

		$name = RP_Normalizer::name( $data['namn'] ?? '' );
		if ( '' !== $name ) {
			$id = self::find_by_meta( 'rp_namn_nyckel', $name );
			if ( $id ) {
				return array(
					'id' => $id,
					'by' => 'namn',
				);
			}
		}

		return array(
			'id' => 0,
			'by' => '',
		);
	}

	/**
	 * First company with a meta value.
	 *
	 * @param string $key   Meta key.
	 * @param string $value Value.
	 */
	private static function find_by_meta( string $key, string $value ): int {
		$ids = get_posts(
			array(
				'post_type'        => RP_Post_Types::COMPANY,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Dry run: what would happen to each row.
	 *
	 * @param array<int, array<string,string>> $rows    Mapped rows.
	 * @param bool                             $overwrite Overwrite existing values.
	 * @return array<int, array{row:int, namn:string, action:string, id:int, by:string, message:string}>
	 */
	public static function preview( array $rows, bool $overwrite = false ): array {
		$out  = array();
		$seen = array();
		foreach ( $rows as $i => $data ) {
			$name = trim( $data['namn'] ?? '' );
			if ( '' === $name ) {
				$out[] = array(
					'row'     => $i + 1,
					'namn'    => '',
					'action'  => 'skipped',
					'id'      => 0,
					'by'      => '',
					'message' => 'Namn saknas',
				);
				continue;
			}
			$key = RP_Normalizer::orgnr( $data['orgnr'] ?? '' ) . '|' . RP_Normalizer::domain( $data['webbplats'] ?? '' ) . '|' . RP_Normalizer::name( $name );
			if ( isset( $seen[ $key ] ) ) {
				$out[] = array(
					'row'     => $i + 1,
					'namn'    => $name,
					'action'  => 'skipped',
					'id'      => 0,
					'by'      => '',
					'message' => 'Dubblett i filen (rad ' . $seen[ $key ] . ')',
				);
				continue;
			}
			$seen[ $key ] = $i + 1;
			$found        = self::find_existing( $data );
			$out[]        = array(
				'row'     => $i + 1,
				'namn'    => $name,
				'action'  => $found['id'] ? 'updated' : 'created',
				'id'      => $found['id'],
				'by'      => $found['by'],
				'message' => $found['id'] ? sprintf( 'Matchar befintligt företag via %s%s', $found['by'], $overwrite ? ' (skriver över)' : ' (fyller bara tomma fält)' ) : 'Nytt företag',
			);
		}
		return $out;
	}

	/**
	 * Create or update a company.
	 *
	 * @param array<string,string|string[]>                         $data    Mapped data (column keys).
	 * @param array{status?:string, overwrite?:bool, dry_run?:bool} $options Options.
	 * @return array{id:int, action:string, message:string}
	 */
	public static function upsert_company( array $data, array $options = array() ): array {
		$options = wp_parse_args(
			$options,
			array(
				'status'    => 'draft',
				'overwrite' => false,
				'dry_run'   => false,
			)
		);
		$data    = array_map(
			static function ( $v ) {
				return is_array( $v ) ? implode( ', ', $v ) : trim( (string) $v );
			},
			$data
		);

		$name = $data['namn'] ?? '';
		if ( '' === $name ) {
			return array(
				'id'      => 0,
				'action'  => 'skipped',
				'message' => 'Namn saknas',
			);
		}

		$found     = self::find_existing( $data );
		$id        = $found['id'];
		$overwrite = (bool) $options['overwrite'];

		if ( $options['dry_run'] ) {
			return array(
				'id'      => $id,
				'action'  => $id ? 'updated' : 'created',
				'message' => $id ? 'Matchar via ' . $found['by'] : 'Nytt företag',
			);
		}

		$postarr = array(
			'post_type' => RP_Post_Types::COMPANY,
		);
		if ( $id ) {
			$postarr['ID'] = $id;
			$existing      = get_post( $id );
			if ( $overwrite ) {
				$postarr['post_title'] = $name;
			}
			if ( isset( $data['beskrivning'] ) && ( $overwrite || '' === trim( (string) $existing->post_content ) ) ) {
				$postarr['post_content'] = wp_kses_post( $data['beskrivning'] );
			}
			if ( count( $postarr ) > 2 ) {
				$result = wp_update_post( wp_slash( $postarr ), true );
				if ( is_wp_error( $result ) ) {
					return array(
						'id'      => $id,
						'action'  => 'error',
						'message' => $result->get_error_message(),
					);
				}
			}
			$action = 'updated';
		} else {
			$status                  = in_array( $options['status'], array( 'draft', 'publish', 'pending' ), true ) ? $options['status'] : 'draft';
			$postarr['post_title']   = $name;
			$postarr['post_status']  = $status;
			$postarr['post_content'] = isset( $data['beskrivning'] ) ? wp_kses_post( $data['beskrivning'] ) : '';
			$result                  = wp_insert_post( wp_slash( $postarr ), true );
			if ( is_wp_error( $result ) ) {
				return array(
					'id'      => 0,
					'action'  => 'error',
					'message' => $result->get_error_message(),
				);
			}
			$id     = (int) $result;
			$action = 'created';
			if ( ! isset( $data['kalla'] ) ) {
				$data['kalla'] = 'import';
			}
		}

		self::save_fields( $id, $data, $overwrite || 'created' === $action );

		foreach ( array(
			'tjanster'  => RP_Post_Types::TAX_SERVICE,
			'branscher' => RP_Post_Types::TAX_INDUSTRY,
			'regioner'  => RP_Post_Types::TAX_REGION,
		) as $col => $tax ) {
			if ( ! isset( $data[ $col ] ) ) {
				continue;
			}
			$term_ids = self::ensure_terms( RP_Normalizer::list( $data[ $col ] ), $tax );
			if ( $term_ids ) {
				wp_set_object_terms( $id, $term_ids, $tax, ! $overwrite && 'updated' === $action );
			}
		}

		if ( ! empty( $data['logo_url'] ) && ( $overwrite || 'created' === $action || ! get_post_meta( $id, 'rp_logo', true ) ) ) {
			$logo = self::sideload_image( $data['logo_url'], $id, $name . ' logotyp' );
			if ( $logo ) {
				update_post_meta( $id, 'rp_logo', $logo );
			}
		}

		if ( isset( $data['paket'] ) && '' !== $data['paket'] ) {
			RP_Packages::set( $id, RP_Normalizer::package( $data['paket'] ), $data['paket_giltigt_till'] ?? '' );
		}

		RP_Post_Types::ensure_company_defaults( $id );

		/**
		 * Fires after a company has been imported.
		 *
		 * @param int    $id     Company ID.
		 * @param string $action created|updated.
		 * @param array  $data   Imported data.
		 */
		do_action( 'rp_company_imported', $id, $action, $data );

		return array(
			'id'      => $id,
			'action'  => $action,
			'message' => 'created' === $action ? 'Skapat' : 'Uppdaterat (matchat via ' . ( $found['by'] ? $found['by'] : 'id' ) . ')',
		);
	}

	/**
	 * Save meta fields and the derived duplicate keys.
	 *
	 * @param int                  $id        Company.
	 * @param array<string,string> $data      Data.
	 * @param bool                 $overwrite Overwrite non-empty values.
	 */
	public static function save_fields( int $id, array $data, bool $overwrite ): void {
		foreach ( RP_Post_Types::company_fields() as $key => $field ) {
			if ( ! array_key_exists( $key, $data ) || in_array( $field['type'], array( 'attachment', 'gallery' ), true ) ) {
				continue;
			}
			$current = (string) get_post_meta( $id, $field['meta'], true );
			if ( ! $overwrite && '' !== $current ) {
				continue;
			}
			$value = self::sanitize_field( $field['type'], (string) $data[ $key ] );
			if ( 'orgnr' === $key ) {
				$value = RP_Normalizer::orgnr( $value ) ? RP_Normalizer::orgnr( $value ) : $value;
			}
			if ( 'kort_beskrivning' === $key ) {
				$value = mb_substr( $value, 0, 300 );
			}
			update_post_meta( $id, $field['meta'], $value );
		}
		self::update_keys( $id );
	}

	/**
	 * Update hidden duplicate-detection keys.
	 *
	 * @param int $id Company.
	 */
	public static function update_keys( int $id ): void {
		update_post_meta( $id, 'rp_domain', RP_Normalizer::domain( (string) get_post_meta( $id, 'rp_webbplats', true ) ) );
		update_post_meta( $id, 'rp_namn_nyckel', RP_Normalizer::name( get_the_title( $id ) ) );
		$orgnr = RP_Normalizer::orgnr( (string) get_post_meta( $id, 'rp_orgnr', true ) );
		if ( '' !== $orgnr ) {
			update_post_meta( $id, 'rp_orgnr', $orgnr );
		}
	}

	/**
	 * Sanitize a value by field type.
	 *
	 * @param string $type  Field type.
	 * @param string $value Value.
	 */
	public static function sanitize_field( string $type, string $value ): string {
		return match ( $type ) {
			'url'      => esc_url_raw( '' !== $value && ! preg_match( '#^https?://#i', $value ) ? 'https://' . $value : $value ),
			'email'    => sanitize_email( $value ),
			'int'      => '' === $value ? '' : (string) absint( $value ),
			'textarea', 'lines' => sanitize_textarea_field( $value ),
			default    => sanitize_text_field( $value ),
		};
	}

	/**
	 * Find or create terms by name (or slug).
	 *
	 * @param string[] $names    Names.
	 * @param string   $taxonomy Taxonomy.
	 * @return int[]
	 */
	public static function ensure_terms( array $names, string $taxonomy ): array {
		$ids = array();
		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
			}
			if ( ! $term ) {
				$created = wp_insert_term( $name, $taxonomy );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$ids[] = (int) $created['term_id'];
				continue;
			}
			$ids[] = (int) $term->term_id;
		}
		return $ids;
	}

	/**
	 * Download an image into the media library.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Parent.
	 * @param string $desc    Alt/description.
	 */
	public static function sideload_image( string $url, int $post_id, string $desc ): int {
		if ( ! wp_http_validate_url( $url ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_sideload_image( $url, $post_id, $desc, 'id' );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( (int) $id, '_wp_attachment_image_alt', $desc );
		return (int) $id;
	}

	/**
	 * Import many rows and write a log entry.
	 *
	 * @param array<int, array<string,string>> $rows    Mapped rows.
	 * @param array<string, mixed>             $options Options for upsert_company.
	 * @param string                           $source  Where the import came from (admin, cli, rest).
	 * @return array{created:int, updated:int, skipped:int, errors:int, rows:array<int, array{row:int, namn:string, id:int, action:string, message:string}>}
	 */
	public static function import_rows( array $rows, array $options, string $source ): array {
		$summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'rows'    => array(),
		);
		$seen    = array();

		wp_defer_term_counting( true );
		foreach ( $rows as $i => $data ) {
			$key = RP_Normalizer::orgnr( $data['orgnr'] ?? '' ) . '|' . RP_Normalizer::domain( $data['webbplats'] ?? '' ) . '|' . RP_Normalizer::name( $data['namn'] ?? '' );
			if ( isset( $seen[ $key ] ) && empty( $options['dry_run'] ) ) {
				$result = array(
					'id'      => 0,
					'action'  => 'skipped',
					'message' => 'Dubblett i filen',
				);
			} else {
				$result = self::upsert_company( $data, $options );
			}
			$seen[ $key ] = true;

			$bucket = match ( $result['action'] ) {
				'created' => 'created',
				'updated' => 'updated',
				'error'   => 'errors',
				default   => 'skipped',
			};
			++$summary[ $bucket ];
			$summary['rows'][] = array(
				'row'     => $i + 1,
				'namn'    => (string) ( $data['namn'] ?? '' ),
				'id'      => $result['id'],
				'action'  => $result['action'],
				'message' => $result['message'],
			);
		}
		wp_defer_term_counting( false );

		if ( empty( $options['dry_run'] ) ) {
			$log = get_option( 'rp_import_log', array() );
			$log = is_array( $log ) ? $log : array();
			array_unshift(
				$log,
				array(
					'time'    => current_time( 'mysql' ),
					'user'    => get_current_user_id(),
					'source'  => $source,
					'created' => $summary['created'],
					'updated' => $summary['updated'],
					'skipped' => $summary['skipped'],
					'errors'  => $summary['errors'],
					'rows'    => array_slice( $summary['rows'], 0, 500 ),
				)
			);
			update_option( 'rp_import_log', array_slice( $log, 0, 20 ), false );
		}

		return $summary;
	}
}
