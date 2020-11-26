<?php

/**
  *
  * @link               http://pandammonium.org/wordpress-dev/blipper-widget/
  * @since              0.0.1
  * @package            Blipper_Widget
  *
  * @wordpress-plugin
  * Plugin Name:        Blipper Widget
  * Plugin URI:         http://pandammonium.org/wordpress-dev/blipper-widget/
  * Description:        Display your latest blip in a widget.  Requires a Blipfoto account (available free of charge).
  * Version:            1.1.1
  * Author:             Caity Ross
  * Author URI:         http://pandammonium.org/
  * License:            GPL-2.0+
  * License URI:        http://www.gnu.org/licenses/gpl-2.0.txt
  * Text Domain:        blipper-widget
  * Domain Path:        /languages
  */

/**  Copyright 2015 Caity Ross

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

// If this file is called directly, abort.
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_Client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_BaseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException;
use blipper_widget\blipper_widget_settings;

// --- Action hooks --------------------------------------------------------- //

/**
  * Register the WP Blipper widget
  *
  * @since 0.0.1
  */
function register_blipper_widget() {
  register_widget( 'Blipper_Widget' );
}
add_action( 'widgets_init', 'register_blipper_widget' );

/**
  * Add a link to the Blipper Widget Settings page from the installed plugins
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

/**
  * Generic error handling
  *
  * @since 0.0.1
  */
function blipper_widget_exception( $e ) {
  echo '<p>An unexpected error has occurred.  ' . $e->getMessage() . '  Sorry about that.  Please check your settings or try again later.</p>';
}
set_exception_handler('blipper_widget_exception');

// -- Blipper Widget -------------------------------------------------------- //

/**
 * Widget settings.
 *
 * @since 0.0.2
 */
class Blipper_Widget extends WP_Widget {

/**
  * @since    0.0.1
  * @access   private
  * @var      array     $default_setting_values   The widget's default settings
  */
  private $default_setting_values = array (
    'title'                 => 'My latest blip',
    'display-date'          => 'show',
    'display-journal-title' => 'hide',
    'add-link-to-blip'      => 'hide',
    'display-powered-by'    => 'hide',
    'border-style'          => 'inherit',
    'border-width'          => 'inherit',
    // Using 'inherit' for the default colour causes an error in the colour
    // picker.  Leaving it blank has the same effect as using 'inherit'.
    'border-color'          => 'inherit',
    'background-color'      => 'inherit',
    'color'                 => 'inherit',
    'link-color'            => 'initial',
    'padding'               => '0',
  );

/**
  * @since    0.0.1
  * @access   private
  * @var      blipper_widget_Client     $client   The Blipfoto client
  */
  private $client;

/**
  * @since    0.0.1
  * @access   private
  * @var      blipper_widget_settings   $settings The Blipper Widget settings
  */
  private $settings;

/**
  * Construct an instance of the widget.
  *
  * @since    0.0.1
  * @access   public
  */
  public function __construct() {

    $params = array(
      'description' => __( 'The latest blip from your Blipfoto account.', 'blipper-widget' ),
      'name'        => __( 'Blipper Widget', 'blipper-widget' ),
    );
    parent::__construct( 'blipper_widget', 'Blipper Widget', $params );

    // Not using is_active_widget here because that function is only supposed to
    // return true if the widget is on a sidebar.  The widget isn't necessarily
    // on a sidebar when the OAuth access settings are set.
    $this->load_dependencies();
    $this->settings = new blipper_widget_settings();
    $this->client = null;

    // function to load WP Blipper:
    // add_action( 'admin_notices', 'blipper_widget_settings_check' );
    // add_action( 'load-widgets.php', array( $this, 'blipper_widget_load_colour_picker') );
    add_action( 'admin_enqueue_scripts', array( $this, 'blipper_widget_enqueue_scripts' ) );
    add_action( 'admin_footer-widgets.php', array( $this, 'blipper_widget_print_scripts' ), 9999 );
    add_shortcode('blipper_widget', array( $this, 'blipper_widget_shortcode_blip_display') );

}

/**
  * Render the widget on the WP site in a widget-enabled area.  This is the
  * front-end of the widget.
  *
  * @since    0.0.1
  * @access   public
  */
  public function widget( $args, $settings ) {

    echo $args['before_widget'];

    if ( ! empty( $settings['title'] ) ) {
      echo $args['before_title'] . apply_filters( 'widget_title', $settings['title'] ) . $args['after_title'];
    }

    if ( $this->blipper_widget_create_blipfoto_client( $settings ) ) {
      $this->blipper_widget_display_blip( $settings );
    }

    echo $args['after_widget'];

  }

/**
  * Render the form used in the widget admin settings panel or the WordPress
  * customiser.  This is the back-end of the widget.  The form displays the
  * settings already saved in the database, and allows the user to change them
  * if desired.
  *
  * @since    0.0.1
  * @access   public
  * @param    array     $settings         The settings currently saved in the
  *                                         database
  */
  public function form( $settings ) {

    $this->blipper_widget_display_form( $this->blipper_widget_get_display_values( $settings ) );

  }

/**
  * Update the widget settings that were set using the form in the admin
  * panel/customiser.
  *
  * @since    0.0.1
  * @access   public
  * @param    array     $new_settings     The settings the user wants to change
  * @param    array     $old_settings     The settings currently saved in the
  *                                         database
  * @return   array     $settings         The validated settings based on the
  *                                         user's input to be saved in the
  *                                         database
  */
  public function update( $new_settings, $old_settings ) {

    $title                  = $this->blipper_widget_validate( $new_settings, $old_settings, 'title' );
    $display_date           = $this->blipper_widget_validate( $new_settings, $old_settings, 'display-date' );
    $display_journal_title  = $this->blipper_widget_validate( $new_settings, $old_settings, 'display-journal-title' );
    $add_link_to_blip       = $this->blipper_widget_validate( $new_settings, $old_settings, 'add-link-to-blip' );
    $powered_by             = $this->blipper_widget_validate( $new_settings, $old_settings, 'display-powered-by' );
    $border_style           = $this->blipper_widget_validate( $new_settings, $old_settings, 'border-style' );
    $border_width           = $this->blipper_widget_validate( $new_settings, $old_settings, 'border-width' );
    $border_colour          = $this->blipper_widget_validate( $new_settings, $old_settings, 'border-color' );
    $background_colour      = $this->blipper_widget_validate( $new_settings, $old_settings, 'background-color' );
    $colour                 = $this->blipper_widget_validate( $new_settings, $old_settings, 'color' );
    $link_colour            = $this->blipper_widget_validate( $new_settings, $old_settings, 'link-color' );
    $padding                = $this->blipper_widget_validate( $new_settings, $old_settings, 'padding' );

    $settings['title']                  = $title;
    $settings['display-date']           = $display_date;
    $settings['display-journal-title']  = $display_journal_title;
    $settings['add-link-to-blip']       = $add_link_to_blip;
    $settings['display-powered-by']     = $powered_by;
    $settings['border-style']           = $border_style;
    $settings['border-width']           = $border_width;
    $settings['border-color']           = $border_colour;
    $settings['background-color']       = $background_colour;
    $settings['color']                  = $colour;
    $settings['link-color']             = $link_colour;
    $settings['padding']                = $padding;

    return $settings;

  }
/**
 * Add a shortcode so the widget can be placed in a post or on a page.
 *
 * @var   array    $atts        The settings (attributes) included in the
 *                                shortcode.  Not all the default settings are
 *                                necessarily supported.
 * @var   string   $content     The content, if any, from between the shortcode
 *                                tags.
 *
 * @since 1.1
 */
public function blipper_widget_shortcode_blip_display( $atts, $content=null, $shortcode="", $print=false) {

  $default_title_level = 'h2';
  $args = shortcode_atts( $this->default_setting_values, $atts, $shortcode );
  extract( $args );

  // Attributes that aren't included in the defaults but that are used in the display of the shortcode.
  $args['title-level'] = isset( $atts['title-level'] ) ? $atts['title-level'] : $default_title_level;
  $args['display-body'] = isset( $atts['display-body'] ) ? 'show' : 'hide';

  // There are better ways of removing these keys from the array, but this seems
  // the simplest, especially given that the values are not necessarily known.
  // They are removed because the border doesn't necessarily fit snugly around
  // the image, so it looks a bit naff.  The removed values will be replaced by
  // the defaults when the blip is constructed.
  unset ( $args['border-style'] );
  unset ( $args['border-width'] );
  unset ( $args['border-color'] );
  unset ( $args['background-color'] );
  unset ( $args['padding'] );
  if ( ! isset( $args['display-body'] ) && ! empty( $args['display-body'] ) ) {
    return $args['display-body'];
  }

  $the_title = '';
  if ( ! empty( $args['title'] ) ) {
    if ( ! ( $args['title-level'] === 'h1' ||
             $args['title-level'] === 'h2' ||
             $args['title-level'] === 'h3' ||
             $args['title-level'] === 'h4' ||
             $args['title-level'] === 'h5' ||
             $args['title-level'] === 'h6' ||
             $args['title-level'] === 'p' ) ) {
      $args['title-level'] = $default_title_level;
    }
    $the_title = '<' . $args['title-level'] . '>' . apply_filters( 'widget_title', $args['title'] ) . '</' . $args['title-level'] . '>';
  }

  error_log( "Blipper_Widget::blipper_widget_shortcode_blip_display( " . json_encode( $atts ) . "), '" . $content . "', " . $shortcode . ")\n" );
  // error_log( "SHORTCODE: " . $shortcode );
  error_log( "Collated arguments: " . json_encode( $args ) . "\n" );
  // error_log( "CONTENT: " . json_encode( $content ) );
  if ( $this->blipper_widget_create_blipfoto_client( $args ) ) {
    return $the_title . $this->get_blipper_widget_display_blip( $args, $content );
  }

}

/**
  * Validate the input.
  * Make sure the input comprises only printable/alphanumeric (depending on the
  * field) characters; otherwise, return an empty string/the default value.
  *
  * @since    0.0.1
  * @access   private
  * @var      array     $new_settings     The setting the user wants to change
  * @var      array     $old_settings     The setting currently saved in the
  *                                         database
  * @var      string    $setting_field    The setting to validate.
  * @return   string    $settings         The validated setting.
  */
  private function blipper_widget_validate( $new_settings, $old_settings, $setting_field ) {

    error_log( "Blipper_Widget::blipper_widget_validate( $setting_field )" );
    error_log( "\tCurrent value:   " . ( array_key_exists( $setting_field, $old_settings ) ? $old_settings[$setting_field] : "undefined" ) );
    error_log( "\tProposed value:  " . ( array_key_exists( $setting_field, $new_settings ) ? $new_settings[$setting_field] : "undefined" ) );

    if ( array_key_exists( $setting_field, $old_settings ) && array_key_exists( $setting_field, $new_settings ) ) {
      if ( $new_settings[$setting_field] === $old_settings[$setting_field] ) {
        $settings =  $old_settings[$setting_field];
        error_log( "\tValue unchanged\n" );
        return $settings;
      }
    }

    $settings = $this->default_setting_values[$setting_field];

    if ( array_key_exists( $setting_field, $new_settings ) ) {
      $new_settings[$setting_field] = esc_attr( $new_settings[$setting_field] );
    }

    switch ( $setting_field ) {
      case 'title':
        if ( array_key_exists( $setting_field, $new_settings ) ) {
          if ( true == ctype_print( $new_settings[$setting_field] ) ) {
            $settings = trim( $new_settings[$setting_field] );
          } else if ( empty( $new_settings[$setting_field] ) ) {
            $settings = '';
          } else {
            $settings = 'Please enter printable characters only or leave the field blank';
          }
        }
      break;
      case 'display-date':
      case 'display-journal-title':
      case 'add-link-to-blip':
      case 'display-powered-by':
        $settings = array_key_exists( $setting_field, $new_settings ) ? ( ! empty( $new_settings[$setting_field] ) ? 'show' : 'hide' ) : 'hide';
      break;
      default:
        if ( array_key_exists( $setting_field, $new_settings ) ) {
          if ( ! empty( $new_settings[$setting_field] ) ) {
            $settings = $new_settings[$setting_field];
          }
        }
    }

    error_log( "\tNew value:       $settings\n" );

    return $settings;
  }

/**
  * Get the values to display.
  *
  * @since    0.0.1
  * @access   private
  * @var      array     $settings         The widget settings saved in the
  *                                         database.
  * @return   array                       The widget settings saved in the
  *                                         database
  */
  private function blipper_widget_get_display_values( $settings ) {

    $new_settings['title'] = $this->blipper_widget_get_display_value( 'title', $settings );
    $new_settings['display-date'] = $this->blipper_widget_get_display_value( 'display-date', $settings );
    $new_settings['display-journal-title'] = $this->blipper_widget_get_display_value( 'display-journal-title', $settings );
    $new_settings['add-link-to-blip'] = $this->blipper_widget_get_display_value( 'add-link-to-blip', $settings );
    $new_settings['display-powered-by'] = $this->blipper_widget_get_display_value( 'display-powered-by', $settings );
    $new_settings['border-style'] = $this->blipper_widget_get_display_value( 'border-style', $settings );
    $new_settings['border-width'] = $this->blipper_widget_get_display_value( 'border-width', $settings );
    $new_settings['border-color'] = $this->blipper_widget_get_display_value( 'border-color', $settings );
    $new_settings['background-color'] = $this->blipper_widget_get_display_value( 'background-color', $settings );
    $new_settings['color'] = $this->blipper_widget_get_display_value( 'color', $settings );
    $new_settings['link-color'] = $this->blipper_widget_get_display_value( 'link-color', $settings );
    $new_settings['padding'] = $this->blipper_widget_get_display_value( 'padding', $settings );

    return $new_settings;

  }

  private function blipper_widget_get_display_value( $setting, $settings ) {

    return array_key_exists( $setting, $settings ) ? esc_attr( $settings[$setting] ) : $this->default_setting_values[$setting];

  }

/**
  * Load the files this widget needs.
  *
  * @since    0.0.1
  * @access   private
  */
  private function load_dependencies() {

    require( plugin_dir_path( __FILE__ ) . 'includes/class-settings.php' );
    // require( plugin_dir_path( __FILE__ ) . 'includes/class-colour-picker.php' );

    $this->load_blipfoto_dependencies();

  }

/**
  * Load the Blipfoto API.
  *
  * @since    0.0.1
  * @access   private
  */
  private function load_blipfoto_dependencies() {

    $folders = array(
        'Traits' => array(
          'Helper'
          ),
        'Exceptions' => array(
          'BaseException',
          'ApiResponseException',
          'InvalidResponseException',
          'NetworkException',
          'OAuthException',
          'FileException'
          ),
        'Api' => array(
          'Client',
          'OAuth',
          'Request',
          'Response',
          'File'
          )
        );

    $path = plugin_dir_path( __FILE__ ) . 'includes/Blipfoto/';

    foreach ( $folders as $folder => $files ) {
      foreach ( $files as $file ) {
        require( $path . $folder . '/' . $file . '.php' );
      }
    }
  }

/**
  * Construct an instance of the Blipfoto client and test it's ok
  *
  * @since    0.0.1
  * @access   private
  * @param    array     $settings         The settings just saved in the
  *                                         database
  * @return   bool      $client_ok        True if the client was created
  *                                         successfully, else false
  */
  private function blipper_widget_create_blipfoto_client( $settings ) {

    $client_ok = false;
    $this->client = null;
    try {

      // Get the settings from the database
      $oauth_settings = $this->settings->blipper_widget_get_settings();

      if ( empty( $oauth_settings['username'] ) && empty( $oauth_settings['access-token'] ) ) {
        throw new blipper_widget_OAuthException( 'Missing username and access token.');
      } else if ( empty( $oauth_settings['username'] ) ) {
        throw new blipper_widget_OAuthException( 'Missing username.' );
      } else if ( empty( $oauth_settings['access-token'] ) ) {
        throw new blipper_widget_OAuthException( 'Missing access token.' );
      }

      // Create a new client using the OAuth settings from the database

      $this->client = new blipper_widget_Client (
        '',
        '',
        $oauth_settings['access-token']
      );

      if ( empty( $this->client ) || ! isset( $this->client ) ) {

        throw new blipper_widget_ApiResponseException( 'Failed to create the Blipfoto client.' );

      } else {

        $client_ok = true;

      }

    } catch ( blipper_widget_OAuthException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( 'OAuth error.  ' . $e->getMessage() . '  Please check your OAuth settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.', 'blipper-widget' ) . '</p>';
      }

    } catch ( blipper_widget_ApiResponseException $e ) {

      if ( current_user_can( 'manage_options' ) ) {
        echo '<p>' . __( 'Blipfoto error.  ' . $e->getMessage() . 'Please try again later.', 'blipper-widget' ) . '</p>';
      }

    }

    if ( true == $client_ok ) {
      $client_ok = false;
      try {
        $user_profile = $this->client->get( 'user/profile' );
        if ( $user_profile->error() ) {

          throw new blipper_widget_ApiResponseException( $user_profile->error() );

        }

        $user = $user_profile->data()['user'];

        if ( $user['username'] != $oauth_settings['username'] ) {

          throw new blipper_widget_OAuthException( 'Unable to verify user.  Please check the username you entered on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> is correct.' );

        } else {

          $client_ok = true;

        }

      } catch ( blipper_widget_OAuthException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'OAuth error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      } catch ( blipper_widget_ApiResponseException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'Blipfoto error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      } catch ( blipper_widget_BaseException $e ) {

        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'Error.  ' . $e->getMessage(), 'blipper-widget' ) . '</p>';
        }

      }
    }
    return $client_ok;

  }


/**
  * Get the blip using the settings stored in the database.
  *
  * @since    1.1
  * @access   private
  * @param    array     $settings         The settings saved in the database
  * @param    string    $content          The content from the shortcode (i.e.
  *                                         the stuff that goes between the
  *                                         opening shortcode tag and the
  *                                         closing shortcode tag).  Not
  *                                         accessible from the widget settings
  *                                         when in a widgety area
  */
  private function get_blipper_widget_display_blip( $settings, $content=null ) {

    $the_blip = '';
    $user_profile = null;
    $user_settings = null;
    $continue = false;

    try {

      $user_profile = $this->client->get( 'user/profile' );

      if ( $user_profile->error() ) {
        throw new blipper_widget_ApiResponseException( $user_profile->error() . '  Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
      } else {
        $continue = true;
      }

    } catch ( blipper_widget_ApiResponseException $e ) {
      if ( current_user_can( 'manage_options' ) ) {
        $the_blip = '<p>Blipfoto error.  ' . $e->getMessage() . '</p>';
      }
    }

    if ( $continue ) {
      $continue = false;

      try {

        $user_settings = $this->client->get( 'user/settings' );

        if ( $user_settings->error() ) {
          throw new blipper_widget_ApiResponseException( $user_settings->error() . '  Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          $continue = true;
        }

      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    if ( $continue ) {
      $continue = false;

      try {

        $user = $user_profile->data('user');

        if ( empty( $user ) ) {
          throw new blipper_widget_ApiResponseException( 'Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.');
        } else {
          $continue = true;
        }

      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    if ( $continue ) {
      $continue = false;

      try {

        // A page index of zero gives the most recent page of blips.
        // A page size of one means there will be only one blip on that page.
        // Together, these ensure that the most recent blip is obtained — which
        // is exactly what we want to display.
        $journal = $this->client->get(
          'entries/journal',
          array(
            'page_index'  => 0,
            'page_size'   => 1
          )
        );

        if ( $journal->error() ) {
          throw new blipper_widget_ApiResponseException( $journal->error() . '  Can\'t access your journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }

      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    if ( $continue ) {
      $continue = false;

      try {

        $blips = $journal->data( 'entries' );

        if ( empty( $blips ) ) {
          throw new ErrorException( 'Can\'t access your journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          $continue = true;
        }

      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    // Assuming any blips have been retrieved, there should only be one.
    if ( $continue ) {
      $continue = false;

      try {

        switch ( count( $blips ) ) {
          case 0:
            throw new Exception( 'No blips found.  Do you have <a href="https://www.blipfoto.com/' . $user['username'] . '" rel="nofollow">any Blipfoto entries</a>?');
          break;
          case 1:
            $continue = true;
          break;
          default:
            throw new ErrorException( count( $blips ) . ' blips found, but was only looking for one.  Something has gone wrong.  Please try again.');
        }

      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      } catch ( Exception $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    if ( $continue ) {
      $continue = false;

      $blip = $blips[0];
      try {

        $details = $this->client->get(
          'entry',
          array(
            'entry_id'          => $blip['entry_id_str'],
            'return_details'    => 1,
            'return_image_urls' => 1
          )
        );

        if ( $details->error() ) {
          throw new blipper_widget_ApiResponseException( $details->error() . '  Can\'t get the blip details.' );
        } else {
         $continue = true;
        }

      } catch ( blipper_widget_ApiResponseException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Blipfoto error.  ' . $e->getMessage() . '</p>';
        }
      }

    }

    // Get the blip's descriptive text as HTML, which will need to
    // be sanitised on use.
    if ( isset ( $settings['display-body'] ) && $continue ) {
      $continue = false;

      $descriptive_text = null;
      // Get the blip's descriptive text, which may contain markup.
      try {
        if ( $details->data( 'details.description_html' ) ) {
          $descriptive_text = $details->data( 'details.description_html' );
          $continue = true;
        } else {
          throw new ErrorException('Did not get the descriptive text.');
        }
      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }
    }

    if ( $continue ) {
      $continue = false;

      // Blipfoto has different quality images, each with its own URL.
      // Access is currently limited by Blipfoto to standard resolution, but
      // the plugin nevertheless looks for the highest quality image available.
      $image_url = null;
      try {

        if ( $details->data( 'image_urls.original' ) ) {
          $image_url = $details->data( 'image_urls.original' );
        } else if ( $details->data( 'image_urls.hires' ) ) {
          $image_url = $details->data( 'image_urls.hires' );
        } else if ( $details->data( 'image_urls.stdres' ) ) {
          $image_url = $details->data( 'image_urls.stdres' );
        } else if ( $details->data( 'image_urls.lores' ) ) {
          $image_url = $details->data( 'image_urls.lores' );
        } else {
          throw new ErrorException('Unable to get URL of image.');
        }

      } catch ( ErrorException $e ) {
        if ( current_user_can( 'manage_options' ) ) {
          $the_blip = '<p>Error.  ' . $e->getMessage() . '</p>';
        }
      }

      $continue = ! empty ( $image_url );
    }

    if ( $continue ) {

      // Display the blip.

      $the_blip = "<div class='the-blip'>";

      $the_blip .= '<figure class=\'the-blip-image\' style="'
        . $this->blipper_widget_get_style( $settings, 'border-style')
        . $this->blipper_widget_get_style( $settings, 'border-width')
        . $this->blipper_widget_get_style( $settings, 'border-color')
        . $this->blipper_widget_get_style( $settings, 'background-color' )
        . $this->blipper_widget_get_style( $settings, 'padding' )
        . '">';

      // Link back to the blip on the Blipfoto site.
      $this->blipper_widget_log_display_values( $settings, 'add-link-to-blip', 'get_blipper_widget_display_blip' );
      if ( ! array_key_exists( 'add-link-to-blip' , $settings ) ) {
        // Necessary for when Blipper Widget is added via the Customiser
        $settings['add-link-to-blip'] = $this->default_setting_values['add-link-to-blip'];
      }
      if ( $settings['add-link-to-blip'] == 'show' ) {
        $the_blip .= '<a href="https://www.blipfoto.com/entry/' . $blip['entry_id_str'] . '" rel="nofollow">';
      }
      // Add the image.
      $the_blip .= '<img src="'
        . $image_url
        . ' "class="blipper-widget-image" alt="'
        . $blip['title']
        . '">';
      // Close the link (anchor) tag.
      if ( $settings['add-link-to-blip'] == 'show' ) {
        $the_blip .= '</a>';
      }
          . $this->sanitise_url( $image_url )

      // Display any associated data.
      $the_blip .= '<figcaption class=\'the-blip-image-caption\' style="padding-top:7px;'
        . $this->blipper_widget_get_style( $settings, 'color' )
        . '">';

      // Date (optional), title and username
      $this->blipper_widget_log_display_values( $settings, 'display-date', 'get_blipper_widget_display_blip' );
      if ( ! array_key_exists( 'display-date' , $settings ) ) {
        // Necessary for when Blipper Widget is added via the Customiser
        $settings['display-date'] = $this->default_setting_values['display-date'];
      }
      if ( $settings['display-date'] == 'show' ) {
        $the_blip .= date( get_option( 'date_format' ), $blip['date_stamp'] );
        if ( !empty( $blip['title'] ) ) {
          $the_blip .= '<br>';
        }
      }
      if ( ! empty( $blip['title'] ) ) {
        $the_blip .= '<i>'
          . $blip['title']
          . '</i>';
      }
      $the_blip .= ' '
        . __( 'by', 'blipper-widget' )
        . ' '
        . $user['username'];

      // Display any content provided by the user in a shortcode.
      if ( ! empty( $content ) ) {
        $the_blip .= '<br /><span class=\'the-blip-image-caption-content\'>'
          . $content
          . '</span>';
      }

      // Journal title and/or display-powered-by link.
      $this->blipper_widget_log_display_values( $settings, 'display-journal-title', 'get_blipper_widget_display_blip' );
      $this->blipper_widget_log_display_values( $settings, 'display-powered-by', 'get_blipper_widget_display_blip' );
      if ( ! array_key_exists( 'display-journal-title' , $settings ) ) {
        // Necessary for when Blipper Widget is added via the Customiser.
        $settings['display-journal-title'] = $this->default_setting_values['display-journal-title'];
      }
      if ( ! array_key_exists( 'display-powered-by' , $settings ) ) {
        // Necessary for when Blipper Widget is added via the Customiser.
        $settings['display-powered-by'] = $this->default_setting_values['display-powered-by'];
      }

      if ( $settings['display-journal-title'] == 'show' && $settings['display-powered-by'] == 'show' ) {
        $the_blip .= '<footer class=\'the-blip-image-caption-footer\' style="margin-bottom:0"><p style="font-size:75%;">'
          . __( 'From', 'blipper-widget' )
          . ' <a href="https://www.blipfoto.com/'
          . $user_settings->data( 'username' )
          . '" rel="nofollow" style="'
          . $this->blipper_widget_get_style( $settings, 'link-color' )
          . '">'
          . $user_settings->data( 'journal_title' )
          . '</a> | Powered by <a href="https://www.blipfoto.com/" rel="nofollow" style="'
          . $this->blipper_widget_get_style( $settings, 'link-color' )
          . '">Blipfoto</a></p></footer>';
      } else if ( $settings['display-journal-title'] == 'show' && $settings['display-powered-by'] == 'hide' ) {
        $the_blip .= '<footer><p style="font-size:75%">'
          . __( 'From', 'blipper-widget' )
          . ' <a href="https://www.blipfoto.com/'
          . $user_settings->data( 'username' )
          . '" rel="nofollow" style="'
          . $this->blipper_widget_get_style( $settings, 'link-color' )
          . '">' . $user_settings->data( 'journal_title' )
          . '</a></p></footer>';
      } else if ( $settings['display-journal-title'] == 'hide' && $settings['display-powered-by'] == 'show' ) {
        $the_blip .= '<footer><p style="font-size:75%">'
          . __( 'Powered by', 'blipper-widget' )
          . ' <a href="https://www.blipfoto.com/" rel="nofollow" style="'
          . $this->blipper_widget_get_style( $settings, 'link-color' )
          . '">Blipfoto</a></p></footer>';
      } else {
        error_log( "Blipper_Widget::get_blipper_widget_display_blip( 'display-journal-title', 'display-powered-by' )\tnot displayed\n" );
      }

      $the_blip .= '</figcaption></figure>';

      $the_blip .= empty( $descriptive_text ) ? '' : '<div class=\'the-blip-descriptive-text\'>'
        . $this->sanitise_html( $descriptive_text )
        . '</div>';

      $the_blip .= "</div>"; // .the-blip

      error_log( "The completed blip:\n" . $the_blip );

    }

    return $the_blip;
  }
/**
 * Sanitise third-party HTML.
 *
 * @since     1.1.1
 * @access    private
 * @param     string     $html   The HTML string to be sanitised.
 * @return    string             The sanitised HTML string.
 *
 */
private function sanitise_html( $html ) {
  return wp_kses( $html, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'b', 'em', 'div'] );
}
/**
 * Sanitise URL.
 *
 * @since     1.1.1
 * @access    private
 * @param     string     $html   The URL to be sanitised.
 * @return    string             The sanitised URL.
 *
 */
private function sanitise_url( $url ) {
  return esc_url( $url );
}

/**
  * Display the blip using the settings stored in the database.
  *
  * @since    0.0.1
  * @access   private
  * @param    array     $settings         The settings saved in the database
  * @param    string    $content          The content from the shortcode (i.e.
  *                                         the stuff that comes between the
  *                                         opening shortcode tag and the
  *                                         closing shortcode tag).  Not
  *                                         accessible from the widget settings
  *                                         when in a widgety area
  */
  private function blipper_widget_display_blip( $settings, $content=null ) {

    echo $this->get_blipper_widget_display_blip( $settings, $content );

  }

/**
  * Display the back-end widget form.
  *
  * @since     0.0.1
  * @access    private
  * @param     array         $settings       The settings saved in the database
  */
  private function blipper_widget_display_form( $settings ) {

    error_log( "Blipper_Widget::blipper_widget_display_form\nproperty " . var_export( $settings, true ) . "\n" );

    $oauth_settings = $this->settings->blipper_widget_get_settings();

    if ( empty( $oauth_settings['username'] ) ||
         empty( $oauth_settings['access-token'] )

      ) {

      echo '<p>You need to set the Blipfoto settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>';

    } else {

      ?>
      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
          <?php _e( 'Widget title', 'blipper-widget' ); ?>
        </label>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'title' ) ?>"
          name="<?php echo $this->get_field_name( 'title' ); ?>"
          type="text"
          value="<?php echo esc_attr( $settings['title'] ); ?>"
          placeholder="The title will be blank"
        >
      </p>
      <p class="description">Leave the widget title field blank if you don't want to display a title.  The default widget title is <i><?php _e( $this->default_setting_values['title'] ); ?></i>.</p>

      <p>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'display-date' ); ?>"
          name="<?php echo $this->get_field_name( 'display-date' ); ?>"
          type="checkbox"
          value="1"
          <?php checked( 'show', esc_attr( $settings['display-date'] ) ); ?>
        >
        <label for="<?php echo $this->get_field_id( 'display-date' ); ?>">
          <?php _e( 'Display date of your latest blip', 'display-date' ) ?>
        </label>
      </p>
      <p class="description">Untick the box to hide the date of your latest blip.  Leave it ticked if you want to display the date of your latest blip.  The box is ticked by default.</p>

      <p>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>"
          name="<?php echo $this->get_field_name( 'add-link-to-blip' ); ?>"
          type="checkbox"
          value="1"
          <?php checked( 'show', esc_attr( $settings['add-link-to-blip'] ) ); ?>
        >
        <label for="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>">
          <?php _e( 'Include link to your latest blip', 'add-link-to-blip' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to include a link from the image link back to the corresponding blip in your journal.  Leave it unticked if you don't want to include a link back to your latest blip.  The box is unticked by default.</p>

      <p>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'display-journal-title' ); ?>"
          name="<?php echo $this->get_field_name( 'display-journal-title' ); ?>"
          type="checkbox"
          value="1"
          <?php checked( 'show', esc_attr( $settings['display-journal-title'] ) ); ?>
        >
        <label for="<?php echo $this->get_field_id( 'display-journal-title' ); ?>">
          <?php _e( 'Display journal title and link', 'display-journal-title' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to show the name of your journal with a link back to your Blipfoto journal.  Leave it unticked if you don't want to show the name of your journal and link back to your journal.  The box is unticked by default.</p>

      <p>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'display-powered-by' ); ?>"
          name="<?php echo $this->get_field_name( 'display-powered-by' ); ?>"
          type="checkbox"
          value="1"
          <?php checked( 'show', esc_attr( $settings['display-powered-by'] ) ); ?>
        >
        <label for="<?php echo $this->get_field_id( 'display-powered-by' ); ?>">
          <?php _e( 'Include a \'powered by\' link', 'display-powered-by' ) ?>
        </label>
      </p>
      <p class="description">Tick the box to include a 'powered by' link back to Blipfoto.  Leave it unticked if you don't want to include a 'powered by' link.  The box is unticked by default.</p>

      <h4>Styling</h4>
      <p class="description">You can style your widget if you like.  If you leave the default settings, the widget will be displayed using your normal sidebar style.</p>

      <p>
        <label for="<?php echo $this->get_field_id( 'border-style' ); ?>">
          <?php _e( 'Border style', 'blipper-widget' ) ?>
        </label>
        <select
          class="widefat"
          id="<?php echo $this->get_field_id( 'border-style' ); ?>"
          name="<?php echo $this->get_field_name('border-style'); ?>">
          <option value="inherit" <?php selected( 'inherit', esc_attr( $settings['border-style'] ) ); ?>>default</option>
          <option value="none" <?php selected( 'none', esc_attr( $settings['border-style'] ) ); ?>>none</option>
          <option value="solid" <?php selected( 'solid', esc_attr( $settings['border-style'] ) ); ?>>solid line</option>
          <option value="dotted" <?php selected( 'dotted', esc_attr( $settings['border-style'] ) ); ?>>dotted line</option>
          <option value="dashed" <?php selected( 'dashed', esc_attr( $settings['border-style'] ) ); ?>>dashed line</option>
          <option value="double" <?php selected( 'double', esc_attr( $settings['border-style'] ) ); ?>>double line</option>
          <option value="groove" <?php selected( 'groove', esc_attr( $settings['border-style'] ) ); ?>>groove</option>
          <option value="ridge" <?php selected( 'ridge', esc_attr( $settings['border-style'] ) ); ?>>ridge</option>
          <option value="inset" <?php selected( 'inset', esc_attr( $settings['border-style'] ) ); ?>>inset line</option>
          <option value="outset" <?php selected( 'outset', esc_attr( $settings['border-style'] ) ); ?>>outset line</option>
        </select>
      </p>
      <p class="description">The default style uses your theme's style.  The border won't show if the style is set to 'no line'.</p>
      <p>
        <label for="<?php echo $this->get_field_id( 'border-width' ); ?>">
          <?php _e( 'Border width', 'blipper-widget' ); ?>
        </label>
        <select
          class="widefat"
          id="<?php echo $this->get_field_id( 'border-width' ); ?>"
          name="<?php echo $this->get_field_name( 'border-width' ); ?>"
        >
          <option value="inherit" <?php selected( 'inherit', esc_attr( $settings['border-width'] ) ); ?>>default</option>
          <option value="0px" <?php selected( '0px', esc_attr( $settings['border-width'] ) ); ?>>0 pixels</option>
          <option value="1px" <?php selected( '1px', esc_attr( $settings['border-width'] ) ); ?>>1 pixel</option>
          <option value="2px" <?php selected( '2px', esc_attr( $settings['border-width'] ) ); ?>>2 pixels</option>
          <option value="3px" <?php selected( '3px', esc_attr( $settings['border-width'] ) ); ?>>3 pixels</option>
          <option value="4px" <?php selected( '4px', esc_attr( $settings['border-width'] ) ); ?>>4 pixels</option>
          <option value="5px" <?php selected( '5px', esc_attr( $settings['border-width'] ) ); ?>>5 pixels</option>
          <option value="6px" <?php selected( '6px', esc_attr( $settings['border-width'] ) ); ?>>6 pixels</option>
          <option value="7px" <?php selected( '7px', esc_attr( $settings['border-width'] ) ); ?>>7 pixels</option>
          <option value="8px" <?php selected( '8px', esc_attr( $settings['border-width'] ) ); ?>>8 pixels</option>
          <option value="9px" <?php selected( '9px', esc_attr( $settings['border-width'] ) ); ?>>9 pixels</option>
          <option value="10px" <?php selected( '10px', esc_attr( $settings['border-width'] ) ); ?>>10 pixels</option>
        </select>
      </p>
      <p class="description">The border width is in pixels.  The default is to use your theme's style.  The border won't show if the width is zero.</p>


      <script type='text/javascript'>
          jQuery(document).ready(function($) {
            $('.blipper-widget-colour-picker').wpColorPicker();
          });
      </script>
      <p>
        <label for="<?php echo $this->get_field_id( 'border-color' ); ?>">
          <?php _e( 'Border colour', 'blipper-widget' ); ?>
        </label><br>
        <input
          class="blipper-widget-colour-picker widefat"
          id="<?php echo $this->get_field_id( 'border-color' ); ?>"
          name="<?php echo $this->get_field_name( 'border-color' ); ?>"
          type="text"
          value="<?php echo esc_attr( $settings['border-color'] ); ?>"
          placeholder="#"
          data-default-color="<?php //echo $this->default_setting_values['border-color']; ?>"
        >
      </p>
      <p class="description">
        Pick a colour for the widget border colour.  Clearing your colour choice will use the colour set by your theme.
      </p>

      <script type='text/javascript'>
          jQuery(document).ready(function($) {
            $('.blipper-widget-colour-picker').wpColorPicker();
          });
      </script>
      <p>
        <label for="<?php echo $this->get_field_id( 'background-color' ); ?>">
          <?php _e( 'Background colour', 'blipper-widget' ); ?>
        </label><br>
        <input
          class="blipper-widget-colour-picker widefat"
          id="<?php echo $this->get_field_id( 'background-color' ); ?>"
          name="<?php echo $this->get_field_name( 'background-color' ); ?>"
          type="text"
          value="<?php echo esc_attr( $settings['background-color'] ); ?>"
          placeholder="#"
          data-default-color="<?php //echo $this->default_setting_values['background-color']; ?>"
        >
      </p>
      <p class="description">
        Pick a colour for the widget background colour.  Clearing your colour choice will use the colour set by your theme.
      </p>

      <script type='text/javascript'>
          jQuery(document).ready(function($) {
            $('.blipper-widget-colour-picker').wpColorPicker();
          });
      </script>
      <p>
        <label for="<?php echo $this->get_field_id( 'color' ); ?>">
          <?php _e( 'Text colour', 'blipper-widget' ); ?>
        </label><br>
        <input
          class="blipper-widget-colour-picker widefat"
          id="<?php echo $this->get_field_id( 'color' ); ?>"
          name="<?php echo $this->get_field_name( 'color' ); ?>"
          type="text"
          value="<?php echo esc_attr( $settings['color'] ); ?>"
          placeholder="#"
          data-default-color="<?php //echo $this->default_setting_values['color']; ?>"
        >
      </p>
      <p class="description">
        Pick a colour for the widget text colour.  Clearing your colour choice will use the colour set by your theme.  The link text will always be the same colour as the surrounding text.
      </p>

      <script type='text/javascript'>
          jQuery(document).ready(function($) {
            $('.blipper-widget-colour-picker').wpColorPicker();
          });
      </script>
      <p>
        <label for="<?php echo $this->get_field_id( 'link-color' ); ?>">
          <?php _e( 'Link colour', 'blipper-widget' ); ?>
        </label><br>
        <input
          class="blipper-widget-colour-picker widefat"
          id="<?php echo $this->get_field_id( 'link-color' ); ?>"
          name="<?php echo $this->get_field_name( 'link-color' ); ?>"
          type="text"
          value="<?php echo esc_attr( $settings['link-color'] ); ?>"
          placeholder="#"
          data-default-color="<?php //echo $this->default_setting_values['link-color']; ?>"
        >
      </p>
      <p class="description">
        Pick a colour for the widget link colour.  Clearing your colour choice will use the colour set by your theme.
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'padding' ); ?>">
          <?php _e( 'Padding (pixels)', 'blipper-widget' ); ?>
        </label>
        <input
          class="widefat"
          id="<?php echo $this->get_field_id( 'padding' ); ?>"
          name="<?php echo $this->get_field_name( 'padding' ); ?>"
          type="number"
          min="0"
          max="20"
          step="1"
          value="<?php echo $settings['padding'] ? esc_attr( $settings['padding'] ) : $this->default_setting_values['padding']; ?>"
        >
      </p>
      <p class="description">
        Pick a number of pixels between zero and twenty.  Changing the padding will increase the distance between the border and the edge of the image.  Bear in mind that the more padding you have, the smaller your image will appear.
      </p>

      <?php
    }

  }

  private function blipper_widget_get_style( $settings, $style_element ) {

    $this->blipper_widget_log_display_values( $settings, $style_element, 'blipper_widget_get_style' );

    $element = $style_element;
    $style = '';

    switch( $style_element ) {
      case 'link-color':
        $element = 'color';
        return array_key_exists( $style_element, $settings )
          ? ( empty( $settings[$style_element] )
            ? $element . ':' . $this->default_setting_values[$style_element]
            : $element . ':' . $settings[$style_element] . ';'
            )
          : $element . ':' . $this->default_setting_values[$style_element] . ';';
      case 'padding':
        return array_key_exists( $style_element, $settings )
          ? ( empty( $settings[$style_element] )
            ? $element . ':' . $this->default_setting_values[$style_element]
            : $element . ':' . $settings[$style_element] . 'px' . ';'
            )
          : $element . ':' . $this->default_setting_values[$style_element] . 'px' . ';';

      default:
        return array_key_exists( $style_element, $settings )
          ? ( empty( $settings[$style_element] )
            ? $element . ':' . $this->default_setting_values[$style_element]
            : $element . ':' . $settings[$style_element] . ';'
            )
          : $element . ':' . $this->default_setting_values[$style_element] . ';';
    }

  }

  private function blipper_widget_log_display_values( $settings, $display_element, $function_name ) {
      $message =
        array_key_exists( $display_element, $settings )
        ? ( empty( $settings[$display_element] )
          ? ( "Key has no value; using default: " . $this->default_setting_values[$display_element] )
          : ( "Value: " . $settings[$display_element] )
          )
        : ( "No key, no value; adding default: " . $this->default_setting_values['display-journal-title'] );
      error_log( "Blipper_Widget::$function_name( $display_element )" );
      error_log( "\t" . $message . "\n" );
  }

  // --- Action hooks ------------------------------------------------------- //

  /**
   * Check the Blipfoto OAuth settings have been set, otherwise display a message to the user.
   */
  public function blipper_widget_settings_check() {
    $api = get_option('blipper-widget-settings-oauth');
    if ( !empty( $api ) ) {
      $apistring = implode( '', $api );
    }
    if ( empty( $apistring ) ) {
      $optionslink = 'options-general.php?page=blipper-widget';
      $msgString = __('Please update <a href="%1$s">your settings for Blipper Widget</a>.','blipper-widget');
      echo "<html><body><div class='error'><p>" . sprintf( $msgString, $optionslink ) . "</p></div></body></html>";
    }
  }

  /**
   * Add the WP colour picker.
   */
  public function blipper_widget_load_colour_picker() {
    error_log( "Blipper_Widget::blipper_widget_load_colour_picker()" );
  }

  public function blipper_widget_enqueue_scripts( $hook_suffix ) {
    // error_log( "Blipper_Widget::blipper_widget_enqueue()" );
    // error_log( "\tHook suffix: $hook_suffix\n" );
    if ( 'widgets.php' === $hook_suffix ) {
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_script( 'wp-color-picker' );
    }
  }

  /**
   * Print scripts.
   *
   * @since 0.0.5
   */
  public function blipper_widget_print_scripts() {
    ?>
    <script>
      ( function( $ ){
        function initColourPicker( widget ) {
          widget.find( '.blipper-widget-colour-picker' ).wpColorPicker( {
            change: _.throttle( function() { // For Customiser
              $(this).trigger( 'change' );
            }, 3000 )
          });
        }

        function onFormUpdate( event, widget ) {
          initColourPicker( widget );
        }

        $( document ).on( 'widget-added widget-updated', onFormUpdate );

        $( document ).ready( function() {
          $( '#widgets-right .widget:has(.blipper-widget-colour-picker)' ).each( function () {
            initColourPicker( $( this ) );
          } );
        } );
      }( jQuery ) );
      </script>
    <?php
  }

}

