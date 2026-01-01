<?php
/**
 * Helper function to create city hub placeholder pages
 * 
 * @param array $job_rows Array of job rows containing city/state data
 * @param array $form Form data containing company info
 * @return array Map of city_slug => post_id
 */
function seogen_create_city_hub_placeholders( $job_rows, $form ) {
	$city_hub_map = array();
	$unique_hub_cities = array();
	
	// Extract unique hub+city combinations from job rows
	// Use hub_key directly from job rows (already determined during validation)
	foreach ( $job_rows as $row ) {
		$city = isset( $row['city'] ) ? trim( (string) $row['city'] ) : '';
		$state = isset( $row['state'] ) ? trim( (string) $row['state'] ) : '';
		$hub_key = isset( $row['hub_key'] ) ? trim( (string) $row['hub_key'] ) : '';
		
		if ( '' !== $city && '' !== $state && '' !== $hub_key ) {
			$city_slug = sanitize_title( $city . '-' . strtolower( $state ) );
			
			// Create unique key for hub+city combination
			$hub_city_key = $hub_key . '|' . $city_slug;
			
			if ( ! isset( $unique_hub_cities[ $hub_city_key ] ) ) {
				$unique_hub_cities[ $hub_city_key ] = array(
					'city' => $city,
					'state' => strtoupper( $state ),
					'city_slug' => $city_slug,
					'hub_key' => $hub_key,
				);
			}
		}
	}
	
	file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Found ' . count( $unique_hub_cities ) . ' unique hub+city combinations for city hub creation' . PHP_EOL, FILE_APPEND );
	
	// Get business config to retrieve hub information
	$config = get_option( 'hyper_local_business_config', array() );
	$company_name = isset( $form['company_name'] ) ? sanitize_text_field( (string) $form['company_name'] ) : '';
	
	// Get hubs from business config
	$hubs = isset( $config['hubs'] ) && is_array( $config['hubs'] ) ? $config['hubs'] : array();
	
	if ( empty( $hubs ) ) {
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: No hubs configured, cannot create city hub placeholders' . PHP_EOL, FILE_APPEND );
		return array();
	}
	
	// Create a map of hub_key => hub data
	$hub_data_map = array();
	foreach ( $hubs as $hub ) {
		if ( isset( $hub['key'] ) ) {
			$hub_data_map[ $hub['key'] ] = $hub;
		}
	}
	
	// Get vertical for trade name
	$vertical = isset( $config['vertical'] ) ? strtolower( $config['vertical'] ) : 'electrician';
	
	// Map vertical to trade name - matches backend vertical_profiles.py
	$trade_name_map = array(
		'roofer' => 'Roofing',
		'roofing' => 'Roofing',
		'electrician' => 'Electrical',
		'electrical' => 'Electrical',
		'plumber' => 'Plumbing',
		'plumbing' => 'Plumbing',
		'hvac' => 'HVAC',
		'hvac technician' => 'HVAC',
		'landscaper' => 'Landscaping',
		'landscaping' => 'Landscaping',
		'handyman' => 'Handyman Services',
		'handyman services' => 'Handyman Services',
		'painter' => 'Painting',
		'painting' => 'Painting',
		'concrete' => 'Concrete',
		'siding' => 'Siding',
		'locksmith' => 'Locksmith Services',
		'locksmith services' => 'Locksmith Services',
		'cleaning' => 'Cleaning Services',
		'cleaning services' => 'Cleaning Services',
		'garage-door' => 'Garage Door',
		'garage door' => 'Garage Door',
		'windows' => 'Window Services',
		'window services' => 'Window Services',
		'pest-control' => 'Pest Control',
		'pest control' => 'Pest Control',
		'other' => 'Home Services',
		'home services' => 'Home Services',
	);
	$trade_name = isset( $trade_name_map[ $vertical ] ) ? $trade_name_map[ $vertical ] : 'Home Services';
	
	file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Vertical: ' . $vertical . ' Trade name: ' . $trade_name . PHP_EOL, FILE_APPEND );
	
	// Create placeholder page for each unique hub+city combination
	foreach ( $unique_hub_cities as $hub_city_key => $hub_city_data ) {
		$hub_key = $hub_city_data['hub_key'];
		$city_slug = $hub_city_data['city_slug'];
		$city_data = array(
			'city' => $hub_city_data['city'],
			'state' => $hub_city_data['state'],
			'slug' => $city_slug,
		);
		
		// Get hub data for this hub_key
		if ( ! isset( $hub_data_map[ $hub_key ] ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: Hub not found for key: ' . $hub_key . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		$hub = $hub_data_map[ $hub_key ];
		$hub_label = isset( $hub['label'] ) ? $hub['label'] : 'Services';
		$hub_slug = isset( $hub['slug'] ) ? $hub['slug'] : '';
		
		// Find the service hub parent page by hub_slug
		$service_hub_parent_id = 0;
		if ( '' !== $hub_slug ) {
			$hub_query = new WP_Query( array(
				'post_type' => 'service_page',
				'post_status' => 'any',
				'posts_per_page' => 1,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => '_seogen_hub_slug',
						'value' => $hub_slug,
						'compare' => '='
					),
					array(
						'key' => '_seogen_page_mode',
						'value' => 'service_hub',
						'compare' => '='
					),
				),
			) );
			
			if ( $hub_query->have_posts() ) {
				$service_hub_parent_id = $hub_query->posts[0]->ID;
				$hub_title = $hub_query->posts[0]->post_title;
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Found service hub parent: ' . $hub_slug . ' (ID: ' . $service_hub_parent_id . ', Title: ' . $hub_title . ')' . PHP_EOL, FILE_APPEND );
			} else {
				file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] WARNING: Service hub not found for slug: ' . $hub_slug . PHP_EOL, FILE_APPEND );
			}
			wp_reset_postdata();
		}
		// Check if city hub already exists for this hub+city combination
		$existing_args = array(
			'post_type' => 'service_page',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_page_type',
					'value' => 'city_hub',
					'compare' => '='
				),
				array(
					'key' => '_city_slug',
					'value' => $city_slug,
					'compare' => '='
				),
				array(
					'key' => '_seogen_hub_slug',
					'value' => $hub_slug,
					'compare' => '='
				),
			),
		);
		
		$existing_query = new WP_Query( $existing_args );
		
		if ( $existing_query->have_posts() ) {
			// City hub already exists for this hub+city, use it
			$existing_post = $existing_query->posts[0];
			$city_hub_map[ $hub_city_key ] = $existing_post->ID;
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] City hub already exists: ' . $hub_key . '|' . $city_slug . ' (ID: ' . $existing_post->ID . ')' . PHP_EOL, FILE_APPEND );
			wp_reset_postdata();
			continue;
		}
		wp_reset_postdata();
		
		// Build title: "Residential Roofing Services in Tulsa, OK | M Electric"
		// Format: "{hub_label} {trade_name} Services in {city}, {state} | {company_name}"
		$title = sprintf(
			'%s %s Services in %s, %s',
			$hub_label,
			$trade_name,
			$city_data['city'],
			$city_data['state']
		);
		
		if ( '' !== $company_name ) {
			$title .= ' | ' . $company_name;
		}
		
		// Create minimal placeholder page
		$postarr = array(
			'post_type' => 'service_page',
			'post_status' => 'draft',
			'post_title' => $title,
			'post_name' => $city_slug,
			'post_content' => '', // Empty content - will be filled later with AI
			'post_parent' => $service_hub_parent_id,
		);
		
		$post_id = wp_insert_post( $postarr, true );
		
		if ( is_wp_error( $post_id ) ) {
			file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Failed to create city hub placeholder: ' . $city_slug . ' - ' . $post_id->get_error_message() . PHP_EOL, FILE_APPEND );
			continue;
		}
		
		// Add meta fields to identify this as a city hub placeholder
		update_post_meta( $post_id, '_page_type', 'city_hub' );
		update_post_meta( $post_id, '_seogen_page_mode', 'city_hub' );
		update_post_meta( $post_id, '_city_slug', $city_slug );
		update_post_meta( $post_id, '_seogen_city_slug', $city_slug );
		update_post_meta( $post_id, '_seogen_city', $city_data['city'] . ', ' . $city_data['state'] );
		update_post_meta( $post_id, '_seogen_hub_slug', $hub_slug );
		update_post_meta( $post_id, '_seogen_hub_key', $hub_key );
		update_post_meta( $post_id, '_is_placeholder', '1' );
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		
		$city_hub_map[ $hub_city_key ] = $post_id;
		
		file_put_contents( WP_CONTENT_DIR . '/seogen-debug.log', '[' . date('Y-m-d H:i:s') . '] Created city hub placeholder: ' . $hub_key . '|' . $city_slug . ' (ID: ' . $post_id . ') - ' . $title . PHP_EOL, FILE_APPEND );
	}
	
	return $city_hub_map;
}
