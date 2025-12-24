<?php
/**
 * License Management for SEOgen Plugin
 * 
 * Handles license validation, site registration with Railway backend,
 * and webhook endpoint for license status updates.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_License {
	
	/**
	 * Railway backend URL
	 */
	const BACKEND_URL = 'https://your-railway-backend.com'; // TODO: Update with actual Railway URL
	
	/**
	 * Initialize license management
	 */
	public static function init() {
		// Register REST API endpoint for license status updates
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		
		// Hook into API key save to register site
		add_action( 'update_option_seogen_settings', array( __CLASS__, 'handle_api_key_update' ), 10, 2 );
	}
	
	/**
	 * Register REST API routes
	 */
	public static function register_rest_routes() {
		register_rest_route( 'seogen/v1', '/license-check', array(
			'methods' => 'POST',
			'callback' => array( __CLASS__, 'handle_license_webhook' ),
			'permission_callback' => array( __CLASS__, 'verify_webhook_secret' ),
		) );
	}
	
	/**
	 * Verify webhook secret key
	 */
	public static function verify_webhook_secret( $request ) {
		$secret_header = $request->get_header( 'X-SEOgen-Secret' );
		$stored_secret = get_option( 'seogen_webhook_secret' );
		
		if ( empty( $stored_secret ) ) {
			return new WP_Error( 'no_secret', 'Webhook secret not configured', array( 'status' => 500 ) );
		}
		
		if ( ! hash_equals( $stored_secret, $secret_header ) ) {
			return new WP_Error( 'invalid_secret', 'Invalid webhook secret', array( 'status' => 403 ) );
		}
		
		return true;
	}
	
	/**
	 * Handle license status webhook from Railway
	 */
	public static function handle_license_webhook( $request ) {
		$params = $request->get_json_params();
		$license_status = isset( $params['license_status'] ) ? sanitize_text_field( $params['license_status'] ) : '';
		
		if ( empty( $license_status ) ) {
			return new WP_Error( 'missing_status', 'License status is required', array( 'status' => 400 ) );
		}
		
		// Store license status
		update_option( 'seogen_license_status', $license_status );
		update_option( 'seogen_license_last_check', current_time( 'mysql' ) );
		
		$result = array(
			'success' => true,
			'status' => $license_status,
			'site_url' => get_site_url(),
		);
		
		if ( 'expired' === $license_status || 'cancelled' === $license_status ) {
			// Unpublish all generated pages
			$unpublished_count = self::unpublish_generated_pages();
			$result['pages_unpublished'] = $unpublished_count;
			
			// Set transient for admin notice
			set_transient( 'seogen_license_expired_notice', $unpublished_count, 300 );
			
		} elseif ( 'active' === $license_status ) {
			// Clear any expired notices and set reactivation notice
			delete_transient( 'seogen_license_expired_notice' );
			
			$unpublished_count = get_option( 'seogen_unpublished_count', 0 );
			if ( $unpublished_count > 0 ) {
				set_transient( 'seogen_license_renewed_notice', $unpublished_count, 300 );
			}
		}
		
		return rest_ensure_response( $result );
	}
	
	/**
	 * Unpublish all generated pages
	 * 
	 * @return int Number of pages unpublished
	 */
	private static function unpublish_generated_pages() {
		global $wpdb;
		
		// Query all posts with the _hyper_local_managed meta key
		$generated_post_ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_hyper_local_managed' AND meta_value = '1'"
		);
		
		if ( empty( $generated_post_ids ) ) {
			return 0;
		}
		
		$unpublished_count = 0;
		
		// Change all published posts to draft
		foreach ( $generated_post_ids as $post_id ) {
			$post = get_post( $post_id );
			
			if ( $post && $post->post_status === 'publish' ) {
				wp_update_post( array(
					'ID' => $post_id,
					'post_status' => 'draft',
				) );
				$unpublished_count++;
			}
		}
		
		// Store count and timestamp
		if ( $unpublished_count > 0 ) {
			update_option( 'seogen_unpublished_count', $unpublished_count );
			update_option( 'seogen_unpublished_at', current_time( 'mysql' ) );
		}
		
		return $unpublished_count;
	}
	
	/**
	 * Handle API key update - register site with Railway
	 */
	public static function handle_api_key_update( $old_value, $new_value ) {
		// Check if API key changed
		$old_api_key = isset( $old_value['api_key'] ) ? $old_value['api_key'] : '';
		$new_api_key = isset( $new_value['api_key'] ) ? $new_value['api_key'] : '';
		
		if ( $old_api_key === $new_api_key ) {
			return; // No change
		}
		
		if ( empty( $new_api_key ) ) {
			return; // API key removed
		}
		
		// Generate or retrieve webhook secret
		$webhook_secret = get_option( 'seogen_webhook_secret' );
		if ( empty( $webhook_secret ) ) {
			$webhook_secret = wp_generate_password( 32, false );
			update_option( 'seogen_webhook_secret', $webhook_secret );
		}
		
		// Register site with Railway backend
		self::register_site_with_backend( $new_api_key, $webhook_secret );
	}
	
	/**
	 * Register site with Railway backend
	 */
	private static function register_site_with_backend( $api_key, $webhook_secret ) {
		$site_url = get_site_url();
		
		$response = wp_remote_post( self::BACKEND_URL . '/api/sites/register', array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( array(
				'site_url' => $site_url,
				'api_key' => $api_key,
				'secret_key' => $webhook_secret,
				'plugin_version' => SEOGEN_VERSION,
				'wordpress_version' => get_bloginfo( 'version' ),
			) ),
		) );
		
		if ( is_wp_error( $response ) ) {
			// Log error but don't block
			error_log( '[SEOgen] Failed to register site with backend: ' . $response->get_error_message() );
			return false;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( isset( $data['success'] ) && $data['success'] ) {
			// Store license status if provided
			if ( isset( $data['license_status'] ) ) {
				update_option( 'seogen_license_status', $data['license_status'] );
			}
			if ( isset( $data['expires_at'] ) ) {
				update_option( 'seogen_license_expires_at', $data['expires_at'] );
			}
			
			update_option( 'seogen_site_registered', true );
			update_option( 'seogen_site_registered_at', current_time( 'mysql' ) );
			
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get current license status
	 */
	public static function get_license_status() {
		return get_option( 'seogen_license_status', 'unknown' );
	}
	
	/**
	 * Get license expiration date
	 */
	public static function get_license_expires_at() {
		return get_option( 'seogen_license_expires_at', '' );
	}
	
	/**
	 * Check if site is registered with backend
	 */
	public static function is_site_registered() {
		return (bool) get_option( 'seogen_site_registered', false );
	}
	
	/**
	 * Get webhook secret for display in admin
	 */
	public static function get_webhook_secret() {
		return get_option( 'seogen_webhook_secret', '' );
	}
	
	/**
	 * Manual test function for license expiration (for testing without Railway)
	 */
	public static function test_license_expiration() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		
		$unpublished_count = self::unpublish_generated_pages();
		update_option( 'seogen_license_status', 'expired' );
		set_transient( 'seogen_license_expired_notice', $unpublished_count, 300 );
		
		return $unpublished_count;
	}
}
