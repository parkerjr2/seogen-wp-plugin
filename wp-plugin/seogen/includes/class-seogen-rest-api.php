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
	const ASYNC_IMPORT_HOOK = 'seogen_async_import_item';

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

	}

	/**
	 * Register the Action Scheduler callback for async imports.
	 * Must be called on every page load (not just rest_api_init)
	 * so Action Scheduler can fire the hook.
	 */
	public function register_async_hooks() {
		add_action( self::ASYNC_IMPORT_HOOK, array( $this, 'process_async_import' ), 10, 1 );
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
	 * Strategy: Try synchronous import first (fast with postmeta index).
	 * If sync fails or times out, store payload and queue for async retry.
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

		// Add canonical_key to item_metadata
		$item_metadata['canonical_key'] = $canonical_key;

		// Try synchronous import first (fast path)
		if ( ! class_exists( 'SEOgen_Admin' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-seogen-admin.php';
		}

		try {
			$importer = new SEOgen_Admin();
			$result = $importer->import_item_with_lock( $result_json, $item_metadata, $job_id, $item_index );

			if ( $result['success'] ) {
				error_log( sprintf(
					'[SEOgen REST API] Sync import success: canonical_key=%s post_id=%d already_existed=%s',
					$canonical_key,
					$result['post_id'],
					$result['already_existed'] ? 'yes' : 'no'
				) );

				return new WP_REST_Response( array(
					'success'          => true,
					'post_id'          => $result['post_id'],
					'already_imported' => $result['already_existed'],
					'canonical_key'    => $canonical_key,
				), 200 );
			}

			// Sync import returned failure — log and fall through to async
			error_log( sprintf(
				'[SEOgen REST API] Sync import failed, queuing async: canonical_key=%s error=%s',
				$canonical_key,
				$result['error']
			) );

		} catch ( Exception $e ) {
			// Sync import threw exception — log and fall through to async
			error_log( sprintf(
				'[SEOgen REST API] Sync import exception, queuing async: canonical_key=%s error=%s',
				$canonical_key,
				$e->getMessage()
			) );
		}

		// Sync failed — store payload and queue for async retry
		$payload_key = 'seogen_import_payload_' . md5( $canonical_key );
		$payload = array(
			'result_json'   => $result_json,
			'item_metadata' => $item_metadata,
			'job_id'        => $job_id,
			'item_index'    => $item_index,
			'queued_at'     => time(),
		);

		update_option( $payload_key, $payload, false );

		// Schedule async import via Action Scheduler
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				self::ASYNC_IMPORT_HOOK,
				array( 'canonical_key' => $canonical_key ),
				'seogen-import'
			);
		} else {
			wp_schedule_single_event( time(), self::ASYNC_IMPORT_HOOK, array( $canonical_key ) );
		}

		return new WP_REST_Response( array(
			'success'       => true,
			'queued'        => true,
			'canonical_key' => $canonical_key,
		), 202 );
	}

	/**
	 * Process a queued async import (called by Action Scheduler)
	 *
	 * @param string $canonical_key Canonical key identifying the payload
	 */
	public function process_async_import( $canonical_key ) {
		$payload_key = 'seogen_import_payload_' . md5( $canonical_key );
		$payload = get_option( $payload_key );

		if ( empty( $payload ) || ! is_array( $payload ) ) {
			error_log( '[SEOgen Async Import] No payload found for canonical_key=' . $canonical_key );
			return;
		}

		$result_json   = isset( $payload['result_json'] ) ? $payload['result_json'] : array();
		$item_metadata = isset( $payload['item_metadata'] ) ? $payload['item_metadata'] : array();
		$job_id        = isset( $payload['job_id'] ) ? $payload['job_id'] : '';
		$item_index    = isset( $payload['item_index'] ) ? (int) $payload['item_index'] : 0;

		// Load admin class with import coordinator
		if ( ! class_exists( 'SEOgen_Admin' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-seogen-admin.php';
		}

		$success = false;

		try {
			$importer = new SEOgen_Admin();
			$result = $importer->import_item_with_lock( $result_json, $item_metadata, $job_id, $item_index );

			if ( $result['success'] ) {
				$success = true;
				error_log( sprintf(
					'[SEOgen Async Import] Success: canonical_key=%s post_id=%d already_existed=%s',
					$canonical_key,
					$result['post_id'],
					$result['already_existed'] ? 'yes' : 'no'
				) );
			} else {
				error_log( sprintf(
					'[SEOgen Async Import] Failed: canonical_key=%s error=%s',
					$canonical_key,
					$result['error']
				) );
			}
		} catch ( Exception $e ) {
			error_log( '[SEOgen Async Import] Exception: canonical_key=' . $canonical_key . ' error=' . $e->getMessage() );
		}

		// Only clean up payload on success — failed imports keep payload for retry
		if ( $success ) {
			delete_option( $payload_key );
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
