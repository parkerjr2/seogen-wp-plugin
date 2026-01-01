<?php
/**
 * Cities Management Handlers
 * 
 * Handles saving and deleting cities for City Hub page generation.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Admin_Cities {
	
	/**
	 * Handle save cities form submission
	 */
	public function handle_save_cities() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_save_cities', 'hyper_local_cities_nonce' );

		// Get campaign settings to determine mode
		$campaign_settings = get_option( 'seogen_campaign_settings', array() );
		$campaign_mode = isset( $campaign_settings['campaign_mode'] ) ? $campaign_settings['campaign_mode'] : 'multi_city';
		$is_single_city = ( 'single_city' === $campaign_mode );

		$cities = array();

		// Process existing cities (edits)
		if ( isset( $_POST['cities'] ) && is_array( $_POST['cities'] ) ) {
			foreach ( $_POST['cities'] as $city_data ) {
				if ( isset( $city_data['name'], $city_data['state'] ) ) {
					$name = sanitize_text_field( $city_data['name'] );
					$state = sanitize_text_field( $city_data['state'] );
					
					// For single-city mode, state is actually the type (neighborhood, district, etc.)
					// For multi-city mode, state is the state code
					if ( ! $is_single_city ) {
						$state = strtoupper( $state );
					}
					
					$cities[] = array(
						'name' => $name,
						'state' => $state,
						'slug' => sanitize_title( $name . '-' . $state ),
					);
				}
			}
		}

		// Process bulk add cities
		if ( isset( $_POST['bulk_cities'] ) && '' !== trim( $_POST['bulk_cities'] ) ) {
			$lines = explode( "\n", wp_unslash( $_POST['bulk_cities'] ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}

				if ( $is_single_city ) {
					// Single-city mode: "Location Name | Type" format
					if ( strpos( $line, '|' ) !== false ) {
						$parts = array_map( 'trim', explode( '|', $line, 2 ) );
						if ( count( $parts ) === 2 ) {
							$location_name = $parts[0];
							$location_type = $parts[1];
							
							$cities[] = array(
								'name' => sanitize_text_field( $location_name ),
								'state' => sanitize_text_field( $location_type ),
								'slug' => sanitize_title( $location_name . '-' . $location_type ),
							);
						}
					}
				} else {
					// Multi-city mode: "City Name, ST" format
					$parts = array_map( 'trim', explode( ',', $line ) );
					if ( count( $parts ) >= 2 ) {
						$city_name = $parts[0];
						$state_code = strtoupper( $parts[1] );
						
						// Validate state code is 2 letters
						if ( strlen( $state_code ) === 2 && ctype_alpha( $state_code ) ) {
							$cities[] = array(
								'name' => sanitize_text_field( $city_name ),
								'state' => $state_code,
								'slug' => sanitize_title( $city_name . '-' . $state_code ),
							);
						}
					}
				}
			}
		}

		// Remove duplicates based on slug
		$unique_cities = array();
		$seen_slugs = array();
		foreach ( $cities as $city ) {
			if ( ! in_array( $city['slug'], $seen_slugs, true ) ) {
				$unique_cities[] = $city;
				$seen_slugs[] = $city['slug'];
			}
		}

		update_option( 'hyper_local_cities_cache', $unique_cities );

		$success_message = $is_single_city ? 'Locations saved successfully.' : 'Cities saved successfully.';
		
		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-services',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( $success_message ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle delete city action
	 */
	public function handle_delete_city() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$index = isset( $_GET['index'] ) ? (int) $_GET['index'] : -1;
		
		if ( $index < 0 ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-services',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Invalid city index.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		check_admin_referer( 'hyper_local_delete_city_' . $index, 'nonce' );

		$cities = get_option( 'hyper_local_cities_cache', array() );
		
		if ( ! isset( $cities[ $index ] ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-services',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'City not found.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$deleted_city_name = $cities[ $index ]['name'];
		
		// Remove the city at the specified index
		array_splice( $cities, $index, 1 );
		
		// Re-index the array to maintain sequential keys
		$cities = array_values( $cities );
		
		update_option( 'hyper_local_cities_cache', $cities );

		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-services',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( 'City "' . $deleted_city_name . '" deleted successfully.' ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}
