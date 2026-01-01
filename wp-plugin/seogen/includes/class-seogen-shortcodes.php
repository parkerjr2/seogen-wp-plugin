<?php
/**
 * SEOgen Shortcodes - Mode-Aware Internal Linking
 * Phase 3: Shortcodes that adapt to campaign mode (multi-city vs single-city)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Shortcodes {
	
	/**
	 * Register shortcodes
	 */
	public static function init() {
		add_shortcode( 'seogen_service_hub_geo_links', array( __CLASS__, 'render_service_hub_geo_links' ) );
		add_shortcode( 'seogen_geo_parent_link', array( __CLASS__, 'render_geo_parent_link' ) );
	}
	
	/**
	 * Render service hub geo links shortcode
	 * 
	 * On service_hub pages, lists child pages:
	 * - Multi-city mode: city pages or city hubs
	 * - Single-city mode: area pages (service_area) for that service
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render_service_hub_geo_links( $atts ) {
		$atts = shortcode_atts( array(
			'service'        => '',
			'limit'          => 6,
			'include_types'  => '', // Comma-separated list of area types
		), $atts, 'seogen_service_hub_geo_links' );
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return self::debug_comment( 'No post ID' );
		}
		
		// Get service from parameter or post meta
		$service = $atts['service'];
		if ( empty( $service ) ) {
			$service = get_post_meta( $post_id, '_seogen_service', true );
		}
		
		if ( empty( $service ) ) {
			return self::debug_comment( 'No service specified or found in post meta' );
		}
		
		// Get campaign settings
		$campaign_settings = get_option( 'seogen_campaign_settings', array() );
		$campaign_mode = isset( $campaign_settings['campaign_mode'] ) ? $campaign_settings['campaign_mode'] : 'multi_city';
		
		$limit = absint( $atts['limit'] );
		if ( $limit < 1 ) {
			$limit = 6;
		}
		
		if ( 'single_city' === $campaign_mode ) {
			// Single-city mode: query service_area pages
			return self::render_area_links( $service, $limit, $atts['include_types'], $campaign_settings );
		} else {
			// Multi-city mode: query service_city pages or city_hub pages
			return self::render_city_links( $service, $limit );
		}
	}
	
	/**
	 * Render area links for single-city mode
	 * 
	 * @param string $service Service name
	 * @param int $limit Number of links to show
	 * @param string $include_types Comma-separated area types
	 * @param array $campaign_settings Campaign settings
	 * @return string HTML output
	 */
	private static function render_area_links( $service, $limit, $include_types, $campaign_settings ) {
		$primary_city = isset( $campaign_settings['primary_city'] ) ? $campaign_settings['primary_city'] : '';
		$primary_state = isset( $campaign_settings['primary_state'] ) ? $campaign_settings['primary_state'] : '';
		
		if ( empty( $primary_city ) || empty( $primary_state ) ) {
			return self::debug_comment( 'Primary city/state not configured in campaign settings' );
		}
		
		// Build meta query
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => '_seogen_page_mode',
				'value' => 'service_area',
			),
			array(
				'key'   => '_seogen_service',
				'value' => $service,
			),
			array(
				'key'   => '_seogen_city',
				'value' => $primary_city,
			),
			array(
				'key'   => '_seogen_state',
				'value' => $primary_state,
			),
		);
		
		// Filter by area types if specified
		if ( ! empty( $include_types ) ) {
			$types = array_map( 'trim', explode( ',', $include_types ) );
			$meta_query[] = array(
				'key'     => '_seogen_area_type',
				'value'   => $types,
				'compare' => 'IN',
			);
		}
		
		// Query posts
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_query'     => $meta_query,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		
		$posts = get_posts( $args );
		
		if ( empty( $posts ) ) {
			return self::debug_comment( 'No area pages found for service: ' . $service );
		}
		
		// Build output
		$output = '<div class="seogen-area-links">';
		$output .= '<ul class="seogen-links-list">';
		
		foreach ( $posts as $post ) {
			$area_name = get_post_meta( $post->ID, '_seogen_area_name', true );
			$area_type = get_post_meta( $post->ID, '_seogen_area_type', true );
			
			$link_text = $post->post_title;
			if ( ! empty( $area_name ) ) {
				// Use area name in link text if available
				$link_text = $service . ' Near ' . $area_name;
			}
			
			$output .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_permalink( $post->ID ) ),
				esc_html( $link_text )
			);
		}
		
		$output .= '</ul>';
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Render city links for multi-city mode
	 * 
	 * @param string $service Service name
	 * @param int $limit Number of links to show
	 * @return string HTML output
	 */
	private static function render_city_links( $service, $limit ) {
		// Query service_city pages for this service
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_seogen_page_mode',
					'value' => 'service_city',
				),
				array(
					'key'   => '_seogen_service',
					'value' => $service,
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		
		$posts = get_posts( $args );
		
		if ( empty( $posts ) ) {
			return self::debug_comment( 'No city pages found for service: ' . $service );
		}
		
		// Build output
		$output = '<div class="seogen-city-links">';
		$output .= '<ul class="seogen-links-list">';
		
		foreach ( $posts as $post ) {
			$city = get_post_meta( $post->ID, '_seogen_city', true );
			$state = get_post_meta( $post->ID, '_seogen_state', true );
			
			$link_text = $post->post_title;
			if ( ! empty( $city ) ) {
				// Use city in link text
				$link_text = $service . ' in ' . $city;
				if ( ! empty( $state ) ) {
					$link_text .= ', ' . $state;
				}
			}
			
			$output .= sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( get_permalink( $post->ID ) ),
				esc_html( $link_text )
			);
		}
		
		$output .= '</ul>';
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Render geo parent link shortcode
	 * 
	 * On service_area pages, links back to:
	 * - Primary city location page if configured
	 * - Otherwise, relevant service hub
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public static function render_geo_parent_link( $atts ) {
		$atts = shortcode_atts( array(
			'text' => '', // Custom link text
		), $atts, 'seogen_geo_parent_link' );
		
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return self::debug_comment( 'No post ID' );
		}
		
		// Only render on service_area pages
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'service_area' !== $page_mode ) {
			return self::debug_comment( 'Not a service_area page (page_mode: ' . $page_mode . ')' );
		}
		
		// Get page metadata
		$service = get_post_meta( $post_id, '_seogen_service', true );
		$city = get_post_meta( $post_id, '_seogen_city', true );
		$state = get_post_meta( $post_id, '_seogen_state', true );
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		
		// Get campaign settings
		$campaign_settings = get_option( 'seogen_campaign_settings', array() );
		$city_anchor_page = isset( $campaign_settings['city_anchor_page'] ) ? $campaign_settings['city_anchor_page'] : '';
		
		$parent_url = '';
		$parent_text = '';
		
		// Option 1: Link to city anchor page if configured
		if ( ! empty( $city_anchor_page ) ) {
			$parent_url = home_url( $city_anchor_page );
			$parent_text = ! empty( $atts['text'] ) ? $atts['text'] : 'All Services in ' . $city;
		} else {
			// Option 2: Link to service hub
			$parent_post = self::find_service_hub( $service, $hub_key );
			
			if ( $parent_post ) {
				$parent_url = get_permalink( $parent_post->ID );
				$parent_text = ! empty( $atts['text'] ) ? $atts['text'] : get_the_title( $parent_post->ID );
			}
		}
		
		if ( empty( $parent_url ) ) {
			return self::debug_comment( 'No parent page found (city anchor page not configured, service hub not found)' );
		}
		
		// Build output
		$output = '<div class="seogen-geo-parent-link">';
		$output .= sprintf(
			'<a href="%s" class="seogen-parent-link">← %s</a>',
			esc_url( $parent_url ),
			esc_html( $parent_text )
		);
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Find service hub page
	 * 
	 * @param string $service Service name
	 * @param string $hub_key Hub key
	 * @return WP_Post|null Service hub post or null
	 */
	private static function find_service_hub( $service, $hub_key ) {
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'   => '_seogen_page_mode',
				'value' => 'service_hub',
			),
		);
		
		// Try to match by hub_key first (more specific)
		if ( ! empty( $hub_key ) ) {
			$meta_query[] = array(
				'key'   => '_seogen_hub_key',
				'value' => $hub_key,
			);
		}
		
		// Also try to match by service
		if ( ! empty( $service ) ) {
			$meta_query[] = array(
				'key'   => '_seogen_service',
				'value' => $service,
			);
		}
		
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => $meta_query,
			'no_found_rows'  => true,
		);
		
		$posts = get_posts( $args );
		
		return ! empty( $posts ) ? $posts[0] : null;
	}
	
	/**
	 * Generate debug comment
	 * 
	 * @param string $message Debug message
	 * @return string HTML comment or empty string
	 */
	private static function debug_comment( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return '<!-- seogen_shortcode: ' . esc_html( $message ) . ' -->';
		}
		return '';
	}
}

// Initialize shortcodes
SEOgen_Shortcodes::init();
