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
	 * Strip city token from text - AGGRESSIVE removal
	 * Removes city name wherever it appears using word boundaries
	 * 
	 * @param string $text Text to clean
	 * @param string $city_name City name to remove
	 * @return string Cleaned text with zero city mentions
	 */
	private static function strip_city_from_text( $text, $city_name ) {
		if ( '' === $city_name || '' === $text ) {
			return $text;
		}
		
		$city_escaped = preg_quote( $city_name, '/' );
		
		// Pattern 1: "{City}'s" possessive - remove entire token
		$text = preg_replace( '/\b' . $city_escaped . '\'s\b/i', '', $text );
		
		// Pattern 2: "{City}-based" or "{City}-area" - remove city part, keep suffix
		$text = preg_replace( '/\b' . $city_escaped . '[\-–—]/i', '', $text );
		
		// Pattern 3: "in {City}" "around {City}" "near {City}" - remove preposition + city
		$text = preg_replace( '/\s+(in|around|near|throughout|across)\s+' . $city_escaped . '\b/i', '', $text );
		
		// Pattern 4: "{City} [noun]" - remove city, keep noun
		$text = preg_replace( '/\b' . $city_escaped . '\s+/i', '', $text );
		
		// Pattern 5: ", {City}" or "({City})" - punctuation-wrapped
		$text = preg_replace( '/[,\(]\s*' . $city_escaped . '\s*[\),]/i', '', $text );
		
		// Pattern 6: Standalone city token (word boundary on both sides)
		$text = preg_replace( '/\b' . $city_escaped . '\b/i', '', $text );
		
		// CLEANUP PHASE - fix artifacts from removal
		
		// Fix orphaned prepositions at sentence start: "In ," -> ""
		$text = preg_replace( '/^(In|Around|Near|Throughout|Across)\s*[,\.]/i', '', $text );
		
		// Fix orphaned prepositions mid-sentence: "word. In ," -> "word."
		$text = preg_replace( '/\.\s+(In|Around|Near|Throughout|Across)\s*[,\.]/i', '.', $text );
		
		// Fix double punctuation: ", ," or ". ." or ", ."
		$text = preg_replace( '/([.,;:?!])\s*[,\.]+/', '$1', $text );
		
		// Fix orphaned commas: "word , word" -> "word, word"
		$text = preg_replace( '/\s+,/', ',', $text );
		
		// Fix space before punctuation: "word ." -> "word."
		$text = preg_replace( '/\s+([.,;:?!])/', '$1', $text );
		
		// Collapse multiple spaces
		$text = preg_replace( '/\s{2,}/', ' ', $text );
		
		// Fix sentence starts after cleanup: ". word" -> ". Word"
		$text = preg_replace_callback( '/\.\s+([a-z])/', function( $matches ) {
			return '. ' . strtoupper( $matches[1] );
		}, $text );
		
		// Fix start of text: "word" -> "Word"
		if ( strlen( $text ) > 0 ) {
			$text = strtoupper( substr( $text, 0, 1 ) ) . substr( $text, 1 );
		}
		
		return trim( $text );
	}
	
	/**
	 * Test city stripping with known failing examples
	 * For debugging - can be called to verify stripping works
	 */
	public static function test_city_stripping() {
		$test_cases = array(
			'This can happen in older Tulsa commercial properties where wiring is outdated.',
			'In Tulsa, many commercial buildings built before 1979 have outdated wiring.',
			'Tulsa-based businesses often face these issues.',
			'Properties around Tulsa may require upgrades.',
			'Tulsa\'s commercial properties often need inspection.',
			'older Tulsa commercial properties',
		);
		
		$results = array();
		foreach ( $test_cases as $input ) {
			$output = self::strip_city_from_text( $input, 'Tulsa' );
			$has_tulsa = stripos( $output, 'Tulsa' ) !== false;
			$results[] = array(
				'input' => $input,
				'output' => $output,
				'pass' => ! $has_tulsa,
			);
		}
		
		return $results;
	}
	
	/**
	 * Validate FAQ compliance - CRITICAL ASSERTION
	 * Ensures exactly 1 city-specific question, zero city in other answers
	 * 
	 * @param array $faq_blocks FAQ blocks to validate
	 * @param string $city_name City name to check for
	 * @param int $post_id Post ID for logging
	 * @return array Validation result with 'pass' boolean and 'violations' array
	 */
	public static function validate_faq_compliance( $faq_blocks, $city_name, $post_id = 0 ) {
		$violations = array();
		$city_in_questions = 0;
		$city_in_answers = 0;
		
		foreach ( $faq_blocks as $index => $block ) {
			$question = isset( $block['question'] ) ? $block['question'] : '';
			$answer = isset( $block['answer'] ) ? $block['answer'] : '';
			
			// Check question for city
			if ( stripos( $question, $city_name ) !== false ) {
				$city_in_questions++;
			}
			
			// Check answer for city (should be ZERO for all non-local FAQs)
			if ( stripos( $answer, $city_name ) !== false ) {
				$city_in_answers++;
				$violations[] = array(
					'type' => 'city_in_answer',
					'index' => $index,
					'question' => $question,
					'answer' => substr( $answer, 0, 100 ) . '...',
				);
			}
		}
		
		// Check question count
		if ( $city_in_questions !== 1 ) {
			$violations[] = array(
				'type' => 'wrong_question_count',
				'expected' => 1,
				'actual' => $city_in_questions,
			);
		}
		
		$pass = ( $city_in_questions === 1 && $city_in_answers === 0 );
		
		// Log violations
		if ( ! $pass && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'SEOgen FAQ Compliance VIOLATION - Post %d, City: %s, Questions with city: %d (expected 1), Answers with city: %d (expected 0)',
				$post_id,
				$city_name,
				$city_in_questions,
				$city_in_answers
			) );
			foreach ( $violations as $v ) {
				error_log( 'SEOgen FAQ Violation: ' . print_r( $v, true ) );
			}
		}
		
		return array(
			'pass' => $pass,
			'violations' => $violations,
			'city_in_questions' => $city_in_questions,
			'city_in_answers' => $city_in_answers,
		);
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
		
		// CRITICAL: Validate compliance BEFORE returning
		// Backend FAQs should have zero city mentions in answers (always)
		// Backend FAQs should have 1 city mention in questions (only if no localized template)
		$expected_city_questions = $has_localized_faq ? 0 : 1;
		$city_in_questions = 0;
		$city_in_answers = 0;
		
		foreach ( $normalized_faqs as $faq ) {
			if ( stripos( $faq['question'], $city_name ) !== false ) {
				$city_in_questions++;
			}
			if ( stripos( $faq['answer'], $city_name ) !== false ) {
				$city_in_answers++;
			}
		}
		
		// Log if validation fails
		if ( $city_in_questions !== $expected_city_questions || $city_in_answers !== 0 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'SEOgen FAQ Backend Validation: City=%s, Has_Localized=%s, Questions_with_city=%d (expected %d), Answers_with_city=%d (expected 0)',
					$city_name,
					$has_localized_faq ? 'yes' : 'no',
					$city_in_questions,
					$expected_city_questions,
					$city_in_answers
				) );
			}
		}
		
		// Replace FAQ blocks in original array
		foreach ( $faq_indices as $i => $original_index ) {
			$blocks[ $original_index ]['question'] = $normalized_faqs[ $i ]['question'];
			$blocks[ $original_index ]['answer'] = $normalized_faqs[ $i ]['answer'];
		}
		
		return $blocks;
	}
}
