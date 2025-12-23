<?php
/**
 * Wizard Page Template
 * 
 * Main wizard interface with step-by-step setup flow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$state = $this->get_wizard_state();
$current_step = isset( $state['current_step'] ) ? (int) $state['current_step'] : 1;
$steps_completed = isset( $state['steps_completed'] ) ? $state['steps_completed'] : array();
?>

<div class="wrap seogen-wizard-wrap">
	<h1><?php esc_html_e( 'Hyper Local Setup Wizard', 'seogen' ); ?></h1>
	
	<!-- Progress Bar -->
	<div class="seogen-wizard-progress">
		<div class="seogen-wizard-steps">
			<div class="seogen-wizard-step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo ! empty( $steps_completed['settings'] ) ? 'completed' : ''; ?>">
				<span class="step-number">1</span>
				<span class="step-label"><?php esc_html_e( 'Settings', 'seogen' ); ?></span>
			</div>
			<div class="seogen-wizard-step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo ! empty( $steps_completed['business'] ) ? 'completed' : ''; ?>">
				<span class="step-number">2</span>
				<span class="step-label"><?php esc_html_e( 'Business', 'seogen' ); ?></span>
			</div>
			<div class="seogen-wizard-step <?php echo $current_step >= 3 ? 'active' : ''; ?> <?php echo ! empty( $steps_completed['services'] ) ? 'completed' : ''; ?>">
				<span class="step-number">3</span>
				<span class="step-label"><?php esc_html_e( 'Services', 'seogen' ); ?></span>
			</div>
			<div class="seogen-wizard-step <?php echo $current_step >= 4 ? 'active' : ''; ?> <?php echo ! empty( $steps_completed['cities'] ) ? 'completed' : ''; ?>">
				<span class="step-number">4</span>
				<span class="step-label"><?php esc_html_e( 'Cities', 'seogen' ); ?></span>
			</div>
			<div class="seogen-wizard-step <?php echo $current_step >= 5 ? 'active' : ''; ?>">
				<span class="step-number">5</span>
				<span class="step-label"><?php esc_html_e( 'Generate', 'seogen' ); ?></span>
			</div>
		</div>
		<div class="seogen-wizard-progress-bar">
			<div class="seogen-wizard-progress-fill" style="width: <?php echo ( ( $current_step - 1 ) / 4 ) * 100; ?>%;"></div>
		</div>
	</div>
	
	<!-- Step Content -->
	<div class="seogen-wizard-content">
		
		<!-- Step 1: Settings -->
		<div class="seogen-wizard-step-content" data-step="1" style="<?php echo 1 === $current_step ? '' : 'display:none;'; ?>">
			<h2><?php esc_html_e( 'Step 1: License Key', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Enter your Hyper Local license key to enable page generation.', 'seogen' ); ?></p>
			
			<div class="seogen-wizard-form">
				<?php
				$settings = get_option( 'seogen_settings', array() );
				$api_url = isset( $settings['api_url'] ) && ! empty( $settings['api_url'] ) ? $settings['api_url'] : 'https://seogen-production.up.railway.app';
				$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
				?>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seogen-wizard-settings-form">
					<input type="hidden" name="action" value="seogen_wizard_save_settings">
					<?php wp_nonce_field( 'seogen_wizard_save_settings' ); ?>
					
					<!-- Hidden field with default API URL -->
					<input type="hidden" name="seogen_settings[api_url]" value="<?php echo esc_attr( $api_url ); ?>">
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="license_key"><?php esc_html_e( 'License Key', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="text" name="seogen_settings[license_key]" id="license_key" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" required>
								<p class="description"><?php esc_html_e( 'Your Hyper Local license key.', 'seogen' ); ?></p>
							</td>
						</tr>
					</table>
					
					<p class="submit">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Save & Continue', 'seogen' ); ?>
						</button>
					</p>
				</form>
				
				<div class="seogen-wizard-validation-message"></div>
			</div>
		</div>
		
		<!-- Step 2: Business Setup -->
		<div class="seogen-wizard-step-content" data-step="2" style="<?php echo 2 === $current_step ? '' : 'display:none;'; ?>">
			<h2><?php esc_html_e( 'Step 2: Business Setup', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Configure your business information for page generation.', 'seogen' ); ?></p>
			
			<div class="seogen-wizard-form">
				<?php
				$config = get_option( 'seogen_business_config', array() );
				?>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="seogen-wizard-business-form">
					<input type="hidden" name="action" value="seogen_wizard_save_business">
					<?php wp_nonce_field( 'seogen_wizard_save_business' ); ?>
					
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="vertical"><?php esc_html_e( 'Business Vertical', 'seogen' ); ?></label>
							</th>
							<td>
								<select name="seogen_business_config[vertical]" id="vertical" required>
									<option value=""><?php esc_html_e( 'Select...', 'seogen' ); ?></option>
									<option value="electrician" <?php selected( isset( $config['vertical'] ) ? $config['vertical'] : '', 'electrician' ); ?>><?php esc_html_e( 'Electrician', 'seogen' ); ?></option>
									<option value="plumber" <?php selected( isset( $config['vertical'] ) ? $config['vertical'] : '', 'plumber' ); ?>><?php esc_html_e( 'Plumber', 'seogen' ); ?></option>
									<option value="hvac" <?php selected( isset( $config['vertical'] ) ? $config['vertical'] : '', 'hvac' ); ?>><?php esc_html_e( 'HVAC', 'seogen' ); ?></option>
									<option value="roofer" <?php selected( isset( $config['vertical'] ) ? $config['vertical'] : '', 'roofer' ); ?>><?php esc_html_e( 'Roofer', 'seogen' ); ?></option>
									<option value="landscaper" <?php selected( isset( $config['vertical'] ) ? $config['vertical'] : '', 'landscaper' ); ?>><?php esc_html_e( 'Landscaper', 'seogen' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="business_name"><?php esc_html_e( 'Business Name', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="text" name="seogen_business_config[business_name]" id="business_name" value="<?php echo esc_attr( isset( $config['business_name'] ) ? $config['business_name'] : '' ); ?>" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="phone"><?php esc_html_e( 'Phone Number', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="tel" name="seogen_business_config[phone]" id="phone" value="<?php echo esc_attr( isset( $config['phone'] ) ? $config['phone'] : '' ); ?>" class="regular-text" required>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="email"><?php esc_html_e( 'Email', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="email" name="seogen_business_config[email]" id="email" value="<?php echo esc_attr( isset( $config['email'] ) ? $config['email'] : '' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Optional - used in generated pages', 'seogen' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="address"><?php esc_html_e( 'Address', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="text" name="seogen_business_config[address]" id="address" value="<?php echo esc_attr( isset( $config['address'] ) ? $config['address'] : '' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Optional - full business address', 'seogen' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cta_text"><?php esc_html_e( 'CTA Text', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="text" name="seogen_business_config[cta_text]" id="cta_text" value="<?php echo esc_attr( isset( $config['cta_text'] ) ? $config['cta_text'] : 'Request a Free Estimate' ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="service_area_label"><?php esc_html_e( 'Service Area Label', 'seogen' ); ?></label>
							</th>
							<td>
								<input type="text" name="seogen_business_config[service_area_label]" id="service_area_label" value="<?php echo esc_attr( isset( $config['service_area_label'] ) ? $config['service_area_label'] : '' ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Optional - e.g., "Tulsa Metro" or "Greater Austin Area"', 'seogen' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label><?php esc_html_e( 'Hub Categories', 'seogen' ); ?> <span style="color: #d63638;">*</span></label>
							</th>
							<td>
								<?php
								$hub_categories = isset( $config['hub_categories'] ) ? $config['hub_categories'] : array( 'residential', 'commercial' );
								if ( ! is_array( $hub_categories ) ) {
									$hub_categories = array( 'residential', 'commercial' );
								}
								?>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Select the service hub categories for your business:', 'seogen' ); ?></legend>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="residential" <?php checked( in_array( 'residential', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Residential (residential-services)', 'seogen' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="commercial" <?php checked( in_array( 'commercial', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Commercial (commercial-services)', 'seogen' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="emergency" <?php checked( in_array( 'emergency', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Emergency (emergency-services)', 'seogen' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="repair" <?php checked( in_array( 'repair', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Repair (repair-services)', 'seogen' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="installation" <?php checked( in_array( 'installation', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Installation (installation-services)', 'seogen' ); ?>
									</label><br>
									<label>
										<input type="checkbox" name="seogen_business_config[hub_categories][]" value="maintenance" <?php checked( in_array( 'maintenance', $hub_categories ) ); ?>>
										<?php esc_html_e( 'Maintenance (maintenance-services)', 'seogen' ); ?>
									</label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Select at least one hub category. You can add custom hubs later.', 'seogen' ); ?></p>
							</td>
						</tr>
					</table>
					
					<p class="submit">
						<button type="button" class="button button-secondary button-large seogen-wizard-back">
							<?php esc_html_e( '← Back', 'seogen' ); ?>
						</button>
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Save & Continue', 'seogen' ); ?>
						</button>
					</p>
				</form>
				
				<div class="seogen-wizard-validation-message"></div>
			</div>
		</div>
		
		<!-- Step 3: Services -->
		<div class="seogen-wizard-step-content" data-step="3" style="<?php echo 3 === $current_step ? '' : 'display:none;'; ?>">
			<h2><?php esc_html_e( 'Step 3: Add Services', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Add at least 3 services that you offer. These will be used to generate service pages.', 'seogen' ); ?></p>
			
			<div class="seogen-wizard-form">
				<!-- Add Single Service Form -->
				<div class="seogen-wizard-add-form" style="margin-bottom: 20px;">
					<h3><?php esc_html_e( 'Add a Service', 'seogen' ); ?></h3>
					<div style="display: flex; gap: 10px; align-items: flex-start;">
						<input type="text" id="seogen-wizard-new-service" placeholder="<?php esc_attr_e( 'e.g., Electrical Panel Upgrade', 'seogen' ); ?>" class="regular-text" style="flex: 1;">
						<select id="seogen-wizard-service-hub" class="regular-text">
							<option value=""><?php esc_html_e( 'Select Hub...', 'seogen' ); ?></option>
							<?php
							$config = get_option( 'seogen_business_config', array() );
							$hub_categories = isset( $config['hub_categories'] ) && is_array( $config['hub_categories'] ) 
								? $config['hub_categories'] 
								: array( 'residential', 'commercial' );
							foreach ( $hub_categories as $hub ) {
								echo '<option value="' . esc_attr( $hub ) . '">' . esc_html( ucfirst( $hub ) ) . '</option>';
							}
							?>
						</select>
						<button type="button" class="button button-primary seogen-wizard-add-service">
							<?php esc_html_e( 'Add Service', 'seogen' ); ?>
						</button>
					</div>
				</div>
				
				<!-- Bulk Add Services Form -->
				<div class="seogen-wizard-add-form" style="margin-bottom: 20px; padding: 20px; background: #f0f0f1; border-radius: 4px;">
					<h3><?php esc_html_e( 'Bulk Add Services', 'seogen' ); ?></h3>
					<p class="description" style="margin-bottom: 10px;">
						<?php esc_html_e( 'Add multiple services at once. Format: "hub_key: Service Name" (one per line). If hub_key is omitted, the first hub will be used.', 'seogen' ); ?>
					</p>
					<textarea id="seogen-wizard-bulk-services" rows="6" class="large-text" placeholder="<?php esc_attr_e( "residential: Outlet Installation\ncommercial: Panel Upgrade\nLighting Repair", 'seogen' ); ?>"></textarea>
					<p style="margin-top: 10px;">
						<button type="button" class="button button-secondary seogen-wizard-bulk-add-services">
							<?php esc_html_e( 'Bulk Add Services', 'seogen' ); ?>
						</button>
					</p>
				</div>
				
				<!-- Services List -->
				<div id="seogen-wizard-services-container">
					<?php
					$services = get_option( 'hyper_local_services_cache', array() );
					if ( ! empty( $services ) && is_array( $services ) ) {
						echo '<ul class="seogen-wizard-list seogen-wizard-list-deletable">';
						foreach ( $services as $idx => $service ) {
							$service_name = is_array( $service ) ? ( isset( $service['name'] ) ? $service['name'] : '' ) : $service;
							$service_hub = is_array( $service ) && isset( $service['hub'] ) ? $service['hub'] : '';
							if ( $service_name ) {
								echo '<li data-index="' . esc_attr( $idx ) . '">';
								echo '<span class="seogen-wizard-list-text">' . esc_html( $service_name );
								if ( $service_hub ) {
									echo ' <span style="color: #666; font-size: 12px;">(' . esc_html( ucfirst( $service_hub ) ) . ')</span>';
								}
								echo '</span>';
								echo '<button type="button" class="button button-small seogen-wizard-delete-service" data-index="' . esc_attr( $idx ) . '" data-name="' . esc_attr( $service_name ) . '">';
								echo esc_html__( 'Delete', 'seogen' );
								echo '</button>';
								echo '</li>';
							}
						}
						echo '</ul>';
						echo '<p class="seogen-wizard-count">' . sprintf( esc_html__( '%d services added', 'seogen' ), count( $services ) ) . '</p>';
					} else {
						echo '<p class="seogen-wizard-empty">' . esc_html__( 'No services added yet. Add at least 3 services above.', 'seogen' ) . '</p>';
					}
					?>
				</div>
				
				<div class="seogen-wizard-validation-message"></div>
				
				<p class="submit">
					<button type="button" class="button button-secondary button-large seogen-wizard-back">
						<?php esc_html_e( '← Back', 'seogen' ); ?>
					</button>
					<button type="button" class="button button-primary button-large seogen-wizard-next" data-step="3">
						<?php esc_html_e( 'Continue', 'seogen' ); ?>
					</button>
				</p>
			</div>
		</div>
		
		<!-- Step 4: Cities -->
		<div class="seogen-wizard-step-content" data-step="4" style="<?php echo 4 === $current_step ? '' : 'display:none;'; ?>">
			<h2><?php esc_html_e( 'Step 4: Add Cities', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Add at least 3 cities that you serve. These will be used to generate location-specific pages.', 'seogen' ); ?></p>
			
			<div class="seogen-wizard-form">
				<!-- Add City Form -->
				<div class="seogen-wizard-add-form" style="margin-bottom: 20px;">
					<h3><?php esc_html_e( 'Add a City', 'seogen' ); ?></h3>
					<div style="display: flex; gap: 10px; align-items: flex-start;">
						<input type="text" id="seogen-wizard-new-city" placeholder="<?php esc_attr_e( 'e.g., Tulsa, OK', 'seogen' ); ?>" class="regular-text" style="flex: 1;">
						<button type="button" class="button button-primary seogen-wizard-add-city">
							<?php esc_html_e( 'Add City', 'seogen' ); ?>
						</button>
					</div>
					<p class="description"><?php esc_html_e( 'Format: City Name, State (e.g., "Austin, TX" or "New York, NY")', 'seogen' ); ?></p>
				</div>
				
				<!-- Cities List -->
				<div id="seogen-wizard-cities-container">
					<?php
					$cities = get_option( 'hyper_local_cities_cache', array() );
					if ( ! empty( $cities ) && is_array( $cities ) ) {
						echo '<ul class="seogen-wizard-list seogen-wizard-list-deletable">';
						foreach ( $cities as $idx => $city ) {
							$city_name = is_array( $city ) ? ( isset( $city['name'] ) ? $city['name'] : '' ) : $city;
							if ( $city_name ) {
								echo '<li data-index="' . esc_attr( $idx ) . '">';
								echo '<span class="seogen-wizard-list-text">' . esc_html( $city_name ) . '</span>';
								echo '<button type="button" class="button button-small seogen-wizard-delete-city" data-index="' . esc_attr( $idx ) . '" data-name="' . esc_attr( $city_name ) . '">';
								echo esc_html__( 'Delete', 'seogen' );
								echo '</button>';
								echo '</li>';
							}
						}
						echo '</ul>';
						echo '<p class="seogen-wizard-count">' . sprintf( esc_html__( '%d cities added', 'seogen' ), count( $cities ) ) . '</p>';
					} else {
						echo '<p class="seogen-wizard-empty">' . esc_html__( 'No cities added yet. Add at least 3 cities above.', 'seogen' ) . '</p>';
					}
					?>
				</div>
				
				<div class="seogen-wizard-validation-message"></div>
				
				<p class="submit">
					<button type="button" class="button button-secondary button-large seogen-wizard-back">
						<?php esc_html_e( '← Back', 'seogen' ); ?>
					</button>
					<button type="button" class="button button-primary button-large seogen-wizard-next" data-step="4">
						<?php esc_html_e( 'Continue to Generation', 'seogen' ); ?>
					</button>
				</p>
			</div>
		</div>
		
		<!-- Step 5: Generation -->
		<div class="seogen-wizard-step-content" data-step="5" style="<?php echo 5 === $current_step ? '' : 'display:none;'; ?>">
			<h2><?php esc_html_e( 'Step 5: Generate Pages', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'Setup complete! Now we can automatically generate all your pages.', 'seogen' ); ?></p>
			
			<div class="seogen-wizard-generation-plan">
				<h3><?php esc_html_e( 'What Will Be Generated', 'seogen' ); ?></h3>
				
				<?php
				$services = get_option( 'hyper_local_services_cache', array() );
				$cities = get_option( 'hyper_local_cities_cache', array() );
				
				$service_count = is_array( $services ) ? count( $services ) : 0;
				$city_count = is_array( $cities ) ? count( $cities ) : 0;
				$service_hub_count = $service_count;
				$service_city_count = $service_count * $city_count;
				$city_hub_count = $city_count;
				$total_pages = $service_hub_count + $service_city_count + $city_hub_count;
				?>
				
				<ul class="seogen-wizard-generation-list">
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Phase 1: Service Hub Pages', 'seogen' ); ?></strong>
						<span class="count">(<?php echo esc_html( $service_hub_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'Overview pages for each service type', 'seogen' ); ?></p>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Phase 2: Service + City Pages', 'seogen' ); ?></strong>
						<span class="count">(<?php echo esc_html( $service_city_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'Location-specific service pages for each service in each city', 'seogen' ); ?></p>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Phase 3: City Hub Pages', 'seogen' ); ?></strong>
						<span class="count">(<?php echo esc_html( $city_hub_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'City overview pages listing all services', 'seogen' ); ?></p>
					</li>
				</ul>
				
				<p class="seogen-wizard-total">
					<strong><?php esc_html_e( 'Total:', 'seogen' ); ?></strong>
					<?php echo esc_html( sprintf( __( '%d pages', 'seogen' ), $total_pages ) ); ?>
				</p>
				
				<div style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
					<p style="margin: 0; font-size: 13px;">
						<strong><?php esc_html_e( 'Note:', 'seogen' ); ?></strong>
						<?php esc_html_e( 'Pages will be generated in 3 sequential phases. All pages will be created automatically.', 'seogen' ); ?>
					</p>
				</div>
				
				<p class="description" style="margin-top: 15px;">
					<?php esc_html_e( 'Pages will be generated in batches. Please keep this page open until generation completes.', 'seogen' ); ?>
				</p>
			</div>
			
			<div class="seogen-wizard-generation-progress" style="display:none; margin-top: 30px; padding: 20px; background: #f0f0f1; border-radius: 4px;">
				<h3><?php esc_html_e( 'Generation Progress', 'seogen' ); ?></h3>
				
				<div style="margin: 20px 0;">
					<div style="background: #fff; border-radius: 4px; height: 30px; position: relative; overflow: hidden;">
						<div class="seogen-wizard-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
						<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: 600;">
							<span class="seogen-wizard-progress-percentage">0%</span>
						</div>
					</div>
					<p style="margin: 10px 0 0; text-align: center;">
						<span class="seogen-wizard-progress-text">0 / 0 pages</span>
					</p>
					<p class="seogen-wizard-progress-stats" style="margin: 5px 0 0; text-align: center;"></p>
				</div>
				
				<div style="margin-top: 20px;">
					<h4><?php esc_html_e( 'Recent Activity', 'seogen' ); ?></h4>
					<div class="seogen-wizard-generation-log" style="max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px;"></div>
				</div>
			</div>
			
			<p class="submit">
				<button type="button" class="button button-secondary button-large seogen-wizard-back">
					<?php esc_html_e( '← Back', 'seogen' ); ?>
				</button>
				<button type="button" class="button button-primary button-large seogen-wizard-start-generation">
					<?php esc_html_e( 'Start Generation', 'seogen' ); ?>
				</button>
				<button type="button" class="button button-secondary button-large seogen-wizard-skip-generation">
					<?php esc_html_e( 'Skip for Now', 'seogen' ); ?>
				</button>
			</p>
		</div>
		
	</div>
	
	<!-- Footer -->
	<div class="seogen-wizard-footer">
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local' ) ); ?>">
				<?php esc_html_e( 'Exit Wizard', 'seogen' ); ?>
			</a>
		</p>
	</div>
</div>
