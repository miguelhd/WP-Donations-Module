<?php
/**
Plugin Name: Donations Module
Plugin URI: https://miguelhd.com
Description: Un plugin para aceptar donaciones a través de PayPal Donate SDK para organizaciones sin fines de lucro.
Version: 1.2.1
Author: Miguel Hernández Domenech
Author URI: https://miguelhd.com
License: GPLv2 or later
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// Include required files
include_once plugin_dir_path( __FILE__ ) . 'includes/class-donations-module.php';

// Activation and deactivation hooks
register_activation_hook( __FILE__, array( 'Donations_Module', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Donations_Module', 'deactivate' ) );

// Initialize the plugin
add_action( 'plugins_loaded', array( 'Donations_Module', 'init' ) );

// Add a Settings link to the Plugins page
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'donations_module_add_settings_link' );

/**
 * Add a settings link to the plugins page.
 *
 * @param array $links Existing links.
 * @return array Links including the settings link.
 */
function donations_module_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url('admin.php?page=donations-module') . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
