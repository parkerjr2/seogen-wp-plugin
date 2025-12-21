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
		
		// 2) Inject parent hub link after H1
		$markup = $this->inject_after_h1( $markup, $parent_hub_link );
		
		// 3) Reduce city name repetition
		$markup = $this->cleanup_city_repetition( $markup, $city );
		
		// 4) Remove duplicate FAQ heading
		$markup = $this->remove_duplicate_faq_heading( $markup );
		
		return $markup;
	}
}
