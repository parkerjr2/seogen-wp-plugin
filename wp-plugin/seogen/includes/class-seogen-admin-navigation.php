<?php
/**
 * Admin Navigation Helper
 * 
 * Handles "Save & Continue to Next Step" functionality across admin pages.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Admin_Navigation {
	
	/**
	 * Handle redirect after settings save
	 */
	public function handle_settings_redirect() {
		if ( isset( $_POST['_seogen_redirect'] ) && isset( $_POST['option_page'] ) && $_POST['option_page'] === 'seogen_settings_group' ) {
			$redirect_url = esc_url_raw( wp_unslash( $_POST['_seogen_redirect'] ) );
			if ( $redirect_url ) {
				set_transient( 'seogen_redirect_after_save_' . get_current_user_id(), $redirect_url, 30 );
			}
		}
		
		// Check for pending redirect
		$redirect_url = get_transient( 'seogen_redirect_after_save_' . get_current_user_id() );
		if ( $redirect_url && isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			delete_transient( 'seogen_redirect_after_save_' . get_current_user_id() );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
	
	/**
	 * Render Save & Continue button
	 * 
	 * @param string $current_page Current page slug
	 */
	public function render_save_continue_button( $current_page ) {
		$next_url = $this->get_next_setup_page_url( $current_page );
		if ( ! $next_url ) {
			return;
		}
		?>
		<button type="submit" name="save_and_continue" value="1" class="button button-primary" style="margin-left: 10px;">
			<?php echo esc_html__( 'Save & Continue to Next Step →', 'seogen' ); ?>
		</button>
		<script>
		jQuery(document).ready(function($) {
			$('button[name="save_and_continue"]').on('click', function(e) {
				var form = $(this).closest('form');
				form.append('<input type="hidden" name="_seogen_redirect" value="<?php echo esc_js( $next_url ); ?>"/>');
			});
		});
		</script>
		<?php
	}
}
