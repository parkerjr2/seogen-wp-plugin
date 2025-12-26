<?php
/**
 * Plugin Name: Hyper Local
 * Plugin URI: https://hyperlocalseo.io
 * Description: Rank in Every City You Serve — Without Writing Hundreds of Pages. Hyper Local creates SEO-ready "Service + City" pages inside WordPress, so local businesses can win more searches without agencies or manual work.
 * Version: 1.0.0
 * Author: Hyper Local
 * License: GPLv2 or later
 * Text Domain: seogen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOGEN_VERSION', '1.0.0' );
define( 'SEOGEN_PLUGIN_FILE', __FILE__ );
define( 'SEOGEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOGEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-plugin.php';

function seogen_plugin() {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new SEOgen_Plugin();
	}
	return $plugin;
}

function seogen_run() {
	seogen_plugin()->run();
}
add_action( 'plugins_loaded', 'seogen_run' );

function seogen_activate() {
	seogen_plugin()->activate();
}
register_activation_hook( __FILE__, 'seogen_activate' );

function seogen_deactivate() {
	seogen_plugin()->deactivate();
}
register_deactivation_hook( __FILE__, 'seogen_deactivate' );
