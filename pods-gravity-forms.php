<?php
/*
Plugin Name: Pods Gravity Forms Add-On
Plugin URI: http://pods.io/
Description: Integration with Gravity Forms (http://www.gravityforms.com/); Provides a UI for mapping a Form's submissions into a Pod
Version: 1.0 Alpha 5
Author: Pods Framework Team
Author URI: http://pods.io/about/
Text Domain: pods-gravity-forms
Domain Path: /languages/

Copyright 2013  Pods Foundation, Inc  (email : contact@podsfoundation.org)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * @package Pods\Gravity Forms
 */

define( 'PODS_GF_VERSION', '1.0-a-5' );
define( 'PODS_GF_FILE', __FILE__ );
define( 'PODS_GF_DIR', plugin_dir_path( PODS_GF_FILE ) );
define( 'PODS_GF_URL', plugin_dir_url( PODS_GF_FILE ) );
define( 'PODS_GF_ADDON_FILE', basename( PODS_GF_DIR ) . '/' . basename( PODS_GF_FILE ) );


if ( class_exists( 'GFForms' ) && defined( 'PODS_VERSION' ) ) {

/**
 * @global Pods_GF_UI $GLOBALS['pods_gf_ui']
 * @name $pods_gf_ui
 */
global $pods_gf_ui, $pods_gf_ui_loaded;

/**
 * Include the Pods GF Add-On
 */
function pods_gf_init() {

	if ( !function_exists( 'pods' ) || !class_exists( 'GFCommon' ) ) {
		return false;
	}

	// Include main functions
	require_once( PODS_GF_DIR . 'includes/functions.php' );

	// Include GF Feed Addon code
	if ( !class_exists( 'GFFeedAddOn' ) ) {
		GFForms::include_feed_addon_framework();
	}

	// Include GF Add-On
	require_once( PODS_GF_DIR . 'includes/Pods_GF_Addon.php' );

	// Init Pods GF UI
	add_action( 'wp', 'pods_gf_ui_init', 7 );

	// Pods GF UI shortcode
	add_shortcode( 'pods-gf-ui', 'pods_gf_ui_shortcode' );

	if ( is_admin() ) {
		add_action( 'wp_ajax_pods_gf_save_for_later', 'pods_gf_save_for_later_ajax' );
		add_action( 'wp_ajax_nopriv_pods_gf_save_for_later', 'pods_gf_save_for_later_ajax' );
	}

}
add_action( 'init', 'pods_gf_init' );

// Warning: Gravity Forms Duplicate Prevention plugin's JS *will* break secondary submits!
// So we need to disable it now
add_filter( 'gform_duplicate_prevention_load_script', '__return_false' );

// Include GF Feed Addon code
	if ( !class_exists( 'GFFeedAddOn' ) ) {
		GFForms::include_feed_addon_framework();
	}

	// Include GF Add-On
	require_once( PODS_GF_DIR . 'includes/Pods_GF_Addon.php' );

}

else {
	if ( is_admin() ) {
		echo sprintf( '<div id="message" class="error"><p>%s</p></div>',
			sprintf(
				__( '%1$s requires that the Pods and Gravity Forms core plugins be installed and activated.', 'pods-gf' ),
				'Pods Gravity Forms' )
		);
	}

}
