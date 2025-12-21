<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Admin_Extensions {

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

	private function get_available_verticals() {
		return array(
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Business Setup (Step 0)', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Configure your business type and service hubs. This is required before generating pages.', 'seogen' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
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
						<th scope="row"><?php esc_html_e( 'Hub Categories', 'seogen' ); ?> *</th>
						<td>
							<p><?php esc_html_e( 'Select the service hub categories for your business:', 'seogen' ); ?></p>
							<?php foreach ( $default_hubs as $hub ) : ?>
								<?php
								$checked = false;
								if ( ! empty( $config['hubs'] ) ) {
									foreach ( $config['hubs'] as $saved_hub ) {
										if ( isset( $saved_hub['key'] ) && $saved_hub['key'] === $hub['key'] ) {
											$checked = true;
											break;
										}
									}
								}
								?>
								<label style="display: block; margin-bottom: 8px;">
									<input type="checkbox" name="hubs[]" value="<?php echo esc_attr( json_encode( $hub ) ); ?>" <?php checked( $checked ); ?> />
									<?php echo esc_html( $hub['label'] ); ?> (<?php echo esc_html( $hub['slug'] ); ?>)
								</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Select at least one hub category. You can add custom hubs later.', 'seogen' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Business Configuration', 'seogen' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function handle_save_business_config() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		check_admin_referer( 'hyper_local_save_business_config', 'hyper_local_business_config_nonce' );

		$config = array(
			'vertical' => isset( $_POST['vertical'] ) ? sanitize_text_field( wp_unslash( $_POST['vertical'] ) ) : '',
			'business_name' => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
			'phone' => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'cta_text' => isset( $_POST['cta_text'] ) ? sanitize_text_field( wp_unslash( $_POST['cta_text'] ) ) : 'Request a Free Estimate',
			'service_area_label' => isset( $_POST['service_area_label'] ) ? sanitize_text_field( wp_unslash( $_POST['service_area_label'] ) ) : '',
			'hubs' => array(),
		);

		if ( isset( $_POST['hubs'] ) && is_array( $_POST['hubs'] ) ) {
			foreach ( $_POST['hubs'] as $hub_json ) {
				$hub = json_decode( stripslashes( $hub_json ), true );
				if ( is_array( $hub ) && isset( $hub['key'], $hub['label'], $hub['slug'] ) ) {
					$config['hubs'][] = $hub;
				}
			}
		}

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
			<h1><?php esc_html_e( 'Services', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Configure the services your business offers, grouped by hub category.', 'seogen' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hyper_local_save_services', 'hyper_local_services_nonce' ); ?>
				<input type="hidden" name="action" value="hyper_local_save_services" />

				<h2><?php esc_html_e( 'Current Services', 'seogen' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Service Name', 'seogen' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'seogen' ); ?></th>
							<th><?php esc_html_e( 'Hub Category', 'seogen' ); ?></th>
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
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr>
								<td colspan="3"><?php esc_html_e( 'No services configured yet. Add services using the bulk add feature below.', 'seogen' ); ?></td>
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

		$services = array();

		if ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
			foreach ( $_POST['services'] as $service_data ) {
				if ( isset( $service_data['name'], $service_data['slug'], $service_data['hub_key'] ) ) {
					$services[] = array(
						'name' => sanitize_text_field( $service_data['name'] ),
						'slug' => sanitize_title( $service_data['slug'] ),
						'hub_key' => sanitize_text_field( $service_data['hub_key'] ),
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

				$services[] = array(
					'name' => sanitize_text_field( $service_name ),
					'slug' => sanitize_title( $service_name ),
					'hub_key' => sanitize_text_field( $hub_key ),
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

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Hub Label', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'seogen' ); ?></th>
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
						?>
						<tr>
							<td><strong><?php echo esc_html( $hub['label'] ); ?></strong></td>
							<td><code><?php echo esc_html( $hub['slug'] ); ?></code></td>
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

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			wp_die( 'Invalid API response' );
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

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['title'], $data['blocks'] ) ) {
			wp_redirect( add_query_arg( array(
				'page' => 'hyper-local-service-hubs',
				'hl_notice' => 'error',
				'hl_msg' => rawurlencode( 'Invalid API response' ),
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$gutenberg_markup = $this->build_gutenberg_content_from_blocks( $data['blocks'] );

		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$header_content = $this->get_template_content( $header_template_id );
			if ( '' !== $header_content ) {
				$gutenberg_markup = $header_content . "\n\n" . $gutenberg_markup;
			}
		}

		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$footer_content = $this->get_template_content( $footer_template_id );
			if ( '' !== $footer_content ) {
				$gutenberg_markup = $gutenberg_markup . "\n\n" . $footer_content;
			}
		}

		$existing_hub_page = $this->find_hub_page( $hub_key );

		$post_data = array(
			'post_title' => $data['title'],
			'post_content' => $gutenberg_markup,
			'post_status' => 'publish',
			'post_type' => 'service_page',
			'post_name' => $hub['slug'],
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

		// SEO meta
		$meta_description = isset( $data['meta_description'] ) ? $data['meta_description'] : '';
		if ( '' !== $meta_description ) {
			update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
			update_post_meta( $post_id, 'rank_math_description', $meta_description );
		}

		// Apply page builder settings to disable theme header/footer if configured (same as service+city)
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$this->apply_page_builder_settings( $post_id );
		}

		// Debug logging with verification
		$actual_post_type = get_post_type( $post_id );
		error_log( sprintf(
			'[HyperLocal] Created/updated hub page: post_id=%d, requested_post_type=service_page, actual_post_type=%s, _hl_page_type=service_hub, hub_key=%s, content_length=%d',
			$post_id,
			$actual_post_type,
			$hub['key'],
			$content_length
		) );
		
		// Verify post_type is correct
		if ( $actual_post_type !== 'service_page' ) {
			error_log( sprintf(
				'[HyperLocal] ERROR: Hub page has wrong post_type! Expected service_page, got %s. This will cause layout issues.',
				$actual_post_type
			) );
		}

		$action_text = $existing_hub_page ? 'updated' : 'created';
		wp_redirect( add_query_arg( array(
			'page' => 'hyper-local-service-hubs',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( 'Hub page ' . $action_text . ' successfully: ' . $data['title'] ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}
