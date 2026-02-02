<?php
/**
 * SEOgen Cron API
 * REST API endpoint for Railway cron to trigger scheduled publishing
 *
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Cron_API {

	/**
	 * Register REST API routes
	 */
	public static function register_routes() {
		register_rest_route( 'seogen/v1', '/publish-scheduled', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'publish_scheduled_posts' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		register_rest_route( 'seogen/v1', '/cron-status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_cron_status' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );
	}

	/**
	 * Verify API key from request
	 *
	 * @param WP_REST_Request $request Request object
	 * @return bool True if API key is valid
	 */
	public static function verify_api_key( $request ) {
		$provided_key = $request->get_param( 'api_key' );

		if ( empty( $provided_key ) ) {
			return false;
		}

		// Get stored API key from license settings
		$settings = get_option( 'seogen_settings', array() );
		$stored_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';

		if ( empty( $stored_key ) ) {
			return false;
		}

		return $provided_key === $stored_key;
	}

	/**
	 * Publish scheduled posts (called by Railway cron)
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response with publish results
	 */
	public static function publish_scheduled_posts( $request ) {
		// Load scheduler
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-publishing-scheduler.php';

		$scheduler = new SEOgen_Publishing_Scheduler();
		$published = $scheduler->publish_scheduled_posts();
		$pending = $scheduler->get_pending_count();

		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen Cron API] Published %d posts, %d pending',
				$published,
				$pending
			) );
		}

		return new WP_REST_Response( array(
			'success'   => true,
			'published' => $published,
			'pending'   => $pending,
			'timestamp' => current_time( 'mysql' ),
		), 200 );
	}

	/**
	 * Get cron status (for monitoring)
	 *
	 * @param WP_REST_Request $request Request object
	 * @return WP_REST_Response Response with cron status
	 */
	public static function get_cron_status( $request ) {
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-publishing-scheduler.php';

		$scheduler = new SEOgen_Publishing_Scheduler();
		$pending = $scheduler->get_pending_count();

		// Get next scheduled post time
		global $wpdb;
		$next_post = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(pm.meta_value)
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND p.post_status = 'draft'
			AND pm.meta_value > %s",
			'_seogen_scheduled_publish_timestamp',
			current_time( 'timestamp' )
		) );

		$next_publish_time = null;
		if ( $next_post ) {
			$next_publish_time = gmdate( 'Y-m-d H:i:s', (int) $next_post );
		}

		return new WP_REST_Response( array(
			'success'           => true,
			'pending_count'     => $pending,
			'next_publish_time' => $next_publish_time,
			'current_time'      => current_time( 'mysql' ),
			'wordpress_url'     => home_url(),
		), 200 );
	}
}
