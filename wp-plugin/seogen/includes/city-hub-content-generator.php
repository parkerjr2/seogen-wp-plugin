<?php
/**
 * City Hub Content Generator
 * Generates AI content for city hub placeholder pages after bulk service page generation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function seogen_generate_city_hub_content( $job_id, $job ) {
	// Get settings using the correct option name
	$settings = get_option( 'hyper_local_settings', array() );
	$api_url = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
	$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
	
	if ( '' === $api_url || '' === $license_key ) {
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR: Cannot generate city hubs - missing API settings (api_url=' . $api_url . ', license_key=' . ( $license_key ? 'SET' : 'EMPTY' ) . ')' . PHP_EOL, FILE_APPEND );
		return;
	}
	
	$config = get_option( 'hyper_local_business_config', array() );
	$company_name = isset( $job['inputs']['company_name'] ) ? $job['inputs']['company_name'] : '';
	$phone = isset( $job['inputs']['phone'] ) ? $job['inputs']['phone'] : '';
	$email = isset( $job['inputs']['email'] ) ? $job['inputs']['email'] : '';
	$address = isset( $job['inputs']['address'] ) ? $job['inputs']['address'] : '';
	
	// Get hubs from business config
	$hubs = isset( $config['hubs'] ) && is_array( $config['hubs'] ) ? $config['hubs'] : array();
	$default_hub = ! empty( $hubs ) ? $hubs[0] : null;
	
	if ( ! $default_hub ) {
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR: No hubs configured for city hub generation' . PHP_EOL, FILE_APPEND );
		return;
	}
	
	$hub_label = isset( $default_hub['label'] ) ? $default_hub['label'] : 'Services';
	$hub_slug = isset( $default_hub['slug'] ) ? $default_hub['slug'] : '';
	$hub_key = isset( $default_hub['key'] ) ? $default_hub['key'] : '';
	$vertical = isset( $config['vertical'] ) ? $config['vertical'] : 'electrician';
	
	// Iterate through city hub map and generate content for each
	foreach ( $job['city_hub_map'] as $city_slug => $city_hub_id ) {
		// Get the city hub post
		$city_hub_post = get_post( $city_hub_id );
		if ( ! $city_hub_post ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: City hub not found: ' . $city_slug . ' (ID: ' . $city_hub_id . ')' . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		// Check if it's a placeholder
		$is_placeholder = get_post_meta( $city_hub_id, '_is_placeholder', true );
		if ( '1' !== $is_placeholder ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Skipping city hub (not a placeholder): ' . $city_slug . ' (ID: ' . $city_hub_id . ')' . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		// Parse city and state from slug (format: city-state)
		$parts = explode( '-', $city_slug );
		if ( count( $parts ) < 2 ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: Invalid city slug format: ' . $city_slug . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		$state = strtoupper( array_pop( $parts ) );
		$city = ucwords( str_replace( '-', ' ', implode( '-', $parts ) ) );
		
		// Build API payload for city hub generation
		$payload = array(
			'license_key' => $license_key,
			'page_mode' => 'city_hub',
			'vertical' => $vertical,
			'hub_key' => $hub_key,
			'hub_label' => $hub_label,
			'hub_slug' => $hub_slug,
			'city' => $city,
			'state' => $state,
			'city_slug' => $city_slug,
			'company_name' => $company_name,
			'phone' => $phone,
			'email' => $email,
			'address' => $address,
			'business_name' => $company_name,
			'cta_text' => 'Request a Free Estimate',
			'preview' => false,
		);
		
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Generating city hub content: ' . $city . ', ' . $state . ' (ID: ' . $city_hub_id . ')' . PHP_EOL, FILE_APPEND );
		
		// Call API to generate city hub content
		$url = trailingslashit( $api_url ) . 'generate-page';
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 180,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body' => wp_json_encode( $payload ),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR generating city hub: ' . $response->get_error_message() . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		
		if ( 200 !== $code ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR generating city hub: HTTP ' . $code . ' - ' . $body . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || ! isset( $data['blocks'] ) || ! is_array( $data['blocks'] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR: Invalid city hub response format' . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		// Build Gutenberg content from blocks
		$page_mode = isset( $data['page_mode'] ) ? $data['page_mode'] : 'city_hub';
		$gutenberg_markup = seogen_build_gutenberg_blocks( $data['blocks'], $page_mode );
		
		// Prepend header template if configured
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_post = get_post( $header_template_id );
			if ( $header_post ) {
				$header_content = $header_post->post_content;
				if ( '' !== $header_content ) {
					$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
					$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
				}
			}
		}
		
		// Append footer template if configured
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_post = get_post( $footer_template_id );
			if ( $footer_post ) {
				$footer_content = $footer_post->post_content;
				if ( '' !== $footer_content ) {
					$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
					$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
				}
			}
		}
		
		// Update the city hub post with generated content
		$postarr = array(
			'ID' => $city_hub_id,
			'post_content' => $gutenberg_markup,
		);
		
		$result = wp_update_post( $postarr, true );
		
		if ( is_wp_error( $result ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] ERROR updating city hub: ' . $result->get_error_message() . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		// Update meta to mark as no longer a placeholder
		update_post_meta( $city_hub_id, '_is_placeholder', '0' );
		update_post_meta( $city_hub_id, '_hyper_local_source_json', wp_json_encode( $data ) );
		
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Successfully generated city hub content: ' . $city . ', ' . $state . ' (ID: ' . $city_hub_id . ')' . PHP_EOL, FILE_APPEND );
	}
	
	file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Completed city hub content generation for job: ' . $job_id . PHP_EOL, FILE_APPEND );
}

/**
 * Build Gutenberg content from blocks array
 * Simplified version for city hub generation
 */
function seogen_build_gutenberg_blocks( $blocks, $page_mode ) {
	if ( ! is_array( $blocks ) ) {
		return '';
	}
	
	$output = '';
	
	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) || ! isset( $block['type'] ) ) {
			continue;
		}
		
		$type = $block['type'];
		
		if ( 'heading' === $type ) {
			$level = isset( $block['level'] ) ? (int) $block['level'] : 2;
			$text = isset( $block['text'] ) ? $block['text'] : '';
			$output .= '<!-- wp:heading {"level":' . $level . '} -->' . "\n";
			$output .= '<h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '>' . "\n";
			$output .= '<!-- /wp:heading -->' . "\n\n";
		} elseif ( 'paragraph' === $type ) {
			$text = isset( $block['text'] ) ? $block['text'] : '';
			$output .= '<!-- wp:paragraph -->' . "\n";
			$output .= '<p>' . wp_kses_post( $text ) . '</p>' . "\n";
			$output .= '<!-- /wp:paragraph -->' . "\n\n";
		} elseif ( 'faq' === $type ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			$answer = isset( $block['answer'] ) ? $block['answer'] : '';
			$output .= '<!-- wp:details {"summary":"' . esc_attr( $question ) . '"} -->' . "\n";
			$output .= '<details class="wp-block-details"><summary>' . esc_html( $question ) . '</summary>' . "\n";
			$output .= '<!-- wp:paragraph -->' . "\n";
			$output .= '<p>' . wp_kses_post( $answer ) . '</p>' . "\n";
			$output .= '<!-- /wp:paragraph --></details>' . "\n";
			$output .= '<!-- /wp:details -->' . "\n\n";
		} elseif ( 'cta' === $type ) {
			$text = isset( $block['text'] ) ? $block['text'] : 'Request a Free Estimate';
			$phone = isset( $block['phone'] ) ? $block['phone'] : '';
			$output .= '<!-- wp:buttons -->' . "\n";
			$output .= '<div class="wp-block-buttons">' . "\n";
			$output .= '<!-- wp:button {"className":"is-style-fill"} -->' . "\n";
			$output .= '<div class="wp-block-button is-style-fill">';
			if ( $phone ) {
				$output .= '<a class="wp-block-button__link wp-element-button" href="tel:' . esc_attr( $phone ) . '">' . esc_html( $text ) . '</a>';
			} else {
				$output .= '<a class="wp-block-button__link wp-element-button">' . esc_html( $text ) . '</a>';
			}
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:button -->' . "\n";
			$output .= '</div>' . "\n";
			$output .= '<!-- /wp:buttons -->' . "\n\n";
		}
	}
	
	return $output;
}
