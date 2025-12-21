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

		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
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

	public function render_city_hub_links_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'hub_key' => '',
			'city_slug' => '',
		), $atts, 'seogen_city_hub_links' );

		$hub_key = sanitize_text_field( $atts['hub_key'] );
		$city_slug = sanitize_text_field( $atts['city_slug'] );
		
		if ( '' === $hub_key || '' === $city_slug ) {
			return '<p><em>Error: hub_key and city_slug attributes are required.</em></p>';
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
				array(
					'key' => '_seogen_city_slug',
					'value' => $city_slug,
				),
			),
			'orderby' => 'title',
			'order' => 'ASC',
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return '<p><em>No service pages found for this city yet.</em></p>';
		}

		$output = '<div class="seogen-city-hub-links"><ul>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$output .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>';
		}
		$output .= '</ul></div>';
		wp_reset_postdata();

		return $output;
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
	 * Get intro sentence for city links section
	 */
	private function get_city_links_intro( $hub_key, $trade_name ) {
		$intros = array(
			'residential' => "We're proud to serve homeowners throughout the area with professional {$trade_name}.",
			'commercial' => "Our team provides reliable {$trade_name} to businesses and commercial properties across the region.",
			'emergency' => "When you need urgent {$trade_name}, we respond quickly to properties throughout the area.",
		);
		
		if ( isset( $intros[ $hub_key ] ) ) {
			return $intros[ $hub_key ];
		}
		
		return "We provide professional {$trade_name} throughout the area.";
	}

	/**
	 * Generate varied anchor text with electrical/trade keywords
	 * 
	 * Rules:
	 * - At most 1 exact-match anchor (e.g., "Residential Electrical Services in City, OK")
	 * - Others are partial/semantic anchors with trade keywords
	 * - Always include electrical/electrician or trade-specific keyword
	 * 
	 * @return array ['text' => anchor text, 'pattern' => pattern ID, 'is_exact_match' => bool]
	 */
	private function generate_varied_city_anchor( $city_name, $state, $hub_key, $trade_name, $trade_keyword, $index, $exact_match_used, $used_patterns ) {
		$service_label = ucfirst( $this->get_service_label_from_hub_key( $hub_key ) );
		
		// Define anchor patterns with trade keywords
		$patterns = array();
		
		// Exact match pattern (only use once)
		if ( ! $exact_match_used && $index === 0 ) {
			$state_suffix = ! empty( $state ) ? ', ' . $state : '';
			$patterns[] = array(
				'pattern' => 'exact_match',
				'text' => $service_label . ' ' . ucfirst( $trade_keyword ) . ' Services in ' . $city_name . $state_suffix,
				'is_exact_match' => true,
			);
		}
		
		// Partial/semantic anchors with trade keywords
		$trade_noun = ( 'electrical' === $trade_keyword ) ? 'electricians' : $trade_keyword;
		
		$partial_patterns = array(
			array(
				'pattern' => 'trade_in_city',
				'text' => $trade_keyword . ' services in ' . $city_name,
				'is_exact_match' => false,
			),
			array(
				'pattern' => 'service_trade_city',
				'text' => strtolower( $service_label ) . ' ' . $trade_noun . ' in ' . $city_name,
				'is_exact_match' => false,
			),
			array(
				'pattern' => 'specific_service_city',
				'text' => $this->get_specific_service_anchor( $hub_key, $trade_keyword, $city_name, $index ),
				'is_exact_match' => false,
			),
			array(
				'pattern' => 'trade_help_city',
				'text' => $trade_keyword . ' help in ' . $city_name,
				'is_exact_match' => false,
			),
			array(
				'pattern' => 'city_trade',
				'text' => $city_name . ' ' . $trade_keyword . ' services',
				'is_exact_match' => false,
			),
		);
		
		$patterns = array_merge( $patterns, $partial_patterns );
		
		// Filter out used patterns
		$available = array_filter( $patterns, function( $p ) use ( $used_patterns ) {
			return ! in_array( $p['pattern'], $used_patterns, true );
		} );
		
		if ( empty( $available ) ) {
			$available = $partial_patterns; // Reset to partials only
		}
		
		// Select deterministically
		$available = array_values( $available );
		$hash = crc32( $hub_key . $city_name . $index );
		$selected = $available[ $hash % count( $available ) ];
		
		return $selected;
	}

	/**
	 * Get specific service anchor text based on hub type
	 * Examples: "panel upgrades in City", "GFCI installation in City"
	 */
	private function get_specific_service_anchor( $hub_key, $trade_keyword, $city_name, $index ) {
		$services = array(
			'residential' => array(
				'panel upgrades in ' . $city_name,
				'GFCI/AFCI safety upgrades in ' . $city_name,
				'home rewiring in ' . $city_name,
				'surge protection in ' . $city_name,
				'dedicated circuits in ' . $city_name,
			),
			'commercial' => array(
				'commercial ' . $trade_keyword . ' in ' . $city_name,
				'business ' . $trade_keyword . ' services in ' . $city_name,
				'facility ' . $trade_keyword . ' in ' . $city_name,
			),
			'emergency' => array(
				'emergency ' . $trade_keyword . ' in ' . $city_name,
				'24/7 ' . $trade_keyword . ' service in ' . $city_name,
				'urgent ' . $trade_keyword . ' repairs in ' . $city_name,
			),
		);
		
		$list = isset( $services[ $hub_key ] ) ? $services[ $hub_key ] : array( $trade_keyword . ' services in ' . $city_name );
		return $list[ $index % count( $list ) ];
	}

	/**
	 * Generate improved city sentence with:
	 * - Varied lead-in phrases
	 * - Hub-specific context (no "businesses" on residential)
	 * - Local relevance clause
	 */
	private function generate_improved_city_sentence( $city_name, $state, $anchor_text, $permalink, $hub_key, $trade_keyword, $index ) {
		// Build link
		$link = '<a href="' . esc_url( $permalink ) . '">' . esc_html( $anchor_text ) . '</a>';
		
		// Get lead-in phrase (varied, hub-specific)
		$lead_in = $this->get_varied_lead_in( $city_name, $hub_key, $index );
		
		// Get local relevance clause
		$relevance = $this->get_local_relevance_clause( $hub_key, $index );
		
		// Assemble sentence: lead-in + link + relevance
		return $lead_in . ' ' . $link . ' ' . $relevance . '.';
	}

	/**
	 * Get varied lead-in phrases (hub-specific, no "businesses" on residential)
	 */
	private function get_varied_lead_in( $city_name, $hub_key, $index ) {
		$lead_ins = array();
		
		if ( 'residential' === $hub_key ) {
			$lead_ins = array(
				'Homeowners in ' . $city_name . ' often call us for',
				'In ' . $city_name . ', we frequently help with',
				'If you\'re in ' . $city_name . ', our team can assist with',
				'Many homes in ' . $city_name . ' need',
				'Families in ' . $city_name . ' rely on us for',
				'Property owners in ' . $city_name . ' trust us for',
			);
		} elseif ( 'commercial' === $hub_key ) {
			$lead_ins = array(
				'Businesses in ' . $city_name . ' depend on us for',
				'Commercial properties in ' . $city_name . ' need',
				'In ' . $city_name . ', we provide',
				'Facility managers in ' . $city_name . ' choose us for',
				'Companies in ' . $city_name . ' rely on our',
				'Business owners in ' . $city_name . ' trust our',
			);
		} elseif ( 'emergency' === $hub_key ) {
			$lead_ins = array(
				'When emergencies strike in ' . $city_name . ', we offer',
				'Property owners in ' . $city_name . ' call us for',
				'In ' . $city_name . ', we respond quickly with',
				'For urgent issues in ' . $city_name . ', we provide',
				'Residents and businesses in ' . $city_name . ' trust our',
			);
		} else {
			$lead_ins = array(
				'In ' . $city_name . ', we provide',
				'Property owners in ' . $city_name . ' choose us for',
				'Our team serves ' . $city_name . ' with',
			);
		}
		
		return $lead_ins[ $index % count( $lead_ins ) ];
	}

	/**
	 * Get local relevance clause (why it matters)
	 * Topics: panel upgrades, GFCI/AFCI, rewiring, surge protection, dedicated circuits, safety inspections
	 */
	private function get_local_relevance_clause( $hub_key, $index ) {
		$clauses = array();
		
		if ( 'residential' === $hub_key ) {
			$clauses = array(
				'for panel upgrades and modern safety features',
				'when homes need GFCI and AFCI protection',
				'to update older home wiring to current code',
				'for surge protection and whole-home safety',
				'when adding dedicated circuits for appliances',
				'for comprehensive electrical safety inspections',
			);
		} elseif ( 'commercial' === $hub_key ) {
			$clauses = array(
				'to minimize downtime and maintain operations',
				'for code compliance and safety inspections',
				'when facilities need reliable power systems',
				'to upgrade aging commercial infrastructure',
				'for energy-efficient lighting and controls',
			);
		} elseif ( 'emergency' === $hub_key ) {
			$clauses = array(
				'with 24/7 rapid response',
				'to resolve urgent safety hazards',
				'when power outages threaten operations',
				'for immediate electrical repairs',
			);
		} else {
			$clauses = array(
				'for professional service and quality work',
				'when you need licensed expertise',
				'to ensure safety and code compliance',
			);
		}
		
		return $clauses[ $index % count( $clauses ) ];
	}
}
