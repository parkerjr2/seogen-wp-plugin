<?php
/**
 * Content validation for doorway-page risk mitigation
 * 
 * Validates that service+city pages have required intent-based content
 * Sets noindex for pages that fail validation (soft fail)
 * Blocks publish for pages with unsafe truth violations (hard fail)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Content_Validator {
	
	/**
	 * Validate service+city page content
	 * 
	 * @param string $content Gutenberg markup
	 * @param string $page_mode Page type (service_city, service_hub, city_hub)
	 * @param array $metadata Page metadata (intent_group, service_slug, etc.)
	 * @return array Validation result with status and issues
	 */
	public static function validate_content( $content, $page_mode, $metadata = array() ) {
		$issues = array();
		$warnings = array();
		
		// Only validate service_city pages
		if ( 'service_city' !== $page_mode ) {
			return array(
				'valid' => true,
				'issues' => array(),
				'warnings' => array(),
				'should_noindex' => false,
				'should_block' => false,
			);
		}
		
		// Check for "Why This Page Exists" block
		$has_why_block = self::has_why_block( $content );
		if ( ! $has_why_block ) {
			$issues[] = 'Missing "Why This Page Exists" content block';
		}
		
		// Check for intent-specific CTA text
		$has_intent_cta = self::has_intent_specific_cta( $content, $metadata );
		if ( ! $has_intent_cta ) {
			$warnings[] = 'CTA text may not be intent-specific';
		}
		
		// Check for unsafe truth violations (hard fail)
		$unsafe_violations = self::check_unsafe_truth( $content );
		if ( ! empty( $unsafe_violations ) ) {
			$issues = array_merge( $issues, $unsafe_violations );
		}
		
		// Determine validation status
		$has_unsafe_violations = ! empty( $unsafe_violations );
		$has_critical_issues = ! $has_why_block;
		
		return array(
			'valid' => empty( $issues ),
			'issues' => $issues,
			'warnings' => $warnings,
			'should_noindex' => $has_critical_issues && ! $has_unsafe_violations,
			'should_block' => $has_unsafe_violations,
		);
	}
	
	/**
	 * Check if content has "Why This Page Exists" block
	 * 
	 * @param string $content Gutenberg markup
	 * @return bool
	 */
	private static function has_why_block( $content ) {
		// Check for seogen-why-exists class
		if ( strpos( $content, 'seogen-why-exists' ) !== false ) {
			return true;
		}
		
		// Check for debug comment
		if ( strpos( $content, 'seogen_debug: inserted why_this_page_exists block' ) !== false ) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Check if CTA text appears to be intent-specific
	 * 
	 * @param string $content Gutenberg markup
	 * @param array $metadata Page metadata
	 * @return bool
	 */
	private static function has_intent_specific_cta( $content, $metadata ) {
		// If no intent_group, can't validate
		if ( ! isset( $metadata['intent_group'] ) || '' === $metadata['intent_group'] ) {
			return true; // Pass validation if no intent data
		}
		
		$intent_group = $metadata['intent_group'];
		
		// Check for generic CTA text that should be replaced
		$generic_ctas = array(
			'Call Now for Free Quote',
			'Contact Us',
			'Get a Quote',
			'Request Service',
		);
		
		// Check for intent-specific CTA keywords
		$intent_keywords = array(
			'emergency_response' => array( 'emergency', 'help now', 'immediate' ),
			'repair_service' => array( 'repair', 'fix' ),
			'inspection_diagnostic' => array( 'inspection', 'diagnostic', 'inspect' ),
			'replacement_installation' => array( 'estimate', 'replacement', 'install' ),
			'preventative_maintenance' => array( 'maintenance', 'tune-up', 'schedule maintenance' ),
			'compliance_safety' => array( 'compliance', 'safety', 'safety inspection' ),
		);
		
		// If content has generic CTA and no intent-specific keywords, flag it
		$has_generic = false;
		foreach ( $generic_ctas as $generic_cta ) {
			if ( stripos( $content, $generic_cta ) !== false ) {
				$has_generic = true;
				break;
			}
		}
		
		if ( $has_generic ) {
			// Check if it also has intent-specific keywords
			$keywords = isset( $intent_keywords[ $intent_group ] ) ? $intent_keywords[ $intent_group ] : array();
			foreach ( $keywords as $keyword ) {
				if ( stripos( $content, $keyword ) !== false ) {
					return true; // Has both generic and specific, probably okay
				}
			}
			return false; // Only has generic CTA
		}
		
		return true; // No generic CTA found, assume it's intent-specific
	}
	
	/**
	 * Check for unsafe truth violations
	 * 
	 * @param string $content Gutenberg markup
	 * @return array List of violations
	 */
	private static function check_unsafe_truth( $content ) {
		$violations = array();
		
		// Check for fake testimonials (quotes with names)
		if ( preg_match( '/"[^"]{20,}"\s*-\s*[A-Z][a-z]+\s+[A-Z]\.?/i', $content ) ) {
			$violations[] = 'Possible fake testimonial detected';
		}
		
		// Check for fake awards/certifications
		$fake_award_patterns = array(
			'/Best\s+\w+\s+Award/i',
			'/\d{4}\s+Award\s+Winner/i',
			'/#1\s+Rated/i',
			'/Top\s+\d+\s+\w+/i',
		);
		foreach ( $fake_award_patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$violations[] = 'Possible fake award/certification detected';
				break;
			}
		}
		
		// Check for fake "years in business" claims
		if ( preg_match( '/\d{2,}\+?\s+years?\s+(of\s+)?experience/i', $content ) ) {
			$violations[] = 'Unverifiable "years in business" claim detected';
		}
		
		// Check for fake reviews/ratings
		if ( preg_match( '/\d+\+?\s+(5-star\s+)?reviews?/i', $content ) ) {
			$violations[] = 'Unverifiable review count detected';
		}
		
		return $violations;
	}
	
	/**
	 * Apply validation result to post
	 * 
	 * @param int $post_id Post ID
	 * @param array $validation_result Result from validate_content()
	 * @return void
	 */
	public static function apply_validation_result( $post_id, $validation_result ) {
		// Store validation status
		update_post_meta( $post_id, '_seogen_validation_status', $validation_result['valid'] ? 'valid' : 'invalid' );
		update_post_meta( $post_id, '_seogen_validation_issues', wp_json_encode( $validation_result['issues'] ) );
		update_post_meta( $post_id, '_seogen_validation_warnings', wp_json_encode( $validation_result['warnings'] ) );
		update_post_meta( $post_id, '_seogen_validation_date', current_time( 'mysql' ) );
		
		// Apply noindex if needed (soft fail)
		if ( $validation_result['should_noindex'] ) {
			self::set_noindex( $post_id, true );
			update_post_meta( $post_id, '_seogen_noindex_reason', 'validation_failed' );
		} else {
			// Remove noindex if validation passes
			self::set_noindex( $post_id, false );
			delete_post_meta( $post_id, '_seogen_noindex_reason' );
		}
	}
	
	/**
	 * Set noindex meta for post
	 * 
	 * @param int $post_id Post ID
	 * @param bool $noindex Whether to noindex
	 * @return void
	 */
	private static function set_noindex( $post_id, $noindex ) {
		// Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', $noindex ? '1' : '0' );
		}
		
		// Rank Math
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			update_post_meta( $post_id, 'rank_math_robots', $noindex ? array( 'noindex' ) : array() );
		}
		
		// All in One SEO
		if ( defined( 'AIOSEO_VERSION' ) ) {
			update_post_meta( $post_id, '_aioseo_noindex', $noindex ? '1' : '0' );
		}
		
		// Generic fallback
		update_post_meta( $post_id, '_seogen_noindex', $noindex ? '1' : '0' );
	}
	
	/**
	 * Get validation status for post
	 * 
	 * @param int $post_id Post ID
	 * @return array|null Validation data or null if not validated
	 */
	public static function get_validation_status( $post_id ) {
		$status = get_post_meta( $post_id, '_seogen_validation_status', true );
		if ( empty( $status ) ) {
			return null;
		}
		
		return array(
			'status' => $status,
			'issues' => json_decode( get_post_meta( $post_id, '_seogen_validation_issues', true ), true ),
			'warnings' => json_decode( get_post_meta( $post_id, '_seogen_validation_warnings', true ), true ),
			'date' => get_post_meta( $post_id, '_seogen_validation_date', true ),
			'noindex_reason' => get_post_meta( $post_id, '_seogen_noindex_reason', true ),
		);
	}
}
