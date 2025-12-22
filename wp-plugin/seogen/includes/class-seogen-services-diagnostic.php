<?php
/**
 * Services Cache Diagnostic
 * 
 * Quick diagnostic tool to check services cache structure.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Services_Diagnostic {

	const SERVICES_CACHE_OPTION = 'hyper_local_services_cache';

	/**
	 * Initialize admin page
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 102 );
	}

	/**
	 * Add admin menu page
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'hyper-local',
			__( 'Services Diagnostic', 'seogen' ),
			__( 'Services Diagnostic', 'seogen' ),
			'manage_options',
			'seogen-services-diagnostic',
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

		$services = get_option( self::SERVICES_CACHE_OPTION, array() );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Services Cache Diagnostic', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'This shows the raw services cache data to diagnose hub_key matching issues.', 'seogen' ); ?></p>

			<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;">
				<h2><?php esc_html_e( 'Services Cache Contents', 'seogen' ); ?></h2>
				
				<?php if ( empty( $services ) ) : ?>
					<p style="color:#d63638;"><strong><?php esc_html_e( 'Services cache is EMPTY!', 'seogen' ); ?></strong></p>
					<p><?php esc_html_e( 'Go to Hyper Local → Services and save your services to populate the cache.', 'seogen' ); ?></p>
				<?php else : ?>
					<p><strong><?php echo count( $services ); ?> services found in cache</strong></p>
					
					<table class="widefat striped">
						<thead>
							<tr>
								<th>Index</th>
								<th>Service Name</th>
								<th>Slug</th>
								<th>hub_key</th>
								<th>Raw Data</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $services as $idx => $service ) : ?>
								<tr>
									<td><?php echo esc_html( $idx ); ?></td>
									<td>
										<?php if ( isset( $service['name'] ) ) : ?>
											<strong><?php echo esc_html( $service['name'] ); ?></strong>
										<?php else : ?>
											<span style="color:#d63638;">MISSING</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( isset( $service['slug'] ) ) : ?>
											<code><?php echo esc_html( $service['slug'] ); ?></code>
										<?php else : ?>
											<span style="color:#d63638;">MISSING</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( isset( $service['hub_key'] ) ) : ?>
											<code style="background:#d4edda;padding:2px 5px;"><?php echo esc_html( $service['hub_key'] ); ?></code>
										<?php else : ?>
											<span style="color:#d63638;font-weight:bold;">MISSING</span>
										<?php endif; ?>
									</td>
									<td>
										<pre style="font-size:10px;max-width:300px;overflow:auto;"><?php echo esc_html( print_r( $service, true ) ); ?></pre>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">
				<h3><?php esc_html_e( 'What to Check', 'seogen' ); ?></h3>
				<ul>
					<li><strong>hub_key column:</strong> Should show values like "residential", "commercial", etc.</li>
					<li><strong>If hub_key is MISSING:</strong> Go to Hyper Local → Services and click "Save Services" to rebuild the cache</li>
					<li><strong>Service names:</strong> Should match what you enter in Bulk Generate</li>
				</ul>
			</div>
		</div>
		<?php
	}
}

// Initialize
new SEOgen_Services_Diagnostic();
