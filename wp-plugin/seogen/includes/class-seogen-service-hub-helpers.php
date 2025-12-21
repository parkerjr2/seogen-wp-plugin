<?php
/**
 * Service Hub Quality Improvement Helpers
 * 
 * These helpers apply scale-safety improvements to Service Hub pages:
 * - Remove duplicate FAQ headings
 * - Enforce city link anchor rules
 * - Insert service hub framing sentence
 * - Soften repetitive service headings
 * 
 * @package SEOgen
 */

trait SEOgen_Service_Hub_Helpers {

	/**
	 * A) Remove duplicate FAQ headings from Service Hub pages
	 * MANDATORY: Each Service Hub page may contain EXACTLY ONE FAQ section heading
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Cleaned markup
	 */
	private function remove_duplicate_faq_headings( $markup ) {
		// SCALE SAFETY GUARD: Prevent duplicate FAQ headings that look auto-generated
		
		// Check if both "Frequently Asked Questions" and standalone "FAQ" exist
		$has_full_heading = ( false !== stripos( $markup, 'Frequently Asked Questions' ) );
		$has_short_heading = preg_match( '/<h2[^>]*>FAQ<\/h2>/i', $markup );
		
		if ( $has_full_heading && $has_short_heading ) {
			// Keep "Frequently Asked Questions", remove redundant "FAQ" heading only
			// Do NOT remove FAQ content, only the duplicate heading
			$markup = preg_replace(
				'/<h2[^>]*>FAQ<\/h2>/i',
				'',
				$markup,
				1 // Remove only first occurrence
			);
		}
		
		return $markup;
	}

	/**
	 * B) Enforce city link anchor text rules for Service Hub pages
	 * STANDARDIZE: Ensure city hub links remain natural and safe at scale
	 * 
	 * Rules:
	 * - No keyword-stuffed anchors like "Residential Electrical Services in Broken Arrow"
	 * - Maximum ONE city name per sentence (plain text + anchor combined)
	 * - No service keywords inside city anchors
	 * 
	 * @param string $markup Gutenberg markup
	 * @return string Cleaned markup
	 */
	private function enforce_city_link_anchor_rules( $markup ) {
		// SCALE SAFETY GUARD: Prevent keyword-stuffed city hub link anchors
		
		// Pattern: Find links with city names that include service keywords
		// Example: <a href="...">Residential Electrical Services in Broken Arrow</a>
		// Should be: <a href="...">Broken Arrow</a> or plain text with city name
		
		// This is a conservative cleanup - we look for obvious keyword stuffing patterns
		$keyword_patterns = array(
			'Services in',
			'Service in',
			'Electrician in',
			'Plumber in',
			'Roofer in',
			'HVAC in',
			'Contractor in',
		);
		
		foreach ( $keyword_patterns as $pattern ) {
			// Find anchor tags containing keyword patterns
			$markup = preg_replace(
				'/<a([^>]*)>([^<]*' . preg_quote( $pattern, '/' ) . '\s+([^<]+))<\/a>/i',
				'<a$1>$3</a>', // Keep only the city name part
				$markup
			);
		}
		
		return $markup;
	}

	/**
	 * C) Insert service hub framing sentence near the top
	 * CLARITY: Make it clear that Service Hub pages are overviews
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $hub_label Hub label (e.g., "Residential Electrical")
	 * @return string Modified markup
	 */
	private function insert_service_hub_framing_sentence( $markup, $hub_label ) {
		// SCALE SAFETY GUARD: Add ONE framing sentence to clarify Service Hub role
		
		// Check if framing sentence already exists (prevent duplicates)
		if ( false !== strpos( $markup, 'seogen-hub-framing' ) ) {
			return $markup;
		}
		
		// Create framing sentence
		$framing = "This page provides an overview of our " . strtolower( $hub_label ) . " services, with dedicated pages for each city we serve.";
		
		// Wrap in paragraph with class for identification
		$framing_block = "\n\n<!-- wp:html --><p class=\"seogen-hub-framing\">" . esc_html( $framing ) . "</p><!-- /wp:html -->\n\n";
		
		// Insert after first H1 (similar to city hub parent link injection)
		$h1_close = stripos( $markup, '</h1>' );
		if ( false !== $h1_close ) {
			$insert_pos = $h1_close + 5;
			$markup = substr_replace( $markup, $framing_block, $insert_pos, 0 );
		}
		
		return $markup;
	}

	/**
	 * D) Soften repetitive service headings
	 * ANTI-TEMPLATE SIGNAL: Reduce H2 headings that repeat the same service phrase
	 * 
	 * Rules:
	 * - Allow ONLY 1-2 H2 headings to include the full service name
	 * - Remaining H2s should use natural variations
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $hub_label Hub label (e.g., "Residential Electrical")
	 * @return string Modified markup
	 */
	private function soften_repetitive_service_headings( $markup, $hub_label ) {
		// SCALE SAFETY GUARD: Light de-duplication of repetitive service name in H2s
		
		// Find all H2 headings containing the hub label
		$pattern = '/<h2[^>]*>([^<]*' . preg_quote( $hub_label, '/' ) . '[^<]*)<\/h2>/i';
		
		if ( preg_match_all( $pattern, $markup, $matches, PREG_OFFSET_CAPTURE ) ) {
			$count = count( $matches[0] );
			
			// If more than 2 H2s contain the service name, soften subsequent ones
			if ( $count > 2 ) {
				// Keep first 2, modify the rest
				for ( $i = 2; $i < $count; $i++ ) {
					$original_h2 = $matches[0][$i][0];
					$h2_content = $matches[1][$i][0];
					
					// Remove the hub label from this H2
					$softened_content = str_ireplace( $hub_label, '', $h2_content );
					$softened_content = trim( $softened_content );
					
					// Clean up any leftover words like "Services" at the start
					$softened_content = preg_replace( '/^Services\s+/i', '', $softened_content );
					$softened_content = trim( $softened_content );
					
					// Only replace if we have meaningful content left
					if ( ! empty( $softened_content ) && strlen( $softened_content ) > 3 ) {
						$new_h2 = '<h2>' . $softened_content . '</h2>';
						$markup = str_replace( $original_h2, $new_h2, $markup );
					}
				}
			}
		}
		
		return $markup;
	}

	/**
	 * Apply all Service Hub quality improvements to Gutenberg markup
	 * INCLUDES FOUR SCALE SAFETY GUARDS (DETERMINISTIC POST-GENERATION CLEANUP)
	 * 
	 * @param string $markup Gutenberg markup
	 * @param string $hub_label Hub label for framing and heading cleanup
	 * @return string Improved markup with scale safety guards enforced
	 */
	private function apply_service_hub_quality_improvements( $markup, $hub_label ) {
		// A) Remove duplicate FAQ headings (MANDATORY)
		$markup = $this->remove_duplicate_faq_headings( $markup );
		
		// B) Enforce city link anchor rules (STANDARDIZE)
		$markup = $this->enforce_city_link_anchor_rules( $markup );
		
		// C) Insert service hub framing sentence (CLARITY)
		$markup = $this->insert_service_hub_framing_sentence( $markup, $hub_label );
		
		// D) Soften repetitive service headings (ANTI-TEMPLATE SIGNAL)
		$markup = $this->soften_repetitive_service_headings( $markup, $hub_label );
		
		return $markup;
	}
}
