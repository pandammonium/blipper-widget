<?php

/**
 * The main file for Blipper Widget.
 * Plugin Name:        Blipper Widget
 * Plugin URI:         https://wordpress.org/plugins/blipper-widget/
 * Description:        Display your latest blip in a widget.  Requires a Blipfoto account (available free of charge).
 * Version:            1.2.6-alpha
 * Requires at least:  4.3
 * Tested up to:       6.7
 * Requires PHP:       8.4
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

namespace Blipper_Widget;

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

require_once( plugin_dir_path(__FILE__ ) . 'classes/class-settings.php' );
require_once( plugin_dir_path(__FILE__ ) . 'classes/class-widget.php' );

use Blipper_Widget\Settings\Blipper_Widget_Settings;
use Blipper_Widget\Widget\Blipper_Widget;

// -------------------------------------------------------------------------- //

/**
 * @ignore This variable is used to determine the debug status of WordPress
 * and should not be accessed elsewhere.
 * @var bool $status True if WordPress is set to log debug information.
 * @since 1.2.6
 */
$status = ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) && ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG );
/**
 * @ignore This constant is used for debug purposes only.
 */
define( 'BW_DEBUG', false && $status );
/**
 * @var The prefix used to identify Blipper Widget strings.
 *
 * Used in the cache key to distinguish it from other transient keys.
 *
 * @author pandammonium
 * @since 1.2.3
 */
define( 'BW_ID', 'bw' );
/**
 * @var The prefix used in the cache key to distinguish it from other
 * transient keys.
 *
 * @author pandammonium
 * @since 1.2.3
 */
define( 'BW_PREFIX', BW_ID . '_' );
/**
 * @var The prefix used to identify Blipper Widget debug notices.
 *
 * @author pandammonium
 */
define( 'BW_PREFIX_DEBUG', strtoupper( BW_ID ) . ' | ' );

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

    register_widget( 'Blipper_Widget\Widget\Blipper_Widget' );
  }
  add_action(
    hook_name:'widgets_init',
    callback: 'Blipper_Widget\bw_register_widget'
  );
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

      $links = array_merge( $links, [ '<a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">' . __('Settings', 'blipper-widget') . '</a>' ] );

    }
    return $links;
  }
  add_filter(
    hook_name: 'plugin_action_links',
    callback: 'Blipper_Widget\bw_add_settings_link',
    priority: 10,
    accepted_args: 2
  );
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
      error_log( BW_PREFIX_DEBUG . wp_strip_all_tags( $e->getMessage() ) . ' in '. $function . '() on line ' . $e->getLine() . ' in ' . $e->getFile() . '.' );
    }
    return __('<p class="blipper-widget error">Blipper Widget | ' . $e->getMessage() . ' in <code>'. $function . '()</code> on line ' . $e->getLine() . ' in ' . $e->getFile() . '.</p>', 'blipper-widget');
  }
  set_exception_handler( 'Blipper_Widget\bw_exception' );
}

if ( !function_exists( 'bw_delete_all_cached_blips')) {
  function bw_delete_all_cached_blips( string $prefix ): bool {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );

    global $wpdb;
    $deleted = [];

    // Get all the Blipper Widget transients:
    $transients = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_' . $prefix ) . '%'
    ));
    // error_log( 'transients: ' . var_export( $transients, true ) );

    // Loop through and delete each transient
    foreach ( $transients as $transient ) {
      $transient_name = str_replace( '_transient_', '', $transient );
      // error_log( 'transient: ' . var_export( $transient_name, true ) );
      $result = delete_transient( $transient_name );
      // error_log( 'deleted transient: ' . var_export( $result, true ) );
      $deleted[] = $result;
    }
    // Check all the Blipper Widget transients were deleted:
    $result = array_all( $deleted, function( string $value ) {
      return true === $value;
    });
    // error_log( 'result of deletion: ' . var_export( $result, true ) );
    return $result;
  }
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
  function bw_log( string $data_name, mixed $data = null, bool $echo = false, bool $includes_data = true ): string {
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
        $string = BW_PREFIX_DEBUG . print_r( $data_name, true );
        if ( $includes_data ) {
          error_log( $string . ': ' . var_export( $data, true ) );
        } else {
          error_log( $string );
        }
      }
      return BW_PREFIX_DEBUG . print_r( $data_name, true ) . ': ' . var_export( $data, true );
    } else {
      return '';
    }
  }
}
