<?php
/**
 * Troubleshooting Page
 * 
 * Central hub for all troubleshooting and diagnostic tools.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Troubleshooting {

	/**
	 * Initialize
	 */
	public function __construct() {
		// This page is registered in class-seogen-admin.php register_menu()
		// We just need to provide the render method
		
		// Register AJAX handler for flushing permalinks
		add_action( 'wp_ajax_seogen_flush_permalinks', array( $this, 'ajax_flush_permalinks' ) );
	}
	
	/**
	 * AJAX handler to flush permalinks
	 */
	public function ajax_flush_permalinks() {
		check_ajax_referer( 'seogen_flush_permalinks', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}
		
		// Flush rewrite rules
		flush_rewrite_rules();
		
		wp_send_json_success( array( 'message' => 'Permalinks flushed successfully!' ) );
	}

	/**
	 * Render troubleshooting page
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Troubleshooting', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Diagnostic tools and utilities to help resolve issues with your SEO pages.', 'seogen' ); ?></p>

			<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin:20px 0;">
				<h2><?php esc_html_e( 'Available Tools', 'seogen' ); ?></h2>
				
				<div style="margin:20px 0;">
					<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=hyper-local-generate' ) ); ?>">Generate Page</a></h3>
					<p><?php esc_html_e( 'Generate a single page for testing and preview purposes.', 'seogen' ); ?></p>
				</div>

				<div style="margin:20px 0;">
					<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=seogen-meta-inspector' ) ); ?>">Meta Inspector</a></h3>
					<p><?php esc_html_e( 'Inspect meta keys on service pages to diagnose City Hub service links issues.', 'seogen' ); ?></p>
				</div>

				<div style="margin:20px 0;">
					<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=seogen-services-diagnostic' ) ); ?>">Services Diagnostics</a></h3>
					<p><?php esc_html_e( 'Check services cache structure and validate hub_key assignments.', 'seogen' ); ?></p>
				</div>

				<div style="margin:20px 0;">
					<h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=seogen-clear-cache' ) ); ?>">Clear Cache</a></h3>
					<p><?php esc_html_e( 'Clear all SEOgen transient caches (City Hub service links, etc.).', 'seogen' ); ?></p>
				</div>

				<div style="margin:20px 0;">
					<h3>Flush Permalinks</h3>
					<p><?php esc_html_e( 'Refresh WordPress permalink structure. This fixes issues where pages exist but show 404 errors.', 'seogen' ); ?></p>
					<button type="button" id="seogen-flush-permalinks" class="button button-secondary">
						<?php esc_html_e( 'Flush Permalinks Now', 'seogen' ); ?>
					</button>
					<span id="flush-permalinks-status" style="margin-left: 10px;"></span>
				</div>
			</div>

			<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">
				<h3><?php esc_html_e( 'Common Issues', 'seogen' ); ?></h3>
				<ul>
					<li><strong>City Hub showing old/empty service links:</strong> Use Clear Cache tool</li>
					<li><strong>Service pages missing metadata:</strong> Use Meta Inspector to diagnose</li>
					<li><strong>Services not appearing correctly:</strong> Use Services Diagnostics</li>
					<li><strong>Test page generation:</strong> Use Generate Page tool</li>
					<li><strong>Pages exist but show 404 errors:</strong> Use Flush Permalinks tool</li>
				</ul>
			</div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#seogen-flush-permalinks').on('click', function() {
				var button = $(this);
				var status = $('#flush-permalinks-status');
				
				// Disable button and show loading
				button.prop('disabled', true).text('Flushing...');
				status.html('<span style="color: #666;">Processing...</span>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'seogen_flush_permalinks',
						nonce: '<?php echo wp_create_nonce( 'seogen_flush_permalinks' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							status.html('<span style="color: #00a32a; font-weight: 600;">✓ ' + response.data.message + '</span>');
							setTimeout(function() {
								status.fadeOut(function() {
									status.html('').show();
								});
							}, 3000);
						} else {
							status.html('<span style="color: #d63638;">✗ Error: ' + response.data.message + '</span>');
						}
						button.prop('disabled', false).text('<?php esc_html_e( 'Flush Permalinks Now', 'seogen' ); ?>');
					},
					error: function() {
						status.html('<span style="color: #d63638;">✗ Request failed. Please try again.</span>');
						button.prop('disabled', false).text('<?php esc_html_e( 'Flush Permalinks Now', 'seogen' ); ?>');
					}
				});
			});
		});
		</script>
		<?php
	}
}

// Initialize
new SEOgen_Troubleshooting();
