<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Plugin {
	public function run() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_head', array( $this, 'maybe_output_service_schema' ) );
		add_filter( 'body_class', array( $this, 'filter_body_class' ) );
		add_filter( 'the_title', array( $this, 'filter_the_title' ), 10, 2 );
		add_filter( 'wpseo_breadcrumb_output', array( $this, 'filter_yoast_breadcrumb_output' ), 10, 1 );
		add_filter( 'rank_math/frontend/breadcrumb/html', array( $this, 'filter_rankmath_breadcrumb_html' ), 10, 2 );

		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		$admin->register_bulk_worker_hooks();
		if ( is_admin() ) {
			$admin->run();
		}
	}

	public function filter_body_class( $classes ) {
		if ( is_singular( 'programmatic_page' ) ) {
			$classes[] = 'hyper-local-page';
		}
		return $classes;
	}

	public function filter_the_title( $title, $post_id ) {
		if ( is_admin() ) {
			return $title;
		}

		if ( ! is_singular( 'programmatic_page' ) ) {
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
		if ( is_singular( 'programmatic_page' ) ) {
			return '';
		}
		return $output;
	}

	public function filter_rankmath_breadcrumb_html( $html, $crumbs ) {
		if ( is_singular( 'programmatic_page' ) ) {
			return '';
		}
		return $html;
	}

	public function enqueue_frontend_assets() {
		if ( ! is_singular( 'programmatic_page' ) ) {
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
		if ( ! is_singular( 'programmatic_page' ) ) {
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
		if ( ! $post || 'programmatic_page' !== (string) $post->post_type ) {
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
			'name'                  => __( 'Programmatic Pages', 'seogen' ),
			'singular_name'         => __( 'Programmatic Page', 'seogen' ),
			'menu_name'             => __( 'Programmatic Pages', 'seogen' ),
			'name_admin_bar'        => __( 'Programmatic Page', 'seogen' ),
			'add_new'               => __( 'Add New', 'seogen' ),
			'add_new_item'          => __( 'Add New Programmatic Page', 'seogen' ),
			'new_item'              => __( 'New Programmatic Page', 'seogen' ),
			'edit_item'             => __( 'Edit Programmatic Page', 'seogen' ),
			'view_item'             => __( 'View Programmatic Page', 'seogen' ),
			'all_items'             => __( 'Programmatic Pages', 'seogen' ),
			'search_items'          => __( 'Search Programmatic Pages', 'seogen' ),
			'not_found'             => __( 'No programmatic pages found.', 'seogen' ),
			'not_found_in_trash'    => __( 'No programmatic pages found in Trash.', 'seogen' ),
			'filter_items_list'     => __( 'Filter programmatic pages list', 'seogen' ),
			'items_list_navigation' => __( 'Programmatic pages list navigation', 'seogen' ),
			'items_list'            => __( 'Programmatic pages list', 'seogen' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'show_in_menu'       => false,
			'has_archive'        => false,
			'capability_type'    => 'page',
			'map_meta_cap'       => true,
			'rewrite'            => array(
				'slug'       => 'service-area',
				'with_front' => false,
			),
			'supports'           => array( 'title', 'editor', 'revisions' ),
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-admin-page',
		);

		register_post_type( 'programmatic_page', $args );
	}
}
