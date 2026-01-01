<?php
/**
 * City Hub Link Shortcode
 * 
 * Renders a contextual backlink from service+city pages to their parent city hub page.
 * Uses dynamic query at render time so it works even if city hubs are generated later.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_City_Hub_Link {
	
	/**
	 * Render city hub link shortcode
	 * 
	 * Displays a contextual sentence with link to parent city hub page.
	 * Only renders on service_city pages. Returns empty string if:
	 * - Not a service_city page
	 * - Missing required meta (hub_key or city_slug)
	 * - No matching city hub page found
	 * 
	 * @return string HTML output or empty string
	 */
	public static function render() {
		$post_id = get_the_ID();
		$debug_info = array();
		
		if ( ! $post_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: no post_id -->';
			}
			return '';
		}

		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_info[] = "post_id={$post_id}";
			$debug_info[] = "page_mode={$page_mode}";
			$debug_info[] = "hub_key={$hub_key}";
			$debug_info[] = "city_slug={$city_slug}";
		}
		
		if ( 'service_city' !== $page_mode ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: ' . esc_html( implode( ' | ', $debug_info ) ) . ' | ERROR: expected page_mode=service_city -->';
			}
			return '';
		}

		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: ' . esc_html( implode( ' | ', $debug_info ) ) . ' | ERROR: hub_key or city_slug empty -->';
			}
			return '';
		}

		$city_hub_id = self::find_city_hub_page( $hub_key, $city_slug );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_info[] = $city_hub_id ? "found city_hub_id={$city_hub_id}" : 'no city hub found';
		}
		
		if ( ! $city_hub_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: ' . esc_html( implode( ' | ', $debug_info ) ) . ' -->';
			}
			return '';
		}

		$city_hub_url = get_permalink( $city_hub_id );
		
		// Extract city name from slug (e.g., "tulsa-ok" → "Tulsa")
		$city_name = self::extract_city_from_slug( $city_slug );
		
		// Get readable hub label (e.g., "residential" → "residential electrical")
		$hub_label = self::get_hub_label( $hub_key );
		
		// Select random anchor text template
		$anchor_text = self::get_random_anchor_text( $hub_label, $city_name, $post_id );
		
		// Select random sentence template and build final output
		$sentence = self::get_random_sentence_template( $anchor_text, $city_name, $city_hub_url, $post_id );
		
		$output = '';
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_info[] = "rendering link: '{$anchor_text}'";
			$output .= '<!-- seogen_city_hub_link: ' . esc_html( implode( ' | ', $debug_info ) ) . ' -->' . "\n";
		}
		
		// Admin-only debug info
		if ( current_user_can( 'manage_options' ) ) {
			$output .= '<!-- Admin Debug: hub_key=' . esc_html( $hub_key ) . ', city_slug=' . esc_html( $city_slug ) . ', city_hub_id=' . esc_html( $city_hub_id ) . ' -->' . "\n";
		}
		
		$output .= '<p class="seogen-city-hub-link">';
		$output .= $sentence;
		$output .= '</p>';
		
		return $output;
	}
	
	/**
	 * Find city hub page matching hub_key and city_slug
	 * 
	 * Uses transient caching to avoid repeated queries.
	 * Cache key: seogen_city_hub_{hub_key}_{city_slug}
	 * Cache duration: 
	 *   - 12 hours for positive matches (city hub found)
	 *   - 5 minutes for negative matches (no city hub found)
	 * 
	 * This ensures newly created city hubs appear on service+city pages
	 * within minutes, while still caching successful lookups long-term.
	 * 
	 * @param string $hub_key Hub key (e.g., 'residential')
	 * @param string $city_slug City slug (e.g., 'tulsa-ok')
	 * @return int|false Post ID if found, false otherwise
	 */
	private static function find_city_hub_page( $hub_key, $city_slug ) {
		$cache_key = 'seogen_city_hub_' . $hub_key . '_' . $city_slug;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}
		
		$query = new WP_Query( array(
			'post_type' => 'service_page',
			'post_status' => 'publish',
			'posts_per_page' => 1,
			'orderby' => 'title',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'city_hub',
					'compare' => '=',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
					'compare' => '=',
				),
				array(
					'key' => '_seogen_city_slug',
					'value' => $city_slug,
					'compare' => '=',
				),
			),
		) );
		
		$result = false;
		if ( $query->have_posts() ) {
			$result = $query->posts[0]->ID;
		}
		
		wp_reset_postdata();
		
		// Cache positive matches for 12 hours, negative matches for only 5 minutes
		if ( $result ) {
			set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
		} else {
			set_transient( $cache_key, false, 5 * MINUTE_IN_SECONDS );
		}
		
		return $result;
	}
	
	/**
	 * Clean city hub title for display
	 * 
	 * Removes trailing location info and "services" suffix.
	 * Example: "Residential Electrical in Tulsa, OK" → "Residential Electrical"
	 * 
	 * @param string $title Original title
	 * @return string Cleaned title
	 */
	private static function clean_city_hub_title( $title ) {
		$clean = preg_replace( '/\s+(in|near|around|for)\s+[A-Z][^,]+,?\s*[A-Z]{2}$/i', '', $title );
		$clean = preg_replace( '/\s+services$/i', '', $clean );
		return trim( $clean );
	}
	
	/**
	 * Extract city name from city slug
	 * 
	 * Converts slug format to proper city name.
	 * Example: "tulsa-ok" → "Tulsa"
	 * Example: "broken-arrow-ok" → "Broken Arrow"
	 * 
	 * @param string $city_slug City slug (e.g., "tulsa-ok")
	 * @return string City name (e.g., "Tulsa")
	 */
	private static function extract_city_from_slug( $city_slug ) {
		// Remove state suffix (last hyphen + 2 characters)
		$city_part = preg_replace( '/-[a-z]{2}$/i', '', $city_slug );
		
		// Replace hyphens with spaces and title case
		$city_name = str_replace( '-', ' ', $city_part );
		$city_name = ucwords( $city_name );
		
		return $city_name;
	}
	
	/**
	 * Get readable hub label from hub key
	 * 
	 * @param string $hub_key Hub key (e.g., "residential")
	 * @return string Readable label (e.g., "residential roofing" or "residential electrical")
	 */
	private static function get_hub_label( $hub_key ) {
		// Get business config to determine vertical
		$config = get_option( 'hyper_local_business_config', array() );
		$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
		
		// Map vertical to trade name
		$vertical_map = array(
			'electrician' => 'electrical',
			'plumber'     => 'plumbing',
			'hvac'        => 'HVAC',
			'roofer'      => 'roofing',
			'painter'     => 'painting',
			'flooring'    => 'flooring',
			'lighting'    => 'lighting',
			'contractor'  => 'contractor',
		);
		
		$trade_name = isset( $vertical_map[ $vertical ] ) ? $vertical_map[ $vertical ] : '';
		
		// Build label based on hub key and vertical
		if ( ! empty( $trade_name ) ) {
			// For property type hubs (residential, commercial, etc.), add trade name
			if ( in_array( $hub_key, array( 'residential', 'commercial', 'industrial', 'emergency' ), true ) ) {
				return $hub_key . ' ' . $trade_name;
			}
		}
		
		// Fallback to hub key as-is for specialty hubs
		return $hub_key;
	}
	
	/**
	 * Get random anchor text template
	 * 
	 * Uses deterministic randomization based on post_id for consistency.
	 * 
	 * Template pool (expand as needed):
	 * - "{hub_label} overview in {city}"
	 * - "{city} {hub_label} services"
	 * - "all {hub_label} services in {city}"
	 * - "{hub_label} options in {city}"
	 * - "other {hub_label} work in {city}"
	 * 
	 * @param string $hub_label Readable hub label
	 * @param string $city_name City name
	 * @param int $post_id Current post ID (for deterministic selection)
	 * @return string Anchor text
	 */
	private static function get_random_anchor_text( $hub_label, $city_name, $post_id ) {
		$templates = array(
			'{hub_label} overview in {city}',
			'{city} {hub_label} services',
			'all {hub_label} services in {city}',
			'{hub_label} options in {city}',
			'other {hub_label} work in {city}',
			'{hub_label} services in {city}',
		);
		
		// Deterministic selection based on post_id
		$index = abs( crc32( 'anchor_' . $post_id ) ) % count( $templates );
		$template = $templates[ $index ];
		
		// Replace placeholders
		$anchor_text = str_replace( '{hub_label}', $hub_label, $template );
		$anchor_text = str_replace( '{city}', $city_name, $anchor_text );
		
		return $anchor_text;
	}
	
	/**
	 * Get random sentence template
	 * 
	 * Returns varied sentence structures to sound more human-written.
	 * Uses deterministic randomization based on post_id for consistency.
	 * 
	 * Template pool (expand as needed):
	 * - "Need the bigger picture in {city}? Start with our {anchor}."
	 * - "Not sure where to begin? See our {anchor}."
	 * - "Want a broader view for {city}? Visit {anchor}."
	 * - "Looking for more options? Explore {anchor}."
	 * - "Considering other services? Check out {anchor}."
	 * 
	 * To expand: Add new sentence patterns to the $templates array below.
	 * Keep {anchor} placeholder for link insertion.
	 * Avoid brand names and overly promotional language.
	 * 
	 * @param string $anchor_text Pre-built anchor text
	 * @param string $city_name City name
	 * @param string $url City hub URL
	 * @param int $post_id Current post ID (for deterministic selection)
	 * @return string Complete sentence with link
	 */
	private static function get_random_sentence_template( $anchor_text, $city_name, $url, $post_id ) {
		$templates = array(
			'Need the bigger picture in {city}? Start with our {anchor}.',
			'Not sure where to begin? See our {anchor}.',
			'Want a broader view for {city}? Visit our {anchor}.',
			'Looking for more options? Explore our {anchor}.',
			'Considering other services? Check out our {anchor}.',
			'Want to see what else we offer in {city}? Browse our {anchor}.',
		);
		
		// Deterministic selection based on post_id (different seed than anchor)
		$index = abs( crc32( 'sentence_' . $post_id ) ) % count( $templates );
		$template = $templates[ $index ];
		
		// Build the link
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor_text ) . '</a>';
		
		// Replace placeholders
		$sentence = str_replace( '{anchor}', $link, $template );
		$sentence = str_replace( '{city}', esc_html( $city_name ), $sentence );
		
		return $sentence;
	}
	
	/**
	 * Get sentence template with deterministic variation
	 * 
	 * Uses hash of (hub_key + city_slug + post_id) to select from 10 templates.
	 * All templates are trade-neutral and avoid banned phrases.
	 * 
	 * @param string $hub_key Hub key
	 * @param string $city_slug City slug
	 * @param int $post_id Current post ID
	 * @return string Sentence template with {title} placeholder
	 */
	private static function get_sentence_template( $hub_key, $city_slug, $post_id ) {
		$templates = array(
			'Need a broader view? Check out our <a href="{url}">{title}</a> page.',
			'Want to see more options? Visit our <a href="{url}">{title}</a> page.',
			'Exploring other solutions? See our <a href="{url}">{title}</a> page.',
			'Looking for related work? Browse our <a href="{url}">{title}</a> page.',
			'Considering different approaches? Review our <a href="{url}">{title}</a> page.',
			'Need more context? View our <a href="{url}">{title}</a> page.',
			'Want the full picture? See our <a href="{url}">{title}</a> page.',
			'Comparing your options? Check our <a href="{url}">{title}</a> page.',
			'Weighing different methods? Visit our <a href="{url}">{title}</a> page.',
			'Thinking through your choices? Browse our <a href="{url}">{title}</a> page.',
		);
		
		$hash = crc32( $hub_key . $city_slug . $post_id );
		$index = abs( $hash ) % count( $templates );
		
		return $templates[ $index ];
	}
	
	/**
	 * Purge transient for a specific post
	 * 
	 * Called when a city hub or service+city page is saved/published.
	 * Clears the specific transient for that hub_key + city_slug combination.
	 * 
	 * @param int $post_id Post ID
	 */
	public static function purge_city_hub_transient_for_post( $post_id ) {
		// Ignore autosaves and revisions
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		
		// Only process service_page post type
		if ( 'service_page' !== get_post_type( $post_id ) ) {
			return;
		}
		
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		
		// Only purge for city_hub and service_city pages
		if ( ! in_array( $page_mode, array( 'city_hub', 'service_city' ), true ) ) {
			return;
		}
		
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
		
		if ( $hub_key && $city_slug ) {
			$cache_key = 'seogen_city_hub_' . $hub_key . '_' . $city_slug;
			delete_transient( $cache_key );
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "SEOgen: Purged transient {$cache_key} for post {$post_id} (page_mode={$page_mode})" );
			}
		}
	}
	
	/**
	 * Clear city hub link cache
	 * 
	 * Called when city hub pages are created/updated/deleted.
	 * Clears all transients matching pattern: seogen_city_hub_*
	 */
	public static function clear_cache() {
		global $wpdb;
		
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_seogen_city_hub_%'
			)
		);
		
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_seogen_city_hub_%'
			)
		);
	}
}
