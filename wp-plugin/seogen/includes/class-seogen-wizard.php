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
			'auto_generation' => array(
				'service_hubs' => 'pending',
				'service_pages' => 'pending',
				'city_hubs' => 'pending',
				'job_ids' => array(),
				'created_posts' => array(),
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
				'nonce' => wp_create_nonce( 'seogen_wizard' ),
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
	 * AJAX: Start automated generation
	 */
	public function ajax_start_generation() {
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		// Get services and cities from cache
		$services = get_option( 'hyper_local_services_cache', array() );
		$cities = get_option( 'hyper_local_cities_cache', array() );
		
		if ( empty( $services ) || empty( $cities ) ) {
			wp_send_json_error( array( 'message' => 'No services or cities configured' ) );
		}
		
		// Get settings
		$settings = get_option( 'seogen_settings', array() );
		$api_url = isset( $settings['api_url'] ) ? trim( $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( $settings['license_key'] ) : '';
		
		if ( empty( $api_url ) || empty( $license_key ) ) {
			wp_send_json_error( array( 'message' => 'API settings not configured' ) );
		}
		
		// Create job ID
		$job_id = sanitize_key( 'wizard_job_' . wp_generate_password( 12, false, false ) );
		
		// Build rows for generation
		$rows = array();
		foreach ( $services as $service ) {
			$service_name = is_array( $service ) ? $service['name'] : $service;
			foreach ( $cities as $city ) {
				$city_name = is_array( $city ) ? $city['city'] : $city;
				$state = is_array( $city ) && isset( $city['state'] ) ? $city['state'] : '';
				
				$rows[] = array(
					'service' => $service_name,
					'city' => $city_name,
					'state' => $state,
				);
			}
		}
		
		// Store job data
		$job_data = array(
			'job_id' => $job_id,
			'status' => 'pending',
			'total' => count( $rows ),
			'processed' => 0,
			'successful' => 0,
			'failed' => 0,
			'rows' => $rows,
			'settings' => $settings,
			'started_at' => current_time( 'mysql' ),
		);
		
		set_transient( 'seogen_wizard_job_' . $job_id, $job_data, 12 * HOUR_IN_SECONDS );
		
		// Update wizard state
		$this->update_wizard_state( array(
			'generation_job_id' => $job_id,
			'generation_status' => 'running',
		) );
		
		wp_send_json_success( array(
			'message' => 'Generation started',
			'job_id' => $job_id,
			'total' => count( $rows ),
		) );
	}
	
	/**
	 * AJAX: Process generation batch
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied' ) );
		}
		
		$job_id = isset( $_POST['job_id'] ) ? sanitize_text_field( $_POST['job_id'] ) : '';
		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => 'Job ID required' ) );
		}
		
		// Get job data
		$job_data = get_transient( 'seogen_wizard_job_' . $job_id );
		if ( ! $job_data ) {
			wp_send_json_error( array( 'message' => 'Job not found or expired' ) );
		}
		
		// Update status to running
		if ( $job_data['status'] === 'pending' ) {
			$job_data['status'] = 'running';
		}
		
		// Process batch (5 pages at a time)
		$batch_size = 5;
		$processed = $job_data['processed'];
		$rows = $job_data['rows'];
		$settings = $job_data['settings'];
		
		$batch_end = min( $processed + $batch_size, count( $rows ) );
		$results = array();
		
		// Get admin instance for page generation
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
		$admin = new SEOgen_Admin();
		
		for ( $i = $processed; $i < $batch_end; $i++ ) {
			$row = $rows[ $i ];
			
			try {
				// Generate page using existing admin method
				$result = $this->generate_service_city_page( $row, $settings, $admin );
				
				if ( $result['success'] ) {
					$job_data['successful']++;
					$results[] = array(
						'success' => true,
						'service' => $row['service'],
						'city' => $row['city'],
						'post_id' => $result['post_id'],
					);
				} else {
					$job_data['failed']++;
					$results[] = array(
						'success' => false,
						'service' => $row['service'],
						'city' => $row['city'],
						'error' => $result['error'],
					);
				}
			} catch ( Exception $e ) {
				$job_data['failed']++;
				$results[] = array(
					'success' => false,
					'service' => $row['service'],
					'city' => $row['city'],
					'error' => $e->getMessage(),
				);
			}
			
			$job_data['processed']++;
		}
		
		// Check if complete
		if ( $job_data['processed'] >= count( $rows ) ) {
			$job_data['status'] = 'completed';
			$job_data['completed_at'] = current_time( 'mysql' );
			
			// Mark wizard as complete
			$this->update_wizard_state( array(
				'completed' => true,
				'completed_at' => current_time( 'mysql' ),
				'generation_status' => 'completed',
			) );
		}
		
		// Update job data
		set_transient( 'seogen_wizard_job_' . $job_id, $job_data, 12 * HOUR_IN_SECONDS );
		
		wp_send_json_success( array(
			'status' => $job_data['status'],
			'total' => $job_data['total'],
			'processed' => $job_data['processed'],
			'successful' => $job_data['successful'],
			'failed' => $job_data['failed'],
			'batch_results' => $results,
			'complete' => $job_data['status'] === 'completed',
		) );
	}
	
	/**
	 * Generate a single service+city page
	 */
	private function generate_service_city_page( $row, $settings, $admin ) {
		$service = $row['service'];
		$city = $row['city'];
		$state = isset( $row['state'] ) ? $row['state'] : '';
		
		// Build API payload
		$business_config = get_option( 'seogen_business_config', array() );
		
		$payload = array(
			'service_name' => $service,
			'city_name' => $city,
			'state' => $state,
			'business_name' => isset( $business_config['business_name'] ) ? $business_config['business_name'] : '',
			'service_area_label' => isset( $business_config['service_area_label'] ) ? $business_config['service_area_label'] : '',
		);
		
		// Call API
		$api_url = $settings['api_url'];
		$license_key = $settings['license_key'];
		
		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'generate',
			array(
				'timeout' => 60,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-License-Key' => $license_key,
				),
				'body' => wp_json_encode( $payload ),
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error' => $response->get_error_message(),
			);
		}
		
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		
		if ( ! $data || ! isset( $data['content'] ) ) {
			return array(
				'success' => false,
				'error' => 'Invalid API response',
			);
		}
		
		// Create WordPress post
		$title = $service . ' in ' . $city . ( $state ? ', ' . $state : '' );
		$slug = sanitize_title( $title );
		
		$post_data = array(
			'post_title' => $title,
			'post_name' => $slug,
			'post_content' => $data['content'],
			'post_status' => 'publish',
			'post_type' => 'service_page',
		);
		
		$post_id = wp_insert_post( $post_data );
		
		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'error' => $post_id->get_error_message(),
			);
		}
		
		// Save metadata
		update_post_meta( $post_id, '_hyper_local_service_name', $service );
		update_post_meta( $post_id, '_hyper_local_city_name', $city );
		if ( $state ) {
			update_post_meta( $post_id, '_hyper_local_state', $state );
		}
		
		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}
	
	/**
	 * AJAX: Get generation progress
	 */
	public function ajax_generation_progress() {
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		$settings = isset( $_POST['seogen_settings'] ) ? $_POST['seogen_settings'] : array();
		
		// Sanitize settings
		$sanitized = array(
			'api_url' => isset( $settings['api_url'] ) ? esc_url_raw( $settings['api_url'] ) : 'https://seogen-production.up.railway.app',
			'license_key' => isset( $settings['license_key'] ) ? sanitize_text_field( $settings['license_key'] ) : '',
		);
		
		// Save settings
		update_option( 'seogen_settings', $sanitized );
		
		// Return JSON for AJAX
		wp_send_json_success( array(
			'message' => 'Settings saved successfully',
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
		check_ajax_referer( 'seogen_wizard', 'nonce' );
		
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
