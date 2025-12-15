<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Admin {
	const OPTION_NAME = 'seogen_settings';
	const LAST_PREVIEW_TRANSIENT_PREFIX = 'hyper_local_last_preview_';
	const BULK_JOB_OPTION_PREFIX = 'hyper_local_job_';
	const BULK_JOBS_INDEX_OPTION = 'hyper_local_jobs_index';
	const BULK_VALIDATE_TRANSIENT_PREFIX = 'hyper_local_bulk_validate_';
	const BULK_PROCESS_HOOK = 'hyper_local_process_job_batch';

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
	}

	public function register_bulk_worker_hooks() {
		add_action( self::BULK_PROCESS_HOOK, array( $this, 'process_bulk_job' ), 10, 1 );
		add_action( 'hyper_local_bulk_process_job', array( $this, 'process_bulk_job' ), 10, 1 );
		add_action( 'hyper_local_bulk_process_job_action', array( $this, 'process_bulk_job' ), 10, 1 );
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

	public function render_field_primary_cta_label() {
		$settings = $this->get_settings();
		$value = isset( $settings['primary_cta_label'] ) ? (string) $settings['primary_cta_label'] : '';
		if ( '' === $value ) {
			$value = 'Call Now';
		}
		printf(
			'<input type="text" class="regular-text" name="%1$s[primary_cta_label]" value="%2$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
	}

	private function build_gutenberg_content_from_blocks( array $blocks ) {
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
		$primary_cta_label = isset( $settings['primary_cta_label'] ) ? (string) $settings['primary_cta_label'] : 'Call Now';
		$primary_cta_label = trim( $primary_cta_label );
		if ( '' === $primary_cta_label ) {
			$primary_cta_label = 'Call Now';
		}

		$output = array();
		$output[] = '<!-- wp:group {"className":"hyper-local-content' . $preset_class . '"} -->';
		$output[] = '<div class="wp-block-group hyper-local-content' . esc_attr( $preset_class ) . '">';
		$faq_heading_added = false;
		$hero_heading_text = null;
		$hero_paragraph_text = null;
		$hero_emitted = false;
		$body_group_open = false;
		$last_phone = '';
		$separator_after_hero_added = false;
		$scannable_headings_added = false;
		$section_heading_state = 0;
		$paragraphs_seen_after_hero = 0;
		$issues_list_added = false;
		$context_city = '';
		$context_state = '';
		$context_business = '';
		$context_service = '';
		$context_phone = '';
		$process_added = false;

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

		$add_process_section = function () use ( &$output, &$context_service ) {
			$service = ( '' !== $context_service ) ? $context_service : esc_html__( 'Roof Repair', 'seogen' );
			$output[] = '<!-- wp:heading {"level":2} -->';
			$output[] = '<h2>' . esc_html( sprintf( __( '%s Process', 'seogen' ), $service ) ) . '</h2>';
			$output[] = '<!-- /wp:heading -->';
			$output[] = '<!-- wp:list {"ordered":true} -->';
			$output[] = '<ol>';
			$output[] = '<li>' . esc_html__( 'Inspection & diagnosis', 'seogen' ) . '</li>';
			$output[] = '<li>' . esc_html__( 'Clear estimate & plan', 'seogen' ) . '</li>';
			$output[] = '<li>' . esc_html__( 'Quality repairs', 'seogen' ) . '</li>';
			$output[] = '<li>' . esc_html__( 'Final walkthrough', 'seogen' ) . '</li>';
			$output[] = '</ol>';
			$output[] = '<!-- /wp:list -->';
		};

		$add_h2 = function ( $text ) use ( &$output ) {
			$output[] = '<!-- wp:heading {"level":2} -->';
			$output[] = '<h2>' . esc_html( (string) $text ) . '</h2>';
			$output[] = '<!-- /wp:heading -->';
		};

		$maybe_add_scannable_section_heading = function () use ( &$scannable_headings_added, &$section_heading_state, &$output, &$context_city, &$context_state, &$context_business, &$context_service, $add_h2 ) {
			if ( ! $scannable_headings_added ) {
				$scannable_headings_added = true;
			}

			$city = ( '' !== $context_city ) ? $context_city : __( 'Your City', 'seogen' );
			$state = ( '' !== $context_state ) ? $context_state : __( 'Your State', 'seogen' );
			$biz = ( '' !== $context_business ) ? $context_business : __( 'Your Business', 'seogen' );

			if ( 0 === $section_heading_state ) {
				$service = ( '' !== $context_service ) ? $context_service : esc_html__( 'Roof Repair', 'seogen' );
				$add_h2( sprintf( __( 'Our %s Services', 'seogen' ), $service ) );
				$section_heading_state = 1;
				return;
			}

			if ( 1 === $section_heading_state ) {
				$add_h2( __( 'Common Roofing Issues We Fix', 'seogen' ) );
				$section_heading_state = 2;
				return;
			}

			if ( 2 === $section_heading_state ) {
				$add_h2( sprintf( __( 'Why Homeowners Choose %s', 'seogen' ), $biz ) );
				$section_heading_state = 3;
				return;
			}
		};

		$add_issues_list = function () use ( &$output ) {
			$items = array(
				esc_html__( 'Leak detection and repair', 'seogen' ),
				esc_html__( 'Shingle replacement', 'seogen' ),
				esc_html__( 'Storm and hail damage repairs', 'seogen' ),
				esc_html__( 'Flashing and vent repairs', 'seogen' ),
				esc_html__( 'Preventive inspections', 'seogen' ),
			);
			$output[] = '<!-- wp:list -->';
			$output[] = '<ul>';
			foreach ( $items as $item ) {
				$output[] = '<li>' . $item . '</li>';
			}
			$output[] = '</ul>';
			$output[] = '<!-- /wp:list -->';
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
				$text = isset( $block['text'] ) ? esc_html( (string) $block['text'] ) : '';

				if ( ! $hero_emitted && null !== $hero_heading_text && null === $hero_paragraph_text ) {
					$hero_paragraph_text = $text;
					$emit_hero_if_ready( true );
					continue;
				}

				$emit_hero_if_ready( true );
				$open_body_group_if_needed();

				if ( $hero_emitted ) {
					$paragraphs_seen_after_hero++;
					if ( 1 === $paragraphs_seen_after_hero ) {
						$maybe_add_scannable_section_heading();
					}
					if ( 2 === $paragraphs_seen_after_hero ) {
						$maybe_add_scannable_section_heading();
						if ( ! $issues_list_added ) {
							$add_issues_list();
							$issues_list_added = true;
						}
					}
					if ( 4 === $paragraphs_seen_after_hero ) {
						$maybe_add_scannable_section_heading();
					}
				}

				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $text . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				continue;
			}

			if ( 'faq' === $type ) {
				$emit_hero_if_ready( true );
				if ( ! $process_added && $section_heading_state >= 3 && $paragraphs_seen_after_hero >= 4 ) {
					$add_process_section();
					$process_added = true;
				}
				$close_body_group_if_open();

				if ( ! $faq_heading_added ) {
					$add_separator();
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
				if ( ! $process_added && $section_heading_state >= 3 && $paragraphs_seen_after_hero >= 4 ) {
					$add_process_section();
					$process_added = true;
				}
				$close_body_group_if_open();
				$add_separator();

				$business_name_raw = isset( $block['business_name'] ) ? (string) $block['business_name'] : '';
				$business_name = esc_html( $business_name_raw );
				$address       = isset( $block['address'] ) ? esc_html( (string) $block['address'] ) : '';
				$phone         = isset( $block['phone'] ) ? esc_html( (string) $block['phone'] ) : '';
				$last_phone    = isset( $block['phone'] ) ? (string) $block['phone'] : '';
				if ( '' !== trim( $business_name_raw ) && '' === $context_business ) {
					$context_business = trim( $business_name_raw );
				}
				if ( '' !== trim( $last_phone ) && '' === $context_phone ) {
					$context_phone = trim( (string) $last_phone );
				}

				$output[] = '<!-- wp:heading {"level":2} -->';
				$output[] = '<h2>' . esc_html__( 'Contact', 'seogen' ) . '</h2>';
				$output[] = '<!-- /wp:heading -->';
				$output[] = '<!-- wp:group {"className":"hyper-local-card hyper-local-nap"} -->';
				$output[] = '<div class="wp-block-group hyper-local-card hyper-local-nap">';
				$output[] = '<!-- wp:columns -->';
				$output[] = '<div class="wp-block-columns">';

				$output[] = '<!-- wp:column -->';
				$output[] = '<div class="wp-block-column">';
				$output[] = '<!-- wp:heading {"level":4} -->';
				$output[] = '<h4>' . esc_html__( 'Business', 'seogen' ) . '</h4>';
				$output[] = '<!-- /wp:heading -->';
				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $business_name . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:column -->';

				$output[] = '<!-- wp:column -->';
				$output[] = '<div class="wp-block-column">';
				$output[] = '<!-- wp:heading {"level":4} -->';
				$output[] = '<h4>' . esc_html__( 'Address', 'seogen' ) . '</h4>';
				$output[] = '<!-- /wp:heading -->';
				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $address . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:column -->';

				$output[] = '<!-- wp:column -->';
				$output[] = '<div class="wp-block-column">';
				$output[] = '<!-- wp:heading {"level":4} -->';
				$output[] = '<h4>' . esc_html__( 'Phone', 'seogen' ) . '</h4>';
				$output[] = '<!-- /wp:heading -->';
				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $phone . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:column -->';

				$output[] = '</div>';
				$output[] = '<!-- /wp:columns -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:group -->';
				continue;
			}

			if ( 'cta' === $type ) {
				$emit_hero_if_ready( true );
				if ( ! $process_added && $section_heading_state >= 3 && $paragraphs_seen_after_hero >= 4 ) {
					$add_process_section();
					$process_added = true;
				}
				$close_body_group_if_open();
				$add_separator();
				if ( '' === $context_city || '' === $context_state || '' === $context_business ) {
					$infer_context_from_title( (string) $hero_heading_text );
				}
				$add_h2( __( 'Get a Free Estimate', 'seogen' ) );

				$text = isset( $block['text'] ) ? esc_html( (string) $block['text'] ) : '';
				$tel_digits = preg_replace( '/\D+/', '', (string) $last_phone );
				$tel_url = '';
				if ( '' !== $tel_digits ) {
					$tel_url = 'tel:' . $tel_digits;
				}

				$output[] = '<!-- wp:buttons -->';
				$output[] = '<div class="wp-block-buttons">';
				$output[] = '<!-- wp:button ' . wp_json_encode( array( 'url' => $tel_url ) ) . ' -->';
				$output[] = '<div class="wp-block-button"><a class="wp-block-button__link" href="' . esc_url( $tel_url ) . '">' . esc_html__( 'Call Now', 'seogen' ) . '</a></div>';
				$output[] = '<!-- /wp:button -->';
				$output[] = '</div>';
				$output[] = '<!-- /wp:buttons -->';

				$output[] = '<!-- wp:paragraph -->';
				$output[] = '<p>' . $text . '</p>';
				$output[] = '<!-- /wp:paragraph -->';
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
		$last_preview['gutenberg_markup'] = $this->build_gutenberg_content_from_blocks( $last_preview['blocks'] );
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
				'timeout' => 90,
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
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $full_data['blocks'] );
		$source_json      = $full_data;

		$postarr = array(
			'post_type'    => 'programmatic_page',
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

		$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'programmatic_page', 0 );
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
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $source_json ) );
		update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );

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

		add_submenu_page(
			'hyper-local',
			__( 'Programmatic Pages', 'seogen' ),
			__( 'Programmatic Pages', 'seogen' ),
			'edit_posts',
			'edit.php?post_type=programmatic_page'
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
				$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $preview['blocks'] );
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

			<p>
				<strong><?php echo esc_html__( 'API URL:', 'seogen' ); ?></strong>
				<code><?php echo esc_html( $api_url ); ?></code>
			</p>
			<p>
				<strong><?php echo esc_html__( 'License Key:', 'seogen' ); ?></strong>
				<code><?php echo esc_html( $masked_license ); ?></code>
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
					if(res && res.success){render(res.data);return res.data;}
					render(null);return null;
				}).catch(function(err){
					console.error('[SEOgen] Fetch error:', err);
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
			console.log('[SEOgen] Starting initial fetchStatus');
			fetchStatus().then(function(job){
				console.log('[SEOgen] Initial fetch complete, job:', job);
				if(job && (job.status === 'pending' || job.status === 'running')){
					console.log('[SEOgen] Job is active, starting polling interval');
					setInterval(fetchStatus,5000);
				} else {
					console.log('[SEOgen] Job not active, status:', job ? job.status : 'null');
				}
			});
		})();
		</script>
		<?php endif; ?>
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
				'post_type'      => 'programmatic_page',
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

	private function parse_service_areas( $raw_lines ) {
		$lines = $this->parse_bulk_lines( $raw_lines );
		$areas = array();
		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( ',', (string) $line ) );
			$parts = array_values( array_filter( $parts, static function ( $v ) {
				return '' !== trim( (string) $v );
			} ) );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$areas[] = array(
				'city'  => sanitize_text_field( (string) $parts[0] ),
				'state' => sanitize_text_field( (string) $parts[1] ),
			);
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
			if ( is_array( $data ) && isset( $data['detail'] ) ) {
				$error = $error . ': ' . sanitize_text_field( (string) $data['detail'] );
			}
			error_log( '[HyperLocal API] api_json_request FAILED code=' . $code . ' error=' . $error );
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
			'seogen_api_url',
			__( 'API Base URL', 'seogen' ),
			array( $this, 'render_field_api_url' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_license_key',
			__( 'License Key', 'seogen' ),
			array( $this, 'render_field_license_key' ),
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

		add_settings_field(
			'hyper_local_primary_cta_label',
			__( 'Primary CTA Label', 'seogen' ),
			array( $this, 'render_field_primary_cta_label' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

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

		$primary_cta_label = 'Call Now';
		if ( isset( $input['primary_cta_label'] ) ) {
			$primary_cta_label = sanitize_text_field( (string) $input['primary_cta_label'] );
		}
		$primary_cta_label = trim( $primary_cta_label );
		if ( '' === $primary_cta_label ) {
			$primary_cta_label = 'Call Now';
		}
		$sanitized['primary_cta_label'] = $primary_cta_label;

		return $sanitized;
	}

	private function get_settings() {
		$defaults = array(
			'api_url'      => 'https://seogen-production.up.railway.app',
			'license_key'  => '',
			'design_preset' => 'theme_default',
			'show_h1_in_content' => '0',
			'hero_style' => 'minimal',
			'cta_style' => 'button_only',
			'enable_mobile_sticky_cta' => '0',
			'primary_cta_label' => 'Call Now',
		);

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
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

		$defaults = array(
			'services'        => '',
			'service_areas'   => '',
			'company_name'    => '',
			'phone'           => '',
			'address'         => '',
			'update_existing' => '0',
		);
		if ( is_array( $validated ) && isset( $validated['form'] ) && is_array( $validated['form'] ) ) {
			$defaults = wp_parse_args( $validated['form'], $defaults );
		}
		$status_nonce = wp_create_nonce( 'hyper_local_bulk_job_status' );
		$cancel_nonce = wp_create_nonce( 'hyper_local_bulk_job_cancel' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Bulk Generate', 'seogen' ); ?></h1>
			<p class="description">
				<strong><?php echo esc_html__( 'Backend:', 'seogen' ); ?></strong>
				<?php echo esc_html( $this->get_bulk_backend_label() ); ?>
				|
				<strong><?php echo esc_html__( 'DISABLE_WP_CRON:', 'seogen' ); ?></strong>
				<?php echo esc_html( ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'true' : 'false' ); ?>
			</p>

			<?php if ( is_array( $current_job ) ) : ?>
				<h2><?php echo esc_html__( 'Current Job', 'seogen' ); ?></h2>
				<div id="hyper-local-bulk-job" data-job-id="<?php echo esc_attr( $job_id ); ?>"></div>
				<p>
					<button type="button" class="button" id="hyper-local-bulk-refresh"><?php echo esc_html__( 'Refresh status', 'seogen' ); ?></button>
					<button type="button" class="button" id="hyper-local-bulk-cancel"><?php echo esc_html__( 'Cancel Job', 'seogen' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_bulk_run_batch&job_id=' . $job_id ), 'hyper_local_bulk_run_batch_' . $job_id, 'nonce' ) ); ?>"><?php echo esc_html__( 'Run Next Batch Now', 'seogen' ); ?></a>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_bulk_export&job_id=' . $job_id ), 'hyper_local_bulk_export_' . $job_id, 'nonce' ) ); ?>"><?php echo esc_html__( 'Export Results CSV', 'seogen' ); ?></a>
				</p>
				<hr />
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
				<div class="hyper-local-bulk-grid">
					<div class="hyper-local-bulk-col">
						<label for="hl_bulk_services"><?php echo esc_html__( 'Services (one per line)', 'seogen' ); ?></label>
						<textarea name="services" id="hl_bulk_services" class="large-text" rows="10"><?php echo esc_textarea( (string) $defaults['services'] ); ?></textarea>
					</div>
					<div class="hyper-local-bulk-col">
						<label for="hl_bulk_service_areas"><?php echo esc_html__( 'Service Areas (one per line: City, ST)', 'seogen' ); ?></label>
						<textarea name="service_areas" id="hl_bulk_service_areas" class="large-text" rows="10"><?php echo esc_textarea( (string) $defaults['service_areas'] ); ?></textarea>
						<p class="description" style="margin-top:6px;"><?php echo esc_html__( 'Example: Dallas, TX', 'seogen' ); ?></p>
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
							<th scope="row"><label for="hl_bulk_address"><?php echo esc_html__( 'Address (optional)', 'seogen' ); ?></label></th>
							<td><input name="address" id="hl_bulk_address" type="text" class="regular-text" value="<?php echo esc_attr( (string) $defaults['address'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Update existing drafts', 'seogen' ); ?></th>
							<td>
								<label><input type="checkbox" name="update_existing" value="1" <?php checked( (string) $defaults['update_existing'], '1' ); ?> /> <?php echo esc_html__( 'Update existing drafts instead of skipping', 'seogen' ); ?></label>
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
			'address'         => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'update_existing' => ( isset( $_POST['update_existing'] ) && '1' === (string) wp_unslash( $_POST['update_existing'] ) ) ? '1' : '0',
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
				if ( '' === $city || '' === $state ) {
					continue;
				}
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
			'inputs'     => array(
				'company_name' => isset( $form['company_name'] ) ? sanitize_text_field( (string) $form['company_name'] ) : '',
				'phone'        => isset( $form['phone'] ) ? sanitize_text_field( (string) $form['phone'] ) : '',
				'address'      => isset( $form['address'] ) ? sanitize_text_field( (string) $form['address'] ) : '',
			),
			'rows'       => $job_rows,
		);

		$api_items = array();
		$job_name = ( isset( $form['job_name'] ) ? sanitize_text_field( (string) $form['job_name'] ) : '' );
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Building API items from ' . count( $job_rows ) . ' job_rows' . PHP_EOL, FILE_APPEND );
		foreach ( $job_rows as $row ) {
			$api_items[] = array(
				'service'      => isset( $row['service'] ) ? (string) $row['service'] : '',
				'city'         => isset( $row['city'] ) ? (string) $row['city'] : '',
				'state'        => isset( $row['state'] ) ? (string) $row['state'] : '',
				'company_name' => isset( $job['inputs']['company_name'] ) ? (string) $job['inputs']['company_name'] : '',
				'phone'        => isset( $job['inputs']['phone'] ) ? (string) $job['inputs']['phone'] : '',
				'address'      => isset( $job['inputs']['address'] ) ? (string) $job['inputs']['address'] : '',
			);
		}

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
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Fetching results: api_job_id=' . $job['api_job_id'] . ' cursor=' . $cursor . PHP_EOL, FILE_APPEND );
			$results = $this->api_get_bulk_job_results( $api_url, $license_key, $job['api_job_id'], $cursor, 10 );
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] API results: ' . wp_json_encode( $results ) . PHP_EOL, FILE_APPEND );
			$acked_ids = array();
			if ( ! empty( $results['ok'] ) && is_array( $results['data'] ) && isset( $results['data']['items'] ) && is_array( $results['data']['items'] ) ) {
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Processing ' . count( $results['data']['items'] ) . ' result items' . PHP_EOL, FILE_APPEND );
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
					
					if ( 'failed' === $item_status ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Item failed: ' . $error . PHP_EOL, FILE_APPEND );
						if ( isset( $job['rows'][ $idx ] ) ) {
							$job['rows'][ $idx ]['status'] = 'failed';
							$job['rows'][ $idx ]['message'] = '' !== $error ? $error : __( 'Generation failed.', 'seogen' );
							$job['rows'][ $idx ]['post_id'] = 0;
						}
						$acked_ids[] = $item_id;
						continue;
					}
					
					if ( ! is_array( $result_json ) ) {
						file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping item: result_json not array' . PHP_EOL, FILE_APPEND );
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
					$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $blocks );

					$postarr = array(
						'post_type'    => 'programmatic_page',
						'post_status'  => 'draft',
						'post_title'   => $title,
						'post_name'    => sanitize_title( $slug ),
						'post_content' => $gutenberg_markup,
					);

					file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Creating/updating post: title=' . $title . ' slug=' . $slug . PHP_EOL, FILE_APPEND );
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
					$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'programmatic_page', 0 );
					if ( $unique_slug ) {
						wp_update_post(
							array(
								'ID'        => $post_id,
								'post_name' => $unique_slug,
							)
						);
					}

					update_post_meta( $post_id, '_hyper_local_managed', '1' );
					if ( '' !== $canonical_key ) {
						update_post_meta( $post_id, '_hyper_local_key', $canonical_key );
					}
					update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
					update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
					update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );

					$service_for_meta = '';
					if ( isset( $job['rows'][ $idx ] ) && isset( $job['rows'][ $idx ]['service'] ) ) {
						$service_for_meta = sanitize_text_field( (string) $job['rows'][ $idx ]['service'] );
					}
					$this->apply_seo_plugin_meta( $post_id, $service_for_meta, $title, $meta_description, true );

					if ( isset( $job['rows'][ $idx ] ) ) {
						$job['rows'][ $idx ]['status'] = 'success';
						$job['rows'][ $idx ]['message'] = __( 'Imported.', 'seogen' );
						$job['rows'][ $idx ]['post_id'] = $post_id;
					}
					$acked_ids[] = $item_id;
				}
				if ( isset( $results['data']['next_cursor'] ) ) {
					$job['api_cursor'] = sanitize_text_field( (string) $results['data']['next_cursor'] );
				}
			}
			if ( ! empty( $acked_ids ) ) {
				$this->api_ack_bulk_job_items( $api_url, $license_key, $job['api_job_id'], $acked_ids );
			}
			$this->save_bulk_job( $job_id, $job );
		} else {
			if ( isset( $job['status'] ) && in_array( (string) $job['status'], array( 'pending', 'running' ), true ) ) {
				$this->schedule_bulk_job( $job_id );
			}
		}
		$rows = array();
		if ( isset( $job['rows'] ) && is_array( $job['rows'] ) ) {
			foreach ( $job['rows'] as $row ) {
				$edit_url = '';
				if ( isset( $row['post_id'] ) && (int) $row['post_id'] > 0 ) {
					$edit_url = get_edit_post_link( (int) $row['post_id'], 'raw' );
				}
				$rows[] = array(
					'service'  => isset( $row['service'] ) ? (string) $row['service'] : '',
					'city'     => isset( $row['city'] ) ? (string) $row['city'] : '',
					'state'    => isset( $row['state'] ) ? (string) $row['state'] : '',
					'status'   => isset( $row['status'] ) ? (string) $row['status'] : '',
					'message'  => isset( $row['message'] ) ? (string) $row['message'] : '',
					'edit_url' => $edit_url ? (string) $edit_url : '',
				);
			}
		}
		wp_send_json_success(
			array(
				'id'         => (string) $job_id,
				'status'     => isset( $job['status'] ) ? (string) $job['status'] : '',
				'total_rows' => isset( $job['total_rows'] ) ? (int) $job['total_rows'] : 0,
				'processed'  => isset( $job['processed'] ) ? (int) $job['processed'] : 0,
				'success'    => isset( $job['success'] ) ? (int) $job['success'] : 0,
				'failed'     => isset( $job['failed'] ) ? (int) $job['failed'] : 0,
				'skipped'    => isset( $job['skipped'] ) ? (int) $job['skipped'] : 0,
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
		$processed_in_run = 0;
		$update_existing = ( isset( $job['update_existing'] ) && '1' === (string) $job['update_existing'] );
		$common_inputs = ( isset( $job['inputs'] ) && is_array( $job['inputs'] ) ) ? $job['inputs'] : array();
		$company_name = isset( $common_inputs['company_name'] ) ? trim( (string) $common_inputs['company_name'] ) : '';
		$phone = isset( $common_inputs['phone'] ) ? trim( (string) $common_inputs['phone'] ) : '';
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

		foreach ( $job['rows'] as $i => $row ) {
			if ( $processed_in_run >= $batch_size ) {
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
				$processed_in_run++;
				continue;
			}

			$existing_id = $this->find_existing_post_id_by_key( $key );
			if ( $existing_id > 0 && ! $update_existing ) {
				$job['rows'][ $i ]['status'] = 'skipped';
				$job['rows'][ $i ]['message'] = __( 'Existing page found for key; skipping.', 'seogen' );
				$job['rows'][ $i ]['post_id'] = $existing_id;
				$job['skipped'] = isset( $job['skipped'] ) ? ( (int) $job['skipped'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				$processed_in_run++;
				continue;
			}

			$inputs = array(
				'service'      => $service,
				'city'         => $city,
				'state'        => $state,
				'company_name' => sanitize_text_field( $company_name ),
				'phone'        => sanitize_text_field( $phone ),
				'address'      => sanitize_text_field( $address ),
			);
			$payload = $this->build_generate_preview_payload( $settings, $inputs );
			$payload['preview'] = false;
			$url = trailingslashit( $api_url ) . 'generate-page';
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
				error_log( '[HyperLocal Bulk] API error job_id=' . $job_id . ' row=' . $key . ' msg=' . $response->get_error_message() );
				$job['rows'][ $i ]['status'] = 'failed';
				$job['rows'][ $i ]['message'] = $response->get_error_message();
				$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				$processed_in_run++;
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
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
			$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $full_data['blocks'] );

			$postarr = array(
				'post_type'    => 'programmatic_page',
				'post_status'  => 'draft',
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
				$job['rows'][ $i ]['status'] = 'failed';
				$job['rows'][ $i ]['message'] = $post_id->get_error_message();
				$job['failed'] = isset( $job['failed'] ) ? ( (int) $job['failed'] + 1 ) : 1;
				$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
				$processed_in_run++;
				continue;
			}
			$post_id = (int) $post_id;
			$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'programmatic_page', 0 );
			if ( $unique_slug ) {
				wp_update_post(
					array(
						'ID'        => $post_id,
						'post_name' => $unique_slug,
					)
				);
			}

			update_post_meta( $post_id, '_hyper_local_managed', '1' );
			update_post_meta( $post_id, '_hyper_local_key', $key );
			update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
			update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $full_data ) );
			update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );
			$this->apply_seo_plugin_meta( $post_id, $service, $title, $meta_description, true );

			$job['rows'][ $i ]['status'] = 'success';
			$job['rows'][ $i ]['message'] = __( 'Created.', 'seogen' );
			$job['rows'][ $i ]['post_id'] = $post_id;
			$job['success'] = isset( $job['success'] ) ? ( (int) $job['success'] + 1 ) : 1;
			$job['processed'] = isset( $job['processed'] ) ? ( (int) $job['processed'] + 1 ) : 1;
			$processed_in_run++;
		}

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
}
