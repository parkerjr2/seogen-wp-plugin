<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for importing pre-generated pages from bulk job results
 * Used by wizard to produce identical quality to individual generation
 */
trait SEOgen_Admin_Import {

	/**
	 * Import Service Hub from pre-generated result
	 * 
	 * @param array $result_json - Already generated content from backend
	 * @param array $config - Business config (vertical, etc.)
	 * @param array $item - Item metadata (hub_key, hub_label, etc.)
	 * @param string $post_status - Post status ('draft' or 'publish'), defaults to 'publish'
	 * @return array - ['success' => bool, 'post_id' => int, 'title' => string, 'error' => string]
	 */
	public function import_service_hub_from_result( $result_json, $config, $item, $post_status = 'publish' ) {
		$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
		$hub_label = isset( $item['hub_label'] ) ? $item['hub_label'] : '';
		
		error_log( '[IMPORT] import_service_hub_from_result - hub_key: ' . $hub_key . ', hub_label: ' . $hub_label );
		
		if ( empty( $hub_key ) ) {
			error_log( '[IMPORT] ERROR: Missing hub_key in item data' );
			return array(
				'success' => false,
				'error' => 'Missing hub_key in item data',
			);
		}
		
		// Extract data from result
		$title = isset( $result_json['title'] ) ? $result_json['title'] : '';
		$slug = isset( $result_json['slug'] ) ? $result_json['slug'] : sanitize_title( $hub_key );
		$meta_description = isset( $result_json['meta_description'] ) ? $result_json['meta_description'] : '';
		$blocks = isset( $result_json['blocks'] ) ? $result_json['blocks'] : array();
		$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : 'service_hub';
		
		error_log( '[IMPORT] Service Hub data - title: ' . $title . ', blocks: ' . count( $blocks ) );
		
		if ( empty( $blocks ) ) {
			error_log( '[IMPORT] ERROR: No blocks in result_json' );
			return array(
				'success' => false,
				'error' => 'No blocks in result_json',
			);
		}
		
		// Build Gutenberg content
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );
		error_log( '[IMPORT] Built Gutenberg content, length: ' . strlen( $gutenberg_markup ) );
		
		// Apply Service Hub quality improvements (FAQ dedup, framing, heading variation)
		$gutenberg_markup = $this->apply_service_hub_quality_improvements( $gutenberg_markup, $hub_label );
		
		// Apply header/footer templates
		$settings = $this->get_settings();
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
			}
		}
		
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
			}
		}
		
		// Check for existing Service Hub
		$existing_post_id = $this->find_service_hub_post_id( $hub_key );
		error_log( '[IMPORT] Existing Service Hub post_id: ' . $existing_post_id );
		
		// Create/update post
		$post_data = array(
			'post_type' => 'service_page',
			'post_status' => $post_status,
			'post_title' => $title,
			'post_name' => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
		);
		
		if ( $existing_post_id > 0 ) {
			error_log( '[IMPORT] Updating existing Service Hub post_id: ' . $existing_post_id );
			$post_data['ID'] = $existing_post_id;
			unset( $post_data['post_name'] );
			$post_id = wp_update_post( $post_data, true );
		} else {
			error_log( '[IMPORT] Creating new Service Hub post' );
			$post_id = wp_insert_post( $post_data, true );
		}
		
		if ( is_wp_error( $post_id ) ) {
			error_log( '[IMPORT] ERROR creating/updating Service Hub: ' . $post_id->get_error_message() );
			return array(
				'success' => false,
				'error' => $post_id->get_error_message(),
			);
		}
		
		error_log( '[IMPORT] Service Hub post created/updated successfully, post_id: ' . $post_id );
		
		// Save metadata
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
		update_post_meta( $post_id, '_seogen_page_mode', 'service_hub' );
		update_post_meta( $post_id, '_seogen_vertical', isset( $config['vertical'] ) ? $config['vertical'] : '' );
		update_post_meta( $post_id, '_seogen_hub_key', $hub_key );
		update_post_meta( $post_id, '_seogen_hub_slug', sanitize_title( $hub_key ) );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		update_post_meta( $post_id, '_hl_page_type', 'service_hub' );
		
		// Apply SEO plugin meta with trade name
		$vertical = isset( $config['vertical'] ) ? strtolower( $config['vertical'] ) : 'electrician';
		$trade_name_map = array(
			'roofer' => 'Roofing',
			'roofing' => 'Roofing',
			'electrician' => 'Electrical',
			'electrical' => 'Electrical',
			'plumber' => 'Plumbing',
			'plumbing' => 'Plumbing',
			'hvac' => 'HVAC',
			'hvac technician' => 'HVAC',
			'landscaper' => 'Landscaping',
			'landscaping' => 'Landscaping',
			'handyman' => 'Handyman Services',
			'painter' => 'Painting',
			'painting' => 'Painting',
			'concrete' => 'Concrete',
			'siding' => 'Siding',
			'locksmith' => 'Locksmith Services',
			'cleaning' => 'Cleaning Services',
			'garage-door' => 'Garage Door',
			'garage door' => 'Garage Door',
			'windows' => 'Window Services',
		);
		$trade_name = isset( $trade_name_map[ $vertical ] ) ? $trade_name_map[ $vertical ] : 'Services';
		
		// Focus keyword: "Commercial Electrical" not "Commercial Services"
		$focus_keyword = $hub_label . ' ' . $trade_name;
		
		// Ensure meta description meets Google best practices
		if ( empty( $meta_description ) || strlen( $meta_description ) < 100 ) {
			$meta_description = sprintf(
				'Expert %s %s services. Licensed professionals, quality workmanship, and reliable service. %s',
				strtolower( $hub_label ),
				strtolower( $trade_name ),
				isset( $config['cta_text'] ) ? $config['cta_text'] : 'Contact us today'
			);
		}
		if ( strlen( $meta_description ) > 160 ) {
			$meta_description = substr( $meta_description, 0, 157 ) . '...';
		}
		
		$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );
		
		// Apply page builder settings if header/footer disabled
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}
		
		error_log( '[IMPORT] Service Hub import complete - post_id: ' . $post_id . ', title: ' . $title );
		
		return array(
			'success' => true,
			'post_id' => $post_id,
			'title' => $title,
		);
	}
	
	/**
	 * Import City Hub from pre-generated result
	 * 
	 * @param array $result_json - Already generated content from backend
	 * @param array $config - Business config (vertical, etc.)
	 * @param array $item - Item metadata (hub_key, city_slug, etc.)
	 * @return array - ['success' => bool, 'post_id' => int, 'title' => string, 'error' => string]
	 */
	public function import_city_hub_from_result( $result_json, $config, $item, $post_status = 'draft' ) {
		$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
		$city_slug = isset( $item['city_slug'] ) ? $item['city_slug'] : '';
		
		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			return array(
				'success' => false,
				'error' => 'Missing hub_key or city_slug in item data',
			);
		}
		
		// Extract data from result
		$title = isset( $result_json['title'] ) ? $result_json['title'] : '';
		$slug = isset( $result_json['slug'] ) ? $result_json['slug'] : $city_slug;
		$meta_description = isset( $result_json['meta_description'] ) ? $result_json['meta_description'] : '';
		$blocks = isset( $result_json['blocks'] ) ? $result_json['blocks'] : array();
		$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : 'city_hub';
		
		if ( empty( $blocks ) ) {
			return array(
				'success' => false,
				'error' => 'No blocks in result_json',
			);
		}
		
		// Build city data for quality improvements
		$city = array(
			'name' => isset( $item['city'] ) ? $item['city'] : '',
			'state' => isset( $item['state'] ) ? $item['state'] : '',
			'slug' => $city_slug,
		);
		
		// Find parent Service Hub
		$hub_post_id = $this->find_service_hub_post_id( $hub_key );
		
		// Build Gutenberg content
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );
		
		// Apply City Hub quality improvements (parent link, city repetition cleanup, FAQ dedup, etc.)
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		$gutenberg_markup = $this->apply_city_hub_quality_improvements( $gutenberg_markup, $hub_key, $city, $vertical );
		
		// Apply header/footer templates
		$settings = $this->get_settings();
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				$gutenberg_markup = $header_content . $gutenberg_markup;
			}
		}
		
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				$gutenberg_markup = $gutenberg_markup . $footer_content;
			}
		}
		
		// Check for existing City Hub
		$existing_post_id = $this->find_city_hub_post_id( $hub_key, $city_slug );
		
		// Create/update post with parent relationship
		$post_data = array(
			'post_type' => 'service_page',
			'post_status' => $post_status,
			'post_title' => $title,
			'post_name' => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
			'post_parent' => $hub_post_id,
		);
		
		if ( $existing_post_id > 0 ) {
			$post_data['ID'] = $existing_post_id;
			unset( $post_data['post_name'] );
			$post_id = wp_update_post( $post_data, true );
		} else {
			$post_id = wp_insert_post( $post_data, true );
		}
		
		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'error' => $post_id->get_error_message(),
			);
		}
		
		// Save metadata
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
		update_post_meta( $post_id, '_seogen_page_mode', 'city_hub' );
		update_post_meta( $post_id, '_seogen_vertical', $vertical );
		update_post_meta( $post_id, '_seogen_hub_key', $hub_key );
		update_post_meta( $post_id, '_seogen_hub_slug', sanitize_title( $hub_key ) );
		update_post_meta( $post_id, '_seogen_city', $city['name'] . ', ' . $city['state'] );
		update_post_meta( $post_id, '_seogen_city_slug', $city_slug );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		
		// Apply page builder settings if header/footer disabled
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}
		
		// Apply SEO plugin meta
		$hub_label = isset( $item['hub_label'] ) ? $item['hub_label'] : ucfirst( $hub_key );
		$focus_keyword = $hub_label . ' ' . $city['name'];
		$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );
		
		// Ensure unique slug
		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', $hub_post_id );
		if ( $unique_slug ) {
			wp_update_post( array(
				'ID' => $post_id,
				'post_name' => $unique_slug,
			) );
		}
		
		return array(
			'success' => true,
			'post_id' => $post_id,
			'title' => $title,
		);
	}
	
	/**
	 * Import Service+City page from pre-generated result
	 * 
	 * @param array $result_json - Already generated content from backend
	 * @param array $config - Business config (vertical, etc.)
	 * @param array $item - Item metadata (service, city, etc.)
	 * @param string $post_status - Post status ('draft' or 'publish'), defaults to 'publish'
	 * @return array - ['success' => bool, 'post_id' => int, 'title' => string, 'error' => string]
	 */
	public function import_service_city_from_result( $result_json, $config, $item, $post_status = 'publish' ) {
		// Extract data from result
		$title = isset( $result_json['title'] ) ? $result_json['title'] : '';
		$slug = isset( $result_json['slug'] ) ? $result_json['slug'] : '';
		$meta_description = isset( $result_json['meta_description'] ) ? $result_json['meta_description'] : '';
		$blocks = isset( $result_json['blocks'] ) ? $result_json['blocks'] : array();
		$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : 'service_city';
		
		if ( empty( $blocks ) ) {
			return array(
				'success' => false,
				'error' => 'No blocks in result_json',
			);
		}
		
		// Build Gutenberg content
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );
		
		// Apply header/footer templates
		$settings = $this->get_settings();
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				$gutenberg_markup = $header_content . $gutenberg_markup;
			}
		}
		
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				$gutenberg_markup = $gutenberg_markup . $footer_content;
			}
		}
		
		// Find City Hub parent if hub_key and city info available
		$city_hub_parent_id = 0;
		if ( isset( $item['hub_key'] ) && ! empty( $item['hub_key'] ) && isset( $item['city'], $item['state'] ) ) {
			$city_slug = sanitize_title( $item['city'] . '-' . $item['state'] );
			$city_hub_parent_id = $this->find_city_hub_post_id( $item['hub_key'], $city_slug );
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Looking for city hub parent: hub_key=' . $item['hub_key'] . ' city_slug=' . $city_slug . ' found_id=' . $city_hub_parent_id . PHP_EOL, FILE_APPEND );
		} else {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping city hub parent lookup: hub_key=' . ( isset( $item['hub_key'] ) ? $item['hub_key'] : 'NOT SET' ) . ' city=' . ( isset( $item['city'] ) ? $item['city'] : 'NOT SET' ) . ' state=' . ( isset( $item['state'] ) ? $item['state'] : 'NOT SET' ) . PHP_EOL, FILE_APPEND );
		}
		
		// Create post (no duplicate detection for service+city pages currently)
		$post_data = array(
			'post_type' => 'service_page',
			'post_status' => $post_status,
			'post_title' => $title,
			'post_name' => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
			'post_parent' => $city_hub_parent_id,
		);
		
		$post_id = wp_insert_post( $post_data, true );
		
		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'error' => $post_id->get_error_message(),
			);
		}
		
		// Save metadata
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
		update_post_meta( $post_id, '_seogen_page_mode', 'service_city' );
		update_post_meta( $post_id, '_seogen_vertical', isset( $config['vertical'] ) ? $config['vertical'] : '' );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		
		// Extract and save service/city metadata from item
		if ( isset( $item['service'] ) ) {
			update_post_meta( $post_id, '_seogen_service_name', sanitize_text_field( $item['service'] ) );
			update_post_meta( $post_id, '_seogen_service_slug', sanitize_title( $item['service'] ) );
		}
		
		if ( isset( $item['city'], $item['state'] ) ) {
			$city_state = $item['city'] . ', ' . $item['state'];
			update_post_meta( $post_id, '_seogen_city', sanitize_text_field( $city_state ) );
			update_post_meta( $post_id, '_seogen_city_slug', sanitize_title( $item['city'] . '-' . $item['state'] ) );
		}
		
		// Try to determine hub_key from item
		if ( isset( $item['hub_key'] ) && ! empty( $item['hub_key'] ) ) {
			update_post_meta( $post_id, '_seogen_hub_key', $item['hub_key'] );
		}
		
		// Apply page builder settings if header/footer disabled
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}
		
		// Apply SEO plugin meta
		$service = isset( $item['service'] ) ? $item['service'] : '';
		$city = isset( $item['city'] ) ? $item['city'] : '';
		$focus_keyword = $service . ' ' . $city;
		$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );
		
		// Ensure unique slug
		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', 0 );
		if ( $unique_slug ) {
			wp_update_post( array(
				'ID' => $post_id,
				'post_name' => $unique_slug,
			) );
		}
		
		return array(
			'success' => true,
			'post_id' => $post_id,
			'title' => $title,
		);
	}
}
