<?php

/**
 * WP Duplicate - WordPress Migration Plugin
 *
 * @link              https://revmakx.com
 * @since             1.0.0
 * @package           Local_Sync
 *
 * @wordpress-plugin
 * Plugin Name:       WP Duplicate - WordPress Migration Plugin
 * Plugin URI:        https://localsync.io
 * Description:       Easily migrate your WordPress Site from one host to another.
 * Version:           1.1.6
 * Author:            Revmakx
 * Author URI:        https://revmakx.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       local-sync
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
error_reporting(error_reporting() & ~E_DEPRECATED);

require plugin_dir_path( __FILE__ ) . 'local-sync-constants.php';
$constants = new Local_Sync_Constants();
$constants->init_live_plugin();

require plugin_dir_path( __FILE__ ) . 'local-sync-debug.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-local-sync-activator.php
 */
function activate_local_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-local-sync-activator.php';
	Local_Sync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-local-sync-deactivator.php
 */
function deactivate_local_sync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-local-sync-deactivator.php';
	Local_Sync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_local_sync' );
register_deactivation_hook( __FILE__, 'deactivate_local_sync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-local-sync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_local_sync() {

	$plugin = new Local_Sync();
	$plugin->run();

}
run_local_sync();;
