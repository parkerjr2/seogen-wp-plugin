<?php
/**
 * FAQ Normalization Helper
 * 
 * Enforces Google Helpful Content and doorway safety rules for FAQ sections:
 * - service_city: Exactly ONE city-specific FAQ (question mentions city)
 * - city_hub: ZERO FAQs (remove all)
 * - service_hub: ZERO city-specific FAQs (generic FAQs allowed)
 * 
 * City-specific = city name appears in the QUESTION (not just answer)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_FAQ_Normalizer {
	
	/**
	 * Normalize FAQ content based on page_mode
	 * 
	 * @param string $content Gutenberg content
	 * @param string $page_mode Page mode (service_city, city_hub, service_hub)
	 * @param string $city_name City name for detection
	 * @return string Normalized content
	 */
	public static function normalize_faq_content( $content, $page_mode, $city_name = '' ) {
		if ( 'city_hub' === $page_mode ) {
			return self::remove_all_faqs( $content );
		}
		
		if ( 'service_hub' === $page_mode ) {
			return self::remove_city_specific_faqs( $content, $city_name );
		}
		
		if ( 'service_city' === $page_mode && '' !== $city_name ) {
			return self::enforce_single_city_faq( $content, $city_name );
		}
		
		return $content;
	}
	
	/**
	 * Remove entire FAQ section (for city_hub pages)
	 * 
	 * @param string $content Gutenberg content
	 * @return string Content with FAQ section removed
	 */
	private static function remove_all_faqs( $content ) {
		// Remove FAQ heading and all FAQ items
		$lines = explode( "\n", $content );
		$output = array();
		$in_faq_section = false;
		$skip_until_next_section = false;
		
		foreach ( $lines as $line ) {
			// Detect FAQ heading
			if ( preg_match( '/<h2[^>]*>FAQ<\/h2>/i', $line ) ) {
				$in_faq_section = true;
				$skip_until_next_section = true;
				continue;
			}
			
			// Detect next section (any h2 that's not FAQ)
			if ( $skip_until_next_section && preg_match( '/<h2[^>]*>(?!FAQ)/i', $line ) ) {
				$skip_until_next_section = false;
				$in_faq_section = false;
			}
			
			// Skip FAQ-related blocks
			if ( $skip_until_next_section ) {
				// Skip details blocks, FAQ headings, and FAQ comments
				if ( strpos( $line, 'wp:details' ) !== false ||
				     strpos( $line, '<details' ) !== false ||
				     strpos( $line, '</details>' ) !== false ||
				     strpos( $line, 'wp:heading' ) !== false && $in_faq_section ||
				     strpos( $line, '<h3' ) !== false && $in_faq_section ||
				     strpos( $line, 'seogen-localized-faq' ) !== false ||
				     strpos( $line, 'seogen_debug: inserted localized FAQ' ) !== false ) {
					continue;
				}
			}
			
			$output[] = $line;
		}
		
		return implode( "\n", $output );
	}
	
	/**
	 * Remove city-specific FAQs (for service_hub pages)
	 * 
	 * @param string $content Gutenberg content
	 * @param string $city_name City name to detect
	 * @return string Content with city-specific FAQs removed
	 */
	private static function remove_city_specific_faqs( $content, $city_name ) {
		if ( '' === $city_name ) {
			return $content;
		}
		
		$lines = explode( "\n", $content );
		$output = array();
		$skip_current_faq = false;
		$in_details_block = false;
		
		foreach ( $lines as $line ) {
			// Detect start of details block with city name in summary
			if ( preg_match( '/<details[^>]*><summary>(.*?)<\/summary>/i', $line, $matches ) ) {
				$summary = $matches[1];
				if ( stripos( $summary, $city_name ) !== false ) {
					$skip_current_faq = true;
					$in_details_block = true;
					continue;
				}
			}
			
			// Detect end of details block
			if ( $in_details_block && strpos( $line, '</details>' ) !== false ) {
				if ( $skip_current_faq ) {
					$skip_current_faq = false;
					$in_details_block = false;
					continue;
				}
				$in_details_block = false;
			}
			
			// Skip lines within a city-specific FAQ
			if ( $skip_current_faq ) {
				continue;
			}
			
			// Also check h3 headings (fallback for non-details format)
			if ( preg_match( '/<h3[^>]*>(.*?)<\/h3>/i', $line, $matches ) ) {
				$heading = $matches[1];
				if ( stripos( $heading, $city_name ) !== false ) {
					$skip_current_faq = true;
					continue;
				}
			}
			
			// Reset skip flag at next FAQ or section
			if ( $skip_current_faq && ( strpos( $line, 'wp:details' ) !== false || strpos( $line, 'wp:heading' ) !== false ) ) {
				$skip_current_faq = false;
			}
			
			$output[] = $line;
		}
		
		return implode( "\n", $output );
	}
	
	/**
	 * Enforce exactly one city-specific FAQ (for service_city pages)
	 * 
	 * @param string $content Gutenberg content
	 * @param string $city_name City name to detect
	 * @return string Content with exactly one city-specific FAQ
	 */
	private static function enforce_single_city_faq( $content, $city_name ) {
		$lines = explode( "\n", $content );
		$city_faq_count = 0;
		$first_city_faq_found = false;
		$output = array();
		$skip_current_faq = false;
		$in_details_block = false;
		
		// First pass: count city-specific FAQs and mark extras for removal
		foreach ( $lines as $line ) {
			// Detect details block with city name in summary
			if ( preg_match( '/<details[^>]*><summary>(.*?)<\/summary>/i', $line, $matches ) ) {
				$summary = $matches[1];
				if ( stripos( $summary, $city_name ) !== false ) {
					$city_faq_count++;
					if ( $first_city_faq_found ) {
						// This is a duplicate city FAQ - skip it
						$skip_current_faq = true;
						$in_details_block = true;
						continue;
					} else {
						$first_city_faq_found = true;
					}
				}
			}
			
			// Detect end of details block
			if ( $in_details_block && strpos( $line, '</details>' ) !== false ) {
				if ( $skip_current_faq ) {
					$skip_current_faq = false;
					$in_details_block = false;
					continue;
				}
				$in_details_block = false;
			}
			
			// Skip lines within a duplicate city FAQ
			if ( $skip_current_faq ) {
				continue;
			}
			
			// Also check h3 headings (fallback for non-details format)
			if ( preg_match( '/<h3[^>]*>(.*?)<\/h3>/i', $line, $matches ) ) {
				$heading = $matches[1];
				if ( stripos( $heading, $city_name ) !== false ) {
					$city_faq_count++;
					if ( $first_city_faq_found ) {
						$skip_current_faq = true;
						continue;
					} else {
						$first_city_faq_found = true;
					}
				}
			}
			
			$output[] = $line;
		}
		
		return implode( "\n", $output );
	}
	
	/**
	 * Check if content has city-specific FAQ
	 * 
	 * @param string $content Gutenberg content
	 * @param string $city_name City name to detect
	 * @return bool True if city-specific FAQ exists
	 */
	public static function has_city_specific_faq( $content, $city_name ) {
		if ( '' === $city_name ) {
			return false;
		}
		
		// Check details blocks
		if ( preg_match_all( '/<details[^>]*><summary>(.*?)<\/summary>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $summary ) {
				if ( stripos( $summary, $city_name ) !== false ) {
					return true;
				}
			}
		}
		
		// Check h3 headings (fallback)
		if ( preg_match_all( '/<h3[^>]*>(.*?)<\/h3>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $heading ) {
				if ( stripos( $heading, $city_name ) !== false ) {
					return true;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Count city-specific FAQs in content
	 * 
	 * @param string $content Gutenberg content
	 * @param string $city_name City name to detect
	 * @return int Count of city-specific FAQs
	 */
	public static function count_city_specific_faqs( $content, $city_name ) {
		if ( '' === $city_name ) {
			return 0;
		}
		
		$count = 0;
		
		// Check details blocks
		if ( preg_match_all( '/<details[^>]*><summary>(.*?)<\/summary>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $summary ) {
				if ( stripos( $summary, $city_name ) !== false ) {
					$count++;
				}
			}
		}
		
		// Check h3 headings (fallback)
		if ( preg_match_all( '/<h3[^>]*>(.*?)<\/h3>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $heading ) {
				if ( stripos( $heading, $city_name ) !== false ) {
					$count++;
				}
			}
		}
		
		return $count;
	}
}
