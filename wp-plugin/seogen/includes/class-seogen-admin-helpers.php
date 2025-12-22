<?php
/**
 * City Hub Quality Improvement Helpers
 * 
 * Helper functions for improving City Hub page output quality:
 * - Parent hub link generation and positioning
 * - City name repetition reduction
 * - Duplicate FAQ heading removal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Admin_City_Hub_Helpers {

	/**
	 * Remove service enumeration paragraphs from City Hub content
	 * 
	 * Detects and removes paragraphs that enumerate specific services (doorway-style content).
	 * Looks for patterns like "Our offerings include X, Y, Z" or "We offer A, B, and C".
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Cleaned markup with service enumeration removed
	 */
	private function remove_service_enumeration_paragraphs( $markup ) {
		// Get services from cache to build detection patterns
		$services = get_option( 'hyper_local_services_cache', array() );
		if ( empty( $services ) || ! is_array( $services ) ) {
			return $markup;
		}
		
		// Build list of service names for detection (lowercase for matching)
		$service_names = array();
		foreach ( $services as $service ) {
			if ( isset( $service['name'] ) ) {
				$service_names[] = strtolower( trim( $service['name'] ) );
			}
		}
		
		if ( empty( $service_names ) ) {
			return $markup;
		}
		
		// Split markup into blocks
		$blocks = preg_split( '/(<!-- wp:[^>]+ -->|<!-- \/wp:[^>]+ -->)/', $markup, -1, PREG_SPLIT_DELIM_CAPTURE );
		
		$output = '';
		$in_paragraph = false;
		$paragraph_content = '';
		$paragraph_open_tag = '';
		
		foreach ( $blocks as $block ) {
			// Detect paragraph block opening
			if ( preg_match( '/^<!-- wp:paragraph/', $block ) ) {
				$in_paragraph = true;
				$paragraph_content = '';
				$paragraph_open_tag = $block;
				continue;
			}
			
			// Detect paragraph block closing
			if ( $in_paragraph && preg_match( '/^<!-- \/wp:paragraph/', $block ) ) {
				$in_paragraph = false;
				
				// Check if this paragraph enumerates services
				$is_enumeration = $this->is_service_enumeration_paragraph( $paragraph_content, $service_names );
				
				if ( ! $is_enumeration ) {
					// Keep this paragraph
					$output .= $paragraph_open_tag . $paragraph_content . $block;
				}
				// If it IS enumeration, skip it (don't add to output)
				
				continue;
			}
			
			// Collect paragraph content
			if ( $in_paragraph ) {
				$paragraph_content .= $block;
			} else {
				$output .= $block;
			}
		}
		
		return $output;
	}
	
	/**
	 * Check if a paragraph contains service enumeration
	 * 
	 * @param string $content Paragraph content (HTML)
	 * @param array $service_names Array of lowercase service names
	 * @return bool True if paragraph enumerates services
	 */
	private function is_service_enumeration_paragraph( $content, $service_names ) {
		// Strip HTML tags for text analysis
		$text = wp_strip_all_tags( $content );
		$text_lower = strtolower( $text );
		
		// Check for enumeration trigger phrases
		$enumeration_triggers = array(
			'our offerings include',
			'we offer',
			'services include',
			'including',
			'such as',
			'from',
			'range of',
			'everything from',
		);
		
		$has_trigger = false;
		foreach ( $enumeration_triggers as $trigger ) {
			if ( false !== strpos( $text_lower, $trigger ) ) {
				$has_trigger = true;
				break;
			}
		}
		
		if ( ! $has_trigger ) {
			return false;
		}
		
		// Count how many known service names appear in this paragraph
		$service_matches = 0;
		foreach ( $service_names as $service_name ) {
			if ( false !== strpos( $text_lower, $service_name ) ) {
				$service_matches++;
			}
		}
		
		// If paragraph has trigger phrase AND mentions 2+ services, it's enumeration
		return $service_matches >= 2;
	}

	/**
	 * Build parent hub link HTML with clean naming
	 * 
	 * @param string $hub_key Hub key to find parent Service Hub
	 * @return string HTML block or empty string if parent not found
	 */
	private function build_parent_hub_link_html( $hub_key ) {
		$hub_post_id = $this->find_service_hub_post_id( $hub_key );
		if ( $hub_post_id <= 0 ) {
			return '';
		}

		$parent_hub_url = get_permalink( $hub_post_id );
		$parent_hub_title = get_the_title( $hub_post_id );
		
		// Clean title for display: Remove business name, location, and trailing 'Services'
		// Example: "Residential Electrical Services | M Electric" → "Residential Electrical"
		$clean_title = $parent_hub_title;
		
		// Remove business name after pipe
		if ( false !== strpos( $clean_title, '|' ) ) {
			$parts = explode( '|', $clean_title );
			$clean_title = trim( $parts[0] );
		}
		
		// Remove trailing location phrases
		$clean_title = preg_replace( '/\s+(in|near|around|for)\s+[A-Z][^,]+,?\s*[A-Z]{2}$/i', '', $clean_title );
		
		// Remove trailing "Services" or "Service"
		$clean_title = preg_replace( '/\s+services?$/i', '', $clean_title );
		$clean_title = trim( $clean_title );
		
		// Generate link HTML - simple "Back to {Name}" without redundant "services"
		$link_text = '← Back to ' . esc_html( $clean_title );
		$parent_hub_link_html = '<p class="seogen-parent-hub-link"><a href="' . esc_url( $parent_hub_url ) . '">' . $link_text . '</a></p>';
		
		return '<!-- wp:html -->' . $parent_hub_link_html . '<!-- /wp:html -->';
	}

	/**
	 * Inject HTML block immediately after first H1 in Gutenberg markup
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $html_block HTML block to inject
	 * @return string Modified markup
	 */
	private function inject_after_h1( $markup, $html_block ) {
		if ( empty( $html_block ) ) {
			return $markup;
		}

		// Find first closing </h1> tag
		$h1_close_pos = strpos( $markup, '</h1>' );
		
		if ( false !== $h1_close_pos ) {
			// Insert after </h1> with proper spacing
			$insert_pos = $h1_close_pos + strlen( '</h1>' );
			$markup = substr_replace( $markup, "\n\n" . $html_block . "\n\n", $insert_pos, 0 );
		} else {
			// No H1 found, insert at beginning of content
			$markup = $html_block . "\n\n" . $markup;
		}
		
		return $markup;
	}

	/**
	 * Reduce city name repetition to avoid "scaled footprint" appearance
	 * 
	 * @param string $markup Gutenberg markup
	 * @param array $city City data with 'name' and 'state' keys
	 * @return string Modified markup
	 */
	private function cleanup_city_repetition( $markup, $city ) {
		if ( empty( $city['name'] ) ) {
			return $markup;
		}

		$city_name = $city['name'];
		$state = isset( $city['state'] ) ? $city['state'] : '';
		
		// 1) De-localize headings: "Services We Offer in {City}" → "Services We Offer"
		// Only remove " in {City}" at the end of headings
		$markup = preg_replace(
			'/<h([2-6])>(.*?)\s+in\s+' . preg_quote( $city_name, '/' ) . '<\/h\1>/i',
			'<h$1>$2</h$1>',
			$markup
		);
		
		// 2) Body repetition cap: Replace "In {City}," with "In the area," after first paragraph
		// Find first paragraph closing tag
		$first_p_close = strpos( $markup, '</p>' );
		
		if ( false !== $first_p_close ) {
			// Only apply replacements after first paragraph
			$before_first_p = substr( $markup, 0, $first_p_close + 4 );
			$after_first_p = substr( $markup, $first_p_close + 4 );
			
			// Replace leading "In {City}," patterns with "In the area,"
			$after_first_p = preg_replace(
				'/\bIn\s+' . preg_quote( $city_name, '/' ) . ',\s+/i',
				'In the area, ',
				$after_first_p
			);
			
			// Also replace "in {City}, {State}" in later sections
			if ( ! empty( $state ) ) {
				$after_first_p = preg_replace(
					'/\bin\s+' . preg_quote( $city_name, '/' ) . ',\s*' . preg_quote( $state, '/' ) . '\b/i',
					'in the area',
					$after_first_p
				);
			}
			
			$markup = $before_first_p . $after_first_p;
		}
		
		return $markup;
	}

	/**
	 * Remove duplicate FAQ heading if both "Frequently Asked Questions" and "FAQ" exist
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Modified markup
	 */
	private function remove_duplicate_faq_heading( $markup ) {
		// Check if "Frequently Asked Questions" heading exists
		$has_full_faq = ( false !== stripos( $markup, 'Frequently Asked Questions' ) );
		
		if ( ! $has_full_faq ) {
			// No duplicate issue, return as-is
			return $markup;
		}
		
		// Remove standalone "FAQ" heading block that appears after "Frequently Asked Questions"
		// Match Gutenberg heading block with just "FAQ"
		$markup = preg_replace(
			'/<!-- wp:heading[^>]*? -->\s*<h[2-6]>FAQ<\/h[2-6]>\s*<!-- \/wp:heading -->/i',
			'',
			$markup,
			1 // Only remove first occurrence
		);
		
		// Also handle plain HTML headings (non-Gutenberg)
		$markup = preg_replace(
			'/<h[2-6]>FAQ<\/h[2-6]>/i',
			'',
			$markup,
			1
		);
		
		return $markup;
	}

	/**
	 * Polish parent hub link markup with editorial context
	 * 
	 * @param string $html Parent hub link HTML
	 * @return string Polished HTML with editorial lead-in
	 */
	private function polish_parent_hub_link_markup( $html ) {
		if ( empty( $html ) ) {
			return $html;
		}
		
		// Add editorial context: "Looking for an overview? ← Back to {Service}"
		// This makes the link feel more natural and less like navigation chrome
		$html = str_replace(
			'<p class="seogen-parent-hub-link"><a href=',
			'<p class="seogen-parent-hub-link">Looking for an overview? <a href=',
			$html
		);
		
		return $html;
	}

	/**
	 * Fix locality replacement artifacts (e.g., "In the area, OK")
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Fixed markup
	 */
	private function fix_locality_artifacts( $markup ) {
		// Fix broken phrases like "In the area, OK" or "in the area OK"
		// These occur when state abbreviation remains after city name replacement
		
		// Replace "In the area, OK" (and similar state codes) with "Locally,"
		$markup = preg_replace(
			'/\bIn the area,?\s+[A-Z]{2}\b/i',
			'Locally',
			$markup
		);
		
		// Also catch lowercase variants mid-sentence
		$markup = preg_replace(
			'/\bin the area,?\s+[A-Z]{2}\b/',
			'locally',
			$markup
		);
		
		return $markup;
	}

	/**
	 * Reduce generic "any-city" repetition patterns
	 * 
	 * @param string $markup Gutenberg markup
	 * @param array $city City data
	 * @return string Modified markup
	 */
	private function reduce_generic_city_repetition( $markup, $city ) {
		if ( empty( $city['name'] ) ) {
			return $markup;
		}
		
		$city_name = $city['name'];
		
		// Identify generic phrases that signal templated content
		$generic_patterns = array(
			'Choosing local',
			'Our team understands',
			'offers numerous benefits',
			'local expertise',
			'familiar with the area',
		);
		
		// Find ONE paragraph containing both a generic phrase AND the city name
		// Remove city name from that paragraph only (conservative approach)
		foreach ( $generic_patterns as $pattern ) {
			// Match paragraph containing both the pattern and city name
			if ( preg_match( '/<p>([^<]*' . preg_quote( $pattern, '/' ) . '[^<]*' . preg_quote( $city_name, '/' ) . '[^<]*)<\/p>/i', $markup, $matches ) ) {
				$original_p = $matches[0];
				$p_content = $matches[1];
				
				// Remove city name from this paragraph
				$cleaned_content = preg_replace(
					'/\b' . preg_quote( $city_name, '/' ) . '\b/i',
					'',
					$p_content,
					1 // Only first occurrence
				);
				
				// Clean up any double spaces or awkward punctuation
				$cleaned_content = preg_replace( '/\s+/', ' ', $cleaned_content );
				$cleaned_content = preg_replace( '/\s+,/', ',', $cleaned_content );
				$cleaned_content = preg_replace( '/\s+\./', '.', $cleaned_content );
				$cleaned_content = trim( $cleaned_content );
				
				$cleaned_p = '<p>' . $cleaned_content . '</p>';
				$markup = str_replace( $original_p, $cleaned_p, $markup );
				
				// Only rewrite ONE paragraph per page
				break;
			}
		}
		
		return $markup;
	}

	/**
	 * Cleanup FAQ locality references for clarity
	 * 
	 * @param string $markup Gutenberg markup
	 * @param array $city City data
	 * @return string Modified markup
	 */
	private function cleanup_faq_locality( $markup, $city ) {
		if ( empty( $city['name'] ) ) {
			return $markup;
		}
		
		$city_name = $city['name'];
		
		// Find FAQ section (after "Frequently Asked Questions" heading)
		$faq_start = stripos( $markup, 'Frequently Asked Questions' );
		if ( false === $faq_start ) {
			// Try "FAQ" heading
			$faq_start = stripos( $markup, '<h2>FAQ</h2>' );
		}
		
		if ( false === $faq_start ) {
			// No FAQ section found
			return $markup;
		}
		
		// Extract FAQ section (from heading to end of content)
		$before_faq = substr( $markup, 0, $faq_start );
		$faq_section = substr( $markup, $faq_start );
		
		// In FAQ answers only: Remove "in {City}" when it adds no value
		// Keep city references in questions (they're often location-specific)
		// Only remove from answer paragraphs (not from question headings)
		
		// Remove "in {City}" from FAQ answer paragraphs
		$faq_section = preg_replace(
			'/(<p>[^<]*?)\s+in\s+' . preg_quote( $city_name, '/' ) . '\b/i',
			'$1',
			$faq_section
		);
		
		// Remove "in the area" from FAQ answers when it's filler
		$faq_section = preg_replace(
			'/(<p>[^<]*?)\s+in the area\b/i',
			'$1',
			$faq_section
		);
		
		// Clean up any double spaces
		$faq_section = preg_replace( '/\s+/', ' ', $faq_section );
		
		return $before_faq . $faq_section;
	}

	/**
	 * PRIORITY 1: HARD KILL "In the area, OK" BUG (ZERO TOLERANCE)
	 * This is a REQUIRED post-generation cleanup pass - NOT best-effort
	 * This bug must NEVER appear in final output - NO EXCEPTIONS
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Fixed markup with zero-tolerance validation
	 */
	private function kill_in_the_area_ok_bug( $markup ) {
		// SCALE SAFETY GUARD: This is a mandatory cleanup pass that runs AFTER all other
		// city-name replacement logic to ensure "In the area, OK" never appears
		// This is NON-NEGOTIABLE and must be enforced programmatically
		
		// Pattern 1: "In the area, OK" at start of sentence → "Locally,"
		$markup = preg_replace(
			'/\bIn the area,?\s+[A-Z]{2}\b([^a-z])/i',
			'Locally$1',
			$markup
		);
		
		// Pattern 2: "in the area, OK" mid-sentence → remove entirely
		$markup = preg_replace(
			'/\s+in the area,?\s+[A-Z]{2}\b/',
			'',
			$markup
		);
		
		// Pattern 3: Catch any remaining "area, OK" patterns (zero-tolerance validation)
		$markup = preg_replace(
			'/\barea,?\s+[A-Z]{2}\b/',
			'area',
			$markup
		);
		
		// Clean up any double spaces or awkward punctuation from removals
		$markup = preg_replace( '/\s+/', ' ', $markup );
		$markup = preg_replace( '/\s+,/', ',', $markup );
		$markup = preg_replace( '/\s+\./', '.', $markup );
		
		// VALIDATION: Assert that "area, OK" does NOT exist anywhere in markup
		// If it still exists, fail the cleanup step and remove the phrase entirely
		if ( false !== stripos( $markup, 'area, OK' ) || false !== stripos( $markup, 'area OK' ) ) {
			// Emergency fallback: Remove any remaining instances
			$markup = str_ireplace( array( 'area, OK', 'area OK' ), 'area', $markup );
		}
		
		return $markup;
	}

	/**
	 * PRIORITY 2: Generate EXACTLY ONE city-specific nuance using gpt-4o-mini
	 * This nuance exists ONLY to break large-scale duplication and add human realism
	 * NO MORE, NO LESS - exactly ONE per City Hub page
	 * 
	 * @param array $city City data with 'name' and 'state'
	 * @param string $vertical Vertical (e.g., 'electrician', 'plumber')
	 * @param string $hub_key Hub key (e.g., 'residential', 'commercial')
	 * @return string City nuance text or empty string if generation fails
	 */
	private function generate_city_nuance_gpt( $city, $vertical, $hub_key ) {
		if ( empty( $city['name'] ) || empty( $city['state'] ) ) {
			return '';
		}
		
		$settings = $this->get_settings();
		$api_key = isset( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '';
		
		if ( empty( $api_key ) ) {
			return '';
		}
		
		$city_name = $city['name'];
		$state = $city['state'];
		
		// SCALE SAFETY GUARD: Construct prompt for gpt-4o-mini
		// This generates EXACTLY ONE subtle nuance to break duplication patterns
		$prompt = "Provide ONE short, factual, non-specific observation (1 sentence, max 25 words) relevant to {$hub_key} {$vertical} work in {$city_name}, {$state}.\n\nChoose ONE category only:\n- housing age or housing stock patterns\n- common upgrade or repair triggers\n- general permitting or inspection considerations (generic, non-legal)\n\nDo NOT mention neighborhoods, statistics, dates, or the business.\nDo NOT exaggerate.\nReturn plain text only.";
		
		$payload = array(
			'model' => 'gpt-4o-mini',
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'max_tokens' => 50,
			'temperature' => 0.7,
		);
		
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode( $payload ),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return '';
		}
		
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return '';
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return '';
		}
		
		$nuance = trim( $data['choices'][0]['message']['content'] );
		
		// VALIDATION: Enforce strict rules for city nuance
		// - Must be 1 sentence, max 25 words, contains city name
		// - City name must appear exactly ONCE
		// - Must not be empty, vague, or redundant
		if ( empty( $nuance ) || strlen( $nuance ) > 200 || stripos( $nuance, $city_name ) === false ) {
			return '';
		}
		
		// Ensure city name appears exactly once (EXACTLY ONE)
		if ( substr_count( strtolower( $nuance ), strtolower( $city_name ) ) !== 1 ) {
			return '';
		}
		
		// Word count validation: max 25 words
		$word_count = str_word_count( $nuance );
		if ( $word_count > 25 ) {
			return '';
		}
		
		return $nuance;
	}

	/**
	 * Insert city nuance after first intro paragraph
	 * ENFORCES: EXACTLY ONE nuance paragraph per City Hub page (NO MORE, NO LESS)
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $nuance_text City nuance text
	 * @return string Modified markup
	 */
	private function insert_city_nuance_after_intro( $markup, $nuance_text ) {
		if ( empty( $nuance_text ) ) {
			return $markup;
		}
		
		// SCALE SAFETY GUARD: Check if nuance already exists (prevent duplicates)
		if ( false !== strpos( $markup, 'seogen-city-nuance' ) ) {
			// Nuance already inserted, do not add another
			return $markup;
		}
		
		// Find first paragraph closing tag (after H1 and parent hub link)
		$first_p_close = strpos( $markup, '</p>' );
		
		if ( false === $first_p_close ) {
			return $markup;
		}
		
		// Find second paragraph closing tag (this is the intro paragraph)
		$second_p_close = strpos( $markup, '</p>', $first_p_close + 4 );
		
		if ( false === $second_p_close ) {
			// Only one paragraph found, insert after it
			$second_p_close = $first_p_close;
		}
		
		// Create nuance paragraph as Gutenberg HTML block
		$nuance_block = "\n\n<!-- wp:html --><p class=\"seogen-city-nuance\">" . esc_html( $nuance_text ) . "</p><!-- /wp:html -->\n\n";
		
		// Insert after intro paragraph
		$insert_pos = $second_p_close + 4;
		$markup = substr_replace( $markup, $nuance_block, $insert_pos, 0 );
		
		return $markup;
	}

	/**
	 * PRIORITY 3: Cap generic paragraphs to MAXIMUM ONE per City Hub page
	 * Generic paragraphs are allowed but strictly limited to prevent duplication
	 * 
	 * @param string $markup Gutenberg markup
	 * @param array $city City data
	 * @return string Modified markup
	 */
	private function enforce_single_generic_paragraph( $markup, $city ) {
		if ( empty( $city['name'] ) ) {
			return $markup;
		}
		
		$city_name = $city['name'];
		$state = isset( $city['state'] ) ? $city['state'] : '';
		
		// SCALE SAFETY GUARD: Identify generic phrases that signal templated content
		$generic_patterns = array(
			'Choosing local',
			'Our team understands',
			'offers numerous benefits',
			'There are several benefits',
			'local expertise',
			'familiar with the area',
			'common challenges',
			'hiring professionals',
		);
		
		$generic_count = 0;
		$max_generic_allowed = 1;
		
		// Find all paragraphs containing generic patterns
		foreach ( $generic_patterns as $pattern ) {
			// Match paragraphs containing the generic pattern
			if ( preg_match_all( '/<p>([^<]*' . preg_quote( $pattern, '/' ) . '[^<]*)<\/p>/i', $markup, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$generic_count++;
					
					// If this is beyond the first generic paragraph, remove city name from it
					if ( $generic_count > $max_generic_allowed ) {
						$original_p = $match[0];
						$p_content = $matches[1][ $generic_count - 1 ][0];
						
						// Remove city name and state from this paragraph
						$cleaned_content = preg_replace(
							'/\b' . preg_quote( $city_name, '/' ) . '\b/i',
							'',
							$p_content
						);
						
						if ( ! empty( $state ) ) {
							$cleaned_content = preg_replace(
								'/\b' . preg_quote( $state, '/' ) . '\b/',
								'',
								$cleaned_content
							);
						}
						
						// Clean up double spaces and awkward punctuation
						$cleaned_content = preg_replace( '/\s+/', ' ', $cleaned_content );
						$cleaned_content = preg_replace( '/\s+,/', ',', $cleaned_content );
						$cleaned_content = preg_replace( '/\s+\./', '.', $cleaned_content );
						$cleaned_content = trim( $cleaned_content );
						
						$cleaned_p = '<p>' . $cleaned_content . '</p>';
						$markup = str_replace( $original_p, $cleaned_p, $markup );
					}
				}
			}
		}
		
		return $markup;
	}

	/**
	 * Apply all City Hub quality improvements to Gutenberg markup
	 * INCLUDES THREE MANDATORY SCALE SAFETY GUARDS (NON-NEGOTIABLE)
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $hub_key Hub key for parent link
	 * @param array $city City data
	 * @param string $vertical Vertical for city nuance generation
	 * @return string Improved markup with scale safety guards enforced
	 */
	private function apply_city_hub_quality_improvements( $markup, $hub_key, $city, $vertical = '' ) {
		// 1) Build parent hub link
		$parent_hub_link = $this->build_parent_hub_link_html( $hub_key );
		
		// 2) Polish parent hub link with editorial context
		$parent_hub_link = $this->polish_parent_hub_link_markup( $parent_hub_link );
		
		// 3) Inject parent hub link after H1
		$markup = $this->inject_after_h1( $markup, $parent_hub_link );
		
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		// PRIORITY 1.5: Remove service enumeration paragraphs (ANTI-DOORWAY GUARD)
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		$markup = $this->remove_service_enumeration_paragraphs( $markup );
		
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		// PRIORITY 2: Add EXACTLY ONE city-specific nuance (SCALE SAFETY GUARD)
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		if ( ! empty( $vertical ) ) {
			$city_nuance = $this->generate_city_nuance_gpt( $city, $vertical, $hub_key );
			$markup = $this->insert_city_nuance_after_intro( $markup, $city_nuance );
		}
		
		// 4) Reduce city name repetition
		$markup = $this->cleanup_city_repetition( $markup, $city );
		
		// 5) Fix locality replacement artifacts ("In the area, OK")
		$markup = $this->fix_locality_artifacts( $markup );
		
		// 6) Reduce generic "any-city" repetition patterns
		$markup = $this->reduce_generic_city_repetition( $markup, $city );
		
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		// PRIORITY 3: Cap generic paragraphs to ONE per page (SCALE SAFETY GUARD)
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		$markup = $this->enforce_single_generic_paragraph( $markup, $city );
		
		// 7) Cleanup FAQ locality references
		$markup = $this->cleanup_faq_locality( $markup, $city );
		
		// 8) Remove duplicate FAQ heading
		$markup = $this->remove_duplicate_faq_heading( $markup );
		
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		// PRIORITY 1: HARD KILL "In the area, OK" bug (ZERO TOLERANCE - SCALE SAFETY GUARD)
		// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
		$markup = $this->kill_in_the_area_ok_bug( $markup );
		
		return $markup;
	}
}
