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
			</div>

			<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">
				<h3><?php esc_html_e( 'Common Issues', 'seogen' ); ?></h3>
				<ul>
					<li><strong>City Hub showing old/empty service links:</strong> Use Clear Cache tool</li>
					<li><strong>Service pages missing metadata:</strong> Use Meta Inspector to diagnose</li>
					<li><strong>Services not appearing correctly:</strong> Use Services Diagnostics</li>
					<li><strong>Test page generation:</strong> Use Generate Page tool</li>
				</ul>
			</div>
		</div>
		<?php
	}
}

// Initialize
new SEOgen_Troubleshooting();
