<?php

/**
 * The main file for Blipper Widget.
 * Plugin Name:        Blipper Widget
 * Plugin URI:         https://wordpress.org/plugins/blipper-widget/
 * Description:        Display your latest blip in a widget.  Requires a Blipfoto account (available free of charge).
 * Version:            1.2.6-alpha
 * Requires at least:  4.3
 * Tested up to:       6.7
 * Requires PHP:       8.0
 * Author:             Caity Ross
 * Author URI:         http://pandammonium.org/
 * License:            GPL-2.0 or later
 * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:        blipper-widget
 * @link               http://pandammonium.org/wordpress-dev/blipper-widget/
 * @since              0.0.1
 * @package            Pandammonium-BlipperWidget
 * @author             lumpysimon, pandammonium
 * @license            GPLv2 or later
 * @wordpress-plugin
 */

/**
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
/**
 * @ignore
 */
define( 'BW_PREFIX', 'BW | ' );

// --- Action hooks --------------------------------------------------------- //

if (!function_exists('bw_register_widget')) {
/**
  * Register the WP Blipper widget
  *
  * @since 0.0.1
  */
  function bw_register_widget() {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );

    register_widget( 'Blipper_Widget' );
  }
  add_action( 'widgets_init', 'bw_register_widget' );
}

if (!function_exists('bw_add_settings_link')) {
/**
  * Add a link to the Blipper Widget settings page from the installed plugins
  * list.
  *
  * @since 0.0.1
  */
  function bw_add_settings_link( $links, $file ) {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );

    if ( strpos( $file, 'blipper-widget.php' ) !== false ) {

      $links = array_merge( $links, array( '<a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">' . __('Settings', 'blipper-widget') . '</a>' ) );

    }
    return $links;
  }
  add_filter( 'plugin_action_links', 'bw_add_settings_link', 10, 2 );
}

if (!function_exists('bw_exception')) {
/**
  * Generic error handling
  *
  * @since 0.0.1
  */
  function bw_exception( $e ) {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );

    $trace = $e->getTrace();
    $function = $trace[ 0 ][ 'function' ];
    if ( BW_DEBUG ) {
      error_log( BW_PREFIX . wp_strip_all_tags( $e->getMessage() ) . ' in '. $function . '() on line ' . $e->getLine() . ' in ' . $e->getFile() . '.' );
    }
    return __('<p class="blipper-widget error">Blipper Widget | ' . $e->getMessage() . ' in <code>'. $function . '()</code> on line ' . $e->getLine() . ' in ' . $e->getFile() . '.</p>', 'blipper-widget');
  }
  set_exception_handler('bw_exception');
}

if ( !function_exists( 'bw_log' ) ) {
  /**
   * Logs the provided data either to the WP error log or to the display.
   *
   * Uses the `$echo` flag to determine whether to log to the WP error log or
   * to the display.
   *
   * @author pandammonium
   * @since 1.2.3
   *
   * @param string sdata_name The name of or label for the data.
   * @param mixed $data The data to be logged.
   * @param bool $echo
   * * `true`: Echo the data name and the data
   * * `false`: Send the data name and the data to the error log (default)
   */
  function bw_log( string $data_name, mixed $data = null, bool $echo = false, bool $includes_data = true ) {
    // error_log( 'function: ' . var_export( __FILE__ . '::' . __FUNCTION__ . '()', true ) );
    // error_log( 'arguments: ' . var_export( func_get_args(), true ) );

    if ( BW_DEBUG ) {
      if ( $echo ) {
        $string = 'Blipper Widget: ' . print_r( $data_name, false );
        if ( $includes_data ) {
          echo $string . ': ' . var_export( $data, false );
        } else {
          echo $string;
        }
      } else {
        $string = BW_PREFIX . print_r( $data_name, true );
        if ( $includes_data ) {
          error_log( $string . ': ' . var_export( $data, true ) );
        } else {
          error_log( $string );
        }
      }
      return BW_PREFIX . print_r( $data_name, true ) . ': ' . var_export( $data, true );
    } else {
      return '';
    }
  }
}
