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
	 * DISABLED: All City Hubs now have service links integrated at generation time.
	 * Auto-injection is no longer needed and was causing duplicate service sections.
	 * 
	 * @param string $content Post content
	 * @return string Unmodified content
	 */
	public function inject_service_links_into_city_hub( $content ) {
		// DISABLED: Service links are now integrated at generation time for ALL City Hubs.
		// Auto-injection caused duplicate "Services Available" sections appearing after FAQ.
		// All City Hub pages should be regenerated to get integrated service links.
		// Simply return content unchanged.
		
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
				$post_id = get_the_ID();
				$debug = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">';
				$debug .= '<strong>DEBUG: City Service Links</strong><br>';
				$debug .= 'Post ID: ' . $post_id . '<br>';
				$debug .= 'Hub Key: ' . ( empty( $hub_key ) ? '<em>MISSING</em>' : esc_html( $hub_key ) ) . '<br>';
				$debug .= 'City Slug: ' . ( empty( $city_slug ) ? '<em>MISSING</em>' : esc_html( $city_slug ) ) . '<br>';
				$debug .= '</div>';
				return $debug;
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

		// Render output with Service Hub UI style
		$output = $this->render_service_links_html( $service_pages, $city_display_name, $hub_key, $city_slug );

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
		
		// Output debug info to browser console
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'wp_footer', function() use ( $hub_key, $city_slug, $post_status, $posts, $query ) {
				?>
				<script>
				console.group('🔍 SEOgen City Service Links Debug');
				console.log('Query Parameters:', {
					hub_key: <?php echo wp_json_encode( $hub_key ); ?>,
					city_slug: <?php echo wp_json_encode( $city_slug ); ?>,
					post_status: <?php echo wp_json_encode( $post_status ); ?>
				});
				console.log('Results Found:', <?php echo count( $posts ); ?>);
				console.log('SQL Query:', <?php echo wp_json_encode( $query->request ); ?>);
				<?php if ( ! empty( $posts ) ) : ?>
				console.log('Found Posts:', <?php echo wp_json_encode( array_map( function( $p ) {
					return array(
						'ID' => $p->ID,
						'title' => $p->post_title,
						'slug' => $p->post_name,
					);
				}, $posts ) ); ?>);
				<?php else : ?>
				console.warn('No posts found! Checking for posts with partial matches...');
				
				// Query without city_slug to see what's available
				<?php
				$debug_args = array(
					'post_type' => 'service_page',
					'post_status' => $post_status,
					'posts_per_page' => 10,
					'meta_query' => array(
						'relation' => 'AND',
						array(
							'key' => '_seogen_page_mode',
							'value' => 'service_city',
							'compare' => '=',
						),
						array(
							'key' => '_seogen_hub_key',
							'value' => $hub_key,
							'compare' => '=',
						),
					),
				);
				$debug_query = new WP_Query( $debug_args );
				if ( ! empty( $debug_query->posts ) ) :
					$debug_posts = array_map( function( $p ) {
						return array(
							'ID' => $p->ID,
							'title' => $p->post_title,
							'_seogen_city_slug' => get_post_meta( $p->ID, '_seogen_city_slug', true ),
							'_seogen_hub_key' => get_post_meta( $p->ID, '_seogen_hub_key', true ),
						);
					}, $debug_query->posts );
					?>
					console.log('Posts with hub_key=<?php echo esc_js( $hub_key ); ?> (any city):', <?php echo wp_json_encode( $debug_posts ); ?>);
				<?php endif; ?>
				<?php endif; ?>
				console.groupEnd();
				</script>
				<?php
			}, 999 );
		}
		
		wp_reset_postdata();

		return $posts;
	}

	/**
	 * Render debug output for administrators
	 * 
	 * @param string $hub_key Hub key being queried
	 * @param string $city_slug City slug being queried
	 * @param array  $service_pages Array of WP_Post objects found
	 * @return string Debug HTML
	 */
	private function render_debug_output( $hub_key, $city_slug, $service_pages ) {
		$debug = '<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;font-family:monospace;font-size:12px;">';
		$debug .= '<strong style="font-size:14px;">🔍 DEBUG: City Service Links Query</strong><br><br>';
		$debug .= '<strong>Query Parameters:</strong><br>';
		$debug .= '• Hub Key: <code>' . esc_html( $hub_key ) . '</code><br>';
		$debug .= '• City Slug: <code>' . esc_html( $city_slug ) . '</code><br>';
		$debug .= '• Results Found: <strong>' . count( $service_pages ) . '</strong><br><br>';

		if ( empty( $service_pages ) ) {
			// Show first 5 service_city posts in this hub with their city_slug values
			$debug .= '<strong style="color:#d63638;">No matches found. Showing first 5 service_city pages in this hub:</strong><br>';
			
			$args = array(
				'post_type'      => 'service_page',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 5,
				'orderby'        => 'date',
				'order'          => 'DESC',
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
				),
			);

			$sample_query = new WP_Query( $args );
			if ( $sample_query->have_posts() ) {
				$debug .= '<table style="width:100%;border-collapse:collapse;margin-top:10px;">';
				$debug .= '<tr style="background:#f0f0f0;"><th style="text-align:left;padding:5px;">Post Title</th><th style="text-align:left;padding:5px;">_seogen_city_slug</th><th style="text-align:left;padding:5px;">Permalink</th></tr>';
				while ( $sample_query->have_posts() ) {
					$sample_query->the_post();
					$post_id = get_the_ID();
					$stored_city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
					$permalink = get_permalink( $post_id );
					$debug .= '<tr style="border-bottom:1px solid #ddd;">';
					$debug .= '<td style="padding:5px;">' . esc_html( get_the_title() ) . '</td>';
					$debug .= '<td style="padding:5px;"><code>' . ( $stored_city_slug ? esc_html( $stored_city_slug ) : '<em style="color:#d63638;">MISSING</em>' ) . '</code></td>';
					$debug .= '<td style="padding:5px;font-size:10px;">' . esc_html( basename( $permalink ) ) . '</td>';
					$debug .= '</tr>';
				}
				$debug .= '</table>';
				wp_reset_postdata();
			} else {
				$debug .= '<em>No service_city pages found in this hub at all.</em>';
			}

			$debug .= '<br><br><strong>💡 Likely Issue:</strong> City slug mismatch. Expected <code>' . esc_html( $city_slug ) . '</code> but pages have different values.';
		} else {
			$debug .= '<strong style="color:#46b450;">✓ Query successful</strong>';
		}

		$debug .= '</div>';
		return $debug;
	}

	/**
	 * Render service links HTML with natural inline links
	 * 
	 * Uses shared helper from admin-helpers trait to ensure consistent output.
	 * Renders natural prose with inline service links using service_name meta.
	 * 
	 * @param array  $service_pages Array of WP_Post objects
	 * @param string $city_display_name City display name (e.g., "Tulsa, OK")
	 * @param string $hub_key Hub key for debug comment
	 * @param string $city_slug City slug for debug comment
	 * @return string HTML output
	 */
	private function render_service_links_html( $service_pages, $city_display_name, $hub_key = '', $city_slug = '' ) {
		// Parse city and state from display name
		$parts = explode( ',', $city_display_name );
		$city_name = trim( $parts[0] );
		$state = isset( $parts[1] ) ? trim( $parts[1] ) : '';
		
		// Check if we can use the shared helper from admin-helpers trait
		if ( method_exists( $this, 'render_natural_city_service_links' ) ) {
			return $this->render_natural_city_service_links( $service_pages, $city_name, $state, $hub_key, $city_slug );
		}
		
		// Fallback: Render using local implementation (same logic as shared helper)
		return $this->render_natural_service_links_fallback( $service_pages, $city_name, $state, $hub_key, $city_slug );
	}
	
	/**
	 * Fallback renderer for natural service links
	 * 
	 * Implements same logic as shared helper in admin-helpers trait.
	 * Used when trait method is not available.
	 * 
	 * @param array  $service_pages Array of WP_Post objects
	 * @param string $city_name City name
	 * @param string $state State abbreviation
	 * @param string $hub_key Hub key
	 * @param string $city_slug City slug
	 * @return string HTML output
	 */
	private function render_natural_service_links_fallback( $service_pages, $city_name, $state, $hub_key = '', $city_slug = '' ) {
		$output = '';
		
		// Admin-only debug comment
		if ( current_user_can( 'manage_options' ) ) {
			$output .= sprintf(
				'<!-- seogen city services: hub_key=%s, city_slug=%s, count=%d -->' . "\n",
				esc_attr( $hub_key ),
				esc_attr( $city_slug ),
				count( $service_pages )
			);
		}
		
		$output .= '<div class="seogen-hub-links">' . "\n";
		
		if ( empty( $service_pages ) ) {
			// Empty state
			$output .= '  <p>We\'re expanding our service coverage in this area. Call us and we\'ll confirm availability.</p>' . "\n";
			if ( current_user_can( 'manage_options' ) ) {
				$output .= '  <!-- No service_city pages found with matching hub_key + city_slug -->' . "\n";
			}
		} else {
			// Get templates
			$templates = $this->get_service_link_sentence_templates();
			$hash = md5( $hub_key . '_' . $city_slug );
			$template_index = hexdec( substr( $hash, 0, 8 ) ) % count( $templates );
			$template = $templates[ $template_index ];
			
			// Build inline links using clean anchor text
			$inline_count = min( count( $service_pages ), 5 );
			$inline_links = array();
			
			for ( $i = 0; $i < $inline_count; $i++ ) {
				$post = $service_pages[ $i ];
				$permalink = get_permalink( $post->ID );
				$anchor_text = $this->get_service_anchor_text( $post->ID );
				$inline_links[] = '<a href="' . esc_url( $permalink ) . '">' . esc_html( $anchor_text ) . '</a>';
			}
			
			// Format links naturally
			if ( count( $inline_links ) === 1 ) {
				$links_text = $inline_links[0];
			} elseif ( count( $inline_links ) === 2 ) {
				$links_text = $inline_links[0] . ' and ' . $inline_links[1];
			} else {
				$last = array_pop( $inline_links );
				$links_text = implode( ', ', $inline_links ) . ', and ' . $last;
			}
			
			// Replace placeholders
			$sentence = str_replace(
				array( '{city}', '{links}' ),
				array( esc_html( $city_name ), $links_text ),
				$template
			);
			
			$output .= '  <p>' . $sentence . '</p>' . "\n";
			
			// Optional list (capped at 12)
			if ( count( $service_pages ) > 3 ) {
				$output .= '  <ul>' . "\n";
				$list_count = min( count( $service_pages ), 12 );
				for ( $i = 0; $i < $list_count; $i++ ) {
					$post = $service_pages[ $i ];
					$permalink = get_permalink( $post->ID );
					$anchor_text = $this->get_service_anchor_text( $post->ID );
					$output .= '    <li><a href="' . esc_url( $permalink ) . '">' . esc_html( $anchor_text ) . '</a></li>' . "\n";
				}
				$output .= '  </ul>' . "\n";
			}
		}
		
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Get trade-neutral sentence templates
	 * 
	 * @return array Array of sentence templates
	 */
	private function get_service_link_sentence_templates() {
		return array(
			'Homeowners in {city} often call for {links}—and we also handle related work when needed.',
			'If you\'re comparing options for {links} in {city}, these pages explain what to expect and when to call a pro.',
			'For common projects in {city}, start with {links}.',
			'Local property owners frequently schedule {links} to keep things running smoothly.',
			'Many projects we handle in {city} involve {links}.',
			'Property owners in {city} regularly need services such as {links}.',
			'Common service requests in the area include {links}.',
			'Residents and businesses in {city} often require {links}.',
			'We frequently assist clients in {city} with {links}.',
			'Popular services in the area include {links}.',
			'Many {city} properties benefit from services like {links}.',
			'Typical service needs in {city} include {links}.',
		);
	}
	
	/**
	 * Get clean anchor text for service link
	 * 
	 * @param int $post_id Service page post ID
	 * @return string Clean anchor text
	 */
	private function get_service_anchor_text( $post_id ) {
		// Try service_name meta first
		$service_name = get_post_meta( $post_id, '_seogen_service_name', true );
		if ( ! empty( $service_name ) ) {
			return $service_name;
		}
		
		// Fallback: Clean the title
		$title = get_the_title( $post_id );
		
		// Remove " in {City}" pattern
		$title = preg_replace( '/\s+in\s+[A-Z][^|]+/', '', $title );
		
		// Remove " | {Business Name}" pattern
		$title = preg_replace( '/\s*\|\s*.+$/', '', $title );
		
		return trim( $title );
	}

	/**
	 * Bust service links cache when a service_page is saved/updated
	 * 
	 * Busts cache for both service_city pages and city_hub pages
	 * 
	 * @param int $post_id Post ID
	 */
	public function bust_service_links_cache( $post_id ) {
		$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
		
		// Bust cache for service_city pages
		if ( 'service_city' === $page_mode ) {
			$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
			$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );

			if ( ! empty( $hub_key ) && ! empty( $city_slug ) ) {
				$cache_key = 'seogen_city_services_' . md5( $hub_key . '_' . $city_slug );
				delete_transient( $cache_key );
			}
		}
		
		// Bust cache for city_hub pages (when hub page is updated, clear its service links cache)
		if ( 'city_hub' === $page_mode ) {
			$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
			$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );

			if ( ! empty( $hub_key ) && ! empty( $city_slug ) ) {
				$cache_key = 'seogen_city_services_' . md5( $hub_key . '_' . $city_slug );
				delete_transient( $cache_key );
			}
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
