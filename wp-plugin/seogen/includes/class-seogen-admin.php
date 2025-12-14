<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Admin {
	const OPTION_NAME = 'seogen_settings';

	public function run() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_seogen_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_post_hyper_local_generate_preview', array( $this, 'handle_generate_preview' ) );
	}

	private function render_generate_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['hl_gen'] ) ) {
			return;
		}

		$gen = sanitize_text_field( wp_unslash( $_GET['hl_gen'] ) );
		$msg = '';
		if ( isset( $_GET['hl_msg'] ) ) {
			$msg = rawurldecode( (string) wp_unslash( $_GET['hl_msg'] ) );
			$msg = sanitize_text_field( $msg );
		}

		if ( 'success' === $gen ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	private function mask_license_key( $license_key ) {
		$license_key = (string) $license_key;
		$license_key = trim( $license_key );

		if ( '' === $license_key ) {
			return '';
		}

		if ( strlen( $license_key ) <= 10 ) {
			return substr( $license_key, 0, 3 ) . '...';
		}

		return substr( $license_key, 0, 5 ) . '...' . substr( $license_key, -3 );
	}

	private function build_generate_preview_payload( $settings, $data ) {
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		return array(
			'license_key' => $license_key,
			'data'        => array(
				'service'      => isset( $data['service'] ) ? (string) $data['service'] : '',
				'city'         => isset( $data['city'] ) ? (string) $data['city'] : '',
				'state'        => isset( $data['state'] ) ? (string) $data['state'] : '',
				'company_name' => isset( $data['company_name'] ) ? (string) $data['company_name'] : '',
				'phone'        => isset( $data['phone'] ) ? (string) $data['phone'] : '',
				'address'      => isset( $data['address'] ) ? (string) $data['address'] : '',
			),
		);
	}

	public function handle_generate_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}

		check_admin_referer( 'hyper_local_generate_preview', 'hyper_local_generate_preview_nonce' );

		$settings    = $this->get_settings();
		$api_url     = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		$redirect_url = admin_url( 'admin.php?page=hyper-local-generate' );

		if ( '' === $api_url ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'API Base URL is missing. Please set it on the Hyper Local Settings page.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( '' === $license_key ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'License Key is missing. Please set it on the Hyper Local Settings page.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$post_data = array(
			'service'      => isset( $_POST['service'] ) ? sanitize_text_field( wp_unslash( $_POST['service'] ) ) : '',
			'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
			'state'        => isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '',
			'company_name' => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'phone'        => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'address'      => isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '',
		);

		if ( '' === $post_data['company_name'] ) {
			$post_data['company_name'] = 'Test Company';
		}
		if ( '' === $post_data['phone'] ) {
			$post_data['phone'] = '(000) 000-0000';
		}
		if ( '' === $post_data['address'] ) {
			$post_data['address'] = 'Test Address';
		}

		if ( '' === $post_data['service'] || '' === $post_data['city'] || '' === $post_data['state'] ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'Service, City, and State are required.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$payload = $this->build_generate_preview_payload( $settings, $post_data );
		$url     = trailingslashit( $api_url ) . 'generate-page';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 90,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			if ( false !== stripos( $error_message, 'cURL error 28' ) || false !== stripos( $error_message, 'timed out' ) ) {
				$error_message = __( 'The API request timed out. Generation can take 60–90 seconds. Please retry.', 'seogen' );
			}
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => $error_message,
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$snippet = substr( $body, 0, 500 );
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => sprintf( __( 'API returned HTTP %d. Response: %s', 'seogen' ), $code, $snippet ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$redirect_url = add_query_arg(
				array(
					'hl_gen' => 'fail',
					'hl_msg' => __( 'API response was not valid JSON.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$preview_key = 'hl_preview_' . wp_generate_password( 12, false, false );
		set_transient( $preview_key, $data, 5 * MINUTE_IN_SECONDS );

		$redirect_url = add_query_arg(
			array(
				'hl_gen'     => 'success',
				'hl_msg'     => __( 'Preview generated successfully.', 'seogen' ),
				'hl_preview' => $preview_key,
			),
			$redirect_url
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function render_test_connection_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['hl_test'] ) ) {
			return;
		}

		$test = sanitize_text_field( wp_unslash( $_GET['hl_test'] ) );
		$msg  = '';
		if ( isset( $_GET['hl_msg'] ) ) {
			$msg = rawurldecode( (string) wp_unslash( $_GET['hl_msg'] ) );
			$msg = sanitize_text_field( $msg );
		}

		if ( 'success' === $test ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	public function handle_test_connection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'seogen' ) );
		}

		check_admin_referer( 'seogen_test_connection', 'seogen_test_connection_nonce' );

		$settings = $this->get_settings();
		$api_url  = isset( $settings['api_url'] ) ? trim( (string) $settings['api_url'] ) : '';
		$license_key = isset( $settings['license_key'] ) ? trim( (string) $settings['license_key'] ) : '';

		$redirect_url = admin_url( 'admin.php?page=hyper-local' );

		if ( '' === $api_url ) {
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => __( 'API Base URL is missing. Please save an API Base URL first.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$health = $this->check_api_health( $api_url );
		if ( empty( $health['ok'] ) ) {
			$error = __( 'API Connection failed.', 'seogen' );
			if ( ! empty( $health['error'] ) ) {
				$error = $health['error'];
			}
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => $error,
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		if ( '' === $license_key ) {
			$redirect_url = add_query_arg(
				array(
					'hl_test' => 'fail',
					'hl_msg'  => __( 'API Connection succeeded, but License Key is missing.', 'seogen' ),
				),
				$redirect_url
			);
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$redirect_url = add_query_arg(
			array(
				'hl_test' => 'success',
				'hl_msg'  => __( 'API Connection succeeded. License Key is set (not validated with server to avoid consuming credits).', 'seogen' ),
			),
			$redirect_url
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function register_menu() {
		add_menu_page(
			__( 'Hyper Local Settings', 'seogen' ),
			__( 'Hyper Local', 'seogen' ),
			'manage_options',
			'hyper-local',
			array( $this, 'render_settings_page' ),
			'dashicons-chart-area',
			59
		);

		add_submenu_page(
			'hyper-local',
			__( 'Programmatic Pages', 'seogen' ),
			__( 'Programmatic Pages', 'seogen' ),
			'edit_posts',
			'edit.php?post_type=programmatic_page'
		);

		add_submenu_page(
			'hyper-local',
			__( 'Generate Page', 'seogen' ),
			__( 'Generate Page', 'seogen' ),
			'manage_options',
			'hyper-local-generate',
			array( $this, 'render_generate_page' )
		);
	}

	public function render_generate_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings      = $this->get_settings();
		$api_url       = isset( $settings['api_url'] ) ? (string) $settings['api_url'] : '';
		$license_key   = isset( $settings['license_key'] ) ? (string) $settings['license_key'] : '';
		$masked_license = $this->mask_license_key( $license_key );

		$preview = null;
		if ( isset( $_GET['hl_preview'] ) ) {
			$preview_key = sanitize_text_field( wp_unslash( $_GET['hl_preview'] ) );
			$preview     = get_transient( $preview_key );
			if ( false !== $preview ) {
				delete_transient( $preview_key );
			}
		}

		$title = '';
		$slug  = '';
		$meta_description = '';
		if ( is_array( $preview ) ) {
			if ( isset( $preview['title'] ) ) {
				$title = (string) $preview['title'];
			}
			if ( isset( $preview['slug'] ) ) {
				$slug = (string) $preview['slug'];
			}
			if ( isset( $preview['meta_description'] ) ) {
				$meta_description = (string) $preview['meta_description'];
			} elseif ( isset( $preview['meta'] ) && is_array( $preview['meta'] ) && isset( $preview['meta']['description'] ) ) {
				$meta_description = (string) $preview['meta']['description'];
			}
		}

		$defaults = array(
			'service'      => 'Roof Repair',
			'city'         => 'Dallas',
			'state'        => 'TX',
			'company_name' => '',
			'phone'        => '',
			'address'      => '',
		);
		?>
		<div class="wrap">
			<?php $this->render_generate_notice(); ?>
			<h1><?php echo esc_html__( 'Generate Page', 'seogen' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'This will call the API and display the returned JSON for preview/debug only. No WordPress pages are created in this phase.', 'seogen' ); ?>
			</p>

			<p>
				<strong><?php echo esc_html__( 'API URL:', 'seogen' ); ?></strong>
				<code><?php echo esc_html( $api_url ); ?></code>
			</p>
			<p>
				<strong><?php echo esc_html__( 'License Key:', 'seogen' ); ?></strong>
				<code><?php echo esc_html( $masked_license ); ?></code>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hyper_local_generate_preview" />
				<?php wp_nonce_field( 'hyper_local_generate_preview', 'hyper_local_generate_preview_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="hl_service"><?php echo esc_html__( 'Service', 'seogen' ); ?></label></th>
							<td><input name="service" id="hl_service" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['service'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_city"><?php echo esc_html__( 'City', 'seogen' ); ?></label></th>
							<td><input name="city" id="hl_city" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['city'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_state"><?php echo esc_html__( 'State', 'seogen' ); ?></label></th>
							<td><input name="state" id="hl_state" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['state'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_company_name"><?php echo esc_html__( 'Company Name', 'seogen' ); ?></label></th>
							<td><input name="company_name" id="hl_company_name" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['company_name'] ); ?>" /></td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_phone"><?php echo esc_html__( 'Phone', 'seogen' ); ?></label></th>
							<td>
								<input name="phone" id="hl_phone" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['phone'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'If left blank, a Preview placeholder will be used.', 'seogen' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="hl_address"><?php echo esc_html__( 'Address', 'seogen' ); ?></label></th>
							<td>
								<input name="address" id="hl_address" type="text" class="regular-text" value="<?php echo esc_attr( $defaults['address'] ); ?>" />
								<p class="description"><?php echo esc_html__( 'If left blank, a Preview placeholder will be used.', 'seogen' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="description"><?php echo esc_html__( 'Generation can take up to 60–90 seconds.', 'seogen' ); ?></p>
				<?php submit_button( __( 'Generate Preview', 'seogen' ), 'primary', 'submit' ); ?>
			</form>

			<?php if ( is_array( $preview ) ) : ?>
				<h2><?php echo esc_html__( 'Preview', 'seogen' ); ?></h2>

				<?php if ( '' !== $title ) : ?>
					<p><strong><?php echo esc_html__( 'Title:', 'seogen' ); ?></strong> <?php echo esc_html( $title ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $slug ) : ?>
					<p><strong><?php echo esc_html__( 'Slug:', 'seogen' ); ?></strong> <?php echo esc_html( $slug ); ?></p>
				<?php endif; ?>
				<?php if ( '' !== $meta_description ) : ?>
					<p><strong><?php echo esc_html__( 'Meta description:', 'seogen' ); ?></strong> <?php echo esc_html( $meta_description ); ?></p>
				<?php endif; ?>

				<pre><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
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
			<?php $this->render_test_connection_notice(); ?>
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

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="seogen_test_connection" />
				<?php wp_nonce_field( 'seogen_test_connection', 'seogen_test_connection_nonce' ); ?>
				<?php submit_button( __( 'Test API Connection', 'seogen' ), 'secondary', 'submit', false ); ?>
			</form>

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
