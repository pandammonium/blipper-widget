<?php

/**
  * Uninstalls Blipper Widget.
  * Uninstalls Blipper Widget and removes the options stored in the database.
  * Some of this code is unashamedly swiped from the uninstall.php file
  * of the WP-Spamshield plugin.
  *
  * @author   pandammonium
  * @since    0.0.3
  * @license  GPLv2 or later
  * @package  Pandammonium-BlipperWidget-Uninstall
  */

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();
// If uninstall not called from WordPress, exit:
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit();
}

require_once( plugin_dir_path(__FILE__ ) . 'blipper-widget.php' );

// use Blipper_Widget;
use function Blipper_Widget\bw_delete_all_cached_blips;
use function Blipper_Widget\bw_exception;

if (!function_exists( 'blipper_widget_uninstall' )) {
/**
 * Uninstalls Blipper Widget.
  * @since  0.0.3
  * @return void
  */
  function blipper_widget_uninstall() {
    // bw_log( 'function', __FILE__ . '::' . __FUNCTION__ . '()' );
    // bw_log( 'arguments', func_get_args() );
    // bw_log( 'current filter', current_filter() );

    if ( current_user_can( 'edit_plugins' ) ) {

      try {
        // Delete options in database:
        $option_names = [
          'blipper-widget-settings-oauth',
        ];
        foreach ( $option_names as $option_name ) {
          delete_option( $option_name );
          // For site options in multi-site:
          delete_site_option( $option_name );
        }

        // Clean up widget options
        $sidebar_widgets = get_option( 'sidebars_widgets' );
        foreach ( $sidebar_widgets as $key => $value ) {
          if ( is_array( $value ) ) {
            foreach ( $value as $inner_key => $inner_value ) {
              if ( false !== strpos( $inner_value, 'blipper_widget' ) ||
                   false !== strpos( $inner_value, 'blipper-widget' ) ||
                   false !== strpos( $inner_value, BW_PREFIX ) ) {
                // Don't want to mess with any widget that isn't the Blipper Widget.
                // error_log( 'sidebar widgets [key][0]: ' . var_export( $sidebar_widgets[$key][0], true ) );
                unset( $sidebar_widgets[$key][0] );
              }
            }
            // Tidy up the array
            $sidebar_widgets[$key] = array_values( $sidebar_widgets[$key] );
          }
        }
        update_option( 'sidebars_widgets', $sidebar_widgets );

        // Unregister the widget:
        unregister_widget( 'Blipper_Widget' );

      } catch ( \TypeError $e ) {
        bw_exception( $e );
      } catch ( \Exception $e ) {
        bw_exception( $e );
      }

      try {
        // Delete orphaned options:
        $all_options = wp_load_alloptions();
        foreach ( $all_options as $key => $value ) {
          if ( false !== strpos( $key, 'blipper_widget'  ) ) {
            delete_option( $key );
            // For options in multi-site:
            delete_site_option( $key );
          }
          if ( false !== strpos( $key, 'blipper-widget'  ) ) {
            delete_option( $key );
            // For options in multi-site:
            delete_site_option( $key );
          }
        }
      } catch ( \TypeError $e ) {
        bw_exception( $e );
      } catch ( \Exception $e ) {
        bw_exception( $e );
      }

      try {
        // Delete all Blipper Widget transients (cached blips):
        $result = bw_delete_all_cached_blips( BW_PREFIX );
        if ( !$result ) {
          throw new \Exception( 'Failed to delete all cached blips. Please check your transients and delete all those that start with ' . BW_PREFIX );
        }
      } catch ( \TypeError $e ) {
        bw_exception( $e );
      } catch ( \Exception $e ) {
        bw_exception( $e );
      }
    }
  }
}

blipper_widget_uninstall();

/** Sorry to see you go.  Bye bye! */


