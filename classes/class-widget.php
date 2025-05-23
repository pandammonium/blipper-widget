<?php

/**
  * Blipper Widget shortcode and widget.
  * @author   pandammonium
  * @since    0.0.2
  * @license  GPLv2 or later
  * @package  Pandammonium-BlipperWidget-Widget
  *
  */

namespace Blipper_Widget\Widget;

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use Blipfoto\Api\Client;
use Blipfoto\Exceptions\BaseException;
use Blipfoto\Exceptions\ApiResponseException;
use Blipfoto\Exceptions\OAuthException;
use Blipfoto\Exceptions\InvalidResponseException;

use \WP_Widget;
use Blipper_Widget\Settings\Blipper_Widget_Settings;

use function Blipper_Widget\bw_delete_all_cached_blips;
use function Blipper_Widget\bw_log;
use function Blipper_Widget\bw_exception;

if (!class_exists('Blipper_Widget\Widget\Blipper_Widget')) {
  // error_log( 'class Blipper_Widget\Widget\Blipper_Widget does not exist (yet)' );
  /**
   * The Blipper Widget class.
   *
   * @author pandammonium
   * @since 0.0.2
   */
  class Blipper_Widget extends \WP_Widget {

    /**
      * The default widget settings.
      *
      * @access private
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
        ),
      );

    /**
     * @var ?int CACHE_EXPIRY Define how long the cache (implemented as a
     * WP transient) should persist for. If null, then it never expires. If a
     * number, then the cache should expire up to that many seconds after
     * creation. NB There is no guarantee that a transient will not expire
     * before the expiration time is up. WP default: 60. The WP constant for
     * the number of seconds in a day is used here because the idea behind
     * Blipfoto is that one blip a day is published. If the cache expired in
     * a longer time, new blips might not be shown.
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
    private static string $cache_key = '';

    /**
     * @var number WP_TRANSIENT_KEY_LIMIT Reflects the maximum character
     * limit permitted for WordPress transient names. It is currently 172.
     * @access private
     *
     * @since 1.2.6
     * @author pandammonium
     *
     * @see https://developer.wordpress.org/reference/functions/set_transient/
     *  WordPress developer documentation for set_transient().
     * @see https://developer.wordpress.org/reference/functions/get_transient/
     *   WordPress developer documentation for get_transient().
     */
    private const WP_TRANSIENT_KEY_LIMIT = 172;

    /**
      * @since    1.1.1
      * @deprecated 1.2.6 Unused.
      * @property array     $style_control_classes   The classes used for styling
      *                                              the widget.
      */
    private $style_control_classes;

    /**
      * @since    0.0.1
      * @property Client     $client   The Blipfoto client
      */
    private static ?Client $client = null;

    /**
      * @var Blipper_Widget_Settings $settings The Blipper Widget settings.
      * @since    0.0.1
      * @deprecated 1.2.6 Unnecessary because the settings class
      * (Blipper_Widget_Settings) is now static.
      */
    private static ?Blipper_Widget_Settings $settings = null;

    /**
     * @var array BW_ALLOWED_HTML Stores the HTML elements from third-party
     * sources that are deemed safe enough to display.
     * @since 1.2.6
     * @author pandammonium
     */
    private const BW_ALLOWED_HTML = [
      'p' => [],
      'h1' => [],
      'h2' => [],
      'h3' => [],
      'h4' => [],
      'h5' => [],
      'h6' => [],
      'i' => [],
      'b' => [],
      'em' => [],
      'strong' => [],
      // 'div' => [],
      'br' => [],
      // 'a' => [
      //   'href' => [],
      //   'title' => [],
      // ],
    ];

    private const QUOTES = [
      '“' => '',
      '”' => '',
      '‘' => '',
      '’' => '',
      '&#8217;' => '',
      '&#8217;' => '',
      '&#8220;' => '',
      '&#8221;' => ''
    ];

    private static $dependencies_loaded = false;
    private static $blipfoto_dependencies_loaded = false;
    private static $hooks_and_filters_added = false;

    /**
      * Constructs an instance of the widget.
      *
      * @since    0.0.1
      */
    public function __construct() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'current filter', current_filter() );
      // $backtrace = debug_backtrace( options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: 8 );
      // $i = 0;
      // foreach ( $backtrace as $function ) {
      //   bw_log( ++$i, ( empty( $function['class'] ) ? '' : $function['class'] . '::' ) . $function['function'] . '()' );
      //   // bw_log( ++$i, $function );
      // }
      // bw_log( 'debug backtrace', debug_backtrace( options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: 8 ) );
      try {
        $widget_options = [
          'classname' => 'widget_' . BW_ID_BASE,
          'description' => __( 'The latest blip from your Blipfoto account.', 'blipper-widget' ),
          'show_instance_in_rest' => true,
        ];
        $control_options = [
          'id_base' => BW_ID_BASE,
        ];
        parent::__construct(
          id_base: $control_options['id_base'],
          name: __( 'Blipper Widget', 'blipper-widget' ),
          widget_options: $widget_options,
          control_options: $control_options
        );

        self::bw_load_dependencies();
        // error_log( 'dependencies loaded: ' . var_export( self::$dependencies_loaded, true ) );

        self::$settings = new Blipper_Widget_Settings();
        // error_log( 'settings created: ' . var_export( !empty( self::$settings ), true ) );

        self::add_hooks_and_filters();
        // error_log( 'hooks and filters added: ' . var_export( self::$hooks_and_filters_added, true ) );

      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg( $e );
      } finally {
        // if ( false !== $this->id ) {
          // error_log( 'widget id: ' . var_export( $this->id, true ) );
        // }
        // error_log( 'this: ' . var_export( $this, true ) );
      }
    }

    /**
      * Renders the widget in a widget-enabled area. This is the
      * front-end of the widget.
      *
      * @since    0.0.1
      * @api
      *
      * @param array $widget_settings The settings from the widget as
      * provided by WordPress.
      * @param array $user_attributes The settings from the widget as
      * provided by the user in the widget's backend form as seen in the
      * Customiser or Appearance > Widgets > Blipper Widget. The array may be
      * empty on adding the widget to a widget area.
      */
    public function widget( $widget_settings, $user_attributes ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'current filter', current_filter() );
      // bw_log( 'widget id', $this->id );

      echo '<p><b>' . $this->id . '</b></p>';

      // error_log( 'initial user attributes: ' . var_export( $user_attributes, true ) );

      if ( empty( $user_attributes ) ) {
        $user_attributes = self::bw_get_default_attributes( true );
        // error_log( 'using default attributes:' );
      // } else {
        // error_log( 'using provided user attributes:' );
      }
      // $this->bw_update_option( $user_attributes );
      $user_attributes = self::bw_standardise_settings_format( $user_attributes );
      // error_log( var_export( $user_attributes, true ) );

      echo $widget_settings['before_widget'];
      echo self::bw_render_the_blip(
        user_attributes: $user_attributes,
        is_widget: true,
        widget_settings: $widget_settings
      );
      echo $widget_settings['after_widget'];

      // error_log( 'widget() this: ' . var_export( $this, true ) );
    }

    /**
      * Renders the form used in the widget settings (user attributes) panel
      * of the customiser. The form displays the settings already saved in
      * the database, and allows the user to change them if desired. This is
      * the back end of the widget (but not the backend of the plugin. The
      * backend of the plugin is in Settings > Blipper Widget).
      *
      * @since    0.0.1
      * @api
      * @param    array     $user_attributes  The settings currently saved in the database
      * @return string An empty string. The parent function returns 'noform'.
      */
    public function form( $user_attributes ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'current filter', current_filter() );
      // bw_log( 'widget id', $this->id );

      echo '<p><b>' . $this->id . '</b></p>';

      $user_attributes = self::bw_get_display_values( $user_attributes );
      ksort( $user_attributes );
      // error_log( 'user attributes: ' . var_export( $user_attributes, true ) );
      $result = $this->bw_display_form( $user_attributes );

      // error_log( 'form() this: ' . var_export( $this, true ) );
      return '';
    }

    /**
      * Updates the widget settings (user attributes) that were set using the
      * widget settings (attributes) form in the admin panel (Appearance >
      * Widgets > Blipper Widget) or the customiser.
      *
      * @since    0.0.1
      * @api
      * @param    array     $new_settings     The settings the user wants to change
      * @param    array     $old_settings     The settings currently saved in the database
      * @return   array                       The validated settings based on the user's input to be saved in the database
      */
    public function update( $new_settings, $old_settings ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'debug backtrace', debug_backtrace( options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: 7 ) );

      // error_log( 'widget id: ' . var_export( $this->id, true ) );


      // Save the old settings so any cache created from them, eg in the Customiser, can be deleted later:
      // $updated = update_option(
      //   option: BW_DB_WIDGET_OLD_SETTINGS,
      //   value: $old_settings
      // );
      // error_log( 'updated ' . BW_DB_WIDGET_OLD_SETTINGS . ': ' . var_export( $updated, true ) );
      // if ( $updated ) {
        // error_log( var_export( get_option( BW_DB_WIDGET_OLD_SETTINGS ) ) );
      // }

      $new_settings = self::bw_standardise_settings_format( $new_settings );
      $old_settings = self::bw_standardise_settings_format( $old_settings );

      return self::bw_validate_widget_settings( $new_settings, $old_settings );
    }

    public static function bw_save_old_shortcode_attributes( int $post_id ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'current filter', current_filter() );

      // Check if this is a valid post or page type and if it's not an autosave:
      $post_type = get_post_type( $post_id );
      // error_log( 'post type: ' . var_export( $post_type, true ) );
      if ( ( 'post' !== $post_type && 'page' !== $post_type ) || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        // error_log( 'not saving old attributes of ' . var_export( $post_type, true ) );
        return;
      }

      // Get the post content and see if the shortcode is present. If it is, then harvest the shortcode's attributes and store them in the post meta:
      $post_content = get_post_field( 'post_content', $post_id );
      preg_match( '/\[blipper_widget\s+([^]]+)\]/', $post_content, $matches );
      // error_log( 'matches: ' . var_export( $matches, true ) );
      if ( !empty( $matches[1] ) ) {
        // Clear up any existing old attributes:
        $old_attributes = get_post_meta( $post_id, '_bw_old_attributes', true );
        // error_log( 'post ' . $post_id . '; existing old attributes: ' . var_export( $old_attributes, true ) );
        delete_post_meta( $post_id, '_bw_old_attributes' );
        // error_log( 'post ' . $post_id . '; deleted old attributes: ' . var_export( empty( $old_attributes ), true ) );

        // Save the new old attributes:
        update_post_meta( $post_id, '_bw_old_attributes', $matches[1] );
        $old_attributes = get_post_meta($post_id, '_bw_old_attributes', true);
        // error_log( 'post ' . $post_id . '; saved old attributes: ' . var_export( $old_attributes, true ) );
      }
    }

    /**
     * Adds a shortcode so the widget can be placed in a post or on a page.
     *
     * @param array    $shortcode_attributes        The settings (attributes) included in the
     *                                shortcode.  Not all the available settings are
     *                                necessarily supported.
     * @param string   $content     The content, if any, from between the shortcode
     *                                tags.
     *
     * @since 1.1
     */
    public static function bw_shortcode_blip_display( array $shortcode_attributes, string $content = '', string $shortcode = '' ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $defaults = self::bw_get_default_attributes( false );
        $current_attributes = shortcode_atts( $defaults, $shortcode_attributes, $shortcode );
        $the_blip_title = self::bw_get_the_blip_title( $current_attributes );

        // Generate a new cache key based on the current attributes and the title:
        $new_cache_key = self::bw_get_a_cache_key( $current_attributes, $the_blip_title );

        $old_cache_key = '';
        // Get the old cache key, if there is one, from the post meta:
        $post_id = get_the_ID();
        if ( false !== $post_id ) {
          $old_attributes = explode( ' ', get_post_meta( $post_id, '_bw_old_attributes', true ) );
          // error_log( 'old shortcode attributes: ' . var_export( $old_attributes, true ) );
          $temp_atts = [];
          $title = '';
          foreach ( $old_attributes as $attribute ) {
            $key_value = explode( '=', $attribute, 2 );
            // error_log( var_export( $key_value, true ) );
            // Check if we have both key and value
            if ( count( $key_value ) === 2 ) {
              // error_log( $key_value[0] . ' => ' . $key_value[1] );
              // Trim the key and value, and handle quotes for the title
              $key = trim( $key_value[0] );
              $value = trim( $key_value[1], "'" ); // Remove single quotes from the value if present
              $temp_atts[$key] = $value;
            } else if ( count( $key_value ) === 1 ) {
              // If the title contains a space, it'll have been separated during the explosion of the shortcode attributes from the post content. Collate it back here:
              $title .= ' ' . $key_value[0];
            }
          }
          // error_log( 'temp attributes: ' . var_export( $temp_atts, true ) );
          $old_attributes = shortcode_atts( $defaults, $temp_atts, $shortcode );
          // Put the title back together:
          $old_attributes['title'] .= trim( $title, "'" );
          // error_log( 'old attributes: ' . var_export( $old_attributes, true ) );

          // Generate the old cache key based on the old attributes and the old title:
          $the_old_blip_title = self::bw_get_the_blip_title( $old_attributes );
          $old_cache_key = self::bw_get_a_cache_key( $old_attributes, $the_old_blip_title );
          // error_log( 'old cache key: ' . var_export( $old_cache_key, true ) );
          // error_log( 'new cache key: ' . var_export( $new_cache_key, true ) );
          $updated = ( $new_cache_key !== $old_cache_key ) || self::bw_compare_old_and_new_attributes( $old_attributes, $current_attributes, false );

          if ( $updated ) {
            // error_log( 'shortcode attributes have changed' );
            $deleted = self::bw_delete_cache( $old_cache_key );
            // error_log( 'deleted old shortcode cache: ' . var_export( $deleted, true ) );
            // $old_attributes = get_post_meta( $post_id, '_bw_old_attributes', true );
            // error_log( 'post ' . $post_id . '; deleting old attributes: ' . var_export( $old_attributes, true ) );
            // delete_post_meta( $post_id, '_bw_old_attributes' );
            // $old_attributes = get_post_meta( $post_id, '_bw_old_attributes', true );
            // error_log( 'post ' . $post_id . '; deleted old attributes: ' . var_export( empty( $old_attributes ), true ) );
            self::bw_save_old_shortcode_attributes( $post_id );
          } else {
            // error_log( 'shortcode attributes haven\'t changed' );
          }
        }
        return self::bw_render_the_blip(
          user_attributes: $current_attributes,
          is_widget: false,
          content: $content,
          cache_key: $old_cache_key
        );
      } catch( \Exception $e ) {
        return self::bw_display_error_msg( $e );
      }
    }

    private static function bw_get_default_attributes( bool $is_widget, bool $standardise = true ): array {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $default_attributes = self::DEFAULT_SETTING_VALUES['common'];
      if ( $is_widget ) {
        $default_attributes = array_merge( self::DEFAULT_SETTING_VALUES['widget'], $default_attributes );
      } else {
        $default_attributes = array_merge( self::DEFAULT_SETTING_VALUES['shortcode'], $default_attributes );
      }
      if ( $standardise ) {
        $default_attributes = self::bw_standardise_settings_format( $default_attributes );
      }
      // error_log( 'default settings: ' . var_export( $default_attributes, true ) );
      return $default_attributes;
    }

    /**
     * Compares the old and new attributes to see if there's been any change.
     *
     * @param array<string, mixed> $old_attributes The settings as they were
     * before they were changed in the Customiser or the shortcode.
     * @param array<string, mixed> $new_attributes The settings as they are
     * after they were changed in the Customiser or the shortcode.
     * @param bool $is_widget True if the blip is being rendered by the
     * widget; false if the blip is being rendered by a shortcode. Used for
     * debug purposes only.
     * @return bool True if the settings are different; false if they're the
     * same.
     */
    private static function bw_compare_old_and_new_attributes( array $old_attributes, array $new_attributes, bool $is_widget ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $result = false;

      $old_attributes = self::bw_standardise_settings_format( $old_attributes );
      $new_attributes = self::bw_standardise_settings_format( $new_attributes );

      // $old_attributes = array_merge( self::bw_get_default_attributes( true ), $old_attributes );
      // $new_attributes = array_merge( self::bw_get_default_attributes( true ), $new_attributes );

      // Need to perform array_diff_assoc() both ways round because it's not known whether there'll be settings missing from the new one or the old one or whatever. The results of each operation need merging. If the resulting array is empty, there have been no changes to the settings:
      $updated_attributes_only = array_merge( array_diff_assoc( $new_attributes, $old_attributes ), array_diff_assoc( $old_attributes, $new_attributes ) );
      $result = $result || empty( $updated_attributes_only ) ? false : true;
      // bw_log( ( $is_widget ? 'Widget' : 'Shortcode' ) . ' attributes changed', $result );
      return $result;
    }

    private static function bw_get_a_cache_key( array $settings, ?string $the_blip_title = null ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'debug backtrace', debug_backtrace( options: DEBUG_BACKTRACE_IGNORE_ARGS, limit: 3 ) );

      try {
        $settings = self::bw_standardise_settings_format( $settings );
        $cache_key = BW_PREFIX . md5( self::CACHE_EXPIRY . implode( ' ', $settings ) );
        // bw_log( 'Cache key', $cache_key );
        // bw_log( 'Cache key ' . var_export( $cache_key, true ) . ' generated from', [ self::CACHE_EXPIRY, $settings, wp_strip_all_tags( $the_blip_title ), ] );
        $length = strlen( $cache_key );
        if ( self::WP_TRANSIENT_KEY_LIMIT < $length ) {
          bw_log( 'Cache key length (must be <' . self::WP_TRANSIENT_KEY_LIMIT + 1 . ' chars)', var_export( $length, true ) );
          $cache_key = substr( $cache_key, 0, self::WP_TRANSIENT_KEY_LIMIT );
          throw new \LengthException( 'Too long a cache key generated; it should be less than ' . self::WP_TRANSIENT_KEY_LIMIT + 1 . ' characters, but it is ' . var_export( $length, true ) . ' characters. Cache key has been truncated, which may cause errors later.' );
        }
      } catch ( \LengthException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e );
      } finally {
        // bw_log( 'Generated cache key for ' . var_export( self::bw_get_the_blip_title( $settings ), true ), $cache_key );
        return $cache_key;
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
     * @param string[] $widget_settings The array of WP widget settings or default
     * settings.
     * @param string[] $settings The array of BW settings from either the
     * widget or the shortcode (set by the user).
     * @param string $the_blip_title Deprecated since 1.2.6.
     * @param bool $cache_key If the shortcode has been cached, the cache key
     * that was generated will not match that of the shortcode with the
     * current settings. This is really only needed when coming from the
     * shortcode, at least for now. Perhaps the shortcode code won't need to
     * check this, but done here later.
     * @param bool $is_widget True if the blip is being rendered by the
     * widget; false if the blip is being rendered by a shortcode. Used for
     * debug purposes only.
     * @return string|bool The HTML that will render the blip or false on
     * failure.
     */
    private static function bw_render_the_blip( array $user_attributes, bool $is_widget, ?string $the_blip_title = null, ?array $widget_settings = null, ?string $content = null, ?string $cache_key = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( null !== $the_blip_title ) {
        _deprecated_argument( __METHOD__, '1.2.6' );
      }

      $the_blip = '';
      // error_log( 'cache key: ' . var_export( self::$cache_key, true ) );
      try {
        $client_ok = empty( self::$client ) ? self::bw_create_blipfoto_client() : true;
        // error_log( 'client ok: ' . var_export( $client_ok, true ) );
        if ( $client_ok ) {
          self::$cache_key = self::bw_get_a_cache_key( $user_attributes );
          $the_cache = self::bw_get_cache();
          // error_log( 'rendering blip with cache key: ' . var_export( self::$cache_key, true ) );

          // bw_log( 'This blip has been cached', ( empty( $the_cache ) ? 'no' : 'yes' ) );

          if ( empty( $the_cache ) ) {
            // bw_log( data_name: 'Generating ' . ( $is_widget ? 'widget' : 'shortcode' ) . ' blip from scratch with cache key', data: self::$cache_key );
            // The blip does not exist in the cache, so it needs to be generated:
            $the_blip = self::bw_generate_blip(
              widget_settings: $widget_settings,
              user_attributes: $user_attributes,
              is_widget: $is_widget,
              content: $content
            );
          } else {
            // bw_log( 'Rendering ' . ( $is_widget ? 'widget' : 'shortcode' ) . ' blip from cache', self::$cache_key );
            // The blip has been cached recently and its settings have not changed, so return the cached blip:
            $the_blip = $the_cache;
          }
        } else {
          // If the client isn't ok, then we don't want to display any blips, including cached ones; delete them all:
          bw_delete_all_cached_blips( BW_PREFIX );
          self::$client = null;
        }
      } catch ( Blipper_Widget_OAuthException $e ) {
        bw_delete_all_cached_blips( BW_PREFIX );
        self::bw_display_error_msg( $e, __( 'Please check your OAuth credentials are valid and try again', 'blipper-widget' ) );
      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg(
          e: $e,
          write_to_log: true
        );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e );
      }
      return $the_blip;
    }

    /**
     * Gets the given cache (stored as a WP transient).
     *
     * @since <1.2.6
     * @author pandammonium
     *
     * @param ?string $cache_key The key of the cache to find. If it's not
     * provided, the stored cache key is used. Default is null.
     * @return false|string Returns false on failure, otherwise returns the
     * transient in the form of a string.
     */
    private static function bw_get_cache( ?string $cache_key = null ): string|false {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $transient = get_transient( $cache_key ?? self::$cache_key );
      // bw_log( data_name: 'Cache ' . var_export( $cache_key ?? self::$cache_key, true ), data: $transient, is_html: true );
      return $transient;
    }

    /**
     * Generates the blip from scratch
     */
    private static function bw_generate_blip( array $user_attributes, bool $is_widget, ?array $widget_settings = null, ?string $content = null ): string|false {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( ( self::$client ?? null ) !== null ) {

        $styled_title = self::bw_get_styled_title(
          user_attributes: $user_attributes,
          widget_settings: $widget_settings
        );
        $the_blip = '<div class="blipper-widget">' . $styled_title . self::bw_get_blip(
            user_attributes: $user_attributes,
            is_widget: $is_widget,
            widget_settings: $widget_settings,
            content: $content
          ) . '</div>';
        // bw_log( 'Creating blip with user attributes', $user_attributes );
        // bw_log( 'The blip', $the_blip );

        // Save the blip in the cache for next time it's loaded before it expires:
        try {
          self::bw_set_cache( $the_blip, $is_widget );
        } catch ( \Exception $e ) {
          self::bw_display_error_msg( $e );
        } finally {
          // Need to display the blip whether it's been cached or not.
          return $the_blip;
        }
      } else {
        return false;
      }
    }

    /**
     * Caches the given data.
     *
     * @author dartiss, pandammonium
     * @since 2.0.0
     *
     * @param string $data_to_cache The data that should be cached – that is,
     * the newly generated blip data.
     * @param bool $is_widget True if the blip is being rendered by the
     * widget; false if the blip is being rendered by a shortcode. Used for
     * debug purposes only.
     * @return bool True if the data was successfully cached; otherwise false.
     */
    private static function bw_set_cache( string $data_to_cache, $is_widget ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // bw_log( 'Cache key', self::$cache_key );
      // bw_log( 'Cache expiry', self::CACHE_EXPIRY );

      $result = false;

      try {
        if ( self::CACHE_EXPIRY ?? is_numeric( self::CACHE_EXPIRY ) ) {
          // error_log( 'cache expiry is null or numeric: ' . gettype( self::CACHE_EXPIRY ) );
          if ( empty( self::$cache_key ) ) {
            // error_log( 'cache key is empty' );
            throw new \LogicException( 'The cache key has not yet been set.' );
          } else if ( false === strpos( BW_PREFIX, substr( self::$cache_key, 0, strlen( BW_PREFIX ) ) ) ) {
            // error_log( 'cache key does not start with ' . BW_PREFIX );
            throw new \InvalidArgumentException( 'The cache key is invalid.' );
          }
          $transient = get_transient( self::$cache_key );
          if ( false === $transient ) {
            $result = set_transient( self::$cache_key, $data_to_cache, self::CACHE_EXPIRY );
            // error_log( 'cache set: ' . var_export( $result, true ) );
            $transient = get_transient( self::$cache_key );
            // error_log( 'cache ' . var_export( self::$cache_key, true ) . ': ' . ( false === $transient ? 'does not exist' : 'exists' ) );
          } else {
            // error_log( 'cache already exists' );
            // Don't fail if the cache already exists:
            $result = true;
          }
        } else {
          // error_log( 'cache expiry is neither null nor numeric: ' . gettype( self::CACHE_EXPIRY ) );
          throw new \TypeError( 'Cache expiry time is invalid. Expected null or integer; got ' . var_export( gettype( self::CACHE_EXPIRY ), true ) . ' with value ' . var_export( self::CACHE_EXPIRY, true ) . '.' );
        }
      } catch ( \LogicException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \InvalidArgumentException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \TypeError $e ) {
        self::bw_display_error_msg( $e );
      } finally {
        // bw_log( ( $is_widget ? 'Widget' : 'Shortcode' ) . ' blip ' . ( isset( $data_to_cache['title'] ) ? var_export( $data_to_cache['title'], true ) : var_export( $data_to_cache['title'], true ) ) . ' cached with key ' . var_export( self::$cache_key, true ), $result );
      }

      try {
        if ( false === $result ) {
          // Clean up on failure.
          $deleted = self::bw_delete_cache( self::$cache_key );
          $deleted_msg = ( $deleted ? 'Cache has been deleted' : ( ( !isset( $transient ) || false === $transient ) ? 'Cache was not created' : ' Cache was not deleted and still exists in some state' ) );
          throw new \Exception( 'Failed to save blip in cache ' . var_export( self::$cache_key, true ) . '. ' . $deleted_msg );
        }
      } catch( \Exception $e ) {
        self::bw_display_error_msg( $e );
      } finally {
        return $result;
      }
    }

    private static function bw_delete_cache( string $cache_key ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $transient = get_transient( $cache_key );
      // error_log( 'cache ' . var_export( $cache_key, true ) . ' exists: ' . var_export( false !== $transient, true ) );
      $result = ( false !== $transient ) && delete_transient( $cache_key );
      // bw_log( 'Deleted cache ' . var_export( $cache_key, true ), $result );
      return $result;
    }

    /**
     * Normalises the arguments from the shortcode.
     *
     * @since <1.2.6
     * @deprecated 1.2.6 Isn't necessary any more.
     */
    private static function bw_normalise_attributes( string|array|null $shortcode_attributes, $shortcode = '' ): string|array|null {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( null === $shortcode_attributes ) {
        return null;
      } else {
        $normalised_attributes = [];
        switch( gettype( $shortcode_attributes ) ) {
          case 'array':
            if ( isset( $shortcode_attributes[ 'title' ] ) ) {
              $normalised_attributes[ 'title' ] = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $shortcode_attributes[ 'title' ]);
              // error_log( 'shortcode  atts[\'title\']: ' . var_export( $shortcode_attributes[ 'title' ], true ) );
              // error_log( 'normalised atts[\'title\']: ' . var_export( $normalised_attributes[ 'title' ], true ) );
            }
            $i = 0;
            foreach ( $shortcode_attributes as $key => $value ) {
              if ( ( $i === $key ) && isset( $shortcode_attributes[ $key ] ) ) {
                $normalised_attributes[ $key ] = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $shortcode_attributes[ $key ]);
                $normalised_attributes[ 'title' ] .= ' ' . $shortcode_attributes[ $key ];
                // error_log( 'key => value: ' . var_export( $key, true ) . ' => ' . var_export( $value, true ) );
                // error_log( 'shortcode  atts[\'title\']: ' . var_export( $shortcode_attributes[ 'title' ], true ) );
                // error_log( 'normalised atts[\'title\']: ' . var_export( $normalised_attributes[ 'title' ], true ) );
                // error_log( 'shortcode  atts[' . $normalised_attributes[ $key ] . ']: ' . var_export( $shortcode_attributes[ $key ], true ) );
                // error_log( 'normalised atts[' . $shortcode_attributes[ $key ] . ']: ' . var_export( $normalised_attributes[ $key ], true ) );
                unset( $shortcode_attributes[ $i ] );
                ++$i;
              }
            }
          break;
          case 'string':
            $normalised_attributes = str_replace(array_keys(self::QUOTES), array_values(self::QUOTES), $shortcode_attributes);
          break;
          default:
            throw new \Exception( 'Please check your shortcode: <samp><kbd>[' . ( '' === $shortcode ? '&lt;shortcode&gt;' : $shortcode ) . ' ' . print_r( $shortcode_attributes, true ) . ']' . '</kbd></samp>. These attributes are invalid' ) ;
        }
      }
      // bw_log( 'Normalised attributes', $normalised_attributes );
      return $normalised_attributes;
    }

    private static function bw_validate_widget_settings( array $new_settings, array $old_settings ): array {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $validated_settings = self::bw_validate_the_widget_settings( $new_settings, $old_settings );

      // Get the old cache key:
      $old_cache_key = self::bw_get_a_cache_key( $old_settings );
      // error_log( 'old cache key: ' . var_export( $old_cache_key, true ) );

      // Get the validated cache key:
      $validated_cache_key = self::bw_get_a_cache_key( $validated_settings );
      // error_log( 'validated cache key: ' . var_export( $validated_cache_key, true ) );

      $updated = ( $validated_cache_key !== $old_cache_key ) || self::bw_compare_old_and_new_attributes( $old_settings, $validated_settings, true );

      if ( $updated ) {
        // Delete the cache so there isn't an unnecessary build-up of transients.
        $deleted = self::bw_delete_cache( $old_cache_key );
      // } else {
        // error_log( 'widget settings haven\'t changed' );
      }
      self::$cache_key = $validated_cache_key;
      // bw_log( 'Updated widget settings', $validated_settings );
      return $validated_settings;
    }

    private static function bw_validate_the_widget_settings( array $new_settings, array $old_settings ): array {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $validated_settings = [];

      $validated_settings['title'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'title' );
      $validated_settings['display-date'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'display-date' );
      $validated_settings['display-journal-title']  = self::bw_validate_widget_setting( $new_settings, $old_settings, 'display-journal-title' );
      $validated_settings['add-link-to-blip'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'add-link-to-blip' );
      $validated_settings['display-powered-by'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'display-powered-by' );
      $validated_settings['border-style'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'border-style' );
      $validated_settings['border-width'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'border-width' );
      $validated_settings['border-color'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'border-color' );
      $validated_settings['background-color'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'background-color' );
      $validated_settings['color'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'color' );
      $validated_settings['link-color']   = self::bw_validate_widget_setting( $new_settings, $old_settings, 'link-color' );
      $validated_settings['padding'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'padding' );
      $validated_settings['style-control'] = self::bw_validate_widget_setting( $new_settings, $old_settings, 'style-control');
      $validated_settings = self::bw_standardise_settings_format( $validated_settings );

      return $validated_settings;
    }

    /**
     * Standardises the format of the settings such that settings with empty
     * values (or values equivalent to empty) are removed for consistency. The
     * settings are sorted by key for good measure.
     *
     * @since 1.2.6
     * @author pandammonium
     *
     * @param array $settings The settings to standardise.
     * @return array The standardised aettings.
     */
    private static function bw_standardise_settings_format( array $settings ): array {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $settings = array_filter( $settings, function( $setting ) {
        return $setting !== 'hide' && !empty( $setting );
      });
      // Manipulate the settings to make them comparable to those retrieved from the Customiser:
      foreach ( $settings as $setting => $value ) {
        if ( 'show' === $value || '1' === $value ) {
          $new_settings[$setting] = 'show';
        }
      }
      ksort( $settings );
      return $settings;
    }

    /**
      * Validates the input.
      *
      * Makes sure the input comprises only printable/alphanumeric (depending on the
      * field) characters; otherwise, returns an empty string/the default value.
      *
      * @since    0.0.1
      * @param    array     $new_settings     The setting the user wants to change
      * @param    array     $old_settings     The setting currently saved in the
      *                                         database
      * @param    string    $setting_field    The setting to validate.
      * @return   string    $setting          The validated setting.
      */
    private static function bw_validate_widget_setting( $new_settings, $old_settings, $setting_field ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $setting = null;

      // bw_log( 'setting field', $setting_field );
      // bw_log( 'old setting', $old_settings[$setting_field] );
      // bw_log( 'proposed setting', $old_settings[$setting_field] );

      if ( array_key_exists( $setting_field, $old_settings ) && array_key_exists( $setting_field, $new_settings ) ) {
        if ( $new_settings[$setting_field] === $old_settings[$setting_field] ) {
          $setting = $old_settings[$setting_field];
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
          // $setting = array_key_exists( $setting_field, $new_settings ) ? ( ! empty( $new_settings[$setting_field] ) ? 'show' : 'hide' ) : 'hide';
          if ( array_key_exists( $setting_field, $new_settings ) ) {
            if ( empty( $new_settings[$setting_field] ) ) {
              $setting = 'hide';
            } else {
              $setting = 'show';
            }
          } else {
            $setting = 'hide';
          }
          // error_log( var_export( $setting_field, true ) . ': ' . var_export( $setting, true ) );
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
      * Gets the values to display on the settings form.
      *
      * @since    0.0.1
      * @param    array     $settings         The BW widget settings saved in
      *                                         the database.
      * @return   array                       The widget settings saved in the
      *                                         database
      */
    private static function bw_get_display_values( array $settings ): array {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $new_settings = [];

      try {
        $new_settings['title'] = self::bw_get_display_value( 'title', $settings );
        $new_settings['display-date'] = self::bw_get_display_value( 'display-date', $settings );
        $new_settings['display-journal-title'] = self::bw_get_display_value( 'display-journal-title', $settings );
        $new_settings['add-link-to-blip'] = self::bw_get_display_value( 'add-link-to-blip', $settings );
        $new_settings['display-powered-by'] = self::bw_get_display_value( 'display-powered-by', $settings );
        $new_settings['border-style'] = self::bw_get_display_value( 'border-style', $settings );
        $new_settings['border-width'] = self::bw_get_display_value( 'border-width', $settings );
        $new_settings['border-color'] = self::bw_get_display_value( 'border-color', $settings );
        $new_settings['background-color'] = self::bw_get_display_value( 'background-color', $settings );
        $new_settings['color'] = self::bw_get_display_value( 'color', $settings );
        $new_settings['link-color'] = self::bw_get_display_value( 'link-color', $settings );
        $new_settings['padding'] = self::bw_get_display_value( 'padding', $settings );
        $new_settings['style-control'] = self::bw_get_display_value( 'style-control', $settings );

      } catch ( \ErrorException $e ) {

        self::bw_display_error_msg( $e, __( 'Please check your settings are valid and try again', 'blipper-widget' ) );

      } catch ( \Exception $e ) {

        self::bw_display_error_msg( $e, __( 'Something has gone wrong getting the user settings', 'blipper-widget' ) );

      }
      ksort( $new_settings );
      // bw_log( 'new display settings', $new_settings );

      return $new_settings;
    }

    /**
     * Gets the display value of the given setting.
     */
    private static function bw_get_display_value( string $setting, array $settings ): string|null {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        if ( array_key_exists( $setting, $settings ) ) {
          return esc_attr( $settings[$setting] );
        } else {
          switch ( $setting ) {
            case 'background-color':
            case 'border-color':
            case 'border-style':
            case 'border-width':
            case 'color':
            case 'link-color':
            case 'padding':
            case 'style-control':
              if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES['widget'] ) ) {
                return self::DEFAULT_SETTING_VALUES['widget'][$setting];
              } else if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES['common'] ) ) {
                return self::DEFAULT_SETTING_VALUES['common'][$setting];
              } else {
                throw new \ErrorException( __( 'Invalid setting requested', 'blipper-widget' ) . ': ' . $setting );
              }
            break;
            case 'title':
            return '';
            case 'add-link-to-blip':
            case 'display-date':
            case 'display-journal-title':
            case 'display-powered-by':
            return 'hide';
            default:
            throw new \ErrorException( __( 'Invalid setting requested', 'blipper-widget' ) . ': ' . $setting );
          }
        }

      } catch ( \ErrorException $e ) {

        self::bw_display_error_msg( $e );
        return null;

      } catch ( \Exception $e ) {

        self::bw_display_error_msg( $e, 'Something has gone wrong getting the user settings' );
        return null;

      }
    }

    /**
      * Loads the files this widget needs.
      *
      * @since    0.0.1
      */
    private static function bw_load_dependencies(): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( self::$dependencies_loaded ) {
        // error_log( 'dependencies already loaded' );
      } else {
        // error_log( 'loading dependencies' );
        self::bw_load_blipfoto_dependencies();
        if ( self::$blipfoto_dependencies_loaded ) {
          require_once( plugin_dir_path( __FILE__ ) . 'class-settings.php' );
          self::$dependencies_loaded = true;
        }
      }
    }

    /**
      * Loads the Blipfoto API.
      *
      * @since    0.0.1
      */
    private static function bw_load_blipfoto_dependencies(): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( self::$blipfoto_dependencies_loaded ) {
        // error_log( 'blipfoto dependencies already loaded' );
      } else {
        $folders = [
          'Traits' => [
            'Helper'
          ],
          'Exceptions' => [
            'BaseException',
            'ApiResponseException',
            'InvalidResponseException',
            'NetworkException',
            'OAuthException',
            'FileException'
          ],
          'Api' => [
              'Client',
              'OAuth',
              'Request',
              'Response',
              'File'
            ],
          ];

        $path = plugin_dir_path( __FILE__ ) . '../includes/php-sdk/src/Blipfoto/';

        foreach ( $folders as $folder => $files ) {
          foreach ( $files as $file ) {
            require_once( $path . $folder . '/' . $file . '.php' );
          }
        }
        self::$blipfoto_dependencies_loaded = true;
        // error_log( 'blipfoto dependencies loaded' );
      }
    }

    /**
      * Creates an instance of the Blipfoto client and tests it's ok
      *
      * @since    0.0.1
      * @param    array     $widget_settings             The WP widget settings;
      *                                         apparently unused
      * @return   bool      $client_ok        True if the client was created
      *                                         successfully, else false
      * @throws Blipper_Widget_OAuthException If no OAuth credentials (ie
      * username and access token) have been supplied.
      */
    private static function bw_create_blipfoto_client( $widget_settings = null ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $create_new_client = empty( self::$client ) ? true : false;
        $client_ok = !$create_new_client && empty( self::$client->accessToken() ) ? false : true;
        // error_log( 'create new client: ' . var_export( $create_new_client, true ) );
        // error_log( 'client ok: ' . var_export( $client_ok, true ) );

        // Get the settings from the back-end form:
        $oauth_settings = Blipper_Widget_Settings::bw_get_settings();

        if ( empty( $oauth_settings ) ) {
          throw new Blipper_Widget_OAuthException();
        }

        if ( !$client_ok || $create_new_client ) {
          $client_ok = self::bw_check_oauth_credentials_exist( $oauth_settings );
        }

        if ( $client_ok && $create_new_client ) {
          $client_ok = self::bw_create_blipfoto_client_create_new_client( $oauth_settings );
        }

        if ( $client_ok ) {
          $client_ok = self::bw_create_blipfoto_client_get_user_profile( $oauth_settings );
        }

        if ( !$client_ok ) {
          bw_delete_all_cached_blips( BW_PREFIX );
          throw new \ErrorException( 'Could not create new Blipfoto client.' );
        }
      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e );
      } finally {
        // error_log( 'client ok: ' . var_export( $client_ok, true ) );
        return $client_ok;
      }
    }

    /**
     * Checks that the user has supplied OAuth credentials.
     *
     * Does not check that they are actually correct.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, string> $oauth_settings The Blipfoto username and
     * access token as supplied by the user in the back-end settings form.
     * @return bool True if all fields have been filled in; otherwise false.
     */
    private static function bw_check_oauth_credentials_exist( array $oauth_settings ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $credentials_exist = false;
      try {
        if ( empty( $oauth_settings['username'] ) && empty( $oauth_settings['access-token'] ) ) {
          throw new Blipper_Widget_OAuthException( 'Missing username and access token.');
        } else if ( empty( $oauth_settings['username'] ) ) {
          throw new Blipper_Widget_OAuthException( 'Missing username.' );
        } else if ( empty( $oauth_settings['access-token'] ) ) {
          throw new Blipper_Widget_OAuthException( 'Missing access token.' );
        } else {
          $credentials_exist = true;
        }
      } catch ( Blipper_Widget_OAuthException $e ) {
        // bw_log( 'Blipper_Widget_OAuthException thrown in ' . $e->getFile() . ' on line ' . $e->getLine(), $e->getMessage() );
        self::bw_display_error_msg( $e, 'You are attempting to display your latest blip with Blipper Widget, but your OAuth credentials are invalid.  Please check these credentials on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '" rel="nofollow nopopener noreferral">the Blipper Widget settings page</a> to continue.<br/>If your settings are ok, try again later. If it still doesn\'t work, please consider <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem, and include any useful information from the WordPress error log' );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting the Blipfoto account' );
      } finally {
        if ( !$credentials_exist ) {
          bw_log( 'OAuth credentials not set', data: $oauth_settings, includes_data: true, php_error: E_USER_WARNING );
          bw_delete_all_cached_blips( BW_PREFIX );
        }
      }
      return $credentials_exist;
    }

    /**
     * Creates a new Blipfoto client using the OAuth settings that the user
     * provided.
     *
     * Only the access token is required. The username is supplied only to
     * check that the user knows the username of the account for a vague
     * security check. The client id and the client secret are not necessary,
     * even though they're the only non-optional arguments to the Blipfoto
     * API function.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, string> $oauth_settings The Blipfoto username and
     * access token as supplied by the user in the back-end settings form.
     * @return bool True if a new client was created; otherwise false.
     */
    private static function bw_create_blipfoto_client_create_new_client( array $oauth_settings ): bool {
      bw_log( 'method', __METHOD__ . '()' );
      bw_log( 'arguments', func_get_args() );

      $client_ok = false;
      try {
        // Create a new client using the OAuth settings from the database
        self::$client = self::$client ?? new Client (
          '', // client id
          '', // client secret
          $oauth_settings['access-token'],
        );
        // error_log( 'client: ' . var_export( self::$client, true ) );
        if ( !isset( self::$client ) || empty( self::$client ) ) {
          unset( self::$client );
          throw new ApiResponseException( 'Failed to create the Blipfoto client.' );
        } else {
          // bw_log( 'Created new client', self::$client );
          $client_ok = true;
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e, 'Please try again later' );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong when trying to create the Blipfoto client' );
      } finally {
        if ( !$client_ok ) {
          $deleted = bw_delete_all_cached_blips( BW_PREFIX );
          bw_log( 'Client is not ok. Any cached blips were deleted', $deleted, php_error: E_USER_WARNING );
          self::$client = null;
        }
      }
      return $client_ok;
    }

    /**
     * Gets the user profile from the client.
     *
     * The username that the user provides is checked against the username of the account retrieved by the client. If there is a mismatch, the
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, string> $oauth_settings The Blipfoto username and
     * access token as supplied by the user in the back-end settings form.
     * @return bool True if a new client was created; otherwise false.
     */
    private static function bw_create_blipfoto_client_get_user_profile( array $oauth_settings ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $user_profile_ok = false;
      try {
        $user_profile = self::$client->get( 'user/profile' );
        if ( !empty( $user_profile ) && $user_profile->error() ) {
          throw new ApiResponseException( $user_profile->error() );
        }
        $user = $user_profile->data()['user'];
        if ( $user['username'] !== $oauth_settings['username'] ) {
          throw new Blipper_Widget_OAuthException( 'Unable to verify username.  Please check the username ' . var_export( $oauth_settings['username'], true ) . ' you entered on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> is correct.' );
          unset( self::$client );
        } else {
          $user_profile_ok = true;
        }
      } catch ( Blipper_Widget_OAuthException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e, 'There is a problem with the OAuth credentials' );
      } catch ( Blipper_Widget_BaseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting your Blipfoto account' );
      } finally {
        if ( !$user_profile_ok ) {
          // error_log( 'user profile is not ok' );
          // bw_delete_all_cached_blips( BW_PREFIX );
        }
      }
      return $user_profile_ok;
    }

    /**
      * Gets the blip using the user-set attributes and the widget settings.
      *
      * @since    1.1
      * @since    1.2.6 Refactored: method extraction.
      * @param    array     $widget_settings             The WP widget settings
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
    private static function bw_get_blip( array $user_attributes, bool $is_widget, ?array $widget_settings = null, ?string $content = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $the_blip = '';

      $data = [
        'blip' => null,
        'blips' => null,
        'descriptive_text' => null,
        'details' => null,
        'image_url' => '',
        'journal' => null,
        'user' => null,
        'user_profile' => null,
        'user_attributes' => null,
      ];

      $continue = self::bw_get_blip_get_blipfoto_user_profile( $data );

      if ( $continue ) {
        $continue = self::bw_get_blip_get_blipfoto_user_settings( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_get_blipfoto_user( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_get_blipfoto_journal( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_get_blips_from_journal( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_check_number_of_blips( $data );
      }

      if ( $continue ) {
        $data['blip'] = $data['blips'][0];
        $continue = self::bw_get_blip_get_blip_details( $data );
      }

      if ( isset( $user_attributes['display-desc-text'] ) && 'show' === $user_attributes['display-desc-text'] && $continue ) {
        // This is a shortcode-only attribute:
        if ( $is_widget ) {
          throw new \LogicException( 'The option to display the descriptive text is only permitted with the shortcode.' );
        }
        $continue = self::bw_get_blip_get_blip_descriptive_text( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_get_blip_image_url( $data );
      }

      if ( $continue ) {
        $continue = self::bw_get_blip_display_blip(
          the_blip: $the_blip,
          widget_settings: $widget_settings,
          user_attributes: $user_attributes,
          is_widget: $is_widget,
          content: $content,
          data: $data
        );
      }

      if ( $continue ) {
        return $the_blip;
      } else {
        return '';
      }
    }

    /**
     * Gets the user profile from the client.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the user profile is saved.
     * @return bool True if the user profile is obtained; otherwise false.
     */
    private static function bw_get_blip_get_blipfoto_user_profile( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['user_profile'] = self::$client->get( 'user/profile' );
        // error_log( 'data: ' . var_export( $data, true ) );
        if ( $data['user_profile']->error() ) {
          throw new ApiResponseException( $data['user_profile']->error() . '  Can\'t access your Blipfoto account.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          return true;
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting your user profile' );
      }
      return false;
    }

    /**
     * Gets the user settings from the client.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the user settings are saved.
     * @return bool True if the user settings are obtained; otherwise false.
     */
    private static function bw_get_blip_get_blipfoto_user_settings( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['user_attributes'] = self::$client->get( 'user/settings' );
        if ( $data['user_attributes']->error() ) {
          throw new ApiResponseException( $data['user_attributes']->error() . '  Can\'t access your Blipfoto account details.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.' );
        } else {
          return true;
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting your user settings' );
      }
      return false;
    }

    /**
     * Gets the user from the user profile.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the user is saved.
     * @return bool True if the user is obtained; otherwise false.
     */
    private static function bw_get_blip_get_blipfoto_user( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['user'] = $data['user_profile']->data('user');
        if ( empty( $data['user'] ) ) {
          throw new ApiResponseException( 'Can\'t access your Blipfoto account data.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.');
        } else {
          return true;
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto account' );
      }
      return false;
    }

    /**
     * Gets the journal from the client.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the journal is saved.
     * @return bool True if the journal is obtained; otherwise false.
     */
    private static function bw_get_blip_get_blipfoto_journal( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        // A page index of zero gives the most recent page of blips.
        // A page size of one means there will be only one blip on that page.
        // Together, these ensure that the most recent blip is obtained — which
        // is exactly what we want to display.
        $data['journal'] = self::$client->get(
          'entries/journal',
          [
            'page_index'  => 0,
            'page_size'   => 1
          ]
        );
        if ( $data['journal']->error() ) {
          throw new ApiResponseException( $data['journal']->error() . '  Can\'t access your Blipfoto journal.  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          return true;
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong accessing your Blipfoto journal' );
      }
      return false;
    }

    /**
     * Gets the blips (journal entries) from the journal.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the blips are saved.
     * @return bool True if the blips are obtained; otherwise false.
     */
    private static function bw_get_blip_get_blips_from_journal( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['blips'] = $data['journal']->data( 'entries' );
        if ( empty( $data['blips'] ) ) {
          throw new \ErrorException( 'Can\'t access your Blipfoto journal entries (blips).  Please check your settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue or try again later.');
        } else {
          return true;
        }
      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong accessing your entries (blips)' );
      }
      return false;
    }

    /**
     * Checks only one blip (entry) has been obtained from the journal.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the blips are saved.
     * @return bool True if there is only one blip; otherwise false.
     */
    private static function bw_get_blip_check_number_of_blips( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        // Assuming any blips have been retrieved, there should only be one.
        switch ( count( $data['blips'] ) ) {
          case 0:
            throw new \Exception( 'No Blipfoto entries (blips) found.  <a href="https://www.blipfoto.com/' . $data['user']['username'] . '" rel="nofollow">Your Blipfoto journal</a> must have at least one entry (blip) before Blipper Widget can display anything.');
          break;
          case 1:
            return true;
          break;
          default:
            throw new Blipper_Widget_BaseException( 'Blipper Widget was looking for one entry (blip) only, but found ' . count( $data['blips'] ) . '. Something has gone wrong.  Please try again' );
        }
      } catch ( Blipper_Widget_BaseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e );
      }
      return false;
    }

    /**
     * Gets the details from the client.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the details are saved.
     * @return bool True if the details are obtained; otherwise false.
     */
    private static function bw_get_blip_get_blip_details( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['details'] = self::$client->get(
          'entry',
          [
            'entry_id'          => $data['blip']['entry_id_str'],
            'return_details'    => 1,
            'return_image_urls' => 1
          ]
        );
        if ( empty( $data['details']->error() ) ) {
          return true;
        } else {
          throw new ApiResponseException( $data['details']->error() . '  Can\'t get the entry (blip) details.' );
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting the entry (blip) details' );
      }
      return false;
    }

    /**
     * Gets the descriptive text from the details.
     *
     * The descriptive text is the text component of the blip. There might
     * not be any.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the descriptive text is
     * saved.
     * @return bool True if the descriptive text is obtained; otherwise false.
     */
    private static function bw_get_blip_get_blip_descriptive_text( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      try {
        $data['descriptive_text'] = self::bw_sanitise_html( $data['details']->data( 'details.description_html' ), self::BW_ALLOWED_HTML );
        if ( isset( $data['descriptive_text'] ) ) {
          return true;
        } else {
          throw new ApiResponseException('Did not get the descriptive text.');
        }
      } catch ( ApiResponseException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting the entry\'s (blip\'s) descriptive text' );
      }
      return false;
    }

    /**
     * Gets the URL of the image from the details.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> &$data Where the image URL saved.
     * @return bool True if the image URL is obtained; otherwise false.
     */
    private static function bw_get_blip_get_blip_image_url( array &$data ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // Blipfoto has different quality images, each with its own URL.
      // Access is currently limited by Blipfoto to standard resolution, but
      // the plugin nevertheless looks for the highest quality image available.
      // $data['image_url'] = null;
      try {
        if ( $data['details']->data( 'image_urls.original' ) ) {
          $data['image_url'] = $data['details']->data( 'image_urls.original' );
        } else if ( $data['details']->data( 'image_urls.hires' ) ) {
          $data['image_url'] = $data['details']->data( 'image_urls.hires' );
        } else if ( $data['details']->data( 'image_urls.stdres' ) ) {
          $data['image_url'] = $data['details']->data( 'image_urls.stdres' );
        } else if ( $data['details']->data( 'image_urls.lores' ) ) {
          $data['image_url'] = $data['details']->data( 'image_urls.lores' );
        } else {
          throw new \ErrorException('Unable to get URL of image.');
        }
      } catch ( \ErrorException $e ) {
        self::bw_display_error_msg( $e );
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong getting the image URL' );
      }
      return !empty( $data['image_url'] );
    }

    /**
     * Gets the HTML used to render the blip and stores it in $the_blip.
     *
     * @param string|null $content The text from between the bracketed terms of the
     * shortcode.
     * @param bool $is_widget True if the blip is being rendered by the
     * widget; false if the blip is being rendered by a shortcode.
     */
    private static function bw_get_blip_display_blip( string &$the_blip, array $user_attributes, bool $is_widget, array $data, ?array $widget_settings = null, ?string $content = null ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $blip_ok = false;
      // Display the blip.
      try {
        // Given that all the data used to determine $style_control is passed to blipper_widget_get_styling, it might seem pointless to calculate here once and pass to that function; but this way, it's only calculated once.  I don't really know how much this affects performance.

        // Set $style_control to true if the widget settings form (default for widgets) should be used, otherwise set to false.

        // For the widget, as opposed to the shortcode, need to check whether the style control has been set or not because of, I think, the Customiser.  If it hasn't, then set $style_control to true, indicating that CSS should be used:
        // bw_log( 'is widget', $is_widget );
        // bw_log( 'style control is set', isset( $user_attributes['style-control'] ) );
        $style_control = $is_widget ? ( isset( $user_attributes['style-control'] ) ? ( $user_attributes['style-control'] === self::bw_get_default_setting_value( 'widget', 'style-control' ) ) : true ) : false;

        $the_blip = "<div" . self::bw_get_styling( 'div|blip', $is_widget, $style_control, $user_attributes ) . ">";

        $the_blip .= "<figure" . self::bw_get_styling( 'figure', $is_widget, $style_control, $user_attributes ) . ">";

        $the_blip .= self::bw_get_blip_display_blip_add_link_and_image( $user_attributes, $is_widget, $style_control, $data );

        // Display any associated data.
        $the_blip .= "<figcaption" . self::bw_get_styling( 'figcaption', $is_widget, $style_control, $user_attributes ) . ">";

        $the_blip .= self::bw_get_blip_display_blip_add_date( $user_attributes, $is_widget, $style_control, $data );

        $the_blip .= self::bw_get_blip_display_blip_add_title( $data );

        $the_blip .= self::bw_get_blip_display_blip_add_byline( $data );

        $the_blip .= self::bw_get_blip_display_blip_add_shortcode_text( $user_attributes, $is_widget, $style_control, $content );

        $the_blip .= self::bw_get_blip_display_blip_add_source( $user_attributes, $is_widget, $style_control, $data );

        $the_blip .= '</figcaption></figure>';

        $the_blip .= self::bw_get_blip_display_blip_add_descriptive_text( $user_attributes, $is_widget, $style_control, $data );

        $the_blip .= "</div>"; // .bw-blip
        $blip_ok = true;
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e, 'Something has gone wrong constructing your entry (blip)' );
      // } finally {
      //   bw_log( 'The completed blip', $the_blip );
      }
      return $blip_ok;
    }

    /**
     * Gets the HTML for the link, if it's to be added, and for the image.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_link_and_image( array $user_attributes, bool $is_widget, bool $style_control, array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = '';
      // Link back to the blip on the Blipfoto site.
      if ( ! array_key_exists( 'add-link-to-blip' , $user_attributes ) ) {
        // Necessary for when Blipper Widget is added via the Customiser
        $user_attributes['add-link-to-blip'] = self::DEFAULT_SETTING_VALUES['common']['add-link-to-blip'];
      }
      if ( $user_attributes['add-link-to-blip'] === 'show' ) {
        $the_url = self::bw_sanitise_url( 'https://www.blipfoto.com/entry/' . $data['blip']['entry_id_str'] );
        $html .= '<a href="' . $the_url . '" rel="nofollow">';
      }
      $html .= self::bw_get_blip_display_blip_add_image( $user_attributes, $is_widget, $style_control, $data );
      // Close the link (anchor) tag.
      if ( $user_attributes['add-link-to-blip'] === 'show' ) {
        $html .= '</a>';
      }
      return $html;
    }

    /**
     * Gets the HTML for the image.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_image( array $user_attributes, bool $is_widget, bool $style_control, array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = '';
      // Add the image.
      $html .= '<img src="'
        . self::bw_sanitise_url( $data['image_url'] )
        . '"'
        . self::bw_get_styling( 'img', $is_widget, $style_control, $user_attributes )
        . ' alt="'
        . $data['blip']['title']
        . '">';
      return $html;
    }

    /**
     * Gets the HTML for the date.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_date( array $user_attributes, bool $is_widget, bool $style_control, array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'user attributes', $user_attributes );

      $html = '';
      // Date (optional), title and username
      if ( $user_attributes['display-date'] === 'show' || ! empty( $data['blip']['title'] ) ) {
        $html .= "<header" . self::bw_get_styling( 'header', $is_widget, $style_control, $user_attributes ) . ">";
      }
      if ( $user_attributes['display-date'] === 'show' ) {
          $html .= date( get_option( 'date_format' ), $data['blip']['date_stamp'] );
        if ( !empty( $data['blip']['title'] ) ) {
          $html .= '<br>';
        }
      }
      return $html;
    }

    /**
     * Gets the HTML for the blip title.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_title( array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = '';
      if ( ! empty( $data['blip']['title'] ) ) {
        $html .= '<i>'
          . $data['blip']['title']
          . '</i>';
      }
      return $html;
    }

    /**
     * Gets the HTML for the blip byline.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_byline( array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = ' '
        . __( 'by', 'blipper-widget' )
        . ' '
        . $data['user']['username']
        . '</header>';
      return $html;
    }

    /**
     * Gets the HTML for the shortcode's text.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param string|null $content The text from between the shortcode's
     * bracketed components.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_shortcode_text( array $user_attributes, bool $is_widget, bool $style_control, string|null $content ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = '';
      // Display any content provided by the user in a shortcode.
      if ( !$is_widget && !empty( $content ) ) {
        $html = '<div' . self::bw_get_styling( 'div|content', $is_widget, $style_control, $user_attributes ) . '>'
          . $content
          . '</div>';
      }
      return $html;
    }

    /**
     * Gets the HTML for the source.
     *
     * The source is the journal title and 'Blipfoto'.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_source( array $user_attributes, bool $is_widget, bool $style_control, array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // Journal title and/or display-powered-by link.
      if ( ! array_key_exists( 'display-journal-title' , $user_attributes ) ) {
        // Necessary for when Blipper Widget is added via the Customiser.
        $user_attributes['display-journal-title'] = self::DEFAULT_SETTING_VALUES['common']['display-journal-title'];
      }
      if ( ! array_key_exists( 'display-powered-by' , $user_attributes ) ) {
        // Necessary for when Blipper Widget is added via the Customiser.
        $user_attributes['display-powered-by'] = self::DEFAULT_SETTING_VALUES['common']['display-powered-by'];
      }

      $html = '';
      if ( $user_attributes['display-journal-title'] === 'show' || $user_attributes['display-powered-by'] === 'show' ) {
        $html .= "<footer" . self::bw_get_styling( 'footer', $is_widget, $style_control, $user_attributes ) . ">";
        if ( $user_attributes['display-journal-title'] === 'show' ) {
            $html .= __( 'From', 'blipper-widget' )
              . ' <a href="https://www.blipfoto.com/'
              . $data['user_attributes']->data( 'username' )
              . '" rel="nofollow"' . self::bw_get_styling( 'link', $is_widget, $style_control, $user_attributes ) . '>'
              . $data['user_attributes']->data( 'journal_title' )
              . '</a>';
        }
        if ( $user_attributes['display-journal-title'] === 'show' && $user_attributes['display-powered-by'] === 'show' ) {
          $html .= ' | ';
        }
        $html .= self::bw_get_blip_display_blip_add_powered_by( $user_attributes, $is_widget, $style_control );
        $html .= '</footer>';
      }
      return $html;
    }

    /**
     * Gets the HTML for the powered-by link to Blipfoto.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_powered_by( array $user_attributes, bool $is_widget, $style_control ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = '';
      if ( $user_attributes['display-powered-by'] === 'show' ) {
        $html = 'Powered by <a href="https://www.blipfoto.com/" rel="nofollow"' . self::bw_get_styling( 'link', $is_widget, $style_control, $user_attributes ) . '>Blipfoto</a>';
      }
      return $html;
    }

    /**
     * Gets the HTML for the descriptive text that the blipper may have added
     * to the blip.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $user_attributes The user-set display settings for the blip.
     * @param bool $is_widget True if the HTML is required for the widget;
     * false if it is required for the shortcode.
     * @param bool style_control True of CSS should be used to style the
     * widget version of the blip; false if the widget style settings should
     * be used.
     * @param array<string, mixed> $data The data that represents the blip.
     * @return string The HTML needed to display the image along with the
     * link, if specified in the settings.
     */
    private static function bw_get_blip_display_blip_add_descriptive_text( array $user_attributes, bool $is_widget, bool $style_control, array $data ): string {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $html = empty( $data['descriptive_text'] ) ? '' : '<div' . self::bw_get_styling( 'div|desc-text', $is_widget, $style_control, $user_attributes ) . ">"
        . $data['descriptive_text']
        . '</div>';
      return $html;
    }

    /**
     * Returns the class or style attributes (and their values) used to style the
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
     * @param    array      $user_attributes        The user-defined settings containing
     *                                         the style data
     */
    private static function bw_get_styling( $element, $is_widget, bool $style_control, $user_attributes ) {
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
          ( ' style=\'' .  self::bw_get_style( $user_attributes, 'border-style')
            . self::bw_get_style( $user_attributes, 'border-width')
            . self::bw_get_style( $user_attributes, 'border-color')
            . '\'' );
        case 'figure':
        return $use_class ?
          ( ' class=\'bw-figure\'' ) :
          ( ' style=\'' . self::bw_get_style( $user_attributes, 'background-color' )
            . self::bw_get_style( $user_attributes, 'padding' ) . '\'' );
        case 'img':
        return $use_class ?
          ( ' class=\'bw-image\'' ) :
          ( ' style=\'margin:auto;\'' );
        case 'figcaption':
        return $use_class ?
          ( ' class=\'bw-caption\'' ) :
          ( ' style=\'padding-top:7px;line-height:2;'
            . self::bw_get_style( $user_attributes, 'color' )
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
            . self::bw_get_style( $user_attributes, 'link-color' )
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
     * Sanitises third-party HTML.
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

      // bw_log( 'dirty html', $html );
      // bw_log( 'clean html', wp_kses( $html, self::BW_ALLOWED_HTML ) );

      return wp_kses( $html, self::BW_ALLOWED_HTML );
    }

    /**
     * Sanitises URL.
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
      * Displays the blip using the settings stored in the database.
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
    private function bw_display_blip( $user_attributes, $is_widget, $content = null ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      return self::bw_get_blip(
        user_attributes: $user_attributes,
        is_widget: $is_widget,
        content: $content
      );
    }

    /**
      * Displays the back-end widget form.
      *
      * @since     0.0.1
      * @access    private
      * @param     array         $settings       The settings saved in the database
      */
    private function bw_display_form( array $settings ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $oauth_settings = Blipper_Widget_Settings::bw_get_settings();

      if ( empty( $oauth_settings['username'] ) || empty( $oauth_settings['access-token'] ) ) {

        echo '<p>You need to set the Blipfoto settings on <a href="' . esc_url( admin_url( 'options-general.php?page=blipper-widget' ) ) . '">the Blipper Widget settings page</a> to continue.</p>';
        return false;

      } else {

        $this->bw_display_form_display_settings( $settings );
        $this->bw_display_form_styling_settings( $settings );
        return true;

      }
    }

    private function bw_display_form_display_settings( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
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
      <?php
      $this->bw_display_form_display_title( $settings );
      $this->bw_display_form_display_date( $settings );
      $this->bw_display_form_display_link_to_blip( $settings );
      $this->bw_display_form_display_journal_title( $settings );
      $this->bw_display_form_display_powered_by( $settings );
    }

    private function bw_display_form_styling_settings( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        <?php
        $this->bw_display_form_styling_border_style( $settings );
        $this->bw_display_form_styling_border_width( $settings );
        $this->bw_display_form_styling_border_colour( $settings );
        $this->bw_display_form_styling_background_colour( $settings );
        $this->bw_display_form_styling_text_colour( $settings );
        $this->bw_display_form_styling_link_colour( $settings );
        $this->bw_display_form_styling_padding( $settings );
        ?>
      </div>
      <?php
    }

    private function bw_display_form_display_title( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
      <div>
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_display_date( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // error_log( 'display date: ' . var_export( $settings['display-date'], true ) );
      // error_log( 'checked: ' . var_export( checked( 'show', esc_attr( $settings['display-date'] ), false ), true ) );
      ?>
      <div class="option">
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_display_link_to_blip( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      ?>
      <div class="option">
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_display_journal_title( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
      <div class="option">
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_display_powered_by( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
      <div class="option">
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_border_style( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
      <div class="option">
        <p class="description">
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_border_width( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_border_colour( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        <?php // bw_log( 'border color', esc_attr( $settings['border-color'] ) ); ?>
        </p>
        <p class="description">
          Pick a colour for the widget border colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme.  If you pick a colour, including the default colour, that colour will be used instead.
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_background_colour( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        <?php // bw_log( 'background color', esc_attr( $settings['background-color'] ) ); ?>
        </p>
        <p class="description">
          Pick a colour for the widget background colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme.  If you pick a colour, including the default colour, that colour will be used instead.
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_text_colour( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        <?php // bw_log( 'color', esc_attr( $settings['color'] ) ); ?>
        </p>
        <p class="description">
          Pick a colour for the widget text colour.  If you don't pick a colour or you delete the colour, the colour will be that defined by your theme; the link text will be the same colour as the surrounding text.  If you pick a colour, including the default colour, that colour will be used instead.
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_link_colour( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        <?php // bw_log( 'link color', esc_attr( $settings['link-color'] ) ); ?>
        </p>
        <p class="description">
          Pick a colour for the widget link colour.  If you pick a colour, including the default colour, that colour will be used instead.
        </p>
      </div>
      <?php
    }

    private function bw_display_form_styling_padding( array $settings ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      ?>
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
        </p>
      </div>
      <?php
    }

    /**
     * Gets the default value for the given setting.
     *
     * @since <1.2.6
     * @author pandammonium
     *
     * @param string $setting_type Indicates what type of setting it is; one of
     * 'widget', 'shortcode', 'common'.
     * @param string $setting The setting whose default value is to be
     * obtained.
     * @param bool $is_colour Colours need to be handled differently. True if
     * the setting is a colour; otherwise false. Default is false.
     * @return mixed The default value of the given setting.
     */
    private static function bw_get_default_setting_value( $setting_type, $setting, $is_colour = false ): mixed {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( array_key_exists( $setting_type, self::DEFAULT_SETTING_VALUES ) ) {

        if ( array_key_exists( $setting, self::DEFAULT_SETTING_VALUES[$setting_type] ) ) {

          if ( $is_colour ) {
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
          // bw_log( 'Default ' . $setting_type . ' ' . $setting, $default_setting );
          return $default_setting;

        } else {
          throw new \Exception( 'Invalid setting ' . var_export( $setting, true ) );
        }

      } else {
        throw new \Exception( 'Invalid setting type ' . var_export( $setting_type, true ) );
      }
    }

    private static function bw_get_style( $settings, $style_element ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $element = $style_element;

      try {
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
            $style = '';
            if ( array_key_exists( $style_element, $settings ) ){
              if ( empty( $settings[$style_element] ) ) {
                $style = $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . ';';
              } else {
                $style = $element . ':' . $settings[$style_element];
                if ( is_numeric( $settings[$style_element] ) ) {
                  $style .= 'px';
                }
                $style .= ';';
              }
            } else {
              $style = $element . ':' . self::bw_get_default_setting_value( 'widget', $style_element ) . 'px' . ';';
            }
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
      } catch ( \Exception $e ) {
        self::bw_display_error_msg( $e );
      }
    }

    private static function bw_get_the_blip_title( array $user_attributes ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $the_blip_title = '';
      if ( array_key_exists( 'title', $user_attributes ) ) {
        $the_blip_title = $user_attributes['title'];
        // error_log( 'the blip title (from settings): ' . var_export( $the_blip_title, true ) );
      } else {
        // error_log( 'the blip title (using empty string): ' . var_export( $the_blip_title, true ) );
      }
      return $the_blip_title;
    }

    private static function bw_get_styled_title( array $user_attributes, ?array $widget_settings = null ) {
      $styled_title = '';
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $styled_title = '';
      if ( array_key_exists( 'title', $user_attributes ) ) {
        $the_blip_title = $user_attributes['title'];
        if ( !empty( $user_attributes['title'] ) ) {
          if ( empty( $widget_settings ) ) {
            if ( ! ( $user_attributes['title-level'] === 'h1' ||
                     $user_attributes['title-level'] === 'h2' ||
                     $user_attributes['title-level'] === 'h3' ||
                     $user_attributes['title-level'] === 'h4' ||
                     $user_attributes['title-level'] === 'h5' ||
                     $user_attributes['title-level'] === 'h6' ||
                     $user_attributes['title-level'] === 'p' ) ) {
              $user_attributes['title-level'] = self::bw_get_default_setting_value( 'shortcode', 'title-level' );
            }
            $styled_title = '<' . $user_attributes['title-level'] . '>' . apply_filters( 'widget_title', $the_blip_title ) . '</' . $user_attributes['title-level'] . '>';
          } else {
            $styled_title = $widget_settings['before_title'] . apply_filters( 'widget_title', $the_blip_title ) . $widget_settings['after_title'];
          }
        } else {
          $styled_title = $the_blip_title;
        }
      }
      // bw_log( 'Styled title', $styled_title );
      return $styled_title;
    }

    // --- Output: logging, error tracking and debugging ------------------- //

    /**
     * Logs the value of the given display element. If there is none, the
     * default is logged instead.
     *
     * @since <1.2.6
     * @deprecated 1.2.6 Overly complicated.
     */
    private static function bw_log_display_values( $settings, $display_element, $function_name ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      _deprecated_function( __METHOD__, '1.2.6' );

      // $message =
      //   array_key_exists( $display_element, $settings )
      //   ? ( empty( $settings[$display_element] )
      //     ? ( "key has no value; using default (widget): " . self::bw_get_default_setting_value( 'widget', $display_element ) )
      //     : ( $settings[$display_element] )
      //     )
      //   : ( "No key, no value; adding default (common): " . self::bw_get_default_setting_value( 'common', $display_element ) );
      // // bw_log( $display_element, $message );
    }

    /**
     * Displays an error message after an exception was thrown.
     *
     * @param    \Exception $e                  The exception object
     * containing information
     *                                 about the error
     * @param    string $additional_info    Extra information to help the
     * user.
     * @param    bool $request_limit_reached True if the Blipfoto request
     * limit has been reached; otherwise false.
     * @param bool $write_to_log True to write the error to the log file as
     * well; false not to. Default is false.
     * @since    1.1.1
    */
    private static function bw_display_error_msg( \Throwable $e, string $additional_info = '', bool $request_limit_reached = false, bool $write_to_log = true ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( $request_limit_reached  ) {
        bw_log( 'Blipfoto request limit reached', $request_limit_reached );
      }
      // bw_log( 'Throwable class: ' . self::bw_get_throwable_class( $e ), null, false, false );

      if ( current_user_can( 'manage_options' ) ) {
        self::bw_display_private_error_msg( $e, $additional_info, $request_limit_reached );
      } else {
        self::bw_display_public_error_msg( $request_limit_reached );
      }

      if ( $write_to_log ) {
        bw_exception( $e );
      }
    }


    /**
     * Displays an error message for a user that can manage options.
     *
     * @param    \Exception $e                  The exception object
     * containing information
     *                                 about the error
     * @param    string $additional_info    Extra information to help the
     * user.
     * @param    bool $request_limit_reached True if the Blipfoto request
     * limit has been reached; otherwise false.
     * @since    1.1.1
      */
    private static function bw_display_private_error_msg( \Throwable $e, string $additional_info = '', bool $request_limit_reached = false ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $exception_class = __( self::bw_get_throwable_class( $e ), 'blipper-widget' );
      $code = ' (' . $e->getCode() . ')';
      $start = '<p>Blipper Widget <span class=\'' . self::bw_get_css_error_classes( $e ) . '\'>' . $exception_class . $code . '</span>: ';
      $message = __( htmlspecialchars( $e->getMessage() ), 'blipper-widget' );
      $additional_info = empty( $additional_info ) ? '' : (' ' . __( $additional_info . '.', 'blipper-widget' ));
      $request_limit_info = ( $request_limit_reached ? __( ' Please try again in 15 minutes.', 'blipper-widget' ) : '' );
      $end = '</p>';

      echo $start . $message . $additional_info . $request_limit_info . $end;
    }

    /**
     * Displays an error message for a user that cannot manage options.
     *
     * @since    1.1.1
     *
     * @param    bool $request_limit_reached True if the Blipfoto request
     *  limit has been reached; otherwise false.
     */
    private static function bw_display_public_error_msg( bool $request_limit_reached = false ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      if ( $request_limit_reached ) {
        // Translators: do not translate Blipfoto: it's the name of a service.
        echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' .  __( 'The Blipfoto request limit has been reached. Please try again in 15 minutes.', 'blipper-widget' ) . '</p>';
      } else {
        echo '';
        if ( current_user_can( 'manage_options' ) ) {
          echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' . __( 'There is a problem with Blipper Widget or a service it relies on. Please check your settings and try again. If your settings are ok, try again later. If it still doesn\'t work, please consider <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem, and include any useful information from the WordPress error log.', 'blipper-widget' ) . '</p>';
        } else {
          echo '<p class="' . self::bw_get_css_class( 'error' ) . '">' . __( 'There is a problem with Blipper Widget or a service it relies on. Please try again later; if it still doesn\'t work, please consider informing the owner of this website or <a href="https://github.com/pandammonium/blipper-widget/issues" rel="nofollow noopener noreferrer external">adding an issue to Blipper Widget on GitHub</a>. If you do add an issue on GitHub, please give instructions to reproduce the problem, and include any useful information from the WordPress error log.', 'blipper-widget' ) . '</p>';
        }
      }
    }

    /**
     * Displays a message based on the exception class.
     */
    private static function bw_get_throwable_class( $e ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // error_log( 'class: ' . var_export( get_class( $e ), true ) );

      switch ( get_class( $e ) ) {
        case 'Blipfoto\Exceptions\ApiResponseException':
        return 'Blipfoto API response error';
        case 'Blipfoto\Exceptions\BaseException':
        return 'Blipfoto error';
        case 'Blipfoto\Exceptions\FileException':
        return 'File error';
        case 'Blipfoto\Exceptions\InvalidResponseException':
        return 'Invalid response';
        case 'Blipfoto\Exceptions\NetworkException':
        return 'Network error';
        case 'Blipfoto\Exceptions\OAuthException':
        return 'OAuth error';
        case 'ErrorException':
        case 'Error':
        return 'Error';
        case 'InvalidArgumentException':
        case 'LengthException':
        case 'Exception':
        default:
        return 'Warning';
        }
    }

    /**
     * Uses the exception class to get appropriate CSS classes.
     */
    private static function bw_get_css_error_classes( $e ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      switch ( get_class( $e ) ) {
        case 'Blipfoto\Exceptions\BaseException':
        case 'Blipfoto\Exceptions\ApiResponseException':
        case 'Blipfoto\Exceptions\FileException':
        case 'Blipfoto\Exceptions\InvalidResponseException':
        case 'Blipfoto\Exceptions\NetworkException':
        case 'Blipfoto\Exceptions\OAuthException':
        case 'ErrorException':
        return self::bw_get_css_class( 'error' );
        case 'Exception':
        return self::bw_get_css_class( 'warning' );
        default:
          // error_log( 'exception class: ' . var_export( get_class( $e ), true ) );
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
        return __( $type . ' ' . 'blipper-widget-' . $type, 'blipper-widget' );
        default:
          // Unrecognised type.
          // bw_log( 'CSS class type', $type );
        return '';
      }
    }

    // --- Action hooks ---------------------------------------------------- //

    /**
     * Does stuff that needs doing if a widget setting is changed in the
     * backend.
     *
     * The backend widget settings are access from Appearance > Widgets >
     * Blipper Widget.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param array $new_widget_settings The new settings for the widget.
     * @param string $widget_id The widget ID (e.g., text, recent-posts,
     * etc.).
     * @param array $widget_args The arguments passed to the widget.
     */
    private function bw_on_widget_setting_change_in_backend( array $new_widget_settings, string $widget_id, array $widget_args ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // error_log( 'widget id: ' . var_export( $this->id, true ) );
      // error_log( 'widget id (arg): ' . var_export( $widget_id, true ) );

      $widget_settings = [];
      $result = $this->bw_get_widget_settings( $widget_id, $id_base, $widget_settings );
      if ( false !== $result ) {
        foreach ( $widget_settings as $setting ) {
          // Check if it's a widget setting:
          if ( 0 === strpos( $setting->id, 'widget_' ) ) {
            // error_log( 'widget setting: ' . var_export( $setting, true ) );
            // // Do something with the widget settings:
            // $new_value = $setting->value(); // Get the new value
            // // Process the new value as needed
          }
        }
      }
    }

    public static function bw_on_widget_setting_change_in_customiser( $arg ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'Called from', current_filter() );
    }

    public function bw_on_delete_widget_from_customiser( $wp_customize ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'type of argument', gettype( $wp_customize ) );
      // bw_log( 'argument is empty', empty( $wp_customize ) ? 'yes' : 'no' );
      // bw_log( 'Called from', current_filter() );

      // error_log( 'widget id: ' . var_export( $this->id, true ) );

      // // Get the old and new settings:

      // $old_widget_settings = get_option( 'old_widget_settings_' . $this->id_base );
      // // ksort( $old_widget_settings );
      // error_log( 'old widget settings: ' . var_export( $old_widget_settings, true ) );
      // $new_widget_settings = get_option( 'widget_' . $this->id_base );
      // // ksort( $new_widget_settings );
      // error_log( 'new widget settings: ' . var_export( $new_widget_settings, true ) );

      // // Check if the widget settings have changed:
      // if ( $old_widget_settings === $new_widget_settings ) {
      //   // error_log( 'the new settings are the same as the old settings' );
      // } else {
      //   // error_log( 'the new widget settings are different to the old widget settings' );

      //   // Compare the old and new settings to detect widget deletion
      //   $old_widget_settings = maybe_unserialize( $old_widget_settings );
      //   // error_log( 'old widget settings (maybe unserialised): ' . var_export( $old_widget_settings, true ) );
      //   $new_widget_settings = maybe_unserialize( $new_widget_settings );
      //   // error_log( 'new widget settings (maybe unserialised): ' . var_export( $new_widget_settings, true ) );

      // //   if ( is_array( $old_widget_settings ) && is_array( $new_widget_settings ) ) {
      // //     // error_log( 'old and new widget settings are arrays' );
      // //     // Find widgets that are in the old settings but not in the new settings
      // //     $deleted_widgets = array_diff_key( $old_widget_settings, $new_widget_settings );
      // //     // error_log( 'deleted widgets: ' . var_export( $deleted_widgets, true ) );

      // //     if ( !empty( $deleted_widgets ) ) {
      // //       // Widgets have been deleted, perform your actions here
      // //       foreach ( $deleted_widgets as $widget_id => $widget_data ) {
      // //         // Perform actions for each deleted widget
      // //         // For example, you can log the widget ID
      // //         // error_log( 'deleted widget id: ' . $widget_id );
      // //         // error_log( 'deleted widget data: ' . $widget_data );
      // //       }
      // //     } else {
      // //       // error_log( 'no deleted widgets' );
      // //     }
      // //   } else {
      // //     // error_log( 'old/new widgets is not an array' );
      // //   }
      // }
      // // // Clear the stored old widget settings
      // // delete_option( 'old_widget_settings_' . $this->id_base );

      // // ------------------------------------------------------------------- //

      // // // ChatGPT

      // // // Check if the widget settings exist
      // // // $widgets = get_option( 'sidebars_widgets' );
      // // $widgets = wp_get_sidebars_widgets();
      // // // Loop through the widget areas
      // // foreach ( $widgets as $sidebar => $widget_ids ) {
      // //   // error_log( $sidebar . ' ' . var_export( $widget_ids, true ) );
      // //   // Check if the sidebar is the one you are interested in
      // //   if ( 'wp_inactive_widgets' === $sidebar ) {
      // //     // Loop through the widget IDs
      // //     foreach ( $widget_ids as $widget_id ) {
      // //       // Check if the widget is deleted
      // //       if ( ! isset( $wp_customize->settings[ 'widget_' . $widget_id ] ) ) {
      // //         // Clean up associated settings or data
      // //         // For example, delete the widget's options
      // //         delete_option( $widget_id );
      // //       }
      // //     }
      // //   }
      // // }
      // // ------------------------------------------------------------------- //

      // // // Llama

      // // // Get the current widget instances
      // // $current_widgets = wp_get_sidebars_widgets();
      // // // $current_widgets = get_option( 'sidebars_widgets' );
      // error_log( 'current widgets: ' . var_export( $current_widgets, true ) );

      // // // Get the previous widget instances (e.g. from a transient or option)
      // // $previous_widgets = get_transient( 'my_widget_instances' );
      // error_log( 'previous widgets: ' . var_export( $previous_widgets, true ) );

      // // // If there are no previous widget instances, set the current instances as the previous instances
      // // if ( empty( $previous_widgets ) ) {
      // //   set_transient( 'my_widget_instances', $current_widgets );
      // //   return;
      // // }

      // // // Loop through the previous widget instances to find deleted widgets
      // // foreach ( $previous_widgets as $sidebar_id => $widgets ) {
      // //   foreach ( $widgets as $widget_id ) {
      // //     // Check if the widget instance has been deleted
      // //     if ( !isset( $current_widgets[$sidebar_id] ) || !in_array( $widget_id, $current_widgets[$sidebar_id] ) ) {
      // //       // Remove any unnecessary data associated with the deleted widget instance
      // //       // For example, you can remove any custom metadata or settings
      // //       delete_option( 'my_widget_setting_' . $widget_id );
      // //     }
      // //   }
      // // }

      // // // Update the previous widget instances
      // // set_transient( 'my_widget_instances', $current_widgets );
    }

    /**
     * Deletes all the Blipper Widget widgets from Appearance > Widgets on
     * pressing the Clear Inactive Widgets button.
     *
     * @since 1.2.6
     * @author pandammonium
     */
    public function bw_on_delete_inactive_widgets_from_backend() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      check_ajax_referer('delete-inactive-widgets-nonce', 'nonce');

      // Get the IDs of the inactive widgets
      $inactive_widget_ids = self::get_inactive_widget_ids();
      foreach ( $inactive_widget_ids as $widget_id ) {
        // error_log( 'inactive widget id: ' . $widget_id );
        $result = $this->bw_on_delete_widget_from_backend( $widget_id, 'wp_inactive_widgets', $this->id_base );
        // error_log( 'inactive widget ' . var_export( $widget_id, true ) . ' cleaned up: ' . var_export( $result, true ) );
      }

      // Send a response back to the client
      wp_send_json_success( 'Custom action executed successfully.' );
    }

    /**
     * Gets the widgets that are in Appearance > Widgets > Inactive Widgets.
     *
     * @since 1.2.6
     * @author pandammonium
     *
     * @return array An array containing the IDs of any inactive widgets. If
     * there aren't any, the array will be empty.
     */
    public static function get_inactive_widget_ids(): array {
      // Get all sidebar widgets
      $sidebars_widgets = wp_get_sidebars_widgets();

      // Check if the inactive widgets sidebar exists:
      if ( isset( $sidebars_widgets['wp_inactive_widgets'] ) ) {
          // Return the widget IDs in the inactive widgets sidebar:
          return $sidebars_widgets['wp_inactive_widgets'];
      }
      // Return an empty array if the inactive widgets sidebar does not exist:
      return [];
    }

    /**
     * Removes data that doesn't need storing if an instance of the widget is
     * removed.
     *
     * This is done in two stages. First, the cache, if there is one, is
     * deleted. Second, the widget settings are removed.
     *
     * @author pandammonium
     * @since 1.2.6
     *
     * @param string $widget_id The identifier of this instance of the widget.
     * @param string sidebar_id The identifier of the sidebar on which the
     * widget was placed.
     * @param ?string $id_base The identifier of the Blipper Widget widget.
     * Default: null.
     * @return bool True if the given widget is absent from the cache and the
     * database. False if it could not be removed or if the widget is not a
     * Blipper Widget widget.
     */
    public function bw_on_delete_widget_from_backend( string $widget_id, string $sidebar_id, ?string $id_base = null ): bool {
      bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $deleted = false;
      $cache_is_clean = false;
      $option_is_gone = false;

      $needle = $this->id_base;
      if ( str_starts_with( haystack: $widget_id, needle: $needle ) ) {

        $widget_settings = [];
        $result = $this->bw_get_widget_settings( $widget_id, $id_base, $widget_settings );
        // error_log( 'widget settings (' . var_export( $result, true ) . '): ' . var_export( $widget_settings, true ) );
        if ( false === $result ) {
          // An error occurred; cannot delete cache (if it exists).
        } else {
          if ( empty( $widget_settings ) ) {
            // Report success because there not being any settings isn't a failure:
            $cache_is_clean = true;
          } else {
            self::$cache_key = self::bw_get_a_cache_key( $widget_settings );
            if ( false === self::bw_get_cache( self::$cache_key ) ) {
              // bw_log( 'Cache not found', self::$cache_key );
              // If there's no cached blip, then there's nothing to tidy up:
              $cache_is_clean = true;
            } else {
              $cache_is_clean = self::bw_delete_cache( self::$cache_key );
              // error_log( 'deleted cache key ' . var_export( self::$cache_key, true ) . ': ' . var_export( $cache_is_clean, true ) );
            }
          }
          // bw_log( 'Widget ' . var_export( $widget_id, true ) . ' on sidebar ' . var_export( $sidebar_id, true ) . ' cache cleaned', $cache_is_clean );
        }

        if ( 'wp_inactive_widgets' === $sidebar_id ) {
          // bw_log( 'Widget ' . var_export( $widget_id, true ) . ' is inactive', includes_data: false );
          // Report success because the widget isn't on a sidebar to be deleted from:
          $option_is_gone = true;
        } else {
          if ( empty( $widget_settings ) ) {
            // bw_log( 'Widget ' . var_export( $widget_id, true ) . '\'s settings not found', includes_data: false );
            // Report success because there not being any settings isn't a failure:
            $option_is_gone = true;
          } else {
            unset( $widget_settings[$widget_id] );
            // Why update option rather than delete it?
            $option_is_gone = update_option( 'widget_' . $id_base, $widget_settings );
            // $option_is_gone = delete_option( $widget_id, true );
          }
        }
        // bw_log( 'Widget ' . var_export( $widget_id, true ) . ' on sidebar ' . var_export( $sidebar_id, true ) . ' settings cleaned', $option_is_gone );
      } else {
        // error_log( 'widget ' . var_export( $widget_id, true ) . ' is not a blipper widget' );
      }

      $deleted = $cache_is_clean && $option_is_gone;
      // bw_log( 'Cleaned up widget ' . var_export( $widget_id, true ), $deleted );
      return $deleted;
    }

    /**
     * Gets the settings of the Blipper Widget widget specified by the widget
     * identifier.
     *
     * Depending on whether the widget settings were set in the constructor – using parent::set_settings() – or not, parent::get_settings() will return either a whole load of
     *
     * @param string widget_id The identifier of the widget whose settings
     * are to be obtained.
     * @param string id_base A prefix indicating the type of widget to get.
     * Should be 'blipper_widget' for Blipper Widget widgets.
     * @param array &$widget_settings The array in which the widget settings
     * are returned.
     *
     * @return bool True on success, which includes not being able to find
     * the settings; false on failure.
     */
    private function bw_get_widget_settings( string $widget_id, string $id_base, array &$widget_settings ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $all_widget_settings = parent::get_settings();
      // error_log( 'all widget settings: ' . var_export( $all_widget_settings, true ) );

      try {
        // Get the settings for this widget. Start by finding the array key from the widget id:
        $key_string = str_replace( $id_base . '-', '', $widget_id );
        // error_log( 'key string: ' . var_export( $key_string, true ) );
        $widget_key = (int)$key_string;
        // error_log( 'widget key: ' . var_export( $widget_key, true ) );
        // Casting a string that doesn't start with a digit returns 0, which is a valid array index. Therefore, need to distinguish between a genuine zero and a zero caused by an unexpected error, although it's likely that zero will never be a valid value because the id numbering typically seems to start at one – but you can't be too careful:
        if ( 0 === $widget_key && '0' !== $key_string ) {
          throw new \LogicException( 'Unexpected widget id' );
        }
        // error_log( 'widget ' . var_export( $widget_key, true ) . ' settings: ' . var_export( $all_widget_settings[$widget_key], true ) );
        if ( empty( $all_widget_settings[ $widget_key ] ) ) {
          // bw_log( 'Settings not found for widget', $widget_id );
          return true;
        } else {
          $widget_settings = $all_widget_settings[ $widget_key ];
          return true;
        }
      } catch ( \LogicException $e ) {
        self::bw_display_error_msg( $e, write_to_log: false );
        $widget_settings = [];
        return false;
      }
    }

    /**
     * Checks the Blipfoto OAuth settings have been set, otherwise displays a
     * message to the user.
     *
     * @deprecated 1.2.6 Superseded by improved error handling.
     */
    public static function bw_settings_check(): void {
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
     * Adds the WP colour picker.
     *
     * @deprecated 1.2.6 Use bw_enqueue_scripts() instead.
     */
    public static function bw_load_colour_picker(): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
    }

    public static function bw_enqueue_scripts( $hook_suffix ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $delete_inactive_widgets_script = plugin_dir_url(__FILE__) . '../includes/js/delete-inactive-widgets.js';
      // error_log( 'delete inactive widgets script: ' . var_export( $delete_inactive_widgets_script, true ) );
      wp_enqueue_script(
        'delete-inactive-widgets',
        $delete_inactive_widgets_script,
        array('jquery'),
        null,
        true
      );
      wp_localize_script('delete-inactive-widgets', 'delete_inactive_widgets_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('delete-inactive-widgets-nonce')
      ));


      if ( 'widgets.php' === $hook_suffix ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
      }
    }

    /**
     * Prints the Javascript scripts needed by the widget settings form.
     *
     * @since 0.0.5
     */
    public static function bw_print_scripts(): void {
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

    public static function bw_save_old_customiser_settings() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );
      // bw_log( 'Called from', current_filter() );

      // // Get the current old option, if there is one:
      // $old_option = get_option( BW_DB_WIDGET_OLD_SETTINGS );
      // ksort( $old_option );
      // error_log( 'old ' . BW_DB_WIDGET_OLD_SETTINGS . ': ' . var_export( $old_option, true ) );

      // // Store the current widget settings
      // $current_option = get_option( 'widget_' . BW_ID_BASE );
      // ksort( $current_option );
      // error_log( 'current option: ' . var_export( $current_option, true ) );
      // $updated = update_option(
      //   option: BW_DB_WIDGET_OLD_SETTINGS,
      //   value: $current_option
      // );
      // error_log( 'updated ' . BW_DB_WIDGET_OLD_SETTINGS . ': ' . var_export( $updated, true ) );
      // if ( $updated ) {
      //   // error_log( var_export( get_option( BW_DB_WIDGET_OLD_SETTINGS ) ) );
      // }
    }

    public function bw_update_option( ?array $bw_option = null ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $option_name = $this->option_name;

      // error_log( 'widget id: ' . var_export( $this->id, true ) );
      $key_string = str_replace( $this->id_base . '-', '', $this->id );
      // error_log( 'key string: ' . var_export( $key_string, true ) );
      // $widget_key = (int)$key_string;
      // error_log( 'widget key: ' . var_export( $widget_key, true ) );

      // $settings = [
      //   $widget_key => $option ?? self::bw_get_default_attributes( true ),
      // ];
      // error_log( 'going to set widget ' . $this->id . ' settings to: ' . var_export( $settings, true ) );


      $widget_key = (int) $key_string; // Ensure it's an integer
      // error_log( 'widget key: ' . var_export( $widget_key, true ) . ' (' . gettype( $widget_key ) . ')' );
      $settings_array = $bw_option ?? self::bw_get_default_attributes( true );
      // error_log( 'going to set widget ' . $this->id . ' settings to: ' . var_export( $settings_array, true ) );

      $option_value = [ $widget_key => $settings_array ];
      $option_value = array_combine(array_map('intval', array_keys($option_value)), array_values($option_value));
      // error_log( 'setting widget ' . $this->id . ' settings to: ' . var_export( $option_value, true ) );

      $result = update_option($option_name, $option_value);
      // error_log( 'update option: ' . var_export( $result, true ) );

      // $option = get_option($option_name);
      // error_log('get_option( $option ): ' . var_export($option, true));

      // $option = get_option($option_name, false);
      // error_log('get_option( $option, false ): ' . var_export($option, true));

      // 1. Remove all filters:
      // remove_all_filters('pre_option_' . $option_name);
      // remove_all_filters('option_' . $option_name);
      // $option = get_option($option_name);
      // error_log(var_export($option, true));

      // 2. Remove widget options filtering (widget system interference)
      $option = get_option($option_name, false);
      // error_log('raw option data: ' . var_export($option, true));
      // $unfiltered_option = wp_cache_get($option_name, 'options');
      // error_log('uncached option data: ' . var_export($unfiltered_option, true));

      // $result = update_option( $option_name, $settings );
      // $result = delete_option( $option_name );
      // $result = $result && add_option( $option_name, $settings );

      // global $wpdb;
      // $option_value = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'widget_blipper_widget'" );
      // error_log( 'direct from the database: ' .  var_export( maybe_unserialize( $option_value ), true ) );

      // error_log( 'updated widget ' . $this->id . ' ' . $option_name . ': ' . var_export( $result, true ) );
      // $saved_option = 'unset';
      // // $saved_options = get_option( $option_name );
      // $saved_options = get_option($option_name);
      // error_log('get option: ' . var_export($saved_options, true));

      // if (!isset($saved_options[$widget_key])) {
      //     // error_log('Key [' . $widget_key . '] does not exist!');
      // } elseif (empty($saved_options[$widget_key])) {
      //     // error_log('Key [' . $widget_key . '] exists but is empty!');
      // } else {
      //     // error_log('Key [' . $widget_key . '] has data: ' . var_export($saved_options[1], true));
      // }

      // if (isset($saved_options[(string)$widget_key])) {
      //     // error_log('Key ' . (string)$widget_key . ' exists as a string: ' . var_export($saved_options[(string)$widget_key], true));
      // }

      // error_log( 'saved option ' . $this->id . ' ' . $option_name . ': ' . var_export( $saved_option, true ) ) ;

      // remove_all_filters('option_' . $option_name);
      // $unfiltered_option = get_option($option_name);
      // error_log( 'UNFILTERED OPTION ' . $this->id . ' ' . $option_name . ': ' . var_export( $unfiltered_option, true ) ) ;

      // $settings_array = array_map('intval', array_keys($option_value)); // Ensure values are integers
      // update_option($option_name, [$widget_key => $settings_array]);
      // $option_int = get_option($option_name);
      // error_log('key type: ' . gettype($widget_key) . '; ' . var_export($option_int, true));
    }

    /**
     * Adds all the hooks, filters and shortcodes that Blipper Widget needs.
     *
     * @author pandammonium
     * @since 1.2.6
     */
    public function add_hooks_and_filters(): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // function to load Blipper Widget:
      // add_action( 'admin_notices', [ self::class, 'bw_settings_check' ] );
      // add_action( 'load-widgets.php', [ self::class, 'bw_load_colour_picker' ] );

      if ( self::$hooks_and_filters_added ) {
        // error_log( 'hooks and filters already added' );
      } else {
        add_action(
          hook_name: 'admin_enqueue_scripts',
          callback: [ self::class, 'bw_enqueue_scripts' ]
        );

        add_action(
          hook_name: 'admin_footer-widgets.php',
          callback: [ self::class, 'bw_print_scripts' ],
          priority: 9999
        );

        add_action(
          hook_name: 'customize_save_validation_before',
          callback: [ self::class, 'bw_save_old_customiser_settings' ],
          accepted_args: 0
        );

        add_action(
          hook_name: 'customize_publish_after',
          callback: [ $this, 'bw_on_delete_widget_from_customiser' ],
          priority: 9999,
          accepted_args: 1
        );

        add_action(
          hook_name: 'customize_save_after',
          callback: [ $this, 'bw_on_delete_widget_from_customiser' ],
          priority: 9999,
          accepted_args: 1
        );

        add_action(
          hook_name: 'delete_widget',
          callback: [ $this, 'bw_on_delete_widget_from_backend' ],
          accepted_args: 3
        );

        add_action(
          hook_name: 'pre_post_update',
          callback: [ self::class, 'bw_save_old_shortcode_attributes' ]
        );

        add_action(
          hook_name: 'updated_widget',
          callback: [ $this, 'bw_on_widget_setting_change_in_backend' ],
          accepted_args: 3
        );

        // add_action(
        //   hook_name: 'widgets_init',
        //   callback: [ $this, 'bw_update_option' ],
        //   accepted_args: 1
        // );

        add_action(
          hook_name: 'wp_ajax_bw_on_delete_inactive_widgets_from_backend',
          callback: [ $this, 'bw_on_delete_inactive_widgets_from_backend' ]
        );

        add_shortcode(
          tag: 'blipper_widget',
          callback: [ self::class, 'bw_shortcode_blip_display' ]
        );

        // Repeating the above hooks with debug info ------------------------ //

        add_action(
          hook_name: 'admin_enqueue_scripts',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 1
        );

        add_action(
          hook_name: 'admin_footer-widgets.php',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 1
        );

        add_action(
          hook_name: 'customize_preview_init',
          callback: function() {
            // error_log( current_filter() );
            return true;
          },
          priority: 10,
          accepted_args: 0
        );

        add_action(
          hook_name: 'customize_publish_after',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            if ( str_starts_with( $arg0, $this->id_base ) ) {
              // error_log( PHP_EOL . PHP_EOL . 'BLIPPER WIDGET!' . PHP_EOL );
            }
            return true;
          },
          priority: 9999,
          accepted_args: 1
        );

        add_action(
          hook_name: 'customize_save_after',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( '$arg: ' . self::bw_array_to_string( $arg ) );
            return true;
          },
          priority: 10,
          accepted_args: 1
        );

        add_action(
          hook_name: 'delete_widget',
          callback: function( $arg0, $arg1, $arg2 ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 3
        );

        add_action(
          hook_name: 'pre_post_update',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 1
        );

        add_action(
          hook_name: 'updated_widget',
          callback: function( $arg0, $arg1, $arg2 ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 3
        );

        add_action(
          hook_name: 'wp_ajax_bw_on_delete_inactive_widgets_from_backend',
          callback: function( $arg ) {
            // error_log( current_filter() );
            // error_log( var_export( func_get_args(), true ) );
            return true;
          },
          priority: 10,
          accepted_args: 1
        );


        self::$hooks_and_filters_added = true;
      }
    }

    private static function bw_array_to_string( mixed $input, int $indent_by = 0 ) {

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
          $output .= $indent . '  ' . var_export( $key, true ) . ' => ' . self::bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
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
            //   $output .= $indent . var_export( $key, true ) . ' => ' . self::bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
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
                //   // error_log( 'property: ' . var_export( $property, true ) . ' (value type ' . gettype( $value ) . ')' . self::bw_array_to_string( $value ) );
                //   $output .= $indent . var_export( $property, true ) . ' => ' . self::bw_array_to_string( $value, $indent_by + 1 ) . PHP_EOL;
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
// } else {
  // error_log( 'class Blipper_Widget\Widget\Blipper_Widget does exist' );
}
