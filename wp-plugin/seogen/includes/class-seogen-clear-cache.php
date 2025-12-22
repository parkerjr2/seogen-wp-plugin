<?php
/**
 * Clear Cache Tool
 * 
 * One-click tool to clear all seogen transient caches.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Clear_Cache {

	/**
	 * Initialize admin page and AJAX
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 103 );
		add_action( 'wp_ajax_seogen_clear_cache', array( $this, 'ajax_clear_cache' ) );
	}

	/**
	 * Add admin menu page
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'hyper-local',
			__( 'Clear Cache', 'seogen' ),
			__( 'Clear Cache', 'seogen' ),
			'manage_options',
			'seogen-clear-cache',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Clear Cache', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Clear all SEOgen transient caches (City Hub service links, etc.).', 'seogen' ); ?></p>

			<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;margin:20px 0;">
				<h2><?php esc_html_e( 'Clear All Caches', 'seogen' ); ?></h2>
				<p><?php esc_html_e( 'This will clear all transient caches used by SEOgen, including City Hub service links caches.', 'seogen' ); ?></p>
				
				<button type="button" id="seogen-clear-cache-btn" class="button button-primary button-large">
					<?php esc_html_e( 'Clear All Caches Now', 'seogen' ); ?>
				</button>

				<div id="seogen-clear-cache-result" style="margin-top:20px;"></div>
			</div>

			<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">
				<h3><?php esc_html_e( 'When to Use This', 'seogen' ); ?></h3>
				<ul>
					<li><strong>After regenerating service pages:</strong> Clear cache so City Hub pages show updated service links</li>
					<li><strong>After updating services:</strong> Clear cache so Service Hub pages show updated service lists</li>
					<li><strong>City Hub showing old/empty service links:</strong> Clear cache to force fresh query</li>
				</ul>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#seogen-clear-cache-btn').on('click', function() {
				var btn = $(this);
				var result = $('#seogen-clear-cache-result');
				
				btn.prop('disabled', true).text('Clearing...');
				result.html('<p>Clearing caches...</p>');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'seogen_clear_cache',
						nonce: '<?php echo wp_create_nonce( 'seogen_clear_cache' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							result.html('<div style="background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:3px;"><strong style="color:#155724;">✓ Success!</strong><br>' + response.data.message + '<br><strong>Cleared ' + response.data.count + ' cache entries.</strong></div>');
						} else {
							result.html('<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:3px;"><strong style="color:#721c24;">Error:</strong> ' + response.data + '</div>');
						}
						btn.prop('disabled', false).text('Clear All Caches Now');
					},
					error: function() {
						result.html('<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:3px;"><strong style="color:#721c24;">Error:</strong> Failed to clear caches.</div>');
						btn.prop('disabled', false).text('Clear All Caches Now');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler to clear caches
	 */
	public function ajax_clear_cache() {
		check_ajax_referer( 'seogen_clear_cache', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		global $wpdb;

		// Delete all transients starting with seogen_city_services_
		$count = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_seogen_city_services_' ) . '%'
			)
		);

		// Also delete transient timeout entries
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_seogen_city_services_' ) . '%'
			)
		);

		wp_send_json_success( array(
			'message' => 'All SEOgen caches have been cleared. Refresh your City Hub page to see updated service links.',
			'count' => $count,
		) );
	}
}

// Initialize
new SEOgen_Clear_Cache();
