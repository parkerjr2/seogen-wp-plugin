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
		// Always output something when WP_DEBUG is on to confirm shortcode is being called
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'SEOgen: seogen_city_hub_link shortcode called' );
		}
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: no post_id -->';
			}
			return '';
		}

		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'service_city' !== $page_mode ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: page_mode=' . esc_html( $page_mode ) . ', expected service_city -->';
			}
			return '';
		}

		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );

		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: hub_key=' . esc_html( $hub_key ) . ', city_slug=' . esc_html( $city_slug ) . ' (one or both empty) -->';
			}
			return '';
		}

		$city_hub_id = self::find_city_hub_page( $hub_key, $city_slug );
		
		if ( ! $city_hub_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				return '<!-- seogen_city_hub_link: no city hub found for hub_key=' . esc_html( $hub_key ) . ', city_slug=' . esc_html( $city_slug ) . ' -->';
			}
			return '';
		}

		$city_hub_url = get_permalink( $city_hub_id );
		$city_hub_title = get_the_title( $city_hub_id );
		
		$clean_title = self::clean_city_hub_title( $city_hub_title );
		
		$template = self::get_sentence_template( $hub_key, $city_slug, $post_id );
		$sentence = str_replace( '{url}', esc_url( $city_hub_url ), $template );
		$sentence = str_replace( '{title}', esc_html( $clean_title ), $sentence );
		
		$output = '<p class="seogen-city-hub-link">';
		$output .= $sentence;
		$output .= '</p>';
		
		if ( current_user_can( 'manage_options' ) ) {
			$output .= '<!-- DEBUG: hub_key=' . esc_html( $hub_key ) . ', city_slug=' . esc_html( $city_slug ) . ', city_hub_id=' . esc_html( $city_hub_id ) . ' -->';
		}
		
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
