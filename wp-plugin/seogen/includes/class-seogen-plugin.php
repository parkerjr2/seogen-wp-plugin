<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Plugin {
	/**
	 * Check if a post is managed by SEOgen (service_city or service_hub page)
	 * 
	 * @param int $post_id Post ID to check
	 * @return bool True if managed by SEOgen
	 */
	public static function seogen_is_managed_page( $post_id ) {
		if ( ! $post_id ) {
			return false;
		}
		
		$managed = get_post_meta( $post_id, '_hyper_local_managed', true );
		if ( '1' === $managed ) {
			return true;
		}
		
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( in_array( $page_mode, array( 'service_city', 'service_hub' ), true ) ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Get the page mode for a SEOgen managed page
	 * 
	 * @param int $post_id Post ID to check
	 * @return string Page mode (service_city, service_hub, or empty string)
	 */
	public static function seogen_get_page_mode( $post_id ) {
		if ( ! $post_id ) {
			return '';
		}
		
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( in_array( $page_mode, array( 'service_city', 'service_hub' ), true ) ) {
			return $page_mode;
		}
		
		// Fallback: check _hl_page_type
		$page_type = get_post_meta( $post_id, '_hl_page_type', true );
		if ( in_array( $page_type, array( 'service_city', 'service_hub' ), true ) ) {
			return $page_type;
		}
		
		return '';
	}
	
	public function run() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_head', array( $this, 'maybe_output_service_schema' ) );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'the_title', array( $this, 'filter_the_title' ), 10, 2 );
		add_filter( 'wpseo_breadcrumb_output', array( $this, 'filter_yoast_breadcrumb_output' ), 10, 1 );
		add_filter( 'rank_math/frontend/breadcrumb/html', array( $this, 'filter_rankmath_breadcrumb_html' ), 10, 2 );
		add_shortcode( 'seogen_service_hub_links', array( $this, 'render_service_hub_links_shortcode' ) );
		add_shortcode( 'seogen_city_hub_links', array( $this, 'render_city_hub_links_shortcode' ) );
		add_shortcode( 'seogen_service_hub_city_links', array( $this, 'render_service_hub_city_links_shortcode' ) );
		add_shortcode( 'seogen_parent_hub_link', array( $this, 'render_parent_hub_link_shortcode' ) );
		add_shortcode( 'seogen_city_hub_link', array( $this, 'render_city_hub_link_shortcode' ) );

		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-city-service-links.php';
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-meta-inspector.php';
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-services-diagnostic.php';
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-diagnostics.php';
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-clear-cache.php';
		$admin = new SEOgen_Admin();
		$admin->register_bulk_worker_hooks();
		
		// Always register frontend hooks (needed for preview requests)
		$admin->register_frontend_hooks();
		
		if ( is_admin() ) {
			$admin->run();
		}
	}

	public function filter_body_class( $classes ) {
		// Check by post_type first (fast path)
		if ( is_singular( 'service_page' ) ) {
			$classes[] = 'hyper-local-page';
			$post_id = get_queried_object_id();
			if ( $post_id && self::seogen_is_managed_page( $post_id ) ) {
				$classes[] = 'seogen-managed';
				$settings = get_option( 'seogen_settings', array() );
				if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
					$classes[] = 'seogen-no-header-footer';
				}
			}
			return $classes;
		}
		
		// Meta-based detection (for pages that might be WP Page type but are SEOgen managed)
		$post_id = get_queried_object_id();
		if ( $post_id && self::seogen_is_managed_page( $post_id ) ) {
			$classes[] = 'hyper-local-page';
			$classes[] = 'seogen-managed';
			$settings = get_option( 'seogen_settings', array() );
			if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
				$classes[] = 'seogen-no-header-footer';
			}
		}
		
		return $classes;
	}

	public function filter_the_title( $title, $post_id ) {
		if ( is_admin() ) {
			return $title;
		}

		// Check by post_type OR meta
		$is_service_page = is_singular( 'service_page' );
		$is_managed = $post_id && self::seogen_is_managed_page( $post_id );
		
		if ( ! $is_service_page && ! $is_managed ) {
			return $title;
		}

		if ( ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}

		$queried_id = get_queried_object_id();
		if ( $queried_id && (int) $queried_id !== (int) $post_id ) {
			return $title;
		}

		return '';
	}

	public function filter_yoast_breadcrumb_output( $output ) {
		if ( is_singular( 'service_page' ) ) {
			return '';
		}
		
		// Meta-based detection
		$post_id = get_queried_object_id();
		if ( $post_id && self::seogen_is_managed_page( $post_id ) ) {
			return '';
		}
		
		return $output;
	}

	public function filter_rankmath_breadcrumb_html( $html, $crumbs ) {
		if ( is_singular( 'service_page' ) ) {
			return '';
		}
		
		// Meta-based detection
		$post_id = get_queried_object_id();
		if ( $post_id && self::seogen_is_managed_page( $post_id ) ) {
			return '';
		}
		
		return $html;
	}

	public function enqueue_frontend_assets() {
		// Check by post_type OR meta
		$is_service_page = is_singular( 'service_page' );
		$post_id = get_queried_object_id();
		$is_managed = $post_id && self::seogen_is_managed_page( $post_id );
		
		if ( ! $is_service_page && ! $is_managed ) {
			return;
		}

		wp_enqueue_style(
			'hyper-local-cleanup',
			SEOGEN_PLUGIN_URL . 'assets/hyper-local-cleanup.css',
			array(),
			SEOGEN_VERSION
		);

		wp_enqueue_style(
			'hyper-local-adaptive',
			SEOGEN_PLUGIN_URL . 'assets/hyper-local-adaptive.css',
			array(),
			SEOGEN_VERSION
		);

		$settings = get_option( 'seogen_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$sticky_enabled = ! empty( $settings['enable_mobile_sticky_cta'] );
		$cta_label = isset( $settings['primary_cta_label'] ) ? (string) $settings['primary_cta_label'] : 'Call Now';
		$cta_label = trim( $cta_label );
		if ( '' === $cta_label ) {
			$cta_label = 'Call Now';
		}

		if ( $sticky_enabled ) {
			$post_id = get_queried_object_id();
			$tel_url = '';
			if ( $post_id ) {
				$source_json = get_post_meta( $post_id, '_hyper_local_source_json', true );
				if ( is_string( $source_json ) && '' !== $source_json ) {
					$decoded = json_decode( $source_json, true );
					if ( is_array( $decoded ) && isset( $decoded['blocks'] ) && is_array( $decoded['blocks'] ) ) {
						foreach ( $decoded['blocks'] as $block ) {
							if ( ! is_array( $block ) || empty( $block['type'] ) ) {
								continue;
							}
							if ( 'nap' !== (string) $block['type'] ) {
								continue;
							}
							$phone = isset( $block['phone'] ) ? (string) $block['phone'] : '';
							$digits = preg_replace( '/\D+/', '', $phone );
							if ( '' !== $digits ) {
								$tel_url = 'tel:' . $digits;
								break;
							}
						}
					}
				}
			}

			if ( '' !== $tel_url ) {
				wp_enqueue_script(
					'hyper-local-sticky-cta',
					SEOGEN_PLUGIN_URL . 'assets/hyper-local-sticky-cta.js',
					array(),
					SEOGEN_VERSION,
					true
				);
				wp_localize_script(
					'hyper-local-sticky-cta',
					'hyperLocalStickyCta',
					array(
						'telUrl' => esc_url_raw( $tel_url ),
						'label'  => $cta_label,
					)
				);
			}
		}

		$preset = isset( $settings['design_preset'] ) ? (string) $settings['design_preset'] : 'theme_default';
		$preset = sanitize_key( $preset );
		if ( '' === $preset ) {
			$preset = 'theme_default';
		}

		if ( 'theme_default' === $preset ) {
			return;
		}

		wp_enqueue_style(
			'hyper-local-frontend',
			SEOGEN_PLUGIN_URL . 'assets/hyper-local-frontend.css',
			array(),
			SEOGEN_VERSION
		);
	}

	private function is_yoast_active() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return true;
		}
		if ( class_exists( 'WPSEO_Meta' ) ) {
			return true;
		}
		return false;
	}

	private function is_rankmath_active() {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return true;
		}
		if ( class_exists( '\\RankMath\\Helper' ) ) {
			return true;
		}
		if ( function_exists( 'rank_math' ) ) {
			return true;
		}
		return false;
	}

	private function parse_service_city_state_from_title( $title ) {
		$title = sanitize_text_field( wp_strip_all_tags( (string) $title ) );
		$title = trim( $title );
		if ( '' === $title ) {
			return array( '', '', '' );
		}

		if ( false !== strpos( $title, '|' ) ) {
			$parts = array_map( 'trim', explode( '|', $title ) );
			if ( isset( $parts[0] ) && '' !== $parts[0] ) {
				$title = $parts[0];
			}
		}

		$service = '';
		$city = '';
		$state = '';
		if ( preg_match( '/^(.+?)\s+in\s+([A-Za-z\s\.-]+),\s*([A-Za-z]{2})\b/', $title, $m ) ) {
			$service = trim( (string) $m[1] );
			$city = trim( (string) $m[2] );
			$state = trim( (string) $m[3] );
		}

		return array( $service, $city, $state );
	}

	public function maybe_output_service_schema() {
		if ( is_admin() ) {
			return;
		}
		if ( ! is_singular( 'service_page' ) ) {
			return;
		}
		if ( ! $this->is_yoast_active() && ! $this->is_rankmath_active() ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'service_page' !== (string) $post->post_type ) {
			return;
		}

		$source_json = get_post_meta( $post_id, '_hyper_local_source_json', true );
		$decoded = null;
		if ( is_string( $source_json ) && '' !== $source_json ) {
			$decoded = json_decode( $source_json, true );
		}
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		$business_name = '';
		$phone = '';
		$address = '';
		if ( isset( $decoded['blocks'] ) && is_array( $decoded['blocks'] ) ) {
			foreach ( $decoded['blocks'] as $block ) {
				if ( ! is_array( $block ) || empty( $block['type'] ) ) {
					continue;
				}
				if ( 'nap' !== (string) $block['type'] ) {
					continue;
				}
				$business_name = isset( $block['business_name'] ) ? (string) $block['business_name'] : '';
				$phone = isset( $block['phone'] ) ? (string) $block['phone'] : '';
				$address = isset( $block['address'] ) ? (string) $block['address'] : '';
				break;
			}
		}

		$service = '';
		$city = '';
		$state = '';
		list( $service, $city, $state ) = $this->parse_service_city_state_from_title( $post->post_title );

		$service = sanitize_text_field( wp_strip_all_tags( (string) $service ) );
		$city = sanitize_text_field( wp_strip_all_tags( (string) $city ) );
		$state = sanitize_text_field( wp_strip_all_tags( (string) $state ) );
		$business_name = sanitize_text_field( wp_strip_all_tags( (string) $business_name ) );
		$phone_digits = preg_replace( '/\D+/', '', (string) $phone );
		$phone = sanitize_text_field( wp_strip_all_tags( (string) $phone ) );
		$address = sanitize_text_field( wp_strip_all_tags( (string) $address ) );

		$name = '';
		if ( '' !== $service && '' !== $city && '' !== $state ) {
			$name = $service . ' in ' . $city . ', ' . $state;
		}
		if ( '' === $name ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Service',
			'name'     => $name,
		);
		if ( '' !== $service ) {
			$schema['serviceType'] = $service;
		}
		if ( '' !== $city ) {
			$schema['areaServed'] = array(
				'@type' => 'City',
				'name'  => $city,
			);
		}

		$provider = array(
			'@type' => 'LocalBusiness',
		);
		if ( '' !== $business_name ) {
			$provider['name'] = $business_name;
		}
		if ( '' !== $phone_digits ) {
			$provider['telephone'] = $phone;
		}
		if ( '' !== $address || '' !== $city || '' !== $state ) {
			$provider_address = array(
				'@type' => 'PostalAddress',
			);
			if ( '' !== $address ) {
				$provider_address['streetAddress'] = $address;
			}
			if ( '' !== $city ) {
				$provider_address['addressLocality'] = $city;
			}
			if ( '' !== $state ) {
				$provider_address['addressRegion'] = $state;
			}
			$provider_address['addressCountry'] = 'US';
			$provider['address'] = $provider_address;
		}

		if ( count( $provider ) > 1 ) {
			$schema['provider'] = $provider;
		}

		$schema_json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! is_string( $schema_json ) || '' === $schema_json ) {
			return;
		}
		echo '<script type="application/ld+json">' . $schema_json . '</script>';
	}

	public function activate() {
		$this->register_post_type();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Service Pages', 'seogen' ),
			'singular_name'         => __( 'Service Page', 'seogen' ),
			'menu_name'             => __( 'Service Pages', 'seogen' ),
			'name_admin_bar'        => __( 'Service Page', 'seogen' ),
			'add_new'               => __( 'Add New', 'seogen' ),
			'add_new_item'          => __( 'Add New Service Page', 'seogen' ),
			'new_item'              => __( 'New Service Page', 'seogen' ),
			'edit_item'             => __( 'Edit Service Page', 'seogen' ),
			'view_item'             => __( 'View Service Page', 'seogen' ),
			'all_items'             => __( 'Service Pages', 'seogen' ),
			'search_items'          => __( 'Search Service Pages', 'seogen' ),
			'not_found'             => __( 'No service pages found.', 'seogen' ),
			'not_found_in_trash'    => __( 'No service pages found in Trash.', 'seogen' ),
			'filter_items_list'     => __( 'Filter service pages list', 'seogen' ),
			'items_list_navigation' => __( 'Service pages list navigation', 'seogen' ),
			'items_list'            => __( 'Service pages list', 'seogen' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'show_in_menu'       => false,
			'has_archive'        => false,
			'hierarchical'       => true,
			'capability_type'    => 'page',
			'map_meta_cap'       => true,
			'rewrite'            => array(
				'slug'         => 'service-area',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'supports'           => array( 'title', 'editor', 'revisions', 'page-attributes', 'custom-fields', 'thumbnail', 'excerpt' ),
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-admin-page',
		);

		register_post_type( 'service_page', $args );
		
		// Ensure Yoast SEO includes this post type in sitemaps
		add_filter( 'wpseo_sitemap_exclude_post_type', function( $excluded, $post_type ) {
			if ( 'service_page' === $post_type ) {
				return false;
			}
			return $excluded;
		}, 10, 2 );
	}

	public function render_service_hub_links_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'hub_key' => '',
		), $atts, 'seogen_service_hub_links' );

		$hub_key = sanitize_text_field( $atts['hub_key'] );
		if ( '' === $hub_key ) {
			return '<p><em>Error: hub_key attribute is required.</em></p>';
		}

		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => 50,
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_city',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
				),
			),
			'orderby' => 'title',
			'order' => 'ASC',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p><em>No service pages found for this hub yet.</em></p>';
		}

		$pages_by_city = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$city = get_post_meta( $post_id, '_seogen_city', true );
			$service_name = get_post_meta( $post_id, '_seogen_service_name', true );
			
			if ( '' === $city ) {
				$city = 'Other';
			}

			if ( ! isset( $pages_by_city[ $city ] ) ) {
				$pages_by_city[ $city ] = array();
			}

			$pages_by_city[ $city ][] = array(
				'id' => $post_id,
				'title' => get_the_title(),
				'permalink' => get_permalink(),
				'service_name' => $service_name,
			);
		}
		wp_reset_postdata();

		ksort( $pages_by_city );

		$output = '<div class="seogen-hub-links">';
		foreach ( $pages_by_city as $city => $pages ) {
			$output .= '<h3>' . esc_html( 'Services in ' . $city ) . '</h3>';
			$output .= '<ul>';
			foreach ( $pages as $page ) {
				$output .= '<li><a href="' . esc_url( $page['permalink'] ) . '">' . esc_html( $page['title'] ) . '</a></li>';
			}
			$output .= '</ul>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Render city hub links shortcode (DEPRECATED - alias to seogen_city_service_links)
	 * 
	 * This shortcode is kept for backwards compatibility but now calls the canonical
	 * seogen_city_service_links renderer to avoid duplicate service link sections.
	 * 
	 * @deprecated Use [seogen_city_service_links] instead
	 */
	public function render_city_hub_links_shortcode( $atts ) {
		// Check if SEOgen_City_Service_Links class exists and use its canonical renderer
		if ( class_exists( 'SEOgen_City_Service_Links' ) ) {
			$city_service_links = new SEOgen_City_Service_Links();
			return $city_service_links->render_city_service_links_shortcode( $atts );
		}
		
		// Fallback if class doesn't exist (shouldn't happen)
		return '<!-- [seogen_city_hub_links] is deprecated. Use [seogen_city_service_links] instead. -->';
	}

	/**
	 * Render city hub links for service hub pages
	 * 
	 * Outputs natural, varied city link sentences with:
	 * - Hub-specific context (residential vs commercial)
	 * - Varied anchor text with electrical/trade keywords
	 * - Local relevance clauses
	 * - Valid HTML structure
	 */
	public function render_service_hub_city_links_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'hub_key' => '',
			'limit' => 6,
		), $atts, 'seogen_service_hub_city_links' );

		$hub_key = sanitize_text_field( $atts['hub_key'] );
		$limit = absint( $atts['limit'] );
		
		if ( '' === $hub_key ) {
			return '<p><em>Error: hub_key attribute is required.</em></p>';
		}

		// Enforce 4-8 city limit for natural output
		if ( $limit < 4 ) {
			$limit = 4;
		}
		if ( $limit > 8 ) {
			$limit = 8;
		}

		// Query all published city hub pages for this hub
		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'city_hub',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
				),
			),
			'orderby' => 'title',
			'order' => 'ASC',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			$debug = '';
			if ( current_user_can( 'manage_options' ) ) {
				$debug = ' <!-- DEBUG: hub_key=' . esc_attr( $hub_key ) . ', found=' . $query->found_posts . ' -->';
			}
			return '<p class="seogen-placeholder"><em>Service areas will appear here once city pages are published.</em></p>' . $debug;
		}

		// Collect all cities
		$cities = array();
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$city_meta = get_post_meta( $post_id, '_seogen_city', true );
			
			$city_name = '';
			$state = '';
			if ( ! empty( $city_meta ) ) {
				$parts = array_map( 'trim', explode( ',', $city_meta ) );
				$city_name = isset( $parts[0] ) ? $parts[0] : '';
				$state = isset( $parts[1] ) ? $parts[1] : '';
			}
			
			if ( empty( $city_name ) ) {
				$city_name = get_the_title();
			}
			
			$cities[] = array(
				'post_id' => $post_id,
				'city_name' => $city_name,
				'state' => $state,
				'permalink' => get_permalink(),
			);
		}
		wp_reset_postdata();

		// Deterministic selection based on hub_key hash for consistency
		if ( count( $cities ) > $limit ) {
			$seed = crc32( $hub_key );
			mt_srand( $seed );
			shuffle( $cities );
			$cities = array_slice( $cities, 0, $limit );
			mt_srand(); // Reset
		}

		// Get trade name for anchor text
		$trade_name = $this->get_trade_name_from_hub_key( $hub_key );
		$trade_keyword = $this->get_trade_keyword_from_name( $trade_name );

		// Build output with intro + city sentences
		$output = '<div class="seogen-service-hub-city-links">';
		
		// Add intro sentence
		$intro = $this->get_city_links_intro( $hub_key, $trade_name );
		$output .= '<p>' . esc_html( $intro ) . '</p>';
		
		// Track used anchor patterns to ensure variety
		$used_patterns = array();
		$exact_match_used = false;
		
		foreach ( $cities as $index => $city ) {
			// Generate varied anchor text with electrical/trade keywords
			$anchor_data = $this->generate_varied_city_anchor(
				$city['city_name'],
				$city['state'],
				$hub_key,
				$trade_name,
				$trade_keyword,
				$index,
				$exact_match_used,
				$used_patterns
			);
			
			$anchor_text = $anchor_data['text'];
			$used_patterns[] = $anchor_data['pattern'];
			if ( $anchor_data['is_exact_match'] ) {
				$exact_match_used = true;
			}
			
			// Generate sentence with lead-in + link + local relevance
			$sentence = $this->generate_improved_city_sentence(
				$city['city_name'],
				$city['state'],
				$anchor_text,
				$city['permalink'],
				$hub_key,
				$trade_keyword,
				$index
			);
			
			$output .= '<p>' . $sentence . '</p>';
		}
		
		$output .= '</div>';

		return $output;
	}

	private function get_service_label_from_hub_key( $hub_key ) {
		// Map hub keys to service labels
		$hub_labels = array(
			'residential' => 'residential',
			'commercial' => 'commercial',
			'emergency' => 'emergency',
			'repair' => 'repair',
			'installation' => 'installation',
			'maintenance' => 'maintenance',
		);
		
		// Check if hub_key exists in mapping
		if ( isset( $hub_labels[ $hub_key ] ) ) {
			return $hub_labels[ $hub_key ];
		}
		
		// Fallback: humanize hub_key
		$label = str_replace( array( '-', '_' ), ' ', $hub_key );
		return strtolower( $label );
	}

	private function get_trade_name_from_hub_key( $hub_key ) {
		$config = get_option( 'seogen_business_config', array() );
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		
		$trade_names = array(
			'electrician' => 'electrical services',
			'plumber' => 'plumbing services',
			'hvac' => 'HVAC services',
			'roofer' => 'roofing services',
			'landscaper' => 'landscaping services',
			'handyman' => 'handyman services',
			'painter' => 'painting services',
			'concrete' => 'concrete services',
			'siding' => 'siding services',
			'locksmith' => 'locksmith services',
			'cleaning' => 'cleaning services',
			'garage-door' => 'garage door services',
			'windows' => 'window services',
			'pest-control' => 'pest control services',
		);
		
		if ( isset( $trade_names[ $vertical ] ) ) {
			return $trade_names[ $vertical ];
		}
		
		return 'services';
	}

	/**
	 * Extract trade keyword from trade name for anchor text
	 * e.g., "electrical services" -> "electrical" or "electrician"
	 */
	private function get_trade_keyword_from_name( $trade_name ) {
		$keywords = array(
			'electrical services' => 'electrical',
			'plumbing services' => 'plumbing',
			'HVAC services' => 'HVAC',
			'roofing services' => 'roofing',
			'landscaping services' => 'landscaping',
			'handyman services' => 'handyman',
			'painting services' => 'painting',
			'concrete services' => 'concrete',
			'siding services' => 'siding',
			'locksmith services' => 'locksmith',
			'cleaning services' => 'cleaning',
			'garage door services' => 'garage door',
			'window services' => 'window',
			'pest control services' => 'pest control',
		);
		
		if ( isset( $keywords[ $trade_name ] ) ) {
			return $keywords[ $trade_name ];
		}
		
		// Extract first word as fallback
		$parts = explode( ' ', $trade_name );
		return $parts[0];
	}

	/**
	 * Get vertical vocabulary (actions, objects, benefits)
	 * Returns vertical-specific vocabulary or generic fallback
	 */
	private function get_vertical_vocabulary() {
		$config = get_option( 'seogen_business_config', array() );
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		
		$vocab_map = array(
			'electrician' => array(
				'actions' => array('inspect','upgrade','install','repair','replace','update','troubleshoot','restore'),
				'objects' => array(
					'electrical panel','breaker box','circuits','wiring','outlets','switches',
					'lighting','ceiling fans','dedicated circuits','surge protection',
					'GFCI protection','AFCI protection','grounding','service entrance','load capacity'
				),
				'benefits' => array('safety','code compliance','reliability','home protection','efficient power use','peace of mind'),
			),
			'plumber' => array(
				'actions' => array('inspect','repair','replace','install','unclog','clear','restore','maintain','diagnose'),
				'objects' => array(
					'pipes','drains','water lines','shutoff valves','faucets','toilets',
					'sinks','tubs and showers','garbage disposal','water heater',
					'tankless water heater','sewer line','sump pump','hose bibs','leak points'
				),
				'benefits' => array('leak prevention','water efficiency','reliability','home protection','healthy plumbing','code compliance'),
			),
			'hvac' => array(
				'actions' => array('inspect','repair','replace','install','tune','service','clean','optimize','restore'),
				'objects' => array(
					'air conditioner','furnace','heat pump','air handler','thermostat',
					'ductwork','vents and returns','airflow','filters','condensate drain',
					'refrigerant lines','blower motor','indoor air quality system','zone controls'
				),
				'benefits' => array('comfort','energy efficiency','reliability','consistent airflow','better air quality','seasonal readiness'),
			),
			'roofer' => array(
				'actions' => array('inspect','repair','replace','install','seal','restore','reinforce','maintain'),
				'objects' => array(
					'shingles','roof decking','underlayment','flashing','roof vents',
					'ridge cap','valleys','chimney flashing','skylight flashing',
					'roof seals','drip edge','gutters and downspouts','leak areas'
				),
				'benefits' => array('weather protection','leak prevention','home protection','energy efficiency','longer roof life','peace of mind'),
			),
			'landscaper' => array(
				'actions' => array('design','install','refresh','repair','maintain','trim','edge','restore','improve'),
				'objects' => array(
					'plant beds','shrubs and small trees','mulch','sod','lawn health',
					'drainage','grading','retaining wall areas','walkways','hardscaping',
					'garden borders','seasonal cleanup','irrigation coverage'
				),
				'benefits' => array('curb appeal','healthy growth','clean lines','better drainage','easy maintenance','outdoor enjoyment'),
			),
			'handyman' => array(
				'actions' => array('fix','repair','install','replace','patch','adjust','restore','refresh','assemble'),
				'objects' => array(
					'doors and hardware','drywall repairs','trim and baseboards','caulking and sealing',
					'minor plumbing fixtures','light fixtures','ceiling fans','TV mounting',
					'shelving','faucets','tile repairs','fence repairs','smart home devices'
				),
				'benefits' => array('reliability','home upkeep','better function','clean finish','home protection','time savings'),
			),
			'painter' => array(
				'actions' => array('prep','paint','prime','repair','touch up','refresh','seal','recoat'),
				'objects' => array(
					'interior walls','ceilings','trim and baseboards','doors','cabinets',
					'exterior siding','fascia and soffits','decks and railings',
					'drywall patches','stained areas','peeling paint','high-traffic areas'
				),
				'benefits' => array('clean finish','curb appeal','surface protection','longer-lasting paint','easy cleaning','fresh look'),
			),
			'concrete' => array(
				'actions' => array('pour','install','repair','replace','level','resurface','seal','reinforce'),
				'objects' => array(
					'driveway','walkway','patio','steps','slab','garage floor',
					'foundation areas','cracks','uneven sections','expansion joints',
					'surface finish','drainage slope','approach and curb edges'
				),
				'benefits' => array('safety','durability','curb appeal','smooth surfaces','reduced cracking','proper drainage'),
			),
			'siding' => array(
				'actions' => array('inspect','repair','replace','install','seal','restore','secure','upgrade'),
				'objects' => array(
					'siding panels','trim','soffits','fascia','house wrap',
					'moisture barriers','damaged boards','wind-damaged sections',
					'corners and seams','caulking','flashing details','vent openings'
				),
				'benefits' => array('weather protection','home protection','curb appeal','energy efficiency','moisture control','longer exterior life'),
			),
			'locksmith' => array(
				'actions' => array('rekey','repair','replace','install','unlock','secure','upgrade','adjust'),
				'objects' => array(
					'deadbolts','door locks','smart locks','keypads','lock cylinders',
					'door handles','strike plates','sliding door locks','garage entry locks',
					'key copies','master key setup','home lockout situations'
				),
				'benefits' => array('security','peace of mind','reliable access','better lock performance','home protection','convenience'),
			),
			'cleaning' => array(
				'actions' => array('clean','deep clean','sanitize','refresh','remove buildup','detail','deodorize'),
				'objects' => array(
					'kitchens','bathrooms','floors','baseboards','appliances','showers and tubs',
					'high-touch surfaces','windows','carpeted areas','entryways','move-in cleanup','move-out cleanup'
				),
				'benefits' => array('hygiene','freshness','comfort','healthy home','time savings','better appearance'),
			),
			'garage-door' => array(
				'actions' => array('inspect','repair','replace','install','adjust','align','lubricate','restore'),
				'objects' => array(
					'garage door springs','openers','rollers','tracks','cables',
					'safety sensors','weather stripping','door panels','hinges',
					'remote controls','keypads','noisy door issues','off-track doors'
				),
				'benefits' => array('safety','reliable operation','smooth movement','quieter performance','home security','convenience'),
			),
			'windows' => array(
				'actions' => array('inspect','replace','repair','install','seal','upgrade','adjust','restore'),
				'objects' => array(
					'window glass','window frames','seals','weather stripping','locks and latches',
					'sliding tracks','screens','drafty windows','fogged panes','caulk lines',
					'trim around windows','energy-efficient windows'
				),
				'benefits' => array('energy efficiency','comfort','better insulation','noise reduction','smooth operation','home value'),
			),
			'pest-control' => array(
				'actions' => array('inspect','treat','remove','seal','prevent','eliminate','monitor','protect'),
				'objects' => array(
					'entry points','ant trails','roach activity','spider webs','rodent signs',
					'wasp nests','termite risk areas','crawlspace areas','attic activity',
					'perimeter barriers','moisture attractants','nuisance pests'
				),
				'benefits' => array('home protection','prevention','peace of mind','healthier home','reduced recurrence','property protection'),
			),
		);
		
		// Generic fallback for unknown verticals
		$fallback = array(
			'actions' => array('inspect','repair','replace','install','upgrade','maintain'),
			'objects' => array('key systems','common problem areas','critical components','safety items'),
			'benefits' => array('safety','reliability','efficiency','home protection'),
		);
		
		return isset( $vocab_map[ $vertical ] ) ? $vocab_map[ $vertical ] : $fallback;
	}

	/**
	 * Get vertical-specific intro sentence - exactly one, no marketing fluff
	 */
	private function get_city_links_intro( $hub_key, $trade_name ) {
		$config = get_option( 'seogen_business_config', array() );
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		
		$intro_map = array(
			'electrician' => "We help homeowners across the Tulsa area keep residential electrical systems safe and up to date.",
			'plumber' => "We help homeowners across the Tulsa area resolve plumbing issues quickly and keep water systems reliable.",
			'hvac' => "We help homeowners across the Tulsa area maintain comfortable indoor temperatures and reliable HVAC systems.",
			'roofer' => "We help homeowners across the Tulsa area protect their homes with dependable roofing repairs and replacements.",
			'landscaper' => "We help homeowners across the Tulsa area maintain beautiful outdoor spaces and healthy landscapes.",
			'handyman' => "We help homeowners across the Tulsa area with reliable repairs and home improvement projects.",
			'painter' => "We help homeowners across the Tulsa area refresh their homes with professional painting and surface preparation.",
			'concrete' => "We help homeowners across the Tulsa area with durable concrete work for driveways, walkways, and patios.",
			'siding' => "We help homeowners across the Tulsa area protect their homes with quality siding repairs and installations.",
			'locksmith' => "We help homeowners across the Tulsa area secure their properties with reliable lock solutions.",
			'cleaning' => "We help homeowners across the Tulsa area maintain clean, healthy living spaces.",
			'garage-door' => "We help homeowners across the Tulsa area keep garage doors operating safely and smoothly.",
			'windows' => "We help homeowners across the Tulsa area improve comfort and efficiency with quality window solutions.",
			'pest-control' => "We help homeowners across the Tulsa area protect their properties from pests and prevent future issues.",
		);
		
		if ( isset( $intro_map[ $vertical ] ) ) {
			return $intro_map[ $vertical ];
		}
		
		return "We help homeowners across the Tulsa area maintain safe, reliable home systems.";
	}

	/**
	 * Generate anchor text using vertical vocabulary
	 * CITY MENTION BUDGET: City appears in sentence, NOT in anchor
	 * Format: "{action} {object}" or "{object}"
	 */
	private function generate_varied_city_anchor( $city_name, $state, $hub_key, $trade_name, $trade_keyword, $index, $exact_match_used, $used_patterns ) {
		$vocab = $this->get_vertical_vocabulary();
		$actions = $vocab['actions'];
		$objects = $vocab['objects'];
		
		// Build anchor patterns from vertical vocabulary
		// NO city name, NO 'services' keyword
		$patterns = array();
		
		// Generate patterns: action + object
		foreach ( $objects as $obj_index => $object ) {
			$action_index = $obj_index % count( $actions );
			$action = $actions[ $action_index ];
			
			$patterns[] = array(
				'pattern' => 'obj_' . $obj_index,
				'text' => $object,
				'object' => $object, // Track object for rotation
				'is_exact_match' => false,
			);
		}
		
		// Filter out used patterns
		$available = array_filter( $patterns, function( $p ) use ( $used_patterns ) {
			return ! in_array( $p['pattern'], $used_patterns, true );
		} );
		
		if ( empty( $available ) ) {
			$available = $patterns;
		}
		
		// Select deterministically
		$available = array_values( $available );
		$hash = crc32( $hub_key . $city_name . $index );
		$selected = $available[ $hash % count( $available ) ];
		
		return $selected;
	}


	/**
	 * Generate natural city sentence with varied structures
	 * Each sentence uses different pattern and includes electrical keyword naturally
	 */
	private function generate_improved_city_sentence( $city_name, $state, $anchor_text, $permalink, $hub_key, $trade_keyword, $index ) {
		$link = '<a href="' . esc_url( $permalink ) . '">' . esc_html( $anchor_text ) . '</a>';
		
		// Get natural sentence with varied structure
		return $this->get_natural_city_sentence( $city_name, $link, $hub_key, $trade_keyword, $index );
	}

	/**
	 * Generate sentence using vertical vocabulary
	 * CITY MENTION BUDGET: City appears EXACTLY ONCE (in sentence, not anchor)
	 * Structure: ACTION + OBJECT + CITY + BENEFIT
	 */
	private function get_natural_city_sentence( $city_name, $link, $hub_key, $trade_keyword, $index ) {
		$vocab = $this->get_vertical_vocabulary();
		$actions = $vocab['actions'];
		$benefits = $vocab['benefits'];
		
		// Select action and benefit for variety
		$action = $actions[ $index % count( $actions ) ];
		$benefit = $benefits[ $index % count( $benefits ) ];
		
		// Sentence templates - city appears in plain text, NOT in link
		if ( 'residential' === $hub_key ) {
			$patterns = array(
				'Homeowners in ' . $city_name . ' often call us to ' . $action . ' ' . $link . ' to improve ' . $benefit . '.',
				'In ' . $city_name . ', we regularly help with ' . $link . ' when it\'s time to ' . $action . ' and restore ' . $benefit . '.',
				'If you live in ' . $city_name . ', our team can ' . $action . ' ' . $link . ' before small issues affect ' . $benefit . '.',
				'We frequently assist homeowners in ' . $city_name . ' with ' . $link . ', including ' . $action . ' work that supports ' . $benefit . '.',
				'Many homes in ' . $city_name . ' benefit from ' . $link . ' to improve ' . $benefit . '.',
				'Property owners in ' . $city_name . ' reach out when they need to ' . $action . ' ' . $link . '.',
				'We help families in ' . $city_name . ' with ' . $link . ' to maintain ' . $benefit . '.',
				'Older homes in ' . $city_name . ' often need ' . $link . ' to restore ' . $benefit . '.',
			);
		} elseif ( 'commercial' === $hub_key ) {
			$patterns = array(
				'Businesses in ' . $city_name . ' rely on our team to ' . $action . ' ' . $link . '.',
				'In ' . $city_name . ', we help commercial properties with ' . $link . ' to maintain ' . $benefit . '.',
				'Facility managers in ' . $city_name . ' choose us to ' . $action . ' ' . $link . '.',
				'Commercial properties in ' . $city_name . ' need ' . $link . ' for ' . $benefit . '.',
				'We work with businesses in ' . $city_name . ' on ' . $link . ' and ' . $benefit . '.',
			);
		} elseif ( 'emergency' === $hub_key ) {
			$patterns = array(
				'When emergencies happen in ' . $city_name . ', we respond quickly to ' . $action . ' ' . $link . '.',
				'Property owners in ' . $city_name . ' call us to ' . $action . ' ' . $link . '.',
				'In ' . $city_name . ', our team provides ' . $link . ' to restore ' . $benefit . '.',
				'For urgent issues in ' . $city_name . ', we offer ' . $link . ' around the clock.',
			);
		} else {
			$patterns = array(
				'In ' . $city_name . ', we help property owners with ' . $link . '.',
				'Property owners in ' . $city_name . ' choose our team to ' . $action . ' ' . $link . '.',
				'We work with clients in ' . $city_name . ' on ' . $link . ' for ' . $benefit . '.',
			);
		}
		
		return $patterns[ $index % count( $patterns ) ];
	}

	/**
	 * Render parent hub link shortcode
	 * 
	 * INTERNAL LINKING: City Hub → Service Hub (parent)
	 * 
	 * Purpose:
	 * - Improves crawlability and user orientation
	 * - NOT a breadcrumb (simple editorial link)
	 * - NOT doorway-style linking (clean parent-child relationship)
	 * - Helps users navigate back to main service category
	 * 
	 * Safety:
	 * - Only renders on city_hub pages
	 * - Only links to parent Service Hub (same hub_key)
	 * - Fails silently if context invalid
	 * 
	 * @return string HTML output or empty string
	 */
	public function render_parent_hub_link_shortcode() {
		// Safety check: Only render on city_hub pages
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<!-- DEBUG: No post_id -->';
			}
			return '';
		}
		
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'city_hub' !== $page_mode ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<!-- DEBUG: page_mode=' . esc_html( $page_mode ) . ', expected=city_hub -->';
			}
			return '';
		}
		
		// Get hub_key from current city hub page
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		if ( empty( $hub_key ) ) {
			return '';
		}
		
		// Query for parent Service Hub page
		$parent_hub_query = new WP_Query( array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'hub',
					'compare' => '=',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
					'compare' => '=',
				),
			),
		) );
		
		// Safety check: Exactly one parent hub should exist
		if ( ! $parent_hub_query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}
		
		$parent_hub = $parent_hub_query->posts[0];
		wp_reset_postdata();
		
		// Get parent hub URL and title
		$parent_url = get_permalink( $parent_hub->ID );
		$parent_title = get_the_title( $parent_hub->ID );
		
		// Clean title: Remove trailing city/state if present
		// Example: "Residential Electrical Services in Tulsa, OK" → "Residential Electrical"
		$clean_title = preg_replace( '/\s+(in|near|around|for)\s+[A-Z][^,]+,?\s*[A-Z]{2}$/i', '', $parent_title );
		$clean_title = preg_replace( '/\s+services$/i', '', $clean_title ); // Remove trailing "services"
		$clean_title = trim( $clean_title );
		
		// Generate natural link text
		// Pattern: "View all {Service Name} services"
		$link_text = 'View all ' . esc_html( $clean_title ) . ' services';
		
		// Render simple editorial link
		$output = '<p class="seogen-parent-hub-link">';
		$output .= '← <a href="' . esc_url( $parent_url ) . '">' . $link_text . '</a>';
		$output .= '</p>';
		
		return $output;
	}

	/**
	 * Render city hub link for service+city pages
	 * 
	 * Displays "← Back to {Hub Name} in {City}" link on service+city pages
	 * linking back to their parent city hub.
	 * 
	 * @return string HTML output or empty string
	 */
	public function render_city_hub_link_shortcode() {
		// Safety check: Only render on service_city pages
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<!-- DEBUG: No post_id -->';
			}
			return '';
		}
		
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'service_city' !== $page_mode ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<!-- DEBUG: page_mode=' . esc_html( $page_mode ) . ', expected=service_city -->';
			}
			return '';
		}
		
		// Get hub_key and city_slug from current service+city page
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
		
		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			return '';
		}
		
		// Query for parent City Hub page
		$city_hub_query = new WP_Query( array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'city_hub',
					'compare' => '=',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
					'compare' => '=',
				),
				array(
					'key' => '_seogen_city_slug',
					'value' => $city_slug,
					'compare' => '=',
				),
			),
		) );
		
		// If city hub doesn't exist yet, return empty (no error)
		if ( ! $city_hub_query->have_posts() ) {
			wp_reset_postdata();
			return '';
		}
		
		$city_hub = $city_hub_query->posts[0];
		wp_reset_postdata();
		
		// Get city hub URL and title
		$city_hub_url = get_permalink( $city_hub->ID );
		$city_hub_title = get_the_title( $city_hub->ID );
		
		// Clean title: Remove trailing "in {City}, {State}" if present
		// Example: "Residential Electrical in Tulsa, OK" → "Residential Electrical in Tulsa"
		$clean_title = preg_replace( '/\s+(in|near|around|for)\s+[A-Z][^,]+,?\s*[A-Z]{2}$/i', '', $city_hub_title );
		$clean_title = preg_replace( '/\s+services$/i', '', $clean_title ); // Remove trailing "services"
		$clean_title = trim( $clean_title );
		
		// Extract city name from title for link text
		// Pattern: "Residential Electrical in Tulsa, OK" → extract "Tulsa"
		$city_name = '';
		if ( preg_match( '/\s+(in|near|around|for)\s+([A-Z][^,]+)/i', $city_hub_title, $matches ) ) {
			$city_name = trim( $matches[2] );
		}
		
		// Generate natural link text
		// Pattern: "View all {Hub Name} services in {City}"
		if ( ! empty( $city_name ) ) {
			$link_text = 'View all ' . esc_html( $clean_title ) . ' services in ' . esc_html( $city_name );
		} else {
			$link_text = 'View all ' . esc_html( $clean_title ) . ' services';
		}
		
		// Render simple editorial link matching parent hub link style
		$output = '<p class="seogen-city-hub-link">';
		$output .= '← <a href="' . esc_url( $city_hub_url ) . '">' . $link_text . '</a>';
		$output .= '</p>';
		
		return $output;
	}

}
