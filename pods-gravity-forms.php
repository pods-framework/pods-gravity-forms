<?php
/*
Plugin Name: Pods Gravity Forms Add-On
Plugin URI: http://pods.io/
Description: Integration with Gravity Forms (http://www.gravityforms.com/); Provides a UI for mapping a Form's submissions into a Pod
Version: 1.3
Author: Pods Framework Team
Author URI: http://pods.io/about/
Text Domain: pods-gravity-forms
Domain Path: /languages/

Copyright 2013-2017  Pods Foundation, Inc  (email : contact@podsfoundation.org)

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

define( 'PODS_GF_VERSION', '1.3' );
define( 'PODS_GF_FILE', __FILE__ );
define( 'PODS_GF_DIR', plugin_dir_path( PODS_GF_FILE ) );
define( 'PODS_GF_URL', plugin_dir_url( PODS_GF_FILE ) );
define( 'PODS_GF_ADDON_FILE', basename( PODS_GF_DIR ) . '/' . basename( PODS_GF_FILE ) );

/**
 * @global Pods_GF_UI $GLOBALS ['pods_gf_ui']
 * @name              $pods_gf_ui
 */
global $pods_gf_ui;

/**
 * Include the Pods GF Add-On
 */
function pods_gf_include_gf_addon () {

	// Include GF Feed Addon code
	if ( class_exists( 'GFForms' ) && ! class_exists( 'GFFeedAddOn' ) ) {
		GFForms::include_feed_addon_framework();
	}

	// Include GF Add-On
	if ( class_exists( 'GFForms' ) && defined( 'PODS_VERSION' ) ) {
		require_once( PODS_GF_DIR . 'includes/Pods_GF_Addon.php' );
	}
}

add_action( 'plugins_loaded', 'pods_gf_include_gf_addon' );

/**
 * Include main functions and initiate
 */
function pods_gf_init () {

	if ( ! function_exists( 'pods' ) || ! class_exists( 'GFCommon' ) ) {
		return false;
	}

	// Include main functions
	require_once( PODS_GF_DIR . 'includes/functions.php' );

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

/**
 * Admin nag if Pods or GF isn't activated.
 */
add_action( 'plugins_loaded', 'pods_gf_admin_nag' );

function pods_gf_admin_nag () {
	if ( is_admin() && ( ! class_exists( 'GFForms' ) || ! defined( 'PODS_VERSION' ) ) ) {
		echo sprintf( '<div id="message" class="error"><p>%s</p></div>',
					  __( 'Pods Gravity Forms requires that the Pods and Gravity Forms core plugins be installed and activated.', 'pods-gravity-forms' )
		);
	}

}


/**
 * Add Advanced Related Objects
 *
 * @since 1.3
 */
function pods_gf_add_related_objects() {

	PodsField_Pick::$related_objects['gf-forms'] = array(
		'label'         => __( 'Forms', 'pods' ),
		'group'         => __( 'Gravity Forms', 'pods' ),
		'simple'        => true,
		'data_callback' => 'pods_gf_add_related_objects_forms',
	);

}
add_action( 'pods_form_ui_field_pick_related_objects_other', 'pods_gf_add_related_objects' );

/**
 * Pods related data callback for GF Forms
 *
 * @param string       $name    The name of the field
 * @param string|array $value   The value of the field
 * @param array        $options Field options
 * @param array        $pod     Pod data
 * @param int          $id      Item ID
 *
 * @return array
 *
 * @since 1.3
 */
function pods_gf_add_related_objects_forms( $name = null, $value = null, $options = null, $pod = null, $id = null ) {

	$data = array();

	// Get all forms.
	$forms = RGFormsModel::get_forms( null, 'title' );

	foreach ( $forms as $form ) {
		$form_title = $form->title;

		if ( 1 !== (int) $form->is_active ) {
			$form_title = sprintf(
				'%s (%s)',
				$form_title,
				__( 'inactive', 'pods-gravity-forms' )
			);
		}

		$data[ $form->id ] = $form_title;
	}

	return apply_filters( 'pods_form_ui_field_pick_' . __FUNCTION__, $data, $name, $value, $options, $pod, $id );

}