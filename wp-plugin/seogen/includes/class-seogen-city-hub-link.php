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
		$city_hub_title = get_the_title( $city_hub_id );
		
		$clean_title = self::clean_city_hub_title( $city_hub_title );
		
		$template = self::get_sentence_template( $hub_key, $city_slug, $post_id );
		$sentence = str_replace( '{url}', esc_url( $city_hub_url ), $template );
		$sentence = str_replace( '{title}', esc_html( $clean_title ), $sentence );
		
		$output = '';
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_info[] = "rendering link to '{$clean_title}'";
			$output .= '<!-- seogen_city_hub_link: ' . esc_html( implode( ' | ', $debug_info ) ) . ' -->' . "\n";
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
