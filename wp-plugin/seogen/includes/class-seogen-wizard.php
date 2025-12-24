<?php
/**
 * SEOgen First Run Wizard
 * 
 * Guides users through initial setup and automates bulk page generation.
 * Does not modify existing functionality - only adds new guided workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Wizard {
	const WIZARD_STATE_OPTION = 'seogen_wizard_state';
	const WIZARD_DISMISSED_OPTION = 'seogen_wizard_dismissed';
	
	/**
	 * Initialize wizard
	 */
	public function __construct() {
		// Admin hooks
		add_action( 'admin_menu', array( $this, 'add_wizard_menu' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_notices', array( $this, 'show_wizard_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
		
		// AJAX handlers
		add_action( 'wp_ajax_seogen_wizard_validate_step', array( $this, 'ajax_validate_step' ) );
		add_action( 'wp_ajax_seogen_wizard_advance_step', array( $this, 'ajax_advance_step' ) );
		add_action( 'wp_ajax_seogen_wizard_start_generation', array( $this, 'ajax_start_generation' ) );
		add_action( 'wp_ajax_seogen_wizard_process_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_seogen_wizard_generation_progress', array( $this, 'ajax_generation_progress' ) );
		add_action( 'wp_ajax_seogen_wizard_skip_generation', array( $this, 'ajax_skip_generation' ) );
		add_action( 'wp_ajax_seogen_wizard_dismiss', array( $this, 'ajax_dismiss_wizard' ) );
		add_action( 'wp_ajax_seogen_wizard_reset', array( $this, 'ajax_reset_wizard' ) );
		
		// Admin-post handlers for form submissions
		add_action( 'admin_post_seogen_wizard_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_seogen_wizard_save_business', array( $this, 'handle_save_business' ) );
		
		// AJAX handlers for inline service/city management
		add_action( 'wp_ajax_seogen_wizard_add_service', array( $this, 'ajax_add_service' ) );
		add_action( 'wp_ajax_seogen_wizard_bulk_add_services', array( $this, 'ajax_bulk_add_services' ) );
		add_action( 'wp_ajax_seogen_wizard_delete_service', array( $this, 'ajax_delete_service' ) );
		add_action( 'wp_ajax_seogen_wizard_add_city', array( $this, 'ajax_add_city' ) );
		add_action( 'wp_ajax_seogen_wizard_delete_city', array( $this, 'ajax_delete_city' ) );
	}
	
	/**
	 * Get wizard state
	 * 
	 * @return array Wizard state data
	 */
	public function get_wizard_state() {
		$default_state = array(
			'completed' => false,
			'current_step' => 1,
			'steps_completed' => array(
				'settings' => false,
				'business' => false,
				'services' => false,
				'cities' => false,
			),
			'generation' => array(
				'current_phase' => null,
				'phases' => array(
					'service_hubs' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => 0,
						'completed' => 0,
						'failed' => 0,
					),
					'service_city' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => 0,
						'completed' => 0,
						'failed' => 0,
					),
					'city_hubs' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => 0,
						'completed' => 0,
						'failed' => 0,
					),
				),
				'started_at' => null,
				'completed_at' => null,
			),
		);
		
		$state = get_option( self::WIZARD_STATE_OPTION, $default_state );
		
		// Ensure all keys exist
		return wp_parse_args( $state, $default_state );
	}
	
	/**
	 * Update wizard state
	 * 
	 * @param array $updates State updates to merge
	 * @return bool Success
	 */
	public function update_wizard_state( $updates ) {
		$state = $this->get_wizard_state();
		$state = array_replace_recursive( $state, $updates );
		return update_option( self::WIZARD_STATE_OPTION, $state );
	}
	
	/**
	 * Check if wizard is complete
	 * 
	 * @return bool True if wizard completed
	 */
	public function is_wizard_complete() {
		$state = $this->get_wizard_state();
		return ! empty( $state['completed'] );
	}
	
	/**
	 * Check if wizard is dismissed
	 * 
	 * @return bool True if wizard dismissed
	 */
	public function is_wizard_dismissed() {
		return (bool) get_option( self::WIZARD_DISMISSED_OPTION, false );
	}
	
	/**
	 * Reset wizard state
	 * 
	 * @return bool Success
	 */
	public function reset_wizard() {
		delete_option( self::WIZARD_STATE_OPTION );
		delete_option( self::WIZARD_DISMISSED_OPTION );
		return true;
	}
	
	/**
	 * Add wizard menu item
	 */
	public function add_wizard_menu() {
		// Only show wizard menu if user can manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Always show wizard menu for administrators
		// This allows re-running the wizard or accessing it even if completed/dismissed
		add_menu_page(
			__( 'Setup Wizard', 'seogen' ),
			__( 'Setup Wizard', 'seogen' ),
			'manage_options',
			'seogen-wizard',
			array( $this, 'render_wizard_page' ),
			'dashicons-admin-generic',
			3
		);
	}
	
	/**
	 * Maybe redirect to wizard on plugin activation
	 */
	public function maybe_redirect_to_wizard() {
		// Only redirect if wizard not completed and not dismissed
		if ( $this->is_wizard_complete() || $this->is_wizard_dismissed() ) {
			return;
		}
		
		// Check if this is a fresh activation (no settings saved)
		$settings = get_option( 'seogen_settings', array() );
		if ( empty( $settings ) ) {
			// Only redirect on admin pages, not AJAX
			if ( is_admin() && ! wp_doing_ajax() && ! isset( $_GET['page'] ) ) {
				// Check if we should redirect (use transient to prevent redirect loops)
				$redirect_key = 'seogen_wizard_redirect_' . get_current_user_id();
				if ( ! get_transient( $redirect_key ) ) {
					set_transient( $redirect_key, 1, 60 );
					wp_safe_redirect( admin_url( 'admin.php?page=seogen-wizard' ) );
					exit;
				}
			}
		}
	}
	
	/**
	 * Show wizard notice
	 */
	public function show_wizard_notice() {
		// Only show if wizard not completed and not dismissed
		if ( $this->is_wizard_complete() || $this->is_wizard_dismissed() ) {
			return;
		}
		
		// Don't show on wizard page itself
		if ( isset( $_GET['page'] ) && 'seogen-wizard' === $_GET['page'] ) {
			return;
		}
		
		// Only show to users who can manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$wizard_url = admin_url( 'admin.php?page=seogen-wizard' );
		?>
		<div class="notice notice-info is-dismissible seogen-wizard-notice">
			<p>
				<strong><?php esc_html_e( 'Welcome to Hyper Local!', 'seogen' ); ?></strong>
				<?php esc_html_e( 'Complete the setup wizard to get started with automated page generation.', 'seogen' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $wizard_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'Start Setup Wizard', 'seogen' ); ?>
				</a>
				<a href="#" class="button seogen-wizard-dismiss">
					<?php esc_html_e( 'Dismiss', 'seogen' ); ?>
				</a>
			</p>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('.seogen-wizard-dismiss').on('click', function(e) {
				e.preventDefault();
				$.post(ajaxurl, {
					action: 'seogen_wizard_dismiss',
					_ajax_nonce: '<?php echo wp_create_nonce( 'seogen_wizard_dismiss' ); ?>'
				}, function() {
					$('.seogen-wizard-notice').fadeOut();
				});
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Enqueue wizard assets
	 */
	public function enqueue_wizard_assets( $hook ) {
		// Only load on wizard page
		if ( 'toplevel_page_seogen-wizard' !== $hook ) {
			return;
		}
		
		wp_enqueue_style(
			'seogen-wizard',
			SEOGEN_PLUGIN_URL . 'assets/wizard.css',
			array(),
			SEOGEN_VERSION
		);
		
		wp_enqueue_script(
			'seogen-wizard',
			SEOGEN_PLUGIN_URL . 'assets/wizard.js',
			array( 'jquery' ),
			SEOGEN_VERSION,
			true
		);
		
		wp_localize_script(
			'seogen-wizard',
			'seogenWizard',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'seogen_wizard_nonce' ),
				'strings' => array(
					'validating' => __( 'Validating...', 'seogen' ),
					'saving' => __( 'Saving...', 'seogen' ),
					'generating' => __( 'Generating pages...', 'seogen' ),
					'error' => __( 'An error occurred. Please try again.', 'seogen' ),
				),
			)
		);
	}
	
	/**
	 * Render wizard page
	 */
	public function render_wizard_page() {
		// Migrate existing services to include hub field if missing
		$this->migrate_services_hub_field();
		
		$state = $this->get_wizard_state();
		$current_step = isset( $state['current_step'] ) ? (int) $state['current_step'] : 1;
		
		include SEOGEN_PLUGIN_DIR . 'templates/wizard-page.php';
	}
	
	/**
	 * Migrate existing services to include hub field
	 */
	private function migrate_services_hub_field() {
		$services = get_option( 'hyper_local_services_cache', array() );
		if ( empty( $services ) || ! is_array( $services ) ) {
			return;
		}
		
		$config = get_option( 'seogen_business_config', array() );
		$hub_categories = isset( $config['hub_categories'] ) && is_array( $config['hub_categories'] ) 
			? $config['hub_categories'] 
			: array( 'residential', 'commercial' );
		$default_hub = ! empty( $hub_categories ) ? $hub_categories[0] : 'residential';
		
		$updated = false;
		foreach ( $services as $idx => $service ) {
			// If service is a string, convert to array with hub
			if ( is_string( $service ) ) {
				$services[ $idx ] = array(
					'name' => $service,
					'hub' => $default_hub,
				);
				$updated = true;
			}
			// If service is array but missing hub, add default hub
			elseif ( is_array( $service ) && ! isset( $service['hub'] ) ) {
				$services[ $idx ]['hub'] = $default_hub;
				$updated = true;
			}
		}
		
		if ( $updated ) {
			update_option( 'hyper_local_services_cache', $services );
		}
	}
	
	/**
	 * Validate step
	 */
	public function validate_step( $step ) {
		switch ( $step ) {
			case 1:
				return $this->validate_step_settings();
			case 2:
				return $this->validate_step_business();
			case 3:
				return $this->validate_step_services();
			case 4:
				return $this->validate_step_cities();
			default:
				return array( 'valid' => false, 'message' => 'Invalid step' );
		}
	}
	
	/**
	 * Validate settings step
	 */
	public function validate_step_settings() {
		$settings = get_option( 'seogen_settings', array() );
		
		// Check if API URL and license key are set
		if ( empty( $settings['api_url'] ) ) {
			return array( 'valid' => false, 'message' => 'API URL is required' );
		}
		
		if ( empty( $settings['license_key'] ) ) {
			return array( 'valid' => false, 'message' => 'License Key is required' );
		}
		
		// Test API connection
		$api_url = trailingslashit( $settings['api_url'] ) . 'health';
		$response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );
		
		if ( is_wp_error( $response ) ) {
			return array( 'valid' => false, 'message' => 'Cannot connect to API: ' . $response->get_error_message() );
		}
		
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return array( 'valid' => false, 'message' => 'API connection failed (HTTP ' . $status_code . ')' );
		}
		
		return array( 'valid' => true, 'message' => 'Settings validated successfully' );
	}
	
	/**
	 * Validate business setup step
	 */
	public function validate_step_business() {
		$config = get_option( 'seogen_business_config', array() );
		
		$required_fields = array( 'vertical', 'business_name', 'phone' );
		foreach ( $required_fields as $field ) {
			if ( empty( $config[ $field ] ) ) {
				return array( 'valid' => false, 'message' => ucfirst( str_replace( '_', ' ', $field ) ) . ' is required' );
			}
		}
		
		return array( 'valid' => true, 'message' => 'Business setup validated successfully' );
	}
	
	/**
	 * Validate services step
	 */
	public function validate_step_services() {
		$services = get_option( 'hyper_local_services_cache', array() );
		
		if ( empty( $services ) || ! is_array( $services ) ) {
			return array( 'valid' => false, 'message' => 'Please add at least 3 services' );
		}
		
		if ( count( $services ) < 3 ) {
			return array( 'valid' => false, 'message' => 'Please add at least 3 services (currently ' . count( $services ) . ')' );
		}
		
		return array( 'valid' => true, 'message' => 'Services validated successfully' );
	}
	
	/**
	 * Validate cities step
	 */
	public function validate_step_cities() {
		$cities = get_option( 'hyper_local_cities_cache', array() );
		
		if ( empty( $cities ) || ! is_array( $cities ) ) {
			return array( 'valid' => false, 'message' => 'Please add at least 3 cities' );
		}
		
		if ( count( $cities ) < 3 ) {
			return array( 'valid' => false, 'message' => 'Please add at least 3 cities (currently ' . count( $cities ) . ')' );
		}
		
		return array( 'valid' => true, 'message' => 'Cities validated successfully' );
	}
	
	/**
	 * AJAX: Validate step
	 */
	public function ajax_validate_step() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		$result = $this->validate_step( $step );
		
		if ( $result['valid'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
	
	/**
	 * AJAX: Advance to next step
	 */
	public function ajax_advance_step() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;
		
		// Validate current step
		$validation = $this->validate_step( $step );
		if ( ! $validation['valid'] ) {
			wp_send_json_error( $validation );
		}
		
		// Mark step as completed
		$state = $this->get_wizard_state();
		$step_keys = array( 1 => 'settings', 2 => 'business', 3 => 'services', 4 => 'cities' );
		
		if ( isset( $step_keys[ $step ] ) ) {
			$state['steps_completed'][ $step_keys[ $step ] ] = true;
		}
		
		// Advance to next step
		$state['current_step'] = $step + 1;
		
		$this->update_wizard_state( $state );
		
		wp_send_json_success( array(
			'message' => 'Step completed',
			'next_step' => $step + 1,
		) );
	}
	
	/**
	 * AJAX: Start automated generation (3-phase process)
	 */
	public function ajax_start_generation() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		error_log( '[WIZARD] ajax_start_generation called' );
		
		// Check if generation is already running - but allow restart if it's been stuck for >5 minutes
		$state = $this->get_wizard_state();
		if ( ! empty( $state['generation']['current_phase'] ) ) {
			$last_update = isset( $state['generation']['last_update'] ) ? $state['generation']['last_update'] : 0;
			$time_since_update = time() - $last_update;
			
			// If stuck for more than 5 minutes, allow restart
			if ( $time_since_update < 300 ) {
				wp_send_json_error( array( 
					'message' => 'Generation is already running',
					'stuck' => false
				) );
			}
			
			// Clear stuck state
			error_log( '[WIZARD] Clearing stuck generation state (last update: ' . $time_since_update . 's ago)' );
			$this->update_wizard_state( array(
				'generation' => array(
					'current_phase' => null,
					'phases' => array(),
				),
			) );
		}
		
		// Get services and cities from cache
		$services = get_option( 'hyper_local_services_cache', array() );
		$cities = get_option( 'hyper_local_cities_cache', array() );
		
		error_log( '[WIZARD] Services: ' . count( $services ) . ', Cities: ' . count( $cities ) );
		
		if ( empty( $services ) || empty( $cities ) ) {
			wp_send_json_error( array( 'message' => 'No services or cities configured' ) );
		}
		
		// Get hub categories for Service Hub count
		$business_config = get_option( 'seogen_business_config', array() );
		$hub_categories = isset( $business_config['hub_categories'] ) && is_array( $business_config['hub_categories'] )
			? $business_config['hub_categories']
			: array( 'residential', 'commercial' );
		
		// Initialize generation state with all 3 phases
		$this->update_wizard_state( array(
			'generation' => array(
				'current_phase' => 'service_hubs',
				'last_update' => time(),
				'phases' => array(
					'service_hubs' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => count( $hub_categories ),
						'completed' => 0,
						'failed' => 0,
					),
					'service_city' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => count( $services ) * count( $cities ),
						'completed' => 0,
						'failed' => 0,
					),
					'city_hubs' => array(
						'status' => 'pending',
						'job_id' => null,
						'total' => count( $cities ),
						'completed' => 0,
						'failed' => 0,
					),
				),
				'started_at' => current_time( 'mysql' ),
				'completed_at' => null,
			),
		) );
		
		// Start Phase 1: Service Hubs
		$result = $this->start_phase_service_hubs();
		
		if ( ! $result['success'] ) {
			// Get settings for debugging
			$settings = get_option( 'seogen_settings', array() );
			$api_url = isset( $settings['api_url'] ) ? $settings['api_url'] : '';
			$license_key = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
			
			// Reset generation state on failure
			$this->update_wizard_state( array(
				'generation' => array(
					'current_phase' => null,
				),
			) );
			wp_send_json_error( array( 
				'message' => $result['error'],
				'debug_info' => 'Phase 1 failed to start',
				'debug_settings' => array(
					'api_url' => $api_url ? 'SET (' . strlen( $api_url ) . ' chars)' : 'EMPTY',
					'license_key' => $license_key ? 'SET (' . strlen( $license_key ) . ' chars)' : 'EMPTY',
					'settings_option_exists' => ! empty( $settings ),
				),
			) );
		}
		
		$total_pages = count( $hub_categories ) + ( count( $services ) * count( $cities ) ) + count( $cities );
		
		wp_send_json_success( array(
			'message' => 'Phase 1: Generating Service Hub pages',
			'phase' => 'service_hubs',
			'phase_number' => 1,
			'total_phases' => 3,
			'total_pages' => $total_pages,
			'job_id' => $result['job_id'],
			'debug_hub_categories' => $hub_categories,
			'debug_services_count' => count( $services ),
			'debug_cities_count' => count( $cities ),
		) );
	}
	
	/**
	 * Start Phase 1: Service Hub generation
	 */
	private function start_phase_service_hubs() {
		$business_config = get_option( 'seogen_business_config', array() );
		$settings = get_option( 'seogen_settings', array() );
		
		$api_url = isset( $settings['api_url'] ) ? trim( $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		$vertical = isset( $business_config['vertical'] ) ? trim( $business_config['vertical'] ) : '';
		
		error_log( '[WIZARD] start_phase_service_hubs - api_url: ' . ( $api_url ? 'SET (' . strlen( $api_url ) . ' chars)' : 'EMPTY' ) );
		error_log( '[WIZARD] start_phase_service_hubs - license_key: ' . ( $license_key ? 'SET (' . strlen( $license_key ) . ' chars)' : 'EMPTY' ) );
		error_log( '[WIZARD] start_phase_service_hubs - settings option: ' . wp_json_encode( $settings ) );
		
		if ( empty( $api_url ) || empty( $license_key ) ) {
			return array(
				'success' => false,
				'error' => 'API settings not configured',
			);
		}
		
		// Get unique hub categories from business config
		$hub_categories = isset( $business_config['hub_categories'] ) && is_array( $business_config['hub_categories'] )
			? $business_config['hub_categories']
			: array( 'residential', 'commercial' );
		
		// Build Service Hub items - one per unique hub category
		$api_items = array();
		foreach ( $hub_categories as $hub_key ) {
			$business_name = isset( $business_config['business_name'] ) ? $business_config['business_name'] : '';
			$phone = isset( $business_config['phone'] ) ? $business_config['phone'] : '';
			$email = isset( $business_config['email'] ) ? $business_config['email'] : '';
			$address = isset( $business_config['address'] ) ? $business_config['address'] : '';
			$cta_text = isset( $business_config['cta_text'] ) ? $business_config['cta_text'] : 'Request a Free Estimate';
			$service_area_label = isset( $business_config['service_area_label'] ) ? $business_config['service_area_label'] : '';
			
			$api_items[] = array(
				'page_mode' => 'service_hub',
				'hub_key' => $hub_key,
				'hub_label' => ucfirst( $hub_key ),
				'vertical' => $vertical,
				'business_name' => $business_name,
				'company_name' => $business_name,
				'phone' => $phone,
				'email' => $email,
				'address' => $address,
				'cta_text' => $cta_text,
				'service_area_label' => $service_area_label,
				'service' => '',
				'city' => '',
				'state' => '',
			);
		}
		
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		
		$job_name = 'Wizard - Phase 1: Service Hubs - ' . current_time( 'Y-m-d H:i:s' );
		
		// Debug logging
		error_log( '[WIZARD] Creating Service Hub job with ' . count( $api_items ) . ' items' );
		error_log( '[WIZARD] First item payload: ' . wp_json_encode( $api_items[0] ) );
		error_log( '[WIZARD] All items: ' . wp_json_encode( $api_items ) );
		
		$result = $this->call_api_create_bulk_job( $admin, $api_url, $license_key, $job_name, $api_items );
		
		// Log result
		error_log( '[WIZARD] API result: ' . wp_json_encode( $result ) );
		
		if ( $result['success'] ) {
			// Update phase state
			$state = $this->get_wizard_state();
			$state['generation']['phases']['service_hubs']['status'] = 'running';
			$state['generation']['phases']['service_hubs']['job_id'] = $result['job_id'];
			$this->update_wizard_state( $state );
		} else {
			// Add debug info to error
			$result['debug_items_sent'] = $api_items;
		}
		
		return $result;
	}
	
	/**
	 * Call API to create bulk job using admin class method
	 */
	private function call_api_create_bulk_job( $admin, $api_url, $license_key, $job_name, $items ) {
		// Use reflection to call private method
		$method = new ReflectionMethod( $admin, 'api_create_bulk_job' );
		$method->setAccessible( true );
		$response = $method->invoke( $admin, $api_url, $license_key, $job_name, $items );
		
		if ( empty( $response['ok'] ) || ! is_array( $response['data'] ) || empty( $response['data']['job_id'] ) ) {
			return array(
				'success' => false,
				'error' => isset( $response['error'] ) ? $response['error'] : 'Failed to create bulk job',
			);
		}
		
		return array(
			'success' => true,
			'job_id' => $response['data']['job_id'],
		);
	}
	
	/**
	 * AJAX: Process generation batch - polls API and imports results with phase transitions
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		// Get wizard state to determine current phase
		$state = $this->get_wizard_state();
		$current_phase = isset( $state['generation']['current_phase'] ) ? $state['generation']['current_phase'] : null;
		
		if ( ! $current_phase ) {
			wp_send_json_error( array( 'message' => 'No active generation phase' ) );
		}
		
		$phase_data = $state['generation']['phases'][ $current_phase ];
		$api_job_id = $phase_data['job_id'];
		
		if ( empty( $api_job_id ) ) {
			wp_send_json_error( array( 'message' => 'No job ID for current phase' ) );
		}
		
		// Get settings
		$settings = get_option( 'seogen_settings', array() );
		$api_url = isset( $settings['api_url'] ) ? trim( $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		
		// Get admin instance
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		
		// Get job status from API
		$status_result = $this->call_api_get_job_status( $admin, $api_url, $license_key, $api_job_id );
		
		if ( ! $status_result['success'] ) {
			wp_send_json_error( array( 'message' => $status_result['error'] ) );
		}
		
		$status_data = $status_result['data'];
		$job_status = isset( $status_data['status'] ) ? $status_data['status'] : 'pending';
		$total = isset( $status_data['total_items'] ) ? (int) $status_data['total_items'] : 0;
		$completed = isset( $status_data['completed'] ) ? (int) $status_data['completed'] : 0;
		$failed = isset( $status_data['failed'] ) ? (int) $status_data['failed'] : 0;
		
		// Get cursor for this phase
		$cursor_key = 'api_cursor_' . $current_phase;
		$cursor = isset( $state[ $cursor_key ] ) ? $state[ $cursor_key ] : '';
		
		// Fetch results from API (batch of 10)
		$results_response = $this->call_api_get_job_results( $admin, $api_url, $license_key, $api_job_id, $cursor, 10 );
		
		$batch_results = array();
		$new_cursor = $cursor;
		$newly_imported = 0;
		
		if ( $results_response['success'] && ! empty( $results_response['items'] ) ) {
			$new_cursor = isset( $results_response['cursor'] ) ? $results_response['cursor'] : '';
			
			// Import each completed page
			foreach ( $results_response['items'] as $item ) {
				$item_status = isset( $item['status'] ) ? $item['status'] : '';
				
				if ( $item_status === 'completed' && isset( $item['result_json'] ) ) {
					$import_result = $this->import_page_from_api_result( $item, $admin );
					
					if ( $import_result['success'] ) {
						$newly_imported++;
						$batch_results[] = array(
							'success' => true,
							'title' => $import_result['title'],
							'post_id' => $import_result['post_id'],
						);
					} else {
						$batch_results[] = array(
							'success' => false,
							'error' => $import_result['error'],
						);
					}
				} elseif ( $item_status === 'failed' ) {
					$batch_results[] = array(
						'success' => false,
						'error' => isset( $item['error'] ) ? $item['error'] : 'Generation failed',
					);
				}
			}
		}
		
		// Update phase progress
		$state['generation']['phases'][ $current_phase ]['completed'] = $completed;
		$state['generation']['phases'][ $current_phase ]['failed'] = $failed;
		$state[ $cursor_key ] = $new_cursor;
		
		// Check if current phase is complete
		$is_phase_complete = ( $job_status === 'completed' || $job_status === 'complete' );
		
		if ( $is_phase_complete ) {
			// Mark phase as completed
			$state['generation']['phases'][ $current_phase ]['status'] = 'completed';
			
			// Transition to next phase
			if ( $current_phase === 'service_hubs' ) {
				// Start Phase 2: Service+City
				$state['generation']['current_phase'] = 'service_city';
				$this->update_wizard_state( $state );
				
				$result = $this->start_phase_service_city();
				
				if ( ! $result['success'] ) {
					wp_send_json_error( array( 'message' => 'Failed to start Phase 2: ' . $result['error'] ) );
				}
				
				wp_send_json_success( array(
					'status' => 'phase_transition',
					'next_phase' => 'service_city',
					'phase_number' => 2,
					'message' => 'Phase 1 complete! Starting Phase 2: Service+City pages',
					'phase_1_completed' => $completed,
					'phase_1_failed' => $failed,
				) );
				
			} elseif ( $current_phase === 'service_city' ) {
				// Start Phase 3: City Hubs
				$state['generation']['current_phase'] = 'city_hubs';
				$this->update_wizard_state( $state );
				
				$result = $this->start_phase_city_hubs();
				
				if ( ! $result['success'] ) {
					wp_send_json_error( array( 'message' => 'Failed to start Phase 3: ' . $result['error'] ) );
				}
				
				wp_send_json_success( array(
					'status' => 'phase_transition',
					'next_phase' => 'city_hubs',
					'phase_number' => 3,
					'message' => 'Phase 2 complete! Starting Phase 3: City Hub pages',
					'phase_2_completed' => $completed,
					'phase_2_failed' => $failed,
				) );
				
			} elseif ( $current_phase === 'city_hubs' ) {
				// All phases complete!
				$state['generation']['current_phase'] = null;
				$state['generation']['completed_at'] = current_time( 'mysql' );
				$state['completed'] = true;
				$this->update_wizard_state( $state );
				
				$total_pages = $state['generation']['phases']['service_hubs']['completed'] +
				               $state['generation']['phases']['service_city']['completed'] +
				               $state['generation']['phases']['city_hubs']['completed'];
				
				wp_send_json_success( array(
					'status' => 'all_complete',
					'message' => 'All 3 phases complete!',
					'total_pages' => $total_pages,
					'phase_1_completed' => $state['generation']['phases']['service_hubs']['completed'],
					'phase_2_completed' => $state['generation']['phases']['service_city']['completed'],
					'phase_3_completed' => $state['generation']['phases']['city_hubs']['completed'],
				) );
			}
		}
		
		// Update state and continue polling current phase
		$this->update_wizard_state( $state );
		
		wp_send_json_success( array(
			'status' => 'running',
			'phase' => $current_phase,
			'phase_label' => $this->get_phase_label( $current_phase ),
			'completed' => $completed,
			'total' => $total,
			'failed' => $failed,
			'batch_results' => $batch_results,
			'newly_imported' => $newly_imported,
		) );
	}
	
	/**
	 * Get human-readable phase label
	 */
	private function get_phase_label( $phase ) {
		$labels = array(
			'service_hubs' => 'Service Hub Pages',
			'service_city' => 'Service + City Pages',
			'city_hubs' => 'City Hub Pages',
		);
		return isset( $labels[ $phase ] ) ? $labels[ $phase ] : $phase;
	}
	
	/**
	 * Start Phase 2: Service+City generation
	 */
	private function start_phase_service_city() {
		$services = get_option( 'hyper_local_services_cache', array() );
		$cities = get_option( 'hyper_local_cities_cache', array() );
		$business_config = get_option( 'seogen_business_config', array() );
		$settings = get_option( 'seogen_settings', array() );
		
		$api_url = isset( $settings['api_url'] ) ? trim( $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		$vertical = isset( $settings['vertical'] ) ? trim( $settings['vertical'] ) : '';
		
		if ( empty( $api_url ) || empty( $license_key ) ) {
			return array(
				'success' => false,
				'error' => 'API settings not configured',
			);
		}
		
		// Build Service+City items
		$api_items = array();
		$business_name = isset( $business_config['business_name'] ) ? $business_config['business_name'] : '';
		$phone = isset( $business_config['phone'] ) ? $business_config['phone'] : '';
		$email = isset( $business_config['email'] ) ? $business_config['email'] : '';
		$address = isset( $business_config['address'] ) ? $business_config['address'] : '';
		$cta_text = isset( $business_config['cta_text'] ) ? $business_config['cta_text'] : 'Request a Free Estimate';
		$service_area_label = isset( $business_config['service_area_label'] ) ? $business_config['service_area_label'] : '';
		
		foreach ( $services as $service ) {
			$service_name = is_array( $service ) ? $service['name'] : $service;
			foreach ( $cities as $city ) {
				$city_name = is_array( $city ) ? $city['city'] : $city;
				$state = is_array( $city ) && isset( $city['state'] ) ? $city['state'] : '';
				
				$api_items[] = array(
					'page_mode' => 'service_city',
					'service' => $service_name,
					'city' => $city_name,
					'state' => $state,
					'vertical' => $vertical,
					'business_name' => $business_name,
					'company_name' => $business_name,
					'phone' => $phone,
					'email' => $email,
					'address' => $address,
					'cta_text' => $cta_text,
					'service_area_label' => $service_area_label,
				);
			}
		}
		
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		
		$job_name = 'Wizard - Phase 2: Service+City - ' . current_time( 'Y-m-d H:i:s' );
		$result = $this->call_api_create_bulk_job( $admin, $api_url, $license_key, $job_name, $api_items );
		
		if ( $result['success'] ) {
			$state = $this->get_wizard_state();
			$state['generation']['phases']['service_city']['status'] = 'running';
			$state['generation']['phases']['service_city']['job_id'] = $result['job_id'];
			$this->update_wizard_state( $state );
		}
		
		return $result;
	}
	
	/**
	 * Start Phase 3: City Hub generation
	 */
	private function start_phase_city_hubs() {
		$cities = get_option( 'hyper_local_cities_cache', array() );
		$business_config = get_option( 'seogen_business_config', array() );
		$settings = get_option( 'seogen_settings', array() );
		
		$api_url = isset( $settings['api_url'] ) ? trim( $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		$vertical = isset( $settings['vertical'] ) ? trim( $settings['vertical'] ) : '';
		
		if ( empty( $api_url ) || empty( $license_key ) ) {
			return array(
				'success' => false,
				'error' => 'API settings not configured',
			);
		}
		
		// Build City Hub items
		$api_items = array();
		$business_name = isset( $business_config['business_name'] ) ? $business_config['business_name'] : '';
		$phone = isset( $business_config['phone'] ) ? $business_config['phone'] : '';
		$email = isset( $business_config['email'] ) ? $business_config['email'] : '';
		$address = isset( $business_config['address'] ) ? $business_config['address'] : '';
		$cta_text = isset( $business_config['cta_text'] ) ? $business_config['cta_text'] : 'Request a Free Estimate';
		$service_area_label = isset( $business_config['service_area_label'] ) ? $business_config['service_area_label'] : '';
		
		foreach ( $cities as $city ) {
			$city_name = is_array( $city ) ? $city['city'] : $city;
			$state = is_array( $city ) && isset( $city['state'] ) ? $city['state'] : '';
			
			$api_items[] = array(
				'page_mode' => 'city_hub',
				'city' => $city_name,
				'state' => $state,
				'vertical' => $vertical,
				'business_name' => $business_name,
				'company_name' => $business_name,
				'phone' => $phone,
				'email' => $email,
				'address' => $address,
				'cta_text' => $cta_text,
				'service_area_label' => $service_area_label,
				'service' => '',
			);
		}
		
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		
		$job_name = 'Wizard - Phase 3: City Hubs - ' . current_time( 'Y-m-d H:i:s' );
		$result = $this->call_api_create_bulk_job( $admin, $api_url, $license_key, $job_name, $api_items );
		
		if ( $result['success'] ) {
			$state = $this->get_wizard_state();
			$state['generation']['phases']['city_hubs']['status'] = 'running';
			$state['generation']['phases']['city_hubs']['job_id'] = $result['job_id'];
			$this->update_wizard_state( $state );
		}
		
		return $result;
	}
	
	/**
	 * Call API to get job status
	 */
	private function call_api_get_job_status( $admin, $api_url, $license_key, $api_job_id ) {
		$method = new ReflectionMethod( $admin, 'api_get_bulk_job_status' );
		$method->setAccessible( true );
		$response = $method->invoke( $admin, $api_url, $license_key, $api_job_id );
		
		if ( empty( $response['ok'] ) || ! is_array( $response['data'] ) ) {
			return array(
				'success' => false,
				'error' => isset( $response['error'] ) ? $response['error'] : 'Failed to get job status',
			);
		}
		
		return array(
			'success' => true,
			'data' => $response['data'],
		);
	}
	
	/**
	 * Call API to get job results
	 */
	private function call_api_get_job_results( $admin, $api_url, $license_key, $api_job_id, $cursor, $limit ) {
		$method = new ReflectionMethod( $admin, 'api_get_bulk_job_results' );
		$method->setAccessible( true );
		$response = $method->invoke( $admin, $api_url, $license_key, $api_job_id, $cursor, $limit );
		
		if ( empty( $response['ok'] ) || ! is_array( $response['data'] ) ) {
			return array(
				'success' => false,
				'error' => isset( $response['error'] ) ? $response['error'] : 'Failed to get results',
			);
		}
		
		return array(
			'success' => true,
			'items' => isset( $response['data']['items'] ) ? $response['data']['items'] : array(),
			'cursor' => isset( $response['data']['next_cursor'] ) ? $response['data']['next_cursor'] : '',
		);
	}
	
	/**
	 * Import a page from API result
	 */
	private function import_page_from_api_result( $item, $admin ) {
		$result_json = isset( $item['result_json'] ) ? $item['result_json'] : null;
		
		if ( ! is_array( $result_json ) ) {
			return array(
				'success' => false,
				'error' => 'Invalid result data',
			);
		}
		
		$title = isset( $result_json['title'] ) ? $result_json['title'] : '';
		$slug = isset( $result_json['slug'] ) ? $result_json['slug'] : '';
		$meta_description = isset( $result_json['meta_description'] ) ? $result_json['meta_description'] : '';
		$blocks = isset( $result_json['blocks'] ) && is_array( $result_json['blocks'] ) ? $result_json['blocks'] : array();
		$page_mode = isset( $result_json['page_mode'] ) ? $result_json['page_mode'] : '';
		$canonical_key = isset( $item['canonical_key'] ) ? $item['canonical_key'] : '';
		
		// Get settings and config
		$settings_method = new ReflectionMethod( $admin, 'get_settings' );
		$settings_method->setAccessible( true );
		$settings = $settings_method->invoke( $admin );
		
		$config_method = new ReflectionMethod( $admin, 'get_business_config' );
		$config_method->setAccessible( true );
		$config = $config_method->invoke( $admin );
		
		// Build Gutenberg content
		$build_method = new ReflectionMethod( $admin, 'build_gutenberg_content_from_blocks' );
		$build_method->setAccessible( true );
		$content = $build_method->invoke( $admin, $blocks, $page_mode );
		
		// Apply quality improvements based on page mode - EXACT same as individual generation
		if ( $page_mode === 'service_hub' ) {
			$hub_label = isset( $item['hub_label'] ) ? $item['hub_label'] : '';
			if ( $hub_label ) {
				$quality_method = new ReflectionMethod( $admin, 'apply_service_hub_quality_improvements' );
				$quality_method->setAccessible( true );
				$content = $quality_method->invoke( $admin, $content, $hub_label );
			}
		} elseif ( $page_mode === 'city_hub' ) {
			$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
			$city_name = isset( $item['city'] ) ? $item['city'] : '';
			$city_state = isset( $item['state'] ) ? $item['state'] : '';
			$city = array( 'name' => $city_name, 'state' => $city_state );
			$vertical = isset( $config['vertical'] ) ? $config['vertical'] : '';
			
			if ( $hub_key && $city_name ) {
				$quality_method = new ReflectionMethod( $admin, 'apply_city_hub_quality_improvements' );
				$quality_method->setAccessible( true );
				$content = $quality_method->invoke( $admin, $content, $hub_key, $city, $vertical );
			}
		}
		
		// Prepend header template - EXACT same as individual generation
		$header_template_id = isset( $settings['header_template_id'] ) ? (int) $settings['header_template_id'] : 0;
		if ( $header_template_id > 0 ) {
			$template_method = new ReflectionMethod( $admin, 'get_template_content' );
			$template_method->setAccessible( true );
			$header_content = $template_method->invoke( $admin, $header_template_id );
			if ( '' !== $header_content ) {
				$css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-top: 0 !important; margin-top: 0 !important; }</style><!-- /wp:html -->';
				$content = $css_block . $header_content . $content;
			}
		}
		
		// Append footer template - EXACT same as individual generation
		$footer_template_id = isset( $settings['footer_template_id'] ) ? (int) $settings['footer_template_id'] : 0;
		if ( $footer_template_id > 0 ) {
			$template_method = new ReflectionMethod( $admin, 'get_template_content' );
			$template_method->setAccessible( true );
			$footer_content = $template_method->invoke( $admin, $footer_template_id );
			if ( '' !== $footer_content ) {
				$footer_css_block = '<!-- wp:html --><style>.entry-content, .site-content, article, .elementor, .content-area { padding-bottom: 0 !important; margin-bottom: 0 !important; }</style><!-- /wp:html -->';
				$content = $content . $footer_css_block . $footer_content;
			}
		}
		
		// Check for existing page based on page mode
		$existing_post_id = 0;
		if ( $page_mode === 'service_hub' ) {
			$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
			if ( $hub_key ) {
				$find_hub_method = new ReflectionMethod( $admin, 'find_service_hub_post_id' );
				$find_hub_method->setAccessible( true );
				$existing_post_id = $find_hub_method->invoke( $admin, $hub_key );
			}
		} elseif ( $page_mode === 'city_hub' ) {
			$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
			$city_slug = isset( $item['city_slug'] ) ? $item['city_slug'] : '';
			if ( $hub_key && $city_slug ) {
				$find_city_hub_method = new ReflectionMethod( $admin, 'find_city_hub_post_id' );
				$find_city_hub_method->setAccessible( true );
				$existing_post_id = $find_city_hub_method->invoke( $admin, $hub_key, $city_slug );
			}
		}
		
		// Determine post parent for city hubs
		$post_parent = 0;
		if ( $page_mode === 'city_hub' ) {
			$hub_key = isset( $item['hub_key'] ) ? $item['hub_key'] : '';
			if ( $hub_key ) {
				$find_hub_method = new ReflectionMethod( $admin, 'find_service_hub_post_id' );
				$find_hub_method->setAccessible( true );
				$post_parent = $find_hub_method->invoke( $admin, $hub_key );
			}
		}
		
		// Create or update post
		$post_data = array(
			'post_type'    => 'service_page',
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_name'    => sanitize_title( $slug ),
			'post_content' => $content,
			'post_parent'  => $post_parent,
		);
		
		if ( $existing_post_id > 0 ) {
			// Update existing post
			$post_data['ID'] = $existing_post_id;
			unset( $post_data['post_name'] ); // Avoid slug conflicts on update
			$post_id = wp_update_post( $post_data, true );
		} else {
			// Create new post
			$post_id = wp_insert_post( $post_data, true );
		}
		
		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'error' => $post_id->get_error_message(),
			);
		}
		
		$post_id = (int) $post_id;
		
		// Save ALL metadata - EXACT same as individual generation
		update_post_meta( $post_id, '_hyper_local_managed', '1' );
		update_post_meta( $post_id, '_seogen_page_mode', $page_mode );
		update_post_meta( $post_id, '_hyper_local_source_json', wp_json_encode( $result_json ) );
		update_post_meta( $post_id, '_hyper_local_generated_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, '_hyper_local_meta_description', $meta_description );
		update_post_meta( $post_id, '_hyper_local_key', $canonical_key );
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_description );
		
		if ( isset( $config['vertical'] ) ) {
			update_post_meta( $post_id, '_seogen_vertical', $config['vertical'] );
		}
		
		// Page-mode specific metadata
		if ( $page_mode === 'service_hub' ) {
			update_post_meta( $post_id, '_hl_page_type', 'service_hub' );
			if ( isset( $item['hub_key'] ) ) {
				update_post_meta( $post_id, '_seogen_hub_key', $item['hub_key'] );
			}
			if ( isset( $item['hub_slug'] ) ) {
				update_post_meta( $post_id, '_seogen_hub_slug', $item['hub_slug'] );
			}
		} elseif ( $page_mode === 'city_hub' ) {
			if ( isset( $item['hub_key'] ) ) {
				update_post_meta( $post_id, '_seogen_hub_key', $item['hub_key'] );
			}
			if ( isset( $item['hub_slug'] ) ) {
				update_post_meta( $post_id, '_seogen_hub_slug', $item['hub_slug'] );
			}
			if ( isset( $item['city'] ) && isset( $item['state'] ) ) {
				update_post_meta( $post_id, '_seogen_city', $item['city'] . ', ' . $item['state'] );
			}
			if ( isset( $item['city_slug'] ) ) {
				update_post_meta( $post_id, '_seogen_city_slug', $item['city_slug'] );
			}
		} else {
			// service_city mode
			if ( isset( $item['service'] ) ) {
				update_post_meta( $post_id, '_hyper_local_service_name', $item['service'] );
			}
			if ( isset( $item['city'] ) ) {
				update_post_meta( $post_id, '_hyper_local_city_name', $item['city'] );
			}
			if ( isset( $item['state'] ) && ! empty( $item['state'] ) ) {
				update_post_meta( $post_id, '_hyper_local_state', $item['state'] );
			}
		}
		
		// Apply SEO plugin meta - EXACT same as individual generation
		$focus_keyword = '';
		if ( $page_mode === 'service_hub' ) {
			$hub_label = isset( $item['hub_label'] ) ? $item['hub_label'] : '';
			$focus_keyword = $hub_label . ' Services';
		} elseif ( $page_mode === 'city_hub' ) {
			$hub_label = isset( $item['hub_label'] ) ? $item['hub_label'] : '';
			$city_name = isset( $item['city'] ) ? $item['city'] : '';
			$focus_keyword = $hub_label . ' ' . $city_name;
		} else {
			$service = isset( $item['service'] ) ? $item['service'] : '';
			$city = isset( $item['city'] ) ? $item['city'] : '';
			$focus_keyword = $service . ' ' . $city;
		}
		
		if ( $focus_keyword ) {
			$seo_method = new ReflectionMethod( $admin, 'apply_seo_plugin_meta' );
			$seo_method->setAccessible( true );
			$seo_method->invoke( $admin, $post_id, $focus_keyword, $title, $meta_description, true );
		}
		
		// Apply page builder settings - EXACT same as individual generation
		if ( ! empty( $settings['disable_theme_header_footer'] ) ) {
			$apply_method = new ReflectionMethod( $admin, 'apply_page_builder_settings' );
			$apply_method->setAccessible( true );
			$apply_method->invoke( $admin, $post_id );
		}
		
		// Update slug for city hubs - EXACT same as individual generation
		if ( $page_mode === 'city_hub' ) {
			$unique_slug = wp_unique_post_slug( sanitize_title( $slug ), $post_id, 'draft', 'service_page', $post_parent );
			if ( $unique_slug ) {
				wp_update_post( array(
					'ID' => $post_id,
					'post_name' => $unique_slug,
				) );
			}
		}
		
		return array(
			'success' => true,
			'post_id' => $post_id,
			'title' => $title,
		);
	}
	
	/**
	 * AJAX: Get generation progress
	 */
	public function ajax_generation_progress() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( $_POST['job_id'] ) : '';
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => 'Job ID required' ) );
		}
		
		$job_data = get_transient( 'seogen_wizard_job_' . $job_id );
		if ( ! $job_data ) {
			wp_send_json_error( array( 'message' => 'Job not found' ) );
		}
		
		wp_send_json_success( array(
			'status' => $job_data['status'],
			'total' => $job_data['total'],
			'processed' => $job_data['processed'],
			'successful' => $job_data['successful'],
			'failed' => $job_data['failed'],
			'complete' => $job_data['status'] === 'completed',
		) );
	}
	
	/**
	 * AJAX: Skip generation
	 */
	public function ajax_skip_generation() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		// Mark wizard as completed without generation
		$this->update_wizard_state( array(
			'completed' => true,
		) );
		
		wp_send_json_success( array(
			'message' => 'Wizard completed',
			'redirect' => admin_url( 'admin.php?page=hyper-local' ),
		) );
	}
	
	/**
	 * AJAX: Dismiss wizard
	 */
	public function ajax_dismiss_wizard() {
		check_ajax_referer( 'seogen_wizard_dismiss', '_ajax_nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		update_option( self::WIZARD_DISMISSED_OPTION, true );
		
		wp_send_json_success( array(
			'message' => 'Wizard dismissed',
		) );
	}
	
	/**
	 * AJAX: Reset wizard
	 */
	public function ajax_reset_wizard() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$this->reset_wizard();
		
		wp_send_json_success( array(
			'message' => 'Wizard reset successfully',
			'redirect' => admin_url( 'admin.php?page=seogen-wizard' ),
		) );
	}
	
	/**
	 * Handle settings form submission
	 */
	public function handle_save_settings() {
		check_admin_referer( 'seogen_wizard_save_settings' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}
		
		// Get posted data
		$posted_settings = isset( $_POST['seogen_settings'] ) ? $_POST['seogen_settings'] : array();
		
		// Get existing settings to preserve other fields
		$existing_settings = get_option( 'seogen_settings', array() );
		
		// Sanitize settings - ensure api_url has default if empty
		$api_url = isset( $posted_settings['api_url'] ) ? trim( $posted_settings['api_url'] ) : '';
		if ( empty( $api_url ) ) {
			$api_url = 'https://seogen-production.up.railway.app';
		}
		
		// Merge with existing settings to preserve header/footer templates, etc.
		$sanitized = array_merge( $existing_settings, array(
			'api_url' => esc_url_raw( $api_url ),
			'license_key' => isset( $posted_settings['license_key'] ) ? sanitize_text_field( $posted_settings['license_key'] ) : '',
		) );
		
		error_log( '[WIZARD] Saving settings - api_url: ' . $sanitized['api_url'] . ', license_key: ' . ( $sanitized['license_key'] ? 'SET' : 'EMPTY' ) );
		
		// Save settings
		update_option( 'seogen_settings', $sanitized );
		
		// Return JSON for AJAX
		wp_send_json_success( array(
			'message' => 'Settings saved successfully',
			'debug_saved' => array(
				'api_url' => $sanitized['api_url'],
				'license_key_length' => strlen( $sanitized['license_key'] ),
			),
		) );
	}
	
	/**
	 * Handle business config form submission
	 */
	public function handle_save_business() {
		check_admin_referer( 'seogen_wizard_save_business' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}
		
		// Get posted data
		$config = isset( $_POST['seogen_business_config'] ) ? $_POST['seogen_business_config'] : array();
		
		// Sanitize hub categories
		$hub_categories = isset( $config['hub_categories'] ) && is_array( $config['hub_categories'] ) 
			? array_map( 'sanitize_text_field', $config['hub_categories'] ) 
			: array( 'residential', 'commercial' );
		
		// Sanitize config
		$sanitized = array(
			'vertical' => isset( $config['vertical'] ) ? sanitize_text_field( $config['vertical'] ) : '',
			'business_name' => isset( $config['business_name'] ) ? sanitize_text_field( $config['business_name'] ) : '',
			'phone' => isset( $config['phone'] ) ? sanitize_text_field( $config['phone'] ) : '',
			'email' => isset( $config['email'] ) ? sanitize_email( $config['email'] ) : '',
			'address' => isset( $config['address'] ) ? sanitize_text_field( $config['address'] ) : '',
			'cta_text' => isset( $config['cta_text'] ) ? sanitize_text_field( $config['cta_text'] ) : 'Request a Free Estimate',
			'service_area_label' => isset( $config['service_area_label'] ) ? sanitize_text_field( $config['service_area_label'] ) : '',
			'hub_categories' => $hub_categories,
		);
		
		// Save config
		update_option( 'seogen_business_config', $sanitized );
		
		// Return JSON for AJAX
		wp_send_json_success( array(
			'message' => 'Business config saved successfully',
		) );
	}
	
	/**
	 * AJAX: Add service
	 */
	public function ajax_add_service() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$service_name = isset( $_POST['service_name'] ) ? sanitize_text_field( $_POST['service_name'] ) : '';
		$service_hub = isset( $_POST['service_hub'] ) ? sanitize_text_field( $_POST['service_hub'] ) : '';
		
		if ( empty( $service_name ) ) {
			wp_send_json_error( array( 'message' => 'Service name is required' ) );
		}
		
		// Get existing services
		$services = get_option( 'hyper_local_services_cache', array() );
		if ( ! is_array( $services ) ) {
			$services = array();
		}
		
		// Add new service with hub category
		$new_service = array( 'name' => $service_name );
		if ( ! empty( $service_hub ) ) {
			$new_service['hub'] = $service_hub;
		}
		$services[] = $new_service;
		
		// Save services
		update_option( 'hyper_local_services_cache', $services );
		
		wp_send_json_success( array(
			'message' => 'Service added successfully',
			'count' => count( $services ),
		) );
	}
	
	/**
	 * AJAX: Bulk add services
	 */
	public function ajax_bulk_add_services() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$bulk_text = isset( $_POST['bulk_text'] ) ? sanitize_textarea_field( $_POST['bulk_text'] ) : '';
		
		if ( empty( $bulk_text ) ) {
			wp_send_json_error( array( 'message' => 'Bulk text is required' ) );
		}
		
		// Get existing services
		$services = get_option( 'hyper_local_services_cache', array() );
		if ( ! is_array( $services ) ) {
			$services = array();
		}
		
		// Get hub categories for default
		$config = get_option( 'seogen_business_config', array() );
		$hub_categories = isset( $config['hub_categories'] ) && is_array( $config['hub_categories'] ) 
			? $config['hub_categories'] 
			: array( 'residential', 'commercial' );
		$default_hub = ! empty( $hub_categories ) ? $hub_categories[0] : 'residential';
		
		// Parse bulk text
		$lines = explode( "\n", $bulk_text );
		$added_count = 0;
		
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( empty( $line ) ) {
				continue;
			}
			
			// Check if line has hub prefix (e.g., "residential: Service Name")
			if ( strpos( $line, ':' ) !== false ) {
				$parts = explode( ':', $line, 2 );
				$hub = trim( $parts[0] );
				$service_name = trim( $parts[1] );
				
				// Validate hub exists in configured hubs
				if ( in_array( $hub, $hub_categories ) && ! empty( $service_name ) ) {
					$services[] = array(
						'name' => $service_name,
						'hub' => $hub,
					);
					$added_count++;
				}
			} else {
				// No hub specified, use default
				$services[] = array(
					'name' => $line,
					'hub' => $default_hub,
				);
				$added_count++;
			}
		}
		
		// Save services
		update_option( 'hyper_local_services_cache', $services );
		
		wp_send_json_success( array(
			'message' => sprintf( '%d services added successfully', $added_count ),
			'count' => count( $services ),
			'added' => $added_count,
		) );
	}
	
	/**
	 * AJAX: Delete service
	 */
	public function ajax_delete_service() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		
		if ( $index < 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid index' ) );
		}
		
		// Get existing services
		$services = get_option( 'hyper_local_services_cache', array() );
		if ( ! is_array( $services ) ) {
			$services = array();
		}
		
		// Remove service at index
		if ( isset( $services[ $index ] ) ) {
			array_splice( $services, $index, 1 );
			update_option( 'hyper_local_services_cache', $services );
			
			wp_send_json_success( array(
				'message' => 'Service deleted successfully',
				'count' => count( $services ),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'Service not found' ) );
		}
	}
	
	/**
	 * AJAX: Add city
	 */
	public function ajax_add_city() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$city_name = isset( $_POST['city_name'] ) ? sanitize_text_field( $_POST['city_name'] ) : '';
		
		if ( empty( $city_name ) ) {
			wp_send_json_error( array( 'message' => 'City name is required' ) );
		}
		
		// Parse city and state
		$parts = array_map( 'trim', explode( ',', $city_name ) );
		$city = isset( $parts[0] ) ? $parts[0] : '';
		$state = isset( $parts[1] ) ? $parts[1] : '';
		
		// Validate that both city and state are provided
		if ( empty( $city ) || empty( $state ) ) {
			wp_send_json_error( array( 'message' => 'Please enter city in format: City Name, State (e.g., "Tulsa, OK")' ) );
		}
		
		// Validate state is 2 characters (state abbreviation)
		if ( strlen( $state ) !== 2 ) {
			wp_send_json_error( array( 'message' => 'Please use 2-letter state abbreviation (e.g., "OK", "TX", "NY")' ) );
		}
		
		// Get existing cities
		$cities = get_option( 'hyper_local_cities_cache', array() );
		if ( ! is_array( $cities ) ) {
			$cities = array();
		}
		
		// Add new city
		$cities[] = array(
			'name' => $city_name,
			'city' => $city,
			'state' => $state,
		);
		
		// Save cities
		update_option( 'hyper_local_cities_cache', $cities );
		
		wp_send_json_success( array(
			'message' => 'City added successfully',
			'count' => count( $cities ),
		) );
	}
	
	/**
	 * AJAX: Delete city
	 */
	public function ajax_delete_city() {
		check_ajax_referer( 'seogen_wizard_nonce', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$index = isset( $_POST['index'] ) ? intval( $_POST['index'] ) : -1;
		
		if ( $index < 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid index' ) );
		}
		
		// Get existing cities
		$cities = get_option( 'hyper_local_cities_cache', array() );
		if ( ! is_array( $cities ) ) {
			$cities = array();
		}
		
		// Remove city at index
		if ( isset( $cities[ $index ] ) ) {
			array_splice( $cities, $index, 1 );
			update_option( 'hyper_local_cities_cache', $cities );
			
			wp_send_json_success( array(
				'message' => 'City deleted successfully',
				'count' => count( $cities ),
			) );
		} else {
			wp_send_json_error( array( 'message' => 'City not found' ) );
		}
	}
}
