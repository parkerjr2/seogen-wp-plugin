<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin-extensions.php';
require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin-helpers.php';
require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin-cities.php';

class SEOgen_Admin {
	use SEOgen_Admin_Extensions;
	use SEOgen_Admin_City_Hub_Helpers;
	use SEOgen_Admin_Cities;
	const OPTION_NAME = 'seogen_settings';
	const BUSINESS_CONFIG_OPTION = 'hyper_local_business_config';
	const SERVICES_CACHE_OPTION = 'hyper_local_services_cache';
	const LAST_PREVIEW_TRANSIENT_PREFIX = 'hyper_local_last_preview_';
	const BULK_JOB_OPTION_PREFIX = 'hyper_local_job_';
	const BULK_JOBS_INDEX_OPTION = 'hyper_local_jobs_index';
	const BULK_VALIDATE_TRANSIENT_PREFIX = 'hyper_local_bulk_validate_';
	const BULK_PROCESS_HOOK = 'hyper_local_process_job_batch';
	const API_BASE_URL = 'https://seogen-production.up.railway.app';

	public function run() {
		error_log( '[HyperLocal] SEOgen_Admin::run() called - registering admin-post handlers' );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_seogen_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_hyper_local_generate_preview', array( $this, 'handle_generate_preview' ) );
		add_action( 'admin_post_hyper_local_create_draft', array( $this, 'handle_create_draft' ) );
		add_action( 'admin_post_hyper_local_bulk_validate', array( $this, 'handle_bulk_validate' ) );
		add_action( 'admin_post_hyper_local_bulk_start', array( $this, 'handle_bulk_start' ) );
		error_log( '[HyperLocal] Registered admin_post_hyper_local_bulk_start handler' );
		add_action( 'admin_post_hyper_local_bulk_run_batch', array( $this, 'handle_bulk_run_batch' ) );
		add_action( 'admin_post_hyper_local_bulk_export', array( $this, 'handle_bulk_export' ) );
		add_action( 'wp_ajax_hyper_local_bulk_job_status', array( $this, 'ajax_bulk_job_status' ) );
		add_action( 'wp_ajax_hyper_local_bulk_job_cancel', array( $this, 'ajax_bulk_job_cancel' ) );
		add_action( 'admin_notices', array( $this, 'render_hl_notice' ) );
		add_action( 'admin_post_hyper_local_save_business_config', array( $this, 'handle_save_business_config' ) );
		add_action( 'admin_post_hyper_local_save_services', array( $this, 'handle_save_services' ) );
		add_action( 'admin_post_hyper_local_delete_service', array( $this, 'handle_delete_service' ) );
		add_action( 'admin_post_hyper_local_hub_preview', array( $this, 'handle_hub_preview' ) );
		add_action( 'admin_post_hyper_local_hub_create', array( $this, 'handle_hub_create' ) );
		add_action( 'admin_post_hyper_local_city_hub_preview', array( $this, 'handle_city_hub_preview' ) );
		add_action( 'admin_post_hyper_local_city_hub_create', array( $this, 'handle_city_hub_create' ) );
		add_action( 'admin_post_hyper_local_save_cities', array( $this, 'handle_save_cities' ) );
		add_action( 'admin_post_hyper_local_delete_city', array( $this, 'handle_delete_city' ) );
		
		// AJAX handlers for async city hub generation
		add_action( 'wp_ajax_seogen_start_city_hub_batch', array( $this, 'ajax_start_city_hub_batch' ) );
		add_action( 'wp_ajax_seogen_check_city_hub_progress', array( $this, 'ajax_check_city_hub_progress' ) );
		add_action( 'wp_ajax_seogen_process_city_hub_item', array( $this, 'ajax_process_city_hub_item' ) );
		
		// Ensure bulk actions work for service_page post type
		add_filter( 'bulk_actions-edit-service_page', array( $this, 'add_bulk_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'handle_bulk_actions-edit-service_page', array( $this, 'handle_select_all_bulk_action' ), 10, 3 );
	}

	public function register_bulk_worker_hooks() {
		add_action( self::BULK_PROCESS_HOOK, array( $this, 'process_bulk_job' ), 10, 1 );
		add_action( 'hyper_local_bulk_process_job', array( $this, 'process_bulk_job' ), 10, 1 );
		add_action( 'hyper_local_bulk_process_job_action', array( $this, 'process_bulk_job' ), 10, 1 );
	}

	public function register_frontend_hooks() {
		// Force template for service_page posts (including drafts) when header/footer is disabled
		add_filter( 'template_include', array( $this, 'force_service_page_template' ), 99 );
		
		// Add body class and CSS/JS to hide header/footer for service pages
		add_filter( 'body_class', array( $this, 'add_service_page_body_class' ) );
		add_action( 'wp_head', array( $this, 'add_service_page_styles' ), 999 );
		add_action( 'wp_footer', array( $this, 'add_service_page_styles_footer' ), 999 );
	}

	private function get_last_preview_transient_key( $user_id ) {
		return self::LAST_PREVIEW_TRANSIENT_PREFIX . (int) $user_id;
	}

	public function render_hl_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['hl_notice'] ) ) {
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['hl_notice'] ) );
		$msg    = '';
		if ( isset( $_GET['hl_msg'] ) ) {
			$msg = rawurldecode( (string) wp_unslash( $_GET['hl_msg'] ) );
			$msg = sanitize_text_field( $msg );
		}

		if ( '' === $msg ) {
			return;
		}

		if ( 'created' === $notice ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private function render_generate_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['hl_gen'] ) ) {
			return;
		}

		$gen = sanitize_text_field( wp_unslash( $_GET['hl_gen'] ) );
		$msg = '';
		if ( isset( $_GET['hl_msg'] ) ) {
			$msg = rawurldecode( (string) wp_unslash( $_GET['hl_msg'] ) );
			$msg = sanitize_text_field( $msg );
		}

		if ( 'success' === $gen ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private function mask_license_key( $license_key ) {
		$license_key = (string) $license_key;
		$license_key = trim( $license_key );

		if ( '' === $license_key ) {
			return '';
		}

		if ( strlen( $license_key ) <= 10 ) {
			return substr( $license_key, 0, 3 ) . '...';
		}

		return substr( $license_key, 0, 5 ) . '...' . substr( $license_key, -3 );
	}

	private function build_generate_preview_payload( $settings, $data ) {
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		return array(
			'license_key' => $license_key,
			'data'        => array(
				'service'      => isset( $data['service'] ) ? (string) $data['service'] : '',
				'city'         => isset( $data['city'] ) ? (string) $data['city'] : '',
				'state'        => isset( $data['state'] ) ? (string) $data['state'] : '',
				'company_name' => isset( $data['company_name'] ) ? (string) $data['company_name'] : '',
				'phone'        => isset( $data['phone'] ) ? (string) $data['phone'] : '',
				'address'      => isset( $data['address'] ) ? (string) $data['address'] : '',
			),
		);
	}

	public function render_field_design_preset() {
		$settings = $this->get_settings();
		$current = isset( $settings['design_preset'] ) ? sanitize_key( (string) $settings['design_preset'] ) : 'theme_default';
		if ( '' === $current ) {
			$current = 'theme_default';
		}

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[design_preset]">';
		echo '<option value="theme_default"' . selected( $current, 'theme_default', false ) . '>' . esc_html__( 'Theme Default (no plugin CSS)', 'seogen' ) . '</option>';
		echo '<option value="clean_card"' . selected( $current, 'clean_card', false ) . '>' . esc_html__( 'Clean Card', 'seogen' ) . '</option>';
		echo '<option value="bold_sections"' . selected( $current, 'bold_sections', false ) . '>' . esc_html__( 'Bold Sections', 'seogen' ) . '</option>';
		echo '</select>';
	}

	public function render_field_show_h1_in_content() {
		$settings = $this->get_settings();
		$current = ! empty( $settings['show_h1_in_content'] );
		printf(
			'<label><input type="checkbox" name="%1$s[show_h1_in_content]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $current, true, false ),
			esc_html__( 'Enable if your theme does NOT show the post title automatically.', 'seogen' )
		);
	}

	public function render_field_hero_style() {
		$settings = $this->get_settings();
		$current = isset( $settings['hero_style'] ) ? sanitize_key( (string) $settings['hero_style'] ) : 'minimal';
		if ( '' === $current ) {
			$current = 'minimal';
		}

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[hero_style]">';
		echo '<option value="minimal"' . selected( $current, 'minimal', false ) . '>' . esc_html__( 'Minimal', 'seogen' ) . '</option>';
		echo '<option value="banner"' . selected( $current, 'banner', false ) . '>' . esc_html__( 'Banner', 'seogen' ) . '</option>';
		echo '</select>';
	}

	public function render_field_cta_style() {
		$settings = $this->get_settings();
		$current = isset( $settings['cta_style'] ) ? sanitize_key( (string) $settings['cta_style'] ) : 'button_only';
		if ( '' === $current ) {
			$current = 'button_only';
		}

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[cta_style]">';
		echo '<option value="button_only"' . selected( $current, 'button_only', false ) . '>' . esc_html__( 'Button Only', 'seogen' ) . '</option>';
		echo '<option value="button_and_phone"' . selected( $current, 'button_and_phone', false ) . '>' . esc_html__( 'Button and Phone', 'seogen' ) . '</option>';
		echo '</select>';
	}

	public function render_field_enable_mobile_sticky_cta() {
		$settings = $this->get_settings();
		$current = ! empty( $settings['enable_mobile_sticky_cta'] );
		printf(
			'<label><input type="checkbox" name="%1$s[enable_mobile_sticky_cta]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $current, true, false ),
			esc_html__( 'Enable a sticky Call Now bar on mobile (<= 768px).', 'seogen' )
		);
	}

	public function render_field_header_template() {
		$settings = $this->get_settings();
		$current_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		
		$templates = $this->get_available_templates();

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[header_template_id]" id="seogen_header_template_id">';
		echo '<option value="0">' . esc_html__( '-- None --', 'seogen' ) . '</option>';
		
		if ( empty( $templates ) ) {
			echo '<option value="0" disabled>' . esc_html__( 'No templates found', 'seogen' ) . '</option>';
		} else {
			foreach ( $templates as $template ) {
				$type_label = '';
				if ( 'elementor_library' === $template->post_type ) {
					$type_label = ' [Elementor]';
				} elseif ( 'wp_block' === $template->post_type ) {
					$type_label = ' [Block]';
				}
				printf(
					'<option value="%d" %s>%s%s</option>',
					$template->ID,
					selected( $current_id, $template->ID, false ),
					esc_html( $template->post_title ),
					esc_html( $type_label )
				);
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select an Elementor template or reusable block to prepend to all generated pages.', 'seogen' ) . '</p>';
	}

	public function render_field_footer_template() {
		$settings = $this->get_settings();
		$current_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		
		$templates = $this->get_available_templates();

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[footer_template_id]" id="seogen_footer_template_id">';
		echo '<option value="0">' . esc_html__( '-- None --', 'seogen' ) . '</option>';
		
		if ( empty( $templates ) ) {
			echo '<option value="0" disabled>' . esc_html__( 'No templates found', 'seogen' ) . '</option>';
		} else {
			foreach ( $templates as $template ) {
				$type_label = '';
				if ( 'elementor_library' === $template->post_type ) {
					$type_label = ' [Elementor]';
				} elseif ( 'wp_block' === $template->post_type ) {
					$type_label = ' [Block]';
				}
				printf(
					'<option value="%d" %s>%s%s</option>',
					$template->ID,
					selected( $current_id, $template->ID, false ),
					esc_html( $template->post_title ),
					esc_html( $type_label )
				);
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select an Elementor template or reusable block to append to all generated pages.', 'seogen' ) . '</p>';
	}

	public function render_field_disable_theme_header_footer() {
		$settings = $this->get_settings();
		$current = ! empty( $settings['disable_theme_header_footer'] );
		printf(
			'<label><input type="checkbox" name="%1$s[disable_theme_header_footer]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $current, true, false ),
			esc_html__( 'Remove default theme header and footer from generated pages (uses your Header/Footer templates only).', 'seogen' )
		);
		echo '<p class="description">' . esc_html__( 'Works with Elementor, Gutenberg, Divi, and other page builders. Automatically detects your page builder and applies the appropriate settings.', 'seogen' ) . '</p>';
	}

	private function get_available_templates() {
		// Check if Elementor is active
		$has_elementor = class_exists( '\Elementor\Plugin' );
		
		$templates = array();
		
		if ( $has_elementor ) {
			// Get Elementor templates (elementor_library post type)
			$elementor_templates = get_posts( array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_elementor_template_type',
						'value'   => array( 'section', 'page' ),
						'compare' => 'IN',
					),
				),
			) );
			$templates = array_merge( $templates, $elementor_templates );
		}
		
		// Also get WordPress reusable blocks
		$reusable_blocks = get_posts( array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		) );
		$templates = array_merge( $templates, $reusable_blocks );

		return $templates;
	}

	// Removed: render_field_primary_cta_label() - now using Business Setup CTA text

	public function build_gutenberg_content_from_blocks( array $blocks, $page_mode = '' ) {
		// Infer page_mode if empty (fallback for legacy call sites)
		if ( '' === $page_mode ) {
			foreach ( $blocks as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}
				if ( isset( $block['type'] ) && 'cta' === $block['type'] && isset( $block['text'] ) ) {
					$text = (string) $block['text'];
					if ( preg_match( '/\b(in|near)\s+[A-Z][a-z]+/i', $text ) ) {
						$page_mode = 'service_city';
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'SEOgen: Inferred page_mode=service_city from CTA block content' );
						}
						break;
					}
				}
			}
		}
		
		$settings = $this->get_settings();
		$show_h1_in_content = ! empty( $settings['show_h1_in_content'] );
		$design_preset = isset( $settings['design_preset'] ) ? sanitize_key( (string) $settings['design_preset'] ) : 'theme_default';
		if ( '' === $design_preset ) {
			$design_preset = 'theme_default';
		}
		$preset_class = '';
		if ( 'clean_card' === $design_preset ) {
			$preset_class = ' hyper-local-preset-clean-card';
		} elseif ( 'bold_sections' === $design_preset ) {
			$preset_class = ' hyper-local-preset-bold-sections';
		}

		$hero_style = isset( $settings['hero_style'] ) ? sanitize_key( (string) $settings['hero_style'] ) : 'minimal';
		if ( '' === $hero_style ) {
			$hero_style = 'minimal';
		}
		$cta_style = isset( $settings['cta_style'] ) ? sanitize_key( (string) $settings['cta_style'] ) : 'button_only';
		if ( '' === $cta_style ) {
			$cta_style = 'button_only';
		}
		
		// Get CTA text from Business Setup config instead of Settings
		$config = get_option( 'seogen_business_config', array() );
		$primary_cta_label = isset( $config['cta_text'] ) ? (string) $config['cta_text'] : 'Request a Free Estimate';
		$primary_cta_label = trim( $primary_cta_label );
		if ( '' === $primary_cta_label ) {
			$primary_cta_label = 'Request a Free Estimate';
		}

		$output = array();
		$output[] = '<!-- wp:group {"className":"hyper-local-content' . $preset_class . '"} -->';
		$output[] = '<div class="wp-block-group hyper-local-content' . esc_attr( $preset_class ) . '">';
		$faq_heading_added = false;
		$hero_emitted = false;
		$body_group_open = false;
		$scannable_headings_added = false;
		$section_heading_state = 0;
		$issues_list_added = false;
		$city_hub_link_inserted = false;
		$has_faq_blocks = false;
		$has_cta_blocks = false;
		
		// Pre-scan blocks to detect FAQ and CTA presence for fallback insertion
		foreach ( $blocks as $block ) {
			if ( is_array( $block ) && isset( $block['type'] ) ) {
				if ( 'faq' === $block['type'] ) {
					$has_faq_blocks = true;
				}
				if ( 'cta' === $block['type'] ) {
					$has_cta_blocks = true;
				}
			}
		}

		$details_available = class_exists( 'WP_Block_Type_Registry' ) && WP_Block_Type_Registry::get_instance()->is_registered( 'core/details' );

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$last_preview_key = $this->get_last_preview_transient_key( $user_id );
			$preview = get_transient( $last_preview_key );
			if ( is_array( $preview ) && isset( $preview['inputs'] ) && is_array( $preview['inputs'] ) ) {
				$inputs = $preview['inputs'];
				if ( isset( $inputs['city'] ) ) {
					$context_city = trim( (string) $inputs['city'] );
				}
				if ( isset( $inputs['state'] ) ) {
					$context_state = trim( (string) $inputs['state'] );
				}
				if ( isset( $inputs['company_name'] ) ) {
					$context_business = trim( (string) $inputs['company_name'] );
				}
				if ( isset( $inputs['service'] ) ) {
					$context_service = trim( (string) $inputs['service'] );
				}
				if ( isset( $inputs['phone'] ) ) {
					$context_phone = trim( (string) $inputs['phone'] );
				}
			}
		}

		$infer_context_from_title = function ( $title ) use ( &$context_city, &$context_state, &$context_business ) {
			$title = (string) $title;
			$title = trim( $title );
			if ( '' === $title ) {
				return;
			}

			if ( '' === $context_business ) {
				$parts = array_map( 'trim', explode( '|', $title ) );
				if ( count( $parts ) > 1 && '' !== $parts[1] ) {
					$context_business = $parts[1];
				}
			}

			if ( '' === $context_city || '' === $context_state ) {
				$guess = $title;
				if ( false !== strpos( $guess, '|' ) ) {
					$guess_parts = array_map( 'trim', explode( '|', $guess ) );
					$guess = $guess_parts[0];
				}
				if ( preg_match( '/\b([A-Za-z\s\.-]+),\s*([A-Za-z]{2})\b/', $guess, $m ) ) {
					if ( '' === $context_city ) {
						$context_city = trim( (string) $m[1] );
					}
					if ( '' === $context_state ) {
						$context_state = trim( (string) $m[2] );
					}
				}
			}
		};

		$add_h2 = function ( $text ) use ( &$output ) {
			$output[] = '<!-- wp:heading {"level":2} -->';
			$output[] = '<h2>' . esc_html( (string) $text ) . '</h2>';
			$output[] = '<!-- /wp:heading -->';
		};

		$close_body_group_if_open = function () use ( &$output, &$body_group_open ) {
			if ( $body_group_open ) {
				$output[] = '</div>';
				$output[] = '<!-- /wp:group -->';
				$body_group_open = false;
			}
		};

		$open_body_group_if_needed = function () use ( &$output, &$body_group_open ) {
			if ( ! $body_group_open ) {
				$output[] = '<!-- wp:group {"className":"hyper-local-body"} -->';
				$output[] = '<div class="wp-block-group hyper-local-body">';
				$body_group_open = true;
			}
		};

		$add_separator = function () use ( &$output ) {
			$output[] = '<!-- wp:separator -->';
			$output[] = '<hr class="wp-block-separator has-alpha-channel-opacity"/>';
			$output[] = '<!-- /wp:separator -->';
		};

		$emit_hero_if_ready = function ( $force = false ) use ( &$output, &$hero_heading_text, &$hero_paragraph_text, &$hero_emitted, $open_body_group_if_needed, $show_h1_in_content, $cta_style, $primary_cta_label, &$context_business, &$context_phone, &$context_city, &$context_state, &$context_service ) {
			if ( $hero_emitted ) {
				return;
			}

			if ( null === $hero_heading_text ) {
				$service = ( '' !== $context_service ) ? $context_service : esc_html__( 'Roof Repair', 'seogen' );
				$city = ( '' !== $context_city ) ? $context_city : __( 'Your City', 'seogen' );
				$state = ( '' !== $context_state ) ? $context_state : __( 'Your State', 'seogen' );
				$hero_heading_text = esc_html( sprintf( __( '%1$s in %2$s, %3$s', 'seogen' ), $service, $city, $state ) );
			}

			if ( ! $force && null === $hero_paragraph_text ) {
				return;
			}

			$tel_digits = preg_replace( '/\D+/', '', (string) $context_phone );
			$tel_url = ( '' !== $tel_digits ) ? ( 'tel:' . $tel_digits ) : '';

			$output[] = '<!-- wp:group {"className":"hyper-local-hero","align":"wide"} -->';
			$output[] = '<div class="wp-block-group alignwide hyper-local-hero">';

			$output[] = '<!-- wp:heading {"level":1} -->';
			$output[] = '<h1>' . $hero_heading_text . '</h1>';
			$output[] = '<!-- /wp:heading -->';

			if ( null !== $hero_paragraph_text && '' !== $hero_paragraph_text ) {
				$output[] = '<!-- wp:paragraph {"className":"hyper-local-lead"} -->';
				$output[] = '<p class="hyper-local-lead">' . $hero_paragraph_text . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
			}

			if ( '' !== $tel_url ) {
				$output[] = '<!-- wp:buttons -->';
				$output[] = '<div class="wp-block-buttons">';
				$output[] = '<!-- wp:button ' . wp_json_encode( array( 'url' => $tel_url ) ) . ' -->';
				$output[] = '<div class="wp-block-button"><a class="wp-block-button__link" href="' . esc_url( $tel_url ) . '">' . esc_html( $primary_cta_label ) . '</a></div>';
				$output[] = '<!-- /wp:button -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:buttons -->';
			}

			if ( 'button_and_phone' === $cta_style && '' !== $tel_digits && '' !== $context_business ) {
				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . esc_html( sprintf( __( 'Call %1$s at %2$s', 'seogen' ), $context_business, $context_phone ) ) . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
			}

			$output[] = '</div>';
			$output[] = '<!-- /wp:group -->';
			$hero_emitted = true;

			$open_body_group_if_needed();
		};

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}

			$type = (string) $block['type'];

			if ( 'heading' === $type ) {
				$level = isset( $block['level'] ) ? (int) $block['level'] : 2;
				if ( $level < 1 ) {
					$level = 1;
				}
				if ( $level > 6 ) {
					$level = 6;
				}

				$text_raw = isset( $block['text'] ) ? (string) $block['text'] : '';
				$infer_context_from_title( $text_raw );
				$text = esc_html( $text_raw );

				if ( 1 === $level && ! $hero_emitted && null === $hero_heading_text ) {
					$hero_heading_text = $text;
					continue;
				}

				$emit_hero_if_ready( true );
				$open_body_group_if_needed();

				if ( 1 === $level ) {
					$level = 2;
				}
				$output[] = '<!-- wp:heading {"level":' . $level . '} -->';
				$output[] = '<h' . $level . '>' . $text . '</h' . $level . '>';
				$output[] = '<!-- /wp:heading -->';
				continue;
			}

			if ( 'paragraph' === $type ) {
				$text_raw = isset( $block['text'] ) ? (string) $block['text'] : '';
				
				// CRITICAL FIX: If paragraph ends with a service links shortcode,
				// output it as a separate HTML block to avoid invalid <p><div>...</div></p> nesting
				$trimmed_text = trim( $text_raw );
				if ( preg_match( '/\[seogen_(?:city_hub_links|city_service_links)[^\]]*\]\s*$/i', $trimmed_text ) ) {
					// Paragraph ends with a service links shortcode
					// Split into text before shortcode + shortcode itself
					if ( preg_match( '/^(.*?)(\[seogen_(?:city_hub_links|city_service_links)[^\]]*\])\s*$/is', $trimmed_text, $matches ) ) {
						$text_before = trim( $matches[1] );
						$shortcode = $matches[2];
						
						$emit_hero_if_ready( true );
						$open_body_group_if_needed();
						
						// Output text before shortcode as paragraph (if any)
						if ( '' !== $text_before ) {
							$output[] = '<!-- wp:paragraph -->';
							$output[] = '<p>' . esc_html( $text_before ) . '</p>';
							$output[] = '<!-- /wp:paragraph -->';
						}
						
						// Output shortcode as separate HTML block (not wrapped in <p>)
						// This prevents invalid <p><div>...</div></p> nesting
						$output[] = '<!-- wp:html -->';
						$output[] = $shortcode;
						$output[] = '<!-- /wp:html -->';
						continue;
					}
				}
				
				// Check if text contains shortcodes - if so, preserve them
				if ( preg_match( '/\[seogen_[^\]]+\]/', $text_raw ) ) {
					// Text contains shortcodes - escape everything except shortcodes
					$text = wp_kses_post( $text_raw );
				} else {
					// No shortcodes - escape normally
					$text = esc_html( $text_raw );
				}

				if ( ! $hero_emitted && null !== $hero_heading_text && null === $hero_paragraph_text ) {
					$hero_paragraph_text = $text;
					$emit_hero_if_ready( true );
					continue;
				}

				$emit_hero_if_ready( true );
				$open_body_group_if_needed();

				if ( $hero_emitted ) {
					$paragraphs_seen_after_hero++;
				}

				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $text . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				continue;
			}

			if ( 'faq' === $type ) {
				$emit_hero_if_ready( true );
				$close_body_group_if_open();

				if ( ! $faq_heading_added ) {
					$add_separator();
				
				// Add city hub link shortcode before FAQ section (service+city pages only)
				if ( 'service_city' === $page_mode && ! $city_hub_link_inserted ) {
					$output[] = '<!-- wp:shortcode -->';
					$output[] = '[seogen_city_hub_link]';
					$output[] = '<!-- /wp:shortcode -->';
					$output[] = '';
					$city_hub_link_inserted = true;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$output[] = '<!-- seogen_debug: inserted city hub link shortcode before FAQ (page_mode=service_city) -->';
					}
				}
				
				$output[] = '<!-- wp:heading {"level":2} -->';
				$output[] = '<h2>' . esc_html__( 'FAQ', 'seogen' ) . '</h2>';
				$output[] = '<!-- /wp:heading -->';
				$faq_heading_added = true;
			}

				$question = isset( $block['question'] ) ? esc_html( (string) $block['question'] ) : '';
				$answer   = isset( $block['answer'] ) ? esc_html( (string) $block['answer'] ) : '';

				if ( $details_available ) {
					$output[] = '<!-- wp:details -->';
					$output[] = '<details class="wp-block-details"><summary>' . $question . '</summary>';
					$output[] = '<!-- wp:paragraph -->';
					$output[] = '<p>' . $answer . '</p>';
					$output[] = '<!-- /wp:paragraph -->';
					$output[] = '</details>';
					$output[] = '<!-- /wp:details -->';
				} else {
					$output[] = '<!-- wp:heading {"level":3} -->';
					$output[] = '<h3>' . $question . '</h3>';
					$output[] = '<!-- /wp:heading -->';
					$output[] = '<!-- wp:paragraph -->';
					$output[] = '<p>' . $answer . '</p>';
					$output[] = '<!-- /wp:paragraph -->';
				}
				continue;
			}

			if ( 'nap' === $type ) {
				$emit_hero_if_ready( true );
				$close_body_group_if_open();
				$add_separator();

				$business_name_raw = isset( $block['business_name'] ) ? (string) $block['business_name'] : '';
				$business_name = esc_html( $business_name_raw );
				$phone_raw     = isset( $block['phone'] ) ? (string) $block['phone'] : '';
				$phone         = esc_html( $phone_raw );
				$email_raw     = isset( $block['email'] ) ? (string) $block['email'] : '';
				$email         = esc_html( $email_raw );
				$address       = isset( $block['address'] ) ? esc_html( (string) $block['address'] ) : '';
				
				$last_phone    = $phone_raw;
				if ( '' !== trim( $business_name_raw ) && '' === $context_business ) {
					$context_business = trim( $business_name_raw );
				}
				if ( '' !== trim( $last_phone ) && '' === $context_phone ) {
					$context_phone = trim( $last_phone );
				}

				// Only show fields that have values
				$has_phone = '' !== trim( $phone );
				$has_email = '' !== trim( $email );

				// Prepare clickable links
				$tel_digits = preg_replace( '/\D+/', '', $phone_raw );
				$tel_url = ( '' !== $tel_digits ) ? 'tel:' . $tel_digits : '';
				$mailto_url = ( '' !== trim( $email_raw ) ) ? 'mailto:' . $email_raw : '';

				$output[] = '<!-- wp:heading {"level":2} -->';
				$output[] = '<h2>' . esc_html__( 'Ready to Get Started?', 'seogen' ) . '</h2>';
				$output[] = '<!-- /wp:heading -->';
				$output[] = '<!-- wp:group {"className":"hyper-local-contact-cards"} -->';
				$output[] = '<div class="wp-block-group hyper-local-contact-cards">';
				$output[] = '<!-- wp:columns -->';
				$output[] = '<div class="wp-block-columns">';

				if ( $has_phone ) {
					$output[] = '<!-- wp:column -->';
					$output[] = '<div class="wp-block-column">';
					$output[] = '<!-- wp:group {"className":"hyper-local-contact-card"} -->';
					$output[] = '<div class="wp-block-group hyper-local-contact-card">';
					$output[] = '<!-- wp:paragraph {"className":"contact-card-icon"} -->';
					$output[] = '<p class="contact-card-icon">📞</p>';
					$output[] = '<!-- /wp:paragraph -->';
					$output[] = '<!-- wp:heading {"level":3} -->';
					$output[] = '<h3>' . esc_html__( 'Call Us', 'seogen' ) . '</h3>';
					$output[] = '<!-- /wp:heading -->';
					if ( '' !== $tel_url ) {
						$output[] = '<!-- wp:paragraph {"className":"contact-card-link"} -->';
						$output[] = '<p class="contact-card-link"><a href="' . esc_url( $tel_url ) . '">' . $phone . '</a></p>';
						$output[] = '<!-- /wp:paragraph -->';
					} else {
						$output[] = '<!-- wp:paragraph -->';
						$output[] = '<p>' . $phone . '</p>';
						$output[] = '<!-- /wp:paragraph -->';
					}
					$output[] = '<!-- wp:paragraph {"className":"contact-card-label"} -->';
					$output[] = '<p class="contact-card-label">' . esc_html__( 'Tap to call', 'seogen' ) . '</p>';
					$output[] = '<!-- /wp:paragraph -->';
					$output[] = '</div>';
					$output[] = '<!-- /wp:group -->';
					$output[] = '</div>';
					$output[] = '<!-- /wp:column -->';
				}

				if ( $has_email ) {
					$output[] = '<!-- wp:column -->';
					$output[] = '<div class="wp-block-column">';
					$output[] = '<!-- wp:group {"className":"hyper-local-contact-card"} -->';
					$output[] = '<div class="wp-block-group hyper-local-contact-card">';
					$output[] = '<!-- wp:paragraph {"className":"contact-card-icon"} -->';
					$output[] = '<p class="contact-card-icon">✉️</p>';
					$output[] = '<!-- /wp:paragraph -->';
					$output[] = '<!-- wp:heading {"level":3} -->';
					$output[] = '<h3>' . esc_html__( 'Email Us', 'seogen' ) . '</h3>';
					$output[] = '<!-- /wp:heading -->';
					if ( '' !== $mailto_url ) {
						$output[] = '<!-- wp:paragraph {"className":"contact-card-link"} -->';
						$output[] = '<p class="contact-card-link"><a href="' . esc_url( $mailto_url ) . '">' . $email . '</a></p>';
						$output[] = '<!-- /wp:paragraph -->';
					} else {
						$output[] = '<!-- wp:paragraph -->';
						$output[] = '<p>' . $email . '</p>';
						$output[] = '<!-- /wp:paragraph -->';
					}
					$output[] = '<!-- wp:paragraph {"className":"contact-card-label"} -->';
					$output[] = '<p class="contact-card-label">' . esc_html__( 'Send a message', 'seogen' ) . '</p>';
					$output[] = '<!-- /wp:paragraph -->';
					$output[] = '</div>';
					$output[] = '<!-- /wp:group -->';
					$output[] = '</div>';
					$output[] = '<!-- /wp:column -->';
				}

				$output[] = '</div>';
				$output[] = '<!-- /wp:columns -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:group -->';
				continue;
			}

			if ( 'cta' === $type ) {
				$emit_hero_if_ready( true );
				$close_body_group_if_open();
				$add_separator();
				
				// Fallback: Insert city hub link before CTA if no FAQ exists
				if ( 'service_city' === $page_mode && ! $city_hub_link_inserted && ! $has_faq_blocks ) {
					$output[] = '<!-- wp:shortcode -->';
					$output[] = '[seogen_city_hub_link]';
					$output[] = '<!-- /wp:shortcode -->';
					$output[] = '';
					$city_hub_link_inserted = true;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						$output[] = '<!-- seogen_debug: inserted city hub link shortcode before CTA (fallback, no FAQ found, page_mode=service_city) -->';
					}
				}
				
				if ( '' === $context_city || '' === $context_state || '' === $context_business ) {
					$infer_context_from_title( (string) $hero_heading_text );
				}

				$text = isset( $block['text'] ) ? esc_html( (string) $block['text'] ) : '';
				
				// Use phone from CTA block itself, fallback to last_phone or context_phone
				$cta_phone = '';
				if ( isset( $block['phone'] ) && '' !== trim( (string) $block['phone'] ) ) {
					$cta_phone = trim( (string) $block['phone'] );
				} elseif ( '' !== $last_phone ) {
					$cta_phone = $last_phone;
				} elseif ( '' !== $context_phone ) {
					$cta_phone = $context_phone;
				}
				
				$tel_digits = preg_replace( '/\D+/', '', $cta_phone );
				$tel_url = '';
				if ( '' !== $tel_digits ) {
					$tel_url = 'tel:' . $tel_digits;
				}

				$output[] = '<!-- wp:group {"className":"hyper-local-cta-section"} -->';
				$output[] = '<div class="wp-block-group hyper-local-cta-section">';
				
				$output[] = '<!-- wp:group {"className":"cta-content"} -->';
				$output[] = '<div class="wp-block-group cta-content">';
				
				$output[] = '<!-- wp:heading {"level":2,"textAlign":"center","className":"cta-heading"} -->';
				$output[] = '<h2 class="cta-heading has-text-align-center">' . esc_html__( 'Get Your Free Quote Today', 'seogen' ) . '</h2>';
				$output[] = '<!-- /wp:heading -->';
				
				$output[] = '<!-- wp:paragraph {"align":"center"} -->';
				$output[] = '<p class="has-text-align-center">' . $text . '</p>';
				$output[] = '<!-- /wp:paragraph -->';

				$output[] = '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->';
				$output[] = '<div class="wp-block-buttons">';
				$output[] = '<!-- wp:button {"className":"is-style-fill"} -->';
				$output[] = '<div class="wp-block-button is-style-fill"><a class="wp-block-button__link" href="' . esc_url( $tel_url ) . '">' . esc_html__( 'Call Now for Free Quote', 'seogen' ) . '</a></div>';
				$output[] = '<!-- /wp:button -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:buttons -->';
				
				$output[] = '<!-- wp:paragraph {"align":"center","className":"cta-trust-signals"} -->';
				$output[] = '<p class="cta-trust-signals has-text-align-center">✓ ' . esc_html__( 'Licensed & Insured', 'seogen' ) . ' &nbsp;&nbsp; ✓ ' . esc_html__( 'Fast Response', 'seogen' ) . ' &nbsp;&nbsp; ✓ ' . esc_html__( 'Quality Guaranteed', 'seogen' ) . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				
				$output[] = '</div>';
				$output[] = '<!-- /wp:group -->';
				
				$output[] = '</div>';
				$output[] = '<!-- /wp:group -->';
				continue;
			}
		}

		$emit_hero_if_ready( true );
		$close_body_group_if_open();
		$output[] = '</div>';
		$output[] = '<!-- /wp:group -->';

		return implode( "\n", $output );
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

	private function parse_service_from_title( $title ) {
		$title = sanitize_text_field( wp_strip_all_tags( (string) $title ) );
		$title = trim( $title );
		if ( '' === $title ) {
			return '';
		}

		if ( false !== strpos( $title, '|' ) ) {
			$parts = array_map( 'trim', explode( '|', $title ) );
			if ( isset( $parts[0] ) && '' !== $parts[0] ) {
				$title = $parts[0];
			}
		}

		if ( preg_match( '/^(.+?)\s+in\s+[A-Za-z\s\.-]+,\s*[A-Za-z]{2}\b/', $title, $m ) ) {
			return trim( (string) $m[1] );
		}

		if ( preg_match( '/^(.+?)\s+in\s+.+$/', $title, $m ) ) {
			return trim( (string) $m[1] );
		}

		return '';
	}

	private function apply_yoast_meta( $post_id, $service, $ai_title, $ai_meta_description ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( ! $this->is_yoast_active() ) {
			return;
		}

		$service = sanitize_text_field( wp_strip_all_tags( (string) $service ) );
		$service = trim( $service );
		$ai_title = sanitize_text_field( wp_strip_all_tags( (string) $ai_title ) );
		$ai_title = trim( $ai_title );
		$ai_meta_description = sanitize_text_field( wp_strip_all_tags( (string) $ai_meta_description ) );
		$ai_meta_description = trim( $ai_meta_description );

		if ( '' !== $service ) {
			update_post_meta( $post_id, '_yoast_wpseo_focuskw', $service );
		}
		if ( '' !== $ai_title ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', $ai_title );
		}
		if ( '' !== $ai_meta_description ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $ai_meta_description );
		}
	}

	private function apply_rankmath_meta( $post_id, $service, $ai_title, $ai_meta_description, $force = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( ! $this->is_rankmath_active() ) {
			return;
		}

		$service = sanitize_text_field( wp_strip_all_tags( (string) $service ) );
		$service = trim( $service );
		$ai_title = sanitize_text_field( wp_strip_all_tags( (string) $ai_title ) );
		$ai_title = trim( $ai_title );
		$ai_meta_description = sanitize_text_field( wp_strip_all_tags( (string) $ai_meta_description ) );
		$ai_meta_description = trim( $ai_meta_description );

		if ( '' !== $service ) {
			$current = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
			if ( $force || '' === (string) $current ) {
				update_post_meta( $post_id, 'rank_math_focus_keyword', $service );
			}
		}
		if ( '' !== $ai_title ) {
			$current = get_post_meta( $post_id, 'rank_math_title', true );
			if ( $force || '' === (string) $current ) {
				update_post_meta( $post_id, 'rank_math_title', $ai_title );
			}
		}
		if ( '' !== $ai_meta_description ) {
			$current = get_post_meta( $post_id, 'rank_math_description', true );
			if ( $force || '' === (string) $current ) {
				update_post_meta( $post_id, 'rank_math_description', $ai_meta_description );
			}
		}
	}

	private function apply_seo_plugin_meta( $post_id, $service, $ai_title, $ai_meta_description, $force = false ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		$service = sanitize_text_field( wp_strip_all_tags( (string) $service ) );
		$service = trim( $service );
		$ai_title = sanitize_text_field( wp_strip_all_tags( (string) $ai_title ) );
		$ai_title = trim( $ai_title );
		$ai_meta_description = sanitize_text_field( wp_strip_all_tags( (string) $ai_meta_description ) );
		$ai_meta_description = trim( $ai_meta_description );

		if ( '' === $service && '' === $ai_title && '' === $ai_meta_description ) {
			return;
		}

		if ( $this->is_yoast_active() ) {
			$this->apply_yoast_meta( $post_id, $service, $ai_title, $ai_meta_description );
			return;
		}

		if ( $this->is_rankmath_active() ) {
			$this->apply_rankmath_meta( $post_id, $service, $ai_title, $ai_meta_description, $force );
		}
	}

	public function handle_generate_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}

		check_admin_referer( 'hyper_local_generate_preview', 'hyper_local_generate_preview_nonce' );

		$settings    = $this->get_settings();
		$api_url     = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		$redirect_url = admin_url( 'admin.php?page=hyper-local-generate' );

		if ( '' === $api_url ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'API Base URL is missing. Please set it on the Hyper Local Settings page.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( '' === $license_key ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'License Key is missing. Please set it on the Hyper Local Settings page.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$post_data = array(
			'service'      => isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '',
			'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'        => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'company_name' => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'phone'        => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'address'      => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
		);

		if ( '' === $post_data['company_name'] ) {
			$post_data['company_name'] = 'Test Company';
		}
		if ( '' === $post_data['phone'] ) {
			$post_data['phone'] = '(000) 000-0000';
		}
		if ( '' === $post_data['address'] ) {
			$post_data['address'] = 'Test Address';
		}

		if ( '' === $post_data['service'] || '' === $post_data['city'] || '' === $post_data['state'] ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Service, City, and State are required.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$payload = $this->build_generate_preview_payload( $settings, $post_data );
		$payload['preview'] = true;
		$url     = trailingslashit( $api_url ) . 'generate-page';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			if ( false !== stripos( $error_message, 'cURL error 56' ) ) {
				sleep( 1 );
				$response = wp_remote_post(
					$url,
					array(
						'timeout' => 90,
						'headers' => array(
							'Content-Type' => 'application/json',
						),
						'body'    => wp_json_encode( $payload ),
					)
				);
				if ( ! is_wp_error( $response ) ) {
					$error_message = '';
				}
			}
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			}
			if ( false !== stripos( $error_message, 'cURL error 28' ) || false !== stripos( $error_message, 'timed out' ) ) {
				$error_message = __( 'The API request timed out. Generation can take 60–90 seconds. Please retry.', 'seogen' );
			}
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => $error_message,
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$snippet = substr( $body, 0, 500 );
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => sprintf( __( 'API returned HTTP %d. Response: %s', 'seogen' ), $code, $snippet ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'API response was not valid JSON.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$preview_key = 'hl_preview_' . wp_generate_password( 12, false, false );
		set_transient( $preview_key, $data, 5 * MINUTE_IN_SECONDS );

		$user_id = get_current_user_id();
		$last_preview_key = $this->get_last_preview_transient_key( $user_id );
		$last_preview = array(
			'title'            => isset( $data['title'] ) ? (string) $data['title'] : '',
			'slug'             => isset( $data['slug'] ) ? (string) $data['slug'] : '',
			'meta_description' => isset( $data['meta_description'] ) ? (string) $data['meta_description'] : '',
			'blocks'           => ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) ? $data['blocks'] : array(),
			'inputs'           => $post_data,
		);
		$page_mode = isset( $data['page_mode'] ) ? $data['page_mode'] : '';
		$last_preview['gutenberg_markup'] = $this->build_gutenberg_content_from_blocks( $last_preview['blocks'], $page_mode );
		$last_preview['source_json']      = $data;
		set_transient( $last_preview_key, $last_preview, 30 * MINUTE_IN_SECONDS );

		$redirect_url = add_query_arg(
			array(
				'hl_gen'     => 'success',
				'hl_msg'     => __( 'Preview generated successfully.', 'seogen' ),
				'hl_preview' => $preview_key,
			),
			$redirect_url
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function handle_create_draft() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}

		check_admin_referer( 'hyper_local_create_draft', 'hyper_local_create_draft_nonce' );

		$user_id = get_current_user_id();
		$last_preview_key = $this->get_last_preview_transient_key( $user_id );
		$preview = get_transient( $last_preview_key );

		$generate_url = admin_url( 'admin.php?page=hyper-local-generate' );
		if ( ! is_array( $preview ) ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Generate a preview first.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$title = isset( $preview['title'] ) ? (string) $preview['title'] : '';
		$slug  = isset( $preview['slug'] ) ? (string) $preview['slug'] : '';
		$inputs = ( isset( $preview['inputs'] ) && is_array( $preview['inputs'] ) ) ? $preview['inputs'] : null;
		if ( ! is_array( $inputs ) ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Failed to generate full content. Please try again.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		if ( '' === $api_url || '' === $license_key ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Failed to generate full content. Please try again.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$payload = $this->build_generate_preview_payload( $settings, $inputs );
		$payload['preview'] = false;
		$url = trailingslashit( $api_url ) . 'generate-page';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 180,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Failed to generate full content. Please try again.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Failed to generate full content. Please try again.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$full_data = json_decode( $body, true );
		if ( ! is_array( $full_data ) || ! isset( $full_data['blocks'] ) || ! is_array( $full_data['blocks'] ) ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Failed to generate full content. Please try again.', 'seogen' ),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		$title = isset( $full_data['title'] ) ? (string) $full_data['title'] : $title;
		$slug  = isset( $full_data['slug'] ) ? (string) $full_data['slug'] : $slug;
		$meta_description = isset( $full_data['meta_description'] ) ? (string) $full_data['meta_description'] : '';
		$page_mode = isset( $full_data['page_mode'] ) ? $full_data['page_mode'] : '';
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $full_data['blocks'], $page_mode );
		$source_json      = $full_data;

		// Prepend header template if configured
		$settings = $this->get_settings();
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				// Add CSS to remove top spacing from content area
				$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
			}
		}

		// Append footer template if configured
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				// Add CSS to remove bottom spacing from content area
				$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
			}
		}

		$postarr = array(
			'post_type'    => 'service_page',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
		);

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			$generate_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => $post_id->get_error_message(),
				),
				$generate_url
			);
			wp_safe_redirect( $generate_url );
			exit;
		}

		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $source_json ) );
		
		// Store universal meta for service_city pages
		$config = $this->get_business_config();
		update_post_meta( $post_id, '_seogen_page_mode', 'service_city' );
		update_post_meta( $post_id, '_seogen_vertical', isset( $config['vertical'] ) ? $config['vertical'] : '' );
		
		// Extract service and city from inputs
		if ( isset( $inputs['service'] ) ) {
			update_post_meta( $post_id, '_seogen_service_name', sanitize_text_field( $inputs['service'] ) );
			update_post_meta( $post_id, '_seogen_service_slug', sanitize_title( $inputs['service'] ) );
		}
		if ( isset( $inputs['city'], $inputs['state'] ) ) {
			$city_state = $inputs['city'] . ', ' . $inputs['state'];
			update_post_meta( $post_id, '_seogen_city', sanitize_text_field( $city_state ) );
			update_post_meta( $post_id, '_seogen_city_slug', sanitize_title( $inputs['city'] . '-' . $inputs['state'] ) );
		}
		
		// Try to determine hub_key from service name
		$services = $this->get_services();
		if ( isset( $inputs['service'] ) && ! empty( $services ) ) {
			foreach ( $services as $service ) {
				if ( isset( $service['name'], $service['hub_key'] ) && strtolower( $service['name'] ) === strtolower( $inputs['service'] ) ) {
					update_post_meta( $post_id, '_seogen_hub_key', $service['hub_key'] );
					break;
				}
			}
		}

		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', 0 );
		if ( $unique_slug ) {
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $unique_slug,
				)
			);
		}

		$service = '';
		if ( isset( $inputs['service'] ) ) {
			$service = sanitize_text_field( (string) $inputs['service'] );
			$service = trim( $service );
		}
		if ( '' === $service ) {
			$service = $this->parse_service_from_title( $title );
		}
		$this->apply_seo_plugin_meta( $post_id, $service, $title, $meta_description, true );

		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		update_post_meta( $post_id, '_hl_page_type', 'service_city' );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $source_json ) );
		update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );

		// Apply page builder settings to disable theme header/footer if configured
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}

		$edit_url = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
		$edit_url = add_query_arg(
			array(
				'hl_notice' => 'created',
				'hl_msg'    => __( 'Draft created successfully.', 'seogen' ),
			),
			$edit_url
		);
		wp_safe_redirect( $edit_url );
		exit;
	}

	private function render_test_connection_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['hl_test'] ) ) {
			return;
		}

		$test = sanitize_text_field( wp_unslash( $_GET['hl_test'] ) );
		$msg  = '';
		if ( isset( $_GET['hl_msg'] ) ) {
			$msg = rawurldecode( (string) wp_unslash( $_GET['hl_msg'] ) );
			$msg = sanitize_text_field( $msg );
		}

		if ( 'success' === $test ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}

		check_admin_referer( 'seogen_test_connection', 'seogen_test_connection_nonce' );

		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		$redirect_url = admin_url( 'admin.php?page=hyper-local' );

		if ( '' === $api_url ) {
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => __( 'API Base URL is missing. Please save an API Base URL first.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$health = $this->check_api_health( $api_url );
		if ( empty( $health['ok'] ) ) {
			$error = __( 'API Connection failed.', 'seogen' );
			if ( ! empty( $health['error'] ) ) {
				$error = $health['error'];
			}
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => $error,
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( '' === $license_key ) {
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => __( 'API Connection succeeded, but License Key is missing.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$redirect_url = add_query_arg(
			array(
				'hl_test' => 'success',
				'hl_msg'  => __( 'API Connection succeeded. License Key is set (not validated with server to avoid consuming credits).', 'seogen' ),
			),
			$redirect_url
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function register_menu() {
		add_menu_page(
			__( 'Hyper Local Settings', 'seogen' ),
			__( 'Hyper Local', 'seogen' ),
			'manage_options',
			'hyper-local',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			59
		);

		// Rename the first submenu item from "Hyper Local" to "Settings"
		add_submenu_page(
			'hyper-local',
			__( 'Hyper Local Settings', 'seogen' ),
			__( 'Settings', 'seogen' ),
			'manage_options',
			'hyper-local',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'Business Setup (Step 0)', 'seogen' ),
			__( 'Business Setup', 'seogen' ),
			'manage_options',
			'hyper-local-business-setup',
			array( $this, 'render_business_setup_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'Services', 'seogen' ),
			__( 'Services', 'seogen' ),
			'manage_options',
			'hyper-local-services',
			array( $this, 'render_services_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'Service Hubs (Step 3.5)', 'seogen' ),
			__( 'Service Hubs', 'seogen' ),
			'manage_options',
			'hyper-local-service-hubs',
			array( $this, 'render_service_hubs_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'City Hubs (Step 4)', 'seogen' ),
			__( 'City Hubs', 'seogen' ),
			'manage_options',
			'hyper-local-city-hubs',
			array( $this, 'render_city_hubs_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'Service Pages', 'seogen' ),
			__( 'Service Pages', 'seogen' ),
			'edit_posts',
			'edit.php?post_type=service_page'
		);

		add_submenu_page(
			'hyper-local',
			__( 'Generate Page', 'seogen' ),
			__( 'Generate Page', 'seogen' ),
			'manage_options',
			'hyper-local-generate',
			array( $this, 'render_generate_page' )
		);

		add_submenu_page(
			'hyper-local',
			__( 'Bulk Generate', 'seogen' ),
			__( 'Bulk Generate', 'seogen' ),
			'manage_options',
			'hyper-local-bulk',
			array( $this, 'render_bulk_generate_page' )
		);
	}

	public function render_generate_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = $this->get_settings();
		$api_url       = isset( $settings['api_url'] ) ? (string) $settings['api_url'] : '';
		$license_key   = isset( $settings['license_key'] ) ? (string) $settings['license_key'] : '';
		$masked_license = $this->mask_license_key( $license_key );

		$preview = null;
		$last_preview_for_user = null;
		if ( isset( $_GET['hl_preview'] ) ) {
			$preview_key = sanitize_text_field( wp_unslash( $_GET['hl_preview'] ) );
			$preview     = get_transient( $preview_key );
			if ( false !== $preview ) {
				delete_transient( $preview_key );
			}
		}
		$last_preview_for_user = get_transient( $this->get_last_preview_transient_key( get_current_user_id() ) );

		$title = '';
		$slug  = '';
		$meta_description = '';
		$gutenberg_markup = '';
		if ( is_array( $preview ) ) {
			if ( isset( $preview['title'] ) ) {
				$title = (string) $preview['title'];
			}
			if ( isset( $preview['slug'] ) ) {
				$slug = (string) $preview['slug'];
			}
			if ( isset( $preview['meta_description'] ) ) {
				$meta_description = (string) $preview['meta_description'];
			} elseif ( isset( $preview['meta'] ) && is_array( $preview['meta'] ) && isset( $preview['meta']['description'] ) ) {
				$meta_description = (string) $preview['meta']['description'];
			}

			if ( isset( $preview['blocks'] ) && is_array( $preview['blocks'] ) ) {
				$page_mode = isset( $preview['page_mode'] ) ? $preview['page_mode'] : '';
				$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $preview['blocks'], $page_mode );
			}
		}

		$defaults = array(
			'service'      => 'Roof Repair',
			'city'         => 'Dallas',
			'state'        => 'TX',
			'company_name' => '',
			'phone'        => '',
			'address'      => '',
		);
		?>
		<div class="wrap">
			<?php $this->render_generate_notice(); ?>
			<h1><?php echo esc_html__( 'Generate Page', 'seogen' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'This will call the API and display the returned JSON for preview/debug only. No WordPress pages are created in this phase.', 'seogen' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hyper_local_generate_preview" />
				<?php wp_nonce_field( 'hyper_local_generate_preview', 'hyper_local_generate_preview_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="hl_service"><?php echo esc_html__( 'Service', 'seogen' ); ?></label></th>
							<td><input name="service" id="hl_service" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['service'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_city"><?php echo esc_html__( 'City', 'seogen' ); ?></label></th>
							<td><input name="city" id="hl_city" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['city'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_state"><?php echo esc_html__( 'State', 'seogen' ); ?></label></th>
							<td><input name="state" id="hl_state" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['state'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_company_name"><?php echo esc_html__( 'Company Name', 'seogen' ); ?></label></th>
							<td><input name="company_name" id="hl_company_name" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['company_name'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_phone"><?php echo esc_html__( 'Phone', 'seogen' ); ?></label></th>
							<td>
								<input name="phone" id="hl_phone" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['phone'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'If left blank, a Preview placeholder will be used.', 'seogen' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_address"><?php echo esc_html__( 'Address', 'seogen' ); ?></label></th>
							<td>
								<input name="address" id="hl_address" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['address'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'If left blank, a Preview placeholder will be used.', 'seogen' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="description"><?php echo esc_html__( 'Generation can take up to 60–90 seconds.', 'seogen' ); ?></p>
				<?php submit_button( __( 'Generate Preview', 'seogen' ), 'primary', 'submit' ); ?>
			</form>

			<?php if ( is_array( $last_preview_for_user ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hyper_local_create_draft" />
					<?php wp_nonce_field( 'hyper_local_create_draft', 'hyper_local_create_draft_nonce' ); ?>
					<?php submit_button( __( 'Create Draft Programmatic Page', 'seogen' ), 'secondary', 'submit' ); ?>
				</form>
			<?php endif; ?>

			<?php if ( is_array( $preview ) ) : ?>
				<h2><?php echo esc_html__( 'Preview', 'seogen' ); ?></h2>

				<?php if ( '' !== $title ) : ?>
					<p><strong><?php echo esc_html__( 'Title:', 'seogen' ); ?></strong> <?php echo esc_html( $title ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $slug ) : ?>
					<p><strong><?php echo esc_html__( 'Slug:', 'seogen' ); ?></strong> <?php echo esc_html( $slug ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $meta_description ) : ?>
					<p><strong><?php echo esc_html__( 'Meta description:', 'seogen' ); ?></strong> <?php echo esc_html( $meta_description ); ?></p>
				<?php endif; ?>

				<pre><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>

				<h2><?php echo esc_html__( 'Gutenberg Preview Markup', 'seogen' ); ?></h2>
				<textarea class="large-text code" rows="16" readonly><?php echo esc_textarea( $gutenberg_markup ); ?></textarea>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_bulk_validate_transient_key( $user_id ) {
		return self::BULK_VALIDATE_TRANSIENT_PREFIX . (int) $user_id;
	}

	private function get_bulk_job_option_key( $job_id ) {
		return self::BULK_JOB_OPTION_PREFIX . sanitize_key( (string) $job_id );
	}

	private function get_bulk_jobs_index() {
		$index = get_option( self::BULK_JOBS_INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		return $index;
	}

	private function save_bulk_job( $job_id, array $job ) {
		$job_id = sanitize_key( (string) $job_id );
		if ( '' === $job_id ) {
			return;
		}
		update_option( $this->get_bulk_job_option_key( $job_id ), $job, false );
		$index = $this->get_bulk_jobs_index();
		if ( ! in_array( $job_id, $index, true ) ) {
			array_unshift( $index, $job_id );
			$index = array_slice( $index, 0, 50 );
			update_option( self::BULK_JOBS_INDEX_OPTION, $index, false );
		}
	}

	private function load_bulk_job( $job_id ) {
		$job_id = sanitize_key( (string) $job_id );
		if ( '' === $job_id ) {
			return null;
		}
		$job = get_option( $this->get_bulk_job_option_key( $job_id ), null );
		if ( ! is_array( $job ) ) {
			return null;
		}
		return $job;
	}

	private function schedule_bulk_job( $job_id, $delay_seconds = 5 ) {
		$job_id = sanitize_key( (string) $job_id );
		$delay_seconds = (int) $delay_seconds;
		if ( $delay_seconds < 0 ) {
			$delay_seconds = 0;
		}
		if ( '' === $job_id ) {
			return;
		}
		$lock_key = 'hyper_local_bulk_schedule_lock_' . $job_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, '1', 30 );

		error_log( '[HyperLocal Bulk] scheduling batch job_id=' . $job_id . ' delay=' . $delay_seconds . 's backend=' . $this->get_bulk_backend_label() );

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( self::BULK_PROCESS_HOOK, array( 'job_id' => $job_id ), 'hyper-local' );
			if ( ! empty( $action_id ) ) {
				return;
			}
		}
		if ( ! wp_next_scheduled( self::BULK_PROCESS_HOOK, array( $job_id ) ) ) {
			wp_schedule_single_event( time() + $delay_seconds, self::BULK_PROCESS_HOOK, array( $job_id ) );
		}
	}

	private function format_bulk_api_error_message( $code, $body ) {
		$code = (int) $code;
		$body = (string) $body;
		$message = sprintf( __( 'API returned HTTP %d', 'seogen' ), $code );
		$data = json_decode( $body, true );
		if ( is_array( $data ) && isset( $data['detail'] ) && '' !== trim( (string) $data['detail'] ) ) {
			$message .= ': ' . sanitize_text_field( (string) $data['detail'] );
		}
		return $message;
	}

	private function get_bulk_backend_label() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			return 'Action Scheduler';
		}
		return 'WP-Cron';
	}

	public function handle_bulk_run_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}
		$job_id = isset( $_GET['job_id'] ) ? sanitize_key( (string) wp_unslash( $_GET['job_id'] ) ) : '';
		check_admin_referer( 'hyper_local_bulk_run_batch_' . $job_id, 'nonce' );
		$redirect_url = admin_url( 'admin.php?page=hyper-local-bulk&job_id=' . $job_id );
		if ( '' === $job_id ) {
			wp_safe_redirect( $redirect_url );
			exit;
		}
		error_log( '[HyperLocal Bulk] manual run batch job_id=' . $job_id );
		$this->process_bulk_job( $job_id );
		wp_safe_redirect( $redirect_url );
		exit;
	}

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
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
		return 0;
	}

	private function parse_bulk_lines( $raw_lines ) {
		$raw_lines = (string) $raw_lines;
		$lines = preg_split( '/\r\n|\r|\n/', $raw_lines );
		$out = array();
		if ( ! is_array( $lines ) ) {
			return $out;
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$out[] = sanitize_text_field( $line );
		}
		return $out;
	}

	private function parse_service_areas( $raw_lines, $default_state = '' ) {
		$lines = $this->parse_bulk_lines( $raw_lines );
		$areas = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( ',', (string) $line ) );
			$parts = array_values( array_filter( $parts, static function ( $v ) {
				return '' !== trim( (string) $v );
			} ) );
			if ( 1 === count( $parts ) ) {
				// Single value (city/neighborhood only) - state is optional
				$areas[] = array(
					'city'  => sanitize_text_field( (string) $parts[0] ),
					'state' => '', // Empty state for city/neighborhood-only entries
				);
			} elseif ( 2 === count( $parts ) ) {
				// Standard format: City, ST
				$areas[] = array(
					'city'  => sanitize_text_field( (string) $parts[0] ),
					'state' => sanitize_text_field( (string) $parts[1] ),
				);
			}
			// Skip lines that don't match either format
		}
		return $areas;
	}

	private function compute_canonical_key( $service, $city, $state ) {
		$service = strtolower( trim( (string) $service ) );
		$city = strtolower( trim( (string) $city ) );
		$state = strtolower( trim( (string) $state ) );
		return $service . '|' . $city . '|' . $state;
	}

	private function compute_slug_preview( $service, $city, $state ) {
		$raw = trim( (string) $service ) . '-' . trim( (string) $city ) . '-' . trim( (string) $state );
		return sanitize_title( $raw );
	}

	private function api_json_request( $method, $url, $payload, $timeout ) {
		error_log( '[HyperLocal API] api_json_request START method=' . $method . ' url=' . $url . ' timeout=' . $timeout . ' payload_size=' . ( $payload ? strlen( wp_json_encode( $payload ) ) : 0 ) );
		$args = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => (int) $timeout,
			'headers' => array( 'Content-Type' => 'application/json' ),
		);
		if ( null !== $payload ) {
			$args['body'] = wp_json_encode( $payload );
		}
		$response = wp_remote_request( (string) $url, $args );
		error_log( '[HyperLocal API] api_json_request wp_remote_request completed, is_wp_error=' . ( is_wp_error( $response ) ? 'YES' : 'NO' ) );
		if ( is_wp_error( $response ) ) {
			$error_msg = $response->get_error_message();
			error_log( '[HyperLocal API] api_json_request WP_Error: ' . $error_msg );
			return array(
				'ok'    => false,
				'error' => $error_msg,
				'code'  => 0,
				'body'  => '',
				'data'  => null,
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = null;
		if ( '' !== $body ) {
			$decoded = json_decode( $body, true );
			if ( is_array( $decoded ) ) {
				$data = $decoded;
			}
		}
		if ( $code < 200 || $code >= 300 ) {
			$error = sprintf( __( 'API returned HTTP %d', 'seogen' ), $code );
			if ( is_array( $data ) ) {
				if ( isset( $data['detail'] ) ) {
					// FastAPI validation error format
					if ( is_array( $data['detail'] ) ) {
						$error .= ': ' . wp_json_encode( $data['detail'] );
					} else {
						$error .= ': ' . sanitize_text_field( (string) $data['detail'] );
					}
				} else {
					// Show full data if no detail field
					$error .= ': ' . wp_json_encode( $data );
				}
			} elseif ( '' !== $body ) {
				$error .= ': ' . substr( $body, 0, 200 );
			}
			error_log( '[HyperLocal API] api_json_request FAILED code=' . $code . ' error=' . $error . ' full_body=' . $body );
			return array(
				'ok'    => false,
				'error' => $error,
				'code'  => $code,
				'body'  => $body,
				'data'  => $data,
			);
		}
		error_log( '[HyperLocal API] api_json_request SUCCESS code=' . $code );
		return array(
			'ok'   => true,
			'code' => $code,
			'body' => $body,
			'data' => $data,
		);
	}

	private function api_create_bulk_job( $api_url, $license_key, $job_name, $items ) {
		$url = trailingslashit( (string) $api_url ) . 'bulk-jobs';
		$payload = array(
			'license_key' => (string) $license_key,
			'site_url'    => home_url(),
			'job_name'    => (string) $job_name,
			'items'       => $items,
		);
		error_log( '[ADMIN] api_create_bulk_job called with ' . count( $items ) . ' items' );
		error_log( '[ADMIN] First item in api_create_bulk_job: ' . wp_json_encode( $items[0] ) );
		return $this->api_json_request( 'POST', $url, $payload, 60 );
	}

	private function api_get_bulk_job_status( $api_url, $license_key, $api_job_id ) {
		$url = trailingslashit( (string) $api_url ) . 'bulk-jobs/' . rawurlencode( (string) $api_job_id );
		$url = add_query_arg( array( 'license_key' => (string) $license_key ), $url );
		return $this->api_json_request( 'GET', $url, null, 20 );
	}

	private function api_get_bulk_job_results( $api_url, $license_key, $api_job_id, $cursor, $limit = 10 ) {
		$url = trailingslashit( (string) $api_url ) . 'bulk-jobs/' . rawurlencode( (string) $api_job_id ) . '/results';
		$args = array(
			'license_key' => (string) $license_key,
			'limit'       => (int) $limit,
		);
		if ( null !== $cursor && '' !== (string) $cursor ) {
			$args['cursor'] = (string) $cursor;
		}
		$url = add_query_arg( $args, $url );
		return $this->api_json_request( 'GET', $url, null, 30 );
	}

	private function api_ack_bulk_job_items( $api_url, $license_key, $api_job_id, $item_ids ) {
		$url = trailingslashit( (string) $api_url ) . 'bulk-jobs/' . rawurlencode( (string) $api_job_id ) . '/ack';
		$payload = array(
			'license_key'        => (string) $license_key,
			'imported_item_ids'  => array_values( (array) $item_ids ),
		);
		return $this->api_json_request( 'POST', $url, $payload, 30 );
	}

	private function api_cancel_bulk_job( $api_url, $license_key, $api_job_id ) {
		$url = trailingslashit( (string) $api_url ) . 'bulk-jobs/' . rawurlencode( (string) $api_job_id ) . '/cancel';
		$payload = array(
			'license_key' => (string) $license_key,
		);
		return $this->api_json_request( 'POST', $url, $payload, 20 );
	}

	public function register_settings() {
		register_setting(
			'seogen_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'seogen_settings_section_main',
			__( 'API Settings', 'seogen' ),
			'__return_false',
			'seogen-settings'
		);

		add_settings_field(
			'seogen_license_key',
			__( 'License Key', 'seogen' ),
			array( $this, 'render_field_license_key' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_credits_remaining',
			__( 'Credits Remaining', 'seogen' ),
			array( $this, 'render_field_credits_remaining' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'hyper_local_design_preset',
			__( 'Design Preset', 'seogen' ),
			array( $this, 'render_field_design_preset' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'hyper_local_show_h1_in_content',
			__( 'Show H1 in content', 'seogen' ),
			array( $this, 'render_field_show_h1_in_content' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'hyper_local_hero_style',
			__( 'Hero Style', 'seogen' ),
			array( $this, 'render_field_hero_style' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		// Removed: Primary CTA Label field (now using Business Setup CTA text)
		
		add_settings_field(
			'hyper_local_cta_style',
			__( 'CTA Style', 'seogen' ),
			array( $this, 'render_field_cta_style' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'hyper_local_enable_mobile_sticky_cta',
			__( 'Mobile Sticky CTA', 'seogen' ),
			array( $this, 'render_field_enable_mobile_sticky_cta' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_header_template_id',
			__( 'Header Template (Reusable Block)', 'seogen' ),
			array( $this, 'render_field_header_template' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_footer_template_id',
			__( 'Footer Template (Reusable Block)', 'seogen' ),
			array( $this, 'render_field_footer_template' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_disable_theme_header_footer',
			__( 'Disable Theme Header/Footer', 'seogen' ),
			array( $this, 'render_field_disable_theme_header_footer' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);
	}

	public function sanitize_settings( $input ) {
		$sanitized = array();

		$api_url = '';
		if ( isset( $input['api_url'] ) ) {
			$api_url = esc_url_raw( $input['api_url'] );
		}
		$sanitized['api_url'] = $api_url;

		$license_key = '';
		if ( isset( $input['license_key'] ) ) {
			$license_key = sanitize_text_field( $input['license_key'] );
		}
		$sanitized['license_key'] = $license_key;

		$design_preset = 'theme_default';
		if ( isset( $input['design_preset'] ) ) {
			$design_preset = sanitize_key( (string) $input['design_preset'] );
		}
		if ( ! in_array( $design_preset, array( 'theme_default', 'clean_card', 'bold_sections' ), true ) ) {
			$design_preset = 'theme_default';
		}
		$sanitized['design_preset'] = $design_preset;

		$sanitized['show_h1_in_content'] = ( isset( $input['show_h1_in_content'] ) && '1' === (string) $input['show_h1_in_content'] ) ? '1' : '0';

		$hero_style = 'minimal';
		if ( isset( $input['hero_style'] ) ) {
			$hero_style = sanitize_key( (string) $input['hero_style'] );
		}
		if ( ! in_array( $hero_style, array( 'minimal', 'banner' ), true ) ) {
			$hero_style = 'minimal';
		}
		$sanitized['hero_style'] = $hero_style;

		$cta_style = 'button_only';
		if ( isset( $input['cta_style'] ) ) {
			$cta_style = sanitize_key( (string) $input['cta_style'] );
		}
		if ( ! in_array( $cta_style, array( 'button_only', 'button_and_phone' ), true ) ) {
			$cta_style = 'button_only';
		}
		$sanitized['cta_style'] = $cta_style;

		$sanitized['enable_mobile_sticky_cta'] = ( isset( $input['enable_mobile_sticky_cta'] ) && '1' === (string) $input['enable_mobile_sticky_cta'] ) ? '1' : '0';

		// Removed: primary_cta_label sanitization (now using Business Setup CTA text)

		$header_template_id = 0;
		if ( isset( $input['header_template_id'] ) ) {
			$header_template_id = (int) $input['header_template_id'];
		}
		$sanitized['header_template_id'] = $header_template_id;

		$footer_template_id = 0;
		if ( isset( $input['footer_template_id'] ) ) {
			$footer_template_id = (int) $input['footer_template_id'];
		}
		$sanitized['footer_template_id'] = $footer_template_id;

		$sanitized['disable_theme_header_footer'] = ( isset( $input['disable_theme_header_footer'] ) && '1' === (string) $input['disable_theme_header_footer'] ) ? '1' : '0';

		return $sanitized;
	}

	public function add_service_page_body_class( $classes ) {
		global $post;
		
		// Get the actual post ID, handling revisions/autosaves
		$post_id = 0;
		if ( $post ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['p'] ) ) {
			$post_id = (int) $_GET['p'];
		} elseif ( isset( $_GET['preview_id'] ) ) {
			$post_id = (int) $_GET['preview_id'];
		}
		
		if ( ! $post_id ) {
			return $classes;
		}
		
		// If this is a revision/autosave, get the parent post ID
		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			$post_id = $parent_id;
		}
		
		// In preview mode, also check for parent
		if ( is_preview() ) {
			$maybe_parent = wp_get_post_parent_id( $post_id );
			if ( $maybe_parent ) {
				$post_id = $maybe_parent;
			}
		}
		
		// Check if this is a service_page
		$post_obj = get_post( $post_id );
		if ( ! $post_obj || 'service_page' !== $post_obj->post_type ) {
			return $classes;
		}
		
		$settings = $this->get_settings();
		if ( empty( $settings['disable_theme_header_footer'] ) ) {
			return $classes;
		}
		
		$classes[] = 'seogen-no-header-footer';
		return $classes;
	}
	
	public function add_service_page_styles() {
		global $post;
		
		// Get the actual post ID, handling revisions/autosaves
		$post_id = 0;
		if ( $post ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['p'] ) ) {
			$post_id = (int) $_GET['p'];
		} elseif ( isset( $_GET['preview_id'] ) ) {
			$post_id = (int) $_GET['preview_id'];
		}
		
		if ( ! $post_id ) {
			return;
		}
		
		// If this is a revision/autosave, get the parent post ID
		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			$post_id = $parent_id;
		}
		
		// In preview mode, also check for parent
		if ( is_preview() ) {
			$maybe_parent = wp_get_post_parent_id( $post_id );
			if ( $maybe_parent ) {
				$post_id = $maybe_parent;
			}
		}
		
		// Check if this is a service_page
		$post_obj = get_post( $post_id );
		if ( ! $post_obj || 'service_page' !== $post_obj->post_type ) {
			return;
		}
		
		$settings = $this->get_settings();
		if ( empty( $settings['disable_theme_header_footer'] ) ) {
			return;
		}
		
		// Universal CSS to hide header and footer - works across all themes
		echo '<style id="seogen-hide-header-footer">
			body.seogen-no-header-footer header,
			body.seogen-no-header-footer .site-header,
			body.seogen-no-header-footer .header,
			body.seogen-no-header-footer #masthead,
			body.seogen-no-header-footer .masthead,
			body.seogen-no-header-footer footer,
			body.seogen-no-header-footer .site-footer,
			body.seogen-no-header-footer .footer,
			body.seogen-no-header-footer #colophon,
			body.seogen-no-header-footer .colophon {
				display: none !important;
			}
			
			/* Constrain main content width, but not header/footer sections */
			body.seogen-no-header-footer .elementor-location-single .elementor-section-wrap > .elementor-section:not(.elementor-section-full_width) .elementor-container {
				max-width: 1140px;
			}
			
			/* Constrain/center the actual service-page content wrapper */
			body.seogen-no-header-footer .hyper-local-body,
			body.seogen-no-header-footer .hyper-local-content,
			body.seogen-no-header-footer .hyper-local-hero,
			body.seogen-no-header-footer .hyper-local-cta-section,
			body.seogen-no-header-footer .elementor-location-single .elementor-widget-theme-post-content,
			body.seogen-no-header-footer .elementor-location-single .elementor-widget-theme-post-content > .elementor-widget-container {
				max-width: 1140px;
				margin-left: auto;
				margin-right: auto;
				padding-left: 20px;
				padding-right: 20px;
				box-sizing: border-box;
			}
		</style>';
	}
	
	public function add_service_page_styles_footer() {
		global $post;
		
		// Get the actual post ID, handling revisions/autosaves
		$post_id = 0;
		if ( $post ) {
			$post_id = $post->ID;
		} elseif ( isset( $_GET['p'] ) ) {
			$post_id = (int) $_GET['p'];
		} elseif ( isset( $_GET['preview_id'] ) ) {
			$post_id = (int) $_GET['preview_id'];
		}
		
		if ( ! $post_id ) {
			return;
		}
		
		// If this is a revision/autosave, get the parent post ID
		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			$post_id = $parent_id;
		}
		
		// In preview mode, also check for parent
		if ( is_preview() ) {
			$maybe_parent = wp_get_post_parent_id( $post_id );
			if ( $maybe_parent ) {
				$post_id = $maybe_parent;
			}
		}
		
		// Check if this is a service_page
		$post_obj = get_post( $post_id );
		if ( ! $post_obj || 'service_page' !== $post_obj->post_type ) {
			return;
		}
		
		$settings = $this->get_settings();
		if ( empty( $settings['disable_theme_header_footer'] ) ) {
			return;
		}
		
		// Inject JavaScript to hide header/footer as a backup
		echo '<script>
			(function() {
				var selectors = [
					"header", ".site-header", ".header", "#masthead", ".masthead",
					"footer", ".site-footer", ".footer", "#colophon", ".colophon"
				];
				selectors.forEach(function(selector) {
					var elements = document.querySelectorAll(selector);
					elements.forEach(function(el) {
						el.style.display = "none";
					});
				});
			})();
		</script>';
	}

	public function force_service_page_template( $template ) {
		// Only apply to service_page post type
		if ( ! is_singular( 'service_page' ) ) {
			return $template;
		}
		
		$settings = $this->get_settings();
		if ( empty( $settings['disable_theme_header_footer'] ) ) {
			return $template;
		}
		
		// For Elementor, force the header-footer template (removes theme header/footer, keeps Elementor templates)
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				// Force the template meta - this is critical for drafts
				update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
				
				// Also set Elementor's page settings
				$page_settings = get_post_meta( $post_id, '_elementor_page_settings', true );
				if ( ! is_array( $page_settings ) ) {
					$page_settings = array();
				}
				$page_settings['template'] = 'elementor_header_footer';
				$page_settings['hide_title'] = 'yes';
				update_post_meta( $post_id, '_elementor_page_settings', $page_settings );
				
				// Return Elementor's header-footer template file
				$elementor_template = ELEMENTOR_PATH . 'modules/page-templates/templates/header-footer.php';
				if ( file_exists( $elementor_template ) ) {
					return $elementor_template;
				}
			}
		}
		
		return $template;
	}

	public function apply_page_builder_settings( $post_id ) {
		// Elementor: Set to Header/Footer mode (removes theme header/footer but keeps Elementor templates)
		if ( class_exists( '\Elementor\Plugin' ) ) {
			// Set Elementor page settings with template
			$page_settings = array(
				'hide_title' => 'yes',
				'page_title' => '',
				'template' => 'elementor_header_footer',
			);
			update_post_meta( $post_id, '_elementor_page_settings', $page_settings );
			update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			
			// Set page layout to elementor_header_footer (removes theme header/footer, keeps Elementor templates)
			$result = update_post_meta( $post_id, '_wp_page_template', 'elementor_header_footer' );
			
			// Debug logging
			error_log( sprintf( 
				'[HyperLocal] Applied template settings to post %d: _wp_page_template=%s (result=%s)', 
				$post_id, 
				'elementor_header_footer',
				$result ? 'success' : 'failed'
			) );
		}
		// Divi: Set to Blank template
		elseif ( function_exists( 'et_pb_is_pagebuilder_used' ) ) {
			update_post_meta( $post_id, '_wp_page_template', 'page-template-blank.php' );
			update_post_meta( $post_id, '_et_pb_use_builder', 'on' );
		}
		// Beaver Builder: Set to no header/footer
		elseif ( class_exists( 'FLBuilder' ) ) {
			update_post_meta( $post_id, '_fl_builder_enabled', '1' );
			update_post_meta( $post_id, '_fl_builder_data_settings', array(
				'template' => 'no-header-footer',
			) );
		}
		// Oxygen: Set to blank template
		elseif ( class_exists( 'CT_Component' ) ) {
			update_post_meta( $post_id, 'ct_builder_shortcodes', '' );
			update_post_meta( $post_id, 'ct_other_template', '-1' );
		}
		// Bricks: Set to no header/footer
		elseif ( class_exists( 'Bricks\Database' ) ) {
			update_post_meta( $post_id, '_bricks_editor_mode', 'bricks' );
			update_post_meta( $post_id, '_wp_page_template', 'bricks-blank' );
		}
		// Gutenberg/Block Editor: Use full-width template if available
		else {
			// Try common full-width template names
			$templates = array( 'template-fullwidth.php', 'page-templates/full-width.php', 'templates/template-blank.php' );
			foreach ( $templates as $template ) {
				if ( locate_template( $template ) ) {
					update_post_meta( $post_id, '_wp_page_template', $template );
					break;
				}
			}
		}
	}

	public function get_template_content( $template_id ) {
		if ( $template_id <= 0 ) {
			return '';
		}

		$post = get_post( $template_id );
		if ( ! $post ) {
			return '';
		}

		// Check if it's an Elementor template
		if ( 'elementor_library' === $post->post_type && class_exists( '\Elementor\Plugin' ) ) {
			// For Elementor templates, we need to use Elementor's shortcode
			// This will render the template properly when the page is viewed
			return '[elementor-template id="' . $template_id . '"]';
		}

		// For WordPress reusable blocks or other content, use post_content directly
		$content = get_post_field( 'post_content', $template_id );
		if ( is_wp_error( $content ) ) {
			return '';
		}

		return $content;
	}

	private function get_settings() {
		$defaults = array(
			'api_url'      => self::API_BASE_URL,
			'license_key'  => '',
			'design_preset' => 'theme_default',
			'show_h1_in_content' => '0',
			'hero_style' => 'minimal',
			'cta_style' => 'button_only',
			'enable_mobile_sticky_cta' => '0',
		);

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Always override api_url with constant
		$settings = wp_parse_args( $settings, $defaults );
		$settings['api_url'] = self::API_BASE_URL;

		return $settings;
	}

	public function render_field_api_url() {
		$settings = $this->get_settings();

		printf(
			'<input type="url" class="regular-text" name="%1$s[api_url]" value="%2$s" placeholder="https://seogen-production.up.railway.app" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['api_url'] )
		);
	}

	public function render_field_license_key() {
		$settings = $this->get_settings();

		printf(
			'<input type="text" class="regular-text" name="%1$s[license_key]" value="%2$s" autocomplete="off" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['license_key'] )
		);
	}

	public function render_field_credits_remaining() {
		$settings = $this->get_settings();
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		if ( '' === $license_key ) {
			echo '<p class="description">' . esc_html__( 'Enter a license key to view usage.', 'seogen' ) . '</p>';
			return;
		}

		// Fetch license info from API
		$api_url = self::API_BASE_URL;
		$validate_url = trailingslashit( $api_url ) . 'validate-license';
		
		$response = wp_remote_post(
			$validate_url,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'license_key' => $license_key ) ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			echo '<p class="description" style="color: #d63638;">' . esc_html__( 'Unable to fetch usage data.', 'seogen' ) . '</p>';
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 403 === $status_code ) {
			echo '<p class="description" style="color: #d63638;">' . esc_html__( 'Invalid license key.', 'seogen' ) . '</p>';
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			echo '<p class="description" style="color: #d63638;">' . esc_html__( 'Unable to fetch usage data.', 'seogen' ) . '</p>';
			return;
		}

		$status = isset( $data['status'] ) ? (string) $data['status'] : 'unknown';
		$page_limit = isset( $data['page_limit'] ) ? (int) $data['page_limit'] : 0;
		$monthly_limit = isset( $data['monthly_generation_limit'] ) ? (int) $data['monthly_generation_limit'] : 0;
		$total_pages = isset( $data['total_pages_generated'] ) ? (int) $data['total_pages_generated'] : 0;
		$pages_this_month = isset( $data['pages_generated_this_month'] ) ? (int) $data['pages_generated_this_month'] : 0;
		$capacity_remaining = isset( $data['pages_remaining_capacity'] ) ? (int) $data['pages_remaining_capacity'] : 0;
		$monthly_remaining = isset( $data['pages_remaining_this_month'] ) ? (int) $data['pages_remaining_this_month'] : 0;

		$color = 'active' === $status ? '#2271b1' : '#d63638';
		
		?>
		<div style="background: #f6f7f7; border-left: 4px solid <?php echo esc_attr( $color ); ?>; padding: 12px 16px; margin: 8px 0;">
			<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px;">
				<div>
					<div style="font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'Total Pages', 'seogen' ); ?>
					</div>
					<div style="font-size: 24px; font-weight: 600; color: <?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( number_format( $total_pages ) ); ?> <span style="font-size: 14px; color: #646970;">/ <?php echo esc_html( number_format( $page_limit ) ); ?></span>
					</div>
					<div style="font-size: 12px; color: #646970; margin-top: 2px;">
						<?php echo esc_html( number_format( $capacity_remaining ) ); ?> <?php esc_html_e( 'pages remaining', 'seogen' ); ?>
					</div>
				</div>
				<div>
					<div style="font-size: 11px; text-transform: uppercase; color: #646970; font-weight: 600; margin-bottom: 4px;">
						<?php esc_html_e( 'This Month', 'seogen' ); ?>
					</div>
					<div style="font-size: 24px; font-weight: 600; color: <?php echo esc_attr( $color ); ?>;">
						<?php echo esc_html( number_format( $pages_this_month ) ); ?> <span style="font-size: 14px; color: #646970;">/ <?php echo esc_html( number_format( $monthly_limit ) ); ?></span>
					</div>
					<div style="font-size: 12px; color: #646970; margin-top: 2px;">
						<?php echo esc_html( number_format( $monthly_remaining ) ); ?> <?php esc_html_e( 'pages remaining', 'seogen' ); ?>
					</div>
				</div>
			</div>
			<?php if ( 'active' !== $status ) : ?>
				<div style="font-size: 12px; color: #d63638; font-weight: 600; margin-top: 8px; padding-top: 8px; border-top: 1px solid #dcdcde;">
					<?php printf( esc_html__( 'License Status: %s', 'seogen' ), esc_html( ucfirst( $status ) ) ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function check_api_health( $api_url ) {
		$api_url = (string) $api_url;
		$api_url = trim( $api_url );

		if ( '' === $api_url ) {
			return array(
				'ok'    => false,
				'error' => __( 'API URL is empty.', 'seogen' ),
			);
		}

		$health_url = trailingslashit( $api_url ) . 'health';

		$response = wp_remote_get(
			$health_url,
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return array(
				'ok'    => false,
				'error' => sprintf( __( 'Unexpected HTTP status: %d', 'seogen' ), $code ),
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Invalid JSON response.', 'seogen' ),
			);
		}

		if ( isset( $data['status'] ) && 'ok' === $data['status'] ) {
			return array( 'ok' => true );
		}

		return array(
			'ok'    => false,
			'error' => __( 'Health check did not return status=ok.', 'seogen' ),
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$status   = $this->check_api_health( $settings['api_url'] );
		$has_license_key = ( isset( $settings['license_key'] ) && '' !== trim( (string) $settings['license_key'] ) );
		?>
		<div class="wrap">
			<?php $this->render_test_connection_notice(); ?>
			<h1><?php echo esc_html__( 'Hyper Local Settings', 'seogen' ); ?></h1>

			<p>
				<strong><?php echo esc_html__( 'License Key:', 'seogen' ); ?></strong>
				<?php if ( $has_license_key ) : ?>
					<span style="color: #0a7d00; font-weight: 600;">✅ <?php echo esc_html__( 'Set', 'seogen' ); ?></span>
				<?php else : ?>
					<span style="color: #b32d2e; font-weight: 600;">❌ <?php echo esc_html__( 'Missing', 'seogen' ); ?></span>
				<?php endif; ?>
			</p>

			<p>
				<strong><?php echo esc_html__( 'API Connection:', 'seogen' ); ?></strong>
				<?php if ( ! empty( $status['ok'] ) ) : ?>
					<span style="color: #0a7d00; font-weight: 600;">✅ <?php echo esc_html__( 'Connected', 'seogen' ); ?></span>
				<?php else : ?>
					<span style="color: #b32d2e; font-weight: 600;">❌ <?php echo esc_html__( 'Not Connected', 'seogen' ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( empty( $status['ok'] ) && ! empty( $status['error'] ) ) : ?>
				<p class="description"><?php echo esc_html( $status['error'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seogen_test_connection" />
				<?php wp_nonce_field( 'seogen_test_connection', 'seogen_test_connection_nonce' ); ?>
				<?php submit_button( __( 'Test API Connection', 'seogen' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr style="margin: 30px 0;">

			<h2><?php echo esc_html__( 'Setup Wizard', 'seogen' ); ?></h2>
			<p><?php echo esc_html__( 'Run the setup wizard again to reconfigure your settings and generate pages.', 'seogen' ); ?></p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=seogen-wizard' ) ); ?>" class="button button-secondary">
					<?php echo esc_html__( 'Run Setup Wizard', 'seogen' ); ?>
				</a>
			</p>

			<hr style="margin: 30px 0;">

			<form method="post" action="options.php">
				<?php
				settings_fields( 'seogen_settings_group' );
				do_settings_sections( 'seogen-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_bulk_generate_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$validate_key = $this->get_bulk_validate_transient_key( $user_id );
		$validated = get_transient( $validate_key );
		if ( false === $validated ) {
			$validated = null;
		}

		$job_id = isset( $_GET['job_id'] ) ? sanitize_key( (string) wp_unslash( $_GET['job_id'] ) ) : '';
		$current_job = ( '' !== $job_id ) ? $this->load_bulk_job( $job_id ) : null;
		if ( '' !== $job_id ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] render_bulk_generate_page: job_id=' . $job_id . ' current_job=' . ( is_array( $current_job ) ? 'found' : 'NOT FOUND' ) . PHP_EOL, FILE_APPEND );
		}
		$jobs_index = $this->get_bulk_jobs_index();

		// Pre-populate from existing setup data (UX improvement - reduce manual re-entry)
		$business_config = $this->get_business_config();
		$services_cache = $this->get_services();
		$cities = $this->get_cities();

		// Build services list (one per line)
		$services_list = '';
		if ( ! empty( $services_cache ) && is_array( $services_cache ) ) {
			$service_names = array();
			foreach ( $services_cache as $service ) {
				if ( isset( $service['name'] ) && ! empty( $service['name'] ) ) {
					$service_names[] = $service['name'];
				}
			}
			$services_list = implode( "\n", $service_names );
		}

		// Build service areas list (one per line: City, ST)
		$service_areas_list = '';
		if ( ! empty( $cities ) && is_array( $cities ) ) {
			$city_lines = array();
			foreach ( $cities as $city ) {
				if ( isset( $city['name'] ) && ! empty( $city['name'] ) ) {
					$city_line = $city['name'];
					if ( isset( $city['state'] ) && ! empty( $city['state'] ) ) {
						$city_line .= ', ' . $city['state'];
					}
					$city_lines[] = $city_line;
				}
			}
			$service_areas_list = implode( "\n", $city_lines );
		}

		$defaults = array(
			'services'        => $services_list,
			'service_areas'   => $service_areas_list,
			'company_name'    => isset( $business_config['business_name'] ) ? $business_config['business_name'] : '',
			'phone'           => isset( $business_config['phone'] ) ? $business_config['phone'] : '',
			'email'           => isset( $business_config['email'] ) ? $business_config['email'] : '',
			'address'         => isset( $business_config['address'] ) ? $business_config['address'] : '',
			'update_existing' => '0',
			'auto_publish'    => '0',
		);
		if ( is_array( $validated ) && isset( $validated['form'] ) && is_array( $validated['form'] ) ) {
			$defaults = wp_parse_args( $validated['form'], $defaults );
		}

		$status_nonce = wp_create_nonce( 'hyper_local_bulk_job_status' );
		$cancel_nonce = wp_create_nonce( 'hyper_local_bulk_job_cancel' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Bulk Generate', 'seogen' ); ?></h1>

			<?php if ( is_array( $current_job ) ) : ?>
				<h2><?php echo esc_html__( 'Current Job', 'seogen' ); ?></h2>
				
				<?php
				$job_status = isset( $current_job['status'] ) ? $current_job['status'] : '';
				if ( 'running' === $job_status ) :
				?>
				<div class="notice notice-warning" style="padding: 15px; margin: 20px 0; border-left: 4px solid #ffb900;">
					<p style="margin: 0; font-size: 14px; font-weight: bold;">
						⚠️ <?php echo esc_html__( 'IMPORTANT: Keep this tab open until the job completes!', 'seogen' ); ?>
					</p>
					<p style="margin: 10px 0 0 0; font-size: 13px;">
						<?php echo esc_html__( 'Pages are being generated in the background, but they will only be imported to WordPress while this tab remains open. You can minimize the tab, but do not close it or navigate away until the job status shows "complete".', 'seogen' ); ?>
					</p>
				</div>
				<?php endif; ?>
				
				<div id="hyper-local-bulk-job" data-job-id="<?php echo esc_attr( $job_id ); ?>"></div>
				<p>
					<button type="button" class="button" id="hyper-local-bulk-refresh"><?php echo esc_html__( 'Refresh status', 'seogen' ); ?></button>
					<button type="button" class="button" id="hyper-local-bulk-cancel"><?php echo esc_html__( 'Cancel Job', 'seogen' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_bulk_run_batch&job_id=' . $job_id ), 'hyper_local_bulk_run_batch_' . $job_id, 'nonce' ) ); ?>"><?php echo esc_html__( 'Run Next Batch Now', 'seogen' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_bulk_export&job_id=' . $job_id ), 'hyper_local_bulk_export_' . $job_id, 'nonce' ) ); ?>"><?php echo esc_html__( 'Export Results CSV', 'seogen' ); ?></a>
				</p>
				<hr />
			<?php endif; ?>

			<?php if ( is_array( $current_job ) ) : ?>
			<script>
			(function(){
				console.log('[SEOgen] Bulk job page script loaded, ajaxurl:', ajaxurl);
				var container = document.getElementById('hyper-local-bulk-job');
				if(!container){console.log('[SEOgen] ERROR: container not found');return;}
				var jobId = container.getAttribute('data-job-id');
				console.log('[SEOgen] Job ID:', jobId);
				var refreshBtn = document.getElementById('hyper-local-bulk-refresh');
				var cancelBtn = document.getElementById('hyper-local-bulk-cancel');
				function esc(s){return String(s).replace(/[&<>\"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;','\'':'&#39;'}[c]);});}
				function render(job){
					if(!job){container.innerHTML = '<p><?php echo esc_js( __( 'Job not found.', 'seogen' ) ); ?></p>';return;}
					var html = '';
					html += '<p><strong><?php echo esc_js( __( 'Status:', 'seogen' ) ); ?></strong> ' + esc(job.status) + '</p>';
					html += '<p><strong><?php echo esc_js( __( 'Totals:', 'seogen' ) ); ?></strong> ' + esc(job.processed) + '/' + esc(job.total_rows) + ' | <?php echo esc_js( __( 'Success', 'seogen' ) ); ?>: ' + esc(job.success) + ' | <?php echo esc_js( __( 'Failed', 'seogen' ) ); ?>: ' + esc(job.failed) + ' | <?php echo esc_js( __( 'Skipped', 'seogen' ) ); ?>: ' + esc(job.skipped) + '</p>';
					html += '<table class="widefat striped"><thead><tr><th><?php echo esc_js( __( 'Service', 'seogen' ) ); ?></th><th><?php echo esc_js( __( 'City', 'seogen' ) ); ?></th><th><?php echo esc_js( __( 'State', 'seogen' ) ); ?></th><th><?php echo esc_js( __( 'Status', 'seogen' ) ); ?></th><th><?php echo esc_js( __( 'Message', 'seogen' ) ); ?></th><th><?php echo esc_js( __( 'Post', 'seogen' ) ); ?></th></tr></thead><tbody>';
					(job.rows||[]).forEach(function(r){
						html += '<tr>';
						html += '<td>' + esc(r.service||'') + '</td>';
						html += '<td>' + esc(r.city||'') + '</td>';
						html += '<td>' + esc(r.state||'') + '</td>';
						html += '<td>' + esc(r.status||'') + '</td>';
						html += '<td>' + esc(r.message||'') + '</td>';
						if(r.edit_url){
							html += '<td><a href="' + esc(r.edit_url) + '"><?php echo esc_js( __( 'Edit', 'seogen' ) ); ?></a></td>';
						}else{
							html += '<td></td>';
						}
						html += '</tr>';
					});
					html += '</tbody></table>';
					container.innerHTML = html;
				}
				var pollInterval = null;
				function fetchStatus(){
					console.log('[SEOgen] fetchStatus called for job:', jobId);
					var data = new FormData();
					data.append('action','hyper_local_bulk_job_status');
					data.append('job_id',jobId);
					data.append('nonce','<?php echo esc_js( $status_nonce ); ?>');
					console.log('[SEOgen] Fetching from:', ajaxurl);
					return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:data}).then(function(r){
						console.log('[SEOgen] Response status:', r.status);
						return r.json();
					}).then(function(res){
						console.log('[SEOgen] Response data:', res);
						if(res && res.success){
							render(res.data);
							return res.data;
						}
						console.error('[SEOgen] Job fetch failed, stopping polling');
						if(pollInterval){clearInterval(pollInterval);pollInterval=null;}
						render(null);
						return null;
					}).catch(function(err){
						console.error('[SEOgen] Fetch error:', err);
						if(pollInterval){clearInterval(pollInterval);pollInterval=null;}
						return null;
					});
				}
				function cancelJob(){
					var data = new FormData();
					data.append('action','hyper_local_bulk_job_cancel');
					data.append('job_id',jobId);
					data.append('nonce','<?php echo esc_js( $cancel_nonce ); ?>');
					return fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:data}).then(function(r){return r.json();}).then(function(){return fetchStatus();});
				}
				if(refreshBtn){refreshBtn.addEventListener('click',function(e){e.preventDefault();fetchStatus();});}
				if(cancelBtn){cancelBtn.addEventListener('click',function(e){e.preventDefault();cancelJob();});}
				
				// Warn user before leaving page if job is running
				var jobIsRunning = false;
				window.addEventListener('beforeunload', function(e) {
					if (jobIsRunning) {
						var message = '<?php echo esc_js( __( 'Your bulk generation job is still running. If you leave this page, pages will stop being imported to WordPress. Are you sure you want to leave?', 'seogen' ) ); ?>';
						e.preventDefault();
						e.returnValue = message;
						return message;
					}
				});
				
				console.log('[SEOgen] Starting initial fetchStatus');
				fetchStatus().then(function(job){
					console.log('[SEOgen] Initial fetch complete, job:', job);
					if(job && (job.status === 'pending' || job.status === 'running')){
						console.log('[SEOgen] Job is active, starting polling interval');
						jobIsRunning = true;
						pollInterval = setInterval(function(){
							fetchStatus().then(function(updatedJob){
								if(updatedJob && updatedJob.status !== 'pending' && updatedJob.status !== 'running'){
									jobIsRunning = false;
									if(pollInterval){clearInterval(pollInterval);pollInterval=null;}
								}
							});
						},5000);
					} else {
						console.log('[SEOgen] Job not active, status:', job ? job.status : 'null');
						jobIsRunning = false;
					}
				});
			})();
			</script>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Inputs', 'seogen' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hyper_local_bulk_validate" />
				<?php wp_nonce_field( 'hyper_local_bulk_validate', 'hyper_local_bulk_validate_nonce' ); ?>
				<style>
					.hyper-local-bulk-grid{display:flex;gap:24px;align-items:flex-start;margin-top:8px}
					.hyper-local-bulk-grid .hyper-local-bulk-col{flex:1;min-width:280px}
					.hyper-local-bulk-grid label{display:block;font-weight:600;margin:0 0 6px}
					.hyper-local-bulk-grid textarea{width:100%}
					.hyper-local-bulk-count{margin:12px 0 0;padding:10px 12px;border:1px solid #dcdcde;border-radius:4px;background:#fff;max-width:420px}
					.hyper-local-bulk-count strong{display:inline-block;min-width:240px}
					@media (max-width: 960px){.hyper-local-bulk-grid{flex-direction:column}}
				</style>
				<p class="description" style="margin-bottom: 12px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1;">
				<?php echo esc_html__( 'Pre-populated from your setup. Edit as needed before generating.', 'seogen' ); ?>
			</p>
			<div class="hyper-local-bulk-grid">
				<div class="hyper-local-bulk-col">
					<label for="hl_bulk_services"><?php echo esc_html__( 'Services (one per line)', 'seogen' ); ?></label>
					<textarea name="services" id="hl_bulk_services" class="large-text" rows="10"><?php echo esc_textarea( (string) $defaults['services'] ); ?></textarea>
				</div>
				<div class="hyper-local-bulk-col">
					<label for="hl_bulk_service_areas"><?php echo esc_html__( 'Service Areas (one per line: City, ST or just City/Neighborhood)', 'seogen' ); ?></label>
					<textarea name="service_areas" id="hl_bulk_service_areas" class="large-text" rows="10"><?php echo esc_textarea( (string) $defaults['service_areas'] ); ?></textarea>
					<p class="description" style="margin-top:6px;"><?php echo esc_html__( 'Example: Dallas, TX or just Dallas or Maple Ridge', 'seogen' ); ?></p>
				</div>
			</div>
				<div class="hyper-local-bulk-count" id="hyper-local-bulk-count">
					<strong><?php echo esc_html__( 'Total pages to be created:', 'seogen' ); ?></strong>
					<span id="hyper-local-bulk-count-value">0</span>
				</div>
				<script>
				(function(){
					var services = document.getElementById('hl_bulk_services');
					var areas = document.getElementById('hl_bulk_service_areas');
					var out = document.getElementById('hyper-local-bulk-count-value');
					if(!services || !areas || !out){return;}
					function countNonEmptyLines(val){
						return String(val||'').split(/\r\n|\r|\n/).map(function(s){return String(s).trim();}).filter(function(s){return s.length>0;}).length;
					}
					function update(){
						var s = countNonEmptyLines(services.value);
						var a = countNonEmptyLines(areas.value);
						out.textContent = String(s*a);
					}
					services.addEventListener('input', update);
					areas.addEventListener('input', update);
					update();
				})();
				</script>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="hl_bulk_company"><?php echo esc_html__( 'Company Name (optional)', 'seogen' ); ?></label></th>
							<td><input name="company_name" id="hl_bulk_company" type="text" class="regular-text" value="<?php echo esc_attr( (string) $defaults['company_name'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_bulk_phone"><?php echo esc_html__( 'Phone (optional)', 'seogen' ); ?></label></th>
							<td><input name="phone" id="hl_bulk_phone" type="text" class="regular-text" value="<?php echo esc_attr( (string) $defaults['phone'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_bulk_email"><?php echo esc_html__( 'Email (optional)', 'seogen' ); ?></label></th>
							<td><input name="email" id="hl_bulk_email" type="email" class="regular-text" value="<?php echo esc_attr( (string) $defaults['email'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_bulk_address"><?php echo esc_html__( 'Address (optional)', 'seogen' ); ?></label></th>
							<td><input name="address" id="hl_bulk_address" type="text" class="regular-text" value="<?php echo esc_attr( (string) $defaults['address'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Update existing drafts', 'seogen' ); ?></th>
							<td>
								<label><input type="checkbox" name="update_existing" value="1" <?php checked( (string) $defaults['update_existing'], '1' ); ?> /> <?php echo esc_html__( 'Update existing drafts instead of skipping', 'seogen' ); ?></label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Auto-publish pages', 'seogen' ); ?></th>
							<td>
								<label><input type="checkbox" name="auto_publish" value="1" <?php checked( (string) $defaults['auto_publish'], '1' ); ?> /> <?php echo esc_html__( 'Automatically publish pages instead of saving as drafts', 'seogen' ); ?></label>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Validate & Preview Rows', 'seogen' ), 'secondary', 'submit' ); ?>
			</form>

			<?php if ( is_array( $validated ) && isset( $validated['rows'] ) && is_array( $validated['rows'] ) ) : ?>
				<h2><?php echo esc_html__( 'Preview', 'seogen' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hyper_local_bulk_start" />
					<?php wp_nonce_field( 'hyper_local_bulk_start', 'hyper_local_bulk_start_nonce' ); ?>
					<?php submit_button( __( 'Start Bulk Generation', 'seogen' ), 'primary', 'submit' ); ?>
				</form>
				<table class="widefat striped" style="margin-top:12px;">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'Service', 'seogen' ); ?></th>
							<th><?php echo esc_html__( 'City', 'seogen' ); ?></th>
							<th><?php echo esc_html__( 'State', 'seogen' ); ?></th>
							<th><?php echo esc_html__( 'Canonical key', 'seogen' ); ?></th>
							<th><?php echo esc_html__( 'Slug preview', 'seogen' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $validated['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $row['service'] ); ?></td>
								<td><?php echo esc_html( (string) $row['city'] ); ?></td>
								<td><?php echo esc_html( (string) $row['state'] ); ?></td>
								<td><code><?php echo esc_html( (string) $row['key'] ); ?></code></td>
								<td><code><?php echo esc_html( (string) $row['slug_preview'] ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $jobs_index ) ) : ?>
				<h2><?php echo esc_html__( 'Recent Jobs', 'seogen' ); ?></h2>
				<ul>
					<?php foreach ( $jobs_index as $recent_job_id ) : ?>
						<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-bulk&job_id=' . sanitize_key( (string) $recent_job_id ) ) ); ?>"><?php echo esc_html( (string) $recent_job_id ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_bulk_validate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}
		check_admin_referer( 'hyper_local_bulk_validate', 'hyper_local_bulk_validate_nonce' );

		$form = array(
			'services'        => isset( $_POST['services'] ) ? (string) wp_unslash( $_POST['services'] ) : '',
			'service_areas'   => isset( $_POST['service_areas'] ) ? (string) wp_unslash( $_POST['service_areas'] ) : '',
			'company_name'    => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'phone'           => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'email'           => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'address'         => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'update_existing' => ( isset( $_POST['update_existing'] ) && '1' === (string) wp_unslash( $_POST['update_existing'] ) ) ? '1' : '0',
			'auto_publish'    => ( isset( $_POST['auto_publish'] ) && '1' === (string) wp_unslash( $_POST['auto_publish'] ) ) ? '1' : '0',
		);

		$services = $this->parse_bulk_lines( $form['services'] );
		$areas = $this->parse_service_areas( $form['service_areas'] );
		$unique = array();
		$preview = array();
		foreach ( $services as $service ) {
			$service = trim( (string) $service );
			if ( '' === $service ) {
				continue;
			}
			foreach ( $areas as $area ) {
				$city = isset( $area['city'] ) ? trim( (string) $area['city'] ) : '';
				$state = isset( $area['state'] ) ? trim( (string) $area['state'] ) : '';
				if ( '' === $city ) {
					continue;
				}
				// State is now optional - empty state is allowed
				$key = $this->compute_canonical_key( $service, $city, $state );
				if ( isset( $unique[ $key ] ) ) {
					continue;
				}
				$unique[ $key ] = true;
				$preview[] = array(
					'service'      => $service,
					'city'         => $city,
					'state'        => $state,
					'key'          => $key,
					'slug_preview' => $this->compute_slug_preview( $service, $city, $state ),
				);
			}
		}

		$user_id = get_current_user_id();
		$validate_key = $this->get_bulk_validate_transient_key( $user_id );
		set_transient(
			$validate_key,
			array(
				'form' => $form,
				'rows' => $preview,
			),
			30 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'admin.php?page=hyper-local-bulk' ) );
		exit;
	}

	public function handle_bulk_start() {
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] handle_bulk_start ENTRY' . PHP_EOL, FILE_APPEND );
		if ( ! current_user_can( 'manage_options' ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] handle_bulk_start FAILED: insufficient permissions' . PHP_EOL, FILE_APPEND );
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}
		check_admin_referer( 'hyper_local_bulk_start', 'hyper_local_bulk_start_nonce' );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] handle_bulk_start nonce verified' . PHP_EOL, FILE_APPEND );

		$user_id = get_current_user_id();
		$validate_key = $this->get_bulk_validate_transient_key( $user_id );
		$validated = get_transient( $validate_key );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Validation check: is_array=' . ( is_array( $validated ) ? 'yes' : 'no' ) . ' has_rows=' . ( isset( $validated['rows'] ) ? 'yes' : 'no' ) . ' has_form=' . ( isset( $validated['form'] ) ? 'yes' : 'no' ) . ' rows_count=' . ( isset( $validated['rows'] ) ? count( $validated['rows'] ) : 0 ) . PHP_EOL, FILE_APPEND );
		if ( ! is_array( $validated ) || ! isset( $validated['rows'] ) || ! is_array( $validated['rows'] ) || ! isset( $validated['form'] ) || ! is_array( $validated['form'] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] FAILED: validation data missing or expired' . PHP_EOL, FILE_APPEND );
			$redirect_url = admin_url( 'admin.php?page=hyper-local-bulk' );
			$redirect_url = add_query_arg(
				array(
					'hl_notice' => 'fail',
					'hl_msg'    => __( 'Validation data expired. Please click "Validate & Preview Rows" again before starting bulk generation.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
		
		if ( empty( $validated['rows'] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] FAILED: validation rows empty' . PHP_EOL, FILE_APPEND );
			$redirect_url = admin_url( 'admin.php?page=hyper-local-bulk' );
			$redirect_url = add_query_arg(
				array(
					'hl_notice' => 'fail',
					'hl_msg'    => __( 'No rows to process. Please enter services and service areas, then click "Validate & Preview Rows".', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Validation passed, processing ' . count( $validated['rows'] ) . ' rows' . PHP_EOL, FILE_APPEND );
		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API settings: url=' . $api_url . ' license_key=' . ( $license_key ? 'SET' : 'EMPTY' ) . PHP_EOL, FILE_APPEND );
		if ( '' === $api_url || '' === $license_key ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] FAILED: missing api_url or license_key' . PHP_EOL, FILE_APPEND );
			wp_safe_redirect( admin_url( 'admin.php?page=hyper-local-bulk' ) );
			exit;
		}

		$job_id = sanitize_key( 'hl_job_' . wp_generate_password( 12, false, false ) );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Generated job_id=' . $job_id . PHP_EOL, FILE_APPEND );
		$job_rows = array();
		foreach ( $validated['rows'] as $row ) {
			$job_rows[] = array(
				'service'      => isset( $row['service'] ) ? (string) $row['service'] : '',
				'city'         => isset( $row['city'] ) ? (string) $row['city'] : '',
				'state'        => isset( $row['state'] ) ? (string) $row['state'] : '',
				'key'          => isset( $row['key'] ) ? (string) $row['key'] : '',
				'slug_preview' => isset( $row['slug_preview'] ) ? (string) $row['slug_preview'] : '',
				'status'       => 'pending',
				'message'      => '',
				'post_id'      => 0,
			);
		}

		$form = $validated['form'];
		$job = array(
			'id'         => $job_id,
			'status'     => 'running',
			'created_by' => (int) $user_id,
			'created_at' => current_time( 'mysql' ),
			'total_rows' => count( $job_rows ),
			'processed'  => 0,
			'success'    => 0,
			'failed'     => 0,
			'skipped'    => 0,
			'mode'       => 'api',
			'api_job_id' => '',
			'api_cursor' => '',
			'update_existing' => ( isset( $form['update_existing'] ) && '1' === (string) $form['update_existing'] ) ? '1' : '0',
			'auto_publish'    => ( isset( $form['auto_publish'] ) && '1' === (string) $form['auto_publish'] ) ? '1' : '0',
			'inputs'     => array(
				'company_name' => isset( $form['company_name'] ) ? sanitize_text_field( (string) $form['company_name'] ) : '',
				'phone'        => isset( $form['phone'] ) ? sanitize_text_field( (string) $form['phone'] ) : '',
				'email'        => isset( $form['email'] ) ? sanitize_email( (string) $form['email'] ) : '',
				'address'      => isset( $form['address'] ) ? sanitize_text_field( (string) $form['address'] ) : '',
			),
			'rows'       => $job_rows,
		);

		$api_items = array();
		$job_name = ( isset( $form['job_name'] ) ? sanitize_text_field( (string) $form['job_name'] ) : '' );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Building API items from ' . count( $job_rows ) . ' job_rows' . PHP_EOL, FILE_APPEND );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Job inputs: ' . wp_json_encode( $job['inputs'] ) . PHP_EOL, FILE_APPEND );
		foreach ( $job_rows as $row ) {
			$api_items[] = array(
				'service'      => isset( $row['service'] ) ? (string) $row['service'] : '',
				'city'         => isset( $row['city'] ) ? (string) $row['city'] : '',
				'state'        => isset( $row['state'] ) ? (string) $row['state'] : '',
				'company_name' => isset( $job['inputs']['company_name'] ) ? (string) $job['inputs']['company_name'] : '',
				'phone'        => isset( $job['inputs']['phone'] ) ? (string) $job['inputs']['phone'] : '',
				'email'        => isset( $job['inputs']['email'] ) ? (string) $job['inputs']['email'] : '',
				'address'      => isset( $job['inputs']['address'] ) ? (string) $job['inputs']['address'] : '',
			);
		}
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API items being sent: ' . wp_json_encode( $api_items ) . PHP_EOL, FILE_APPEND );

		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Calling API with ' . count( $api_items ) . ' items' . PHP_EOL, FILE_APPEND );
		$created = $this->api_create_bulk_job( $api_url, $license_key, $job_name, $api_items );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API response: ' . wp_json_encode( $created ) . PHP_EOL, FILE_APPEND );
		if ( empty( $created['ok'] ) || ! is_array( $created['data'] ) || empty( $created['data']['job_id'] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API FAILED: ' . ( isset( $created['error'] ) ? $created['error'] : 'unknown' ) . PHP_EOL, FILE_APPEND );
			$redirect_url = admin_url( 'admin.php?page=hyper-local-bulk' );
			$redirect_url = add_query_arg(
				array(
					'hl_notice' => 'fail',
					'hl_msg'    => isset( $created['error'] ) ? (string) $created['error'] : __( 'Failed to create bulk job on API.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API SUCCESS: job_id=' . $created['data']['job_id'] . PHP_EOL, FILE_APPEND );
		$job['api_job_id'] = sanitize_text_field( (string) $created['data']['job_id'] );

		foreach ( $job['rows'] as $i => $row ) {
			$job['rows'][ $i ]['status'] = 'pending';
			$job['rows'][ $i ]['message'] = __( 'Queued on API.', 'seogen' );
		}
		$this->save_bulk_job( $job_id, $job );
		delete_transient( $validate_key );
		error_log( '[HyperLocal Bulk] created API job job_id=' . $job_id . ' api_job_id=' . $job['api_job_id'] . ' total_rows=' . count( $job_rows ) );

		wp_safe_redirect( admin_url( 'admin.php?page=hyper-local-bulk&job_id=' . $job_id ) );
		exit;
	}

	public function handle_bulk_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}
		$job_id = isset( $_GET['job_id'] ) ? sanitize_key( (string) wp_unslash( $_GET['job_id'] ) ) : '';
		check_admin_referer( 'hyper_local_bulk_export_' . $job_id, 'nonce' );
		$job = $this->load_bulk_job( $job_id );
		if ( ! is_array( $job ) ) {
			wp_die( esc_html__( 'Job not found.', 'seogen' ) );
		}

		$filename = 'hyper-local-bulk-' . $job_id . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			exit;
		}
		fputcsv( $out, array( 'service', 'city', 'state', 'status', 'message', 'post_url' ) );
		if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
			foreach ( $job['rows'] as $row ) {
				$post_url = '';
				if ( isset( $row['post_id'] ) && (int) $row['post_id'] > 0 ) {
					$post_url = get_permalink( (int) $row['post_id'] );
				}
				fputcsv(
					$out,
					array(
						isset( $row['service'] ) ? (string) $row['service'] : '',
						isset( $row['city'] ) ? (string) $row['city'] : '',
						isset( $row['state'] ) ? (string) $row['state'] : '',
						isset( $row['status'] ) ? (string) $row['status'] : '',
						isset( $row['message'] ) ? (string) $row['message'] : '',
						$post_url ? (string) $post_url : '',
					)
				);
			}
		}
		fclose( $out );
		exit;
	}

	public function ajax_bulk_job_status() {
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ajax_bulk_job_status called' . PHP_EOL, FILE_APPEND );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'hyper_local_bulk_job_status', 'nonce' );
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( (string) wp_unslash( $_POST['job_id'] ) ) : '';
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Polling for job_id=' . $job_id . PHP_EOL, FILE_APPEND );
		$job = $this->load_bulk_job( $job_id );
		if ( ! is_array( $job ) ) {
			wp_send_json_error();
		}
		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		$is_api_mode = ( isset( $job['mode'] ) && 'api' === (string) $job['mode'] && isset( $job['api_job_id'] ) && '' !== (string) $job['api_job_id'] );

		if ( $is_api_mode && '' !== $api_url && '' !== $license_key ) {
			$status = $this->api_get_bulk_job_status( $api_url, $license_key, $job['api_job_id'] );
			if ( ! empty( $status['ok'] ) && is_array( $status['data'] ) ) {
				$job['status'] = isset( $status['data']['status'] ) ? sanitize_text_field( (string) $status['data']['status'] ) : ( isset( $job['status'] ) ? $job['status'] : '' );
				$job['total_rows'] = isset( $status['data']['total_items'] ) ? (int) $status['data']['total_items'] : ( isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0 );
				$job['processed'] = isset( $status['data']['processed'] ) ? (int) $status['data']['processed'] : ( isset( $job['processed'] ) ? (int) $job['processed'] : 0 );
				$job['success'] = isset( $status['data']['completed'] ) ? (int) $status['data']['completed'] : ( isset( $job['success'] ) ? (int) $job['success'] : 0 );
				$job['failed'] = isset( $status['data']['failed'] ) ? (int) $status['data']['failed'] : ( isset( $job['failed'] ) ? (int) $job['failed'] : 0 );
			}

			$cursor = isset( $job['api_cursor'] ) ? (string) $job['api_cursor'] : '';
			
			// Dynamic batch size: fetch more items when job is complete or has many pending items
			$api_status = isset( $status['data']['status'] ) ? (string) $status['data']['status'] : '';
			$total_items = isset( $status['data']['total_items'] ) ? (int) $status['data']['total_items'] : 0;
			$completed_items = isset( $status['data']['completed'] ) ? (int) $status['data']['completed'] : 0;
			$local_success_count = 0;
			if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
				foreach ( $job['rows'] as $row ) {
					if ( isset( $row['status'] ) && 'success' === $row['status'] ) {
						$local_success_count++;
					}
				}
			}
			$pending_import_count = $completed_items - $local_success_count;
			
			// Use larger batch size when:
			// 1. Job is complete (fetch all remaining)
			// 2. Many items pending import (catch up faster)
			$batch_size = 10; // Default
			if ( 'complete' === $api_status || 'completed' === $api_status ) {
				$batch_size = 100; // Fetch all remaining when job is done
			} elseif ( $pending_import_count > 50 ) {
				$batch_size = 50; // Catch up faster if falling behind
			} elseif ( $pending_import_count > 20 ) {
				$batch_size = 25;
			}
			
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Fetching results: api_job_id=' . $job['api_job_id'] . ' cursor=' . $cursor . ' batch_size=' . $batch_size . ' api_status=' . $api_status . ' pending_import=' . $pending_import_count . PHP_EOL, FILE_APPEND );
			$results = $this->api_get_bulk_job_results( $api_url, $license_key, $job['api_job_id'], $cursor, $batch_size );
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API results: ' . wp_json_encode( $results ) . PHP_EOL, FILE_APPEND );
			$acked_ids = array();
			if ( ! empty( $results['ok'] ) && is_array( $results['data'] ) && isset( $results['data']['items'] ) && is_array( $results['data']['items'] ) ) {
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Processing ' . count( $results['data']['items'] ) . ' result items. Job has ' . count( $job['rows'] ) . ' rows.' . PHP_EOL, FILE_APPEND );
				$update_existing = ( isset( $job['update_existing'] ) && '1' === (string) $job['update_existing'] );
				foreach ( $results['data']['items'] as $item ) {
					$item_id = isset( $item['item_id'] ) ? (string) $item['item_id'] : '';
					$idx = isset( $item['idx'] ) ? (int) $item['idx'] : -1;
					$canonical_key = isset( $item['canonical_key'] ) ? (string) $item['canonical_key'] : '';
					$item_status = isset( $item['status'] ) ? (string) $item['status'] : '';
					$result_json = isset( $item['result_json'] ) && is_array( $item['result_json'] ) ? $item['result_json'] : null;
					$error = isset( $item['error'] ) ? (string) $item['error'] : '';
					
					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Processing item: idx=' . $idx . ' status=' . $item_status . ' item_id=' . $item_id . PHP_EOL, FILE_APPEND );
					if ( '' === $item_id || $idx < 0 ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping item: invalid item_id or idx' . PHP_EOL, FILE_APPEND );
						continue;
					}
					
					// Skip items already successfully imported in local job state
					if ( isset( $job['rows'][ $idx ] ) && 'success' === $job['rows'][ $idx ]['status'] && 'completed' === $item_status ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping item: already imported (local status=success)' . PHP_EOL, FILE_APPEND );
						$acked_ids[] = $item_id;
						continue;
					}
					
					// Handle retry case: item was failed locally but is now completed in API
					if ( isset( $job['rows'][ $idx ] ) && 'failed' === $job['rows'][ $idx ]['status'] && 'completed' === $item_status ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Retry success: item was failed locally but now completed in API, will import' . PHP_EOL, FILE_APPEND );
						// Continue processing to import this item
					}
					
					if ( 'failed' === $item_status ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Item failed: ' . $error . PHP_EOL, FILE_APPEND );
						if ( isset( $job['rows'][ $idx ] ) ) {
							$job['rows'][ $idx ]['status'] = 'failed';
							$job['rows'][ $idx ]['message'] = '' !== $error ? $error : __( 'Generation failed.', 'seogen' );
							$job['rows'][ $idx ]['post_id'] = 0;
						}
						// Only ack permanently failed items (attempts >= 2) so retries can be re-fetched
						$item_attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;
						if ( $item_attempts >= 2 ) {
							file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Permanently failed (attempts=' . $item_attempts . '), acking item' . PHP_EOL, FILE_APPEND );
							$acked_ids[] = $item_id;
						} else {
							file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Failed but may retry (attempts=' . $item_attempts . '), NOT acking item' . PHP_EOL, FILE_APPEND );
						}
						continue;
					}
					
					if ( ! is_array( $result_json ) ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping item: result_json not array' . PHP_EOL, FILE_APPEND );
						if ( isset( $job['rows'][ $idx ] ) ) {
							$job['rows'][ $idx ]['status'] = 'failed';
							$job['rows'][ $idx ]['message'] = __( 'Invalid result data from API.', 'seogen' );
							$job['rows'][ $idx ]['post_id'] = 0;
						}
						$acked_ids[] = $item_id;
						continue;
					}

					$post_id = 0;
					$existing_id = ( '' !== $canonical_key ) ? $this->find_existing_post_id_by_key( $canonical_key ) : 0;
					if ( $existing_id > 0 && ! $update_existing ) {
						$post_id = $existing_id;
						if ( isset( $job['rows'][ $idx ] ) ) {
							$job['rows'][ $idx ]['status'] = 'skipped';
							$job['rows'][ $idx ]['message'] = __( 'Existing page found for key; skipping import.', 'seogen' );
							$job['rows'][ $idx ]['post_id'] = $existing_id;
						}
						$acked_ids[] = $item_id;
						continue;
					}

					$title = isset( $result_json['title'] ) ? (string) $result_json['title'] : '';
					$slug = isset( $result_json['slug'] ) ? (string) $result_json['slug'] : '';
					$meta_description = isset( $result_json['meta_description'] ) ? (string) $result_json['meta_description'] : '';
					$blocks = ( isset( $result_json['blocks'] ) && is_array( $result_json['blocks'] ) ) ? $result_json['blocks'] : array();
					$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : '';
					$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );

					// Prepend header template if configured
					$settings = $this->get_settings();
					$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
					if ( $header_template_id > 0 ) {
						$header_content = $this->get_template_content( $header_template_id );
						if ( '' !== $header_content ) {
							// Add CSS to remove top spacing from content area
							$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
							$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
						}
					}

					// Append footer template if configured
					$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
					if ( $footer_template_id > 0 ) {
						$footer_content = $this->get_template_content( $footer_template_id );
						if ( '' !== $footer_content ) {
							// Add CSS to remove bottom spacing from content area
							$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
							$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
						}
					}

					$auto_publish = isset( $job['auto_publish'] ) && '1' === (string) $job['auto_publish'];
					$post_status = $auto_publish ? 'publish' : 'draft';

					$postarr = array(
						'post_type'    => 'service_page',
						'post_status'  => $post_status,
						'post_title'   => $title,
						'post_name'    => sanitize_title( $slug ),
						'post_content' => $gutenberg_markup,
					);

					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Creating/updating post: title=' . $title . ' slug=' . $slug . ' status=' . $post_status . PHP_EOL, FILE_APPEND );
					if ( $existing_id > 0 && $update_existing ) {
						$postarr['ID'] = $existing_id;
						$post_id = wp_update_post( $postarr, true );
					} else {
						$post_id = wp_insert_post( $postarr, true );
					}
					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Post created/updated: post_id=' . ( is_wp_error( $post_id ) ? 'ERROR' : $post_id ) . PHP_EOL, FILE_APPEND );

					if ( is_wp_error( $post_id ) ) {
						if ( isset( $job['rows'][ $idx ] ) ) {
							$job['rows'][ $idx ]['status'] = 'failed';
							$job['rows'][ $idx ]['message'] = $post_id->get_error_message();
							$job['rows'][ $idx ]['post_id'] = 0;
						}
						$acked_ids[] = $item_id;
						continue;
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

					update_post_meta( $post_id, '_hyper_local_managed', '1' );
					update_post_meta( $post_id, '_hl_page_type', 'service_city' );
					if ( '' !== $canonical_key ) {
						update_post_meta( $post_id, '_hyper_local_key', $canonical_key );
					}
					update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
					update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
					update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );

					// Store _seogen meta keys for City Hub service links query compatibility
					update_post_meta( $post_id, '_seogen_page_mode', 'service_city' );
					
					// Extract service, city, state from job row data
					if ( isset( $job['rows'][ $idx ] ) ) {
						$row = $job['rows'][ $idx ];
						
						// Store service name and slug
						if ( isset( $row['service'] ) && ! empty( $row['service'] ) ) {
							$service_name = sanitize_text_field( $row['service'] );
							update_post_meta( $post_id, '_seogen_service_name', $service_name );
							update_post_meta( $post_id, '_seogen_service_slug', sanitize_title( $service_name ) );
							
							// Try to determine hub_key from service name
							$services = $this->get_services();
							$hub_key_found = false;
							
							if ( ! empty( $services ) ) {
								// Try exact match first
								foreach ( $services as $service ) {
									if ( isset( $service['name'], $service['hub_key'] ) && strtolower( trim( $service['name'] ) ) === strtolower( trim( $service_name ) ) ) {
										update_post_meta( $post_id, '_seogen_hub_key', $service['hub_key'] );
										$hub_key_found = true;
										error_log( sprintf( '[SEOgen] Matched service "%s" to hub_key "%s" (exact match)', $service_name, $service['hub_key'] ) );
										break;
									}
								}
								
								// If no exact match, try partial match (service name contains cache name or vice versa)
								if ( ! $hub_key_found ) {
									foreach ( $services as $service ) {
										if ( isset( $service['name'], $service['hub_key'] ) ) {
											$cache_name_lower = strtolower( trim( $service['name'] ) );
											$input_name_lower = strtolower( trim( $service_name ) );
											
											// Check if either name contains the other
											if ( strpos( $input_name_lower, $cache_name_lower ) !== false || strpos( $cache_name_lower, $input_name_lower ) !== false ) {
												update_post_meta( $post_id, '_seogen_hub_key', $service['hub_key'] );
												$hub_key_found = true;
												error_log( sprintf( '[SEOgen] Matched service "%s" to hub_key "%s" (partial match with "%s")', $service_name, $service['hub_key'], $service['name'] ) );
												break;
											}
										}
									}
								}
								
								// If still no match, use first hub as fallback
								if ( ! $hub_key_found && ! empty( $services ) ) {
									$first_service = reset( $services );
									if ( isset( $first_service['hub_key'] ) ) {
										update_post_meta( $post_id, '_seogen_hub_key', $first_service['hub_key'] );
										error_log( sprintf( '[SEOgen] No match for service "%s", using fallback hub_key "%s"', $service_name, $first_service['hub_key'] ) );
									}
								}
							}
							
							if ( ! $hub_key_found ) {
								error_log( sprintf( '[SEOgen] WARNING: Could not determine hub_key for service "%s". Services cache: %s', $service_name, wp_json_encode( $services ) ) );
							}
						}
						
						// Store city and city_slug
						if ( isset( $row['city'] ) && ! empty( $row['city'] ) ) {
							$city = sanitize_text_field( $row['city'] );
							$state = isset( $row['state'] ) && ! empty( $row['state'] ) ? sanitize_text_field( $row['state'] ) : '';
							
							if ( ! empty( $state ) ) {
								update_post_meta( $post_id, '_seogen_city', $city . ', ' . $state );
								update_post_meta( $post_id, '_seogen_city_slug', sanitize_title( $city . '-' . $state ) );
							} else {
								update_post_meta( $post_id, '_seogen_city', $city );
								update_post_meta( $post_id, '_seogen_city_slug', sanitize_title( $city ) );
							}
						}
						
						// Store vertical from business config
						$config = $this->get_business_config();
						if ( isset( $config['vertical'] ) && ! empty( $config['vertical'] ) ) {
							update_post_meta( $post_id, '_seogen_vertical', $config['vertical'] );
						}
					}

					// Apply page builder settings to disable theme header/footer if configured
					if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
						$this->apply_page_builder_settings( $post_id );
					}

					$service_for_meta = '';
					if ( isset( $job['rows'][ $idx ] ) && isset( $job['rows'][ $idx ]['service'] ) ) {
						$service_for_meta = sanitize_text_field( (string) $job['rows'][ $idx ]['service'] );
					}
					$this->apply_seo_plugin_meta( $post_id, $service_for_meta, $title, $meta_description, true );

					if ( isset( $job['rows'][ $idx ] ) ) {
						$job['rows'][ $idx ]['status'] = 'success';
						$job['rows'][ $idx ]['message'] = __( 'Imported.', 'seogen' );
						$job['rows'][ $idx ]['post_id'] = $post_id;
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Updated row status: idx=' . $idx . ' status=success post_id=' . $post_id . PHP_EOL, FILE_APPEND );
					} else {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: Cannot update row status - job[rows][' . $idx . '] does not exist. Total rows: ' . count( $job['rows'] ) . PHP_EOL, FILE_APPEND );
					}
					$acked_ids[] = $item_id;
				}
				if ( isset( $results['data']['next_cursor'] ) ) {
					$job['api_cursor'] = sanitize_text_field( (string) $results['data']['next_cursor'] );
				}
				
				// If job is complete and we still have a cursor, schedule immediate re-poll to fetch remaining items
				if ( ( 'complete' === $api_status || 'completed' === $api_status ) && isset( $results['data']['next_cursor'] ) && '' !== $results['data']['next_cursor'] ) {
					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Job complete but more items to fetch, will continue polling' . PHP_EOL, FILE_APPEND );
				}
			}
			if ( ! empty( $acked_ids ) ) {
				$this->api_ack_bulk_job_items( $api_url, $license_key, $job['api_job_id'], $acked_ids );
			}
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Saving job with ' . count( $acked_ids ) . ' acked items' . PHP_EOL, FILE_APPEND );
			$this->save_bulk_job( $job_id, $job );
			
			// Schedule background processing if job is still running and user might navigate away
			// Action Scheduler will continue processing even if user leaves or computer sleeps
			if ( ( 'running' === $api_status || 'complete' === $api_status || 'completed' === $api_status ) && $pending_import_count > 0 ) {
				// Schedule immediate re-processing to fetch remaining items
				// This ensures all completed items get imported even if user navigates away
				$this->schedule_bulk_job( $job_id, 2 );
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Scheduled background import: pending=' . $pending_import_count . PHP_EOL, FILE_APPEND );
			}
		} else {
			if ( isset( $job['status'] ) && in_array( (string) $job['status'], array( 'pending', 'running' ), true ) ) {
				$this->schedule_bulk_job( $job_id );
			}
		}
		
		// Sync row statuses from actual WordPress posts on every poll
		// This fixes stale "pending" and "skipped" statuses for items that were actually imported
		$job_status = isset( $job['status'] ) ? (string) $job['status'] : '';
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC: job_status=' . $job_status . ' rows_count=' . ( isset( $job['rows'] ) ? count( $job['rows'] ) : 0 ) . PHP_EOL, FILE_APPEND );
		// Run status sync on every poll for API mode jobs
		if ( $is_api_mode && isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
			$status_updated = false;
			foreach ( $job['rows'] as $idx => $row ) {
				$row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
				// If row shows pending/skipped but job is complete, verify against actual WordPress posts
				if ( in_array( $row_status, array( 'pending', 'skipped' ), true ) ) {
					$canonical_key = isset( $row['service'] ) && isset( $row['city'] ) && isset( $row['state'] ) 
						? sanitize_title( $row['service'] . '-' . $row['city'] . '-' . $row['state'] ) 
						: '';
					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC CHECK: idx=' . $idx . ' status=' . $row_status . ' key=' . $canonical_key . PHP_EOL, FILE_APPEND );
					if ( '' !== $canonical_key ) {
						$existing_id = $this->find_existing_post_id_by_key( $canonical_key );
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC LOOKUP: existing_id=' . $existing_id . PHP_EOL, FILE_APPEND );
						
						// If key lookup fails, try alternate key format (pipe-separated)
						if ( $existing_id === 0 && isset( $row['service'] ) && isset( $row['city'] ) && isset( $row['state'] ) ) {
							$alt_key = strtolower( $row['service'] . '|' . $row['city'] . '|' . $row['state'] );
							$existing_id = $this->find_existing_post_id_by_key( $alt_key );
							file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC ALT KEY: alt_key=' . $alt_key . ' existing_id=' . $existing_id . PHP_EOL, FILE_APPEND );
						}
						
						// If still not found, check if post_id is stored in the row already
						if ( $existing_id === 0 && isset( $row['post_id'] ) && (int) $row['post_id'] > 0 ) {
							$stored_post_id = (int) $row['post_id'];
							$stored_key = get_post_meta( $stored_post_id, '_hyper_local_key', true );
							file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC DEBUG: row has post_id=' . $stored_post_id . ' stored_key=' . $stored_key . PHP_EOL, FILE_APPEND );
							// Use the stored post_id if it exists
							if ( $stored_post_id > 0 ) {
								$existing_id = $stored_post_id;
							}
						}
						if ( $existing_id > 0 ) {
							// Check if post was generated by this system (has the managed meta)
							$is_managed = get_post_meta( $existing_id, '_hyper_local_managed', true );
							file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC META: is_managed=' . $is_managed . PHP_EOL, FILE_APPEND );
							if ( '1' === $is_managed ) {
								// Post exists and was generated by us, update status to success
								$job['rows'][ $idx ]['status'] = 'success';
								$job['rows'][ $idx ]['message'] = __( 'Imported.', 'seogen' );
								$job['rows'][ $idx ]['post_id'] = $existing_id;
								$status_updated = true;
								file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC UPDATED: idx=' . $idx . ' to success' . PHP_EOL, FILE_APPEND );
							}
						}
					}
				}
			}
			if ( $status_updated ) {
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC: Saving updated job' . PHP_EOL, FILE_APPEND );
				$this->save_bulk_job( $job_id, $job );
			} else {
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] STATUS SYNC: No updates needed' . PHP_EOL, FILE_APPEND );
			}
		}
		
		$rows = array();
		// For API mode, use API counters (source of truth from database)
		// For non-API mode, count from local row statuses
		if ( $is_api_mode && isset( $job['processed'] ) ) {
			// Use API counters directly
			$processed_count = isset( $job['processed'] ) ? (int) $job['processed'] : 0;
			$success_count = isset( $job['success'] ) ? (int) $job['success'] : 0;
			$failed_count = isset( $job['failed'] ) ? (int) $job['failed'] : 0;
			$skipped_count = 0; // API doesn't track skipped separately
		} else {
			// Fallback: count from local row statuses for non-API mode
			$success_count = 0;
			$failed_count = 0;
			$skipped_count = 0;
			$processed_count = 0;
		}
		
		if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
			foreach ( $job['rows'] as $row ) {
				$row_status = isset( $row['status'] ) ? (string) $row['status'] : '';
				// Only count for non-API mode
				if ( ! $is_api_mode ) {
					if ( 'success' === $row_status ) {
						$success_count++;
						$processed_count++;
					} elseif ( 'failed' === $row_status ) {
						$failed_count++;
						$processed_count++;
					} elseif ( 'skipped' === $row_status ) {
						$skipped_count++;
						$processed_count++;
					} elseif ( in_array( $row_status, array( 'processing', 'running' ), true ) ) {
						$processed_count++;
					} elseif ( 'pending' === $row_status && 'complete' === $job_status ) {
						$processed_count++;
					}
				}
				$edit_url = '';
				if ( isset( $row['post_id'] ) && (int) $row['post_id'] > 0 ) {
					$edit_url = get_edit_post_link( (int) $row['post_id'], 'raw' );
				}
				$rows[] = array(
					'service'  => isset( $row['service'] ) ? (string) $row['service'] : '',
					'city'     => isset( $row['city'] ) ? (string) $row['city'] : '',
					'state'    => isset( $row['state'] ) ? (string) $row['state'] : '',
					'status'   => $row_status,
					'message'  => isset( $row['message'] ) ? (string) $row['message'] : '',
					'edit_url' => $edit_url ? (string) $edit_url : '',
				);
			}
		}
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Returning AJAX response: total=' . count( $rows ) . ' processed=' . $processed_count . ' success=' . $success_count . ' failed=' . $failed_count . ' skipped=' . $skipped_count . PHP_EOL, FILE_APPEND );
		wp_send_json_success(
			array(
				'id'         => (string) $job_id,
				'status'     => isset( $job['status'] ) ? (string) $job['status'] : '',
				'total_rows' => isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0,
				'processed'  => $processed_count,
				'success'    => $success_count,
				'failed'     => $failed_count,
				'skipped'    => $skipped_count,
				'rows'       => $rows,
			)
		);
	}

	public function ajax_bulk_job_cancel() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'hyper_local_bulk_job_cancel', 'nonce' );
		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( (string) wp_unslash( $_POST['job_id'] ) ) : '';
		$job = $this->load_bulk_job( $job_id );
		if ( ! is_array( $job ) ) {
			wp_send_json_error();
		}
		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		if ( isset( $job['mode'] ) && 'api' === (string) $job['mode'] && isset( $job['api_job_id'] ) && '' !== (string) $job['api_job_id'] && '' !== $api_url && '' !== $license_key ) {
			$this->api_cancel_bulk_job( $api_url, $license_key, $job['api_job_id'] );
		}
		$job['status'] = 'canceled';
		$this->save_bulk_job( $job_id, $job );
		wp_send_json_success();
	}

	public function process_bulk_job( $job_id, $allow_schedule = true ) {
		if ( is_array( $job_id ) && isset( $job_id['job_id'] ) ) {
			$job_id = $job_id['job_id'];
		}
		$job_id = sanitize_key( (string) $job_id );
		if ( '' === $job_id ) {
			return;
		}
		$job = $this->load_bulk_job( $job_id );
		if ( ! is_array( $job ) ) {
			error_log( '[HyperLocal Bulk] missing job job_id=' . $job_id );
			return;
		}

		if ( isset( $job['status'] ) && 'canceled' === (string) $job['status'] ) {
			return;
		}
		if ( isset( $job['status'] ) && 'complete' === (string) $job['status'] ) {
			return;
		}

		error_log( '[HyperLocal Bulk] running batch job_id=' . $job_id . ' processed=' . ( isset( $job['processed'] ) ? (int) $job['processed'] : 0 ) . ' total=' . ( isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0 ) );

		if ( ! isset( $job['rows'] ) || ! is_array( $job['rows'] ) ) {
			$job['status'] = 'failed';
			$this->save_bulk_job( $job_id, $job );
			return;
		}

		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		if ( '' === $api_url || '' === $license_key ) {
			$job['status'] = 'failed';
			$this->save_bulk_job( $job_id, $job );
			return;
		}

		$job['status'] = 'running';
		$batch_size = 3;
		$parallel_requests = 3; // Process 3 pages simultaneously
		$processed_in_run = 0;
		$update_existing = ( isset( $job['update_existing'] ) && '1' === (string) $job['update_existing'] );
		$common_inputs = ( isset( $job['inputs'] ) && is_array( $job['inputs'] ) ) ? $job['inputs'] : array();
		$company_name = isset( $common_inputs['company_name'] ) ? trim( (string) $common_inputs['company_name'] ) : '';
		$phone = isset( $common_inputs['phone'] ) ? trim( (string) $common_inputs['phone'] ) : '';
		$email = isset( $common_inputs['email'] ) ? trim( (string) $common_inputs['email'] ) : '';
		$address = isset( $common_inputs['address'] ) ? trim( (string) $common_inputs['address'] ) : '';
		if ( '' === $company_name ) {
			$company_name = 'Local Business';
		}
		if ( '' === $phone ) {
			$phone = '(000) 000-0000';
		}
		if ( '' === $address ) {
			$address = '123 Main St';
		}

		// Collect items to process in parallel
		$items_to_process = array();
		foreach ( $job['rows'] as $i => $row ) {
			if ( count( $items_to_process ) >= $batch_size ) {
				break;
			}
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['status'] ) && in_array( (string) $row['status'], array( 'success', 'failed', 'skipped' ), true ) ) {
				continue;
			}
			if ( isset( $job['status'] ) && 'canceled' === (string) $job['status'] ) {
				break;
			}

			$service = isset( $row['service'] ) ? sanitize_text_field( (string) $row['service'] ) : '';
			$city = isset( $row['city'] ) ? sanitize_text_field( (string) $row['city'] ) : '';
			$state = isset( $row['state'] ) ? sanitize_text_field( (string) $row['state'] ) : '';
			$key = isset( $row['key'] ) ? (string) $row['key'] : $this->compute_canonical_key( $service, $city, $state );
			$key = trim( $key );

			if ( '' === $service || '' === $city || '' === $state || '' === $key ) {
				$job['rows'][ $i ]['status'] = 'failed';
				$job['rows'][ $i ]['message'] = __( 'Missing required fields.', 'seogen' );
				$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				continue;
			}

			$existing_id = $this->find_existing_post_id_by_key( $key );
			if ( $existing_id > 0 && ! $update_existing ) {
				$job['rows'][ $i ]['status'] = 'skipped';
				$job['rows'][ $i ]['message'] = __( 'Existing page found for key; skipping.', 'seogen' );
				$job['rows'][ $i ]['post_id'] = $existing_id;
				$job['skipped'] = isset( $job['skipped'] ) ? ( (int) $job['skipped'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				continue;
			}

			$items_to_process[] = array(
				'index' => $i,
				'service' => $service,
				'city' => $city,
				'state' => $state,
				'key' => $key,
				'existing_id' => $existing_id,
			);
		}

		// Process items in parallel batches
		$url = trailingslashit( $api_url ) . 'generate-page';
		while ( ! empty( $items_to_process ) ) {
			$current_batch = array_splice( $items_to_process, 0, $parallel_requests );
			$requests = array();
			$request_map = array();

			// Prepare parallel requests
			foreach ( $current_batch as $item ) {
				$inputs = array(
					'service'      => $item['service'],
					'city'         => $item['city'],
					'state'        => $item['state'],
					'company_name' => sanitize_text_field( $company_name ),
					'phone'        => sanitize_text_field( $phone ),
					'email'        => sanitize_email( $email ),
					'address'      => sanitize_text_field( $address ),
				);
				$payload = $this->build_generate_preview_payload( $settings, $inputs );
				$payload['preview'] = false;

				$request_id = 'req_' . $item['index'];
				$requests[ $request_id ] = array(
					'url' => $url,
					'type' => 'POST',
					'headers' => array( 'Content-Type' => 'application/json' ),
					'data' => wp_json_encode( $payload ),
					'timeout' => 90,
				);
				$request_map[ $request_id ] = $item;
			}

			// Execute parallel requests
			$responses = \Requests::request_multiple( $requests );

			// Process responses
			foreach ( $responses as $request_id => $response ) {
				$item = $request_map[ $request_id ];
				$i = $item['index'];
				$key = $item['key'];
				$existing_id = $item['existing_id'];

				if ( is_wp_error( $response ) || ! is_object( $response ) ) {
					error_log( '[HyperLocal Bulk] API error job_id=' . $job_id . ' row=' . $key );
					$job['rows'][ $i ]['status'] = 'failed';
					$job['rows'][ $i ]['message'] = is_wp_error( $response ) ? $response->get_error_message() : 'Request failed';
					$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
					$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
					$processed_in_run++;
					continue;
				}

				$code = (int) $response->status_code;
				$body = (string) $response->body;

				if ( 200 !== $code ) {
					error_log( '[HyperLocal Bulk] API http error job_id=' . $job_id . ' row=' . $key . ' code=' . $code );
					$job['rows'][ $i ]['status'] = 'failed';
					$job['rows'][ $i ]['message'] = $this->format_bulk_api_error_message( $code, $body );
					$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
					$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
					$processed_in_run++;
					continue;
				}

				$full_data = json_decode( $body, true );
				if ( ! is_array( $full_data ) || ! isset( $full_data['blocks'] ) || ! is_array( $full_data['blocks'] ) ) {
					error_log( '[HyperLocal Bulk] API invalid JSON job_id=' . $job_id . ' row=' . $key );
					$job['rows'][ $i ]['status'] = 'failed';
					$job['rows'][ $i ]['message'] = __( 'API response was not valid JSON.', 'seogen' );
					$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
					$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
					$processed_in_run++;
					continue;
				}

				$title = isset( $full_data['title'] ) ? (string) $full_data['title'] : '';
				$slug = isset( $full_data['slug'] ) ? (string) $full_data['slug'] : '';
				$meta_description = isset( $full_data['meta_description'] ) ? (string) $full_data['meta_description'] : '';
				$page_mode = isset( $full_data['page_mode'] ) ? $full_data['page_mode'] : '';
				$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $full_data['blocks'], $page_mode );

				$auto_publish = isset( $job['auto_publish'] ) && '1' === (string) $job['auto_publish'];
				$post_status = $auto_publish ? 'publish' : 'draft';

				$postarr = array(
					'post_type'    => 'service_page',
					'post_status'  => $post_status,
					'post_title'   => $title,
					'post_name'    => sanitize_title( $slug ),
					'post_content' => $gutenberg_markup,
				);

				// Apply template setting to postarr if header/footer should be disabled
				$settings = $this->get_settings();
				if ( ! empty( $settings['disable_theme_header_footer'] ) && class_exists( '\Elementor\Plugin' ) ) {
					$postarr['page_template'] = 'elementor_header_footer';
				}

				$post_id = 0;
				if ( $existing_id > 0 && $update_existing ) {
					$postarr['ID'] = $existing_id;
					$post_id = wp_update_post( $postarr, true );
				} else {
					$post_id = wp_insert_post( $postarr, true );
				}

				if ( is_wp_error( $post_id ) ) {
					$error_msg = $post_id->get_error_message();
					$this->log_debug( "Failed to insert/update post for item_id={$item_id}: {$error_msg}" );
					continue;
				}

				$post_id = (int) $post_id;
				$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, $post_status, 'service_page', 0 );

				update_post_meta( $post_id, '_hyper_local_key', $key );
				update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
				
				// Apply page builder settings to disable theme header/footer if configured
				if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
					$this->apply_page_builder_settings( $post_id );
				}

				$job['rows'][ $i ]['status'] = 'success';
				$job['rows'][ $i ]['message'] = __( 'Page created successfully.', 'seogen' );
				$job['rows'][ $i ]['post_id'] = $post_id;
				$job['success'] = isset( $job['success'] ) ? ( (int) $job['success'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				$processed_in_run++;
			}
		}

		// Check for remaining pending items
		$has_pending = false;
		foreach ( $job['rows'] as $row ) {
			if ( is_array( $row ) && ( ! isset( $row['status'] ) || 'pending' === (string) $row['status'] ) ) {
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

		$this->save_bulk_job( $job_id, $job );
		if ( $allow_schedule && 'running' === (string) $job['status'] ) {
			$this->schedule_bulk_job( $job_id, 10 );
		}
	}

	public function add_bulk_actions( $bulk_actions ) {
		// WordPress already includes 'trash' by default for post types with delete capability
		// This filter ensures the bulk actions dropdown is properly populated
		// The checkbox column with "Select All" is automatically included by WordPress
		return $bulk_actions;
	}

	public function handle_select_all_bulk_action( $redirect_to, $doaction, $post_ids ) {
		// Log what we receive to seogen-debug.log
		$log_file = WP_CONTENT_DIR . '/seogen-debug.log';
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] doaction: ' . $doaction . PHP_EOL, FILE_APPEND );
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] post_ids count: ' . count( $post_ids ) . PHP_EOL, FILE_APPEND );
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] seogen_select_all: ' . ( isset( $_REQUEST['seogen_select_all'] ) ? $_REQUEST['seogen_select_all'] : 'NOT SET' ) . PHP_EOL, FILE_APPEND );
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] $_POST keys: ' . implode( ', ', array_keys( $_POST ) ) . PHP_EOL, FILE_APPEND );
		
		// Check if "Select All" was used
		if ( isset( $_REQUEST['seogen_select_all'] ) && '1' === $_REQUEST['seogen_select_all'] ) {
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] Select All detected, fetching all posts' . PHP_EOL, FILE_APPEND );
			
			// Get all service_page post IDs (not in trash)
			$args = array(
				'post_type'      => 'service_page',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			);
			$all_post_ids = get_posts( $args );
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] Found ' . count( $all_post_ids ) . ' posts to process' . PHP_EOL, FILE_APPEND );
			
			// Perform the bulk action on all posts
			if ( 'trash' === $doaction && ! empty( $all_post_ids ) ) {
				$trashed = 0;
				foreach ( $all_post_ids as $post_id ) {
					$result = wp_trash_post( $post_id );
					if ( $result ) {
						$trashed++;
					}
				}
				file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [SELECT ALL] Trashed ' . $trashed . ' posts' . PHP_EOL, FILE_APPEND );
				$redirect_to = add_query_arg( 'trashed', $trashed, $redirect_to );
			}
		}
		
		return $redirect_to;
	}

	public function enqueue_admin_scripts( $hook ) {
		// Only load on the service_page edit screen
		if ( 'edit.php' !== $hook ) {
			return;
		}
		
		$screen = get_current_screen();
		if ( ! $screen || 'service_page' !== $screen->post_type ) {
			return;
		}
		
		// Add inline JavaScript for Select All functionality
		wp_add_inline_script( 'jquery', "
		jQuery(document).ready(function($) {
			var selectAllEnabled = false;
			console.log('[SEOgen Select All] JavaScript loaded');
			
			// Add 'Select All' notice when header checkbox is clicked
			var headerCheckbox = $('#cb-select-all-1, #cb-select-all-2');
			var allCheckboxes = $('tbody .check-column input[type=\"checkbox\"]');
			console.log('[SEOgen Select All] Found ' + headerCheckbox.length + ' header checkboxes');
			
			headerCheckbox.on('change', function() {
				console.log('[SEOgen Select All] Header checkbox changed, checked=' + $(this).prop('checked'));
				if ($(this).prop('checked')) {
					// Show notice to select all items across all pages
					var totalItems = $('.displaying-num').text().match(/\\d+/);
					if (totalItems && totalItems[0]) {
						var count = parseInt(totalItems[0]);
						var visibleCount = allCheckboxes.length;
						console.log('[SEOgen Select All] Total items: ' + count + ', Visible: ' + visibleCount);
						
						if (count > visibleCount) {
							// Remove existing notice
							$('.seogen-select-all-notice').remove();
							selectAllEnabled = false;
							
							// Add notice above the table
							var notice = $('<div class=\"seogen-select-all-notice\" style=\"background: #e5f5fa; border-left: 4px solid #00a0d2; padding: 12px; margin: 10px 0;\"></div>');
							notice.html('<strong>All ' + visibleCount + ' items on this page are selected.</strong> <a href=\"#\" class=\"seogen-select-all-link\" style=\"text-decoration: underline;\">Select all ' + count + ' items</a>');
							$('.wp-list-table').before(notice);
							console.log('[SEOgen Select All] Notice added');
							
							// Handle click on 'Select all X items' link
							$('.seogen-select-all-link').on('click', function(e) {
								e.preventDefault();
								selectAllEnabled = true;
								console.log('[SEOgen Select All] Select All link clicked, enabled=true');
								$(this).replaceWith('<span>All ' + count + ' items are selected.</span>');
							});
						}
					}
				} else {
					$('.seogen-select-all-notice').remove();
					selectAllEnabled = false;
					console.log('[SEOgen Select All] Header checkbox unchecked, notice removed');
				}
			});
			
			// Intercept both form submission and doaction button clicks
			$('#posts-filter').on('submit', function(e) {
				console.log('[SEOgen Select All] Form submit event fired, selectAllEnabled=' + selectAllEnabled);
			});
			
			// Also intercept the doaction button clicks (WordPress uses these)
			$('#doaction, #doaction2').on('click', function(e) {
				console.log('[SEOgen Select All] Doaction button clicked, selectAllEnabled=' + selectAllEnabled);
				
				// Remove any existing hidden input first
				$('input[name=\"seogen_select_all\"]').remove();
				
				// Add hidden input if Select All was enabled
				if (selectAllEnabled) {
					// Add to the form
					$('#posts-filter').append('<input type=\"hidden\" name=\"seogen_select_all\" value=\"1\" />');
					console.log('[SEOgen Select All] Added hidden input to form before submission');
					
					// Also add to URL if WordPress uses GET
					var form = $('#posts-filter');
					var action = form.attr('action') || '';
					if (action.indexOf('?') > -1) {
						form.attr('action', action + '&seogen_select_all=1');
					} else {
						form.attr('action', action + '?seogen_select_all=1');
					}
					console.log('[SEOgen Select All] Updated form action URL');
				} else {
					console.log('[SEOgen Select All] Select All NOT enabled, no hidden input added');
				}
			});
		});
		" );
	}

	public function render_city_hubs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seogen' ) );
		}

		$config = $this->get_business_config();
		$hubs = $this->get_hubs();
		$cities = $this->get_cities();

		if ( empty( $config ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'City Hubs', 'seogen' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please configure your business settings first.', 'seogen' ) . '</p></div>';
			echo '</div>';
			return;
		}

		if ( empty( $hubs ) ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'City Hubs', 'seogen' ) . '</h1>';
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please configure your service hubs first.', 'seogen' ) . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'City Hubs (Step 4)', 'seogen' ) . '</h1>';
		echo '<p>' . esc_html__( 'Generate city hub pages that serve as parent pages for service+city pages. Each city hub will have the same layout as service hub pages.', 'seogen' ) . '</p>';

		// Cities Management Section
		echo '<h2>' . esc_html__( 'Manage Cities', 'seogen' ) . '</h2>';
		echo '<p>' . esc_html__( 'Add or remove cities that will be available for city hub page generation.', 'seogen' ) . '</p>';
		
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom: 30px;">';
		wp_nonce_field( 'hyper_local_save_cities', 'hyper_local_cities_nonce' );
		echo '<input type="hidden" name="action" value="hyper_local_save_cities" />';
		
		echo '<h3>' . esc_html__( 'Current Cities', 'seogen' ) . '</h3>';
		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr>';
		echo '<th style="width: 30%;">' . esc_html__( 'City Name', 'seogen' ) . '</th>';
		echo '<th style="width: 10%;">' . esc_html__( 'State', 'seogen' ) . '</th>';
		echo '<th style="width: 35%;">' . esc_html__( 'Slug', 'seogen' ) . '</th>';
		echo '<th style="width: 15%;">' . esc_html__( 'Actions', 'seogen' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';
		
		if ( ! empty( $cities ) ) {
			foreach ( $cities as $idx => $city ) {
				echo '<tr>';
				echo '<td><input type="text" name="cities[' . esc_attr( $idx ) . '][name]" value="' . esc_attr( $city['name'] ) . '" style="width: 95%;" required /></td>';
				echo '<td><input type="text" name="cities[' . esc_attr( $idx ) . '][state]" value="' . esc_attr( $city['state'] ) . '" maxlength="2" style="width: 50px; text-align: center;" required /></td>';
				echo '<td><input type="text" name="cities[' . esc_attr( $idx ) . '][slug]" value="' . esc_attr( $city['slug'] ) . '" style="width: 95%;" required /></td>';
				echo '<td>';
				$delete_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=hyper_local_delete_city&index=' . $idx ),
					'hyper_local_delete_city_' . $idx,
					'nonce'
				);
				echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small" onclick="return confirm(\'Delete this city?\');">' . esc_html__( 'Delete', 'seogen' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="4">' . esc_html__( 'No cities configured yet. Add cities using the form below.', 'seogen' ) . '</td></tr>';
		}
		
		echo '</tbody></table>';
		
		echo '<h3>' . esc_html__( 'Add New Cities', 'seogen' ) . '</h3>';
		echo '<p>' . esc_html__( 'Add multiple cities at once. Format: "City Name, ST" (one per line). State code is required.', 'seogen' ) . '</p>';
		echo '<textarea name="bulk_cities" rows="6" class="large-text" placeholder="Tulsa, OK&#10;Broken Arrow, OK&#10;Owasso, OK"></textarea>';
		
		echo '<p class="submit">';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save Cities', 'seogen' ) . '</button>';
		echo '</p>';
		echo '</form>';
		
		echo '<hr style="margin: 40px 0;" />';
		
		echo '<h2>' . esc_html__( 'Generate City Hub Pages', 'seogen' ) . '</h2>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'hyper_local_city_hub_preview', 'hyper_local_city_hub_preview_nonce' );
		echo '<input type="hidden" name="action" value="hyper_local_city_hub_preview" />';

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="hub_key">' . esc_html__( 'Select Hub', 'seogen' ) . '</label></th>';
		echo '<td>';
		echo '<select name="hub_key" id="hub_key" required>';
		echo '<option value="">' . esc_html__( '-- Select Hub --', 'seogen' ) . '</option>';
		foreach ( $hubs as $hub ) {
			$hub_key = isset( $hub['key'] ) ? esc_attr( $hub['key'] ) : '';
			$hub_label = isset( $hub['label'] ) ? esc_html( $hub['label'] ) : '';
			echo '<option value="' . $hub_key . '">' . $hub_label . '</option>';
		}
		echo '</select>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="city_slugs">' . esc_html__( 'Select Cities', 'seogen' ) . '</label></th>';
		echo '<td>';
		echo '<select name="city_slugs[]" id="city_slugs" multiple size="10" style="width: 100%; max-width: 400px;" required>';
		foreach ( $cities as $city ) {
			$city_name = isset( $city['name'] ) ? esc_html( $city['name'] ) : '';
			$city_state = isset( $city['state'] ) ? esc_html( $city['state'] ) : '';
			$city_slug = isset( $city['slug'] ) ? esc_attr( $city['slug'] ) : '';
			$display = $city_name;
			if ( $city_state ) {
				$display .= ', ' . $city_state;
			}
			echo '<option value="' . $city_slug . '">' . $display . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hold Ctrl (Cmd on Mac) to select multiple cities.', 'seogen' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';

		echo '<p class="submit">';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Preview City Hub', 'seogen' ) . '</button>';
		echo '</p>';
		echo '</form>';

		echo '<hr />';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'hyper_local_city_hub_create', 'hyper_local_city_hub_create_nonce' );
		echo '<input type="hidden" name="action" value="hyper_local_city_hub_create" />';
		echo '<input type="hidden" name="hub_key" id="bulk_hub_key" />';
		echo '<input type="hidden" name="city_slugs_bulk" id="city_slugs_bulk" />';

		echo '<p class="submit">';
		echo '<button type="button" class="button button-primary" id="create_city_hubs_btn">' . esc_html__( 'Create/Update City Hubs', 'seogen' ) . '</button>';
		echo '</p>';
		echo '</form>';

		echo '<div id="city_hub_progress" style="display:none; margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #2271b1;">
			<p><strong>Generating City Hub Pages...</strong></p>
			<p id="progress_status">Initializing...</p>
			<div style="background: #f0f0f1; height: 30px; border-radius: 3px; overflow: hidden; margin: 10px 0;">
				<div id="progress_bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
			</div>
			<p id="progress_details" style="font-size: 12px; color: #666;">0 of 0 completed</p>
			<p style="color: #d63638; display: none;" id="progress_errors"></p>
		</div>';

		echo '<script>
		jQuery(document).ready(function($) {
			var batchId = null;
			var processingInterval = null;
			
			$("#create_city_hubs_btn").on("click", function() {
				var hubKey = $("#hub_key").val();
				var citySlugs = $("#city_slugs").val();
				
				if (!hubKey) {
					alert("Please select a hub.");
					return;
				}
				
				if (!citySlugs || citySlugs.length === 0) {
					alert("Please select at least one city.");
					return;
				}
				
				var cityCount = citySlugs.length;
				var estimatedTime = cityCount * 20;
				var minutes = Math.ceil(estimatedTime / 60);
				
				var message = "Create/update " + cityCount + " city hub page(s)?\\n\\n";
				message += "Estimated time: " + minutes + " minute(s)\\n";
				message += "Each page requires AI generation (10-30 seconds per city).";
				
				if (!confirm(message)) {
					return;
				}
				
				// Start async batch processing
				startBatchProcessing(hubKey, citySlugs);
			});
			
			function startBatchProcessing(hubKey, citySlugs) {
				$("#city_hub_progress").show();
				$("#create_city_hubs_btn").prop("disabled", true).text("Generating...");
				$("#progress_status").text("Starting batch...");
				
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: "seogen_start_city_hub_batch",
						nonce: "' . wp_create_nonce( 'seogen_city_hub_batch' ) . '",
						hub_key: hubKey,
						city_slugs: citySlugs.join(",")
					},
					success: function(response) {
						if (response.success) {
							batchId = response.data.batch_id;
							$("#progress_details").text("0 of " + response.data.total + " completed");
							processNextItem();
						} else {
							alert("Error starting batch: " + response.data.message);
							resetUI();
						}
					},
					error: function() {
						alert("Error starting batch. Please try again.");
						resetUI();
					}
				});
			}
			
			function processNextItem() {
				if (!batchId) return;
				
				$.ajax({
					url: ajaxurl,
					type: "POST",
					data: {
						action: "seogen_process_city_hub_item",
						nonce: "' . wp_create_nonce( 'seogen_city_hub_batch' ) . '",
						batch_id: batchId
					},
					success: function(response) {
						if (response.success) {
							var data = response.data.batch_data;
							var percent = Math.round((data.processed / data.total) * 100);
							
							$("#progress_bar").css("width", percent + "%");
							$("#progress_details").text(data.processed + " of " + data.total + " completed (Created: " + data.created + ", Updated: " + data.updated + ")");
							$("#progress_status").text("Processing: " + response.data.current_city);
							
							if (data.errors.length > 0) {
								$("#progress_errors").show().text("Errors: " + data.errors.join(", "));
							}
							
							if (response.data.completed) {
								completeBatch(data);
							} else {
								// Process next item
								setTimeout(processNextItem, 500);
							}
						} else {
							alert("Error processing item: " + response.data.message);
							resetUI();
						}
					},
					error: function() {
						alert("Error processing item. Please refresh and check Service Pages.");
						resetUI();
					}
				});
			}
			
			function completeBatch(data) {
				$("#progress_status").html("<strong style=\"color: #00a32a;\">✓ Completed!</strong>");
				$("#progress_bar").css("background", "#00a32a");
				$("#create_city_hubs_btn").prop("disabled", false).text("Create/Update City Hubs");
				
				var message = "Batch completed!\\n\\n";
				message += "Created: " + data.created + "\\n";
				message += "Updated: " + data.updated + "\\n";
				if (data.errors.length > 0) {
					message += "Errors: " + data.errors.length;
				}
				
				setTimeout(function() {
					alert(message);
					window.location.reload();
				}, 1000);
			}
			
			function resetUI() {
				$("#city_hub_progress").hide();
				$("#create_city_hubs_btn").prop("disabled", false).text("Create/Update City Hubs");
				$("#progress_bar").css("width", "0%");
			}
		});
		</script>';

		echo '</div>';
	}

	public function handle_city_hub_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'hyper_local_city_hub_preview', 'hyper_local_city_hub_preview_nonce' );

		$hub_key = isset( $_POST['hub_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_key'] ) ) : '';
		$city_slugs = isset( $_POST['city_slugs'] ) && is_array( $_POST['city_slugs'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['city_slugs'] ) ) : array();

		if ( '' === $hub_key || empty( $city_slugs ) ) {
			wp_die( 'Missing hub_key or city_slugs' );
		}

		$city_slug = $city_slugs[0];

		$config = $this->get_business_config();
		$hubs = $this->get_hubs();
		$cities = $this->get_cities();
		$services = $this->get_services();

		$hub = null;
		foreach ( $hubs as $h ) {
			if ( isset( $h['key'] ) && $h['key'] === $hub_key ) {
				$hub = $h;
				break;
			}
		}

		$city = null;
		foreach ( $cities as $c ) {
			if ( isset( $c['slug'] ) && $c['slug'] === $city_slug ) {
				$city = $c;
				break;
			}
		}

		if ( ! $hub || ! $city ) {
			wp_die( 'Hub or city not found' );
		}

		$services_for_hub = array();
		foreach ( $services as $service ) {
			if ( isset( $service['hub_key'], $service['name'], $service['slug'] ) && $service['hub_key'] === $hub_key ) {
				$services_for_hub[] = array(
					'name' => $service['name'],
					'slug' => $service['slug'],
				);
			}
		}

		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
		$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';

		if ( '' === $api_url || '' === $license_key ) {
			wp_die( 'API URL or license key not configured' );
		}

		$payload = array(
			'license_key' => $license_key,
			'data' => array(
				'page_mode' => 'city_hub',
				'vertical' => $config['vertical'],
				'business_name' => $config['business_name'],
				'phone' => $config['phone'],
				'cta_text' => $config['cta_text'],
				'service_area_label' => $config['service_area_label'],
				'hub_key' => $hub['key'],
				'hub_label' => $hub['label'],
				'hub_slug' => $hub['slug'],
				'city' => $city['name'],
				'state' => $city['state'],
				'city_slug' => $city['slug'],
				'services_for_hub' => $services_for_hub,
			),
			'preview' => true,
		);

		$url = trailingslashit( $api_url ) . 'generate-page';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 60,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_die( 'API request failed: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			wp_die( 'API returned error: ' . $code . ' - ' . $body );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['blocks'] ) ) {
			wp_die( 'Invalid API response' );
		}

		$title = isset( $data['title'] ) ? $data['title'] : '';
		$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';
		$blocks = $data['blocks'];

		echo '<div style="max-width: 1200px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ccc;">';
		echo '<h1>City Hub Preview</h1>';
		echo '<p><strong>Title:</strong> ' . esc_html( $title ) . '</p>';
		echo '<p><strong>Meta Description:</strong> ' . esc_html( $meta_description ) . '</p>';
		echo '<hr />';
		echo '<h2>Content Blocks:</h2>';
		echo '<pre>' . esc_html( wp_json_encode( $blocks, JSON_PRETTY_PRINT ) ) . '</pre>';
		echo '<hr />';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=hyper-local-city-hubs' ) ) . '" class="button">Back</a></p>';
		echo '</div>';
		exit;
	}

	public function handle_city_hub_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		check_admin_referer( 'hyper_local_city_hub_create', 'hyper_local_city_hub_create_nonce' );

		$hub_key = isset( $_POST['hub_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_key'] ) ) : '';
		$city_slugs_raw = isset( $_POST['city_slugs_bulk'] ) ? sanitize_text_field( wp_unslash( $_POST['city_slugs_bulk'] ) ) : '';
		$city_slugs = array_filter( array_map( 'trim', explode( ',', $city_slugs_raw ) ) );

		if ( '' === $hub_key || empty( $city_slugs ) ) {
			wp_die( 'Missing hub_key or city_slugs' );
		}

		// Increase execution time for bulk operations
		// Each city hub takes 10-30 seconds to generate via AI
		// Allow 60 seconds per city + 60 second buffer
		$timeout = ( count( $city_slugs ) * 60 ) + 60;
		set_time_limit( $timeout );
		ini_set( 'max_execution_time', $timeout );

		$config = $this->get_business_config();
		$hubs = $this->get_hubs();
		$cities = $this->get_cities();
		$services = $this->get_services();

		$hub = null;
		foreach ( $hubs as $h ) {
			if ( isset( $h['key'] ) && $h['key'] === $hub_key ) {
				$hub = $h;
				break;
			}
		}

		if ( ! $hub ) {
			wp_die( 'Hub not found' );
		}

		$services_for_hub = array();
		foreach ( $services as $service ) {
			if ( isset( $service['hub_key'], $service['name'], $service['slug'] ) && $service['hub_key'] === $hub_key ) {
				$services_for_hub[] = array(
					'name' => $service['name'],
					'slug' => $service['slug'],
				);
			}
		}

		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
		$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';

		if ( '' === $api_url || '' === $license_key ) {
			wp_die( 'API URL or license key not configured' );
		}

		$hub_post_id = $this->find_service_hub_post_id( $hub_key );

		$created_count = 0;
		$updated_count = 0;
		$errors = array();

		foreach ( $city_slugs as $city_slug ) {
			$city = null;
			foreach ( $cities as $c ) {
				if ( isset( $c['slug'] ) && $c['slug'] === $city_slug ) {
					$city = $c;
					break;
				}
			}

			if ( ! $city ) {
				$errors[] = "City not found: $city_slug";
				continue;
			}

			$payload = array(
				'license_key' => $license_key,
				'data' => array(
					'page_mode' => 'city_hub',
					'vertical' => $config['vertical'],
					'business_name' => $config['business_name'],
					'phone' => $config['phone'],
					'cta_text' => $config['cta_text'],
					'service_area_label' => $config['service_area_label'],
					'hub_key' => $hub['key'],
					'hub_label' => $hub['label'],
					'hub_slug' => $hub['slug'],
					'city' => $city['name'],
					'state' => $city['state'],
					'city_slug' => $city['slug'],
					'services_for_hub' => $services_for_hub,
				),
				'preview' => false,
			);

			$url = trailingslashit( $api_url ) . 'generate-page';
			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 90,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body' => wp_json_encode( $payload ),
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = "API error for {$city['name']}: " . $response->get_error_message();
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 !== $code ) {
				$errors[] = "API error for {$city['name']}: HTTP $code";
				continue;
			}

			$data = json_decode( $body, true );
			if ( ! is_array( $data ) || ! isset( $data['blocks'] ) ) {
				$errors[] = "Invalid API response for {$city['name']}";
				continue;
			}

			$title = isset( $data['title'] ) ? $data['title'] : "{$hub['label']} in {$city['name']}, {$city['state']}";
			$slug = $city['slug'];
			$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';
			$blocks = $data['blocks'];
			$page_mode = isset( $data['page_mode'] ) ? $data['page_mode'] : '';

			$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );

			// Apply City Hub quality improvements (parent link, city repetition cleanup, FAQ deduplication, city nuance)
			$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
			$gutenberg_markup = $this->apply_city_hub_quality_improvements( $gutenberg_markup, $hub_key, $city, $vertical );

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

			$existing_post_id = $this->find_city_hub_post_id( $hub_key, $city_slug );

			$postarr = array(
				'post_type' => 'service_page',
				'post_status' => 'draft',
				'post_title' => $title,
				'post_name' => sanitize_title( $slug ),
				'post_content' => $gutenberg_markup,
				'post_parent' => $hub_post_id,
			);

			if ( $existing_post_id > 0 ) {
				// For updates, remove post_name to avoid slug conflicts
				unset( $postarr['post_name'] );
				$postarr['ID'] = $existing_post_id;
				
				// Temporarily disable template validation filter
				add_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10, 2 );
				$post_id = wp_update_post( $postarr, true );
				remove_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10 );
				
				if ( ! is_wp_error( $post_id ) ) {
					$updated_count++;
				}
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( ! is_wp_error( $post_id ) ) {
					$created_count++;
				}
			}

			if ( is_wp_error( $post_id ) ) {
				$errors[] = "Post creation error for {$city['name']}: " . $post_id->get_error_message();
				continue;
			}

			update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $data ) );
			update_post_meta( $post_id, '_seogen_page_mode', 'city_hub' );
			update_post_meta( $post_id, '_seogen_vertical', $config['vertical'] );
			update_post_meta( $post_id, '_seogen_hub_key', $hub['key'] );
			update_post_meta( $post_id, '_seogen_hub_slug', $hub['slug'] );
			update_post_meta( $post_id, '_seogen_city', $city['name'] . ', ' . $city['state'] );
			update_post_meta( $post_id, '_seogen_city_slug', $city['slug'] );
			update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
			update_post_meta( $post_id, '_hyper_local_managed', '1' );

			if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
				$this->apply_page_builder_settings( $post_id );
			}

			// Generate focus keyword for SEO plugins: "hub_label city_name"
			$focus_keyword = $hub['label'] . ' ' . $city['name'];
			$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );

			$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', $hub_post_id );
			if ( $unique_slug ) {
				wp_update_post(
					array(
						'ID' => $post_id,
						'post_name' => $unique_slug,
					)
				);
			}
		}

		$redirect_url = admin_url( 'admin.php?page=hyper-local-city-hubs' );
		$total_processed = $created_count + $updated_count + count( $errors );
		$message = "Processed $total_processed city hubs: Created $created_count, Updated $updated_count";
		if ( ! empty( $errors ) ) {
			$message .= ' | ' . count( $errors ) . ' errors occurred';
		}
		$redirect_url = add_query_arg( 'message', urlencode( $message ), $redirect_url );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function find_service_hub_post_id( $hub_key ) {
		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_hub',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
				),
			),
		);

		$query = new WP_Query( $args );
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
		return 0;
	}

	private function find_city_hub_post_id( $hub_key, $city_slug ) {
		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'city_hub',
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
		);

		$query = new WP_Query( $args );
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0];
		}
		return 0;
	}

	/**
	 * AJAX: Start async city hub batch generation
	 * Creates a batch job and returns immediately, processing happens in background
	 */
	public function ajax_start_city_hub_batch() {
		check_ajax_referer( 'seogen_city_hub_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$hub_key = isset( $_POST['hub_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_key'] ) ) : '';
		$city_slugs_raw = isset( $_POST['city_slugs'] ) ? sanitize_text_field( wp_unslash( $_POST['city_slugs'] ) ) : '';
		$city_slugs = array_filter( array_map( 'trim', explode( ',', $city_slugs_raw ) ) );

		if ( empty( $hub_key ) || empty( $city_slugs ) ) {
			wp_send_json_error( array( 'message' => 'Missing hub_key or city_slugs' ) );
		}

		// Create batch job
		$batch_id = 'city_hub_' . time() . '_' . wp_generate_password( 8, false );
		$batch_data = array(
			'batch_id' => $batch_id,
			'hub_key' => $hub_key,
			'city_slugs' => $city_slugs,
			'total' => count( $city_slugs ),
			'processed' => 0,
			'created' => 0,
			'updated' => 0,
			'errors' => array(),
			'status' => 'processing',
			'started_at' => time(),
		);

		set_transient( 'seogen_batch_' . $batch_id, $batch_data, 3600 ); // 1 hour expiry

		wp_send_json_success( array(
			'batch_id' => $batch_id,
			'total' => count( $city_slugs ),
			'message' => 'Batch started successfully'
		) );
	}

	/**
	 * AJAX: Check progress of city hub batch
	 */
	public function ajax_check_city_hub_progress() {
		check_ajax_referer( 'seogen_city_hub_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => 'Missing batch_id' ) );
		}

		$batch_data = get_transient( 'seogen_batch_' . $batch_id );
		if ( false === $batch_data ) {
			wp_send_json_error( array( 'message' => 'Batch not found or expired' ) );
		}

		wp_send_json_success( $batch_data );
	}

	/**
	 * AJAX: Process single city hub item
	 * Called repeatedly by frontend to process one city at a time
	 */
	public function ajax_process_city_hub_item() {
		check_ajax_referer( 'seogen_city_hub_batch', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$batch_id = isset( $_POST['batch_id'] ) ? sanitize_text_field( wp_unslash( $_POST['batch_id'] ) ) : '';
		if ( empty( $batch_id ) ) {
			wp_send_json_error( array( 'message' => 'Missing batch_id' ) );
		}

		$batch_data = get_transient( 'seogen_batch_' . $batch_id );
		if ( false === $batch_data ) {
			wp_send_json_error( array( 'message' => 'Batch not found or expired' ) );
		}

		// Get next city to process
		$processed = $batch_data['processed'];
		if ( $processed >= $batch_data['total'] ) {
			$batch_data['status'] = 'completed';
			set_transient( 'seogen_batch_' . $batch_id, $batch_data, 3600 );
			wp_send_json_success( array(
				'completed' => true,
				'batch_data' => $batch_data
			) );
		}

		$city_slug = $batch_data['city_slugs'][ $processed ];

		// Process this city hub
		$result = $this->process_single_city_hub( $batch_data['hub_key'], $city_slug );

		// Update batch data
		$batch_data['processed']++;
		if ( $result['success'] ) {
			if ( $result['action'] === 'created' ) {
				$batch_data['created']++;
			} else {
				$batch_data['updated']++;
			}
		} else {
			$batch_data['errors'][] = $result['error'];
		}

		// Check if completed
		if ( $batch_data['processed'] >= $batch_data['total'] ) {
			$batch_data['status'] = 'completed';
			$batch_data['completed_at'] = time();
		}

		set_transient( 'seogen_batch_' . $batch_id, $batch_data, 3600 );

		wp_send_json_success( array(
			'completed' => $batch_data['processed'] >= $batch_data['total'],
			'batch_data' => $batch_data,
			'current_city' => $city_slug,
			'result' => $result
		) );
	}

	/**
	 * Process a single city hub (extracted from bulk handler)
	 */
	private function process_single_city_hub( $hub_key, $city_slug ) {
		$config = $this->get_business_config();
		$hubs = $this->get_hubs();
		$cities = $this->get_cities();
		$services = $this->get_services();

		$hub = null;
		foreach ( $hubs as $h ) {
			if ( isset( $h['key'] ) && $h['key'] === $hub_key ) {
				$hub = $h;
				break;
			}
		}

		if ( ! $hub ) {
			return array( 'success' => false, 'error' => 'Hub not found' );
		}

		$city = null;
		foreach ( $cities as $c ) {
			if ( isset( $c['slug'] ) && $c['slug'] === $city_slug ) {
				$city = $c;
				break;
			}
		}

		if ( ! $city ) {
			return array( 'success' => false, 'error' => "City not found: $city_slug" );
		}

		$services_for_hub = array();
		foreach ( $services as $service ) {
			if ( isset( $service['hub_key'], $service['name'], $service['slug'] ) && $service['hub_key'] === $hub_key ) {
				$services_for_hub[] = array(
					'name' => $service['name'],
					'slug' => $service['slug'],
				);
			}
		}

		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
		$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';

		if ( '' === $api_url || '' === $license_key ) {
			return array( 'success' => false, 'error' => 'API URL or license key not configured' );
		}

		$payload = array(
			'license_key' => $license_key,
			'data' => array(
				'page_mode' => 'city_hub',
				'vertical' => $config['vertical'],
				'business_name' => $config['business_name'],
				'phone' => $config['phone'],
				'cta_text' => $config['cta_text'],
				'service_area_label' => $config['service_area_label'],
				'hub_key' => $hub['key'],
				'hub_label' => $hub['label'],
				'hub_slug' => $hub['slug'],
				'city' => $city['name'],
				'state' => $city['state'],
				'city_slug' => $city['slug'],
				'services_for_hub' => $services_for_hub,
			),
			'preview' => false,
		);

		$url = trailingslashit( $api_url ) . 'generate-page';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body' => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => "API error: " . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return array( 'success' => false, 'error' => "API error: HTTP $code" );
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['blocks'] ) ) {
			return array( 'success' => false, 'error' => 'Invalid API response' );
		}

		$title = isset( $data['title'] ) ? $data['title'] : "{$hub['label']} in {$city['name']}, {$city['state']}";
		$slug = $city['slug'];
		$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';
		$blocks = $data['blocks'];
		$page_mode = isset( $data['page_mode'] ) ? $data['page_mode'] : '';

		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks, $page_mode );

		// Apply City Hub quality improvements (parent link, city repetition cleanup, FAQ deduplication, city nuance)
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		$gutenberg_markup = $this->apply_city_hub_quality_improvements( $gutenberg_markup, $hub_key, $city, $vertical );

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
		$existing_post_id = $this->find_city_hub_post_id( $hub_key, $city_slug );

		$postarr = array(
			'post_type' => 'service_page',
			'post_status' => 'draft',
			'post_title' => $title,
			'post_name' => sanitize_title( $slug ),
			'post_content' => $gutenberg_markup,
			'post_parent' => $hub_post_id,
		);

		$action = 'created';
		if ( $existing_post_id > 0 ) {
			// For updates, we need to bypass template validation
			// Remove post_name to avoid slug conflicts, we'll update it separately
			unset( $postarr['post_name'] );
			$postarr['ID'] = $existing_post_id;
			
			// Temporarily disable template validation filter
			add_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10, 2 );
			$post_id = wp_update_post( $postarr, true );
			remove_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10 );
			
			$action = 'updated';
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return array( 'success' => false, 'error' => "Post creation error: " . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $data ) );
		update_post_meta( $post_id, '_seogen_page_mode', 'city_hub' );
		update_post_meta( $post_id, '_seogen_vertical', $config['vertical'] );
		update_post_meta( $post_id, '_seogen_hub_key', $hub['key'] );
		update_post_meta( $post_id, '_seogen_hub_slug', $hub['slug'] );
		update_post_meta( $post_id, '_seogen_city', $city['name'] . ', ' . $city['state'] );
		update_post_meta( $post_id, '_seogen_city_slug', $city['slug'] );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		update_post_meta( $post_id, '_seogen_links_integrated', '1' );

		// Apply page builder settings with template validation bypass to prevent errors
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			add_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10, 2 );
			$this->apply_page_builder_settings( $post_id );
			remove_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10 );
		}

		// Generate focus keyword for SEO plugins: "hub_label city_name"
		$focus_keyword = $hub['label'] . ' ' . $city['name'];
		$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );

		// Update slug with template validation bypass to prevent errors
		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', $hub_post_id );
		if ( $unique_slug ) {
			add_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10, 2 );
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_name' => $unique_slug,
				)
			);
			remove_filter( 'wp_insert_post_data', array( $this, 'bypass_template_validation' ), 10 );
		}

		return array(
			'success' => true,
			'action' => $action,
			'post_id' => $post_id,
			'city' => $city['name']
		);
	}

	/**
	 * Bypass template validation during post updates
	 * Prevents "Invalid page template" error when updating existing posts
	 */
	public function bypass_template_validation( $data, $postarr ) {
		// Remove page_template from validation if it's set
		if ( isset( $data['page_template'] ) ) {
			unset( $data['page_template'] );
		}
		return $data;
	}
}
