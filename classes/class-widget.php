<?php

/**
  * Blipper Widget shortcode and widget.
  * @author   pandammonium
  * @since    0.0.2
  * @license  GPLv2 or later
  * @package  Pandammonium-BlipperWidget-Widget
  *
  */

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_Client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_BaseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException;
use blipper_widget\settings\blipper_widget_settings;

if (!class_exists('Blipper_Widget')) {
  /**
   * The Blipper Widget class.
   *
   * @since 0.0.2
   */
  class Blipper_Widget extends WP_Widget {

    /**
      * The default widget settings.
      * @since    0.0.1
      * @property array     $default_setting_values   The widget's default settings
      */
    private $default_setting_values = array ();

    /**
     * @var string Defines the length of time the cache should be retained for in
     * minutes. Must have a numeric value (i.e. `true ===
     * is_numeric($this->cache_expiry)`).
     * Default: `'60'`.
     * @access private
     *
     * @author pandammonium
     * @since 1.2.3
     */
    private string $cache_expiry;
    /**
     * @var string The key used to identify each cache. Generated using the
     * MD5 algorithm, which isn't recommended for secure cryptographic
     * applications; its use here is only to generate a unique key from the
     * provided data, so additional security is not required.
     * * Default: `''`.
     * @access private
     *
     * @author pandammonium
     * @since 1.2.3
     */
    private string $cache_key;
    /**
     * @const The prefix used in the cache key to distinguish it from other
     * transient keys.
     * * Default: `''`.
     * @access private
     *
     * @author pandammonium
     * @since 1.2.3
     */
    private const CACHE_PREFIX = 'bw_';

    /**
      * @since    1.1.1
      * @property array     $style_control_classes   The classes used for styling
      *                                              the widget.
      */
      private $style_control_classes;

    /**
      * @since    0.0.1
      * @property blipper_widget_Client     $client   The Blipfoto client
      */
      private $client;

    /**
      * @since    0.0.1
      * @property blipper_widget_settings   $settings The Blipper Widget settings
      */
      private $settings;

      private const QUOTES = array(
       '“' => '',
       '”' => '',
       '‘' => '',
       '’' => '',
       '&#8217;' => '',
       '&#8217;' => '',
       '&#8220;' => '',
       '&#8221;' => ''
      );

    /**
      * Construct an instance of the widget.
      *
      * @since    0.0.1
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

      $this->default_setting_values = array (
        'widget'     => array (
          'border-style'           => 'inherit',
          'border-width'           => 'inherit',
          'border-color'           => 'inherit',
          'background-color'       => 'inherit',
          'color'                  => 'inherit',
          'link-color'             => 'inherit',
          'padding'                => 'inherit',
          'style-control'          => 'widget-settings-only',  // 'css'
          'updated'                => false,                   // true
        ),
        'shortcode'  => array (
          'title-level'            => 'h2',                    // 'h1'–'h6','p'
          'display-desc-text'      => 'hide',                  // 'show'
        ),
        'common'     => array (
          'title'                  => 'My latest blip',
          'display-date'           => 'show',                  // 'hide'
          'display-journal-title'  => 'hide',                  // 'show'
          'display-powered-by'     => 'hide',                  // 'show'
          'add-link-to-blip'       => 'hide',                  // 'show'
        ),
      );
      $this->settings = new blipper_widget_settings();
      $this->style_control_classes = array ();
      $this->client = null;
      $this->cache_expiry = '60'; // minutes
      $this->cache_key = '';


      // function to load WP Blipper:
      // add_action( 'admin_notices', 'blipper_widget_settings_check' );
      // add_action( 'load-widgets.php', array( $this, 'blipper_widget_load_colour_picker') );

      add_action( 'admin_enqueue_scripts', array( $this, 'blipper_widget_enqueue_scripts' ) );

      add_action( 'admin_footer-widgets.php', array( $this, 'blipper_widget_print_scripts' ), 9999 );

      add_shortcode('blipper_widget', array( $this, 'blipper_widget_shortcode_blip_display') );

      // blipper_widget_log( 'this', $this );

    }

    /**
      * Render the widget on the WP site in a widget-enabled area.  This is the
      * front-end of the widget.
      *
      * @since    0.0.1
      * @api
      */
    public function widget( $args, $settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      echo $args['before_widget'];

      $the_title = '';
      if ( ! empty( $settings['title'] ) ) {
        $the_title = $args['before_title'] . apply_filters( 'widget_title', $settings['title'] ) . $args['after_title'];
      }

      echo $this->render_the_blip( $args, $settings, $the_title, true );

      echo $args['after_widget'];

    }

    /**
      * Render the form used in the widget admin settings panel or the WordPress
      * customiser.  This is the back-end of the widget.  The form displays the
      * settings already saved in the database, and allows the user to change them
      * if desired.
      *
      * @since    0.0.1
      * @api
      * @param    array     $settings  The settings currently saved in the database
      * @return void
      */
    public function form( $settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $this->blipper_widget_display_form( $this->blipper_widget_get_display_values( $settings ) );

    }

    /**
      * Update the widget settings that were set using the form in the admin
      * panel/customiser.
      *
      * @since    0.0.1
      * @api
      * @param    array     $new_settings     The settings the user wants to change
      * @param    array     $old_settings     The settings currently saved in the database
      * @return   array     $settings         The validated settings based on the user's input to be saved in the database
      */
    public function update( $new_settings, $old_settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $settings = array();
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
      $style_control          = $this->blipper_widget_validate( $new_settings, $old_settings, 'style-control');

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
      $settings['style-control']          = $style_control;

      // blipper_widget_log( 'old settings', $old_settings );
      // blipper_widget_log( 'new settings', $new_settings );

      $updated_settings_only = array_diff( $old_settings, $new_settings );
      // blipper_widget_log( 'Updated settings', $updated_settings_only );
      $settings['updated'] = empty( $updated_settings_only ) ? false : true;
      // blipper_widget_log( 'settings updated', $settings['updated'] );

      return $settings;

    }

    /**
     * Add a shortcode so the widget can be placed in a post or on a page.
     *
     * @param array    $atts        The settings (attributes) included in the
     *                                shortcode.  Not all the default settings are
     *                                necessarily supported.
     * @param string   $content     The content, if any, from between the shortcode
     *                                tags.
     *
     * @since 1.1
     */
    public function blipper_widget_shortcode_blip_display( $atts, $content = null, $shortcode = '', $print = false ) {

        // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
        // blipper_widget_log( 'arguments', func_get_args() );

      try {
        $atts = $this->normalise_attributes( $atts, $shortcode );

        $defaults = array_merge( $this->default_setting_values['shortcode'], $this->default_setting_values['common'] );

        // blipper_widget_log( 'default settings', $defaults );
        // blipper_widget_log( 'user settings', $atts );

        $args = shortcode_atts( $defaults, $atts, $shortcode );
        extract( $args );

        $the_title = '';
        if ( ! empty( $args['title'] ) ) {
          if ( ! ( $args['title-level'] === 'h1' ||
                   $args['title-level'] === 'h2' ||
                   $args['title-level'] === 'h3' ||
                   $args['title-level'] === 'h4' ||
                   $args['title-level'] === 'h5' ||
                   $args['title-level'] === 'h6' ||
                   $args['title-level'] === 'p' ) ) {
            $args['title-level'] = $this->blipper_widget_get_default_setting_value( 'shortcode', 'title-level' );
          }
          $the_title = '<' . $args['title-level'] . '>' . apply_filters( 'widget_title', $args['title'] ) . '</' . $args['title-level'] . '>';
        }

        return $this->render_the_blip( $args, $defaults, $the_title, false, $content );
      } catch( Exception $e ) {
        return blipper_widget_exception( $e );
      }
    }

    /**
     * Gets the HTML to render the blip.
     *
     * If the blip has not yet been cached or its settings have been changed
     * by the user, generate the HTML for the blip from scratch, otherwise use
     * the cached blip.
     *
     * @author pandammonium
     * @since 1.2.3
     *
     * @param string[] $args The array of WP widget settings.
     * @param string[] $settings The array of BW settings from either the
     * widget or the shortcode.
     * @param string The formatted title to be used for this blip.
     * @return string|bool The HTML that will render the blip or false on failure.
     */
    private function render_the_blip( array $args, array $settings, string $the_title, $is_widget, $content = null ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $this->cache_key = self::CACHE_PREFIX . md5( $this->cache_expiry . implode( ' ', $args ) . $the_title );

      try {
        $the_cache = $this->get_cache();
        // blipper_widget_log( 'This blip has been cached', false === $the_cache ? 'no' : 'yes' );
        // blipper_widget_log( 'This blip\'s settings have changed', false === $settings['updated'] ? 'no' : 'yes' );

        if ( false === $the_cache || $settings['updated'] ) {

          // The blip does not exist in the cache or its settings have changed, so it needs to be generated:
          return $this->generate_blip( $args, $settings, $the_title, $is_widget, $content );

        } else {

          // The blip has been cached recently and its settings have not changed, so return the cached blip:
          return $the_cache;
        }
      } catch ( Exception $e ) {
        return blipper_widget_exception( $e );
      }
    }

    /**
     * Generate the blip from scratch
     */
     private function generate_blip( array $args, array $settings, string $the_title, $is_widget, $content ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      if ( $this->blipper_widget_create_blipfoto_client() ) {
        $the_blip = '<!-- Start of Blipper Widget ' . BW_VERSION . ' -->' . $the_title . $this->blipper_widget_get_blip( $args, $settings, $is_widget, $content ) . '<!-- End of Blipper Widget ' . BW_VERSION . ' -->';

        // Save the blip in the cache for next time:
        $this->set_cache( $the_blip );
        return $the_blip;
      } else {
        return false;
      }

     }

    /**
     * Normalise the arguments from the shortcode
     */
    private function normalise_attributes( string|array|null $atts, $shortcode = '' ): string|array|null {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      // blipper_widget_log( 'type of attributes', gettype( $atts ) );

      if ( null === $atts ) {
        return null;
      } else {
        switch( gettype( $atts ) ) {
          case 'array':
            if ( isset( $atts[ 'title' ] ) ) {
              $atts[ 'title' ] = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $atts[ 'title' ]);
              $i = 0;
              foreach ( $atts as $key => $value ) {
                if ( ( $i === $key ) && isset( $atts[ $key ] ) ) {
                  $atts[ $key ] = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $atts[ $key ]);
                  $atts[ 'title' ] .= ' ' . $atts[ $key ];
                  unset( $atts[ $i ] );
                  ++$i;
                }
              }
            }
          break;
          case 'string':
            $atts = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $atts);
          break;
          default:
            throw new Exception( 'Please check your shortcode: <samp><kbd>[' . ( '' === $shortcode ? '&lt;shortcode&gt;' : $shortcode ) . ' ' . print_r( $atts, true ) . ']' . '</kbd></samp>. These attributes are invalid', E_USER_ERROR ) ;
        }
      }
      // blipper_widget_log( 'normalised attributes', $atts );
      return $atts;
    }

    /**
      * Validate the input.
      * Make sure the input comprises only printable/alphanumeric (depending on the
      * field) characters; otherwise, return an empty string/the default value.
      *
      * @since    0.0.1
      * @param    array     $new_settings     The setting the user wants to change
      * @param    array     $old_settings     The setting currently saved in the
      *                                         database
      * @param    string    $setting_field    The setting to validate.
      * @return   string    $setting          The validated setting.
      */
    private function blipper_widget_validate( $new_settings, $old_settings, $setting_field ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $setting = null;

      // blipper_widget_log( 'setting field', $setting_field );
      // blipper_widget_log( 'old setting', $old_settings[$setting_field] );
      // blipper_widget_log( 'proposed setting', $old_settings[$setting_field] );

      if ( array_key_exists( $setting_field, $old_settings ) && array_key_exists( $setting_field, $new_settings ) ) {
        if ( $new_settings[$setting_field] === $old_settings[$setting_field] ) {
          $setting =  $old_settings[$setting_field];
          // blipper_widget_log( 'setting unchanged', $setting );
          return $setting;
        }
      }

      if ( array_key_exists( $setting_field, $new_settings ) ) {
        $new_settings[$setting_field] = esc_attr( $new_settings[$setting_field] );
      }

      switch ( $setting_field ) {
        case 'title':
          if ( array_key_exists( $setting_field, $new_settings ) ) {
            if ( true == ctype_print( $new_settings[$setting_field] ) ) {
              $setting = trim( $new_settings[$setting_field] );
            } else if ( empty( $new_settings[$setting_field] ) ) {
              $setting = '';
            } else {
              $setting = 'Please enter printable characters only or leave the field blank';
            }
          }
        break;
        case 'display-date':
        case 'display-journal-title':
        case 'add-link-to-blip':
        case 'display-powered-by':
          $setting = array_key_exists( $setting_field, $new_settings ) ? ( ! empty( $new_settings[$setting_field] ) ? 'show' : 'hide' ) : 'hide';
        break;
        default:
          if ( array_key_exists( $setting_field, $new_settings ) ) {
            if ( ! empty( $new_settings[$setting_field] ) ) {
              $setting = $new_settings[$setting_field];
            } else {
              // blipper_widget_log( 'setting field', 'is empty' );
            }
          } else {
            // blipper_widget_log( 'setting field', 'does not exist' );
          }
      }
      // blipper_widget_log( 'new setting', $setting );

      return $setting;
    }

    /**
      * Get the values to display on the settings form.
      *
      * @since    0.0.1
      * @param    array     $settings         The BW widget settings saved in
      *                                         the database.
      * @return   array                       The widget settings saved in the
      *                                         database
      */
    private function blipper_widget_get_display_values( $settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $new_settings = array();

      try {
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
        $new_settings['style-control'] = $this->blipper_widget_get_display_value( 'style-control', $settings );
        $new_settings['updated'] = $this->blipper_widget_get_display_value( 'updated', $settings );

      } catch ( ErrorException $e ) {

        $this->blipper_widget_display_error_msg( $e, __( 'Please check your settings are valid and try again', 'blipper-widget' ) );

      } catch ( Exception $e ) {

        $this->blipper_widget_display_error_msg( $e, __( 'Something has gone wrong getting the user settings', 'blipper-widget' ) );

      }

      // blipper_widget_log( 'new settings', $new_settings );

      return $new_settings;

    }

    /**
     * Gets the display value.
     */
    private function blipper_widget_get_display_value( $setting, $settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      try {

          if ( array_key_exists( $setting, $settings ) ) {
            return esc_attr( $settings[$setting] );
          } else {
            if ( array_key_exists( $setting, $this->default_setting_values['widget'] ) ) {
              return $this->default_setting_values['widget'][$setting];
            } else if ( array_key_exists( $setting, $this->default_setting_values['common'] ) ) {
              return $this->default_setting_values['common'][$setting];
            } else {
              if ( BW_DEBUG ) {
                error_log( __( 'Invalid setting requested', 'blipper-widget' ) . ': ' . $setting );
              }
              throw new ErrorException( __( 'Invalid setting requested', 'blipper-widget' ) . ':  <strong>' . $setting . '</strong>.' );
              return '';
            }
          }

        } catch ( ErrorException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting the user settings' );

        }

    }

    /**
      * Load the files this widget needs.
      *
      * @since    0.0.1
      */
    private function load_dependencies() {

      require_once( plugin_dir_path( __FILE__ ) . 'class-settings.php' );

      $this->load_blipfoto_dependencies();

    }

    /**
      * Load the Blipfoto API.
      *
      * @since    0.0.1
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

      $path = plugin_dir_path( __FILE__ ) . '../includes/Blipfoto/';

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
      * @param    array     $args             The WP widget settings;
      *                                         apparently unused
      * @return   bool      $client_ok        True if the client was created
      *                                         successfully, else false
      */
    private function blipper_widget_create_blipfoto_client( $args = null ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

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
        } else {
          $client_ok = true;
        }

      } catch ( blipper_widget_OAuthException $e ) {

        $this->blipper_widget_display_error_msg( $e, 'Please check your OAuth settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '" rel="nofollow nopopener noreferral">the Blipper Widget settings page</a> to continue' );

      } catch ( Exception $e ) {

        $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting the user' );

      }

      if ( $client_ok ) {
        try {
          $client_ok = false;

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
            // blipper_widget_log( 'client', $this->client );

          }

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Please try again later' );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong creating the client' );

        }
      }

      if ( $client_ok ) {
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

          $this->blipper_widget_display_error_msg( $e );

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e, '', true );

        } catch ( blipper_widget_BaseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( ErrorException $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );
        }
      } else {
        if ( BW_DEBUG ) {
          error_log( 'CLIENT IS NOT OK' );
        }
      }
      return $client_ok;

    }

    /**
      * Get the blip using the settings stored in the database.
      *
      * @since    1.1
      * @param    array     $args             The WP widget settings
      * @param    array     $settings         The BW settings saved in the database
      * @param    bool      $is_widget        True if the blip is to be displayed in
      *                                         a widget; false if it is to be
      *                                         displayed elsewhere
      * @param    string    $content          The content from the shortcode (i.e.
      *                                         the stuff that goes between the
      *                                         opening shortcode tag and the
      *                                         closing shortcode tag).  Not
      *                                         accessible from the widget settings
      *                                         when in a widgety area
      * @return   string                      The blip encoded in HTML
      */
    private function blipper_widget_get_blip( $args, $settings, $is_widget, $content = null ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $user_profile = null;
      $user_settings = null;
      $descriptive_text = null;
      $continue = false;
      $the_blip = '';

      try {

        $user_profile = $this->client->get( 'user/profile' );

        if ( $user_profile->error() ) {
          throw new blipper_widget_ApiResponseException( $user_profile->error() . '  Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          $continue = true;
        }

      } catch ( blipper_widget_ApiResponseException $e ) {

        $this->blipper_widget_display_error_msg( $e );

      } catch ( Exception $e ) {

        $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting your user profile' );

      }

      if ( $continue ) {
        $continue = false;

        try {

          $user_settings = $this->client->get( 'user/settings' );

          if ( $user_settings->error() ) {
            throw new blipper_widget_ApiResponseException( $user_settings->error() . '  Can\'t access your Blipfoto account details.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
          } else {
            $continue = true;
          }

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting your user settings' );

        }

      }

      if ( $continue ) {
        $continue = false;

        try {

          $user = $user_profile->data('user');

          if ( empty( $user ) ) {
            throw new blipper_widget_ApiResponseException( 'Can\'t access your Blipfoto account data.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.');
          } else {
            $continue = true;
          }

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto account' );

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
            throw new blipper_widget_ApiResponseException( $journal->error() . '  Can\'t access your Blipfoto journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
          } else {
            $continue = true;
          }

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto journal' );

        }

      }

      if ( $continue ) {
        $continue = false;

        try {

          $blips = $journal->data( 'entries' );

          if ( empty( $blips ) ) {
            throw new ErrorException( 'Can\'t access your Blipfoto journal entries (blips).  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
          } else {
            $continue = true;
          }

        } catch ( ErrorException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong accessing your entries (blips)' );

        }

      }

      // Assuming any blips have been retrieved, there should only be one.
      if ( $continue ) {
        $continue = false;

        try {

          switch ( count( $blips ) ) {
            case 0:
              throw new Exception( 'No Blipfoto entries (blips) found.  <a href="https://www.blipfoto.com/' . $user['username'] . '" rel="nofollow">Your Blipfoto journal</a> must have at least one entry (blip) before Blipper Widget can display anything.');
            break;
            case 1:
              $continue = true;
            break;
            default:
              throw new blipper_widget_BaseException( 'Blipper Widget was looking for one entry (blip) only, but found ' . count( $blips ) . '. Something has gone wrong.  Please try again' );
          }

        } catch ( blipper_widget_BaseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e );

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
            throw new blipper_widget_ApiResponseException( $details->error() . '  Can\'t get the entry (blip) details.' );
          } else {
           $continue = true;
          }

        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting the entry (blip) details' );

        }

      }

      if ( isset( $settings['display-desc-text'] ) && 'show' === $settings['display-desc-text'] && $continue ) {
        $continue = false;

        try {

          // Use the HTML variation because it's easier to deal with than the
          // version potentially containing markup.
          $descriptive_text = $details->data( 'details.description_html' );

          if ( isset( $descriptive_text ) ) {
            $continue = true;
          } else {
            throw new blipper_widget_ApiResponseException('Did not get the descriptive text.');
          }
        } catch ( blipper_widget_ApiResponseException $e ) {

          $this->blipper_widget_display_error_msg( $e );

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting the entry\'s (blip\'s) descriptive text' );

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

          $this->blipper_widget_display_error_msg( $e );
        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong getting the image URL' );

        }

        $continue = ! empty ( $image_url );
      }

      if ( $continue ) {

        // Display the blip.
        try {

          // Given that all the data used to determine $style_control is passed to blipper_widget_get_styling, it might seem pointless to calculate here once and pass to that function; but this way, it's only calculated once.  I don't really know how much this affects performance.

          // Set $style_control to true if the widget settings form (default for widgets) should be used, otherwise set to false.

          // Need to check whether the style control has been set or not because of, I think, the Customiser.  If it hasn't, then set $style_control to true, indicating that CSS should be used:
          // blipper_widget_log( 'is widget', $is_widget );
          // blipper_widget_log( 'style control is set', isset( $settings['style-control'] ) );
          $style_control = $is_widget ? ( isset( $settings['style-control'] ) ? ( $settings['style-control'] === $this->default_setting_values['widget']['style-control'] ) : true ) : false;
          // blipper_widget_log( 'style control', $style_control );

          $the_blip = "<div" . $this->blipper_widget_get_styling( 'div|blip', $is_widget, $style_control, $settings ) . ">";

          $the_blip .= "<figure" . $this->blipper_widget_get_styling( 'figure', $is_widget, $style_control, $settings ) . ">";

          // Link back to the blip on the Blipfoto site.
          $this->blipper_widget_log_display_values( $settings, 'add-link-to-blip', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'add-link-to-blip' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser
            $settings['add-link-to-blip'] = $this->default_setting_values['common']['add-link-to-blip'];
          }
          if ( $settings['add-link-to-blip'] == 'show' ) {
            $the_url = $this->blipper_widget_sanitise_url( 'https://www.blipfoto.com/entry/' . $blip['entry_id_str'] );
            $the_blip .= '<a href="' . $the_url . '" rel="nofollow">';
          }
          // Add the image.
          $the_blip .= '<img src="'
            . $this->blipper_widget_sanitise_url( $image_url )
            . '"'
            . $this->blipper_widget_get_styling( 'img', $is_widget, $style_control, $settings )
            . ' alt="'
            . $blip['title']
            . '">';
          // Close the link (anchor) tag.
          if ( $settings['add-link-to-blip'] == 'show' ) {
            $the_blip .= '</a>';
          }

          // Display any associated data.
          $the_blip .= "<figcaption" . $this->blipper_widget_get_styling( 'figcaption', $is_widget, $style_control, $settings ) . ">";

          // Date (optional), title and username
          $this->blipper_widget_log_display_values( $settings, 'display-date', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'display-date' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser
            $settings['display-date'] = $this->default_setting_values['common']['display-date'];
          }
          if ( $settings['display-date'] == 'show' || ! empty( $blip['title'] ) ) {
            $the_blip .= "<header" . $this->blipper_widget_get_styling( 'header', $is_widget, $style_control, $settings ) . ">";
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
            . $user['username']
            . '</header>';

          // Display any content provided by the user in a shortcode.
          if ( ! empty( $content ) ) {
            $the_blip .= '<div' . $this->blipper_widget_get_styling( 'div|content', $is_widget, $style_control, $settings ) . '>'
              . $content
              . '</div>';
          }

          // Journal title and/or display-powered-by link.
          $this->blipper_widget_log_display_values( $settings, 'display-journal-title', 'blipper_widget_get_blip' );
          $this->blipper_widget_log_display_values( $settings, 'display-powered-by', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'display-journal-title' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser.
            $settings['display-journal-title'] = $this->default_setting_values['common']['display-journal-title'];
          }
          if ( ! array_key_exists( 'display-powered-by' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser.
            $settings['display-powered-by'] = $this->default_setting_values['common']['display-powered-by'];
          }

        if ( $settings['display-journal-title'] == 'show' || $settings['display-powered-by'] == 'show' ) {
            $the_blip .= "<footer" . $this->blipper_widget_get_styling( 'footer', $is_widget, $style_control, $settings ) . ">";
            if ( $settings['display-journal-title'] == 'show' ) {
                $the_blip .= __( 'From', 'blipper-widget' )
                . ' <a href="https://www.blipfoto.com/'
                . $user_settings->data( 'username' )
                . '" rel="nofollow"' . $this->blipper_widget_get_styling( 'link', $is_widget, $style_control, $settings ) . '>'
                . $user_settings->data( 'journal_title' )
                . '</a>';
            }
            if ( $settings['display-journal-title'] == 'show' && $settings['display-powered-by'] == 'show' ) {
              $the_blip .= ' | ';
            }
            if ( $settings['display-powered-by'] == 'show' ) {
              $the_blip .= 'Powered by <a href="https://www.blipfoto.com/" rel="nofollow"' . $this->blipper_widget_get_styling( 'link', $is_widget, $style_control, $settings ) . '>Blipfoto</a>';
            }
            $the_blip .= '</footer>';
          }
          $the_blip .= '</figcaption></figure>';

          $the_blip .= empty( $descriptive_text ) ? "" : "<div" . $this->blipper_widget_get_styling( 'div|desc-text', $is_widget, $style_control, $settings ) . ">"
            . $this->blipper_widget_sanitise_html( $descriptive_text )
            . '</div>';

          $the_blip .= "</div>"; // .bw-blip

        } catch ( Exception $e ) {

          $this->blipper_widget_display_error_msg( $e, 'Something has gone wrong constructing your entry (blip)' );

        // } finally {
        //   blipper_widget_log( 'The completed blip', $the_blip );
        }

      }

      return $the_blip;

    }

    /**
     * Return the class or style attributes (and their values) used to style the
     * given element.
     *
     * @since    1.1.1
     * @access   private
     * @param    string     $element         The element to be styled
     * @param    bool       $is_widget       Only bother with style attributes if
     *                                         the blip is to be displayed in a
     *                                         widget (true) or not (false)
     * @param    bool       $style_control   The user setting indicating whether
     *                                         widgets should be styled using the
     *                                         widget settings form only (true) or
     *                                         CSS only (false)
     * @param    array      $settings        The user-defined settings containing
     *                                         the style data
     */
    private function blipper_widget_get_styling( $element, $is_widget, $style_control, $settings ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      // If the blip is not to be displayed in a widget or if the widget is to be styled using CSS only, return a class attribute.
      // If the blip is to be displayed in a widget using the widget settings only, return a style attribute.
      // The default is an empty string either way.
      $use_class = ( ! $is_widget || ! $style_control );
      switch ( $element ) {
        case 'div|blip':
        return $use_class ?
          ( ' class=\'bw-blip\'' ) :
          ( ' style=\'' .  $this->blipper_widget_get_style( $settings, 'border-style')
            . $this->blipper_widget_get_style( $settings, 'border-width')
            . $this->blipper_widget_get_style( $settings, 'border-color')
            . '\'' );
        case 'figure':
        return $use_class ?
          ( ' class=\'bw-figure\'' ) :
          ( ' style=\'' . $this->blipper_widget_get_style( $settings, 'background-color' )
            . $this->blipper_widget_get_style( $settings, 'padding' ) . '\'' );
        case 'img':
        return $use_class ?
          ( ' class=\'bw-image\'' ) :
          ( ' style=\'margin:auto;\'' );
        case 'figcaption':
        return $use_class ?
          ( ' class=\'bw-caption\'' ) :
          ( ' style=\'padding-top:7px;line-height:2;'
            . $this->blipper_widget_get_style( $settings, 'color' )
            . '\'' );
        case 'header':
        return $use_class ?
          ( ' class=\'bw-caption-header\'' ) :
          ( '' );
        case 'footer':
        return $use_class ?
          ( ' class=\'bw-caption-footer\'' ) :
          ( ' style=\'font-size:75%;margin-bottom:0;\'' );
        case 'div|content':
        return $use_class ?
          ( ' class=\'bw-caption-content\'' ) :
          ( '' );
        case 'link':
        return $use_class ?
          ( ' class=\'bw-caption-link\'' ) :
          ( ' style=\''
            . $this->blipper_widget_get_style( $settings, 'link-color' )
            . 'text-decoration:none;\'' );
        case 'div|desc-text':
        return $use_class ?
          ( ' class=\'bw-text\'' ) :
          ( '' );
        default:
        return '';
      }

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
    private function blipper_widget_sanitise_html( $html ) {

      $allowed_html = array(
        'p' => array(),
        'h1' => array(),
        'h2' => array(),
        'h3' => array(),
        'h4' => array(),
        'h5' => array(),
        'h6' => array(),
        'i' => array(),
        'b' => array(),
        'em' => array(),
        'div' => array(),
        'br' => array(),
        'a' => array(
          'href' => array(),
          'title' => array(),
        ),
      );
      // blipper_widget_log( 'dirty html', $html );
      // blipper_widget_log( 'clean html', wp_kses( $html, $allowed_html ) );

      return wp_kses( $html, $allowed_html );

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
    private function blipper_widget_sanitise_url( $url ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      return esc_url( $url );

    }

    /**
      * Display the blip using the settings stored in the database.
      *
      * @since    0.0.1
      * @param    array     $settings         The settings saved in the database
      * @param    bool      $is_widget        True if the blip is to be displayed in
      *                                         a widget; false if it is to be
      *                                         displayed elsewhere
      * @param    string    $content          The content from the shortcode (i.e.
      *                                         the stuff that comes between the
      *                                         opening shortcode tag and the
      *                                         closing shortcode tag).  Not
      *                                         accessible from the widget settings
      *                                         when in a widgety area
      */
    private function blipper_widget_display_blip( $settings, $is_widget, $content=null ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      return $this->blipper_widget_get_blip( $settings, $is_widget, $content );

    }

    /**
      * Display the back-end widget form.
      *
      * @since     0.0.1
      * @access    private
      * @param     array         $settings       The settings saved in the database
      */
    private function blipper_widget_display_form( $settings ) {
      // if ( BW_DEBUG ) {
      //   blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      //   blipper_widget_log( 'arguments', func_get_args() );
      // }
      $oauth_settings = $this->settings->blipper_widget_get_settings();

      if ( empty( $oauth_settings['username'] ) ||
           empty( $oauth_settings['access-token'] )

        ) {

        echo '<p>You need to set the Blipfoto settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>';

      } else {

        ?>
        <style>
          div.option {
            background-color: rgb(253,253,253);
            border: none;
            color: inherit;
            margin-top: 2px;
            margin-bottom: 3px;
          }
        </style>

        <div><p class="description">
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
          Leave the widget title field blank if you don't want to display a title.  The default widget title is <i><?php _e( $this->default_setting_values['common']['title'] ); ?></i>.
        </p></div>

        <div class="option"><p class="description">
          <input
            class="widefat"
            id="<?php echo $this->get_field_id( 'display-date' ); ?>"
            name="<?php echo $this->get_field_name( 'display-date' ); ?>"
            type="checkbox"
            value="1"
            <?php checked( 'show', esc_attr( $settings['display-date'] ) ); ?>
          >
          <label for="<?php echo $this->get_field_id( 'display-date' ); ?>">
            <?php _e( 'Display the date of your latest blip', 'display-date' ) ?>
          </label>
        </p>
        <p class="description">
          Untick the box to hide the date of your latest blip.  Leave it ticked if you want to display the date of your latest blip.  The box is ticked by default.
        </p></div>

        <div class="option"><p class="description">
          <input
            class="widefat"
            id="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>"
            name="<?php echo $this->get_field_name( 'add-link-to-blip' ); ?>"
            type="checkbox"
            value="1"
            <?php checked( 'show', esc_attr( $settings['add-link-to-blip'] ) ); ?>
          >
          <label for="<?php echo $this->get_field_id( 'add-link-to-blip' ); ?>">
            <?php _e( 'Include a link to your latest blip', 'add-link-to-blip' ) ?>
          </label>
        </p>
        <p class="description">
          Tick the box to include a link from the image link back to the corresponding blip in your journal.  Leave it unticked if you don't want to include a link back to your latest blip.  The box is unticked by default.
        </p></div>

        <div class="option"><p class="description">
          <input
            class="widefat"
            id="<?php echo $this->get_field_id( 'display-journal-title' ); ?>"
            name="<?php echo $this->get_field_name( 'display-journal-title' ); ?>"
            type="checkbox"
            value="1"
            <?php checked( 'show', esc_attr( $settings['display-journal-title'] ) ); ?>
          >
          <label for="<?php echo $this->get_field_id( 'display-journal-title' ); ?>">
            <?php _e( 'Display your journal title and link', 'display-journal-title' ) ?>
          </label>
        </p>
        <p class="description">
          Tick the box to show the name of your journal with a link back to your Blipfoto journal.  Leave it unticked if you don't want to show the name of your journal and link back to your journal.  The box is unticked by default.
        </p></div>

        <div class="option"><p class="description">
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
        <p class="description">
          Tick the box to include a 'powered by' link back to Blipfoto.  Leave it unticked if you don't want to include a 'powered by' link.  The box is unticked by default.
        </p></div>

        <h4>Styling</h4>
        <p class="description">You can style your widget in one of two ways.
          <ol class="description">
            <li>If you select widget settings only, the default, the styles below will be used to style the widget.  Extra CSS settings will be ignored.  If you don't change any of the settings, the widget will be displayed according to the theme's CSS.</li>
            <li>If you select CSS only, <em>the styles below will not apply</em> and your theme's CSS styles will be used.  Each significant element has its own class, which you can use in the Additional CSS section of the Customiser or in a stylesheet.</li>
          </ol>
          <label for="<?php echo $this->get_field_id( 'style-control' ); ?>">
            <?php _e( 'Style control', 'blipper-widget' ); ?>
          </label>
          <select
            class="widefat"
            id="<?php echo $this->get_field_id( 'style-control' ); ?>"
            name="<?php echo $this->get_field_name( 'style-control' ); ?>">
            <option value="widget-settings-only" <?php selected( 'widget-settings-only', esc_attr( $settings['style-control'] ) ); ?>>widget settings only (default)
            </option>
            <option value="css-only"<?php selected( 'css-only', esc_attr( $settings['style-control'] ) ); ?>>CSS only
            </option>
          </select>
        </p>

        <?php
          // blipper_widget_log( 'name', $this->get_field_name( 'style-control' ) );
          // blipper_widget_log( 'id', $this->get_field_name( 'style-control' ) );
          // blipper_widget_log( 'default value', $this->default_setting_values['widget']['style-control'] );
          // blipper_widget_log('actual value', $settings['style-control'] );
        ?>

        <script>
          jQuery(document).ready(function($) {
            the_value = $('#<?php echo $this->get_field_id( 'style-control' ); ?> option:selected').val();
            console.log( 'On load: ' + the_value );
            if (the_value == 'widget-settings-only') {
              console.log( '  showing' );
              $('.blipper-widget-conditional').show();
            } else {
              console.log( '  hiding' );
              $('.blipper-widget-conditional').hide();
            }
            $('#<?php echo $this->get_field_id( 'style-control' ); ?>').on('change', function() {
              the_value = $('#<?php echo $this->get_field_id( 'style-control' ); ?> option:selected').val();
              console.log('On change: ' + the_value);
              if (the_value == 'widget-settings-only') {
                console.log( '  showing' );
                $('.blipper-widget-conditional').show();
              } else {
                console.log( '  hiding' );
                $('.blipper-widget-conditional').hide();
              }
            });
          });
        </script>

        <div id="blipper-widget-conditional" class="blipper-widget-conditional">

          <div class="option"><p class="description">
            <label for="<?php echo $this->get_field_id( 'border-style' ); ?>">
              <?php _e( 'Border style', 'blipper-widget' ) ?>
            </label>
            <select
              class="widefat"
              id="<?php echo $this->get_field_id( 'border-style' ); ?>"
              name="<?php echo $this->get_field_name( 'border-style'); ?>">
              <option value="inherit" <?php selected( 'inherit', esc_attr( $settings['border-style'] ) ); ?>>default</option>
              <option value="none" <?php selected( 'none', esc_attr( $settings['border-style'] ) ); ?>>none</option>
              <option value="solid" <?php selected( 'solid', esc_attr( $settings['border-style'] ) ); ?>>solid line</option>
              <option value="dotted" <?php selected( 'dotted', esc_attr( $settings['border-style'] ) ); ?>>dotted line</option>
              <option value="dashed" <?php selected( 'dashed', esc_attr( $settings['border-style'] ) ); ?>>dashed line</option>
              <option value="double" <?php selected( 'double', esc_attr( $settings['border-style'] ) ); ?>>double line</option>
              <option value="groove" <?php selected( 'groove', esc_attr( $settings['border-style'] ) ); ?>>groove</option>
              <option value="ridge" <?php selected( 'ridge', esc_attr( $settings['border-style'] ) ); ?>>ridge</option>
              <option value="inset" <?php selected( 'inset', esc_attr( $settings['border-style'] ) ); ?>>inset</option>
              <option value="outset" <?php selected( 'outset', esc_attr( $settings['border-style'] ) ); ?>>outset</option>
            </select>
          </p>
          <p class="description">
            The default style uses your theme's style.  The border won't show if the style is set to 'none'.
          </p></div>

          <div class="option"><p class="description">
            <label for="<?php echo $this->get_field_id( 'border-width' ); ?>">
              <?php _e( 'Border width', 'blipper-widget' ); ?>
            </label>
            <input
              class="widefat"
              id="<?php echo $this->get_field_id( 'border-width' ); ?>"
              name="<?php echo $this->get_field_name( 'border-width' ); ?>"
              type="number"
              min="0"
              max="10"
              step="1"
              placeholder="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'border-width' ); ?>"
              value="<?php echo $settings['border-width'] ? esc_attr( $settings['border-width'] ) : $this->blipper_widget_get_default_setting_value( 'widget', 'border-width' ); ?>"
            >
          </p>
          <p class="description">
            The border width is inherited from your theme by default, but you can choose a value between 0 and 10 pixels.  The border won't show if the width is zero.  If you delete the value, the width will be defined by your theme.
          </p></div>

          <div class="option"><p class="description">
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                  $('.blipper-widget-colour-picker').wpColorPicker();
                });
            </script>
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
              data-default-color="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'border-color', true ); ?>"
            >
            <?php //blipper_widget_log( 'border color', esc_attr( $settings['border-color'] ) ); ?>
          </p>
          <p class="description">
            Pick a colour for the widget border colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme.  If you pick a colour, including the default colour, that colour will be used instead.
          </p></div>

          <div class="option"><p class="description">
            <script type='text/javascript'>
              jQuery(document).ready(function($) {
                $('.blipper-widget-colour-picker').wpColorPicker();
              });
            </script>
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
              data-default-color="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'background-color', true ); ?>"
            >
            <?php //blipper_widget_log( 'background color', esc_attr( $settings['background-color'] ) ); ?>
          </p>
          <p class="description">
            Pick a colour for the widget background colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme.  If you pick a colour, including the default colour, that colour will be used instead.
          </p></div>

          <div class="option"><p class="description">
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                  $('.blipper-widget-colour-picker').wpColorPicker();
                });
            </script>
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
              data-default-color="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'color', true ); ?>"
            >
            <?php //blipper_widget_log( 'color', esc_attr( $settings['color'] ) ); ?>
          </p>
          <p class="description">
            Pick a colour for the widget text colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme; the link text will be the same colour as the surrounding text.  If you pick a colour, including the default colour, that colour will be used instead.
          </p></div>

          <div class="option"><p class="description">
            <script type='text/javascript'>
                jQuery(document).ready(function($) {
                  $('.blipper-widget-colour-picker').wpColorPicker();
                });
            </script>
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
              data-default-color="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'link-color', true ); ?>"
            >
            <?php //blipper_widget_log( 'link color', esc_attr( $settings['link-color'] ) ); ?>
          </p>
          <p class="description">
            Pick a colour for the widget link colour.  If you pick a colour, including the default colour, that colour will be used instead.
          </p></div>

          <div class="option"><p class="description">
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
              placeholder="<?php echo $this->blipper_widget_get_default_setting_value( 'widget', 'border-width' ); ?>"
              value="<?php echo $settings['padding'] ? esc_attr( $settings['padding'] ) : $this->blipper_widget_get_default_setting_value( 'widget', 'padding' ); ?>"
            >
          </p>
          <p class="description">
            Pick a number of pixels between zero and twenty.  Changing the padding will increase the distance between the border and the edge of the image.  Bear in mind that the more padding you have, the smaller your image will appear.
          </p></div>
        </div>
        <?php
      }

    }

    private function blipper_widget_get_default_setting_value( $setting_type, $setting, $is_color = false ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      if ( array_key_exists( $setting_type, $this->default_setting_values ) ) {

        if ( array_key_exists( $setting, $this->default_setting_values[$setting_type] ) ) {

          if ( $is_color ) {
            if ( 'inherit' === $this->default_setting_values[$setting_type][$setting] ) {
              // The default behaviour is to inherit the theme's colour, but the colour picker doesn't like 'inherit' as a default colour choice.  Therefore, if the theme's colour is desired, then CSS will have to be used, unless the user knows the default colour and can set it in the colour picker.
              switch ( $setting ) {
                // Colours from the Blipfoto website:
                case 'border-color':
                case 'background-color':
                  $default_setting = '#131313';
                  break;
                default:
                  $default_setting = '#dddddd';
              }
            } else {
              $default_setting = '#' . esc_attr( $this->default_setting_values[$setting_type][$setting] );
            }
          } else {
            $default_setting = esc_attr( $this->default_setting_values[$setting_type][$setting] );
          }

          // blipper_widget_log( 'default ' . $setting_type . ' ' . $setting, $default_setting );

          return $default_setting;

        } else {
          throw new Exception( 'Invalid setting ' . $setting );
        }

      } else {
        throw new Exception( 'Invalid setting type ' . $setting_type );
      }

    }

    private function blipper_widget_get_style( $settings, $style_element ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $this->blipper_widget_log_display_values( $settings, $style_element, 'blipper_widget_get_style' );

      $element = $style_element;

      switch( $style_element ) {
        case 'link-color':
          $element = 'color';
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . ';'
              )
            : $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . ';';
          // blipper_widget_log( 'style', $style );
          return $style;
        case 'padding':
        case 'border-width':
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . 'px' . ';'
              )
            : $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . 'px' . ';';
          // blipper_widget_log( 'style', $style );
        return $style;
        default:
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . ';'
              )
            : $element . ':' . $this->blipper_widget_get_default_setting_value( 'widget', $style_element ) . ';';
          // blipper_widget_log( 'style', $style );
        return $style;
      }

    }

    private function blipper_widget_log_display_values( $settings, $display_element, $function_name ) {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      $message =
        array_key_exists( $display_element, $settings )
        ? ( empty( $settings[$display_element] )
          ? ( "key has no value; using default (widget): " . $this->blipper_widget_get_default_setting_value( 'widget', $display_element ) )
          : ( $settings[$display_element] )
          )
        : ( "No key, no value; adding default (common): " . $this->blipper_widget_get_default_setting_value( 'common', 'display-journal-title' ) );
      // blipper_widget_log( $display_element, $message );
    }

    /**
     * Display an error message after an exception was thrown.
     *
     * @param    $e                  The exception object containing information
     *                                 about the error
     * @param    $additional_info    Extra information to help the user.
     * @since    1.1.1
    */
    private function blipper_widget_display_error_msg( $e, $additional_info = '', $request_limit_reached = false ) {

      if ( BW_DEBUG ) {
        error_log( $this->blipper_widget_get_exception_class( $e ) . '.' );
      }
      if ( current_user_can( 'manage_options' ) ) {
        $this->blipper_widget_display_private_error_msg( $e, $additional_info, $request_limit_reached );
      } else {
        $this->blipper_widget_display_public_error_msg( $request_limit_reached );
      }

    }

    /**
     * Display an error message for a user that can manage options.
     *
     * @param    $e                  The exception object containing information
     *                                 about the error
     * @param    $additional_info    Extra information to help the user.
     * @since    1.1.1
      */
    private function blipper_widget_display_private_error_msg( $e, $additional_info = '', bool $request_limit_reached = false ) {

      blipper_widget_log( 'Blipper Widget ERROR ' . $e->getCode() . ': ' . __( $e->getMessage(), 'blipper-widget' ), false );
        error_log( 'In ' . $e->getFile() . ' on line ' . $e->getLine() );

      echo '<p><span class=\'' . $this->blipper_widget_get_css_error_classes( $e ) . '\'>' . __( $this->blipper_widget_get_exception_class( $e ), 'blipper-widget' ) . '</span>: ERROR ' . $e->getCode() . ' ' . __( $e->getMessage(), 'blipper-widget' ) . ' ' . __( $additional_info, 'blipper-widget' ) . ( $request_limit_reached ? __( '. Please try again in 15 minutes', 'blipper-widget' ) : '' ) . '.</p>';

    }

    /**
     * Display an error message for a user that cannot manage options.
     *
     * @since    1.1.1
     */
    private function blipper_widget_display_public_error_msg( $request_limit_reached = false ) {

      if ( $request_limit_reached ) {
        echo '<p>' .  __( 'The Blipfoto request limit has been reached. Please try again in 15 minutes.', 'blipper-widget' ) . '</p>';
      } else {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p>' . __( 'There is a problem with Blipper Widget or a service it relies on. Please check your settings and try again. If your settings are ok, try again later. If it still doesn\'t work, please consider informing the owner of this website or <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem', 'blipper-widget' ) . '.</p>';
        } else {
          echo '<p>' . __( 'There is a problem with Blipper Widget or a service it relies on. Please check your settings and try again. If your settings are ok, try again later. If it still doesn\'t work, please consider <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem', 'blipper-widget' ) . '.</p>';
        }
      }

    }

    /**
     * Display a message based on the exception class.
     */
    private function blipper_widget_get_exception_class( $e ) {

      switch ( get_class( $e ) ) {
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_BaseException':
          return 'Blipfoto error';
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException':
          return 'Blipfoto API response error';
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_FileException':
          return 'File error';
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException':
          return 'Invalid response';
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_NetworkException':
          return 'Network error';
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException':
          return 'OAuth error';
        case 'ErrorException':
        case 'Error':
          return 'Error';
        case 'Exception':
        default:
          return 'Warning';
        }

    }

    /**
     * Use the exception class to get appropriate CSS classes.
     */
    private function blipper_widget_get_css_error_classes( $e ) {

      switch ( get_class( $e ) ) {
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_BaseException':
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException':
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_FileException':
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_InvalidResponseException':
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_NetworkException':
        case 'blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException':
        case 'ErrorException':
          return 'error';
        case 'Exception':
          return 'warning';
        default:
          return 'notice';
        }

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
      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );
    }

    public function blipper_widget_enqueue_scripts( $hook_suffix ) {
      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

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

    /**
     * Caches the given data.
     *
     *
     *
     * @author dartiss, pandammonium
     * @since 2.0.0
     *
     * @param int $i The current line number of the readme file.
     * @return void
     */
    private function set_cache( mixed $data_to_cache ): void {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      // blipper_widget_log( 'cache key', $this->cache_key );
      // blipper_widget_log( 'cache expiry', $this->cache_expiry );

      $result = false;
      $cached_info = array();

      try {
        if ( is_numeric( $this->cache_expiry ) ) {

          $transient = get_transient( $this->cache_key );
          if ( false === $transient ) {
            $result = set_transient( $this->cache_key, $data_to_cache, 60 * $this->cache_expiry );
          } else {
            // Don't fail if the cache already exists:
            $result = true;
          }
        } else {
          if ( 'no' !== strtolower( $this->cache_expiry ) ) {
            throw new Exception( 'Cache expiry time is invalid. Expected a number; got ' . gettype( $this->cache_expiry ) . ' ' . $this->cache_expiry, E_USER_WARNING );
          }
        }
        if ( false === $result ) {
          $deleted = delete_transient( $this->cache_key );
          $deleted_msg = 'Failed to set cache. ' . ( $deleted ? 'Cache has been deleted' : ( get_transient( $this->cache_key ) ? 'Cache was not deleted, so it is still lurking' : ' Cache doesn\'t exist' ) );
          throw new Exception( 'Failed to set cache ' . $this->cache_key . '. ' . $deleted_msg, E_USER_WARNING );
        }
      } catch( Exception $e ) {
        throw( $e );
      }
    }

    private function get_cache(): bool|array|string {

      // blipper_widget_log( 'method', __CLASS__ . '::' . __FUNCTION__ );
      // blipper_widget_log( 'arguments', func_get_args() );

      // blipper_widget_log( 'cache key', $this->cache_key );
      // blipper_widget_log( 'cache expiry', $this->cache_expiry );

      if ( is_numeric( $this->cache_expiry ) ) {
        $transient = get_transient( $this->cache_key );
        // blipper_widget_log( 'transient', $transient );
        return $transient;
      } else {
        throw new Exception( 'Cache expiry time is invalid. Expected a number; got ' . gettype( $this->cache_expiry ) . ' ' . $this->cache_expiry, E_USER_WARNING );
      }
      // blipper_widget_log( 'cache exists', false === $transient ? 'not found' : 'found' );

    }

  }
}
