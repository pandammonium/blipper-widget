<?php

/**
  * Blipper Widget settings (back end).
  * These settings are set from the Blipper Widget settings page, as opposed to
  * on the back-end widget form. They are settings, such as OAuth credentials,
  * that are unlikely to be changed after they have been set. The settings on
  * the back-end form of the widget are more to do with the appearance of the
  * front-end widget. Therefore, it makes sense to keep them separate.
  *
  * @author    pandammonium   pandammonium
  * @since    0.0.2
  * @license  GPLv2 or later
  * @package  Pandammonium-BlipperWidget-Settings
  *
  */

namespace Blipper_Widget\Settings;

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use Blipfoto\Api\Client;
use Blipfoto\Exceptions\ApiResponseException;
use Blipfoto\Exceptions\OAuthException;

use Blipper_Widget;
use function Blipper_Widget\bw_delete_all_cached_blips;
use function Blipper_Widget\bw_log;


// -- Blipper Widget Settings (Back End) ------------------------------------ //

if (!class_exists( 'Blipper_Widget_Settings' )) {

  /**
    * The Blipper Widget settings (back end).
    * The widget settings in the back end; currently comprises authentication
    * by OAuth2.
    *
    * @since 0.0.2
    * @author    pandammonium
    */
  class Blipper_Widget_Settings {

    /**
     * @author pandammonium
     * @since 1.2.6
     * @var array BW_PLUGIN_DATA Basic plugin data.
     */
    private static ?array $plugin_data = null;

  /**
    * @since    0.0.2
    * @author    pandammonium
    * @var string[]    DEFAULT_OAUTH_SETTINGS   The widget's default settings
    */
    private const DEFAULT_OAUTH_SETTINGS = [
      'username'     => '',
      'access-token' => '',
    ];

  /**
   * @ignore
   */
   // translators: %s: NB stands for Latin 'nota bene', which translates to 'note well' in English. Do not translate the Latin abbreviation, but do translate the translation.
   private const NOTA_BENE = 'Nota bene \'note well\'';

  /**
    * Constructs an instance of this class.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public function __construct() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      self::$plugin_data = [
        'Author' => 'pandammonium',
        'Name' => 'Blipper Widget',
        'Version' => '1.2.6-alpha',
      ];
      // error_log( 'plugin data: ' . var_export( self::$plugin_data, true ) );

      add_action(
        hook_name: 'admin_menu',
        callback: [ self::class, 'bw_admin_menu' ]
      );

      // Ensure the admin page is initialised only when needed:
      // Not calling this results in repeated error messages if error messages
      // are displayed. Repeated error messages look pants.
      if ( !empty ( $GLOBALS['pagenow'] )
        and ( 'options-general.php' === $GLOBALS['pagenow']
        or 'options.php' === $GLOBALS['pagenow']
        or 'options-general/php?page=blipper-widget' === $GLOBALS['pagenow']
        )
      ) {
        add_action(
          hook_name: 'admin_init',
          callback: [ self::class, 'bw_admin_init' ]
        );
      }
    }

  /**
    * Creates a new settings page for the widget in the WP admin settings menu.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public static function bw_admin_menu( $arg ): void {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $plugin_data = self::bw_get_plugin_data();

      // translators: self::$plugin_data['Name']: the plugin name; do not translate
      add_options_page(
        page_title: __( self::$plugin_data['Name'] . '  settings', 'blipper-widget' ),
        menu_title: __( $plugin_data['Name'], 'blipper-widget' ),
        capability: 'manage_options',
        menu_slug: 'blipper-widget',
        callback: [ self::class, 'bw_options_page' ],
        position: 8
      );
    }

  /**
    * Sets up the settings form on the Blipper Widget settings page.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public static function bw_admin_init() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      register_setting(
        // option group:
        'blipper-widget-settings',
        // option name:
        'blipper-widget-settings-oauth',
        // callback function to validate input
        [ self::class, 'bw_oauth_validate' ]
      );

      add_settings_section(
        // section id:
        'blipper-widget-oauth',
        // section title:
        // translators: do not translate 'Blipfoto': it is the name of a service
        __( 'Blipfoto OAuth 2.0 settings', 'blipper-widget' ),
        // section callback function to render information and instructions about
        // this section:
        [ self::class, 'bw_oauth_instructions' ],
        // page id (i.e. menu slug):
        'blipper-widget'
      );

      add_settings_field(
        // field id:
        'blipper-widget-username',
        // field title:
        // translators: do not translate 'Blipfoto': it is the name of a service
        __( 'Blipfoto username', 'blipper-widget' ),
        // callback function to render the field on the form:
        [ self::class, 'bw_render_input_field' ],
        // page id (i.e. menu slug):
        'blipper-widget',
        // section id the field belongs to:
        'blipper-widget-oauth',
        // arguments for the callback function:
        [
          'type'        => 'text',
          'name'        => 'blipper-widget-settings-oauth[username]',
          // translators: do not translate 'Blipfoto': it is the name of a service
          'placeholder' => __( 'Enter your Blipfoto username here', 'blipper-widget' ),
          'id'          => 'blipper-widget-input-username',
          'setting'     => 'username',
        ]
      );
      add_settings_field(
        // field id:
        'blipper-widget-oauth-access-token',
        // field title:
        // translators: do not translate 'Blipfoto': it is the name of a service
        __( 'Blipfoto access token', 'blipper-widget' ),
        // callback function to render the field on the form:
        [ self::class, 'bw_render_input_field' ],
        // page id (i.e. menu slug):
        'blipper-widget',
        // section id the field belongs to:
        'blipper-widget-oauth',
        // arguments for the callback function:
        [
          'type'        => 'text',
          'name'        => 'blipper-widget-settings-oauth[access-token]',
          // translators: do not translate 'Blipfoto': it is the name of a service
          'placeholder' => __( 'Enter your Blipfoto access token here', 'blipper-widget' ),
          'id'          => 'blipper-widget-input-access-token',
          'setting'     => 'access-token',
        ]
      );
    }

  /**
    * Output the value, if there is one, in an input field.
    *
    * Callback function, therefore must be public.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @param     array $args
    * @return    void
    */
    public static function bw_render_input_field( $args ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $settings = get_option( 'blipper-widget-settings-oauth' );
      $value = false === $settings ? self::DEFAULT_OAUTH_SETTINGS[$args['setting']] : $settings[$args['setting']];
      ?>
        <input type="<?php echo $args['type']; ?>" id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" placeholder="<?php echo $args['placeholder']; ?>" value="<?php echo $value; ?>" size="50">
      <?php
    }

  /**
    * Render the options page.
    *
    * Callback function, therefore must be public.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public static function bw_options_page() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $plugin_data = self::bw_get_plugin_data();
      ?>
      <div class="wrap">
        <h2><?php
          printf(
            // translators: 1 is the name of the plugin (do not translate):
            __( '%1$s', 'blipper-widget'),
                $plugin_data['Name']
          );
        ?> settings</h2>
        <script type="text/javascript">pause(\'inside the options page\')</script>
          <?php
        if ( !current_user_can( 'manage_options' ) ) {
          self::bw_no_access_to_options( $plugin_data );
        } else {
          ?>
          <div class="notice">
            <p>
              <strong>
                <abbr title="<?php
                  printf(
                    // translators: %s: NB stands for Latin 'nota bene', which translates to 'note well' in English.
                    __( '%s', 'blipper-widget'),
                    self::NOTA_BENE
                  );?>">NB</abbr>
                  <?php
                    printf(
                      // translators: %1$s: plugin name
                      __( '%1$s is a classic widget with a shortcode. Although there is no %1$s block, %1$s can still be used in block-enabled themes.', 'blipper-widget' ),
                      $plugin_data['Name']
                    );
                  ?>
                </strong>
            </p>
            <p><?php
              printf(
                  // translators: %1$s: plugin name
                __( 'There are two ways to get %1$s to work with block-enabled themes. The first is a workaround; the second uses existing %1$s functionality:', 'blipper-widget' ),
                $plugin_data['Name']
              ); ?>
            </p>
            <ol>
              <li><?php
                printf(
                  // translators: %1$s: plugin name
                  __( 'Install <a href="https://en-gb.wordpress.org/plugins/search/classic+widgets/">a plugin that enables classic widgets</a>. This will allow you to add %1$s to any widget-enabled location on your site. %1$s has been tested with <a href="https://en-gb.wordpress.org/plugins/classic-widgets/">Classic Widgets</a>.', 'blipper-widget' ),
                  $plugin_data['Name']
                ); ?>
              </li>
              <li><?php
                printf(
                  // translators: %1$s: plugin name
                  __( 'Use the %1$s shortcode in a WP <a href="https://wordpress.org/support/article/shortcode-block/">Shortcode block</a> anywhere a shortcode may be used. Example: <code>[blipper_widget title=\'%1$s\' add-link-to-blip=show display-journal-title=show display-powered-by=show display-desc-text=show]</code>', 'blipper-widget' ),
                  $plugin_data['Name']
                ); ?>
              </li>
            </ol>
            <p>Either way, you must fill out the form below.</p>
          </div>
          <form action="options.php" method="POST">
            <?php
              // Render a few hidden fields that tell WP which settings are going to be updated on this page:
              settings_fields( 'blipper-widget-settings' );
              // Output all the sections and fields that have been added to the options page (with slug blipper-widget):
              do_settings_sections( 'blipper-widget' );
              submit_button();
            ?>
          </form>
          <?php
        }
        ?>
        <p><?php
          printf(
            // translators: 1: plugin name (do not translate); 2: plugin version number; 3: name of plugin author (do not translate)
            __( '%1$s version %2$s by %3$s.', 'blipper-widget' ),
                $plugin_data['Name'],
                $plugin_data['Version'],
                $plugin_data['Author']
          ); ?>
        </p>
      </div>
      <?php
    }

  /**
    * Validate the OAuth input.
    *
    * Make sure the input comprises only printable/alphanumeric (depending on the
    * field) characters; otherwise, return an empty string/the default value.
    * Callback function, thereofre must be public.
    *
    * (This might become a loop at some point.)
    *
    * @since     0.0.2
    * @author    pandammonium
    * @param     array         $input             An array containing the settings
    *                                               that the user wants to set.
    * @return    string                           The validated setting.
    */
    public static function bw_oauth_validate( $input ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $output = self::DEFAULT_OAUTH_SETTINGS;

      if ( !is_array( $input ) ) {

        add_settings_error(
          'wp-blipper-settings-group',
          'invalid-input',
          __( 'Something has gone wrong. Please check the OAuth settings.', 'blipper-widget' )
        );

      } else {

        $is_valid = false;
        $settings = get_option( 'blipper-widget-settings-oauth' );

        $input['username'] = trim( esc_attr( $input['username'] ) );
        if ( true === ctype_print( $input['username'] ) ) {
          // $output['username'] = $input['username'];
          $is_valid = true;
        } else if ( empty( $input['username'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'missing-oauth-username',
            __( 'Please enter your Blipfoto username.', 'blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-access-token',
            __( 'Please enter printable characters only for your Blipfoto username.', 'blipper-widget' )
          );
          // $output['username'] = '';
        }
        $output['username'] = $input['username'];

        $input['access-token'] = trim( esc_attr( $input['access-token'] ) );
        if ( true === ctype_alnum( $input['access-token'] ) ) {
          // $output['access-token'] = $input['access-token'];
          $is_valid = true;
        } else if ( empty( $input['access-token'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'missing-oauth-access-token',
            __( 'Please enter your Blipfoto access token.', 'blipper-widget' )
          );
          // $output['access-token'] = '';
        } else {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-access-token',
            __( 'Please enter alphanumeric characters only for your Blipfoto access token.', 'blipper-widget' )
          );
          // $output['access-token'] = '';
        }
        $output['access-token'] = $input['access-token'];

        if ( $is_valid ) {
          if ( !self::bw_test_connection( $output ) ) {
            bw_delete_all_cached_blips( BW_PREFIX );
          }
        }
      }
      return $output;
    }

  /**
    * Output the instructions for setting the plugin's options.
    * Callback function.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public static function bw_oauth_instructions() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $plugin_data = self::bw_get_plugin_data();
      ?>
      <p><?php
        printf(
          // translators: 'Blipfoto' is the sanme of a service and should not be translated
          __( 'You need to authorise access to your Blipfoto account before you can use this plugin. <em>You can revoke access at any time.</em>', 'blipper-widget' )
        ); ?>
      </p>
      <p><?php
        printf(
          __( 'Just follow the instructions below to authorise access and to revoke access.', 'blipper-widget' )
        ); ?>
      </p>
      <h4><?php
        printf(
          // translators: 'Blipfoto' is the sanme of a service and should not be translated
          __( 'How to authorise your Blipfoto account', 'blipper-widget' )
        ); ?>
      </h4>
      <p><?php
        printf(
          // translators: 'Blipfoto' is the sanme of a service and should not be translated
          __( 'To allow WordPress to access your Blipfoto account, you need to carry out a few simple steps:', 'blipper-widget' )
        ); ?>
      </p>
      <ol>
        <li><?php
          printf(
            // translators: 'Blipfoto' is the sanme of a service and should not be translated
            __( 'Open the <a href="https://www.blipfoto.com/developer/apps" rel="nofollow">the Blipfoto apps page</a> in a new tab or window.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Create New App' should not be translated because it is taken from the Blipfoto website
            __( 'Press the <i>Create new app</i> button.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'super-duper' is a reduplicative of 'super' meaning 'excellent, superior'
            __( 'In the <i>Name</i> field, give your app any name you like, for example, <i>My super-duper app</i>.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Type' should not be translated because it is taken from the Blipfoto website
            __( 'The <i>Type</i> field should be set to <i>Web application</i>.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Description' should not be translated because it is taken from the Blipfoto website
            __( 'Optionally, describe your app in the <i>Description</i> field, so you know what it does.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Website' should not be translated because it is taken from the Blipfoto website. %s: the URL of this website
            __( 'In the <i>Website</i> field, enter the URL of your website (most likely <code> %s</code>).', 'blipper-widget' ),
            home_url()
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Redirect URI' should not be translated because it is taken from the Blipfoto website
            __( 'Leave the <i>Redirect URI</i> field blank.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Developer rules' should not be translated because it is taken from the Blipfoto website
            __( 'Indicate that you agree to the <i>Developer rules</i>.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Create a new app' should not be translated because it is taken from the Blipfoto website
            __( 'Press the <i>Create a new app</i> button.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators:  'Client ID', 'Client Secret' and 'Access Token'      should not be translated because it is taken from the Blipfoto website
            __( 'You should now see your <i>Client ID</i>, <i>Client Secret</i> and <i>Access Token</i>. Copy and paste your <i>Access Token</i> only into the corresponding field below.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            __( 'Press the <i>Save Changes</i> button to save the data.', 'blipper-widget' )
          ); ?>
        </li>
      </ol>
      <p>
        <abbr title="<?php
          printf(
            // translators: %s: NB stands for Latin 'nota bene', which translates to 'note well' in English
            __( '%s', 'blipper-widget'),
            self::NOTA_BENE
          );?>">NB</abbr>
        <?php
          printf(
            // translators: %1$s: plugin name
            __( 'Whereas authorisation gives %1$s permission to access your Blipfoto account, it does not give %1$s access to your password.', 'blipper-widget' ),
            $plugin_data['Name']
        ); ?>
      </p>
      <h4><?php
        printf(
          // translators: 'Blipfoto' is the sanme of a service and should not be translated
          __( 'How to revoke access to your Blipfoto account', 'blipper-widget' )
        ); ?>
      </h4>
      <p><?php
        printf(
          __( 'It\'s simple to revoke access. We hope you don\'t want to do this, but if you do, the instructions are laid out below:', 'blipper-widget' )
        ); ?>
      </p>
      <ol>
        <li><?php
          printf(
            // translators: 'Save Changes' should not be translated because it is taken from the Blipfoto website
            __( 'Go to <a href="https://www.blipfoto.com/settings/apps" rel="nofollow">your Blipfoto app settings</a>.', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Save Changes' should not be translated because it is taken from the Blipfoto website
            __( 'Select the app whose access you want to revoke (the one you created using the above instructions).', 'blipper-widget' )
          ); ?>
        </li>
        <li><?php
          printf(
            // translators: 'Save Changes' should not be translated because it is taken from the Blipfoto website
            __( 'Press the <i>Save Changes</i> button.', 'blipper-widget' )
          ); ?>
        </li>
      </ol>
      <p><?php
        printf(
          __( 'Note that your plugin will no longer work. Remember to remove any widgets and shortcodes you\'ve added to your site.', 'blipper-widget' )
        ); ?>
      </p>
      <h4><?php
        printf(
          // translators: 'Blipfoto' is the sanme of a service and should not be translated
          __( 'Blipfoto username', 'blipper-widget' )
        ); ?>
      </h4>
      <p><?php
        printf(
          __( 'You also need to enter your username in the appropriate field below. The widget will check that the access token is valid for your account.', 'blipper-widget' )
        ); ?>
      </p>
      <h4><?php
        printf(
          __( 'Add the widget or shortcode', 'blipper-widget' )
        ); ?>
      </h4>
      <p><?php
        printf(
          // translators:
          __( 'All that\'s left to do now is to add the widget to one of your widget areas (e.g. sidebar, footer) or add the shortcode (to a page, post, etc.), style it and you\'re good to go!', 'blipper-widget' )
        ); ?>
      </p>
      <?php
    }

  /**
    * Checks whether the OAuth credentials are valid or not.
    * A temporary client is created using the settings given. If the settings
    * are invalid, an exception will be thrown when the client is used to get
    * data from Blipfoto.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @internal
    * @param     string[]     The OAuth settings provided by the user.
    * @return    void
    */
    private static function bw_test_connection( array $oauth_settings ): bool {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      $client_ok = false;
      $client = null;
      $user_profile = null;
      try {
        if ( array_key_exists( 'access-token', $oauth_settings ) && !empty( $oauth_settings['access-token'] ) ) {
          $client = new Client(
            '',
            '',
            $oauth_settings['access-token']
          );
        } else {
          throw new Blipper_Widget_OAuthException( 'The access token is missing.' );
        }
      } catch ( Blipper_Widget_OAuthException $e ) {
        add_settings_error(
          'wp-blipper-settings-group',
          'invalid-oauth-credentials',
          // translators: do not translate 'Blipfoto': it is the name of a service
          __( 'Unable to connect to Blipfoto. ' . $e->getMessage(), 'blipper-widget' )
        );
      } catch ( ApiResponseException $e ) {
        add_settings_error(
          'wp-blipper-settings-group',
          'invalid-oauth-credentials',
          // translators: do not translate 'Blipfoto': it is the name of a service
          __( 'Unable to connect to Blipfoto. Please check the OAuth settings.', 'blipper-widget' )
        );
      }
      if ( !empty( $client ) && isset( $client ) ) {

        try {

          $user_profile = $client->get(
            'user/profile'
          );

          $user = $user_profile->data()['user'];

          if ( array_key_exists( 'username', $user ) && array_key_exists( 'username', $oauth_settings ) ) {
            if ( $user['username'] !== $oauth_settings['username'] ) {
              // unset( $client );
              throw new Blipper_Widget_OAuthException( 'The username provided does not match the username of the Blipfoto account given by the access token.' );
            } else {
              $client_ok = true;
            }
          }

        } catch ( Blipper_Widget_OAuthException $e ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-credentials',
            __( 'Error. ' . $e->getMessage(), 'blipper-widget' )
          );
        } catch ( ApiResponseException $e ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-credentials',
            // translators: do not translate 'Blipfoto': it is the name of a service
            // The inline styling is needed because the bullet points of the unordered list are not otherwise shown. The margin styling is taken from the ordered list styling:
            __( 'Unable to connect to your Blipfoto account without a valid access token.<br/><ul style="list-style-type: disc; margin-left: 2em;">
              <li>Please check you have correctly copied <a href="https://www.blipfoto.com/developer/apps" rel="nofollow">your access token at Blipfoto</a> and pasted it into the access token field below.</li>
              <li>If you have entered it correctly, try <a href="https://www.blipfoto.com/developer/apps" rel="nofollow">refreshing your access token at Blipfoto</a> and entering the resulting access token below.</li>
              <li>If you have previously refreshed your Blipfoto OAuth access token, you need to update it below.</li>
              </ul>', 'blipper-widget' )
          );
        }
      }
      return $client_ok;
    }

  /**
    * Check if the settings have been set or not.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    string    The string used as the key in the database, which
    *                        stores the widget's OAuth settings.
    */
    public static function bw_settings_have_been_set() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      return false !== get_option( 'blipper-widget-settings-oauth' );
    }

  /**
    * Return the settings in the database
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    array     The settings in the database or false if not set.
    */
    public static function bw_get_settings() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // error_log( 'settings have been set: ' . var_export( self::bw_settings_have_been_set(), true ) );
      return get_option( 'blipper-widget-settings-oauth' );
    }

  /**
    * Return the name of the options key in the database
    * (see bw_admin_init)
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    string    The string used as the key in the database, which
    *                        stores the widget's OAuth settings.
    */
    public static function bw_get_settings_db_name() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      return 'blipper-widget-settings-oauth';
    }

  /**
   * What to do if the user doesn't have permission to access the options.
   *
   * @since 1.2.1
   * @author pandammonium
   * @return void
   */
    private static function bw_no_access_to_options( $plugin_data ) {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      ?>
      <div class="error">
        <?php
        printf(
          // translators: %s: Plugin name; do not translate
          __( 'You do not have permission to modify the settings for %s.', 'blipper-widget' ),
          $plugin_data['Name']
        );
        ?>
      </div>
      <?php
    }

  /**
   * Gets the information supplied in the header of the main plugin file, plus
   * a few extra bits.
   *
   * In some instances, WordPress's plugin file hasn't been loaded when the
   * plugin data is required. That information is therefore hardcoded so it is
   * available for use in that situation.
   *
   * @since 1.2.1
   * @author pandammonium
   * @return array<string, mixed> The plugin data insofar as it's available at
   * the point of execution.
   */
    public static function bw_get_plugin_data() {
      // bw_log( 'method', __METHOD__ . '()' );
      // bw_log( 'arguments', func_get_args() );

      // Only call for the plugin data if it hasn't already been obtained and the function exists. The default values don't include 'Title', which is automatically added by WordPress:
      if ( !array_key_exists( 'Title', self::$plugin_data ) && function_exists( 'get_plugin_data' ) ) {
        // error_log( 'getting plugin data' );
        $plugin_base = plugin_dir_path(__FILE__) . '../blipper-widget.php';
        self::$plugin_data = get_plugin_data($plugin_base, false, true);
      } else {
        // error_log( 'using saved plugin data' );
      }
      // error_log( 'plugin data: ' . var_export( self::$plugin_data, true ) );
      return self::$plugin_data;
    }
  }
}
