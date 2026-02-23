<?php
/**
 * SEOgen Publishing Scheduler
 * Handles scheduled publishing of bulk-imported pages at a configurable rate
 * Uses Action Scheduler for reliable background processing
 *
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Publishing_Scheduler {

	/**
	 * Action Scheduler hook name for scheduled publishing
	 */
	const ACTION_HOOK = 'seogen_publish_scheduled_posts';

	/**
	 * Action Scheduler group for organizing actions
	 */
	const ACTION_GROUP = 'seogen';

	/**
	 * Register hooks and initialize scheduler
	 */
	public function register_hooks() {
		// Register Action Scheduler callback
		add_action( self::ACTION_HOOK, array( $this, 'publish_scheduled_posts' ) );

		// Handle manual post status transitions (cleanup scheduling meta)
		add_action( 'transition_post_status', array( $this, 'handle_post_status_transition' ), 10, 3 );

		// Ensure action is scheduled on settings save
		add_action( 'update_option_seogen_publishing_settings', array( $this, 'reschedule_action' ), 10, 2 );
	}

	/**
	 * Schedule a post for publishing by adding it to the queue
	 * Calculates staggered publish timestamp based on current queue position
	 *
	 * @param int $post_id Post ID to schedule
	 * @return bool Success
	 */
	public function schedule_post_for_publishing( $post_id ) {
		if ( ! $post_id ) {
			return false;
		}

		// Get settings
		$settings = get_option( 'seogen_publishing_settings', array() );
		$enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : true;

		// If scheduling is disabled, don't add to queue
		if ( ! $enabled ) {
			return false;
		}

		$pages_per_day = isset( $settings['pages_per_day'] ) ? (int) $settings['pages_per_day'] : 10;
		$publish_time = isset( $settings['publish_time'] ) ? $settings['publish_time'] : '09:00';

		// Ensure pages_per_day is at least 1
		if ( $pages_per_day < 1 ) {
			$pages_per_day = 10;
		}

		// NOTE: Queue counter is NOT reset here — concurrent imports would all see
		// pending_count=0 and reset to 0, causing all posts to get the same date.
		// Counter is reset only in publish_scheduled_posts() after all posts are published.

		// Calculate publish timestamp
		$publish_timestamp = $this->get_next_publish_timestamp( $pages_per_day, $publish_time );

		// Store scheduling meta
		update_post_meta( $post_id, '_seogen_scheduled_publish_timestamp', $publish_timestamp );
		update_post_meta( $post_id, '_seogen_publish_scheduled_at', gmdate( 'Y-m-d H:i:s', $publish_timestamp ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen Scheduler] Post %d scheduled for %s (timestamp: %d, position: queue)',
				$post_id,
				gmdate( 'Y-m-d H:i:s', $publish_timestamp ),
				$publish_timestamp
			) );
		}

		// Ensure Action Scheduler recurring action is scheduled
		$this->ensure_action_scheduled();

		return true;
	}

	/**
	 * Calculate next publish timestamp based on queue position
	 *
	 * @param int $pages_per_day Number of pages to publish per day
	 * @param string $publish_time Time in HH:MM format
	 * @return int Unix timestamp
	 */
	protected function get_next_publish_timestamp( $pages_per_day, $publish_time ) {
		// Use atomic increment to prevent race conditions
		// This ensures each post gets a unique queue position even with concurrent requests
		$queue_position = $this->get_and_increment_queue_position();

		// TEST MODE: For quick testing, define SEOGEN_TEST_SCHEDULING in wp-config.php
		// This will schedule posts starting in 5 minutes with 2-minute intervals between batches
		// Action Scheduler will check every 10 minutes to publish ready posts
		if ( defined( 'SEOGEN_TEST_SCHEDULING' ) && SEOGEN_TEST_SCHEDULING ) {
			// Calculate how many batches this post is in
			$batch_number = floor( $queue_position / $pages_per_day );

			// Start in 5 minutes, then add 2 minutes per batch
			$base_timestamp = current_time( 'timestamp' ) + ( 5 * MINUTE_IN_SECONDS );
			$publish_timestamp = $base_timestamp + ( $batch_number * 2 * MINUTE_IN_SECONDS );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen TEST MODE] Post queued at position %d, batch %d, will publish in %d minutes',
					$queue_position,
					$batch_number,
					round( ( $publish_timestamp - current_time( 'timestamp' ) ) / 60 )
				) );
			}

			return $publish_timestamp;
		}

		// PRODUCTION MODE: Normal scheduling starting tomorrow
		// Calculate which day this post should publish
		$days_to_wait = floor( $queue_position / $pages_per_day );

		// Parse publish time
		$time_parts = explode( ':', $publish_time );
		$hour = isset( $time_parts[0] ) ? (int) $time_parts[0] : 9;
		$minute = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

		// Calculate tomorrow at specific time
		$publish_timestamp = strtotime(
			sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ),
			current_time( 'timestamp' )
		);

		// Add days to wait
		$publish_timestamp = $publish_timestamp + ( $days_to_wait * DAY_IN_SECONDS );

		return $publish_timestamp;
	}

	/**
	 * Get and atomically increment the queue position counter
	 * Uses MySQL LAST_INSERT_ID() for a truly atomic read-and-increment
	 * that is safe under concurrent requests.
	 *
	 * @return int Queue position (0-indexed)
	 */
	protected function get_and_increment_queue_position() {
		global $wpdb;

		$option_name = '_seogen_schedule_queue_position';

		// Ensure the row exists (no-op if already present)
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload)
			VALUES (%s, '0', 'no')",
			$option_name
		) );

		// Atomically: capture current value into LAST_INSERT_ID, then increment.
		// LAST_INSERT_ID(option_value) stores the pre-increment value per-connection,
		// so concurrent requests each get their own unique position.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options}
			SET option_value = LAST_INSERT_ID(option_value) + 1
			WHERE option_name = %s",
			$option_name
		) );

		// Retrieve the pre-increment value (unique to this connection)
		$position = (int) $wpdb->get_var( "SELECT LAST_INSERT_ID()" );

		// Flush the WP object cache for this option so subsequent get_option()
		// calls in the same request don't return stale data
		wp_cache_delete( $option_name, 'options' );

		return $position;
	}

	/**
	 * Reset the queue position counter
	 * Call this after posts are published or when starting a new import batch
	 */
	public function reset_queue_position() {
		global $wpdb;

		// Reset to 0 instead of deleting — the row is reused by get_and_increment
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = '0' WHERE option_name = %s",
			'_seogen_schedule_queue_position'
		) );
		wp_cache_delete( '_seogen_schedule_queue_position', 'options' );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen Scheduler] Queue position counter reset' );
		}
	}

	/**
	 * Get count of posts pending scheduled publishing
	 *
	 * @return int Count of pending posts
	 */
	public function get_pending_count() {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = %s
			AND p.post_status = %s
			AND pm.meta_key = %s",
			'service_page',
			'draft',
			'_seogen_scheduled_publish_timestamp'
		) );

		return (int) $count;
	}

	/**
	 * Get scheduled posts ready to be published
	 *
	 * @param int $limit Maximum number of posts to return
	 * @return array Array of WP_Post objects
	 */
	protected function get_scheduled_posts( $limit = 10 ) {
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => 'draft',
			'posts_per_page' => $limit,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_seogen_scheduled_publish_timestamp',
					'value'   => current_time( 'timestamp' ),
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => '_hyper_local_managed',
					'value'   => '1',
					'compare' => '=',
				),
			),
			'orderby'        => 'meta_value_num',
			'order'          => 'ASC',
			'meta_key'       => '_seogen_scheduled_publish_timestamp',
		);

		return get_posts( $args );
	}

	/**
	 * Publish scheduled posts (Action Scheduler callback)
	 * This is the main function called by Action Scheduler
	 *
	 * @return int Number of posts published
	 */
	public function publish_scheduled_posts() {
		// Get settings
		$settings = get_option( 'seogen_publishing_settings', array() );
		$enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : true;
		$pages_per_day = isset( $settings['pages_per_day'] ) ? (int) $settings['pages_per_day'] : 10;

		// If scheduling is disabled, exit early
		if ( ! $enabled ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen Scheduler] Scheduled publishing is disabled, skipping' );
			}
			return 0;
		}

		// Ensure pages_per_day is at least 1
		if ( $pages_per_day < 1 ) {
			$pages_per_day = 10;
		}

		// Get posts to publish
		$posts = $this->get_scheduled_posts( $pages_per_day );

		if ( empty( $posts ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen Scheduler] No posts ready for publishing' );
			}
			return 0;
		}

		$published_count = 0;

		foreach ( $posts as $post ) {
			// Publish the post
			$result = wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'publish',
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				// Remove scheduling meta
				delete_post_meta( $post->ID, '_seogen_scheduled_publish_timestamp' );
				delete_post_meta( $post->ID, '_seogen_publish_scheduled_at' );

				// Add published meta for tracking
				update_post_meta( $post->ID, '_seogen_published_at', current_time( 'mysql' ) );
				update_post_meta( $post->ID, '_seogen_published_via', 'scheduled' );

				$published_count++;

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'[SEOgen Scheduler] Published post %d: %s',
						$post->ID,
						$post->post_title
					) );
				}
			} else {
				error_log( sprintf(
					'[SEOgen Scheduler] ERROR publishing post %d: %s',
					$post->ID,
					$result->get_error_message()
				) );
			}
		}

		// Log summary
		error_log( sprintf(
			'[SEOgen Scheduler] Batch complete: %d/%d posts published',
			$published_count,
			count( $posts )
		) );

		// Reset queue position counter if no more pending posts
		// This keeps the counter from growing indefinitely
		if ( $this->get_pending_count() === 0 ) {
			$this->reset_queue_position();
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen Scheduler] Queue position counter reset (no pending posts)' );
			}
		}

		return $published_count;
	}

	/**
	 * Handle post status transitions to clean up scheduling meta
	 * If a post is manually published, remove scheduling meta
	 *
	 * @param string $new_status New post status
	 * @param string $old_status Old post status
	 * @param WP_Post $post Post object
	 */
	public function handle_post_status_transition( $new_status, $old_status, $post ) {
		// Only handle service_page posts
		if ( 'service_page' !== $post->post_type ) {
			return;
		}

		// If post is being published and has scheduling meta, remove it
		if ( 'publish' === $new_status && 'draft' === $old_status ) {
			$has_scheduling_meta = get_post_meta( $post->ID, '_seogen_scheduled_publish_timestamp', true );

			if ( $has_scheduling_meta ) {
				delete_post_meta( $post->ID, '_seogen_scheduled_publish_timestamp' );
				delete_post_meta( $post->ID, '_seogen_publish_scheduled_at' );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf(
						'[SEOgen Scheduler] Removed scheduling meta from manually published post %d',
						$post->ID
					) );
				}
			}
		}
	}

	/**
	 * Ensure Action Scheduler recurring action is scheduled
	 * Called on plugin activation and when posts are scheduled
	 */
	public function ensure_action_scheduled() {
		// Ensure Action Scheduler is available
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen Scheduler] Action Scheduler not available yet' );
			}
			return;
		}

		// Check if action is already scheduled
		if ( as_next_scheduled_action( self::ACTION_HOOK, array(), self::ACTION_GROUP ) ) {
			return; // Already scheduled
		}

		// TEST MODE: Run every 1 minute for testing
		if ( defined( 'SEOGEN_TEST_SCHEDULING' ) && SEOGEN_TEST_SCHEDULING ) {
			// Schedule recurring action every 1 minute starting in 30 seconds
			$next_run = current_time( 'timestamp' ) + ( 30 ); // 30 seconds

			as_schedule_recurring_action(
				$next_run,
				MINUTE_IN_SECONDS, // 1 minute
				self::ACTION_HOOK,
				array(),
				self::ACTION_GROUP
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen TEST MODE] Action Scheduler configured to run every 1 minute, starting at %s',
					gmdate( 'Y-m-d H:i:s', $next_run )
				) );
			}
			return;
		}

		// PRODUCTION MODE: Run daily at configured time
		$settings = get_option( 'seogen_publishing_settings', array() );
		$publish_time = isset( $settings['publish_time'] ) ? $settings['publish_time'] : '09:00';

		// Parse publish time
		$time_parts = explode( ':', $publish_time );
		$hour = isset( $time_parts[0] ) ? (int) $time_parts[0] : 9;
		$minute = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

		// Calculate tomorrow at specific time
		$next_run = strtotime(
			sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ),
			current_time( 'timestamp' )
		);

		// Schedule recurring daily action
		as_schedule_recurring_action(
			$next_run,
			DAY_IN_SECONDS,
			self::ACTION_HOOK,
			array(),
			self::ACTION_GROUP
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen Scheduler] Action Scheduler configured to run daily at %s, starting at %s',
				$publish_time,
				gmdate( 'Y-m-d H:i:s', $next_run )
			) );
		}
	}

	/**
	 * Reschedule action when settings change
	 *
	 * @param array $old_value Old settings
	 * @param array $new_value New settings
	 */
	public function reschedule_action( $old_value, $new_value ) {
		// Ensure Action Scheduler is available
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		// Unschedule all existing actions for this hook
		as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );

		// Reschedule with new settings
		$this->ensure_action_scheduled();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen Scheduler] Action Scheduler rescheduled due to settings change' );
		}
	}

	/**
	 * Get settings with defaults
	 *
	 * @return array Settings array
	 */
	public static function get_settings() {
		$defaults = array(
			'enabled'       => true,
			'pages_per_day' => 10,
			'publish_time'  => '09:00',
		);

		$settings = get_option( 'seogen_publishing_settings', array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Manually trigger publishing (for admin override)
	 *
	 * @param int $limit Number of posts to publish (default: all pending)
	 * @return int Number of posts published
	 */
	public function manual_publish( $limit = 0 ) {
		$settings = self::get_settings();
		$pages_per_day = $settings['pages_per_day'];

		// If no limit specified, use pages_per_day setting
		if ( $limit === 0 ) {
			$limit = $pages_per_day;
		}

		// Get posts
		$posts = $this->get_scheduled_posts( $limit );

		$published_count = 0;

		foreach ( $posts as $post ) {
			$result = wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'publish',
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				delete_post_meta( $post->ID, '_seogen_scheduled_publish_timestamp' );
				delete_post_meta( $post->ID, '_seogen_publish_scheduled_at' );
				update_post_meta( $post->ID, '_seogen_published_at', current_time( 'mysql' ) );
				update_post_meta( $post->ID, '_seogen_published_via', 'manual' );

				$published_count++;
			}
		}

		return $published_count;
	}

	/**
	 * Reschedule all pending draft pages with correct staggered timestamps.
	 * Fixes pages that were all assigned the same date due to the race condition.
	 *
	 * @return int Number of posts rescheduled
	 */
	public function reschedule_all_pending() {
		$settings = get_option( 'seogen_publishing_settings', array() );
		$enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : true;

		if ( ! $enabled ) {
			return 0;
		}

		$pages_per_day = isset( $settings['pages_per_day'] ) ? (int) $settings['pages_per_day'] : 10;
		$publish_time = isset( $settings['publish_time'] ) ? $settings['publish_time'] : '09:00';

		if ( $pages_per_day < 1 ) {
			$pages_per_day = 10;
		}

		// Get ALL draft service_pages with scheduling meta, ordered by ID
		$posts = get_posts( array(
			'post_type'      => 'service_page',
			'post_status'    => 'draft',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_seogen_scheduled_publish_timestamp',
					'compare' => 'EXISTS',
				),
			),
			'orderby' => 'ID',
			'order'   => 'ASC',
			'fields'  => 'ids',
			'no_found_rows' => true,
		) );

		if ( empty( $posts ) ) {
			return 0;
		}

		// Check if all posts share the same timestamp (the race condition symptom)
		$timestamps = array();
		foreach ( $posts as $post_id ) {
			$ts = get_post_meta( $post_id, '_seogen_scheduled_publish_timestamp', true );
			$timestamps[ $ts ] = true;
		}

		// Only reschedule if all posts have the same timestamp (race condition)
		if ( count( $timestamps ) > 1 ) {
			return 0; // Already staggered, nothing to fix
		}

		// Reset the counter and reassign staggered timestamps
		$this->reset_queue_position();

		// Parse publish time
		$time_parts = explode( ':', $publish_time );
		$hour = isset( $time_parts[0] ) ? (int) $time_parts[0] : 9;
		$minute = isset( $time_parts[1] ) ? (int) $time_parts[1] : 0;

		$rescheduled = 0;
		foreach ( $posts as $position => $post_id ) {
			$days_to_wait = floor( $position / $pages_per_day );

			$publish_timestamp = strtotime(
				sprintf( 'tomorrow %02d:%02d:00', $hour, $minute ),
				current_time( 'timestamp' )
			);
			$publish_timestamp += $days_to_wait * DAY_IN_SECONDS;

			update_post_meta( $post_id, '_seogen_scheduled_publish_timestamp', $publish_timestamp );
			update_post_meta( $post_id, '_seogen_publish_scheduled_at', gmdate( 'Y-m-d H:i:s', $publish_timestamp ) );

			$rescheduled++;
		}

		error_log( sprintf(
			'[SEOgen Scheduler] Rescheduled %d posts across %d days (%d per day)',
			$rescheduled,
			(int) ceil( $rescheduled / $pages_per_day ),
			$pages_per_day
		) );

		return $rescheduled;
	}

	/**
	 * Create database index for scheduled publishing performance
	 * This significantly speeds up the query that finds posts ready to publish
	 *
	 * Call this on plugin activation or when scheduler is first enabled
	 */
	public static function create_database_index() {
		global $wpdb;

		// Check if index already exists
		$index_exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1)
			FROM information_schema.statistics
			WHERE table_schema = %s
			AND table_name = %s
			AND index_name = %s",
			DB_NAME,
			$wpdb->postmeta,
			'seogen_scheduled_timestamp'
		) );

		if ( $index_exists ) {
			return; // Index already exists
		}

		// Create composite index on meta_key and meta_value for numeric comparison
		// This dramatically improves the performance of the scheduled post query
		$wpdb->query(
			"CREATE INDEX seogen_scheduled_timestamp
			ON {$wpdb->postmeta} (meta_key, meta_value(20))
			WHERE meta_key = '_seogen_scheduled_publish_timestamp'"
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen Scheduler] Database index created for scheduled publishing' );
		}
	}

	/**
	 * Remove database index (for uninstall)
	 */
	public static function remove_database_index() {
		global $wpdb;

		$wpdb->query( "DROP INDEX IF EXISTS seogen_scheduled_timestamp ON {$wpdb->postmeta}" );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen Scheduler] Database index removed' );
		}
	}

	/**
	 * Unschedule all actions (for plugin deactivation)
	 */
	public static function unschedule_all() {
		// Ensure Action Scheduler is available
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( self::ACTION_HOOK, array(), self::ACTION_GROUP );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen Scheduler] All scheduled actions unscheduled' );
		}
	}
}
