<?php
/**
 * FAQ Final Compliance Enforcer
 * 
 * CRITICAL: This is the FINAL pass that runs AFTER all FAQs are assembled
 * (backend FAQs + localized template) and IMMEDIATELY BEFORE Gutenberg markup.
 * 
 * Enforces strict invariants:
 * 1) Exactly ONE FAQ question contains city name
 * 2) ALL other FAQ questions do NOT contain city
 * 3) ALL other FAQ answers do NOT contain city
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_FAQ_Final_Compliance {
	
	const TEMPLATE_VERSION = '1.0';
	
	/**
	 * Enforce FAQ city compliance - FINAL AUTHORITATIVE PASS
	 * 
	 * @param array $faqs Array of FAQ items with 'question' and 'answer' keys
	 * @param string $city_name City name to enforce
	 * @param string $service_slug Service slug for deterministic selection
	 * @param string $city_slug City slug for deterministic selection
	 * @param string $intent_group Intent group for deterministic selection
	 * @return array Compliant FAQ array
	 */
	public static function enforce_faq_city_compliance( $faqs, $city_name, $service_slug = '', $city_slug = '', $intent_group = '' ) {
		// CRITICAL DEBUG
		$log_file = WP_CONTENT_DIR . '/seogen-debug.log';
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] city_name="' . $city_name . '", faq_count=' . count( $faqs ) . ', service_slug="' . $service_slug . '", city_slug="' . $city_slug . '", intent_group="' . $intent_group . '"' . PHP_EOL, FILE_APPEND );
		
		if ( empty( $faqs ) || '' === $city_name ) {
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] SKIPPED - empty faqs or city_name' . PHP_EOL, FILE_APPEND );
			return $faqs;
		}
		
		// Extract just city name if it contains state (e.g., "Tulsa, OK" -> "Tulsa")
		$city_parts = explode( ',', $city_name );
		$city_name_clean = trim( $city_parts[0] );
		
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Using city_name_clean="' . $city_name_clean . '" (from "' . $city_name . '")' . PHP_EOL, FILE_APPEND );
		
		// Step 1: Identify which FAQs have city in question
		$city_in_question_indices = array();
		foreach ( $faqs as $index => $faq ) {
			$question = isset( $faq['question'] ) ? $faq['question'] : '';
			if ( self::contains_city_token( $question, $city_name_clean ) ) {
				$city_in_question_indices[] = $index;
				file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Found city in question #' . $index . ': "' . substr( $question, 0, 80 ) . '"' . PHP_EOL, FILE_APPEND );
			}
		}
		
		$local_faq_index = null;
		
		// Step 2: Determine THE local FAQ index
		$count = count( $city_in_question_indices );
		
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Found ' . $count . ' questions with city mention' . PHP_EOL, FILE_APPEND );
		
		if ( 1 === $count ) {
			// Perfect - exactly one city-specific question
			$local_faq_index = $city_in_question_indices[0];
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Using existing city question at index ' . $local_faq_index . PHP_EOL, FILE_APPEND );
		} elseif ( 0 === $count ) {
			// No city-specific question - select one deterministically
			$local_faq_index = self::select_local_faq_index( $faqs, $service_slug, $city_slug, $intent_group );
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] No city questions found, selecting index ' . $local_faq_index . ' to add city' . PHP_EOL, FILE_APPEND );
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Original question: "' . $faqs[ $local_faq_index ]['question'] . '"' . PHP_EOL, FILE_APPEND );
			
			// Add city to selected question
			$faqs[ $local_faq_index ]['question'] = self::add_city_to_question( 
				$faqs[ $local_faq_index ]['question'], 
				$city_name_clean 
			);
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Modified question: "' . $faqs[ $local_faq_index ]['question'] . '"' . PHP_EOL, FILE_APPEND );
		} else {
			// Multiple city-specific questions - keep first, strip others
			$local_faq_index = $city_in_question_indices[0];
			file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Multiple city questions, keeping first at index ' . $local_faq_index . ', stripping ' . ($count - 1) . ' others' . PHP_EOL, FILE_APPEND );
			
			// Strip city from other questions that had it
			for ( $i = 1; $i < $count; $i++ ) {
				$idx = $city_in_question_indices[ $i ];
				$faqs[ $idx ]['question'] = self::strip_city_token( $faqs[ $idx ]['question'], $city_name_clean );
			}
		}
		
		// Step 3: Strip city from ALL non-local FAQ questions and answers
		foreach ( $faqs as $index => $faq ) {
			if ( $index === $local_faq_index ) {
				// This is the local FAQ - strip city from answer only (keep question)
				$faqs[ $index ]['answer'] = self::strip_city_token( $faqs[ $index ]['answer'], $city_name_clean );
			} else {
				// Non-local FAQ - strip city from BOTH question and answer
				$faqs[ $index ]['question'] = self::strip_city_token( $faqs[ $index ]['question'], $city_name_clean );
				$faqs[ $index ]['answer'] = self::strip_city_token( $faqs[ $index ]['answer'], $city_name_clean );
			}
		}
		
		file_put_contents( $log_file, '[' . date('Y-m-d H:i:s') . '] [FINAL FAQ] Compliance enforcement complete' . PHP_EOL, FILE_APPEND );
		
		return $faqs;
	}
	
	/**
	 * Check if text contains city token (word boundary)
	 */
	private static function contains_city_token( $text, $city_name ) {
		$city_escaped = preg_quote( $city_name, '/' );
		return preg_match( '/\b' . $city_escaped . '\b/i', $text ) === 1;
	}
	
	/**
	 * Select which FAQ should be the local one (deterministic)
	 */
	private static function select_local_faq_index( $faqs, $service_slug, $city_slug, $intent_group ) {
		if ( empty( $faqs ) ) {
			return 0;
		}
		
		// Prefer last FAQ, or use hash for deterministic selection
		$hash_input = $service_slug . '|' . $city_slug . '|' . $intent_group . '|final_local_faq|' . self::TEMPLATE_VERSION;
		$hash = crc32( $hash_input );
		$selected_index = abs( $hash ) % count( $faqs );
		
		return $selected_index;
	}
	
	/**
	 * Add city to question
	 */
	private static function add_city_to_question( $question, $city_name ) {
		$question = rtrim( $question, ' ?' );
		return $question . ' in ' . $city_name . '?';
	}
	
	/**
	 * Strip city token from text - AGGRESSIVE word-boundary removal
	 */
	private static function strip_city_token( $text, $city_name ) {
		if ( '' === $city_name || '' === $text ) {
			return $text;
		}
		
		$city_escaped = preg_quote( $city_name, '/' );
		
		// Remove all city token patterns
		$text = preg_replace( '/\b' . $city_escaped . '\'s\b/i', '', $text );
		$text = preg_replace( '/\b' . $city_escaped . '\s*[\-–—]\s*/i', '', $text );
		$text = preg_replace( '/\s+(in|around|near|throughout|across)\s+' . $city_escaped . '\b/i', '', $text );
		$text = preg_replace( '/\b' . $city_escaped . '\s+/i', '', $text );
		$text = preg_replace( '/[,\(]\s*' . $city_escaped . '\s*[\),]/i', '', $text );
		$text = preg_replace( '/\b' . $city_escaped . '\b/i', '', $text );
		
		// Cleanup artifacts
		$text = preg_replace( '/^(In|Around|Near|Throughout|Across)\s*[,\.]/i', '', $text );
		$text = preg_replace( '/\.\s+(In|Around|Near|Throughout|Across)\s*[,\.]/i', '.', $text );
		$text = preg_replace( '/([.,;:?!])\s*[,\.]+/', '$1', $text );
		$text = preg_replace( '/\s+,/', ',', $text );
		$text = preg_replace( '/\s+([.,;:?!])/', '$1', $text );
		$text = preg_replace( '/\s{2,}/', ' ', $text );
		
		// Fix capitalization
		$text = preg_replace_callback( '/\.\s+([a-z])/', function( $matches ) {
			return '. ' . strtoupper( $matches[1] );
		}, $text );
		
		if ( strlen( $text ) > 0 ) {
			$text = strtoupper( substr( $text, 0, 1 ) ) . substr( $text, 1 );
		}
		
		return trim( $text );
	}
	
	/**
	 * Assert FAQ compliance - CRITICAL validation
	 * 
	 * @param array $faqs FAQ array to validate
	 * @param string $city_name City name
	 * @param int $post_id Post ID for logging
	 * @return array Validation result with 'pass', 'violations', 'should_noindex'
	 */
	public static function assert_faq_compliance( $faqs, $city_name, $post_id = 0 ) {
		$violations = array();
		$city_in_questions = 0;
		$city_in_answers = 0;
		
		foreach ( $faqs as $index => $faq ) {
			$question = isset( $faq['question'] ) ? $faq['question'] : '';
			$answer = isset( $faq['answer'] ) ? $faq['answer'] : '';
			
			if ( self::contains_city_token( $question, $city_name ) ) {
				$city_in_questions++;
			}
			
			if ( self::contains_city_token( $answer, $city_name ) ) {
				$city_in_answers++;
				$violations[] = array(
					'type' => 'city_in_answer',
					'index' => $index,
					'question' => substr( $question, 0, 80 ),
					'answer_snippet' => substr( $answer, 0, 100 ),
				);
			}
		}
		
		if ( $city_in_questions !== 1 ) {
			$violations[] = array(
				'type' => 'wrong_question_count',
				'expected' => 1,
				'actual' => $city_in_questions,
			);
		}
		
		$pass = ( $city_in_questions === 1 && $city_in_answers === 0 );
		$should_noindex = ! $pass;
		
		// Log violations
		if ( ! $pass && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'SEOgen FINAL FAQ COMPLIANCE FAILURE - Post %d, City: %s, Questions: %d (expected 1), Answers: %d (expected 0)',
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
			'should_noindex' => $should_noindex,
			'city_in_questions' => $city_in_questions,
			'city_in_answers' => $city_in_answers,
		);
	}
}
