<?php
/**
 * Plugin Name: Donations Module
 * Description: A plugin to accept donations and display a progress bar.
 * Version: 0.1
 * Author: Miguel Hernández
 * Text Domain: donations-module
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
