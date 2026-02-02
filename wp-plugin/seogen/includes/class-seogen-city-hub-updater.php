<?php
/**
 * SEOgen City Hub Updater
 * Automatically updates city hub pages when service pages are published after the hub
 *
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_City_Hub_Updater {

	/**
	 * Constructor - Register hooks
	 */
	public function __construct() {
		// Hook into post status transitions (priority 20 runs after cache invalidation at 10)
		add_action( 'transition_post_status', array( $this, 'handle_service_page_published' ), 20, 3 );
	}

	/**
	 * Handle service page publication
	 * Triggers city hub update when a service page is published
	 *
	 * @param string  $new_status New post status
	 * @param string  $old_status Old post status
	 * @param WP_Post $post       Post object
	 */
	public function handle_service_page_published( $new_status, $old_status, $post ) {
		// Guards: Skip autosaves and revisions
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		// Only process service_page post type
		if ( $post->post_type !== 'service_page' ) {
			return;
		}

		// Only process when transitioning TO publish status
		if ( $new_status !== 'publish' ) {
			return;
		}

		// Get page mode - only process service_city pages
		$page_mode = get_post_meta( $post->ID, '_seogen_page_mode', true );
		if ( $page_mode !== 'service_city' ) {
			return;
		}

		// Extract hub_key and city_slug from service page meta
		$hub_key = get_post_meta( $post->ID, '_seogen_hub_key', true );
		$city_slug = get_post_meta( $post->ID, '_seogen_city_slug', true );

		if ( empty( $hub_key ) || empty( $city_slug ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen City Hub Updater] Service page %d published but missing hub_key or city_slug',
					$post->ID
				) );
			}
			return;
		}

		// Log service page publication
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen City Hub Updater] Service page %d published (hub_key=%s, city_slug=%s, title="%s")',
				$post->ID,
				$hub_key,
				$city_slug,
				get_the_title( $post->ID )
			) );
		}

		// Find parent city hub
		$city_hub_id = $this->find_parent_city_hub( $hub_key, $city_slug );

		if ( ! $city_hub_id ) {
			// City hub doesn't exist yet - skip gracefully
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen City Hub Updater] No city hub found for hub_key=%s, city_slug=%s',
					$hub_key,
					$city_slug
				) );
			}
			return;
		}

		// Update city hub with new service links
		$this->update_city_hub_service_links( $city_hub_id, $hub_key, $city_slug );
	}

	/**
	 * Find parent city hub for a given hub_key and city_slug
	 *
	 * @param string $hub_key   Hub key (e.g., 'residential')
	 * @param string $city_slug City slug (e.g., 'tulsa-ok')
	 * @return int|false City hub post ID or false if not found
	 */
	private function find_parent_city_hub( $hub_key, $city_slug ) {
		$query = new WP_Query( array(
			'post_type'      => 'service_page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => '_seogen_page_mode',
					'value' => 'city_hub',
				),
				array(
					'key'   => '_seogen_hub_key',
					'value' => $hub_key,
				),
				array(
					'key'   => '_seogen_city_slug',
					'value' => $city_slug,
				),
			),
		) );

		if ( $query->have_posts() ) {
			return $query->posts[0];
		}

		return false;
	}

	/**
	 * Update city hub service links
	 * Regenerates service links and updates the city hub content
	 *
	 * @param int    $city_hub_id City hub post ID
	 * @param string $hub_key     Hub key
	 * @param string $city_slug   City slug
	 */
	private function update_city_hub_service_links( $city_hub_id, $hub_key, $city_slug ) {
		// Check transient lock to prevent duplicate updates (5-minute window for batch publishing)
		$lock_key = '_seogen_hub_updating_' . $city_hub_id;
		if ( get_transient( $lock_key ) ) {
			// Another update is in progress or recently completed
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen City Hub Updater] Skipping city hub %d update - transient lock active (batch publishing in progress)',
					$city_hub_id
				) );
			}
			return;
		}

		// Set transient lock (5 minutes to handle production scenario of 10 pages/day)
		set_transient( $lock_key, true, 5 * MINUTE_IN_SECONDS );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen City Hub Updater] Set transient lock for city hub %d (5 minutes)',
				$city_hub_id
			) );
		}

		// Get city hub post
		$city_hub = get_post( $city_hub_id );
		if ( ! $city_hub || $city_hub->post_status !== 'publish' ) {
			return;
		}

		// Get city name and state from meta
		// City hubs store city as "City Name, ST" in _seogen_city field
		$city_meta = get_post_meta( $city_hub_id, '_seogen_city', true );

		if ( empty( $city_meta ) ) {
			return;
		}

		// Parse city and state from combined field
		$city_parts = array_map( 'trim', explode( ',', $city_meta ) );
		$city_name = isset( $city_parts[0] ) ? $city_parts[0] : '';
		$state = isset( $city_parts[1] ) ? $city_parts[1] : '';

		if ( empty( $city_name ) || empty( $state ) ) {
			return;
		}

		// Regenerate service links in content
		$updated_content = $this->regenerate_service_links_in_content(
			$city_hub->post_content,
			$hub_key,
			$city_slug,
			$city_name,
			$state
		);

		// Only update if content changed
		if ( $updated_content === $city_hub->post_content ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'[SEOgen City Hub Updater] City hub %d content unchanged - no update needed (city=%s, hub_key=%s)',
					$city_hub_id,
					$city_name,
					$hub_key
				) );
			}
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen City Hub Updater] Updating city hub %d content with new service links (city=%s, %s, hub_key=%s)',
				$city_hub_id,
				$city_name,
				$state,
				$hub_key
			) );
		}

		// Remove hook temporarily to prevent infinite loop
		remove_action( 'transition_post_status', array( $this, 'handle_service_page_published' ), 20 );

		// Update post
		wp_update_post( array(
			'ID'           => $city_hub_id,
			'post_content' => $updated_content,
		) );

		// Re-add hook
		add_action( 'transition_post_status', array( $this, 'handle_service_page_published' ), 20, 3 );

		// Log for debugging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[SEOgen City Hub Updater] Updated city hub %d with new service links (hub_key=%s, city_slug=%s)',
				$city_hub_id,
				$hub_key,
				$city_slug
			) );
		}
	}

	/**
	 * Regenerate service links in content
	 * Replaces existing service links block or token with updated HTML
	 *
	 * @param string $content   City hub post content
	 * @param string $hub_key   Hub key
	 * @param string $city_slug City slug
	 * @param string $city_name City name
	 * @param string $state     State
	 * @return string Updated content
	 */
	private function regenerate_service_links_in_content( $content, $hub_key, $city_slug, $city_name, $state ) {
		// Generate shortcode (queries service pages dynamically)
		$shortcode = $this->build_service_links_html( $hub_key, $city_slug, $city_name, $state );

		// Check if shortcode already exists
		if ( strpos( $content, '[seogen_city_service_links' ) !== false ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen City Hub Updater] City hub already has service links shortcode - no update needed' );
			}
			return $content;
		}

		// Pattern 1: Replace existing <div class="seogen-hub-links">...</div> block
		$pattern1 = '/<div class="seogen-hub-links">.*?<\/div>/s';
		if ( preg_match( $pattern1, $content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen City Hub Updater] Pattern 1 matched: Replacing existing seogen-hub-links div with shortcode' );
			}
			$gutenberg_block = "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
			return preg_replace( $pattern1, $gutenberg_block, $content );
		}

		// Pattern 2: Replace {{CITY_SERVICE_LINKS}} token in paragraph block
		$pattern2 = '/<!-- wp:paragraph[^>]*-->\s*<p[^>]*>{{CITY_SERVICE_LINKS}}<\/p>\s*<!-- \/wp:paragraph -->/i';
		if ( preg_match( $pattern2, $content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen City Hub Updater] Pattern 2 matched: Replacing {{CITY_SERVICE_LINKS}} token with shortcode' );
			}
			$gutenberg_block = "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
			return preg_replace( $pattern2, $gutenberg_block, $content );
		}

		// Pattern 3: Insert before FAQ section (fallback)
		$faq_pattern = '/<!-- wp:heading[^>]*-->\s*<h2[^>]*>(?:Frequently Asked Questions|FAQ)<\/h2>/i';
		if ( preg_match( $faq_pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[SEOgen City Hub Updater] Pattern 3 matched: Inserting shortcode before FAQ section' );
			}
			$insert_pos = $matches[0][1];
			$gutenberg_block = "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->\n\n";
			return substr_replace( $content, $gutenberg_block, $insert_pos, 0 );
		}

		// No suitable insertion point found - return content unchanged
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[SEOgen City Hub Updater] No pattern matched - cannot insert service links shortcode. Content may need manual adjustment.' );
		}
		return $content;
	}

	/**
	 * Build service links HTML
	 * Returns a shortcode that will query service pages dynamically
	 *
	 * @param string $hub_key   Hub key
	 * @param string $city_slug City slug
	 * @param string $city_name City name (unused, kept for compatibility)
	 * @param string $state     State (unused, kept for compatibility)
	 * @return string Shortcode that will render service links dynamically
	 */
	private function build_service_links_html( $hub_key, $city_slug, $city_name, $state ) {
		// Return shortcode that queries service pages dynamically on every page load
		// This matches how service hubs work with city links
		return '[seogen_city_service_links hub_key="' . esc_attr( $hub_key ) . '" city_slug="' . esc_attr( $city_slug ) . '"]';
	}
}
