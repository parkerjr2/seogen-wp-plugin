<?php
/**
 * SEOgen Breadcrumbs Module
 * 
 * Implements JSON-LD BreadcrumbList schema and optional visible breadcrumbs
 * to reinforce site hierarchy for Google.
 * 
 * Supports 3 page modes:
 * - service_hub: Home → Service Hub
 * - city_hub: Home → Service Hub → City Hub
 * - service_city: Home → Service Hub → City Hub → Service Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Breadcrumbs {
	
	/**
	 * Initialize breadcrumbs module
	 */
	public static function init() {
		// Output JSON-LD schema in wp_head
		add_action( 'wp_head', array( __CLASS__, 'output_breadcrumb_schema' ), 5 );
		
		// Output visible breadcrumbs (optional)
		add_filter( 'the_content', array( __CLASS__, 'prepend_visible_breadcrumbs' ), 1 );
	}
	
	/**
	 * Output JSON-LD BreadcrumbList schema
	 */
	public static function output_breadcrumb_schema() {
		if ( ! is_singular( 'service_page' ) ) {
			return;
		}
		
		global $post;
		
		$page_mode = get_post_meta( $post->ID, '_seogen_page_mode', true );
		
		if ( empty( $page_mode ) || ! in_array( $page_mode, array( 'service_hub', 'city_hub', 'service_city' ), true ) ) {
			return;
		}
		
		$breadcrumbs = self::build_breadcrumb_items( $post->ID, $page_mode );
		
		if ( empty( $breadcrumbs ) ) {
			return;
		}
		
		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'BreadcrumbList',
			'itemListElement' => $breadcrumbs,
		);
		
		echo "\n<!-- SEOgen Breadcrumb Schema -->\n";
		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n";
		echo '</script>' . "\n";
	}
	
	/**
	 * Build breadcrumb items array for JSON-LD
	 * 
	 * @param int $post_id Post ID
	 * @param string $page_mode Page mode (service_hub, city_hub, service_city)
	 * @return array Breadcrumb items
	 */
	private static function build_breadcrumb_items( $post_id, $page_mode ) {
		$items = array();
		$position = 1;
		
		// Always start with Home
		$items[] = array(
			'@type' => 'ListItem',
			'position' => $position++,
			'name' => 'Home',
			'item' => home_url( '/' ),
		);
		
		// Get metadata
		$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
		
		// Build hierarchy based on page mode
		switch ( $page_mode ) {
			case 'service_hub':
				// Home → Service Hub
				$items[] = self::get_service_hub_item( $post_id, $hub_key, $position );
				break;
				
			case 'city_hub':
				// Home → Service Hub → City Hub
				$service_hub_item = self::get_parent_service_hub_item( $hub_key, $position++ );
				if ( $service_hub_item ) {
					$items[] = $service_hub_item;
				}
				$items[] = self::get_city_hub_item( $post_id, $position );
				break;
				
			case 'service_city':
				// Home → Service Hub → City Hub → Service Page
				$service_hub_item = self::get_parent_service_hub_item( $hub_key, $position++ );
				if ( $service_hub_item ) {
					$items[] = $service_hub_item;
				}
				
				$city_hub_item = self::get_parent_city_hub_item( $post_id, $hub_key, $position++ );
				if ( $city_hub_item ) {
					$items[] = $city_hub_item;
				}
				
				$items[] = self::get_service_city_item( $post_id, $position );
				break;
		}
		
		return $items;
	}
	
	/**
	 * Get service hub breadcrumb item (current page)
	 * 
	 * @param int $post_id Post ID
	 * @param string $hub_key Hub key
	 * @param int $position Position in breadcrumb
	 * @return array Breadcrumb item
	 */
	private static function get_service_hub_item( $post_id, $hub_key, $position ) {
		$title = get_the_title( $post_id );
		
		// Remove business name suffix if present
		if ( strpos( $title, ' | ' ) !== false ) {
			$title = substr( $title, 0, strpos( $title, ' | ' ) );
		}
		
		return array(
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $title,
			'item' => get_permalink( $post_id ),
		);
	}
	
	/**
	 * Get parent service hub breadcrumb item
	 * 
	 * @param string $hub_key Hub key
	 * @param int $position Position in breadcrumb
	 * @return array|null Breadcrumb item or null if not found
	 */
	private static function get_parent_service_hub_item( $hub_key, $position ) {
		if ( empty( $hub_key ) ) {
			return null;
		}
		
		// Find service hub post by hub_key
		$hub_posts = get_posts( array(
			'post_type' => 'service_page',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
					'compare' => '=',
				),
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_hub',
					'compare' => '=',
				),
			),
		) );
		
		if ( empty( $hub_posts ) ) {
			// Fallback: use title-cased hub_key
			$hub_label = ucwords( str_replace( array( '-', '_' ), ' ', $hub_key ) );
			return array(
				'@type' => 'ListItem',
				'position' => $position,
				'name' => $hub_label,
				'item' => home_url( '/' . $hub_key . '/' ),
			);
		}
		
		$hub_post = $hub_posts[0];
		$title = get_the_title( $hub_post->ID );
		
		// Remove business name suffix if present
		if ( strpos( $title, ' | ' ) !== false ) {
			$title = substr( $title, 0, strpos( $title, ' | ' ) );
		}
		
		return array(
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $title,
			'item' => get_permalink( $hub_post->ID ),
		);
	}
	
	/**
	 * Get city hub breadcrumb item (current page)
	 * 
	 * @param int $post_id Post ID
	 * @param int $position Position in breadcrumb
	 * @return array Breadcrumb item
	 */
	private static function get_city_hub_item( $post_id, $position ) {
		$title = get_the_title( $post_id );
		
		// Remove business name suffix if present
		if ( strpos( $title, ' | ' ) !== false ) {
			$title = substr( $title, 0, strpos( $title, ' | ' ) );
		}
		
		return array(
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $title,
			'item' => get_permalink( $post_id ),
		);
	}
	
	/**
	 * Get parent city hub breadcrumb item
	 * 
	 * @param int $post_id Current post ID
	 * @param string $hub_key Hub key
	 * @param int $position Position in breadcrumb
	 * @return array|null Breadcrumb item or null if not found
	 */
	private static function get_parent_city_hub_item( $post_id, $hub_key, $position ) {
		// Try to get city hub from post parent
		$parent_id = wp_get_post_parent_id( $post_id );
		
		if ( $parent_id ) {
			$parent_mode = get_post_meta( $parent_id, '_seogen_page_mode', true );
			if ( 'city_hub' === $parent_mode ) {
				$title = get_the_title( $parent_id );
				
				// Remove business name suffix if present
				if ( strpos( $title, ' | ' ) !== false ) {
					$title = substr( $title, 0, strpos( $title, ' | ' ) );
				}
				
				return array(
					'@type' => 'ListItem',
					'position' => $position,
					'name' => $title,
					'item' => get_permalink( $parent_id ),
				);
			}
		}
		
		// Fallback: try to find city hub by city slug and hub_key
		$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
		
		if ( empty( $city_slug ) || empty( $hub_key ) ) {
			return null;
		}
		
		$city_hub_posts = get_posts( array(
			'post_type' => 'service_page',
			'posts_per_page' => 1,
			'meta_query' => array(
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
				array(
					'key' => '_seogen_page_mode',
					'value' => 'city_hub',
					'compare' => '=',
				),
			),
		) );
		
		if ( empty( $city_hub_posts ) ) {
			return null;
		}
		
		$city_hub_post = $city_hub_posts[0];
		$title = get_the_title( $city_hub_post->ID );
		
		// Remove business name suffix if present
		if ( strpos( $title, ' | ' ) !== false ) {
			$title = substr( $title, 0, strpos( $title, ' | ' ) );
		}
		
		return array(
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $title,
			'item' => get_permalink( $city_hub_post->ID ),
		);
	}
	
	/**
	 * Get service+city breadcrumb item (current page)
	 * 
	 * @param int $post_id Post ID
	 * @param int $position Position in breadcrumb
	 * @return array Breadcrumb item
	 */
	private static function get_service_city_item( $post_id, $position ) {
		$title = get_the_title( $post_id );
		
		// Remove business name suffix if present
		if ( strpos( $title, ' | ' ) !== false ) {
			$title = substr( $title, 0, strpos( $title, ' | ' ) );
		}
		
		return array(
			'@type' => 'ListItem',
			'position' => $position,
			'name' => $title,
			'item' => get_permalink( $post_id ),
		);
	}
	
	/**
	 * Prepend visible breadcrumbs to content
	 * 
	 * @param string $content Post content
	 * @return string Modified content with breadcrumbs
	 */
	public static function prepend_visible_breadcrumbs( $content ) {
		if ( ! is_singular( 'service_page' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		
		global $post;
		
		$page_mode = get_post_meta( $post->ID, '_seogen_page_mode', true );
		
		if ( empty( $page_mode ) || ! in_array( $page_mode, array( 'service_hub', 'city_hub', 'service_city' ), true ) ) {
			return $content;
		}
		
		$breadcrumbs = self::build_breadcrumb_items( $post->ID, $page_mode );
		
		if ( empty( $breadcrumbs ) ) {
			return $content;
		}
		
		// Build visible breadcrumb HTML
		$html = '<nav class="seogen-breadcrumbs" aria-label="Breadcrumb">';
		$html .= '<ol class="seogen-breadcrumb-list">';
		
		$total = count( $breadcrumbs );
		foreach ( $breadcrumbs as $index => $item ) {
			$is_last = ( $index === $total - 1 );
			
			$html .= '<li class="seogen-breadcrumb-item">';
			
			if ( $is_last ) {
				$html .= '<span class="seogen-breadcrumb-current" aria-current="page">' . esc_html( $item['name'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $item['item'] ) . '">' . esc_html( $item['name'] ) . '</a>';
			}
			
			if ( ! $is_last ) {
				$html .= '<span class="seogen-breadcrumb-separator" aria-hidden="true"> › </span>';
			}
			
			$html .= '</li>';
		}
		
		$html .= '</ol>';
		$html .= '</nav>';
		
		// Add minimal CSS
		$html .= '<style>
.seogen-breadcrumbs {
	margin: 0 0 1.5em 0;
	padding: 0.5em 0;
	font-size: 0.9em;
	color: #666;
}
.seogen-breadcrumb-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
}
.seogen-breadcrumb-item {
	display: inline-flex;
	align-items: center;
	margin: 0;
}
.seogen-breadcrumb-item a {
	color: #0073aa;
	text-decoration: none;
}
.seogen-breadcrumb-item a:hover {
	text-decoration: underline;
}
.seogen-breadcrumb-current {
	color: #333;
}
.seogen-breadcrumb-separator {
	margin: 0 0.5em;
	color: #999;
}
</style>';
		
		return $html . $content;
	}
}

// Initialize breadcrumbs module
SEOgen_Breadcrumbs::init();
