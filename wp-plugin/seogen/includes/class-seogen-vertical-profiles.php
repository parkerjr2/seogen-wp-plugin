<?php
/**
 * SEOgen Vertical Profiles
 * 
 * Manages vertical-specific hub category defaults and customization
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Vertical_Profiles {
	
	/**
	 * Get default hub categories for a vertical profile
	 * 
	 * @param string $vertical_profile Vertical profile name
	 * @return array Array of hub category objects
	 */
	public static function get_vertical_defaults( $vertical_profile = 'home_services' ) {
		$defaults = array(
			'home_services' => array(
				array(
					'key'        => 'residential',
					'label'      => 'Residential',
					'enabled'    => true,
					'sort_order' => 0,
					'is_custom'  => false,
				),
				array(
					'key'        => 'commercial',
					'label'      => 'Commercial',
					'enabled'    => true,
					'sort_order' => 1,
					'is_custom'  => false,
				),
				array(
					'key'        => 'emergency',
					'label'      => 'Emergency',
					'enabled'    => true,
					'sort_order' => 2,
					'is_custom'  => false,
				),
				array(
					'key'        => 'repair',
					'label'      => 'Repair',
					'enabled'    => true,
					'sort_order' => 3,
					'is_custom'  => false,
				),
				array(
					'key'        => 'installation',
					'label'      => 'Installation',
					'enabled'    => true,
					'sort_order' => 4,
					'is_custom'  => false,
				),
				array(
					'key'        => 'maintenance',
					'label'      => 'Maintenance',
					'enabled'    => true,
					'sort_order' => 5,
					'is_custom'  => false,
				),
			),
			'barbershop' => array(
				array(
					'key'        => 'haircuts',
					'label'      => 'Haircuts',
					'enabled'    => true,
					'sort_order' => 0,
					'is_custom'  => false,
				),
				array(
					'key'        => 'beard-shave',
					'label'      => 'Beard & Shave',
					'enabled'    => true,
					'sort_order' => 1,
					'is_custom'  => false,
				),
				array(
					'key'        => 'kids-family',
					'label'      => 'Kids & Family',
					'enabled'    => true,
					'sort_order' => 2,
					'is_custom'  => false,
				),
				array(
					'key'        => 'specialty-styles',
					'label'      => 'Specialty Styles',
					'enabled'    => true,
					'sort_order' => 3,
					'is_custom'  => false,
				),
				array(
					'key'        => 'packages-memberships',
					'label'      => 'Packages & Memberships',
					'enabled'    => true,
					'sort_order' => 4,
					'is_custom'  => false,
				),
				array(
					'key'        => 'first-time-clients',
					'label'      => 'First-Time Clients',
					'enabled'    => true,
					'sort_order' => 5,
					'is_custom'  => false,
				),
			),
			'spa' => array(
				array(
					'key'        => 'facials-skincare',
					'label'      => 'Facials & Skincare',
					'enabled'    => true,
					'sort_order' => 0,
					'is_custom'  => false,
				),
				array(
					'key'        => 'massage-therapy',
					'label'      => 'Massage Therapy',
					'enabled'    => true,
					'sort_order' => 1,
					'is_custom'  => false,
				),
				array(
					'key'        => 'body-treatments',
					'label'      => 'Body Treatments',
					'enabled'    => true,
					'sort_order' => 2,
					'is_custom'  => false,
				),
				array(
					'key'        => 'wellness-relaxation',
					'label'      => 'Wellness & Relaxation',
					'enabled'    => true,
					'sort_order' => 3,
					'is_custom'  => false,
				),
				array(
					'key'        => 'packages-memberships',
					'label'      => 'Packages & Memberships',
					'enabled'    => true,
					'sort_order' => 4,
					'is_custom'  => false,
				),
				array(
					'key'        => 'special-occasions',
					'label'      => 'Special Occasions',
					'enabled'    => true,
					'sort_order' => 5,
					'is_custom'  => false,
				),
			),
			'dentist' => array(
				array(
					'key'        => 'preventive-care',
					'label'      => 'Preventive Care',
					'enabled'    => true,
					'sort_order' => 0,
					'is_custom'  => false,
				),
				array(
					'key'        => 'restorative-dentistry',
					'label'      => 'Restorative Dentistry',
					'enabled'    => true,
					'sort_order' => 1,
					'is_custom'  => false,
				),
				array(
					'key'        => 'cosmetic-dentistry',
					'label'      => 'Cosmetic Dentistry',
					'enabled'    => true,
					'sort_order' => 2,
					'is_custom'  => false,
				),
				array(
					'key'        => 'emergency-dental-care',
					'label'      => 'Emergency Dental Care',
					'enabled'    => true,
					'sort_order' => 3,
					'is_custom'  => false,
				),
				array(
					'key'        => 'family-dentistry',
					'label'      => 'Family Dentistry',
					'enabled'    => true,
					'sort_order' => 4,
					'is_custom'  => false,
				),
				array(
					'key'        => 'new-patients',
					'label'      => 'New Patients',
					'enabled'    => true,
					'sort_order' => 5,
					'is_custom'  => false,
				),
			),
			'restaurant' => array(
				array(
					'key'        => 'menu',
					'label'      => 'Menu',
					'enabled'    => true,
					'sort_order' => 0,
					'is_custom'  => false,
				),
				array(
					'key'        => 'dining-experience',
					'label'      => 'Dining Experience',
					'enabled'    => true,
					'sort_order' => 1,
					'is_custom'  => false,
				),
				array(
					'key'        => 'specialties',
					'label'      => 'Specialties',
					'enabled'    => true,
					'sort_order' => 2,
					'is_custom'  => false,
				),
				array(
					'key'        => 'catering-events',
					'label'      => 'Catering & Events',
					'enabled'    => true,
					'sort_order' => 3,
					'is_custom'  => false,
				),
				array(
					'key'        => 'reservations',
					'label'      => 'Reservations',
					'enabled'    => true,
					'sort_order' => 4,
					'is_custom'  => false,
				),
				array(
					'key'        => 'our-story',
					'label'      => 'Our Story',
					'enabled'    => true,
					'sort_order' => 5,
					'is_custom'  => false,
				),
			),
		);
		
		return isset( $defaults[ $vertical_profile ] ) ? $defaults[ $vertical_profile ] : $defaults['home_services'];
	}
	
	/**
	 * Get list of available vertical profiles
	 * 
	 * @return array Associative array of profile key => label
	 */
	public static function get_available_verticals() {
		return array(
			'home_services' => __( 'Home Services', 'seogen' ),
			'barbershop'    => __( 'Barbershop', 'seogen' ),
			'spa'           => __( 'Spa', 'seogen' ),
			'dentist'       => __( 'Dentist', 'seogen' ),
			'restaurant'    => __( 'Restaurant', 'seogen' ),
		);
	}
	
	/**
	 * Get saved hub categories
	 * 
	 * @return array Array of hub category objects
	 */
	public static function get_saved_hub_categories() {
		$categories = get_option( 'seogen_hub_categories', array() );
		
		// If empty, migrate from legacy or seed defaults
		if ( empty( $categories ) ) {
			$categories = self::migrate_legacy_hub_categories();
		}
		
		return $categories;
	}
	
	/**
	 * Save hub categories
	 * 
	 * @param array $categories Array of hub category objects
	 * @return bool Success
	 */
	public static function save_hub_categories( $categories ) {
		// Validate and normalize
		$categories = self::validate_hub_categories( $categories );
		
		// Normalize sort order
		$categories = self::normalize_sort_order( $categories );
		
		return update_option( 'seogen_hub_categories', $categories );
	}
	
	/**
	 * Migrate legacy hub categories to new format
	 * 
	 * @return array Array of hub category objects
	 */
	private static function migrate_legacy_hub_categories() {
		// Check for legacy business config
		$business_config = get_option( 'hyper_local_business_config', array() );
		$legacy_categories = isset( $business_config['hub_categories'] ) ? $business_config['hub_categories'] : array();
		
		if ( ! empty( $legacy_categories ) && is_array( $legacy_categories ) ) {
			// Convert legacy format to new format
			$migrated = array();
			$sort_order = 0;
			
			foreach ( $legacy_categories as $key => $label ) {
				$migrated[] = array(
					'key'        => $key,
					'label'      => $label,
					'enabled'    => true,
					'sort_order' => $sort_order++,
					'is_custom'  => false,
				);
			}
			
			// Save migrated categories
			update_option( 'seogen_hub_categories', $migrated );
			update_option( 'seogen_hub_categories_source', 'migrated' );
			update_option( 'seogen_vertical_profile', 'home_services' );
			
			return $migrated;
		}
		
		// No legacy data, seed defaults for home_services
		$defaults = self::get_vertical_defaults( 'home_services' );
		update_option( 'seogen_hub_categories', $defaults );
		update_option( 'seogen_hub_categories_source', 'defaults' );
		update_option( 'seogen_vertical_profile', 'home_services' );
		
		return $defaults;
	}
	
	/**
	 * Validate hub categories array
	 * 
	 * @param array $categories Array of hub category objects
	 * @return array Validated categories
	 */
	public static function validate_hub_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return array();
		}
		
		$validated = array();
		$seen_keys = array();
		
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}
			
			// Required fields
			if ( empty( $category['key'] ) || empty( $category['label'] ) ) {
				continue;
			}
			
			// Ensure unique keys
			$key = sanitize_key( $category['key'] );
			if ( isset( $seen_keys[ $key ] ) ) {
				continue;
			}
			$seen_keys[ $key ] = true;
			
			// Validate label length
			$label = sanitize_text_field( $category['label'] );
			if ( strlen( $label ) > 60 ) {
				$label = substr( $label, 0, 60 );
			}
			
			$validated[] = array(
				'key'        => $key,
				'label'      => $label,
				'enabled'    => ! empty( $category['enabled'] ),
				'sort_order' => isset( $category['sort_order'] ) ? absint( $category['sort_order'] ) : 0,
				'is_custom'  => ! empty( $category['is_custom'] ),
			);
		}
		
		return $validated;
	}
	
	/**
	 * Normalize sort order (0..n-1)
	 * 
	 * @param array $categories Array of hub category objects
	 * @return array Categories with normalized sort order
	 */
	private static function normalize_sort_order( $categories ) {
		// Sort by current sort_order
		usort( $categories, function( $a, $b ) {
			return $a['sort_order'] - $b['sort_order'];
		});
		
		// Renumber
		foreach ( $categories as $i => $category ) {
			$categories[ $i ]['sort_order'] = $i;
		}
		
		return $categories;
	}
	
	/**
	 * Slugify a label to create a key
	 * 
	 * @param string $label Category label
	 * @return string Slugified key
	 */
	public static function slugify_key( $label ) {
		$key = sanitize_title( $label );
		
		// Ensure it's not empty
		if ( empty( $key ) ) {
			$key = 'category-' . time();
		}
		
		return $key;
	}
	
	/**
	 * Ensure key uniqueness
	 * 
	 * @param string $key Proposed key
	 * @param array $existing_categories Existing categories
	 * @return string Unique key
	 */
	public static function ensure_unique_key( $key, $existing_categories ) {
		$original_key = $key;
		$counter = 1;
		
		$existing_keys = array_column( $existing_categories, 'key' );
		
		while ( in_array( $key, $existing_keys, true ) ) {
			$key = $original_key . '-' . $counter;
			$counter++;
		}
		
		return $key;
	}
	
	/**
	 * Get vertical profile
	 * 
	 * @return string Current vertical profile
	 */
	public static function get_vertical_profile() {
		return get_option( 'seogen_vertical_profile', 'home_services' );
	}
	
	/**
	 * Set vertical profile
	 * 
	 * @param string $profile Vertical profile
	 * @return bool Success
	 */
	public static function set_vertical_profile( $profile ) {
		$available = array_keys( self::get_available_verticals() );
		
		if ( ! in_array( $profile, $available, true ) ) {
			$profile = 'home_services';
		}
		
		return update_option( 'seogen_vertical_profile', $profile );
	}
}
