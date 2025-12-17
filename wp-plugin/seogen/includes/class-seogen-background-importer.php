<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Background importer for completed bulk job items.
 * Runs via WordPress cron to import completed items even when user is not on the page.
 */
class SEOgen_Background_Importer {
	const API_BASE_URL = 'https://seogen-production.up.railway.app';
	const BULK_JOB_OPTION_PREFIX = 'hyper_local_job_';
	const BULK_JOBS_INDEX_OPTION = 'hyper_local_jobs_index';

	private $admin;

	public function __construct( $admin ) {
		$this->admin = $admin;
	}

	/**
	 * Process all running bulk jobs and import completed items.
	 * Called by WordPress cron every minute.
	 */
	public function process_all_running_jobs() {
		$jobs_index = get_option( self::BULK_JOBS_INDEX_OPTION, array() );
		if ( ! is_array( $jobs_index ) || empty( $jobs_index ) ) {
			return;
		}

		foreach ( $jobs_index as $job_id ) {
			$job = get_option( self::BULK_JOB_OPTION_PREFIX . $job_id, null );
			if ( ! is_array( $job ) ) {
				continue;
			}

			// Only process running jobs
			$status = isset( $job['status'] ) ? (string) $job['status'] : '';
			if ( 'running' !== $status ) {
				continue;
			}

			// Check if this is an API mode job
			$mode = isset( $job['mode'] ) ? (string) $job['mode'] : 'local';
			if ( 'api' !== $mode ) {
				continue;
			}

			// Process this job
			$this->process_job( $job_id, $job );
		}
	}

	/**
	 * Process a single bulk job - fetch results and import completed items.
	 */
	private function process_job( $job_id, $job ) {
		$settings = get_option( 'seogen_settings', array() );
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		
		if ( '' === $license_key ) {
			return;
		}

		// Fetch results from API
		$results = $this->fetch_bulk_results( $job_id, $license_key );
		if ( ! is_array( $results ) || ! isset( $results['items'] ) || ! is_array( $results['items'] ) ) {
			return;
		}

		$items = $results['items'];
		if ( empty( $items ) ) {
			return;
		}

		$update_existing = isset( $job['update_existing'] ) && '1' === (string) $job['update_existing'];
		$auto_publish = isset( $job['auto_publish'] ) && '1' === (string) $job['auto_publish'];
		$acked_ids = array();
		$imported_count = 0;

		foreach ( $items as $item ) {
			$item_id = isset( $item['item_id'] ) ? (string) $item['item_id'] : '';
			$idx = isset( $item['idx'] ) ? (int) $item['idx'] : -1;
			$canonical_key = isset( $item['canonical_key'] ) ? (string) $item['canonical_key'] : '';
			$item_status = isset( $item['status'] ) ? (string) $item['status'] : '';
			$result_json = isset( $item['result_json'] ) && is_array( $item['result_json'] ) ? $item['result_json'] : null;
			$error = isset( $item['error'] ) ? (string) $item['error'] : '';

			if ( '' === $item_id || $idx < 0 ) {
				continue;
			}

			// Skip items already successfully imported
			if ( isset( $job['rows'][ $idx ] ) && 'success' === $job['rows'][ $idx ]['status'] && 'completed' === $item_status ) {
				$acked_ids[] = $item_id;
				continue;
			}

			// Handle failed items
			if ( 'failed' === $item_status ) {
				if ( isset( $job['rows'][ $idx ] ) ) {
					$job['rows'][ $idx ]['status'] = 'failed';
					$job['rows'][ $idx ]['message'] = '' !== $error ? $error : __( 'Generation failed.', 'seogen' );
					$job['rows'][ $idx ]['post_id'] = 0;
				}
				$item_attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;
				if ( $item_attempts >= 2 ) {
					$acked_ids[] = $item_id;
				}
				continue;
			}

			// Skip if no result_json
			if ( ! is_array( $result_json ) ) {
				continue;
			}

			// Check for existing post
			$existing_id = ( '' !== $canonical_key ) ? $this->find_existing_post_id_by_key( $canonical_key ) : 0;
			if ( $existing_id > 0 && ! $update_existing ) {
				if ( isset( $job['rows'][ $idx ] ) ) {
					$job['rows'][ $idx ]['status'] = 'skipped';
					$job['rows'][ $idx ]['message'] = __( 'Existing page found for key; skipping import.', 'seogen' );
					$job['rows'][ $idx ]['post_id'] = $existing_id;
				}
				$acked_ids[] = $item_id;
				continue;
			}

			// Import the item
			$post_id = $this->import_item( $result_json, $existing_id, $update_existing, $auto_publish, $canonical_key );
			
			if ( $post_id > 0 ) {
				if ( isset( $job['rows'][ $idx ] ) ) {
					$job['rows'][ $idx ]['status'] = 'success';
					$job['rows'][ $idx ]['message'] = __( 'Page created successfully.', 'seogen' );
					$job['rows'][ $idx ]['post_id'] = $post_id;
				}
				$acked_ids[] = $item_id;
				$imported_count++;
			}
		}

		// Acknowledge imported items
		if ( ! empty( $acked_ids ) ) {
			$this->ack_bulk_items( $job_id, $acked_ids, $license_key );
		}

		// Update job counters and status
		$this->update_job_status( $job_id, $job );

		// Save updated job
		update_option( self::BULK_JOB_OPTION_PREFIX . $job_id, $job, false );
	}

	/**
	 * Import a single item as a WordPress post.
	 */
	private function import_item( $result_json, $existing_id, $update_existing, $auto_publish, $canonical_key ) {
		$title = isset( $result_json['title'] ) ? (string) $result_json['title'] : '';
		$slug = isset( $result_json['slug'] ) ? (string) $result_json['slug'] : '';
		$meta_description = isset( $result_json['meta_description'] ) ? (string) $result_json['meta_description'] : '';
		$blocks = ( isset( $result_json['blocks'] ) && is_array( $result_json['blocks'] ) ) ? $result_json['blocks'] : array();
		
		// Build Gutenberg content
		$gutenberg_markup = $this->admin->build_gutenberg_content_from_blocks( $blocks );

		// Add header/footer templates
		$settings = get_option( 'seogen_settings', array() );
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->admin->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
			}
		}

		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->admin->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
			}
		}

		$post_status = $auto_publish ? 'publish' : 'draft';

		$postarr = array(
			'post_type'    => 'service_page',
			'post_status'  => $post_status,
			'post_title'   => $title,
			'post_name'    => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
		);

		$post_id = 0;
		if ( $existing_id > 0 && $update_existing ) {
			$postarr['ID'] = $existing_id;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', 0 );
		if ( $unique_slug ) {
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $unique_slug,
				)
			);
		}

		update_post_meta( $post_id, '_hyper_local_key', $canonical_key );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		
		if ( $auto_publish ) {
			$this->admin->apply_page_builder_settings( $post_id );
		}

		return $post_id;
	}

	/**
	 * Find existing post by canonical key.
	 */
	private function find_existing_post_id_by_key( $canonical_key ) {
		$canonical_key = trim( (string) $canonical_key );
		if ( '' === $canonical_key ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'service_page',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => '_hyper_local_key',
						'value' => $canonical_key,
					),
				),
			)
		);

		$posts = $query->posts;
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/**
	 * Fetch bulk job results from API.
	 */
	private function fetch_bulk_results( $job_id, $license_key ) {
		$url = self::API_BASE_URL . '/bulk-job/' . urlencode( $job_id ) . '/results';
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'X-License-Key' => $license_key,
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Acknowledge imported items to API.
	 */
	private function ack_bulk_items( $job_id, $item_ids, $license_key ) {
		$url = self::API_BASE_URL . '/bulk-job/' . urlencode( $job_id ) . '/ack';
		wp_remote_post(
			$url,
			array(
				'headers' => array(
					'X-License-Key' => $license_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'item_ids' => $item_ids ) ),
				'timeout' => 30,
			)
		);
	}

	/**
	 * Update job status and counters.
	 */
	private function update_job_status( $job_id, &$job ) {
		if ( ! isset( $job['rows'] ) || ! is_array( $job['rows'] ) ) {
			return;
		}

		$has_pending = false;
		foreach ( $job['rows'] as $row ) {
			$row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
			if ( 'pending' === $row_status || 'running' === $row_status ) {
				$has_pending = true;
				break;
			}
		}

		if ( isset( $job['status'] ) && 'canceled' === (string) $job['status'] ) {
			$job['status'] = 'canceled';
		} elseif ( $has_pending ) {
			$job['status'] = 'running';
		} else {
			$job['status'] = 'complete';
		}
	}
}
