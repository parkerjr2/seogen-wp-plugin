<?php
/**
 * Plugin Name: Hyper Local
 * Plugin URI: https://github.com/parkerjr2/seogen-wp-plugin
 * Description: Hyper Local WordPress plugin skeleton.
 * Version: 0.1.0
 * Author: Hyper Local
 * License: GPLv2 or later
 * Text Domain: seogen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEOGEN_VERSION', '0.1.5' );
define( 'SEOGEN_PLUGIN_FILE', __FILE__ );
define( 'SEOGEN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEOGEN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-plugin.php';
require_once SEOGEN_PLUGIN_DIR . 'includes/class-seogen-wizard.php';

function seogen_plugin() {
	static $plugin = null;
	if ( null === $plugin ) {
		$plugin = new SEOgen_Plugin();
	}
	return $plugin;
}

function seogen_wizard() {
	static $wizard = null;
	if ( null === $wizard ) {
		$wizard = new SEOgen_Wizard();
	}
	return $wizard;
}

function seogen_run() {
	seogen_plugin()->run();
	seogen_wizard();
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
