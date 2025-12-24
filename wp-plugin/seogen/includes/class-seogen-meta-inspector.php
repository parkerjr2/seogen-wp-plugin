<?php
/**
 * Meta Inspector Tool
 * 
 * Displays meta keys for all service_page posts to diagnose City Hub service links issues.
 * 
 * @package SEOgen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Meta_Inspector {

	/**
	 * Initialize admin page
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 100 );
	}

	/**
	 * Add admin menu page
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'hyper-local',
			__( 'Meta Inspector', 'seogen' ),
			__( '— Meta Inspector', 'seogen' ),
			'manage_options',
			'seogen-meta-inspector',
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

		// Get filter parameters
		$filter_page_mode = isset( $_GET['filter_page_mode'] ) ? sanitize_text_field( $_GET['filter_page_mode'] ) : '';
		$filter_hub_key = isset( $_GET['filter_hub_key'] ) ? sanitize_text_field( $_GET['filter_hub_key'] ) : '';
		$filter_city_slug = isset( $_GET['filter_city_slug'] ) ? sanitize_text_field( $_GET['filter_city_slug'] ) : '';

		// Query service_page posts
		$args = array(
			'post_type'      => 'service_page',
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 50,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Add meta query filters if specified
		$meta_query = array( 'relation' => 'AND' );
		if ( ! empty( $filter_page_mode ) ) {
			$meta_query[] = array(
				'key'     => '_seogen_page_mode',
				'value'   => $filter_page_mode,
				'compare' => '=',
			);
		}
		if ( ! empty( $filter_hub_key ) ) {
			$meta_query[] = array(
				'key'     => '_seogen_hub_key',
				'value'   => $filter_hub_key,
				'compare' => '=',
			);
		}
		if ( ! empty( $filter_city_slug ) ) {
			$meta_query[] = array(
				'key'     => '_seogen_city_slug',
				'value'   => $filter_city_slug,
				'compare' => '=',
			);
		}
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $args );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Meta Inspector', 'seogen' ); ?></h1>
			<p><?php esc_html_e( 'Inspect meta keys on service_page posts to diagnose City Hub service links issues.', 'seogen' ); ?></p>

			<form method="get" style="background:#fff;padding:15px;border:1px solid #ccd0d4;margin:20px 0;">
				<input type="hidden" name="page" value="seogen-meta-inspector">
				<h3><?php esc_html_e( 'Filters', 'seogen' ); ?></h3>
				<table class="form-table">
					<tr>
						<th><label for="filter_page_mode">Page Mode:</label></th>
						<td>
							<select name="filter_page_mode" id="filter_page_mode">
								<option value="">All</option>
								<option value="service_city" <?php selected( $filter_page_mode, 'service_city' ); ?>>service_city</option>
								<option value="city_hub" <?php selected( $filter_page_mode, 'city_hub' ); ?>>city_hub</option>
								<option value="service_hub" <?php selected( $filter_page_mode, 'service_hub' ); ?>>service_hub</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="filter_hub_key">Hub Key:</label></th>
						<td><input type="text" name="filter_hub_key" id="filter_hub_key" value="<?php echo esc_attr( $filter_hub_key ); ?>" placeholder="e.g., residential"></td>
					</tr>
					<tr>
						<th><label for="filter_city_slug">City Slug:</label></th>
						<td><input type="text" name="filter_city_slug" id="filter_city_slug" value="<?php echo esc_attr( $filter_city_slug ); ?>" placeholder="e.g., tulsa-ok"></td>
					</tr>
				</table>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filters', 'seogen' ); ?></button>
				<a href="<?php echo admin_url( 'admin.php?page=seogen-meta-inspector' ); ?>" class="button"><?php esc_html_e( 'Clear Filters', 'seogen' ); ?></a>
			</form>

			<div style="background:#fff;border:1px solid #ccd0d4;padding:20px;">
				<h2><?php esc_html_e( 'Service Pages', 'seogen' ); ?> (<?php echo $query->found_posts; ?> found)</h2>
				
				<?php if ( $query->have_posts() ) : ?>
					<table class="widefat striped" style="font-size:12px;">
						<thead>
							<tr>
								<th>ID</th>
								<th>Title</th>
								<th>Status</th>
								<th>Permalink</th>
								<th>_seogen_page_mode</th>
								<th>_seogen_hub_key</th>
								<th>_seogen_city_slug</th>
								<th>_seogen_city</th>
								<th>_seogen_service_name</th>
							</tr>
						</thead>
						<tbody>
							<?php while ( $query->have_posts() ) : $query->the_post(); ?>
								<?php
								$post_id = get_the_ID();
								$page_mode = get_post_meta( $post_id, '_seogen_page_mode', true );
								$hub_key = get_post_meta( $post_id, '_seogen_hub_key', true );
								$city_slug = get_post_meta( $post_id, '_seogen_city_slug', true );
								$city = get_post_meta( $post_id, '_seogen_city', true );
								$service_name = get_post_meta( $post_id, '_seogen_service_name', true );
								$permalink = get_permalink( $post_id );
								?>
								<tr>
									<td><?php echo esc_html( $post_id ); ?></td>
									<td><a href="<?php echo get_edit_post_link( $post_id ); ?>" target="_blank"><?php echo esc_html( get_the_title() ); ?></a></td>
									<td><?php echo esc_html( get_post_status() ); ?></td>
									<td style="font-size:10px;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $permalink ); ?>">
										<?php echo esc_html( basename( $permalink ) ); ?>
									</td>
									<td>
										<?php if ( $page_mode ) : ?>
											<code><?php echo esc_html( $page_mode ); ?></code>
										<?php else : ?>
											<span style="color:#d63638;font-weight:bold;">MISSING</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $hub_key ) : ?>
											<code><?php echo esc_html( $hub_key ); ?></code>
										<?php else : ?>
											<span style="color:#d63638;font-weight:bold;">MISSING</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( $city_slug ) : ?>
											<code><?php echo esc_html( $city_slug ); ?></code>
										<?php else : ?>
											<span style="color:#d63638;font-weight:bold;">MISSING</span>
										<?php endif; ?>
									</td>
									<td><?php echo $city ? esc_html( $city ) : '<em>—</em>'; ?></td>
									<td><?php echo $service_name ? esc_html( $service_name ) : '<em>—</em>'; ?></td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No service pages found.', 'seogen' ); ?></p>
				<?php endif; ?>

				<?php wp_reset_postdata(); ?>
			</div>

			<div style="background:#fff3cd;border:1px solid #ffc107;padding:15px;margin:20px 0;">
				<h3><?php esc_html_e( 'How to Use This Tool', 'seogen' ); ?></h3>
				<ol>
					<li><strong>Filter by page_mode = service_city</strong> to see only individual service pages</li>
					<li><strong>Filter by hub_key = residential</strong> and <strong>city_slug = tulsa-ok</strong> to see what City Hub query will find</li>
					<li><strong>Look for MISSING values</strong> in red - these are the pages that need meta keys added</li>
					<li><strong>Check city_slug format</strong> - should be "tulsa-ok" not "tulsa"</li>
				</ol>
				<p><strong>Expected for City Hub "Residential Electrical in Tulsa, OK":</strong></p>
				<ul>
					<li>_seogen_page_mode = <code>service_city</code></li>
					<li>_seogen_hub_key = <code>residential</code></li>
					<li>_seogen_city_slug = <code>tulsa-ok</code></li>
				</ul>
			</div>
		</div>
		<?php
	}
}

// Initialize
new SEOgen_Meta_Inspector();
