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
}
