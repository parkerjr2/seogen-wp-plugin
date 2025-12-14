<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Admin {
	const OPTION_NAME = 'seogen_settings';

	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Hyper Local Settings', 'seogen' ),
			__( 'Hyper Local', 'seogen' ),
			'manage_options',
			'seogen-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			59
		);
	}

	public function register_settings() {
		register_setting(
			'seogen_settings_group',
			self::OPTION_NAME,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'seogen_settings_section_main',
			__( 'API Settings', 'seogen' ),
			'__return_false',
			'seogen-settings'
		);

		add_settings_field(
			'seogen_api_url',
			__( 'API Base URL', 'seogen' ),
			array( $this, 'render_field_api_url' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);

		add_settings_field(
			'seogen_license_key',
			__( 'License Key', 'seogen' ),
			array( $this, 'render_field_license_key' ),
			'seogen-settings',
			'seogen_settings_section_main'
		);
	}

	public function sanitize_settings( $input ) {
		$sanitized = array();

		$api_url = '';
		if ( isset( $input['api_url'] ) ) {
			$api_url = esc_url_raw( $input['api_url'] );
		}
		$sanitized['api_url'] = $api_url;

		$license_key = '';
		if ( isset( $input['license_key'] ) ) {
			$license_key = sanitize_text_field( $input['license_key'] );
		}
		$sanitized['license_key'] = $license_key;

		return $sanitized;
	}

	private function get_settings() {
		$defaults = array(
			'api_url'      => 'https://seogen-production.up.railway.app',
			'license_key'  => '',
		);

		$settings = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, $defaults );
	}

	public function render_field_api_url() {
		$settings = $this->get_settings();

		printf(
			'<input type="url" class="regular-text" name="%1$s[api_url]" value="%2$s" placeholder="https://seogen-production.up.railway.app" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['api_url'] )
		);
	}

	public function render_field_license_key() {
		$settings = $this->get_settings();

		printf(
			'<input type="text" class="regular-text" name="%1$s[license_key]" value="%2$s" autocomplete="off" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['license_key'] )
		);
	}

	private function check_api_health( $api_url ) {
		$api_url = (string) $api_url;
		$api_url = trim( $api_url );

		if ( '' === $api_url ) {
			return array(
				'ok'    => false,
				'error' => __( 'API URL is empty.', 'seogen' ),
			);
		}

		$health_url = trailingslashit( $api_url ) . 'health';

		$response = wp_remote_get(
			$health_url,
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'    => false,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			return array(
				'ok'    => false,
				'error' => sprintf( __( 'Unexpected HTTP status: %d', 'seogen' ), $code ),
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => __( 'Invalid JSON response.', 'seogen' ),
			);
		}

		if ( isset( $data['status'] ) && 'ok' === $data['status'] ) {
			return array( 'ok' => true );
		}

		return array(
			'ok'    => false,
			'error' => __( 'Health check did not return status=ok.', 'seogen' ),
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$status   = $this->check_api_health( $settings['api_url'] );
		$has_license_key = ( isset( $settings['license_key'] ) && '' !== trim( (string) $settings['license_key'] ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Hyper Local Settings', 'seogen' ); ?></h1>

			<p>
				<strong><?php echo esc_html__( 'License Key:', 'seogen' ); ?></strong>
				<?php if ( $has_license_key ) : ?>
					<span style="color: #0a7d00; font-weight: 600;">✅ <?php echo esc_html__( 'Set', 'seogen' ); ?></span>
				<?php else : ?>
					<span style="color: #b32d2e; font-weight: 600;">❌ <?php echo esc_html__( 'Missing', 'seogen' ); ?></span>
				<?php endif; ?>
			</p>

			<p>
				<strong><?php echo esc_html__( 'API Connection:', 'seogen' ); ?></strong>
				<?php if ( ! empty( $status['ok'] ) ) : ?>
					<span style="color: #0a7d00; font-weight: 600;">✅ <?php echo esc_html__( 'Connected', 'seogen' ); ?></span>
				<?php else : ?>
					<span style="color: #b32d2e; font-weight: 600;">❌ <?php echo esc_html__( 'Not Connected', 'seogen' ); ?></span>
				<?php endif; ?>
			</p>
			<?php if ( empty( $status['ok'] ) && ! empty( $status['error'] ) ) : ?>
				<p class="description"><?php echo esc_html( $status['error'] ); ?></p>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'seogen_settings_group' );
				do_settings_sections( 'seogen-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
