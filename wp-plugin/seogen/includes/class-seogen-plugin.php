<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEOgen_Plugin {
	public function run() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		if ( is_admin() ) {
			require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-admin.php';
			$admin = new SEOgen_Admin();
			$admin->run();
		}
	}

	public function activate() {
		$this->register_post_type();
		flush_rewrite_rules();
	}

	public function deactivate() {
		flush_rewrite_rules();
	}

	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Programmatic Pages', 'seogen' ),
			'singular_name'         => __( 'Programmatic Page', 'seogen' ),
			'menu_name'             => __( 'Programmatic Pages', 'seogen' ),
			'name_admin_bar'        => __( 'Programmatic Page', 'seogen' ),
			'add_new'               => __( 'Add New', 'seogen' ),
			'add_new_item'          => __( 'Add New Programmatic Page', 'seogen' ),
			'new_item'              => __( 'New Programmatic Page', 'seogen' ),
			'edit_item'             => __( 'Edit Programmatic Page', 'seogen' ),
			'view_item'             => __( 'View Programmatic Page', 'seogen' ),
			'all_items'             => __( 'Programmatic Pages', 'seogen' ),
			'search_items'          => __( 'Search Programmatic Pages', 'seogen' ),
			'not_found'             => __( 'No programmatic pages found.', 'seogen' ),
			'not_found_in_trash'    => __( 'No programmatic pages found in Trash.', 'seogen' ),
			'filter_items_list'     => __( 'Filter programmatic pages list', 'seogen' ),
			'items_list_navigation' => __( 'Programmatic pages list navigation', 'seogen' ),
			'items_list'            => __( 'Programmatic pages list', 'seogen' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => false,
			'has_archive'        => false,
			'rewrite'            => array( 'slug' => 'programmatic-page' ),
			'supports'           => array( 'title', 'editor', 'revisions' ),
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-admin-page',
		);

		register_post_type( 'programmatic_page', $args );
	}
}
