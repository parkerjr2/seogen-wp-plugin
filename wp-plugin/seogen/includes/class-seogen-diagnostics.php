<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic tools for SEOgen
 */
class SEOgen_Diagnostics {
	
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_diagnostics_page' ), 100 );
		add_action( 'admin_post_seogen_fix_service_meta', array( $this, 'handle_fix_service_meta' ) );
	}
	
	public function add_diagnostics_page() {
		add_submenu_page(
			'hyper-local',
			__( 'Generate Page', 'seogen' ),
			__( '— Generate Page', 'seogen' ),
			'manage_options',
			'hyper-local-generate',
			array( $this, 'render_diagnostics_page' )
		);
	}
	
	public function render_diagnostics_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Get all service_city pages
		$service_pages = get_posts( array(
			'post_type' => 'service_page',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_city',
					'compare' => '='
				)
			),
			'orderby' => 'title',
			'order' => 'ASC'
		) );
		
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SEOgen Diagnostics', 'seogen' ); ?></h1>
			
			<h2><?php esc_html_e( 'Service Pages Meta Fields', 'seogen' ); ?></h2>
			<p><?php esc_html_e( 'This shows the meta fields for all published service_city pages.', 'seogen' ); ?></p>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Page Title', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Page Mode', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Hub Key', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'City Slug', 'seogen' ); ?></th>
						<th><?php esc_html_e( 'Status', 'seogen' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $service_pages ) ) : ?>
						<?php foreach ( $service_pages as $page ) : ?>
							<?php
							$page_mode = get_post_meta( $page->ID, '_seogen_page_mode', true );
							$hub_key = get_post_meta( $page->ID, '_seogen_hub_key', true );
							$city_slug = get_post_meta( $page->ID, '_seogen_city_slug', true );
							
							$has_issues = empty( $hub_key ) || empty( $city_slug );
							$row_class = $has_issues ? 'style="background-color: #fff3cd;"' : '';
							?>
							<tr <?php echo $row_class; ?>>
								<td>
									<a href="<?php echo esc_url( get_edit_post_link( $page->ID ) ); ?>" target="_blank">
										<?php echo esc_html( $page->post_title ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $page_mode ? $page_mode : '—' ); ?></td>
								<td>
									<?php if ( empty( $hub_key ) ) : ?>
										<strong style="color: #d63638;">MISSING</strong>
									<?php else : ?>
										<?php echo esc_html( $hub_key ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( empty( $city_slug ) ) : ?>
										<strong style="color: #d63638;">MISSING</strong>
									<?php else : ?>
										<?php echo esc_html( $city_slug ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $has_issues ) : ?>
										<strong style="color: #d63638;">⚠ Needs Fix</strong>
									<?php else : ?>
										<span style="color: #00a32a;">✓ OK</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No service_city pages found.', 'seogen' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php
			// Count pages with issues
			$pages_with_issues = 0;
			foreach ( $service_pages as $page ) {
				$hub_key = get_post_meta( $page->ID, '_seogen_hub_key', true );
				$city_slug = get_post_meta( $page->ID, '_seogen_city_slug', true );
				if ( empty( $hub_key ) || empty( $city_slug ) ) {
					$pages_with_issues++;
				}
			}
			?>
			
			<?php if ( $pages_with_issues > 0 ) : ?>
				<div class="notice notice-warning" style="margin-top: 20px;">
					<p>
						<strong><?php printf( esc_html__( 'Found %d pages with missing meta fields.', 'seogen' ), $pages_with_issues ); ?></strong>
					</p>
					<p><?php esc_html_e( 'These pages will not appear in City Hub service links until the meta fields are fixed.', 'seogen' ); ?></p>
				</div>
				
				<h2><?php esc_html_e( 'Auto-Fix Missing Meta Fields', 'seogen' ); ?></h2>
				<p><?php esc_html_e( 'This will attempt to extract hub_key and city_slug from the page title and set the meta fields.', 'seogen' ); ?></p>
				<p><strong><?php esc_html_e( 'Example:', 'seogen' ); ?></strong> "Electrical Panel Upgrade in Tulsa | M Electric" → hub_key: "residential", city_slug: "tulsa-ok"</p>
				
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'This will update meta fields on all service pages with missing data. Continue?', 'seogen' ); ?>');">
					<?php wp_nonce_field( 'seogen_fix_service_meta', 'seogen_fix_meta_nonce' ); ?>
					<input type="hidden" name="action" value="seogen_fix_service_meta" />
					<?php submit_button( __( 'Fix Missing Meta Fields', 'seogen' ), 'primary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}
	
	public function handle_fix_service_meta() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}
		
		check_admin_referer( 'seogen_fix_service_meta', 'seogen_fix_meta_nonce' );
		
		// Get business config to know available hubs
		$config = get_option( 'hyper_local_business_config', array() );
		$hubs = isset( $config['hubs'] ) ? $config['hubs'] : array();
		$default_hub_key = ! empty( $hubs ) ? $hubs[0]['key'] : 'residential';
		
		// Get all cities
		$cities = get_option( 'hyper_local_cities_cache', array() );
		
		// Get all service_city pages with missing meta
		$service_pages = get_posts( array(
			'post_type' => 'service_page',
			'posts_per_page' => -1,
			'post_status' => 'publish',
			'meta_query' => array(
				array(
					'key' => '_seogen_page_mode',
					'value' => 'service_city',
					'compare' => '='
				)
			)
		) );
		
		$fixed_count = 0;
		$error_count = 0;
		
		foreach ( $service_pages as $page ) {
			$hub_key = get_post_meta( $page->ID, '_seogen_hub_key', true );
			$city_slug = get_post_meta( $page->ID, '_seogen_city_slug', true );
			
			// Skip if both are already set
			if ( ! empty( $hub_key ) && ! empty( $city_slug ) ) {
				continue;
			}
			
			$needs_fix = false;
			
			// Try to extract city from title
			// Format: "Service Name in City | Business Name"
			if ( empty( $city_slug ) && preg_match( '/\sin\s+([^|]+?)\s*\|/i', $page->post_title, $matches ) ) {
				$city_name = trim( $matches[1] );
				
				// Find matching city in cache
				foreach ( $cities as $city ) {
					if ( strcasecmp( $city['name'], $city_name ) === 0 ) {
						update_post_meta( $page->ID, '_seogen_city_slug', $city['slug'] );
						$needs_fix = true;
						break;
					}
				}
			}
			
			// Set hub_key to default if missing
			if ( empty( $hub_key ) ) {
				update_post_meta( $page->ID, '_seogen_hub_key', $default_hub_key );
				$needs_fix = true;
			}
			
			if ( $needs_fix ) {
				$fixed_count++;
			} else {
				$error_count++;
			}
		}
		
		wp_redirect( add_query_arg( array(
			'page' => 'seogen-diagnostics',
			'hl_notice' => 'created',
			'hl_msg' => rawurlencode( sprintf( 'Fixed %d pages. %d pages could not be auto-fixed.', $fixed_count, $error_count ) ),
		), admin_url( 'admin.php' ) ) );
		exit;
	}
}

new SEOgen_Diagnostics();
