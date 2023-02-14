<?php

/**
  * The main file for Blipper Widget.
  * Plugin Name:        Blipper Widget
  * Plugin URI:         http://pandammonium.org/wordpress-dev/blipper-widget/
  * Description:        Display your latest blip in a widget.  Requires a Blipfoto account (available free of charge).
  * Version:            1.2.1
  * Author:             Caity Ross
  * Author URI:         http://pandammonium.org/
  * License:            GPL-2.0+
  * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain:        blipper-widget
  * Domain Path:        /languages
  * @link               http://pandammonium.org/wordpress-dev/blipper-widget/
  * @since              0.0.1
  * @package            Blipper_Widget
  * @author             pandammonium
  * @license            GPLv2 or later
  * @wordpress-plugin
  */

/**
 * Copyright 2015 Caity Ross
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 */

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

require_once('classes/class-widget.php');
// -------------------------------------------------------------------------- //

/**
 * @ignore
 */
define( 'BW_DEBUG', false );

// --- Action hooks --------------------------------------------------------- //

if (!function_exists('register_blipper_widget')) {
/**
  * Register the WP Blipper widget
  *
  * @since 0.0.1
  */
  function register_blipper_widget() {
    register_widget( 'Blipper_Widget' );
  }
  add_action( 'widgets_init', 'register_blipper_widget' );
}

if (!function_exists('blipper_widget_add_settings_link')) {
/**
  * Add a link to the Blipper Widget settings page from the installed plugins
  * list.
  *
  * @since 0.0.1
  */
  function blipper_widget_add_settings_link( $links ) {
    $links[] = '<a href="' .
      esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) .
      '">' . __('Settings', 'blipper-widget') . '</a>';

    return $links;
  }
  add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'blipper_widget_add_settings_link' );
}

if (!function_exists('blipper_widget_exception')) {
/**
  * Generic error handling
  *
  * @since 0.0.1
  */
  function blipper_widget_exception( $e ) {
    if ( BW_DEBUG ) {
      error_log( 'Blipper Widget: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile() . '.' );
    }
    _e('<p class="blipper-widget error">Blipper Widget: An unexpected error has occurred. ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile() . '.</p>', 'blipper-widget');
  }
  set_exception_handler('blipper_widget_exception');
}
