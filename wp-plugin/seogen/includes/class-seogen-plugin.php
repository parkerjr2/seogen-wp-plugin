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

		if ( $limit < 1 ) {
			$limit = 6;
		}
		if ( $limit > 50 ) {
			$limit = 50;
		}

		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => $limit,
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

		$output = '<div class="seogen-service-hub-city-links">';
		$output .= '<ul>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$city_meta = get_post_meta( get_the_ID(), '_seogen_city', true );
			$link_text = ! empty( $city_meta ) ? esc_html( $city_meta ) : esc_html( get_the_title() );
			$output .= '<li><a href="' . esc_url( get_permalink() ) . '">' . $link_text . '</a></li>';
		}
		$output .= '</ul>';
		$output .= '</div>';
		wp_reset_postdata();

		return $output;
	}
}
