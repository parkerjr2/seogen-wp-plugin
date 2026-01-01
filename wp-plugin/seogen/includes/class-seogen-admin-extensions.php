<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-seogen-service-hub-helpers.php';

trait SEOgen_Admin_Extensions {
	use SEOgen_Service_Hub_Helpers;

	private function get_business_config() {
		$config = get_option( self::BUSINESS_CONFIG_OPTION, array() );
		if ( ! is_array( $config ) ) {
			$config = array();
		}
		$defaults = array(
			'vertical' => '',
			'business_name' => '',
			'phone' => '',
			'cta_text' => 'Request a Free Estimate',
			'service_area_label' => '',
			'hubs' => array(),
		);
		return wp_parse_args( $config, $defaults );
	}

	private function get_services() {
		$services = get_option( self::SERVICES_CACHE_OPTION, array() );
		if ( ! is_array( $services ) ) {
			$services = array();
		}
		return $services;
	}

	private function get_hubs() {
		$config = $this->get_business_config();
		return isset( $config['hubs'] ) && is_array( $config['hubs'] ) ? $config['hubs'] : array();
	}

	private function get_cities() {
		$cities = get_option( 'hyper_local_cities_cache', array() );
		if ( ! is_array( $cities ) ) {
			$cities = array();
		}
		return $cities;
	}

	private function get_available_verticals() {
		return array(
			// Home Services
			'roofer' => 'Roofer',
			'electrician' => 'Electrician',
			'plumber' => 'Plumber',
			'hvac' => 'HVAC',
			'landscaper' => 'Landscaper',
			'handyman' => 'Handyman',
			'painter' => 'Painter',
			'concrete' => 'Concrete',
			'siding' => 'Siding',
			'locksmith' => 'Locksmith',
			'cleaning' => 'Cleaning',
			'garage-door' => 'Garage Door',
			'windows' => 'Windows',
			'pest-control' => 'Pest Control',
			// Single-City Verticals
			'barbershop' => 'Barbershop',
			'spa' => 'Spa',
			'dentist' => 'Dentist',
			'restaurant' => 'Restaurant',
			'other' => 'Other',
		);
	}

	private function get_default_hubs() {
		return array(
			array( 'key' => 'residential', 'label' => 'Residential', 'slug' => 'residential-services' ),
			array( 'key' => 'commercial', 'label' => 'Commercial', 'slug' => 'commercial-services' ),
			array( 'key' => 'emergency', 'label' => 'Emergency', 'slug' => 'emergency-services' ),
			array( 'key' => 'repair', 'label' => 'Repair', 'slug' => 'repair-services' ),
			array( 'key' => 'installation', 'label' => 'Installation', 'slug' => 'installation-services' ),
			array( 'key' => 'maintenance', 'label' => 'Maintenance', 'slug' => 'maintenance-services' ),
		);
	}

	public function render_business_setup_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = $this->get_business_config();
		$verticals = $this->get_available_verticals();
		$default_hubs = $this->get_default_hubs();
		
		// Get hub categories (vertical profile is now same as business type)
		$hub_categories = SEOgen_Vertical_Profiles::get_saved_hub_categories();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Business Setup (Step 0)', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Configure your business type and service hubs. This is required before generating pages.', 'seogen' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="business-setup-form">
				<?php wp_nonce_field( 'hyper_local_save_business_config', 'hyper_local_business_config_nonce' ); ?>
				<input type="hidden" name="action" value="hyper_local_save_business_config" />

				<table class="form-table">
					<tr>
						<th scope="row"><label for="vertical"><?php esc_html_e( 'Business Type / Vertical', 'seogen' ); ?> *</label></th>
						<td>
							<select name="vertical" id="vertical" required>
								<option value=""><?php esc_html_e( '-- Select Business Type --', 'seogen' ); ?></option>
								<?php foreach ( $verticals as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['vertical'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select your business type. Hub categories will adapt to your industry.', 'seogen' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="business_name"><?php esc_html_e( 'Business Name', 'seogen' ); ?></label></th>
						<td>
							<input type="text" name="business_name" id="business_name" class="regular-text" value="<?php echo esc_attr( $config['business_name'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="phone"><?php esc_html_e( 'Phone', 'seogen' ); ?></label></th>
						<td>
							<input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr( $config['phone'] ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cta_text"><?php esc_html_e( 'Primary CTA Text', 'seogen' ); ?></label></th>
						<td>
							<input type="text" name="cta_text" id="cta_text" class="regular-text" value="<?php echo esc_attr( $config['cta_text'] ); ?>" placeholder="Request a Free Estimate" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="service_area_label"><?php esc_html_e( 'Service Area Label (Optional)', 'seogen' ); ?></label></th>
						<td>
							<input type="text" name="service_area_label" id="service_area_label" class="regular-text" value="<?php echo esc_attr( $config['service_area_label'] ); ?>" placeholder="e.g., Tulsa Metro" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="email"><?php esc_html_e( 'Email (Optional)', 'seogen' ); ?></label></th>
						<td>
							<input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr( isset( $config['email'] ) ? $config['email'] : '' ); ?>" placeholder="contact@example.com" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="address"><?php esc_html_e( 'Address (Optional)', 'seogen' ); ?></label></th>
						<td>
							<input type="text" name="address" id="address" class="regular-text" value="<?php echo esc_attr( isset( $config['address'] ) ? $config['address'] : '' ); ?>" placeholder="123 Main St, City, ST 12345" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Hub Categories', 'seogen' ); ?> *</th>
						<td>
							<style>
								.hub-category-item {
									display: flex;
									align-items: center;
									gap: 10px;
									padding: 10px;
									background: #f9f9f9;
									border: 1px solid #ddd;
									margin-bottom: 5px;
									border-radius: 3px;
								}
								.hub-category-item .drag-handle {
									cursor: move;
									color: #999;
								}
								.hub-category-item .hub-category-label {
									flex: 1;
									min-width: 200px;
								}
								.hub-category-item button {
									margin: 0 2px;
								}
								#hub-categories-list {
									margin: 15px 0;
								}
								#new-hub-label {
									width: 300px;
									margin-right: 10px;
								}
							</style>
							
							<p><?php esc_html_e( 'Hub categories group your service hubs. For single-city verticals, keep these intent-based.', 'seogen' ); ?></p>
							
							<div id="hub-categories-list">
								<?php foreach ( $hub_categories as $index => $category ) : ?>
									<div class="hub-category-item" data-index="<?php echo esc_attr( $index ); ?>">
										<span class="dashicons dashicons-menu drag-handle"></span>
										
										<label>
											<input type="checkbox" 
												name="hub_categories[<?php echo esc_attr( $index ); ?>][enabled]" 
												value="1" 
												<?php checked( $category['enabled'] ); ?> />
										</label>
										
										<input type="text" 
											name="hub_categories[<?php echo esc_attr( $index ); ?>][label]" 
											value="<?php echo esc_attr( $category['label'] ); ?>" 
											class="hub-category-label" 
											required />
										
										<input type="hidden" 
											name="hub_categories[<?php echo esc_attr( $index ); ?>][key]" 
											value="<?php echo esc_attr( $category['key'] ); ?>" />
										
										<input type="hidden" 
											name="hub_categories[<?php echo esc_attr( $index ); ?>][sort_order]" 
											value="<?php echo esc_attr( $category['sort_order'] ); ?>" 
											class="hub-category-sort" />
										
										<input type="hidden" 
											name="hub_categories[<?php echo esc_attr( $index ); ?>][is_custom]" 
											value="<?php echo esc_attr( $category['is_custom'] ? '1' : '0' ); ?>" />
										
										<button type="button" class="button button-small hub-move-up">↑</button>
										<button type="button" class="button button-small hub-move-down">↓</button>
										
										<?php if ( $category['is_custom'] ) : ?>
											<button type="button" class="button button-small hub-delete">
												<?php esc_html_e( 'Delete', 'seogen' ); ?>
											</button>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
							
							<div style="margin-top: 15px;">
								<h4><?php esc_html_e( 'Add New Hub Category', 'seogen' ); ?></h4>
								<input type="text" id="new-hub-label" placeholder="<?php esc_attr_e( 'Category Label', 'seogen' ); ?>" />
								<button type="button" id="add-hub-category" class="button">
									<?php esc_html_e( '+ Add Hub Category', 'seogen' ); ?>
								</button>
							</div>
							
							<div style="margin-top: 15px;">
								<button type="button" id="reset-hub-categories" class="button">
									<?php esc_html_e( 'Reset to Defaults for This Vertical', 'seogen' ); ?>
								</button>
							</div>
							
							<?php if ( count( $hub_categories ) > 8 ) : ?>
								<p class="description" style="color: #d63638;">
									<?php esc_html_e( '⚠️ Warning: Too many categories (>8) can create thin pages.', 'seogen' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Business Configuration', 'seogen' ) ); ?>
			</form>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-services' ) ); ?>" class="button button-secondary">
					<?php echo esc_html__( 'Next Step: Services →', 'seogen' ); ?>
				</a>
			</p>
		
		<script>
		jQuery(document).ready(function($) {
			// Add new hub category
			$('#add-hub-category').on('click', function() {
				var label = $('#new-hub-label').val().trim();
				if (!label) {
					alert('Please enter a category label');
					return;
				}
				
				// AJAX call to add category
				$.post(ajaxurl, {
					action: 'seogen_save_hub_categories',
					nonce: '<?php echo wp_create_nonce( 'seogen_hub_categories' ); ?>',
					operation: 'add',
					label: label
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert('Error: ' + (response.data ? response.data.message : 'Unknown error'));
					}
				});
			});
			
			// Move up/down
			$('.hub-move-up').on('click', function() {
				var item = $(this).closest('.hub-category-item');
				var prev = item.prev('.hub-category-item');
				if (prev.length) {
					prev.before(item);
					updateSortOrder();
				}
			});
			
			$('.hub-move-down').on('click', function() {
				var item = $(this).closest('.hub-category-item');
				var next = item.next('.hub-category-item');
				if (next.length) {
					next.after(item);
					updateSortOrder();
				}
			});
			
			// Delete custom category
			$('.hub-delete').on('click', function() {
				if (!confirm('Are you sure you want to delete this category?')) {
					return;
				}
				$(this).closest('.hub-category-item').remove();
				updateSortOrder();
			});
			
			// Reset to defaults
			$('#reset-hub-categories').on('click', function() {
				if (!confirm('This will replace all categories with defaults for the selected business type. Continue?')) {
					return;
				}
				
				var vertical = $('#vertical').val();
				$.post(ajaxurl, {
					action: 'seogen_reset_hub_categories',
					nonce: '<?php echo wp_create_nonce( 'seogen_hub_categories' ); ?>',
					vertical: vertical
				}, function(response) {
					if (response.success) {
						location.reload();
					}
				});
			});
			
			// Business type change (also updates vertical profile)
			var originalVertical = $('#vertical').val();
			$('#vertical').on('change', function() {
				var newVertical = $(this).val();
				if (newVertical === originalVertical || !newVertical) {
					return;
				}
				
				// Check if this vertical has specific hub category defaults
				var verticalsWithDefaults = ['barbershop', 'spa', 'dentist', 'restaurant'];
				if (verticalsWithDefaults.indexOf(newVertical) === -1 && verticalsWithDefaults.indexOf(originalVertical) === -1) {
					// Both are home services types, no need to prompt
					originalVertical = newVertical;
					return;
				}
				
				var choice = confirm(
					'You changed the business type. Would you like to:\n\n' +
					'OK = Use default hub categories for ' + newVertical + '\n' +
					'Cancel = Keep current hub categories'
				);
				
				if (choice) {
					// User wants to use defaults - save and reload
					$.post(ajaxurl, {
						action: 'seogen_change_vertical',
						nonce: '<?php echo wp_create_nonce( 'seogen_hub_categories' ); ?>',
						vertical: newVertical,
						use_defaults: true
					}, function(response) {
						if (response.success) {
							location.reload();
						}
					});
				} else {
					// User wants to keep current categories - just save the vertical without reload
					$.post(ajaxurl, {
						action: 'seogen_change_vertical',
						nonce: '<?php echo wp_create_nonce( 'seogen_hub_categories' ); ?>',
						vertical: newVertical,
						use_defaults: false
					}, function(response) {
						// Don't reload, just update the original value
						originalVertical = newVertical;
					});
				}
			});
			
			function updateSortOrder() {
				$('.hub-category-item').each(function(index) {
					$(this).find('.hub-category-sort').val(index);
					$(this).attr('data-index', index);
					
					// Update field names to maintain proper indexing
					$(this).find('input, select').each(function() {
						var name = $(this).attr('name');
						if (name && name.indexOf('hub_categories[') === 0) {
							var newName = name.replace(/hub_categories\[\d+\]/, 'hub_categories[' + index + ']');
							$(this).attr('name', newName);
						}
					});
				});
			}
		});
		</script>
	</div>
	<?php
}

	public function handle_save_business_config() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_save_business_config', 'hyper_local_business_config_nonce' );

		// Save vertical profile (same as business type)
		if ( isset( $_POST['vertical'] ) ) {
			$vertical = sanitize_text_field( wp_unslash( $_POST['vertical'] ) );
			
			// Map business type to vertical profile for hub categories
			$vertical_profile_map = array(
				'roofer' => 'home_services',
				'electrician' => 'home_services',
				'plumber' => 'home_services',
				'hvac' => 'home_services',
				'landscaper' => 'home_services',
				'handyman' => 'home_services',
				'painter' => 'home_services',
				'concrete' => 'home_services',
				'siding' => 'home_services',
				'locksmith' => 'home_services',
				'cleaning' => 'home_services',
				'garage-door' => 'home_services',
				'windows' => 'home_services',
				'pest-control' => 'home_services',
				'barbershop' => 'barbershop',
				'spa' => 'spa',
				'dentist' => 'dentist',
				'restaurant' => 'restaurant',
				'other' => 'home_services',
			);
			
			$vertical_profile = isset( $vertical_profile_map[ $vertical ] ) ? $vertical_profile_map[ $vertical ] : 'home_services';
			SEOgen_Vertical_Profiles::set_vertical_profile( $vertical_profile );
		}

		// Save hub categories
		if ( isset( $_POST['hub_categories'] ) && is_array( $_POST['hub_categories'] ) ) {
			$categories = array();
			
			foreach ( $_POST['hub_categories'] as $cat_data ) {
				if ( ! is_array( $cat_data ) ) {
					continue;
				}
				
				$categories[] = array(
					'key'        => isset( $cat_data['key'] ) ? sanitize_key( $cat_data['key'] ) : '',
					'label'      => isset( $cat_data['label'] ) ? sanitize_text_field( wp_unslash( $cat_data['label'] ) ) : '',
					'enabled'    => ! empty( $cat_data['enabled'] ),
					'sort_order' => isset( $cat_data['sort_order'] ) ? absint( $cat_data['sort_order'] ) : 0,
					'is_custom'  => ! empty( $cat_data['is_custom'] ),
				);
			}
			
			SEOgen_Vertical_Profiles::save_hub_categories( $categories );
			update_option( 'seogen_hub_categories_source', 'customized' );
			
			// Also update legacy hubs format for backward compatibility
			$legacy_hubs = array();
			foreach ( $categories as $cat ) {
				if ( $cat['enabled'] ) {
					$legacy_hubs[] = array(
						'key'   => $cat['key'],
						'label' => $cat['label'],
						'slug'  => $cat['key'] . '-services',
					);
				}
			}
		}

		$config = array(
			'vertical' => isset( $_POST['vertical'] ) ? sanitize_text_field( wp_unslash( $_POST['vertical'] ) ) : '',
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'cta_text' => isset( $_POST['cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_text'] ) ) : 'Request a Free Estimate',
			'service_area_label' => isset( $_POST['service_area_label'] ) ? sanitize_text_field( wp_unslash( $_POST['service_area_label'] ) ) : '',
			'email' => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'address' => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
			'hubs' => isset( $legacy_hubs ) ? $legacy_hubs : array(),
		);

		update_option( self::BUSINESS_CONFIG_OPTION, $config );

		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-business-setup',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( 'Business configuration saved successfully.' ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_services_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = $this->get_business_config();
		$services = $this->get_services();
		
		// Migrate old 'category' field to 'hub_key' when rendering
		foreach ( $services as &$service ) {
			if ( isset( $service['category'] ) && ! isset( $service['hub_key'] ) ) {
				$service['hub_key'] = $service['category'];
			}
		}
		unset( $service );
		
		$hubs = isset( $config['hubs'] ) ? $config['hubs'] : array();

		if ( empty( $config['vertical'] ) || empty( $hubs ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Services', 'seogen' ); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Please complete Business Setup first before configuring services.', 'seogen' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Services & Cities', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Configure the services your business offers and the cities you serve.', 'seogen' ); ?></p>
			
			<style>
				.hl-section {
					margin-bottom: 40px;
					padding: 20px;
					background: #fff;
					border: 1px solid #c3c4c7;
					box-shadow: 0 1px 1px rgba(0,0,0,.04);
				}
				.hl-section h2 {
					margin-top: 0;
					border-bottom: 2px solid #2271b1;
					padding-bottom: 10px;
					margin-bottom: 15px;
					font-size: 1.3em;
				}
				.hl-section h3 {
					margin-top: 25px;
					margin-bottom: 10px;
					font-size: 1.1em;
				}
				.hl-section .wp-list-table {
					table-layout: fixed;
					width: 100%;
				}
				.hl-section .wp-list-table th,
				.hl-section .wp-list-table td {
					word-wrap: break-word;
					overflow-wrap: break-word;
					padding: 10px 12px;
				}
				.hl-section .wp-list-table input[type="text"],
				.hl-section .wp-list-table select {
					width: 98%;
					max-width: 100%;
					box-sizing: border-box;
				}
				.hl-services-table th:nth-child(1),
				.hl-services-table td:nth-child(1) { width: 30%; }
				.hl-services-table th:nth-child(2),
				.hl-services-table td:nth-child(2) { width: 30%; }
				.hl-services-table th:nth-child(3),
				.hl-services-table td:nth-child(3) { width: 25%; }
				.hl-services-table th:nth-child(4),
				.hl-services-table td:nth-child(4) { width: 15%; text-align: center; }
				.hl-cities-table th:nth-child(1),
				.hl-cities-table td:nth-child(1) { width: 45%; }
				.hl-cities-table th:nth-child(2),
				.hl-cities-table td:nth-child(2) { width: 40%; }
				.hl-cities-table th:nth-child(3),
				.hl-cities-table td:nth-child(3) { width: 15%; text-align: center; }
				.hl-section-divider {
					border: 0;
					border-top: 1px solid #dcdcde;
					margin: 25px 0;
				}
			</style>
			
			<!-- SERVICES SECTION -->
			<div class="hl-section">
				<h2><?php esc_html_e( 'Services', 'seogen' ); ?></h2>
				<p><?php esc_html_e( 'Configure the services your business offers, grouped by hub category.', 'seogen' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hyper_local_save_services', 'hyper_local_services_nonce' ); ?>
				<input type="hidden" name="action" value="hyper_local_save_services" />

				<h3><?php esc_html_e( 'Current Services', 'seogen' ); ?></h3>
				<table class="wp-list-table widefat fixed striped hl-services-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Service Name', 'seogen' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'seogen' ); ?></th>
							<th><?php esc_html_e( 'Hub Category', 'seogen' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'seogen' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! empty( $services ) ) : ?>
							<?php foreach ( $services as $idx => $service ) : ?>
								<tr>
									<td>
										<input type="text" name="services[<?php echo esc_attr( $idx ); ?>][name]" value="<?php echo esc_attr( $service['name'] ); ?>" class="regular-text" required />
									</td>
									<td>
										<input type="text" name="services[<?php echo esc_attr( $idx ); ?>][slug]" value="<?php echo esc_attr( $service['slug'] ); ?>" class="regular-text" required />
									</td>
									<td>
										<select name="services[<?php echo esc_attr( $idx ); ?>][hub_key]" required>
											<?php foreach ( $hubs as $hub ) : ?>
												<option value="<?php echo esc_attr( $hub['key'] ); ?>" <?php selected( $service['hub_key'], $hub['key'] ); ?>><?php echo esc_html( $hub['label'] ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_delete_service&index=' . $idx ), 'hyper_local_delete_service_' . $idx, 'nonce' ) ); ?>" 
										   class="button button-small" 
										   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this service?', 'seogen' ); ?>');">
											<?php esc_html_e( 'Delete', 'seogen' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'No services configured yet. Add services using the bulk add feature below.', 'seogen' ); ?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>

				<h3><?php esc_html_e( 'Bulk Add Services', 'seogen' ); ?></h3>
				<p><?php esc_html_e( 'Add multiple services at once. Format: "hub_key: Service Name" (one per line). If hub_key is omitted, the first hub will be used.', 'seogen' ); ?></p>
				<textarea name="bulk_services" rows="10" class="large-text" placeholder="residential: Outlet Installation&#10;commercial: Panel Upgrade&#10;Lighting Repair"></textarea>

				<?php submit_button( __( 'Save Services', 'seogen' ) ); ?>
			</form>
		</div>
		
		<!-- CITIES SECTION -->
		<div class="hl-section">
			<h2><?php esc_html_e( 'Cities', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Add and manage the cities your business serves.', 'seogen' ); ?></p>
				
				<?php
				$cities = get_option( 'hyper_local_cities_cache', array() );
				?>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'hyper_local_save_cities', 'hyper_local_cities_nonce' ); ?>
					<input type="hidden" name="action" value="hyper_local_save_cities" />
					
					<h3><?php esc_html_e( 'Current Cities', 'seogen' ); ?></h3>
					<table class="wp-list-table widefat fixed striped hl-cities-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'City Name', 'seogen' ); ?></th>
								<th><?php esc_html_e( 'State', 'seogen' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'seogen' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( ! empty( $cities ) ) : ?>
								<?php foreach ( $cities as $idx => $city ) : ?>
									<tr>
										<td>
											<input type="text" name="cities[<?php echo esc_attr( $idx ); ?>][name]" value="<?php echo esc_attr( $city['name'] ); ?>" class="regular-text" required />
										</td>
										<td>
											<input type="text" name="cities[<?php echo esc_attr( $idx ); ?>][state]" value="<?php echo esc_attr( $city['state'] ); ?>" class="regular-text" required />
										</td>
										<td>
											<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hyper_local_delete_city&index=' . $idx ), 'hyper_local_delete_city_' . $idx, 'nonce' ) ); ?>" 
											   class="button button-small" 
											   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this city?', 'seogen' ); ?>');"><?php esc_html_e( 'Delete', 'seogen' ); ?></a>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="3"><?php esc_html_e( 'No cities configured yet. Add cities using the bulk add feature below.', 'seogen' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
					
					<hr class="hl-section-divider" />
					
					<h3><?php esc_html_e( 'Bulk Add Cities', 'seogen' ); ?></h3>
					<p><?php esc_html_e( 'Add multiple cities at once. Format: "City Name, State" (one per line).', 'seogen' ); ?></p>
					<textarea name="bulk_cities" rows="10" class="large-text" placeholder="Austin, TX&#10;Dallas, TX&#10;Houston, TX"></textarea>
					
				<?php submit_button( __( 'Save Cities', 'seogen' ) ); ?>
			</form>
		</div>
		
		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-service-hubs' ) ); ?>" class="button button-secondary">
				<?php echo esc_html__( 'Next Step: Service Hubs →', 'seogen' ); ?>
			</a>
		</p>
		</div>
		<?php
	}

	public function handle_save_services() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_save_services', 'hyper_local_services_nonce' );

		$config = $this->get_business_config();
		$hubs = isset( $config['hubs'] ) ? $config['hubs'] : array();
		$default_hub_key = ! empty( $hubs ) ? $hubs[0]['key'] : 'residential';

		// Migrate old services cache from 'category' to 'hub_key' and add hub_label
		$old_services = get_option( self::SERVICES_CACHE_OPTION, array() );
		$needs_update = false;
		if ( ! empty( $old_services ) && is_array( $old_services ) ) {
			foreach ( $old_services as &$service ) {
				// Migrate 'category' to 'hub_key'
				if ( isset( $service['category'] ) && ! isset( $service['hub_key'] ) ) {
					$service['hub_key'] = $service['category'];
					unset( $service['category'] );
					$needs_update = true;
				}
				
				// Add hub_label if missing
				if ( isset( $service['hub_key'] ) && ! isset( $service['hub_label'] ) ) {
					$hub_label = ucfirst( $service['hub_key'] );
					foreach ( $hubs as $hub ) {
						if ( isset( $hub['key'] ) && $hub['key'] === $service['hub_key'] && isset( $hub['label'] ) ) {
							$hub_label = $hub['label'];
							break;
						}
					}
					$service['hub_label'] = $hub_label;
					$needs_update = true;
				}
			}
			if ( $needs_update ) {
				update_option( self::SERVICES_CACHE_OPTION, $old_services );
			}
		}

		$services = array();

		if ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
			foreach ( $_POST['services'] as $service_data ) {
				if ( isset( $service_data['name'], $service_data['slug'], $service_data['hub_key'] ) ) {
					// Find hub label from config
					$hub_label = ucfirst( $service_data['hub_key'] );
					foreach ( $hubs as $hub ) {
						if ( isset( $hub['key'] ) && $hub['key'] === $service_data['hub_key'] && isset( $hub['label'] ) ) {
							$hub_label = $hub['label'];
							break;
						}
					}
					
					$services[] = array(
						'name' => sanitize_text_field( $service_data['name'] ),
						'slug' => sanitize_title( $service_data['slug'] ),
						'hub_key' => sanitize_text_field( $service_data['hub_key'] ),
						'hub_label' => $hub_label,
					);
				}
			}
		}

		if ( isset( $_POST['bulk_services'] ) && '' !== trim( $_POST['bulk_services'] ) ) {
			$lines = explode( "\n", wp_unslash( $_POST['bulk_services'] ) );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}

				$hub_key = $default_hub_key;
				$service_name = $line;

				if ( strpos( $line, ':' ) !== false ) {
					list( $hub_key, $service_name ) = explode( ':', $line, 2 );
					$hub_key = trim( $hub_key );
					$service_name = trim( $service_name );
				}

				// Find hub label from config
				$hub_label = ucfirst( $hub_key );
				foreach ( $hubs as $hub ) {
					if ( isset( $hub['key'] ) && $hub['key'] === $hub_key && isset( $hub['label'] ) ) {
						$hub_label = $hub['label'];
						break;
					}
				}

				$services[] = array(
					'name' => sanitize_text_field( $service_name ),
					'slug' => sanitize_title( $service_name ),
					'hub_key' => sanitize_text_field( $hub_key ),
					'hub_label' => $hub_label,
				);
			}
		}

		update_option( self::SERVICES_CACHE_OPTION, $services );

		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-services',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( 'Services saved successfully.' ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_delete_service() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		$index = isset( $_GET['index'] ) ? intval( $_GET['index'] ) : -1;
		
		if ( $index < 0 ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-services',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Invalid service index.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		check_admin_referer( 'hyper_local_delete_service_' . $index, 'nonce' );

		$services = $this->get_services();
		
		if ( ! isset( $services[ $index ] ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-services',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Service not found.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$deleted_service_name = $services[ $index ]['name'];
		
		// Remove the service at the specified index
		array_splice( $services, $index, 1 );
		
		// Re-index the array to maintain sequential keys
		$services = array_values( $services );
		
		update_option( self::SERVICES_CACHE_OPTION, $services );

		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-services',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( 'Service "' . $deleted_service_name . '" deleted successfully.' ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_service_hubs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$config = $this->get_business_config();
		$services = $this->get_services();
		$hubs = isset( $config['hubs'] ) ? $config['hubs'] : array();

		if ( empty( $config['vertical'] ) || empty( $hubs ) ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Service Hubs (Step 3.5)', 'seogen' ); ?></h1>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Please complete Business Setup first before creating hub pages.', 'seogen' ); ?></p>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Service Hubs (Step 3.5)', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Create or update service hub pages. These are top-level pages that link to all service+city pages in that category.', 'seogen' ); ?></p>
			
			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Permalink Strategy:', 'seogen' ); ?></strong> <?php esc_html_e( 'Service Pages are created under /service-area/ by default (e.g., /service-area/residential-services/). If a WordPress Page already exists with the same slug at root level, it will take precedence and your hub page will only be accessible via the /service-area/ URL.', 'seogen' ); ?></p>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Hub Label', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'URL', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Services Count', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'seogen' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $hubs as $hub ) : ?>
						<?php
						$hub_services = array_filter( $services, function( $service ) use ( $hub ) {
							return isset( $service['hub_key'] ) && $service['hub_key'] === $hub['key'];
						} );
						$hub_services_count = count( $hub_services );

						$existing_hub_page = $this->find_hub_page( $hub['key'] );
						$hub_permalink = $existing_hub_page ? get_permalink( $existing_hub_page->ID ) : '—';
						
						// Check for slug conflicts
						$has_conflict = false;
						if ( $existing_hub_page ) {
							$conflicting_page = get_posts( array(
								'post_type' => 'page',
								'name' => $hub['slug'],
								'posts_per_page' => 1,
								'post_status' => 'any',
							) );
							if ( ! empty( $conflicting_page ) && (int) $conflicting_page[0]->ID !== (int) $existing_hub_page->ID ) {
								$is_our_page = get_post_meta( $conflicting_page[0]->ID, '_hyper_local_managed', true ) === '1';
								if ( ! $is_our_page ) {
									$has_conflict = true;
								}
							}
						}
						?>
						<tr<?php echo $has_conflict ? ' style="background-color: #fff3cd;"' : ''; ?>>
							<td><strong><?php echo esc_html( $hub['label'] ); ?></strong></td>
							<td><code><?php echo esc_html( $hub['slug'] ); ?></code></td>
							<td>
								<?php if ( $existing_hub_page ) : ?>
									<a href="<?php echo esc_url( $hub_permalink ); ?>" target="_blank"><?php echo esc_html( $hub_permalink ); ?></a>
									<?php if ( $has_conflict ) : ?>
										<br><span style="color: #856404;">⚠️ <?php esc_html_e( 'Slug conflict detected', 'seogen' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<em><?php esc_html_e( 'Not created yet', 'seogen' ); ?></em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $hub_services_count ); ?> services</td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
									<?php wp_nonce_field( 'hyper_local_hub_preview', 'hyper_local_hub_preview_nonce' ); ?>
									<input type="hidden" name="action" value="hyper_local_hub_preview" />
									<input type="hidden" name="hub_key" value="<?php echo esc_attr( $hub['key'] ); ?>" />
									<button type="submit" class="button"><?php esc_html_e( 'Preview Hub', 'seogen' ); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
									<?php wp_nonce_field( 'hyper_local_hub_create', 'hyper_local_hub_create_nonce' ); ?>
									<input type="hidden" name="action" value="hyper_local_hub_create" />
									<input type="hidden" name="hub_key" value="<?php echo esc_attr( $hub['key'] ); ?>" />
									<button type="submit" class="button button-primary"><?php echo $existing_hub_page ? esc_html__( 'Update Hub Page', 'seogen' ) : esc_html__( 'Create Hub Page', 'seogen' ); ?></button>
								</form>
								<?php if ( $existing_hub_page ) : ?>
									<a href="<?php echo esc_url( get_permalink( $existing_hub_page->ID ) ); ?>" class="button" target="_blank"><?php esc_html_e( 'View Page', 'seogen' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top: 20px;">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-bulk' ) ); ?>" class="button button-secondary">
					<?php echo esc_html__( 'Next Step: Generate Service Pages →', 'seogen' ); ?>
				</a>
			</p>
			
			<!-- Progress Indicator -->
			<div id="hub_generation_progress" style="display:none; margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #2271b1;">
				<p><strong>Generating Service Hub Page...</strong></p>
				<p id="hub_progress_status">Contacting API and generating content...</p>
				<div style="background: #f0f0f1; height: 30px; border-radius: 3px; overflow: hidden; margin: 10px 0;">
					<div id="hub_progress_bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
				</div>
				<p style="font-size: 12px; color: #666;">This may take 10-30 seconds. Please wait...</p>
			</div>
			
			<script>
			jQuery(document).ready(function($) {
				// Intercept form submissions for hub creation/update
				$('form[action*="admin-post.php"]').on('submit', function(e) {
					var action = $(this).find('input[name="action"]').val();
					
					// Only show progress for create/update actions, not preview
					if (action === 'hyper_local_hub_create') {
						var button = $(this).find('button[type="submit"]');
						var originalText = button.text();
						
						// Show progress indicator
						$('#hub_generation_progress').show();
						$('#hub_progress_status').text('Generating hub page content...');
						
						// Animate progress bar
						var progress = 0;
						var progressInterval = setInterval(function() {
							progress += 2;
							if (progress > 90) {
								clearInterval(progressInterval);
							}
							$('#hub_progress_bar').css('width', progress + '%');
						}, 300);
						
						// Disable button and show loading state
						button.prop('disabled', true).text('Generating...');
						
						// Store interval ID so we can clear it if needed
						$(this).data('progressInterval', progressInterval);
					}
				});
			});
			</script>
		</div>
		<?php
	}

	private function find_hub_page( $hub_key ) {
		$args = array(
			'post_type' => 'service_page',
			'post_status' => 'any',
			'posts_per_page' => 1,
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_hub',
				),
				array(
					'key' => '_seogen_hub_key',
					'value' => $hub_key,
				),
			),
		);
		$query = new WP_Query( $args );
		return $query->have_posts() ? $query->posts[0] : null;
	}

	public function handle_hub_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_hub_preview', 'hyper_local_hub_preview_nonce' );

		$hub_key = isset( $_POST['hub_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_key'] ) ) : '';
		if ( '' === $hub_key ) {
			wp_die( 'Invalid hub key' );
		}

		$config = $this->get_business_config();
		$services = $this->get_services();
		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		
		if ( empty( $license_key ) ) {
			wp_die( 'License key is not configured. Please go to Settings and enter your license key.' );
		}

		$hub = null;
		foreach ( $config['hubs'] as $h ) {
			if ( $h['key'] === $hub_key ) {
				$hub = $h;
				break;
			}
		}

		if ( ! $hub ) {
			wp_die( 'Hub not found' );
		}

		$hub_services = array_filter( $services, function( $service ) use ( $hub_key ) {
			return isset( $service['hub_key'] ) && $service['hub_key'] === $hub_key;
		} );

		$services_for_hub = array_map( function( $service ) {
			return array(
				'name' => $service['name'],
				'slug' => $service['slug'],
			);
		}, array_values( $hub_services ) );

		$payload = array(
			'license_key' => $license_key,
			'data' => array(
				'page_mode' => 'service_hub',
				'vertical' => $config['vertical'],
				'business_name' => $config['business_name'],
				'phone' => $config['phone'],
				'cta_text' => $config['cta_text'],
				'service_area_label' => $config['service_area_label'],
				'hub_key' => $hub['key'],
				'hub_label' => $hub['label'],
				'hub_slug' => $hub['slug'],
				'services_for_hub' => $services_for_hub,
			),
			'preview' => true,
		);

		$response = wp_remote_post( $api_url . '/generate-page', array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( $payload ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			wp_die( 'API Error: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = is_array( $data ) && isset( $data['detail'] ) ? $data['detail'] : $body;
			wp_die( 'API Error (HTTP ' . $status_code . '): ' . esc_html( $error_message ) );
		}

		if ( ! is_array( $data ) ) {
			wp_die( 'Invalid API response: ' . esc_html( substr( $body, 0, 500 ) ) );
		}

		$preview_key = 'hyper_local_hub_preview_' . wp_generate_password( 12, false );
		set_transient( $preview_key, $data, 300 );

		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-service-hubs',
			'hl_preview' => $preview_key,
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function handle_hub_create() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_hub_create', 'hyper_local_hub_create_nonce' );

		$hub_key = isset( $_POST['hub_key'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_key'] ) ) : '';
		if ( '' === $hub_key ) {
			wp_die( 'Invalid hub key' );
		}

		$config = $this->get_business_config();
		$services = $this->get_services();
		$settings = $this->get_settings();
		$api_url = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';
		
		if ( empty( $license_key ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'License key is not configured. Please go to Settings and enter your license key.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$hub = null;
		foreach ( $config['hubs'] as $h ) {
			if ( $h['key'] === $hub_key ) {
				$hub = $h;
				break;
			}
		}

		if ( ! $hub ) {
			wp_die( 'Hub not found' );
		}

		$hub_services = array_filter( $services, function( $service ) use ( $hub_key ) {
			return isset( $service['hub_key'] ) && $service['hub_key'] === $hub_key;
		} );

		$services_for_hub = array_map( function( $service ) {
			return array(
				'name' => $service['name'],
				'slug' => $service['slug'],
			);
		}, array_values( $hub_services ) );

		$payload = array(
			'license_key' => $license_key,
			'data' => array(
				'page_mode' => 'service_hub',
				'vertical' => $config['vertical'],
				'business_name' => $config['business_name'],
				'phone' => $config['phone'],
				'cta_text' => $config['cta_text'],
				'service_area_label' => $config['service_area_label'],
				'hub_key' => $hub['key'],
				'hub_label' => $hub['label'],
				'hub_slug' => $hub['slug'],
				'services_for_hub' => $services_for_hub,
			),
			'preview' => false,
		);

		$response = wp_remote_post( $api_url . '/generate-page', array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body' => wp_json_encode( $payload ),
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'API Error: ' . $response->get_error_message() ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_message = is_array( $data ) && isset( $data['detail'] ) ? $data['detail'] : $body;
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'API Error (HTTP ' . $status_code . '): ' . $error_message ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! is_array( $data ) || ! isset( $data['title'], $data['blocks'] ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Invalid API response: ' . substr( $body, 0, 200 ) ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Set up transient with context so build_gutenberg_content_from_blocks works correctly
		// This is required because the method pulls context from transient (designed for service+city pages)
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$last_preview_key = 'hyper_local_last_preview_' . $user_id;
			$preview_data = array(
				'title' => $data['title'],
				'slug' => $hub['slug'],
				'blocks' => $data['blocks'],
				'inputs' => array(
					'company_name' => $config['business_name'],
					'phone' => $config['phone'],
					'service' => $hub['label'] . ' Services',  // For hero context
					'city' => '',  // Hub pages don't have city
					'state' => '',  // Hub pages don't have state
				),
			);
			set_transient( $last_preview_key, $preview_data, 30 * MINUTE_IN_SECONDS );
		}

		$page_mode = isset( $data['page_mode'] ) ? $data['page_mode'] : 'service_hub';
		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $data['blocks'], $page_mode );

		// Apply Service Hub quality improvements (FAQ deduplication, city link rules, framing, heading variation)
		$gutenberg_markup = $this->apply_service_hub_quality_improvements( $gutenberg_markup, $hub['label'] );

		// Prepend header template if configured (same as service+city pages)
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				// Add CSS to remove top spacing from content area (same as service+city)
				$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $css_block . $header_content . $gutenberg_markup;
			}
		}

		// Append footer template if configured (same as service+city pages)
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				// Add CSS to remove bottom spacing from content area (same as service+city)
				$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
				$gutenberg_markup = $gutenberg_markup . $footer_css_block . $footer_content;
			}
		}

		$existing_hub_page = $this->find_hub_page( $hub_key );

		$post_data = array(
			'post_title' => $data['title'],
			'post_content' => $gutenberg_markup,
			'post_status' => 'publish',
			'post_type' => 'service_page',
			'post_name' => sanitize_title( $hub['slug'] ),
		);

		if ( $existing_hub_page ) {
			$post_data['ID'] = $existing_hub_page->ID;
			$post_id = wp_update_post( $post_data );
		} else {
			$post_id = wp_insert_post( $post_data );
		}

		if ( is_wp_error( $post_id ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Failed to create/update post: ' . $post_id->get_error_message() ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Verify post was created with content
		$created_post = get_post( $post_id );
		if ( ! $created_post ) {
			error_log( sprintf(
				'[HyperLocal] ERROR: Hub post creation failed - post_id=%d not found after insert/update',
				$post_id
			) );
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Post created but could not be retrieved. Check error logs.' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$content_length = strlen( $created_post->post_content );
		if ( $content_length < 50 ) {
			error_log( sprintf(
				'[HyperLocal] WARNING: Hub post has minimal content - post_id=%d, hub_slug=%s, content_length=%d, response_blocks=%d',
				$post_id,
				$hub['slug'],
				$content_length,
				isset( $data['blocks'] ) ? count( $data['blocks'] ) : 0
			) );
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'warning',
				'hl_msg' => rawurlencode( sprintf( 'Hub page created but content is very short (%d chars). Check error logs.', $content_length ) ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Standard meta fields (same as service+city pages)
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		update_post_meta( $post_id, '_hl_page_type', 'service_hub' );
		update_post_meta( $post_id, '_seogen_page_mode', 'service_hub' );
		update_post_meta( $post_id, '_seogen_vertical', $config['vertical'] );
		update_post_meta( $post_id, '_seogen_hub_key', $hub['key'] );
		update_post_meta( $post_id, '_seogen_hub_slug', $hub['slug'] );
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $data ) );
		update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );

		// SEO meta - use apply_seo_plugin_meta for consistency with service+city pages
		$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';
		$title = $data['title'];
		
		// Get trade name from vertical for focus keyword
		$vertical = isset( $config['vertical'] ) ? strtolower( $config['vertical'] ) : 'electrician';
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
			'painter' => 'Painting',
			'painting' => 'Painting',
			'concrete' => 'Concrete',
			'siding' => 'Siding',
			'locksmith' => 'Locksmith Services',
			'cleaning' => 'Cleaning Services',
			'garage-door' => 'Garage Door',
			'garage door' => 'Garage Door',
			'windows' => 'Window Services',
		);
		$trade_name = isset( $trade_name_map[ $vertical ] ) ? $trade_name_map[ $vertical ] : 'Services';
		
		// Focus keyword: "Commercial Electrical" not just "Commercial Services"
		$focus_keyword = $hub['label'] . ' ' . $trade_name;
		
		// Ensure meta description follows Google best practices (150-160 chars)
		// Formula: Primary service. Key benefit or differentiator. Trust signal or CTA.
		if ( empty( $meta_description ) || strlen( $meta_description ) < 100 ) {
			// Generate better meta description following Google best practices
			$meta_description = sprintf(
				'Expert %s %s services. Licensed professionals, quality workmanship, and reliable service. %s',
				strtolower( $hub['label'] ),
				strtolower( $trade_name ),
				isset( $config['cta_text'] ) ? $config['cta_text'] : 'Contact us today'
			);
		}
		
		// Trim to optimal length (150-160 chars for desktop)
		if ( strlen( $meta_description ) > 160 ) {
			$meta_description = substr( $meta_description, 0, 157 ) . '...';
		}
		
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		$this->apply_seo_plugin_meta( $post_id, $focus_keyword, $title, $meta_description, true );

		// Apply page builder settings to disable theme header/footer if configured (same as service+city)
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}

		// Get actual permalink for the created hub page
		$actual_permalink = get_permalink( $post_id );
		$actual_post_type = get_post_type( $post_id );
		
		// Debug logging with verification
		error_log( sprintf(
			'[HyperLocal] Created/updated hub page: post_id=%d, requested_post_type=service_page, actual_post_type=%s, _hl_page_type=service_hub, hub_key=%s, content_length=%d, permalink=%s',
			$post_id,
			$actual_post_type,
			$hub['key'],
			$content_length,
			$actual_permalink
		) );
		
		// Verify post_type is correct
		if ( $actual_post_type !== 'service_page' ) {
			error_log( sprintf(
				'[HyperLocal] ERROR: Hub page has wrong post_type! Expected service_page, got %s. This will cause layout issues.',
				$actual_post_type
			) );
		}
		
		// Check for slug conflicts with existing WP Pages
		$slug_conflict = false;
		$conflicting_page = get_posts( array(
			'post_type' => 'page',
			'name' => $hub['slug'],
			'posts_per_page' => 1,
			'post_status' => 'any',
		) );
		
		if ( ! empty( $conflicting_page ) ) {
			$conflict_post = $conflicting_page[0];
			// Only a conflict if it's NOT our managed page
			if ( (int) $conflict_post->ID !== (int) $post_id ) {
				$is_our_page = get_post_meta( $conflict_post->ID, '_hyper_local_managed', true ) === '1';
				if ( ! $is_our_page ) {
					$slug_conflict = true;
					error_log( sprintf(
						'[HyperLocal] WARNING: Slug conflict detected! WP Page (ID=%d) exists at /%s/ which will override hub URL. Hub is actually at: %s',
						$conflict_post->ID,
						$hub['slug'],
						$actual_permalink
					) );
				}
			}
		}

		$action_text = $existing_hub_page ? 'updated' : 'created';
		$notice_type = 'created';
		$notice_msg = 'Hub page ' . $action_text . ' successfully: ' . $data['title'];
		
		if ( $slug_conflict ) {
			$notice_type = 'warning';
			$notice_msg = sprintf(
				'Hub page %s, but a WordPress Page already exists at /%s/ which will override the hub URL. Your hub is accessible at: %s. Please delete or rename the conflicting Page, or the hub will not be accessible at the root-level URL.',
				$action_text,
				$hub['slug'],
				$actual_permalink
			);
		}
		
		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-service-hubs',
			'hl_notice' => $notice_type,
			'hl_msg' => rawurlencode( $notice_msg ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}
	
	public function render_service_hubs_page_footer() {
		?>
		<p style="margin-top: 20px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-bulk' ) ); ?>" class="button button-secondary">
				<?php echo esc_html__( 'Next Step: Generate Service Pages →', 'seogen' ); ?>
			</a>
		</p>
		<?php
	}
}
