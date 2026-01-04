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
	 * Ensures exactly one city-specific question, zero city mentions in other FAQs
	 * 
	 * @param array $faq_blocks Array of FAQ blocks from backend
	 * @param string $city_name City name to detect/strip
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $city_slug City slug for deterministic selection
	 * @param string $intent_group Intent group for deterministic selection
	 * @return array Normalized FAQ blocks
	 */
	public static function normalize_service_city_faqs( $faq_blocks, $city_name, $service_slug = '', $city_slug = '', $intent_group = '' ) {
		if ( empty( $faq_blocks ) || '' === $city_name ) {
			return $faq_blocks;
		}
		
		// Step 1: Identify which FAQs have city in question
		$city_in_question = array();
		foreach ( $faq_blocks as $index => $block ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			if ( self::contains_city( $question, $city_name ) ) {
				$city_in_question[] = $index;
			}
		}
		
		$city_faq_index = null;
		
		// Step 2: Determine which FAQ should be the city-specific one
		if ( count( $city_in_question ) === 1 ) {
			// Perfect - already have exactly one
			$city_faq_index = $city_in_question[0];
		} elseif ( count( $city_in_question ) === 0 ) {
			// Need to convert one FAQ to city-specific
			$city_faq_index = self::select_faq_for_localization( $faq_blocks, $service_slug, $city_slug, $intent_group );
		} else {
			// Multiple city questions - keep the first one
			$city_faq_index = $city_in_question[0];
		}
		
		// Step 3: Normalize all FAQ items
		$normalized = array();
		foreach ( $faq_blocks as $index => $block ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			$answer = isset( $block['answer'] ) ? $block['answer'] : '';
			
			if ( $index === $city_faq_index ) {
				// This is THE city-specific FAQ
				// Ensure question has city mention
				if ( ! self::contains_city( $question, $city_name ) ) {
					$question = self::add_city_to_question( $question, $city_name );
				}
				// Answer can stay as-is (may or may not mention city - that's OK for the one localized FAQ)
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
	 * 
	 * @param string $text Text to check
	 * @param string $city_name City name to look for
	 * @return bool True if city found
	 */
	private static function contains_city( $text, $city_name ) {
		return stripos( $text, $city_name ) !== false;
	}
	
	/**
	 * Select which FAQ should become the city-specific one
	 * Uses deterministic selection
	 * 
	 * @param array $faq_blocks FAQ blocks
	 * @param string $service_slug Service slug
	 * @param string $city_slug City slug
	 * @param string $intent_group Intent group
	 * @return int Index of selected FAQ
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
	 * Appends "in {City}" naturally to the question
	 * 
	 * @param string $question Original question
	 * @param string $city_name City name to add
	 * @return string Question with city mention
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
		// Pattern 1: "in {City}" (most common)
		$text = preg_replace( '/\s+in\s+' . preg_quote( $city_name, '/' ) . '\b/i', '', $text );
		
		// Pattern 2: "{City}" at start of sentence (less common)
		$text = preg_replace( '/\b' . preg_quote( $city_name, '/' ) . '\s+/i', '', $text );
		
		// Pattern 3: ", {City}" or "({City})"
		$text = preg_replace( '/[,\(]\s*' . preg_quote( $city_name, '/' ) . '\s*[\),]/i', '', $text );
		
		// Clean up any double spaces or awkward punctuation
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = preg_replace( '/\s+([.,;:?!])/', '$1', $text );
		$text = preg_replace( '/([.,;:?!])\s*([.,;:?!])/', '$1', $text );
		
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
		
		// Normalize FAQ blocks
		$normalized_faqs = self::normalize_service_city_faqs( $faq_blocks, $city_name, $service_slug, $city_slug, $intent_group );
		
		// Replace FAQ blocks in original array
		foreach ( $faq_indices as $i => $original_index ) {
			$blocks[ $original_index ]['question'] = $normalized_faqs[ $i ]['question'];
			$blocks[ $original_index ]['answer'] = $normalized_faqs[ $i ]['answer'];
		}
		
		return $blocks;
	}
}
