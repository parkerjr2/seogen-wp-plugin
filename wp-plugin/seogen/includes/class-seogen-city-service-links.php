<?php
/**
 * City Hub Service Links
 * 
 * Automatically lists and links to all Individual Service Pages for a given city + hub
 * on City Hub pages using meta query (no title parsing).
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_City_Service_Links {

	/**
	 * Initialize shortcode and hooks
	 */
	public function __construct() {
		add_shortcode( 'seogen_city_service_links', array( $this, 'render_city_service_links_shortcode' ) );
		
		// Auto-inject service links into City Hub pages
		add_filter( 'the_content', array( $this, 'inject_service_links_into_city_hub' ), 20 );
		
		// Cache busting: Clear transient when service_page is saved/updated/deleted
		add_action( 'save_post_service_page', array( $this, 'bust_service_links_cache' ), 10, 1 );
		add_action( 'delete_post', array( $this, 'bust_service_links_cache_on_delete' ), 10, 1 );
	}

	/**
	 * Auto-inject service links section into City Hub pages
	 * 
	 * Only injects on city_hub pages, near the end of content but before CTA/FAQ if possible.
	 * 
	 * @param string $content Post content
	 * @return string Modified content
	 */
	public function inject_service_links_into_city_hub( $content ) {
		// Only run on singular city_hub pages
		if ( ! is_singular( 'service_page' ) ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Check if this is a city_hub page
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'city_hub' !== $page_mode ) {
			return $content;
		}

		// Check if shortcode already exists in content (avoid duplicate injection)
		if ( false !== strpos( $content, '[seogen_city_service_links]' ) || false !== strpos( $content, 'seogen-city-service-links' ) ) {
			return $content;
		}

		// Render service links section
		$service_links_html = $this->render_city_service_links_shortcode();

		// Inject before FAQ section if it exists, otherwise append to end
		// Look for common FAQ heading patterns
		$faq_patterns = array(
			'/<h2[^>]*>.*?FAQ.*?<\/h2>/i',
			'/<h2[^>]*>.*?Frequently Asked Questions.*?<\/h2>/i',
			'/<h3[^>]*>.*?FAQ.*?<\/h3>/i',
		);

		$injected = false;
		foreach ( $faq_patterns as $pattern ) {
			if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
				// Insert before FAQ heading
				$insert_pos = $matches[0][1];
				$content = substr_replace( $content, $service_links_html . "\n\n", $insert_pos, 0 );
				$injected = true;
				break;
			}
		}

		// If no FAQ found, append to end
		if ( ! $injected ) {
			$content .= "\n\n" . $service_links_html;
		}

		return $content;
	}

	/**
	 * Render shortcode: [seogen_city_service_links hub_key="residential" city_slug="tulsa-ok"]
	 * 
	 * If attributes are not provided, infer from current post meta.
	 * 
	 * @param array $atts Shortcode attributes
	 * @return string HTML output
	 */
	public function render_city_service_links_shortcode( $atts = array() ) {
		// Parse attributes
		$atts = shortcode_atts(
			array(
				'hub_key'   => '',
				'city_slug' => '',
			),
			$atts,
			'seogen_city_service_links'
		);

		$hub_key = $atts['hub_key'];
		$city_slug = $atts['city_slug'];

		// If not provided via attributes, infer from current post meta
		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			$post_id = get_the_ID();
			if ( ! $post_id ) {
				if ( current_user_can( 'manage_options' ) ) {
					return '<!-- [seogen_city_service_links] No post_id -->';
				}
				return '';
			}

			if ( empty( $hub_key ) ) {
				$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
			}
			if ( empty( $city_slug ) ) {
				$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
			}
		}

		// Validate required data
		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<!-- [seogen_city_service_links] Missing hub_key or city_slug -->';
			}
			return '';
		}

		// Get city display name from current post meta (e.g., "Tulsa, OK")
		$city_display_name = '';
		$post_id = get_the_ID();
		if ( $post_id ) {
			$city_display_name = get_post_meta( $post_id, '_seogen_city', true );
		}
		if ( empty( $city_display_name ) ) {
			// Fallback: Convert slug to title case
			$city_display_name = ucwords( str_replace( '-', ' ', $city_slug ) );
		}

		// Check cache first
		$cache_key = 'seogen_city_services_' . md5( $hub_key . '_' . $city_slug );
		$cached_output = get_transient( $cache_key );
		if ( false !== $cached_output ) {
			return $cached_output;
		}

		// Query service_city pages by meta (no title parsing)
		$service_pages = $this->query_service_city_pages( $hub_key, $city_slug );

		// Render output
		$output = $this->render_service_links_html( $service_pages, $city_display_name );

		// Cache for 12 hours
		set_transient( $cache_key, $output, 12 * HOUR_IN_SECONDS );

		return $output;
	}

	/**
	 * Query service_city pages by meta (hub_key + city_slug)
	 * 
	 * @param string $hub_key Hub key (e.g., "residential")
	 * @param string $city_slug City slug (e.g., "tulsa-ok")
	 * @return array Array of WP_Post objects
	 */
	private function query_service_city_pages( $hub_key, $city_slug ) {
		// Include drafts if user can edit posts (for preview)
		$post_status = array( 'publish' );
		if ( current_user_can( 'edit_posts' ) ) {
			$post_status[] = 'draft';
		}

		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => $post_status,
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_seogen_page_mode',
					'value'   => 'service_city',
					'compare' => '=',
				),
				array(
					'key'     => '_seogen_hub_key',
					'value'   => $hub_key,
					'compare' => '=',
				),
				array(
					'key'     => '_seogen_city_slug',
					'value'   => $city_slug,
					'compare' => '=',
				),
			),
		);

		$query = new WP_Query( $args );
		$posts = $query->posts;
		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Render service links HTML
	 * 
	 * @param array  $service_pages Array of WP_Post objects
	 * @param string $city_display_name City display name (e.g., "Tulsa, OK")
	 * @return string HTML output
	 */
	private function render_service_links_html( $service_pages, $city_display_name ) {
		$output = '<div class="seogen-city-service-links">';
		$output .= '<h2>Services Available in ' . esc_html( $city_display_name ) . '</h2>';

		if ( empty( $service_pages ) ) {
			// Empty state: No services found
			$output .= '<p>We\'re expanding our service coverage in this area. Call us and we\'ll confirm availability.</p>';
		} else {
			// Render service links list
			$output .= '<ul class="seogen-service-list">';
			foreach ( $service_pages as $post ) {
				$permalink = get_permalink( $post->ID );
				$title = get_the_title( $post->ID );
				$output .= '<li><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></li>';
			}
			$output .= '</ul>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Bust service links cache when a service_page is saved/updated
	 * 
	 * Only bust cache if the post is a service_city page
	 * 
	 * @param int $post_id Post ID
	 */
	public function bust_service_links_cache( $post_id ) {
		// Check if this is a service_city page
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'service_city' !== $page_mode ) {
			return;
		}

		// Get hub_key and city_slug to bust specific cache
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );

		if ( ! empty( $hub_key ) && ! empty( $city_slug ) ) {
			$cache_key = 'seogen_city_services_' . md5( $hub_key . '_' . $city_slug );
			delete_transient( $cache_key );
		}
	}

	/**
	 * Bust service links cache when a post is deleted
	 * 
	 * @param int $post_id Post ID
	 */
	public function bust_service_links_cache_on_delete( $post_id ) {
		// Check if this is a service_page
		$post_type = get_post_type( $post_id );
		if ( 'service_page' !== $post_type ) {
			return;
		}

		// Get meta before deletion
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		if ( 'service_city' !== $page_mode ) {
			return;
		}

		// Bust cache
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );

		if ( ! empty( $hub_key ) && ! empty( $city_slug ) ) {
			$cache_key = 'seogen_city_services_' . md5( $hub_key . '_' . $city_slug );
			delete_transient( $cache_key );
		}
	}
}

// Initialize
new SEOgen_City_Service_Links();
