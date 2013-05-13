<?php
/*
Plugin Name: Pods Gravity Forms integration
Plugin URI: http://pods.io/
Description: Integration with Gravity Forms (http://www.gravityforms.com/); Provides a UI for mapping a Form's submissions into a Pod
Version: 1.0 Alpha
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

add_action( 'pods_components_get', 'pods_component_gravity_forms_init' );
add_action( 'pods_components_load', 'pods_component_gravity_forms_load' );

function pods_component_gravity_forms_init() {
    register_activation_hook( __FILE__, 'pods_component_gravity_forms_reset' );
    register_deactivation_hook( __FILE__, 'pods_component_gravity_forms_reset' );

    pods_component_gravity_forms_load();

    add_filter( 'pods_components_register', array( 'PodsComponent_GravityForms', 'component_register' ) );
}

function pods_component_gravity_forms_load() {
    $component_path = plugin_dir_path( __FILE__ );
    $component_file = $component_path . 'PodsComponent_GravityForms.php';

    require_once( $component_file );

    PodsComponent_GravityForms::$component_path = $component_path;
    PodsComponent_GravityForms::$component_file = $component_file;
}

function pods_component_gravity_forms_reset() {
    delete_transient( 'pods_components' );
    delete_transient( 'pods_field_types ' );
}