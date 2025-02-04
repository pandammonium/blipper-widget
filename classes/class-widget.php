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

use Blipper_Widget_Blipfoto\Blipper_Widget_Api\Blipper_Widget_Client;
use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_BaseException;
use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_ApiResponseException;
use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_OAuthException;
use Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_InvalidResponseException;
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
    private const DEFAULT_SETTING_VALUES = array (
        'widget'     => array (
          'border-style'           => 'inherit',
          'border-width'           => 'inherit',
          'border-color'           => 'inherit',
          'background-color'       => 'inherit',
          'color'                  => 'inherit',
          'link-color'             => 'inherit',
          'padding'                => 'inherit',
          'style-control'          => 'widget-settings-only',  // 'css'
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
          'updated'                => false,                   // true
        ),
      );

    /**
     * @var string Defines the length of time the cache should be retained for in
     * minutes. Must have a numeric value (i.e. `true ===
     * is_numeric(self::CACHE_EXPIRY)`).
     * Default: `'60'`.
     * @access private
     *
     * @author pandammonium
     * @since 1.2.3
     */
    private const CACHE_EXPIRY = DAY_IN_SECONDS;
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
      * @deprecated 1.2.6
      * @property array     $style_control_classes   The classes used for styling
      *                                              the widget.
      */
      private $style_control_classes;

    /**
      * @since    0.0.1
      * @property Blipper_Widget_Client     $client   The Blipfoto client
      */
      private static $client = null;

    /**
      * @since    0.0.1
      * @property Blipper_Widget_Settings   $settings The Blipper Widget settings
      * @deprecated 1.2.6
      */
      private static Blipper_Widget_Settings $settings;

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
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $params = array(
        'description' => __( 'The latest blip from your Blipfoto account.', 'blipper-widget' ),
        'name'        => __( 'Blipper Widget', 'blipper-widget' ),
      );
      parent::__construct( 'blipper_widget', 'Blipper Widget', $params );

      // Not using is_active_widget here because that function is only supposed to
      // return true if the widget is on a sidebar.  The widget isn't necessarily
      // on a sidebar when the OAuth access settings are set.
      self::load_dependencies();

      self::$settings = new blipper_widget_settings();
      // self::$client = null;
      $this->cache_key = '';

      // function to load Blipper Widget:
      // add_action( 'admin_notices', array( Blipper_Widget::class, 'bw_settings_check' ) );
      // add_action( 'load-widgets.php', array( Blipper_Widget::class, 'bw_load_colour_picker') );

      add_action( 'admin_enqueue_scripts', array( Blipper_Widget::class, 'bw_enqueue_scripts' ) );

      add_action( 'admin_footer-widgets.php', array( Blipper_Widget::class, 'bw_print_scripts' ), 9999 );

      add_shortcode('blipper_widget', array( &$this, 'bw_shortcode_blip_display') );

      // bw_log( 'this', $this );
      // bw_log( 'default setting values', self::DEFAULT_SETTING_VALUES );
      // bw_log( 'cache prefix', self::CACHE_PREFIX );
      // bw_log( 'quotes', self::QUOTES );

    }

    /**
      * Render the widget on the WP site in a widget-enabled area.  This is the
      * front-end of the widget.
      *
      * @since    0.0.1
      * @api
      */
    public function widget( $args, $settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $this->bw_display_form( $this->bw_get_display_values( $settings ) );
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
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $settings = array();
      $title                  = $this->bw_validate( $new_settings, $old_settings, 'title' );
      $display_date           = $this->bw_validate( $new_settings, $old_settings, 'display-date' );
      $display_journal_title  = $this->bw_validate( $new_settings, $old_settings, 'display-journal-title' );
      $add_link_to_blip       = $this->bw_validate( $new_settings, $old_settings, 'add-link-to-blip' );
      $powered_by             = $this->bw_validate( $new_settings, $old_settings, 'display-powered-by' );
      $border_style           = $this->bw_validate( $new_settings, $old_settings, 'border-style' );
      $border_width           = $this->bw_validate( $new_settings, $old_settings, 'border-width' );
      $border_colour          = $this->bw_validate( $new_settings, $old_settings, 'border-color' );
      $background_colour      = $this->bw_validate( $new_settings, $old_settings, 'background-color' );
      $colour                 = $this->bw_validate( $new_settings, $old_settings, 'color' );
      $link_colour            = $this->bw_validate( $new_settings, $old_settings, 'link-color' );
      $padding                = $this->bw_validate( $new_settings, $old_settings, 'padding' );
      $style_control          = $this->bw_validate( $new_settings, $old_settings, 'style-control');

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

      // bw_log( 'old settings', $old_settings );
      // bw_log( 'new settings', $new_settings );

      $updated_settings_only = array_diff_assoc( $old_settings, $new_settings );
      // bw_log( 'Updated settings', $updated_settings_only );
      $settings['updated'] = empty( $updated_settings_only ) ? false : true;
      // bw_log( 'settings updated', $settings['updated'] );

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
    public function bw_shortcode_blip_display( $atts, $content = null, $shortcode = '', $print = false ) {
        // bw_log( 'method', __METHOD__ . '()' );
        // bw_log( 'arguments', func_get_args() );

      try {
        $atts = self::normalise_attributes( $atts, $shortcode );
        // error_log( 'normalised atts: ' . var_export( $atts, true ) );

        $defaults = array_merge( self::DEFAULT_SETTING_VALUES['shortcode'], self::DEFAULT_SETTING_VALUES['common'] );

        // bw_log( 'default settings', $defaults );
        // bw_log( 'user settings', $atts );

        $args = shortcode_atts( $defaults, $atts, $shortcode );
        extract( $args );

        // Don't have any saved settings to compare the current ones with (unless we get the last-saved version of this location, if that's even possible or worthwhile), so have to set the updated flag to true regardless, otherwise the blip might not be rendered correctly:
        $args['updated'] = true;

        $the_title = '';
        if ( ! empty( $args['title'] ) ) {
          if ( ! ( $args['title-level'] === 'h1' ||
                   $args['title-level'] === 'h2' ||
                   $args['title-level'] === 'h3' ||
                   $args['title-level'] === 'h4' ||
                   $args['title-level'] === 'h5' ||
                   $args['title-level'] === 'h6' ||
                   $args['title-level'] === 'p' ) ) {
            $args['title-level'] = self::bw_get_default_setting_value( 'shortcode', 'title-level' );
          }
          $the_title = '<' . $args['title-level'] . '>' . apply_filters( 'widget_title', $args['title'] ) . '</' . $args['title-level'] . '>';
        }

        // bw_log( 'shortcode atts', $args );

        return $this->render_the_blip( $defaults, $args, $the_title, false, $content );
      } catch( Exception $e ) {
        return bw_exception( $e );
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
     * widget or the shortcode (set by the user).
     * @param string The formatted title to be used for this blip.
     * @return string|bool The HTML that will render the blip or false on failure.
     */
    private function render_the_blip( array $args, array $settings, string $the_title, bool $is_widget, string $content = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $this->cache_key = self::CACHE_PREFIX . md5( self::CACHE_EXPIRY . implode( ' ', $args ) . $the_title );

      try {
        $the_cache = $this->get_cache();
        $updated = $settings['updated'];

        // bw_log( 'This blip has been cached', ( empty( $the_cache ) ? 'no' : 'yes' ) );
        // bw_log( 'This blip\'s settings have changed', ( $updated ? 'yes' : 'no' ) );

        if ( empty( $the_cache ) || $updated ) {
          // error_log( 'rendering the blip from scratch' );

          // The blip does not exist in the cache or its settings have changed, so it needs to be generated:
          return $this->generate_blip( $args, $settings, $the_title, $is_widget, $content );

        } else {
          // error_log( 'rendering the blip from the cache' );

          // The blip has been cached recently and its settings have not changed, so return the cached blip:
          return $the_cache;
        }
      } catch ( Exception $e ) {
        return bw_exception( $e );
      }
    }

    /**
     * Generate the blip from scratch
     */
     private function generate_blip( array $args, array $settings, string $the_title, $is_widget, $content ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( self::bw_create_blipfoto_client() ) {
        $the_blip = '<!-- Start of Blipper Widget ' . BW_VERSION . ' -->' . $the_title . $this->bw_get_blip( $args, $settings, $is_widget, $content ) . '<!-- End of Blipper Widget ' . BW_VERSION . ' -->';

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
    private static function normalise_attributes( string|array|null $atts, $shortcode = '' ): string|array|null {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // bw_log( 'type of attributes', gettype( $atts ) );

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
      // bw_log( 'normalised attributes', $atts );
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
    private static function bw_validate( $new_settings, $old_settings, $setting_field ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $setting = null;

      // bw_log( 'setting field', $setting_field );
      // bw_log( 'old setting', $old_settings[$setting_field] );
      // bw_log( 'proposed setting', $old_settings[$setting_field] );

      if ( array_key_exists( $setting_field, $old_settings ) && array_key_exists( $setting_field, $new_settings ) ) {
        if ( $new_settings[$setting_field] === $old_settings[$setting_field] ) {
          $setting =  $old_settings[$setting_field];
          // bw_log( 'setting unchanged', $setting );
          return $setting;
        }
      }

      if ( array_key_exists( $setting_field, $new_settings ) ) {
        $new_settings[$setting_field] = esc_attr( $new_settings[$setting_field] );
      }

      switch ( $setting_field ) {
        case 'title':
          if ( array_key_exists( $setting_field, $new_settings ) ) {
            if ( true === ctype_print( $new_settings[$setting_field] ) ) {
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
              // bw_log( 'setting field', 'is empty' );
            }
          } else {
            // bw_log( 'setting field', 'does not exist' );
          }
      }
      // bw_log( 'new setting', $setting );

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
    private static function bw_get_display_values( $settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $new_settings = array();

      try {
        $new_settings['title'] = $this->bw_get_display_value( 'title', $settings );
        $new_settings['display-date'] = $this->bw_get_display_value( 'display-date', $settings );
        $new_settings['display-journal-title'] = $this->bw_get_display_value( 'display-journal-title', $settings );
        $new_settings['add-link-to-blip'] = $this->bw_get_display_value( 'add-link-to-blip', $settings );
        $new_settings['display-powered-by'] = $this->bw_get_display_value( 'display-powered-by', $settings );
        $new_settings['border-style'] = $this->bw_get_display_value( 'border-style', $settings );
        $new_settings['border-width'] = $this->bw_get_display_value( 'border-width', $settings );
        $new_settings['border-color'] = $this->bw_get_display_value( 'border-color', $settings );
        $new_settings['background-color'] = $this->bw_get_display_value( 'background-color', $settings );
        $new_settings['color'] = $this->bw_get_display_value( 'color', $settings );
        $new_settings['link-color'] = $this->bw_get_display_value( 'link-color', $settings );
        $new_settings['padding'] = $this->bw_get_display_value( 'padding', $settings );
        $new_settings['style-control'] = $this->bw_get_display_value( 'style-control', $settings );
        $new_settings['updated'] = $this->bw_get_display_value( 'updated', $settings );

      } catch ( ErrorException $e ) {

        self::bw_display_error_msg( $e, __( 'Please check your settings are valid and try again', 'blipper-widget' ) );

      } catch ( Exception $e ) {

        self::bw_display_error_msg( $e, __( 'Something has gone wrong getting the user settings', 'blipper-widget' ) );

      }

      // bw_log( 'new settings', $new_settings );

      return $new_settings;
    }

    /**
     * Gets the display value.
     */
    private static function bw_get_display_value( $setting, $settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {

        if ( array_key_exists( $setting, $settings ) ) {
          return esc_attr( $settings[$setting] );
        } else {
          if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES['widget'] ) ) {
            return self::DEFAULT_SETTING_VALUES['widget'][$setting];
          } else if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES['common'] ) ) {
            return self::DEFAULT_SETTING_VALUES['common'][$setting];
          } else {
            if ( BW_DEBUG ) {
              error_log( __( 'Invalid setting requested', 'blipper-widget' ) . ': ' . $setting );
            }
            throw new ErrorException( __( 'Invalid setting requested', 'blipper-widget' ) . ':  <strong>' . $setting . '</strong>.' );
            return '';
          }
        }

      } catch ( ErrorException $e ) {

        self::bw_display_error_msg( $e );

      } catch ( Exception $e ) {

        self::bw_display_error_msg( $e, 'Something has gone wrong getting the user settings' );

      }
    }

    /**
      * Load the files this widget needs.
      *
      * @since    0.0.1
      */
    private static function load_dependencies() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      require_once( plugin_dir_path( __FILE__ ) . 'class-settings.php' );

      self::load_blipfoto_dependencies();
    }

    /**
      * Load the Blipfoto API.
      *
      * @since    0.0.1
      */
    private static function load_blipfoto_dependencies() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
    private static function bw_create_blipfoto_client( $args = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $client_ok = false;
      $create_new_client = false;
      if ( empty( self::$client ) ) {
        $create_new_client = true;
      } else if ( !empty( self::$client->accessToken() ) ) {
        $client_ok = true;
      }
      // error_log( 'client ok: ' . var_export( $client_ok, true ) );

      // Get the settings from the database
      $oauth_settings = Blipper_Widget_Settings::bw_get_settings();

      if ( !$client_ok ) {
        try {

          if ( empty( $oauth_settings['username'] ) && empty( $oauth_settings['access-token'] ) ) {
            throw new Blipper_Widget_OAuthException( 'Missing username and access token.');

          } else if ( empty( $oauth_settings['username'] ) ) {
            throw new Blipper_Widget_OAuthException( 'Missing username.' );

          } else if ( empty( $oauth_settings['access-token'] ) ) {
            throw new Blipper_Widget_OAuthException( 'Missing access token.' );

          } else {
            $client_ok = true;
          }

        } catch ( Blipper_Widget_OAuthException $e ) {

          bw_log( 'Blipper_Widget_OAuthException thrown in ' . $e->getFile() . ' on line ' . $e->getLine(), $e->getMessage() );

          self::bw_display_error_msg( $e, 'You are attempting to display your latest blip with Blipper Widget, but your OAuth credentials (your Blipfoto username and/or access token) are invalid.  Please check these credentials on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '" rel="nofollow nopopener noreferral">the Blipper Widget settings page</a> to continue' );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting the user' );

        }
      }
      if ( $client_ok ) {
        try {
          $client_ok = false;

          if ( $create_new_client ) {
            // Create a new client using the OAuth settings from the database
            self::$client = new Blipper_Widget_Client (
              '',
              '',
              $oauth_settings['access-token']
            );
            // error_log( 'created new client' );
          }
          // error_log( 'client: ' . var_export( self::$client, true ) );
          if ( empty( self::$client ) || ! isset( self::$client ) ) {
            throw new Blipper_Widget_ApiResponseException( 'Failed to create the Blipfoto client.' );
          } else {
            $client_ok = true;
            // bw_log( 'client', self::$client );

          }

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e, 'Please try again later' );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong creating the client' );

        }
      }

      if ( $client_ok ) {
        $client_ok = false;
        try {

          $user_profile = self::$client->get( 'user/profile' );

          if ( $user_profile->error() ) {

            throw new Blipper_Widget_ApiResponseException( $user_profile->error() );
          }
          $user = $user_profile->data()['user'];
          if ( $user['username'] !== $oauth_settings['username'] ) {
            throw new Blipper_Widget_OAuthException( 'Unable to verify user.  Please check the username you entered on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> is correct.' );
          } else {
            $client_ok = true;
          }
        } catch ( Blipper_Widget_OAuthException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e, '', true );

        } catch ( Blipper_Widget_BaseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( ErrorException $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );
        }
      } else {
        if ( BW_DEBUG ) {
          trigger_error( 'The Blipper Widget client is ' . var_export( self::$client, true ), E_USER_WARNING );
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
    private function bw_get_blip( $args, $settings, $is_widget, $content = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $user_profile = null;
      $user_settings = null;
      $descriptive_text = null;
      $continue = false;
      $the_blip = '';

      try {

        $user_profile = self::$client->get( 'user/profile' );

        if ( $user_profile->error() ) {
          throw new Blipper_Widget_ApiResponseException( $user_profile->error() . '  Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          $continue = true;
        }

      } catch ( Blipper_Widget_ApiResponseException $e ) {

        self::bw_display_error_msg( $e );

      } catch ( Exception $e ) {

        self::bw_display_error_msg( $e, 'Something has gone wrong getting your user profile' );

      }

      if ( $continue ) {
        $continue = false;

        try {

          $user_settings = self::$client->get( 'user/settings' );

          if ( $user_settings->error() ) {
            throw new Blipper_Widget_ApiResponseException( $user_settings->error() . '  Can\'t access your Blipfoto account details.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
          } else {
            $continue = true;
          }

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting your user settings' );

        }

      }

      if ( $continue ) {
        $continue = false;

        try {

          $user = $user_profile->data('user');

          if ( empty( $user ) ) {
            throw new Blipper_Widget_ApiResponseException( 'Can\'t access your Blipfoto account data.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.');
          } else {
            $continue = true;
          }

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto account' );

        }

      }

      if ( $continue ) {
        $continue = false;

        try {

          // A page index of zero gives the most recent page of blips.
          // A page size of one means there will be only one blip on that page.
          // Together, these ensure that the most recent blip is obtained — which
          // is exactly what we want to display.
          $journal = self::$client->get(
            'entries/journal',
            array(
              'page_index'  => 0,
              'page_size'   => 1
            )
          );

          if ( $journal->error() ) {
            throw new Blipper_Widget_ApiResponseException( $journal->error() . '  Can\'t access your Blipfoto journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
          } else {
            $continue = true;
          }

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto journal' );

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

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong accessing your entries (blips)' );

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
              throw new Blipper_Widget_BaseException( 'Blipper Widget was looking for one entry (blip) only, but found ' . count( $blips ) . '. Something has gone wrong.  Please try again' );
          }

        } catch ( Blipper_Widget_BaseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e );

        }

      }

      if ( $continue ) {
        $continue = false;

        $blip = $blips[0];
        try {

          $details = self::$client->get(
            'entry',
            array(
              'entry_id'          => $blip['entry_id_str'],
              'return_details'    => 1,
              'return_image_urls' => 1
            )
          );

          if ( $details->error() ) {
            throw new Blipper_Widget_ApiResponseException( $details->error() . '  Can\'t get the entry (blip) details.' );
          } else {
           $continue = true;
          }

        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting the entry (blip) details' );

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
            throw new Blipper_Widget_ApiResponseException('Did not get the descriptive text.');
          }
        } catch ( Blipper_Widget_ApiResponseException $e ) {

          self::bw_display_error_msg( $e );

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting the entry\'s (blip\'s) descriptive text' );

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

          self::bw_display_error_msg( $e );
        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong getting the image URL' );

        }

        $continue = ! empty ( $image_url );
      }

      if ( $continue ) {

        // Display the blip.
        try {

          // Given that all the data used to determine $style_control is passed to blipper_widget_get_styling, it might seem pointless to calculate here once and pass to that function; but this way, it's only calculated once.  I don't really know how much this affects performance.

          // Set $style_control to true if the widget settings form (default for widgets) should be used, otherwise set to false.

          // Need to check whether the style control has been set or not because of, I think, the Customiser.  If it hasn't, then set $style_control to true, indicating that CSS should be used:
          // bw_log( 'is widget', $is_widget );
          // bw_log( 'style control is set', isset( $settings['style-control'] ) );
          $style_control = $is_widget ? ( isset( $settings['style-control'] ) ? ( $settings['style-control'] === self::DEFAULT_SETTING_VALUES['widget']['style-control'] ) : true ) : false;
          // bw_log( 'style control', $style_control );

          $the_blip = "<div" . $this->bw_get_styling( 'div|blip', $is_widget, $style_control, $settings ) . ">";

          $the_blip .= "<figure" . $this->bw_get_styling( 'figure', $is_widget, $style_control, $settings ) . ">";

          // Link back to the blip on the Blipfoto site.
          $this->bw_log_display_values( $settings, 'add-link-to-blip', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'add-link-to-blip' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser
            $settings['add-link-to-blip'] = self::DEFAULT_SETTING_VALUES['common']['add-link-to-blip'];
          }
          if ( $settings['add-link-to-blip'] === 'show' ) {
            $the_url = self::bw_sanitise_url( 'https://www.blipfoto.com/entry/' . $blip['entry_id_str'] );
            $the_blip .= '<a href="' . $the_url . '" rel="nofollow">';
          }
          // Add the image.
          $the_blip .= '<img src="'
            . self::bw_sanitise_url( $image_url )
            . '"'
            . $this->bw_get_styling( 'img', $is_widget, $style_control, $settings )
            . ' alt="'
            . $blip['title']
            . '">';
          // Close the link (anchor) tag.
          if ( $settings['add-link-to-blip'] === 'show' ) {
            $the_blip .= '</a>';
          }

          // Display any associated data.
          $the_blip .= "<figcaption" . $this->bw_get_styling( 'figcaption', $is_widget, $style_control, $settings ) . ">";

          // Date (optional), title and username
          $this->bw_log_display_values( $settings, 'display-date', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'display-date' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser
            $settings['display-date'] = self::DEFAULT_SETTING_VALUES['common']['display-date'];
          }
          if ( $settings['display-date'] === 'show' || ! empty( $blip['title'] ) ) {
            $the_blip .= "<header" . $this->bw_get_styling( 'header', $is_widget, $style_control, $settings ) . ">";
          }
          if ( $settings['display-date'] === 'show' ) {
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
            $the_blip .= '<div' . $this->bw_get_styling( 'div|content', $is_widget, $style_control, $settings ) . '>'
              . $content
              . '</div>';
          }

          // Journal title and/or display-powered-by link.
          $this->bw_log_display_values( $settings, 'display-journal-title', 'blipper_widget_get_blip' );
          $this->bw_log_display_values( $settings, 'display-powered-by', 'blipper_widget_get_blip' );
          if ( ! array_key_exists( 'display-journal-title' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser.
            $settings['display-journal-title'] = self::DEFAULT_SETTING_VALUES['common']['display-journal-title'];
          }
          if ( ! array_key_exists( 'display-powered-by' , $settings ) ) {
            // Necessary for when Blipper Widget is added via the Customiser.
            $settings['display-powered-by'] = self::DEFAULT_SETTING_VALUES['common']['display-powered-by'];
          }

        if ( $settings['display-journal-title'] === 'show' || $settings['display-powered-by'] === 'show' ) {
            $the_blip .= "<footer" . $this->bw_get_styling( 'footer', $is_widget, $style_control, $settings ) . ">";
            if ( $settings['display-journal-title'] === 'show' ) {
                $the_blip .= __( 'From', 'blipper-widget' )
                . ' <a href="https://www.blipfoto.com/'
                . $user_settings->data( 'username' )
                . '" rel="nofollow"' . $this->bw_get_styling( 'link', $is_widget, $style_control, $settings ) . '>'
                . $user_settings->data( 'journal_title' )
                . '</a>';
            }
            if ( $settings['display-journal-title'] === 'show' && $settings['display-powered-by'] === 'show' ) {
              $the_blip .= ' | ';
            }
            if ( $settings['display-powered-by'] === 'show' ) {
              $the_blip .= 'Powered by <a href="https://www.blipfoto.com/" rel="nofollow"' . $this->bw_get_styling( 'link', $is_widget, $style_control, $settings ) . '>Blipfoto</a>';
            }
            $the_blip .= '</footer>';
          }
          $the_blip .= '</figcaption></figure>';

          $the_blip .= empty( $descriptive_text ) ? "" : "<div" . $this->bw_get_styling( 'div|desc-text', $is_widget, $style_control, $settings ) . ">"
            . self::bw_sanitise_html( $descriptive_text )
            . '</div>';

          $the_blip .= "</div>"; // .bw-blip

        } catch ( Exception $e ) {

          self::bw_display_error_msg( $e, 'Something has gone wrong constructing your entry (blip)' );

        // } finally {
        //   bw_log( 'The completed blip', $the_blip );
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
    private function bw_get_styling( $element, $is_widget, $style_control, $settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // If the blip is not to be displayed in a widget or if the widget is to be styled using CSS only, return a class attribute.
      // If the blip is to be displayed in a widget using the widget settings only, return a style attribute.
      // The default is an empty string either way.
      $use_class = ( ! $is_widget || ! $style_control );
      switch ( $element ) {
        case 'div|blip':
        return $use_class ?
          ( ' class=\'bw-blip\'' ) :
          ( ' style=\'' .  $this->bw_get_style( $settings, 'border-style')
            . $this->bw_get_style( $settings, 'border-width')
            . $this->bw_get_style( $settings, 'border-color')
            . '\'' );
        case 'figure':
        return $use_class ?
          ( ' class=\'bw-figure\'' ) :
          ( ' style=\'' . $this->bw_get_style( $settings, 'background-color' )
            . $this->bw_get_style( $settings, 'padding' ) . '\'' );
        case 'img':
        return $use_class ?
          ( ' class=\'bw-image\'' ) :
          ( ' style=\'margin:auto;\'' );
        case 'figcaption':
        return $use_class ?
          ( ' class=\'bw-caption\'' ) :
          ( ' style=\'padding-top:7px;line-height:2;'
            . $this->bw_get_style( $settings, 'color' )
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
            . $this->bw_get_style( $settings, 'link-color' )
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
    private static function bw_sanitise_html( $html ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
      // bw_log( 'dirty html', $html );
      // bw_log( 'clean html', wp_kses( $html, $allowed_html ) );

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
    private static function bw_sanitise_url( $url ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
    private function bw_display_blip( $settings, $is_widget, $content=null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      return $this->bw_get_blip( $settings, $is_widget, $content );
    }

    /**
      * Display the back-end widget form.
      *
      * @since     0.0.1
      * @access    private
      * @param     array         $settings       The settings saved in the database
      */
    private static function bw_display_form( $settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $oauth_settings = Blipper_Widget_Settings::bw_get_settings();

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
          Leave the widget title field blank if you don't want to display a title.  The default widget title is <i><?php _e( self::DEFAULT_SETTING_VALUES['common']['title'] ); ?></i>.
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
          // bw_log( 'name', $this->get_field_name( 'style-control' ) );
          // bw_log( 'id', $this->get_field_name( 'style-control' ) );
          // bw_log( 'default value', self::DEFAULT_SETTING_VALUES['widget']['style-control'] );
          // bw_log('actual value', $settings['style-control'] );
        ?>

        <script>
          jQuery(document).ready(function($) {
            the_value = $('#<?php echo $this->get_field_id( 'style-control' ); ?> option:selected').val();
            console.log( 'On load: ' + the_value );
            if (the_value === 'widget-settings-only') {
              console.log( '  showing' );
              $('.blipper-widget-conditional').show();
            } else {
              console.log( '  hiding' );
              $('.blipper-widget-conditional').hide();
            }
            $('#<?php echo $this->get_field_id( 'style-control' ); ?>').on('change', function() {
              the_value = $('#<?php echo $this->get_field_id( 'style-control' ); ?> option:selected').val();
              console.log('On change: ' + the_value);
              if (the_value === 'widget-settings-only') {
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
              placeholder="<?php echo self::bw_get_default_setting_value( 'widget', 'border-width' ); ?>"
              value="<?php echo $settings['border-width'] ? esc_attr( $settings['border-width'] ) : self::bw_get_default_setting_value( 'widget', 'border-width' ); ?>"
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
              data-default-color="<?php echo self::bw_get_default_setting_value( 'widget', 'border-color', true ); ?>"
            >
            <?php //bw_log( 'border color', esc_attr( $settings['border-color'] ) ); ?>
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
              data-default-color="<?php echo self::bw_get_default_setting_value( 'widget', 'background-color', true ); ?>"
            >
            <?php //bw_log( 'background color', esc_attr( $settings['background-color'] ) ); ?>
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
              data-default-color="<?php echo self::bw_get_default_setting_value( 'widget', 'color', true ); ?>"
            >
            <?php //bw_log( 'color', esc_attr( $settings['color'] ) ); ?>
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
              data-default-color="<?php echo self::bw_get_default_setting_value( 'widget', 'link-color', true ); ?>"
            >
            <?php //bw_log( 'link color', esc_attr( $settings['link-color'] ) ); ?>
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
              placeholder="<?php echo self::bw_get_default_setting_value( 'widget', 'border-width' ); ?>"
              value="<?php echo $settings['padding'] ? esc_attr( $settings['padding'] ) : self::bw_get_default_setting_value( 'widget', 'padding' ); ?>"
            >
          </p>
          <p class="description">
            Pick a number of pixels between zero and twenty.  Changing the padding will increase the distance between the border and the edge of the image.  Bear in mind that the more padding you have, the smaller your image will appear.
          </p></div>
        </div>
        <?php
      }
    }

    private static function bw_get_default_setting_value( $setting_type, $setting, $is_color = false ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( array_key_exists( $setting_type, self::DEFAULT_SETTING_VALUES ) ) {

        if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES[$setting_type] ) ) {

          if ( $is_color ) {
            if ( 'inherit' === self::DEFAULT_SETTING_VALUES[$setting_type][$setting] ) {
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
              $default_setting = '#' . esc_attr( self::DEFAULT_SETTING_VALUES[$setting_type][$setting] );
            }
          } else {
            $default_setting = esc_attr( self::DEFAULT_SETTING_VALUES[$setting_type][$setting] );
          }

          // bw_log( 'default ' . $setting_type . ' ' . $setting, $default_setting );

          return $default_setting;

        } else {
          throw new Exception( 'Invalid setting ' . $setting );
        }

      } else {
        throw new Exception( 'Invalid setting type ' . $setting_type );
      }
    }

    private function bw_get_style( $settings, $style_element ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $this->bw_log_display_values( $settings, $style_element, 'blipper_widget_get_style' );

      $element = $style_element;

      switch( $style_element ) {
        case 'link-color':
          $element = 'color';
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . ';'
              )
            : $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';';
          // bw_log( 'style', $style );
          return $style;
        case 'padding':
        case 'border-width':
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . 'px' . ';'
              )
            : $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . 'px' . ';';
          // bw_log( 'style', $style );
        return $style;
        default:
          $style = array_key_exists( $style_element, $settings )
            ? ( empty( $settings[$style_element] )
              ? $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';'
              : $element . ':' . $settings[$style_element] . ';'
              )
            : $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';';
          // bw_log( 'style', $style );
        return $style;
      }
    }

    private static function bw_log_display_values( $settings, $display_element, $function_name ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $message =
        array_key_exists( $display_element, $settings )
        ? ( empty( $settings[$display_element] )
          ? ( "key has no value; using default (widget): " . self::bw_get_default_setting_value( 'widget', $display_element ) )
          : ( $settings[$display_element] )
          )
        : ( "No key, no value; adding default (common): " . self::bw_get_default_setting_value( 'common', 'display-journal-title' ) );
      // bw_log( $display_element, $message );
    }

    /**
     * Display an error message after an exception was thrown.
     *
     * @param    $e                  The exception object containing information
     *                                 about the error
     * @param    $additional_info    Extra information to help the user.
     * @since    1.1.1
    */
    private static function bw_display_error_msg( $e, $additional_info = '', $request_limit_reached = false ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      bw_log( self::bw_get_exception_class( $e ), null, false, false );

      if ( current_user_can( 'manage_options' ) ) {
        self::bw_display_private_error_msg( $e, $additional_info, $request_limit_reached );
      } else {
        self::bw_display_public_error_msg( $request_limit_reached );
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
    private static function bw_display_private_error_msg( $e, $additional_info = '', bool $request_limit_reached = false ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      echo '<p>Blipper Widget <span class=\'' . self::bw_get_css_error_classes( $e ) . '\'>' . __( self::bw_get_exception_class( $e ), 'blipper-widget' ) . '</span> (' . $e->getCode() . '): ' . __( $e->getMessage(), 'blipper-widget' ) . ' ' . __( $additional_info, 'blipper-widget' ) . ( $request_limit_reached ? __( 'Please try again in 15 minutes', 'blipper-widget' ) : '' ) . '.</p>';
    }

    /**
     * Display an error message for a user that cannot manage options.
     *
     * @since    1.1.1
     */
    private static function bw_display_public_error_msg( $request_limit_reached = false ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( $request_limit_reached ) {
        // Translators: do not translate Blipfoto: it's the name of a service.
        echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' .  __( 'The Blipfoto request limit has been reached. Please try again in 15 minutes.', 'blipper-widget' ) . '</p>';
      } else {
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' . __( 'There is a problem with Blipper Widget or a service it relies on. Please check your settings and try again. If your settings are ok, try again later. If it still doesn\'t work, please consider informing the owner of this website or <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem', 'blipper-widget' ) . '.</p>';
        } else {
          echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' . __( 'There is a problem with Blipper Widget or a service it relies on. Please check your settings and try again. If your settings are ok, try again later. If it still doesn\'t work, please consider <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem', 'blipper-widget' ) . '.</p>';
        }
      }
    }

    /**
     * Display a message based on the exception class.
     */
    private static function bw_get_exception_class( $e ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      switch ( get_class( $e ) ) {
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_BaseException':
          return 'Blipfoto error';
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_ApiResponseException':
          return 'Blipfoto API response error';
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_FileException':
          return 'File error';
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_InvalidResponseException':
          return 'Invalid response';
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_NetworkException':
          return 'Network error';
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_OAuthException':
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
    private static function bw_get_css_error_classes( $e ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      switch ( get_class( $e ) ) {
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_BaseException':
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_ApiResponseException':
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_FileException':
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_InvalidResponseException':
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_NetworkException':
        case 'Blipper_Widget_Blipfoto\Blipper_Widget_Exception\Blipper_Widget_OAuthException':
        case 'ErrorException':
          return self::bw_get_css_class( 'error' );
        case 'Exception':
          return self::bw_get_css_class( 'warning' );
        default:
          return self::bw_get_css_class( 'notice' );
        }
    }

    private static function bw_get_css_class( string $type ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      switch ( $type ) {
        case 'error':
        case 'warning':
        case 'notice':
          return __( $type . ' ' . 'blipper-widget-' . $type , 'blipper-widget' );
      }
    }

    // --- Action hooks ------------------------------------------------------- //

    /**
     * Check the Blipfoto OAuth settings have been set, otherwise display a message to the user.
     */
    public static function bw_settings_check() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
    public static function bw_load_colour_picker() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
    }

    public static function bw_enqueue_scripts( $hook_suffix ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
    public static function bw_print_scripts() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

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
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // bw_log( 'cache key', $this->cache_key );
      // bw_log( 'cache expiry', self::CACHE_EXPIRY );

      $result = false;
      $cached_info = array();

      try {
        if ( is_numeric( self::CACHE_EXPIRY ) ) {

          $transient = get_transient( $this->cache_key );
          if ( false === $transient ) {
            $result = set_transient( $this->cache_key, $data_to_cache, self::CACHE_EXPIRY );
          } else {
            // Don't fail if the cache already exists:
            $result = true;
          }
        } else {
          if ( 'no' !== strtolower( self::CACHE_EXPIRY ) ) {
            throw new Exception( 'Cache expiry time is invalid. Expected a number; got ' . gettype( self::CACHE_EXPIRY ) . ' ' . self::CACHE_EXPIRY, E_USER_WARNING );
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
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // bw_log( 'cache key', $this->cache_key );
      // bw_log( 'cache expiry', self::CACHE_EXPIRY );

      if ( is_numeric( self::CACHE_EXPIRY ) ) {
        $transient = get_transient( $this->cache_key );
        // bw_log( 'transient', $transient );
        return $transient;
      } else {
        throw new Exception( 'Cache expiry time is invalid. Expected a number; got ' . gettype( self::CACHE_EXPIRY ) . ' ' . self::CACHE_EXPIRY, E_USER_WARNING );
      }
      bw_log( 'cache exists', false === $transient ? 'not found' : 'found' );
    }

  }
}
