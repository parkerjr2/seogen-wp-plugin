<?php
/**
 * Duplicate Cleanup Admin Interface
 * 
 * Adds UI and AJAX handlers for cleaning up duplicate pages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SEOgen_Admin_Duplicate_Cleanup {
	
	/**
	 * Add duplicate cleanup menu item
	 */
	public function add_duplicate_cleanup_menu() {
		add_submenu_page(
			'hyper-local-troubleshooting',
			__( 'Duplicate Cleanup', 'seogen' ),
			__( 'Duplicate Cleanup', 'seogen' ),
			'manage_options',
			'seogen-duplicate-cleanup',
			array( $this, 'render_duplicate_cleanup_section' )
		);
	}
	
	/**
	 * AJAX handler for duplicate cleanup
	 */
	public function ajax_cleanup_duplicates() {
		check_ajax_referer( 'seogen_cleanup_duplicates', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}
		
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-duplicate-cleanup.php';
		
		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];
		
		try {
			$results = SEOgen_Duplicate_Cleanup::cleanup_duplicates( $dry_run );
			
			wp_send_json_success( array(
				'message' => $dry_run 
					? sprintf( 'Found %d duplicate pages that can be cleaned up', $results['trashed'] )
					: sprintf( 'Successfully cleaned up %d duplicate pages', $results['trashed'] ),
				'results' => $results,
			) );
		} catch ( Exception $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
	
	/**
	 * Render duplicate cleanup section in settings
	 */
	public function render_duplicate_cleanup_section() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-duplicate-cleanup.php';
		$summary = SEOgen_Duplicate_Cleanup::get_duplicate_summary();
		
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Duplicate Page Cleanup', 'seogen' ); ?></h2>
			
			<?php if ( $summary['total_duplicates'] > 0 ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Duplicates Found:', 'seogen' ); ?></strong>
						<?php
						printf(
							esc_html__( '%d duplicate pages found in %d groups. These duplicates can be safely removed.', 'seogen' ),
							$summary['total_duplicates'],
							$summary['duplicate_groups']
						);
						?>
					</p>
				</div>
				
				<p>
					<?php esc_html_e( 'This tool will keep the most recent version of each page and move duplicates to trash.', 'seogen' ); ?>
				</p>
				
				<p>
					<button type="button" id="seogen-scan-duplicates" class="button">
						<?php esc_html_e( 'Scan for Duplicates', 'seogen' ); ?>
					</button>
					<button type="button" id="seogen-cleanup-duplicates" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e( 'Clean Up Duplicates', 'seogen' ); ?>
					</button>
				</p>
				
				<div id="seogen-cleanup-results" style="margin-top: 20px;"></div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'No duplicate pages found. Your site is clean!', 'seogen' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#seogen-scan-duplicates').on('click', function() {
				var $btn = $(this);
				var originalText = $btn.text();
				$btn.prop('disabled', true).text('Scanning...');
				
				$.post(ajaxurl, {
					action: 'seogen_cleanup_duplicates',
					nonce: '<?php echo wp_create_nonce( 'seogen_cleanup_duplicates' ); ?>',
					dry_run: '1'
				}, function(response) {
					$btn.prop('disabled', false).text(originalText);
					
					if (response.success) {
						var html = '<div class="notice notice-info"><p>' + response.data.message + '</p>';
						if (response.data.results.details && response.data.results.details.length > 0) {
							html += '<ul>';
							response.data.results.details.forEach(function(detail) {
								html += '<li><strong>' + detail.title + '</strong>: ' + detail.duplicate_count + ' duplicate(s) will be removed</li>';
							});
							html += '</ul>';
						}
						html += '</div>';
						$('#seogen-cleanup-results').html(html);
					} else {
						$('#seogen-cleanup-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
					}
				});
			});
			
			$('#seogen-cleanup-duplicates').on('click', function() {
				if (!confirm('Are you sure you want to clean up duplicate pages? This will move duplicates to trash.')) {
					return;
				}
				
				var $btn = $(this);
				var originalText = $btn.text();
				$btn.prop('disabled', true).text('Cleaning up...');
				
				$.post(ajaxurl, {
					action: 'seogen_cleanup_duplicates',
					nonce: '<?php echo wp_create_nonce( 'seogen_cleanup_duplicates' ); ?>',
					dry_run: '0'
				}, function(response) {
					$btn.prop('disabled', false).text(originalText);
					
					if (response.success) {
						var html = '<div class="notice notice-success"><p>' + response.data.message + '</p>';
						if (response.data.results.details && response.data.results.details.length > 0) {
							html += '<ul>';
							response.data.results.details.forEach(function(detail) {
								html += '<li><strong>' + detail.title + '</strong>: Kept ID ' + detail.kept_id + ', removed ' + detail.duplicate_count + ' duplicate(s)</li>';
							});
							html += '</ul>';
						}
						html += '</div>';
						$('#seogen-cleanup-results').html(html);
						
						// Reload page after 2 seconds to update counts
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						$('#seogen-cleanup-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
					}
				});
			});
		});
		</script>
		<?php
	}
}
