<?php
/**
 * SEOgen Import Coordinator
 * Handles idempotency, locks, and centralized import dispatch
 * Phase 4: Foundation for zero-config auto-import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Import_Coordinator {
	
	/**
	 * Import an item with lock and idempotency check
	 * 
	 * @param array $result_json Result JSON from backend
	 * @param array $item_metadata Item metadata (canonical_key, service, city, state, hub_key, etc.)
	 * @param string $job_id Job ID
	 * @param int $item_index Item index in job
	 * @return array ['success' => bool, 'post_id' => int, 'already_existed' => bool, 'error' => string]
	 */
	public function import_item_with_lock( $result_json, $item_metadata, $job_id, $item_index ) {
		// Extract canonical key (exact from backend, no normalization)
		$canonical_key = isset( $item_metadata['canonical_key'] ) ? trim( $item_metadata['canonical_key'] ) : '';
		
		if ( empty( $canonical_key ) ) {
			return array(
				'success' => false,
				'post_id' => 0,
				'already_existed' => false,
				'error' => 'Missing canonical_key in item metadata'
			);
		}
		
		// Acquire lock (60 second TTL)
		$lock_key = 'seogen_import_lock_' . md5( $canonical_key );
		$lock_acquired = $this->acquire_import_lock( $lock_key );
		
		if ( ! $lock_acquired ) {
			return array(
				'success' => false,
				'post_id' => 0,
				'already_existed' => false,
				'error' => 'Import already in progress for this item (lock held)'
			);
		}
		
		try {
			// Check if already imported (idempotency)
			$existing_post_id = $this->find_post_by_canonical_key( $canonical_key );
			
			if ( $existing_post_id > 0 ) {
				// Already exists, release lock and return
				$this->release_import_lock( $lock_key );
				
				return array(
					'success' => true,
					'post_id' => $existing_post_id,
					'already_existed' => true,
					'error' => ''
				);
			}
			
			// Dispatch to correct import function based on page_mode
			$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : 'service_city';
			$result = $this->dispatch_import( $page_mode, $result_json, $item_metadata, $job_id, $item_index, $canonical_key );
			
			// Release lock
			$this->release_import_lock( $lock_key );
			
			return $result;
			
		} catch ( Exception $e ) {
			// Release lock on exception
			$this->release_import_lock( $lock_key );
			
			return array(
				'success' => false,
				'post_id' => 0,
				'already_existed' => false,
				'error' => $e->getMessage()
			);
		}
	}
	
	/**
	 * Acquire import lock
	 * 
	 * @param string $lock_key Lock key
	 * @return bool True if lock acquired, false if already held
	 */
	private function acquire_import_lock( $lock_key ) {
		// Check if lock already exists
		if ( get_transient( $lock_key ) ) {
			return false;
		}
		
		// Set lock with 60 second TTL
		set_transient( $lock_key, time(), 60 );
		return true;
	}
	
	/**
	 * Release import lock
	 * 
	 * @param string $lock_key Lock key
	 */
	private function release_import_lock( $lock_key ) {
		delete_transient( $lock_key );
	}
	
	/**
	 * Find existing post by canonical key
	 * 
	 * @param string $canonical_key Exact canonical key from backend
	 * @return int Post ID if found, 0 if not found
	 */
	private function find_post_by_canonical_key( $canonical_key ) {
		$posts = get_posts( array(
			'post_type' => 'service_page',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_seogen_canonical_key',
					'value' => $canonical_key,
					'compare' => '='
				)
			),
			'fields' => 'ids'
		) );
		
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
	
	/**
	 * Dispatch import to correct function based on page_mode
	 * 
	 * @param string $page_mode Page mode (service_city, city_hub, service_hub)
	 * @param array $result_json Result JSON from backend
	 * @param array $item_metadata Item metadata
	 * @param string $job_id Job ID
	 * @param int $item_index Item index
	 * @param string $canonical_key Canonical key
	 * @return array Import result
	 */
	private function dispatch_import( $page_mode, $result_json, $item_metadata, $job_id, $item_index, $canonical_key ) {
		// Get business config
		$config = get_option( 'hyper_local_business_config', array() );
		
		// Build item data for import functions
		$item = array(
			'service' => isset( $item_metadata['service'] ) ? $item_metadata['service'] : '',
			'city' => isset( $item_metadata['city'] ) ? $item_metadata['city'] : '',
			'state' => isset( $item_metadata['state'] ) ? $item_metadata['state'] : '',
			'hub_key' => isset( $item_metadata['hub_key'] ) ? $item_metadata['hub_key'] : '',
			'hub_label' => isset( $item_metadata['hub_label'] ) ? $item_metadata['hub_label'] : '',
		);
		
		// Dispatch based on page_mode
		if ( 'city_hub' === $page_mode ) {
			// Calculate city_slug if not provided
			$city_slug = isset( $item_metadata['city_slug'] ) ? $item_metadata['city_slug'] : '';
			if ( empty( $city_slug ) && ! empty( $item['city'] ) && ! empty( $item['state'] ) ) {
				$city_slug = sanitize_title( $item['city'] . '-' . $item['state'] );
			}
			$item['city_slug'] = $city_slug;
			
			// Import city hub using existing function
			$result = $this->import_city_hub_from_result( $result_json, $config, $item, 'publish' );
			
		} elseif ( 'service_hub' === $page_mode ) {
			// Import service hub (if we have this function)
			if ( method_exists( $this, 'import_service_hub_from_result' ) ) {
				$result = $this->import_service_hub_from_result( $result_json, $config, $item );
			} else {
				return array(
					'success' => false,
					'post_id' => 0,
					'already_existed' => false,
					'error' => 'Service hub import not implemented'
				);
			}
			
		} else {
			// Default: service_city page
			// Import using existing function from SEOgen_Admin_Import trait
			$result = $this->import_service_city_from_result( $result_json, $config, $item );
		}
		
		// Store canonical key and metadata on successful import
		if ( isset( $result['success'] ) && $result['success'] && isset( $result['post_id'] ) && $result['post_id'] > 0 ) {
			update_post_meta( $result['post_id'], '_seogen_canonical_key', $canonical_key );
			update_post_meta( $result['post_id'], '_seogen_job_id', $job_id );
			update_post_meta( $result['post_id'], '_seogen_item_index', $item_index );
			update_post_meta( $result['post_id'], '_seogen_imported_via', 'auto_import' );
			update_post_meta( $result['post_id'], '_seogen_imported_at', current_time( 'mysql' ) );
			
			return array(
				'success' => true,
				'post_id' => $result['post_id'],
				'already_existed' => false,
				'error' => ''
			);
		}
		
		// Import failed
		return array(
			'success' => false,
			'post_id' => 0,
			'already_existed' => false,
			'error' => isset( $result['error'] ) ? $result['error'] : 'Import failed'
		);
	}
	
	/**
	 * Run import batch - pull from backend and import items
	 * Time-bounded: 15 seconds max, 10 items max
	 * 
	 * @param string $job_id Job ID
	 * @return array ['imported' => int, 'failed' => int, 'remaining' => int, 'errors' => array]
	 */
	public function run_import_batch( $job_id ) {
		$start_time = time();
		$max_duration = 15; // 15 seconds max
		$max_items = 10; // 10 items max per batch
		
		$imported_count = 0;
		$failed_count = 0;
		$errors = array();
		
		// Load job
		$job = $this->load_bulk_job( $job_id );
		if ( ! $job ) {
			return array(
				'imported' => 0,
				'failed' => 0,
				'remaining' => 0,
				'errors' => array( 'Job not found' )
			);
		}
		
		// Get settings
		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
		$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
		
		if ( empty( $api_url ) || empty( $license_key ) ) {
			return array(
				'imported' => 0,
				'failed' => 0,
				'remaining' => 0,
				'errors' => array( 'API URL or license key not configured' )
			);
		}
		
		$api_job_id = isset( $job['api_job_id'] ) ? $job['api_job_id'] : '';
		if ( empty( $api_job_id ) ) {
			return array(
				'imported' => 0,
				'failed' => 0,
				'remaining' => 0,
				'errors' => array( 'No API job ID' )
			);
		}
		
		// Fetch non-imported items from backend
		$items = $this->fetch_non_imported_items( $api_url, $license_key, $api_job_id, $max_items );
		
		if ( empty( $items ) ) {
			return array(
				'imported' => 0,
				'failed' => 0,
				'remaining' => 0,
				'errors' => array()
			);
		}
		
		$imported_item_ids = array();
		
		// Process each item
		foreach ( $items as $item ) {
			// Check time budget
			if ( time() - $start_time >= $max_duration ) {
				break;
			}
			
			$item_id = isset( $item['item_id'] ) ? $item['item_id'] : '';
			$canonical_key = isset( $item['canonical_key'] ) ? $item['canonical_key'] : '';
			$result_json = isset( $item['result_json'] ) ? $item['result_json'] : null;
			
			if ( empty( $canonical_key ) || empty( $result_json ) ) {
				$failed_count++;
				$errors[] = 'Missing canonical_key or result_json for item ' . $item_id;
				continue;
			}
			
			// Build item metadata
			$item_metadata = array(
				'canonical_key' => $canonical_key,
				'service' => isset( $item['service'] ) ? $item['service'] : '',
				'city' => isset( $item['city'] ) ? $item['city'] : '',
				'state' => isset( $item['state'] ) ? $item['state'] : '',
				'hub_key' => isset( $item['hub_key'] ) ? $item['hub_key'] : '',
				'hub_label' => isset( $item['hub_label'] ) ? $item['hub_label'] : '',
				'city_slug' => isset( $item['city_slug'] ) ? $item['city_slug'] : '',
			);
			
			// Import with lock and idempotency
			$result = $this->import_item_with_lock( $result_json, $item_metadata, $job_id, isset( $item['idx'] ) ? $item['idx'] : 0 );
			
			if ( $result['success'] ) {
				$imported_count++;
				$imported_item_ids[] = $item_id;
				
				// Update job row status
				$this->update_job_row_import_status( $job_id, $canonical_key, 'imported', $result['post_id'] );
			} else {
				$failed_count++;
				$errors[] = 'Import failed for ' . $canonical_key . ': ' . $result['error'];
				
				// Update job row status
				$this->update_job_row_import_status( $job_id, $canonical_key, 'failed', 0, $result['error'] );
			}
		}
		
		// Mark items as imported in backend
		if ( ! empty( $imported_item_ids ) ) {
			$this->mark_items_imported_backend( $api_url, $license_key, $api_job_id, $imported_item_ids );
		}
		
		// Update heartbeat
		$job['last_runner_heartbeat_at'] = time();
		$this->save_bulk_job( $job_id, $job );
		
		// Count remaining items
		$remaining = $this->count_pending_imports( $job );
		
		return array(
			'imported' => $imported_count,
			'failed' => $failed_count,
			'remaining' => $remaining,
			'errors' => $errors
		);
	}
	
	/**
	 * Fetch non-imported items from backend
	 * 
	 * @param string $api_url API URL
	 * @param string $license_key License key
	 * @param string $api_job_id API job ID
	 * @param int $limit Limit
	 * @return array Items
	 */
	private function fetch_non_imported_items( $api_url, $license_key, $api_job_id, $limit ) {
		$url = trailingslashit( $api_url ) . 'bulk-jobs/' . $api_job_id . '/results';
		$url = add_query_arg(
			array(
				'license_key' => $license_key,
				'imported' => 'false',
				'limit' => $limit,
			),
			$url
		);
		
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return array();
		}
		
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array();
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		return isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	}
	
	/**
	 * Mark items as imported in backend
	 * 
	 * @param string $api_url API URL
	 * @param string $license_key License key
	 * @param string $api_job_id API job ID
	 * @param array $item_ids Item IDs
	 */
	private function mark_items_imported_backend( $api_url, $license_key, $api_job_id, $item_ids ) {
		$url = trailingslashit( $api_url ) . 'bulk-jobs/' . $api_job_id . '/items/mark-imported';
		
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode(
					array(
						'license_key' => $license_key,
						'item_ids' => $item_ids,
					)
				),
			)
		);
		
		// Don't fail if mark-imported fails - items are already imported locally
		if ( is_wp_error( $response ) ) {
			error_log( '[SEOgen] Failed to mark items as imported in backend: ' . $response->get_error_message() );
		}
	}
	
	/**
	 * Update job row import status
	 * 
	 * @param string $job_id Job ID
	 * @param string $canonical_key Canonical key
	 * @param string $status Import status (imported|failed)
	 * @param int $post_id Post ID (if imported)
	 * @param string $error Error message (if failed)
	 */
	private function update_job_row_import_status( $job_id, $canonical_key, $status, $post_id = 0, $error = '' ) {
		$job = $this->load_bulk_job( $job_id );
		if ( ! $job || ! isset( $job['rows'] ) ) {
			return;
		}
		
		foreach ( $job['rows'] as $i => $row ) {
			if ( isset( $row['canonical_key'] ) && $row['canonical_key'] === $canonical_key ) {
				$job['rows'][ $i ]['import_status'] = $status;
				$job['rows'][ $i ]['imported_post_id'] = $post_id;
				$job['rows'][ $i ]['last_attempt_at'] = time();
				
				if ( ! empty( $error ) ) {
					$job['rows'][ $i ]['last_import_error'] = $error;
				}
				
				break;
			}
		}
		
		$this->save_bulk_job( $job_id, $job );
	}
	
	/**
	 * Count pending imports in a job
	 * 
	 * @param array $job Job data
	 * @return int Number of pending imports
	 */
	private function count_pending_imports( $job ) {
		if ( ! isset( $job['rows'] ) || ! is_array( $job['rows'] ) ) {
			return 0;
		}
		
		$count = 0;
		foreach ( $job['rows'] as $row ) {
			$import_status = isset( $row['import_status'] ) ? $row['import_status'] : 'pending';
			if ( 'pending' === $import_status || 'importing' === $import_status ) {
				$count++;
			}
		}
		
		return $count;
	}
	
	/**
	 * Test loopback health - can we call our own AJAX endpoint?
	 * Phase 2: Loopback async import
	 * 
	 * @return array ['supported' => bool, 'error' => string]
	 */
	public function test_loopback_health() {
		$url = admin_url( 'admin-ajax.php?action=seogen_loopback_health_check' );
		
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 5,
				'blocking' => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers' => array(
					'User-Agent' => 'SEOgen-Loopback-Test/1.0',
				),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return array(
				'supported' => false,
				'error' => 'Loopback request failed: ' . $response->get_error_message(),
			);
		}
		
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'supported' => false,
				'error' => 'Loopback returned HTTP ' . $code,
			);
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			return array(
				'supported' => false,
				'error' => 'Loopback response invalid',
			);
		}
		
		return array(
			'supported' => true,
			'error' => '',
		);
	}
	
	/**
	 * Trigger loopback import batch (non-blocking)
	 * Phase 2: Loopback async import
	 * 
	 * @param string $job_id Job ID
	 * @return bool True if triggered, false if throttled or failed
	 */
	public function trigger_loopback_import( $job_id ) {
		// Check throttle - max 1 trigger per 10 seconds per job
		$throttle_key = 'seogen_loopback_throttle_' . $job_id;
		if ( get_transient( $throttle_key ) ) {
			return false;
		}
		
		// Set throttle
		set_transient( $throttle_key, 1, 10 );
		
		// Trigger non-blocking request
		$url = admin_url( 'admin-ajax.php' );
		
		$response = wp_remote_post(
			$url,
			array(
				'body' => array(
					'action' => 'seogen_run_import_batch',
					'job_id' => $job_id,
				),
				'timeout' => 0.01,
				'blocking' => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers' => array(
					'User-Agent' => 'SEOgen-Loopback/1.0',
				),
			)
		);
		
		// Don't check response for non-blocking requests
		return true;
	}
	
	/**
	 * Check if loopback is supported for a job
	 * 
	 * @param string $job_id Job ID
	 * @return bool|null True if supported, false if not supported, null if not tested
	 */
	public function is_loopback_supported( $job_id ) {
		$job = $this->load_bulk_job( $job_id );
		if ( ! $job ) {
			return null;
		}
		
		return isset( $job['loopback_supported'] ) ? $job['loopback_supported'] : null;
	}
	
	/**
	 * Set loopback support status for a job
	 * 
	 * @param string $job_id Job ID
	 * @param bool $supported Whether loopback is supported
	 * @param string $error Error message if not supported
	 */
	public function set_loopback_support( $job_id, $supported, $error = '' ) {
		$job = $this->load_bulk_job( $job_id );
		if ( ! $job ) {
			return;
		}
		
		$job['loopback_supported'] = $supported;
		
		if ( ! $supported && ! empty( $error ) ) {
			$job['loopback_error'] = $error;
		}
		
		// Set auto_import_mode based on loopback support
		if ( $supported ) {
			$job['auto_import_mode'] = 'loopback';
		} elseif ( ! isset( $job['auto_import_mode'] ) || 'loopback' === $job['auto_import_mode'] ) {
			// Fall back to admin_assisted if loopback was the mode or not set
			$job['auto_import_mode'] = 'admin_assisted';
		}
		
		$this->save_bulk_job( $job_id, $job );
	}
}

