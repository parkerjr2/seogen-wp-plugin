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
	 * Apply all City Hub quality improvements to Gutenberg markup
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $hub_key Hub key for parent link
	 * @param array $city City data
	 * @return string Improved markup
	 */
	private function apply_city_hub_quality_improvements( $markup, $hub_key, $city ) {
		// 1) Build parent hub link
		$parent_hub_link = $this->build_parent_hub_link_html( $hub_key );
		
		// 2) Polish parent hub link with editorial context
		$parent_hub_link = $this->polish_parent_hub_link_markup( $parent_hub_link );
		
		// 3) Inject parent hub link after H1
		$markup = $this->inject_after_h1( $markup, $parent_hub_link );
		
		// 4) Reduce city name repetition
		$markup = $this->cleanup_city_repetition( $markup, $city );
		
		// 5) Fix locality replacement artifacts ("In the area, OK")
		$markup = $this->fix_locality_artifacts( $markup );
		
		// 6) Reduce generic "any-city" repetition patterns
		$markup = $this->reduce_generic_city_repetition( $markup, $city );
		
		// 7) Cleanup FAQ locality references
		$markup = $this->cleanup_faq_locality( $markup, $city );
		
		// 8) Remove duplicate FAQ heading
		$markup = $this->remove_duplicate_faq_heading( $markup );
		
		return $markup;
	}
}
