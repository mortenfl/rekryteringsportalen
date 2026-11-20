<?php
/**
 * Undo an import: every import run gets a batch ID. Companies it created are moved to the trash,
 * companies it updated are restored to the values they had before the import.
 *
 * Safety: a company is skipped when it has changed since the import (manual edit, a later import,
 * a package activated or expired …) or – for created companies – when it has received reviews.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Import batches and undo.
 */
final class RP_Import_Undo {

	private const META_PREFIX = 'rp_import_';

	/**
	 * Meta keys (besides the company field metas) included in a snapshot.
	 *
	 * @var string[]
	 */
	private const EXTRA_META = array( 'rp_logo', 'rp_paket', 'rp_paket_giltigt_till' );

	/**
	 * New batch ID, e.g. "20261120-153012-a1b2c3".
	 */
	public static function new_batch(): string {
		return gmdate( 'Ymd-His' ) . '-' . strtolower( wp_generate_password( 6, false ) );
	}

	/**
	 * Whether a string looks like a batch ID (also guards the meta key).
	 *
	 * @param string $batch Batch ID.
	 */
	public static function is_valid_batch( string $batch ): bool {
		return (bool) preg_match( '/^\d{8}-\d{6}-[a-z0-9]{6}$/', $batch );
	}

	/**
	 * Meta key holding a company's record for a batch.
	 *
	 * @param string $batch Batch ID.
	 */
	private static function key( string $batch ): string {
		return self::META_PREFIX . $batch;
	}

	/**
	 * Everything an import can change on a company.
	 *
	 * @param int $id Company.
	 * @return array{title:string, content:string, meta:array<string, string|null>, terms:array<string, int[]>}
	 */
	public static function snapshot( int $id ): array {
		$post = get_post( $id );
		$keys = array_merge(
			array_values(
				array_map(
					static fn( $f ) => $f['meta'],
					RP_Post_Types::company_fields()
				)
			),
			self::EXTRA_META
		);
		$meta = array();
		foreach ( array_unique( $keys ) as $key ) {
			$meta[ $key ] = metadata_exists( 'post', $id, $key ) ? (string) get_post_meta( $id, $key, true ) : null;
		}
		$terms = array();
		foreach ( array( RP_Post_Types::TAX_SERVICE, RP_Post_Types::TAX_INDUSTRY, RP_Post_Types::TAX_REGION ) as $tax ) {
			$ids = wp_get_object_terms( $id, $tax, array( 'fields' => 'ids' ) );
			$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
			sort( $ids );
			$terms[ $tax ] = $ids;
		}
		return array(
			'title'   => $post ? (string) $post->post_title : '',
			'content' => $post ? (string) $post->post_content : '',
			'meta'    => $meta,
			'terms'   => $terms,
		);
	}

	/**
	 * Stable hash of a snapshot.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 */
	public static function fingerprint( array $snapshot ): string {
		return md5( (string) wp_json_encode( $snapshot ) );
	}

	/**
	 * Record what an import did to a company. Called at the end of upsert_company().
	 * When the same company is touched twice in one import, the first "before" is kept.
	 *
	 * @param int                       $id     Company.
	 * @param string                    $batch  Batch ID.
	 * @param string                    $action created|updated.
	 * @param array<string, mixed>|null $before Snapshot taken before an update (null when created).
	 */
	public static function record( int $id, string $batch, string $action, ?array $before ): void {
		if ( ! self::is_valid_batch( $batch ) ) {
			return;
		}
		$existing = get_post_meta( $id, self::key( $batch ), true );
		if ( is_array( $existing ) ) {
			$action = (string) $existing['action'];
			$before = $existing['before'];
		}
		update_post_meta(
			$id,
			self::key( $batch ),
			wp_slash(
				array(
					'action' => $action,
					'before' => $before,
					'after'  => self::fingerprint( self::snapshot( $id ) ),
				)
			)
		);
	}

	/**
	 * Companies touched by a batch.
	 *
	 * @param string $batch Batch ID.
	 * @return int[]
	 */
	public static function companies( string $batch ): array {
		if ( ! self::is_valid_batch( $batch ) ) {
			return array();
		}
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'        => RP_Post_Types::COMPANY,
					'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'meta_key'         => self::key( $batch ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_compare'     => 'EXISTS',
				)
			)
		);
	}

	/**
	 * Number of reviews (any status except trash/spam).
	 *
	 * @param int $id Company.
	 */
	private static function review_count( int $id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_type = %s AND comment_approved NOT IN ('trash', 'spam')",
				$id,
				RP_Ratings::TYPE
			)
		);
	}

	/**
	 * Undo (or preview undoing) a batch.
	 *
	 * @param string $batch   Batch ID.
	 * @param bool   $dry_run Only report what would happen.
	 * @return array{batch:string, trashed:int, restored:int, skipped:int, rows:array<int, array{id:int, namn:string, action:string, message:string}>}
	 */
	public static function undo( string $batch, bool $dry_run = false ): array {
		$out = array(
			'batch'    => $batch,
			'trashed'  => 0,
			'restored' => 0,
			'skipped'  => 0,
			'rows'     => array(),
		);

		foreach ( self::companies( $batch ) as $id ) {
			$record = get_post_meta( $id, self::key( $batch ), true );
			if ( ! is_array( $record ) ) {
				continue;
			}
			$name    = get_the_title( $id );
			$changed = self::fingerprint( self::snapshot( $id ) ) !== ( $record['after'] ?? '' );
			$reasons = array();
			if ( $changed ) {
				$reasons[] = 'har ändrats efter importen';
			}

			if ( 'created' === $record['action'] ) {
				$reviews = self::review_count( $id );
				if ( $reviews ) {
					/* translators: %d: number of reviews. */
					$reasons[] = sprintf( _n( 'har %d omdöme', 'har %d omdömen', $reviews, 'rekryteringsportalen-core' ), $reviews );
				}
				if ( $reasons ) {
					$out['rows'][] = self::row( $id, $name, 'skipped', 'Hoppas över: ' . implode( ', ', $reasons ) );
					++$out['skipped'];
					continue;
				}
				if ( ! $dry_run ) {
					foreach ( self::jobs( $id ) as $job ) {
						wp_trash_post( $job );
					}
					delete_post_meta( $id, self::key( $batch ) );
					wp_trash_post( $id );
				}
				$jobs          = count( self::jobs( $id ) );
				$out['rows'][] = self::row( $id, $name, 'trashed', 'Flyttas till papperskorgen' . ( $dry_run && $jobs ? sprintf( ' (med %d jobb)', $jobs ) : '' ) );
				++$out['trashed'];
				continue;
			}

			// Updated company.
			if ( $reasons || ! is_array( $record['before'] ?? null ) ) {
				$out['rows'][] = self::row( $id, $name, 'skipped', 'Hoppas över: ' . ( $reasons ? implode( ', ', $reasons ) : 'tidigare värden saknas' ) );
				++$out['skipped'];
				continue;
			}
			if ( ! $dry_run ) {
				self::restore( $id, $record['before'] );
				delete_post_meta( $id, self::key( $batch ) );
			}
			$out['rows'][] = self::row( $id, $name, 'restored', 'Återställs till värdena före importen' );
			++$out['restored'];
		}

		if ( ! $dry_run ) {
			self::mark_log( $batch, $out );
			/**
			 * Fires after an import has been undone.
			 *
			 * @param string $batch  Batch ID.
			 * @param array  $result Summary.
			 */
			do_action( 'rp_import_undone', $batch, $out );
		}
		return $out;
	}

	/**
	 * Result row.
	 *
	 * @param int    $id      Company.
	 * @param string $name    Name.
	 * @param string $action  trashed|restored|skipped.
	 * @param string $message Message.
	 * @return array{id:int, namn:string, action:string, message:string}
	 */
	private static function row( int $id, string $name, string $action, string $message ): array {
		return array(
			'id'      => $id,
			'namn'    => $name,
			'action'  => $action,
			'message' => $message,
		);
	}

	/**
	 * Job IDs belonging to a company (not already trashed).
	 *
	 * @param int $id Company.
	 * @return int[]
	 */
	private static function jobs( int $id ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => RP_Post_Types::JOB,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', RP_Post_Types::STATUS_EXPIRED ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => 'rp_foretag_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'     => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			)
		);
	}

	/**
	 * Put a company back to a snapshot.
	 *
	 * @param int                  $id     Company.
	 * @param array<string, mixed> $before Snapshot.
	 */
	public static function restore( int $id, array $before ): void {
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $id,
					'post_title'   => (string) $before['title'],
					'post_content' => (string) $before['content'],
				)
			)
		);
		foreach ( (array) $before['meta'] as $key => $value ) {
			if ( null === $value ) {
				delete_post_meta( $id, $key );
			} else {
				update_post_meta( $id, $key, wp_slash( (string) $value ) );
			}
		}
		foreach ( (array) $before['terms'] as $tax => $ids ) {
			wp_set_object_terms( $id, array_map( 'intval', (array) $ids ), $tax, false );
		}
		// Keep derived package state (rp_utvald) consistent.
		$paket = $before['meta']['rp_paket'] ?? null;
		RP_Packages::set( $id, null === $paket || '' === $paket ? RP_Packages::NONE : $paket, (string) ( $before['meta']['rp_paket_giltigt_till'] ?? '' ) );
		if ( null === $paket ) {
			delete_post_meta( $id, 'rp_paket' );
			RP_Post_Types::ensure_company_defaults( $id );
		}
		RP_Importer::update_keys( $id );
	}

	/**
	 * Mark the batch as undone in the import log.
	 *
	 * @param string               $batch  Batch ID.
	 * @param array<string, mixed> $result Undo result.
	 */
	private static function mark_log( string $batch, array $result ): void {
		$log = get_option( 'rp_import_log', array() );
		if ( ! is_array( $log ) ) {
			return;
		}
		foreach ( $log as $i => $entry ) {
			if ( ( $entry['batch'] ?? '' ) === $batch ) {
				$log[ $i ]['undone'] = array(
					'time'     => current_time( 'mysql' ),
					'user'     => get_current_user_id(),
					'trashed'  => $result['trashed'],
					'restored' => $result['restored'],
					'skipped'  => $result['skipped'],
				);
			}
		}
		update_option( 'rp_import_log', $log, false );
	}

	/**
	 * Log entry for a batch, if it is still in the log.
	 *
	 * @param string $batch Batch ID.
	 * @return array<string, mixed>|null
	 */
	public static function log_entry( string $batch ): ?array {
		foreach ( (array) get_option( 'rp_import_log', array() ) as $entry ) {
			if ( is_array( $entry ) && ( $entry['batch'] ?? '' ) === $batch ) {
				return $entry;
			}
		}
		return null;
	}
}
