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
					<input type="hidden" name="action" value="seogen_save_settings">
					<?php wp_nonce_field( 'seogen_save_settings' ); ?>
					
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
					<input type="hidden" name="action" value="hyper_local_save_business_config">
					<?php wp_nonce_field( 'hyper_local_save_business_config' ); ?>
					
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
								<p class="description"><?php esc_html_e( 'e.g., "Tulsa Metro" or "Greater Austin Area"', 'seogen' ); ?></p>
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
				<div id="seogen-wizard-services-container">
					<?php
					$services = get_option( 'hyper_local_services_cache', array() );
					if ( ! empty( $services ) && is_array( $services ) ) {
						echo '<ul class="seogen-wizard-list">';
						foreach ( $services as $service ) {
							$service_name = is_array( $service ) ? ( isset( $service['name'] ) ? $service['name'] : '' ) : $service;
							if ( $service_name ) {
								echo '<li>' . esc_html( $service_name ) . '</li>';
							}
						}
						echo '</ul>';
						echo '<p class="seogen-wizard-count">' . sprintf( esc_html__( '%d services added', 'seogen' ), count( $services ) ) . '</p>';
					} else {
						echo '<p class="seogen-wizard-empty">' . esc_html__( 'No services added yet.', 'seogen' ) . '</p>';
					}
					?>
				</div>
				
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local&tab=services' ) ); ?>" class="button button-secondary" target="_blank">
						<?php esc_html_e( 'Manage Services', 'seogen' ); ?>
					</a>
					<button type="button" class="button button-secondary seogen-wizard-refresh" data-refresh="services">
						<?php esc_html_e( 'Refresh List', 'seogen' ); ?>
					</button>
				</p>
				
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
				<div id="seogen-wizard-cities-container">
					<?php
					$cities = get_option( 'hyper_local_cities_cache', array() );
					if ( ! empty( $cities ) && is_array( $cities ) ) {
						echo '<ul class="seogen-wizard-list">';
						foreach ( $cities as $city ) {
							$city_name = is_array( $city ) ? ( isset( $city['name'] ) ? $city['name'] : '' ) : $city;
							if ( $city_name ) {
								echo '<li>' . esc_html( $city_name ) . '</li>';
							}
						}
						echo '</ul>';
						echo '<p class="seogen-wizard-count">' . sprintf( esc_html__( '%d cities added', 'seogen' ), count( $cities ) ) . '</p>';
					} else {
						echo '<p class="seogen-wizard-empty">' . esc_html__( 'No cities added yet.', 'seogen' ) . '</p>';
					}
					?>
				</div>
				
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local&tab=cities' ) ); ?>" class="button button-secondary" target="_blank">
						<?php esc_html_e( 'Manage Cities', 'seogen' ); ?>
					</a>
					<button type="button" class="button button-secondary seogen-wizard-refresh" data-refresh="cities">
						<?php esc_html_e( 'Refresh List', 'seogen' ); ?>
					</button>
				</p>
				
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
				<h3><?php esc_html_e( 'Generation Plan', 'seogen' ); ?></h3>
				
				<?php
				$services = get_option( 'hyper_local_services_cache', array() );
				$cities = get_option( 'hyper_local_cities_cache', array() );
				$config = get_option( 'seogen_business_config', array() );
				
				$service_count = is_array( $services ) ? count( $services ) : 0;
				$city_count = is_array( $cities ) ? count( $cities ) : 0;
				$service_city_count = $service_count * $city_count;
				
				// Estimate hub counts (simplified)
				$service_hub_count = 2; // residential, commercial
				$city_hub_count = $city_count * 2; // residential + commercial per city
				?>
				
				<ul class="seogen-wizard-generation-list">
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Service Hub Pages', 'seogen' ); ?></strong>
						<span class="count">(~<?php echo esc_html( $service_hub_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'Category pages for your service types', 'seogen' ); ?></p>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'Service + City Pages', 'seogen' ); ?></strong>
						<span class="count">(~<?php echo esc_html( $service_city_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'Location-specific service pages', 'seogen' ); ?></p>
					</li>
					<li>
						<span class="dashicons dashicons-yes-alt"></span>
						<strong><?php esc_html_e( 'City Hub Pages', 'seogen' ); ?></strong>
						<span class="count">(~<?php echo esc_html( $city_hub_count ); ?> pages)</span>
						<p class="description"><?php esc_html_e( 'City-specific service category pages', 'seogen' ); ?></p>
					</li>
				</ul>
				
				<p class="seogen-wizard-total">
					<strong><?php esc_html_e( 'Total:', 'seogen' ); ?></strong>
					<?php echo esc_html( sprintf( __( '~%d pages', 'seogen' ), $service_hub_count + $service_city_count + $city_hub_count ) ); ?>
				</p>
				
				<p class="description">
					<?php esc_html_e( 'Generation will happen in the background. You can close this page and check progress later.', 'seogen' ); ?>
				</p>
			</div>
			
			<div class="seogen-wizard-generation-progress" style="display:none;">
				<h3><?php esc_html_e( 'Generation Progress', 'seogen' ); ?></h3>
				<div class="seogen-wizard-progress-details">
					<p><?php esc_html_e( 'Generating pages...', 'seogen' ); ?></p>
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
