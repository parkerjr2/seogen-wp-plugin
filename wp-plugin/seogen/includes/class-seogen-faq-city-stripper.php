<?php
/**
 * FAQ City Mention Stripper
 * 
 * Enforces the rule: service_city pages must have EXACTLY ONE city-specific FAQ question,
 * with all other FAQ items being completely generic (no city in question or answer).
 * 
 * This prevents templated local patterns and doorway signals.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_FAQ_City_Stripper {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Normalize FAQ content for service_city pages
	 * 
	 * IMPORTANT: The localized FAQ template is inserted separately and is THE city-specific FAQ.
	 * ALL backend FAQs must be completely generic (no city in question or answer).
	 * 
	 * @param array $faq_blocks Array of FAQ blocks from backend
	 * @param string $city_name City name to detect/strip
	 * @return array Normalized FAQ blocks (all generic)
	 */
	public static function normalize_service_city_faqs( $faq_blocks, $city_name ) {
		if ( empty( $faq_blocks ) || '' === $city_name ) {
			return $faq_blocks;
		}
		
		// Strip city mentions from ALL backend FAQ items
		// The localized FAQ template (inserted separately) is the ONE city-specific FAQ
		$normalized = array();
		foreach ( $faq_blocks as $block ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			$answer = isset( $block['answer'] ) ? $block['answer'] : '';
			
			// Strip ALL city mentions from both question and answer
			$question = self::strip_city_from_text( $question, $city_name );
			$answer = self::strip_city_from_text( $answer, $city_name );
			
			$normalized[] = array(
				'question' => $question,
				'answer' => $answer,
			);
		}
		
		return $normalized;
	}
	
	/**
	 * Strip city mentions from text
	 * Removes "in {City}" and similar patterns
	 * 
	 * @param string $text Text to clean
	 * @param string $city_name City name to remove
	 * @return string Cleaned text
	 */
	private static function strip_city_from_text( $text, $city_name ) {
		$city_escaped = preg_quote( $city_name, '/' );
		
		// Pattern 1: "in {City}" - most common
		$text = preg_replace( '/\s+in\s+' . $city_escaped . '\b/i', '', $text );
		
		// Pattern 2: "around {City}"
		$text = preg_replace( '/\s+around\s+' . $city_escaped . '\b/i', '', $text );
		
		// Pattern 3: "near {City}"
		$text = preg_replace( '/\s+near\s+' . $city_escaped . '\b/i', '', $text );
		
		// Pattern 4: "{City} businesses" or "{City} property" at start
		$text = preg_replace( '/\b' . $city_escaped . '\s+(businesses|business owners|properties|property|residents|homeowners|contractors|providers)\b/i', '$1', $text );
		
		// Pattern 5: ", {City}" or "({City})" - punctuation-wrapped
		$text = preg_replace( '/[,\(]\s*' . $city_escaped . '\s*[\),]/i', '', $text );
		
		// Pattern 6: Standalone city at start of sentence (after period/newline)
		$text = preg_replace( '/(^|\.\s+)' . $city_escaped . '\s+/i', '$1', $text );
		
		// Clean up any double spaces or awkward punctuation
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = preg_replace( '/\s+([.,;:?!])/', '$1', $text );
		$text = preg_replace( '/([.,;:?!])\s*([.,;:?!])/', '$1', $text );
		
		// Clean up awkward phrases left after stripping
		$text = preg_replace( '/\s+,/', ',', $text );
		$text = str_replace( '  ', ' ', $text );
		
		return trim( $text );
	}
	
	/**
	 * Process FAQ blocks during content building
	 * This is called BEFORE FAQ blocks are converted to Gutenberg markup
	 * 
	 * @param array $blocks All content blocks from backend
	 * @param string $page_mode Page mode
	 * @param string $city_name City name
	 * @param string $service_slug Service slug
	 * @param string $city_slug City slug
	 * @param string $intent_group Intent group
	 * @return array Processed blocks
	 */
	public static function process_content_blocks( $blocks, $page_mode, $city_name, $service_slug = '', $city_slug = '', $intent_group = '' ) {
		// Only process service_city pages
		if ( 'service_city' !== $page_mode || '' === $city_name ) {
			return $blocks;
		}
		
		// Extract FAQ blocks
		$faq_blocks = array();
		$faq_indices = array();
		foreach ( $blocks as $index => $block ) {
			if ( isset( $block['type'] ) && 'faq' === $block['type'] ) {
				$faq_blocks[] = $block;
				$faq_indices[] = $index;
			}
		}
		
		if ( empty( $faq_blocks ) ) {
			return $blocks;
		}
		
		// Normalize FAQ blocks - strip city from ALL backend FAQs
		// The localized FAQ template (inserted separately) is the ONE city-specific FAQ
		$normalized_faqs = self::normalize_service_city_faqs( $faq_blocks, $city_name );
		
		// Replace FAQ blocks in original array
		foreach ( $faq_indices as $i => $original_index ) {
			$blocks[ $original_index ]['question'] = $normalized_faqs[ $i ]['question'];
			$blocks[ $original_index ]['answer'] = $normalized_faqs[ $i ]['answer'];
		}
		
		return $blocks;
	}
}
