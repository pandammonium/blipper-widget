<?php

/**
  * Blipper Widget — uninstallation
  * @since 0.0.3
  *
  * This files uninstalls Blipper Widget and removes the options stored in the
  * database.
  *
  * Some of this code is unashamedly swiped from the uninstall.php file of the
  * WP-Spamshield plug-in.
  */

// If uninstall not called from WordPress, exit:
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit();
}

/**
  * @since 0.0.3
  */
function blipper_widget_uninstall() {

  if ( current_user_can( 'edit_plugins' ) ) {

    // Delete options in database
    $option_name = 'blipper-widget-settings-oauth';
    delete_option( $option_name );
    // For site options in multi-site:
    delete_site_option( $option_name );

    // Unregister the widget
    unregister_widget( 'Blipper_Widget' );

    // Clean up widget options
    $sidebar_widgets = get_option( 'sidebars_widgets' );
    foreach ( $sidebar_widgets as $key => $value ) {
      if ( is_array( $value ) ) {
        foreach ( $value as $inner_key => $inner_value ) {
          if ( false !== strpos( $inner_value, 'blipper_widget' ) ) {
            // Don't want to mess with any widget that isn't the Blipper Widget.
            unset( $sidebar_widgets[$key][$value] );
          }
        }
        // Tidy up the array
        $sidebar_widgets[$key] = array_values( $sidebar_widgets[$key] );
      }
    }
    update_option( 'sidebars_widgets', $sidebar_widgets );

    // Delete orphaned options
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
  }

}

blipper_widget_uninstall();

/**
  * Sorry to see you go.  Bye bye!
  */

?>
