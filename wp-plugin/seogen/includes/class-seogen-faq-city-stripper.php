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
	 * @param array $faq_blocks Array of FAQ blocks from backend
	 * @param string $city_name City name to detect/strip
	 * @param bool $has_localized_faq Whether localized FAQ template will be inserted
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $city_slug City slug for deterministic selection
	 * @param string $intent_group Intent group for deterministic selection
	 * @return array Normalized FAQ blocks
	 */
	public static function normalize_service_city_faqs( $faq_blocks, $city_name, $has_localized_faq = true, $service_slug = '', $city_slug = '', $intent_group = '' ) {
		if ( empty( $faq_blocks ) || '' === $city_name ) {
			return $faq_blocks;
		}
		
		$city_faq_index = null;
		
		// If localized FAQ template will be inserted, ALL backend FAQs should be generic
		// If NO localized FAQ template, we must convert ONE backend FAQ to city-specific
		if ( ! $has_localized_faq ) {
			// Select one FAQ to become city-specific
			$city_faq_index = self::select_faq_for_localization( $faq_blocks, $service_slug, $city_slug, $intent_group );
		}
		
		// Process all FAQ items
		$normalized = array();
		foreach ( $faq_blocks as $index => $block ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			$answer = isset( $block['answer'] ) ? $block['answer'] : '';
			
			if ( $index === $city_faq_index ) {
				// This is THE city-specific FAQ (fallback when no localized template)
				// Ensure question has city mention
				if ( ! self::contains_city( $question, $city_name ) ) {
					$question = self::add_city_to_question( $question, $city_name );
				}
				// Strip city from answer to keep it clean
				$answer = self::strip_city_from_text( $answer, $city_name );
			} else {
				// This is a generic FAQ - strip ALL city mentions
				$question = self::strip_city_from_text( $question, $city_name );
				$answer = self::strip_city_from_text( $answer, $city_name );
			}
			
			$normalized[] = array(
				'question' => $question,
				'answer' => $answer,
			);
		}
		
		return $normalized;
	}
	
	/**
	 * Check if text contains city name
	 */
	private static function contains_city( $text, $city_name ) {
		return stripos( $text, $city_name ) !== false;
	}
	
	/**
	 * Select which FAQ should become the city-specific one
	 */
	private static function select_faq_for_localization( $faq_blocks, $service_slug, $city_slug, $intent_group ) {
		if ( empty( $faq_blocks ) ) {
			return 0;
		}
		
		// Deterministic selection using hash
		$hash_input = $service_slug . '|' . $city_slug . '|' . $intent_group . '|faq_localization|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		$selected_index = abs( $hash ) % count( $faq_blocks );
		
		return $selected_index;
	}
	
	/**
	 * Add city mention to question
	 */
	private static function add_city_to_question( $question, $city_name ) {
		// Remove trailing question mark if present
		$question = rtrim( $question, '?' );
		$question = rtrim( $question );
		
		// Add "in {City}?" at the end
		return $question . ' in ' . $city_name . '?';
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
		
		// Determine if localized FAQ template will be inserted
		// It requires: page_mode=service_city AND intent_group AND service_slug AND city_name
		$has_localized_faq = ( '' !== $intent_group && '' !== $service_slug && '' !== $city_name );
		
		// Normalize FAQ blocks
		// If localized FAQ will be inserted: strip city from ALL backend FAQs
		// If NO localized FAQ: convert ONE backend FAQ to city-specific
		$normalized_faqs = self::normalize_service_city_faqs( $faq_blocks, $city_name, $has_localized_faq, $service_slug, $city_slug, $intent_group );
		
		// Replace FAQ blocks in original array
		foreach ( $faq_indices as $i => $original_index ) {
			$blocks[ $original_index ]['question'] = $normalized_faqs[ $i ]['question'];
			$blocks[ $original_index ]['answer'] = $normalized_faqs[ $i ]['answer'];
		}
		
		return $blocks;
	}
}
