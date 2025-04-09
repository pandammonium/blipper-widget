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

require_once( plugin_dir_path( __FILE__ ) . 'classes/class-settings.php' );
require_once( plugin_dir_path( __FILE__ ) . 'classes/class-widget.php' );

use Blipper_Widget\Settings\Blipper_Widget_Settings;
use Blipper_Widget\Widget\Blipper_Widget;

// -------------------------------------------------------------------------- //

if ( wp_get_development_mode() || 'development' === wp_get_environment_type() ) {
  /**
   * @var bool $status True if WordPress is set to log debug information.
   * @ignore This variable is used to determine the debug status of WordPress
   * and should not be accessed elsewhere.
   * @since 1.2.6
   */
  $status = ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) && ( defined( 'WP_DEBUG_LOG' ) && true === WP_DEBUG_LOG );
  /**
   * @var bool BW_DEBUG Indicates whether Blipper Widget debug output should be turned on (true) or off (false). Depends on WP debug and debug log settings, as given by $status.
   * @ignore This constant is used for debug purposes only.
   * @since 0.x
   */
  define( 'BW_DEBUG', true && $status );
  // error_log( 'debug status: ' . var_export( BW_DEBUG, true ) );
}

/**
 * @var string BW_ID The prefix used to identify Blipper Widget strings.
 *
 * Used in the cache key to distinguish it from other transient keys.
 *
 * @author pandammonium
 * @since 1.2.3
 */
define( 'BW_ID', 'bw' );

/**
 * @var string BW_PREFIX The prefix used in the cache key to distinguish it from other
 * transient keys.
 *
 * @author pandammonium
 * @since 1.2.3
 */
define( 'BW_PREFIX', BW_ID . '-' );

/**
 * @var string BW_PREFIX_DEBUG The prefix used to identify Blipper Widget debug notices.
 *
 * @author pandammonium
 */
define( 'BW_PREFIX_DEBUG', strtoupper( BW_ID ) . ' | ' );

/**
 * @var string BW_ID_BASE The string that WP uses as the ID base for widgets.
 *
 * @author pandammonium
 * @since 1.2.6
 */
define( 'BW_ID_BASE', 'blipper_widget' );

/**
 * @var string BW_DB_PREFIX The string to prefix database options with.
 *
 * @author pandammonium
 * @since 1.2.6
 */
define( 'BW_DB_PREFIX', 'blipper-widget' );

/**
 */
define( 'BW_CACHE_KEY_CACHE_KEY_SUFFIX', '--' . 'blip-cache_key' );

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
    // bw_log( 'current filter', current_filter() );

    if ( class_exists( 'Blipper_Widget\Widget\Blipper_Widget' ) ) {
      // error_log( 'registering Blipper_Widget' );
      register_widget( 'Blipper_Widget\Widget\Blipper_Widget' );
    } else {
      throw new \Exception( 'class Blipper_Widget\Widget\Blipper_Widget does not exist' );
    }
    // register_widget( 'Blipper_Widget\Widget\Blipper_Widget' );
    bw_add_action_hooks();
    bw_add_filter_hooks();
  }
  add_action(
    hook_name: 'widgets_init',
    callback: 'Blipper_Widget\bw_register_widget',
    priority: 10,
    accepted_args: 0
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
    // bw_log( 'current filter', current_filter() );

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

if ( !function_exists( 'bw_add_action_hooks' ) ) {
  function bw_add_action_hooks() {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );
    // bw_log( 'current filter', current_filter() );

    $blipper_widget = Widget\Blipper_Widget::bw_get_registration_instance();

    add_action(
      hook_name: 'customize_preview_init',
      callback: [ $blipper_widget, 'bw_on_customise_preview_init' ],
      priority: 10,
      accepted_args: 1
    );
    add_action(
      hook_name: 'customize_save_after',
      callback: [ $blipper_widget, 'bw_on_customise_save_after' ],
      priority: 11,
      accepted_args: 1
    );
  }
}

if ( !function_exists( 'bw_add_filter_hooks' ) ) {
  function bw_add_filter_hooks() {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );
    // bw_log( 'current filter', current_filter() );

    // NB Filters need to return the filtered version of the data they were given to filter.

    // $blipper_widget = Widget\Blipper_Widget::bw_get_registration_instance();

    // add_filter(
    //   hook_name: 'pre_update_option_widget_' . BW_ID_BASE,
    //   callback: [ $blipper_widget, 'bw_capture_old_widget_settings' ],
    //   priority: 10,
    //   accepted_args: 2
    // );
  }
}

// ------ Testing Customiser hooks ------------------------------------------ //

// add_action(
//   hook_name: 'customize_save_after',
//   callback: function( $arg ) {
//     error_log( current_filter() );
//     error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_preview_init',
//   callback: function() {
//     // error_log( current_filter() );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 0
// );

// add_action(
//   hook_name: 'customize_save_after',
//   callback: function( $arg ) {
//     // error_log( current_filter() );
//     // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_publish_after',
//   callback: [ Widget\Blipper_Widget::class, 'bw_on_delete_widget_from_customiser' ],
//   priority: 9999,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'updated_widget',
//   callback: [ Widget\Blipper_Widget::class, 'bw_on_widget_setting_change_in_backend' ],
//   accepted_args: 3
// );


// add_action(
//   hook_name: 'customize_save',
//   callback: function( $arg ) {
//     // // // error_log( current_filter() );
//     // // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// // add_action(
// //   hook_name: 'customize_register',
// //   callback: function( $arg ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 1
// // );

// // add_action(
// //   hook_name: 'customize_load_themes',
// //   callback: function( $arg0, $arg1, $arg2 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 3
// // );

// // add_action(
// //   hook_name: 'customize_render_panel',
// //   callback: function( $arg ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 1
// // );

// // add_action(
// //   hook_name: 'customize_controls_head',
// //   callback: function() {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 0
// // );

// // add_action(
// //   hook_name: 'customize_controls_init',
// //   callback: function() {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 0
// // );

// // add_action(
// //   hook_name: 'customize_save_response',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . var_export( $arg0, true ) );
// //     // // error_log( '$arg1: ' . bw_array_to_string( $arg1 ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_action(
// //   hook_name: 'customize_control_active',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . var_export( $arg0, true ) );
// //     // // error_log( '$arg1: ' . bw_array_to_string( $arg1 ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_filter(
// //   hook_name: 'customize_partial_render',
// //   callback: function( $arg0, $arg1, $arg2 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 3
// // );

// add_action(
//   hook_name: 'customize_post_value_set',
//   callback: function( $arg0, $arg1, $arg2 ) {
//     // // error_log( current_filter() );
//     // // error_log( '$arg0: ' . var_export( $arg0, true ) );
//     // // error_log( '$arg1: ' . var_export( $arg1, true ) );
//     // // error_log( '$arg2: ' . bw_array_to_string( $arg2 ) );

//     if ( str_starts_with( $arg0, 'widget_' . BW_ID_BASE ) ) {
//       // // error_log( PHP_EOL . PHP_EOL . 'BLIPPER WIDGET (arg0)!' . PHP_EOL );
//       $widget_settings = [];
//       $widget_id = preg_replace('/widget_(blipper_widget)\[(\d+)\]/', BW_ID_BASE . '-$2', $arg0 );
//       // // error_log( 'widget id: ' . $widget_id );
//       $result = $this->bw_get_widget_settings( $widget_id, BW_ID_BASE, $widget_settings );
//       // // error_log( 'widget ' . var_export( $widget_id, true ) . ' (' . var_export( $result, true ) . '): ' . var_export( $widget_settings ) );
//     } else if ( str_starts_with( $arg0, 'sidebars_widgets' ) ) {
//       foreach ( $arg1 as $widget_id ) {
//         // // error_log( 'widget id: ' . $widget_id );
//         if ( str_starts_with( $widget_id, BW_ID_BASE ) ) {
//           $widget_settings = [];
//           $result = $this->bw_get_widget_settings( $widget_id, BW_ID_BASE, $widget_settings );
//           // // error_log( 'widget ' . var_export( $widget_id, true ) . ' settings (' . var_export( $result, true ) . '): ' . var_export( $widget_settings ) );
//         }
//       }
//     }
//     return true;
//   },
//   priority: 10,
//   accepted_args: 3
// );

// add_action(
//   hook_name: 'customize_render_control',
//   callback: function( $arg ) {
//     // // // error_log( current_filter() );
//     // // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_render_section',
//   callback: function( $arg ) {
//     // // // error_log( current_filter() );
//     // // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_save_' . BW_ID_BASE,
//   callback: function( $arg ) {
//     // // error_log( current_filter() );
//     // // error_log( var_export( func_get_args(), true ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// // add_action(
// //   hook_name: 'customize_loaded_components',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_action(
// //   hook_name: 'customize_changeset_branching',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . var_export( $arg0, true ) );
// //     // // error_log( '$arg1: ' . bw_array_to_string( $arg1 ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_filter(
// //   hook_name: 'customize_changeset_save_data',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . var_export( $arg0, true ) );
// //     // // error_log( '$arg1: ' . bw_array_to_string( $arg1 ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_filter(
// //   hook_name: 'customize_previewable_devices',
// //   callback: function( $arg ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 1
// // );

// // add_filter(
// //   hook_name: 'customize_dynamic_partial_args',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . bw_array_to_string( $arg0 ) );
// //     // // error_log( '$arg1: ' . var_export( $arg1, true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// // add_filter(
// //   // BAD!
// //   hook_name: 'widget_customizer_setting_args',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( '$arg0: ' . bw_array_to_string( $arg0 ) );
// //     // // error_log( '$arg1: ' . var_export( $arg1, true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// add_action(
//   hook_name: 'customize_controls_print_styles',
//   callback: function() {
//     // // // error_log( current_filter() );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 0
// );

// add_action(
//   hook_name: 'customize_render_partials_after',
//   callback: function( $arg0, $arg1 ) {
//     // // // error_log( current_filter() );
//     // // // error_log( var_export( func_get_args(), true ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 2
// );

// add_action(
//   hook_name: 'customize_controls_print_scripts',
//   callback: function() {
//     // // // error_log( current_filter() );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 0
// );

// add_action(
//   hook_name: 'customize_render_partials_before',
//   callback: function( $arg0, $arg1 ) {
//     // // // error_log( current_filter() );
//     // // // error_log( var_export( func_get_args(), true ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 2
// );

// add_action(
//   hook_name: 'customize_save_validation_before',
//   callback: function( $arg ) {
//     // // error_log( current_filter() );
//     // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_controls_enqueue_scripts',
//   callback: function() {
//     // // // error_log( current_filter() );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 0
// );

// add_filter(
//   hook_name: 'customize_render_partials_response',
//   callback: function( $arg0, $arg1, $arg2 ) {
//     // // // error_log( current_filter() );
//     // // // error_log( var_export( func_get_args(), true ) );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 3
// );

// add_action(
//   hook_name: 'customize_render_control_' . $this->id,
//   callback: function( $arg ) {
//     // // // error_log( current_filter() );
//     // // // error_log( '$arg: ' . bw_array_to_string( $arg ) );
//     // // // error_log( 'this id: ' . $this->id );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 1
// );

// add_action(
//   hook_name: 'customize_render_section_' . $this->id,
//   callback: function() {
//     // // error_log( current_filter() );
//     // // error_log( 'this id: ' . $this->id );
//     return true;
//   },
//   priority: 10,
//   accepted_args: 0
// );

// // add_action(
// //   hook_name: 'stop_previewing_theme',
// //   callback: function( $arg ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 1
// // );

// // add_action(
// //   hook_name: 'start_previewing_theme',
// //   callback: function( $arg ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 1
// // );

// // add_filter(
// //   hook_name: 'update_custom_css_data',
// //   callback: function( $arg0, $arg1 ) {
// //     // // error_log( current_filter() );
// //     // // error_log( var_export( func_get_args(), true ) );
// //     return true;
// //   },
// //   priority: 10,
// //   accepted_args: 2
// // );

// ------ End of testing Customiser hooks ----------------------------------- //

// --- Error handling ------------------------------------------------------- //

if (!function_exists('bw_exception')) {
/**
  * Generic error handling
  *
  * @since 0.0.1
  */
  function bw_exception( \Throwable $e ): string {
    // // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // // bw_log( 'arguments', func_get_args() );

    $trace = $e->getTrace();
    $function = $trace[ 0 ][ 'function' ];
    $message = preg_replace( '/(.*)\.$/', '$1', $e->getMessage() );
    if ( BW_DEBUG ) {
      $current_filter = current_filter();
      if ( $current_filter ) {
        error_log( BW_PREFIX_DEBUG . 'Current filter: ' . var_export( $current_filter, true ) );
      }
      // error_log( BW_PREFIX_DEBUG . 'Debug backtrace: ' . var_export( debug_backtrace( options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: 5 ), true ) );
      error_log( BW_PREFIX_DEBUG . wp_strip_all_tags( $message ) . ' in '. $function . '() on line ' . $e->getLine() . ' in ' . $e->getFile() . '.' );
    }
    return __('<p class="blipper-widget error">Blipper Widget | ' . $message . ' in <code>'. $function . '()</code> on line ' . $e->getLine() . ' in ' . $e->getFile() . '.</p>', 'blipper-widget');
  }
  set_exception_handler( 'Blipper_Widget\bw_exception' );
}

// --- Other methods -------------------------------------------------------- //

if ( !function_exists( 'bw_delete_all_blipper_widget_caches')) {
  function bw_delete_all_blipper_widget_caches( string $prefix ): bool {
    // // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // // bw_log( 'arguments', func_get_args() );

    global $wpdb;
    $deleted = [];

    // Get all the Blipper Widget transients:
    $transients = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s",
        $wpdb->esc_like( '_transient_' . $prefix ) . '%'
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

// --- Debugging ------------------------------------------------------------ //

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
   * @param bool $includes_data Sometimes, it's desirable to output text with
   * no data or value. If false, any data supplied is ignored; if true, the
   * data must be given. Default is true.
   * @param bool $is_html Treat HTML as a special case if true; treat it as
   * any other data if false. Default is false.
   * @param ?int $php_error One of the PHP user error constants. If present, uses trigger_error() to trigger a PHP error of that type.
   */
  function bw_log( string $data_name, mixed $data = null, bool $echo = false, bool $includes_data = true, bool $is_html = false, ?int $php_error = null ): string {
    // error_log( 'function: ' . var_export( __FILE__ . '::' . __FUNCTION__ . '()', true ) );
    // error_log( 'arguments: ' . var_export( func_get_args(), true ) );

    if ( current_user_can( 'manage_options' ) ) {
      switch ( $php_error ) {
        case E_USER_ERROR:
        case E_USER_WARNING:
        case E_USER_NOTICE:
        case E_USER_DEPRECATED:
          trigger_error( print_r( $data_name, true ) . ( $includes_data ? ( ': ' . var_export( $data, true ) ) : '' ), $php_error );
        break;
        default:
          // Do nothing.
      }
    }

    if ( defined( 'BW_DEBUG') && BW_DEBUG ) {
      if ( $is_html ) {
        if ( 'string' === gettype( $data ) ) {
          function bw_pretty_print_html( string $html ): string {
            // Use regex to add a newline after each HTML tag
            $pretty_html = preg_replace('/(>)(<)/', "$1\n$2", $html);
            return "\n" . $pretty_html;
          }
          $data = bw_pretty_print_html( $data );
        } else {
          // error_log( 'html is ' . var_export( gettype( $data ), true ) );
          $data = ' ' . var_export( $data, true );
        }
      }
      if ( $echo ) {
        $string = 'Blipper Widget | ' . print_r( $data_name, true );
        if ( $includes_data ) {
          if ( $is_html ) {
            echo $string . ': <pre>' . htmlspecialchars( $data ) . '</pre>';
          } else {
            echo $string . ': ' . print_r( htmlspecialchars( $data ), true );
          }
        } else {
          echo $string;
        }
      } else {
        $data_name = preg_replace( '/(.*)\.$/', '$1', $data_name );
        $string = BW_PREFIX_DEBUG . print_r( $data_name, true );
        if ( $includes_data ) {
          if ( $is_html ) {
            error_log( $string . ':' . $data );
          } else {
            error_log( $string . ': ' . var_export( $data, true ) );
          }
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

if ( !function_exists( 'bw_array_to_string' ) ) {
  /**
   * Outputs as much information as possible from data with circular references
   * that var_export() and similar can't handle.
   *
   * Recursive.
   *
   * @since 1.2.6
   * @author pandammonium
   *
   * @param mixed $input The data to display as well as possible.
   * @param int $indent_by A factor by which to multiply the number of spaces
   * by. This only needs to be used upon recursive invocations.
   * @return string The data as converted to a string, ready for display.
   */
  function bw_array_to_string( mixed $input, int $indent_by = 0 ): string {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );

    $output = '';
    static $seen = [];
    $indent = str_repeat( '  ', $indent_by );

    $circular_ref_text = function( mixed $input ): string {
      return '⛔️ ' . ( 'object' === gettype( $input ) ? gettype( $input ) . ' ' . get_class( $input ) : gettype( $input ) );
    };
    $output_data = function( mixed $input, string $indent, int $indent_by = 0 ): string {
      $output = '';
      foreach ( $input as $key => $value ) {
        // error_log( 'key: ' . var_export( $key, true ) );
        $output .= $indent . '  ' . var_export( $key, true ) . ' => ' . bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
      }
      return $output;
    };

    switch ( gettype( $input ) ) {
      case 'array':
        // error_log( 'input is array' );
        if ( in_array( $input, $seen, true ) ) {
          // error_log( 'found a circular reference' );
          $output .= $circular_ref_text( $input );
        } else {
          $seen[] = $input;
          $output .= PHP_EOL . $indent . 'array ' . '(' . PHP_EOL;
          // foreach ( $input as $key => $value ) {
          //   // error_log( 'key: ' . var_export( $key, true ) );
          //   $output .= $indent . var_export( $key, true ) . ' => ' . bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
          // }
          $output .= $output_data( $input, $indent, $indent_by );
          $output .= $indent . ')';
        }
      break;
      case 'object':
        // error_log( 'input is object' );
        if ( 'Closure' === get_class( $input ) ) {
          $output .= 'object ' . get_class( $input );
          $seen[] = $input;
        } else {
          if ( in_array( $input, $seen, true ) ) {
            // error_log( 'found a circular reference' );
            $output .= $circular_ref_text( $input );
          } else {
            $seen[] = $input;
            $properties = get_object_vars( $input );
            if ( empty( $properties ) ) {
              // error_log( '  … with no public properties' );
            } else {
              // error_log( '  … with these public properties:' );
              $output .= PHP_EOL . $indent . 'object ' . get_class( $input ) . ' {' . PHP_EOL;
              // foreach ( $properties as $property => $value ) {
              //   // error_log( 'property: ' . var_export( $property, true ) . ' (value type ' . gettype( $value ) . ')' . bw_array_to_string( $value ) );
              //   $output .= $indent . var_export( $property, true ) . ' => ' . bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
              // }
              $output .= $output_data( $properties, $indent, $indent_by );
              $output .= $indent . '}';
            }
          }
        }
      break;
      default:
        // error_log( 'input is ' . gettype( $input ) );
        if ( in_array( $input, $seen, true ) ) {
          // error_log( 'found a circular reference' );
          $output .= $circular_ref_text( $input );
        } else {
          // error_log( $indent . 'value: ' . var_export( $input, true ) );
          $output .= var_export( $input, true );
        }
      break;
    }
    return $output;
  }
}
