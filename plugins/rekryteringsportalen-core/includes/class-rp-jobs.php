<?php
/**
 * Jobs: feed import, expiry and per-company job counts.
 *
 * @package RekryteringsportalenCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jobs.
 */
final class RP_Jobs {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', array( self::class, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'rp_import_jobs', array( self::class, 'import_all_feeds' ) );
		add_action( 'rp_daily', array( self::class, 'expire_jobs' ) );
		add_action( 'save_post_' . RP_Post_Types::JOB, array( self::class, 'on_save' ), 20, 2 );
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
		add_action( 'before_delete_post', array( self::class, 'on_delete' ), 10, 2 );
	}

	/**
	 * Two-hourly schedule.
	 *
	 * @param array<string, array{interval:int, display:string}> $schedules Schedules.
	 * @return array<string, array{interval:int, display:string}>
	 */
	public static function cron_schedules( $schedules ): array {
		$schedules['rp_two_hours'] = array(
			'interval' => 2 * HOUR_IN_SECONDS,
			'display'  => 'Varannan timme',
		);
		return $schedules;
	}

	/**
	 * Update company job count after save.
	 *
	 * @param int     $post_id Job.
	 * @param WP_Post $post    Post.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$company = (int) get_post_meta( $post_id, 'rp_foretag_id', true );
		if ( $company ) {
			self::update_count( $company );
		}
	}

	/**
	 * Recount when a job changes status.
	 *
	 * @param string  $new_status New.
	 * @param string  $old_status Old.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( $post instanceof WP_Post && RP_Post_Types::JOB === $post->post_type && $new_status !== $old_status ) {
			$company = (int) get_post_meta( $post->ID, 'rp_foretag_id', true );
			if ( $company ) {
				self::update_count( $company );
			}
		}
	}

	/**
	 * Recount after delete.
	 *
	 * @param int     $post_id Post.
	 * @param WP_Post $post    Post.
	 */
	public static function on_delete( $post_id, $post = null ): void {
		if ( $post instanceof WP_Post && RP_Post_Types::JOB === $post->post_type ) {
			$company = (int) get_post_meta( $post_id, 'rp_foretag_id', true );
			if ( $company ) {
				add_action(
					'deleted_post',
					static function () use ( $company ) {
						RP_Jobs::update_count( $company );
					}
				);
			}
		}
	}

	/**
	 * Store number of published jobs on the company.
	 *
	 * @param int $company_id Company.
	 */
	public static function update_count( int $company_id ): void {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::JOB,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => 'rp_foretag_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $company_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		update_post_meta( $company_id, 'rp_antal_jobb', count( $ids ) );
	}

	/**
	 * Published jobs for a company.
	 *
	 * @param int $company_id Company.
	 * @param int $limit      Max.
	 * @return WP_Post[]
	 */
	public static function for_company( int $company_id, int $limit = 20 ): array {
		return get_posts(
			array(
				'post_type'      => RP_Post_Types::JOB,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'rp_foretag_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $company_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * Daily: set jobs past their last application day to "utgången".
	 */
	public static function expire_jobs(): void {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::JOB,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'rp_sista_ansokningsdag',
						'value'   => current_time( 'Y-m-d' ),
						'compare' => '<',
						'type'    => 'DATE',
					),
					array(
						'key'     => 'rp_sista_ansokningsdag',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		foreach ( $ids as $id ) {
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => RP_Post_Types::STATUS_EXPIRED,
				)
			);
		}
	}

	/**
	 * Import feeds for all companies that have one.
	 *
	 * @return array<int, array{created:int, updated:int, error:string}>
	 */
	public static function import_all_feeds(): array {
		$ids = get_posts(
			array(
				'post_type'      => RP_Post_Types::COMPANY,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => 'rp_jobbfeed_url',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = self::import_feed( (int) $id );
		}
		return $out;
	}

	/**
	 * Import a company's feed (RSS/Atom via SimplePie, or JSON).
	 *
	 * JSON format: array of { id, title, url, description, location, employment_type, deadline } (Swedish keys also accepted).
	 *
	 * @param int $company_id Company.
	 * @return array{created:int, updated:int, error:string}
	 */
	public static function import_feed( int $company_id ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'error'   => '',
		);
		$url    = (string) get_post_meta( $company_id, 'rp_jobbfeed_url', true );
		if ( '' === $url ) {
			return $result;
		}

		$items = array();
		$res   = wp_safe_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			$result['error'] = $res->get_error_message();
			update_post_meta( $company_id, 'rp_jobbfeed_fel', $result['error'] );
			return $result;
		}
		$body = (string) wp_remote_retrieve_body( $res );
		$json = json_decode( $body, true );

		if ( is_array( $json ) ) {
			$list = $json['jobs'] ?? $json['jobb'] ?? $json;
			foreach ( (array) $list as $j ) {
				if ( ! is_array( $j ) ) {
					continue;
				}
				$items[] = array(
					'id'          => (string) ( $j['id'] ?? $j['url'] ?? $j['ansok_url'] ?? '' ),
					'title'       => (string) ( $j['title'] ?? $j['titel'] ?? '' ),
					'url'         => (string) ( $j['url'] ?? $j['ansok_url'] ?? '' ),
					'description' => (string) ( $j['description'] ?? $j['beskrivning'] ?? '' ),
					'location'    => (string) ( $j['location'] ?? $j['ort'] ?? '' ),
					'type'        => (string) ( $j['employment_type'] ?? $j['anstallningsform'] ?? '' ),
					'deadline'    => (string) ( $j['deadline'] ?? $j['sista_ansokningsdag'] ?? '' ),
				);
			}
		} else {
			require_once ABSPATH . WPINC . '/feed.php';
			$feed = fetch_feed( $url );
			if ( is_wp_error( $feed ) ) {
				$result['error'] = $feed->get_error_message();
				update_post_meta( $company_id, 'rp_jobbfeed_fel', $result['error'] );
				return $result;
			}
			foreach ( $feed->get_items( 0, 200 ) as $item ) {
				$items[] = array(
					'id'          => (string) ( $item->get_id() ? $item->get_id() : $item->get_permalink() ),
					'title'       => (string) $item->get_title(),
					'url'         => (string) $item->get_permalink(),
					'description' => (string) $item->get_description(),
					'location'    => '',
					'type'        => '',
					'deadline'    => '',
				);
			}
		}

		foreach ( $items as $item ) {
			if ( '' === trim( $item['title'] ) || '' === $item['id'] ) {
				continue;
			}
			$saved = self::upsert_job( $company_id, $item );
			if ( 'created' === $saved ) {
				++$result['created'];
			} elseif ( 'updated' === $saved ) {
				++$result['updated'];
			}
		}
		delete_post_meta( $company_id, 'rp_jobbfeed_fel' );
		update_post_meta( $company_id, 'rp_jobbfeed_senast', current_time( 'mysql' ) );
		self::update_count( $company_id );
		return $result;
	}

	/**
	 * Create or update a job, deduplicated on (company, external id).
	 *
	 * @param int                  $company_id Company.
	 * @param array<string,string> $item       id, title, url, description, location, type, deadline.
	 * @return string created|updated|skipped
	 */
	public static function upsert_job( int $company_id, array $item ): string {
		$extern   = $company_id . ':' . md5( $item['id'] );
		$found    = get_posts(
			array(
				'post_type'      => RP_Post_Types::JOB,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => 'rp_extern_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $extern, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$deadline = RP_Normalizer::date( $item['deadline'] ?? '' );
		if ( $found && RP_Post_Types::STATUS_EXPIRED === get_post_status( $found[0] ) ) {
			return 'skipped';
		}

		$postarr = array(
			'post_type'    => RP_Post_Types::JOB,
			'post_title'   => wp_strip_all_tags( $item['title'] ),
			'post_content' => wp_kses_post( $item['description'] ?? '' ),
			'post_status'  => ( '' !== $deadline && $deadline < current_time( 'Y-m-d' ) ) ? RP_Post_Types::STATUS_EXPIRED : 'publish',
		);
		if ( $found ) {
			$postarr['ID'] = $found[0];
			wp_update_post( wp_slash( $postarr ) );
			$id     = (int) $found[0];
			$action = 'updated';
		} else {
			$id     = (int) wp_insert_post( wp_slash( $postarr ) );
			$action = 'created';
		}
		if ( ! $id ) {
			return 'skipped';
		}

		update_post_meta( $id, 'rp_foretag_id', $company_id );
		update_post_meta( $id, 'rp_extern_id', $extern );
		update_post_meta( $id, 'rp_ansok_url', esc_url_raw( $item['url'] ?? '' ) );
		update_post_meta( $id, 'rp_sista_ansokningsdag', $deadline );
		if ( ! empty( $item['location'] ) ) {
			update_post_meta( $id, 'rp_ort', sanitize_text_field( $item['location'] ) );
			$region = get_term_by( 'name', $item['location'], RP_Post_Types::TAX_REGION );
			if ( $region ) {
				wp_set_object_terms( $id, array( (int) $region->term_id ), RP_Post_Types::TAX_REGION );
			}
		}
		if ( ! empty( $item['type'] ) ) {
			update_post_meta( $id, 'rp_anstallningsform', sanitize_text_field( $item['type'] ) );
		}
		// Inherit the company's industries so industry filters work for feed jobs.
		if ( 'created' === $action ) {
			$industries = wp_get_object_terms( $company_id, RP_Post_Types::TAX_INDUSTRY, array( 'fields' => 'ids' ) );
			if ( $industries && ! is_wp_error( $industries ) ) {
				wp_set_object_terms( $id, $industries, RP_Post_Types::TAX_INDUSTRY );
			}
		}
		return $action;
	}
}
