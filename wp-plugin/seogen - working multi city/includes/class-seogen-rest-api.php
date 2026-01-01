<?php
/**
 * SEOgen REST API Handler
 * Handles secure backend-to-WordPress callbacks for auto-import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_REST_API {
	
	const NAMESPACE = 'seogen/v1';
	const SIGNATURE_VERSION = '1';
	const MAX_TIMESTAMP_AGE = 300; // 5 minutes
	
	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route( self::NAMESPACE, '/import-page', array(
			'methods' => 'POST',
			'callback' => array( $this, 'import_page' ),
			'permission_callback' => array( $this, 'verify_hmac_signature' ),
		) );
		
		register_rest_route( self::NAMESPACE, '/ping', array(
			'methods' => 'POST',
			'callback' => array( $this, 'ping' ),
			'permission_callback' => array( $this, 'verify_hmac_signature' )
		) );
		
		// Debug endpoint - remove after testing
		register_rest_route( self::NAMESPACE, '/debug-license', array(
			'methods' => 'GET',
			'callback' => array( $this, 'debug_license' ),
			'permission_callback' => '__return_true'
		) );
	}
	
	/**
	 * Validate HMAC signature from backend
	 * 
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function verify_hmac_signature( $request ) {
		$timestamp = $request->get_header( 'X-Seogen-Timestamp' );
		$body_hash = $request->get_header( 'X-Seogen-Body-SHA256' );
		$signature = $request->get_header( 'X-Seogen-Signature' );
		$version = $request->get_header( 'X-Seogen-Signature-Version' );
		
		if ( empty( $timestamp ) || empty( $body_hash ) || empty( $signature ) ) {
			return new WP_Error(
				'missing_signature',
				'Missing required signature headers',
				array( 'status' => 401 )
			);
		}
		
		if ( $version !== self::SIGNATURE_VERSION ) {
			return new WP_Error(
				'invalid_signature_version',
				'Unsupported signature version',
				array( 'status' => 401 )
			);
		}
		
		// Check timestamp age (prevent replay attacks)
		$current_time = time();
		$timestamp_int = (int) $timestamp;
		if ( abs( $current_time - $timestamp_int ) > self::MAX_TIMESTAMP_AGE ) {
			return new WP_Error(
				'timestamp_expired',
				'Request timestamp too old or too far in future',
				array( 'status' => 401 )
			);
		}
		
		// Get callback secret
		$callback_secret = get_option( 'seogen_callback_secret', '' );
		if ( empty( $callback_secret ) ) {
			return new WP_Error(
				'no_callback_secret',
				'Callback secret not configured. Please save settings.',
				array( 'status' => 500 )
			);
		}
		
		// Verify body hash
		$actual_body_hash = hash( 'sha256', $request->get_body() );
		if ( ! hash_equals( $body_hash, $actual_body_hash ) ) {
			return new WP_Error(
				'body_hash_mismatch',
				'Request body hash does not match',
				array( 'status' => 401 )
			);
		}
		
		// Compute expected signature
		$message = $timestamp . '.' . $body_hash;
		$expected_signature = hash_hmac( 'sha256', $message, $callback_secret );
		
		// Verify signature using timing-safe comparison
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return new WP_Error(
				'signature_invalid',
				'HMAC signature verification failed',
				array( 'status' => 401 )
			);
		}
		
		return true;
	}
	
	/**
	 * Import page endpoint
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function import_page( $request ) {
		$params = $request->get_json_params();
		
		$license_key = isset( $params['license_key'] ) ? sanitize_text_field( $params['license_key'] ) : '';
		$job_id = isset( $params['job_id'] ) ? sanitize_text_field( $params['job_id'] ) : '';
		$item_index = isset( $params['item_index'] ) ? (int) $params['item_index'] : 0;
		$result_json = isset( $params['result_json'] ) ? $params['result_json'] : array();
		$item_metadata = isset( $params['item_metadata'] ) ? $params['item_metadata'] : array();
		
		// Verify license key matches this site
		$settings = get_option( 'seogen_settings', array() );
		$site_license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		
		// Normalize both keys for comparison (trim whitespace, case-insensitive)
		$normalized_request_key = trim( strtolower( $license_key ) );
		$normalized_site_key = trim( strtolower( $site_license_key ) );
		
		if ( $normalized_request_key !== $normalized_site_key ) {
			error_log( sprintf(
				'[SEOgen REST API] License mismatch - Request: "%s" (len=%d), Site: "%s" (len=%d)',
				$license_key,
				strlen( $license_key ),
				$site_license_key,
				strlen( $site_license_key )
			) );
			
			return new WP_Error(
				'license_mismatch',
				'License key does not match this site',
				array( 'status' => 403 )
			);
		}
		
		// Extract canonical key for idempotency
		$canonical_key = isset( $item_metadata['canonical_key'] ) ? sanitize_text_field( $item_metadata['canonical_key'] ) : '';
		
		if ( empty( $canonical_key ) ) {
			return new WP_Error(
				'missing_canonical_key',
				'canonical_key is required for idempotent imports',
				array( 'status' => 400 )
			);
		}
		
		// Check for concurrent import (lock)
		$lock_key = 'seogen_import_lock_' . md5( $canonical_key );
		if ( get_transient( $lock_key ) ) {
			return new WP_Error(
				'import_in_progress',
				'Import already in progress for this item',
				array( 'status' => 409 )
			);
		}
		
		// Set lock
		set_transient( $lock_key, 1, 60 );
		
		try {
			// Load admin class with import coordinator
			if ( ! class_exists( 'SEOgen_Admin' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'class-seogen-admin.php';
			}
			
			$importer = new SEOgen_Admin();
			
			// Add canonical_key to item_metadata
			$item_metadata['canonical_key'] = $canonical_key;
			
			// Use centralized import with lock and idempotency
			$result = $importer->import_item_with_lock( $result_json, $item_metadata, $job_id, $item_index );
			
			delete_transient( $lock_key );
			
			if ( $result['success'] ) {
				return new WP_REST_Response( array(
					'success' => true,
					'post_id' => $result['post_id'],
					'already_imported' => $result['already_existed']
				), 200 );
			} else {
				return new WP_Error(
					'import_failed',
					$result['error'],
					array( 'status' => 500 )
				);
			}
			
		} catch ( Exception $e ) {
			delete_transient( $lock_key );
			return new WP_Error(
				'import_exception',
				$e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}
	
	
	/**
	 * Ping endpoint for connection testing
	 * 
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function ping( $request ) {
		$params = $request->get_json_params();
		$license_key = isset( $params['license_key'] ) ? sanitize_text_field( $params['license_key'] ) : '';
		
		// Verify license key
		$settings = get_option( 'seogen_settings', array() );
		$site_license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		
		// Normalize for comparison (case-insensitive)
		$normalized_request_key = trim( strtolower( $license_key ) );
		$normalized_site_key = trim( strtolower( $site_license_key ) );
		
		$license_valid = ( $normalized_request_key === $normalized_site_key && ! empty( $license_key ) );
		
		return new WP_REST_Response( array(
			'success' => true,
			'site_url' => get_site_url(),
			'rest_base_url' => rest_url( self::NAMESPACE . '/' ),
			'license_valid' => $license_valid,
			'timestamp' => time()
		), 200 );
	}
	
	/**
	 * Debug endpoint to check license key configuration
	 * TEMPORARY - Remove after debugging
	 */
	public function debug_license( $request ) {
		$settings = get_option( 'seogen_settings', array() );
		$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
		
		return new WP_REST_Response( array(
			'stored_license_key' => $license_key,
			'key_length' => strlen( $license_key ),
			'key_is_empty' => empty( $license_key ),
			'settings_option_exists' => ! empty( $settings ),
			'all_settings_keys' => array_keys( $settings )
		), 200 );
	}
	
	/**
	 * Generate or regenerate callback secret
	 * 
	 * @return string The generated secret
	 */
	public static function generate_callback_secret() {
		$secret = wp_generate_password( 32, false );
		update_option( 'seogen_callback_secret', $secret );
		return $secret;
	}
	
	/**
	 * Get callback secret (generate if not exists)
	 * 
	 * @return string
	 */
	public static function get_callback_secret() {
		$secret = get_option( 'seogen_callback_secret', '' );
		if ( empty( $secret ) ) {
			$secret = self::generate_callback_secret();
		}
		return $secret;
	}
}
