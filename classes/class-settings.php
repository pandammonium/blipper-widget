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

namespace blipper_widget\settings;

// If this file is called directly, abort:
defined( 'ABSPATH' ) or die();
defined( 'WPINC' ) or die();

use blipper_widget_Blipfoto\blipper_widget_Api\blipper_widget_client;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_ApiResponseException;
use blipper_widget_Blipfoto\blipper_widget_Exceptions\blipper_widget_OAuthException;

// -- Blipper Widget Settings (Back End) ------------------------------------ //

if (!class_exists( 'Blipper_Widget_Settings' )) {

  /**
    * The Blipper Widget settings (back end).
    * The widget settings in the back end; currently comprises authentication by OAuth2.
    *
    * @since 0.0.2
    * @author    pandammonium
    */
  class Blipper_Widget_Settings {

  /**
    * @since    0.0.2
    * @author    pandammonium
    * @property string[]    $blipper_widget_defaults   The widget's default settings
    */
    private $blipper_widget_defaults = array(
      'username'              => '',
      'access-token'          => '',
    );

  /**
   * @ignore
   */
   // translators: %s: NB stands for Latin 'nota bene', which translates to 'note well' in English
   private static $nota_bene = 'Nota bene \'note well\'';

  ///**
  //  * @since    0.0.2
  //  * @property array    $blipper_widget_settings   The widget's user-defined settings
  //  */
    // private $blipper_widget_settings;

  /**
    * Constructs an instance of this class.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public function __construct() {

      add_action( 'admin_menu', array( &$this, 'blipper_widget_admin_menu' ) );
      // Ensure the admin page is initialised only when needed:
      // Not calling this results in repeated error messages, if error messages
      // are displayed. Repeated error messages look pants.
      if ( ! empty ( $GLOBALS['pagenow'] )
        and ( 'options-general.php' === $GLOBALS['pagenow']
        or 'options.php' === $GLOBALS['pagenow']
        or 'options-general/php?page=blipper-widget'  === $GLOBALS['pagenow']
        )
      ) {
        add_action( 'admin_init', array( &$this, 'blipper_widget_admin_init' ) );
      }
    }

  /**
    * Creates a new settings page for the widget in the WP admin settings menu.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public function blipper_widget_admin_menu() {

      $plugin_data = $this->blipper_widget_get_plugin_data();

      add_options_page(
        // translators: $plugin_data['Name']: the plugin name; do not translate
        // text to be displayed in the title tags of the page when the menu is selected (not to be confused with page header):
        __( $plugin_data['Name'] . '  settings', 'blipper-widget' ),
        // text to be used for the menu:
        __( $plugin_data['Name'], 'blipper-widget' ),
        // capability required for this menu to be displayed to the user:
        'manage_options',
        // slug name to refer to this menu by:
        'blipper-widget',
        // function to be called to output the content for this page:
        array( &$this, 'blipper_widget_options_page' ),
        // position in the menu order this item should appear:
        8
      );
    }

  /**
    * Sets up the settings form on the Blipper Widget settings page.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public function blipper_widget_admin_init() {

      register_setting(
        // option group:
        'blipper-widget-settings',
        // option name:
        'blipper-widget-settings-oauth',
        // callback function to validate input
        array( &$this, 'blipper_widget_oauth_validate' )
      );

      add_settings_section(
        // section id:
        'blipper-widget-oauth',
        // section title:
        // translators: do not translate 'Blipfoto': it is the name of a service
        __( 'Blipfoto OAuth 2.0 settings', 'blipper-widget' ),
        // section callback function to render information and instructions about
        // this section:
        array( &$this, 'blipper_widget_oauth_instructions'),
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
        array( &$this, 'wp_blipper_field_render'),
        // page id (i.e. menu slug):
        'blipper-widget',
        // section id the field belongs to:
        'blipper-widget-oauth',
        // arguments for the callback function:
        array(
          'type'        => 'text',
          'name'        => 'blipper-widget-settings-oauth[username]',
          // translators: do not translate 'Blipfoto': it is the name of a service
          'placeholder' => __( 'Enter your Blipfoto username here', 'blipper-widget' ),
          'id'          => 'blipper-widget-input-username',
          'setting'     => 'username',
        )
      );
      add_settings_field(
        // field id:
        'blipper-widget-oauth-access-token',
        // field title:
        // translators: do not translate 'Blipfoto': it is the name of a service
        __( 'Blipfoto access token', 'blipper-widget' ),
        // callback function to render the field on the form:
        array( &$this, 'wp_blipper_field_render'),
        // page id (i.e. menu slug):
        'blipper-widget',
        // section id the field belongs to:
        'blipper-widget-oauth',
        // arguments for the callback function:
        array(
          'type'        => 'text',
          'name'        => 'blipper-widget-settings-oauth[access-token]',
          // translators: do not translate 'Blipfoto': it is the name of a service
          'placeholder' => __( 'Enter your Blipfoto access token here', 'blipper-widget' ),
          'id'          => 'blipper-widget-input-access-token',
          'setting'     => 'access-token',
        )
      );

    }

  /**
    * Output the value, if there is one, in an input field.
    * Callback function.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @param     array $args
    * @return    void
    */
    public function wp_blipper_field_render( $args ) {

      $settings = get_option( 'blipper-widget-settings-oauth' );
      $value = false == $settings ? $this->blipper_widget_defaults[$args['setting']] : $settings[$args['setting']];
      ?>
        <input type="<?php echo $args['type']; ?>" id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" placeholder="<?php echo $args['placeholder']; ?>" value="<?php echo $value; ?>" size="50">
      <?php

    }

  /**
    * Render the options page.
    * Callback function.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    void
    */
    public function blipper_widget_options_page() {

      $plugin_data = $this->blipper_widget_get_plugin_data();
      ?>
      <div class="wrap">
        <h2><?php
          printf(
            // translators: 1 is the name of the plugin:
            __('%1$s', 'blipper-widget'),
            $plugin_data['Name']
          );
        ?> settings</h2>
        <script type="text/javascript">pause(\'inside the options page\')</script>
          <?php
        if ( !current_user_can( 'manage_options' ) ) {
          $this->blipper_widget_no_access_to_options( $plugin_data );
        } else {
          ?>
            <div class="notice">
              <p>
                <strong>
                  <abbr title="<?php
                    printf(
                      // translators: %s: NB stands for Latin 'nota bene', which translates to 'note well' in English
                      __( '%s', 'blipper-widget'),
                      self::$nota_bene
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
                // Output all the sections and fields that have been added to the options page (with slug options-wp-blipper):
                do_settings_sections( 'blipper-widget' );
                submit_button();
              ?>
            </form>
        <?php
          }
        ?>
        <p><?php
          printf(
            // translators: 1: plugin name; 2: plugin version number
            __( '%1$s version %2$s', 'blipper-widget' ),
            $plugin_data['Name'],
            $plugin_data['Version']
          ); ?>
        </p>
      </div>
      <?php

    }

  /**
    * Validate the OAuth input.
    * Make sure the input comprises only printable/alphanumeric (depending on the
    * field) characters; otherwise, return an empty string/the default value.
    * Callback function.
    *
    * (This might become a loop at some point.)
    *
    * @since     0.0.2
    * @author    pandammonium
    * @param     array         $input             An array containing the settings
    *                                               that the user wants to set.
    * @return    string                           The validated setting.
    */
    public function blipper_widget_oauth_validate( $input ) {

      $output = $this->blipper_widget_defaults;

      if ( !is_array( $input ) ) {

        add_settings_error(
          'wp-blipper-settings-group',
          'invalid-input',
          __( 'Something has gone wrong. Please check the OAuth settings.', 'blipper-widget' )
        );

      } else {

        $settings = get_option( 'blipper-widget-settings-oauth' );

        $input['username'] = trim( esc_attr( $input['username'] ) );
        if ( true === ctype_print( $input['username'] ) ) {
          $output['username'] = $input['username'];
        } else if ( empty( $input['username'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'missing-oauth-username',
            __( 'Please enter a value for the username.', 'blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-access-token',
            __( 'Please enter printable characters only for the username.', 'blipper-widget' )
          );
          $output['username'] = '';
        }

        $input['access-token'] = trim( esc_attr( $input['access-token'] ) );
        if ( true === ctype_alnum( $input['access-token'] ) ) {
          $output['access-token'] = $input['access-token'];
        } else if ( empty( $input['access-token'] ) ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'missing-oauth-access-token',
            __( 'Please enter a value for the access token.', 'blipper-widget' )
          );
        } else {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-access-token',
            __( 'Please enter alphanumeric characters only for the access token.', 'blipper-widget' )
          );
          $output['access-token'] = '';
        }

        $this->blipper_widget_test_connection( $output );

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
    public function blipper_widget_oauth_instructions() {

      $plugin_data = $this->blipper_widget_get_plugin_data();
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
            self::$nota_bene
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
    private function blipper_widget_test_connection( $oauth_settings ) {

      $client = null;
      $user_profile = null;
      try {
        $client = new blipper_widget_client (
         '',
         '',
         $oauth_settings['access-token']
        );
      } catch ( blipper_widget_ApiResponseException $e ) {
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

          if ( $user['username'] != $oauth_settings['username'] ) {
            throw new blipper_widget_OAuthException( 'Please check the username you entered is correct.' );
          }

        } catch ( blipper_widget_OAuthException $e ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-credentials',
            __( 'Error. ' . $e->getMessage(), 'blipper-widget' )
          );
        } catch ( blipper_widget_ApiResponseException $e ) {
          add_settings_error(
            'wp-blipper-settings-group',
            'invalid-oauth-credentials',
            // translators: do not translate 'Blipfoto': it is the name of a service
            __( 'Unable to connect to your Blipfoto user profile.<br>Please check you have correctly copied <a href="https://www.blipfoto.com/developer/apps" rel="nofollow">your access token at Blipfoto</a> and pasted it into the settings below.<br>If you have refreshed your Blipfoto OAuth access token, you need to update it below.<br>If you have entered it correctly, try <a href="https://www.blipfoto.com/developer/apps" rel="nofollow">refreshing your access token at Blipfoto</a> and entering it below.', 'blipper-widget' )
          );
        }
      }
      return $client && $user_profile;
    }

  /**
    * Check if the settings have been set or not.
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    string    The string used as the key in the database, which
    *                        stores the widget's OAuth settings.
    */
    public function blipper_widget_settings_have_been_set() {

      return false != get_option( 'blipper-widget-settings-oauth' );

    }

  /**
    * Return the settings in the database
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    array     The settings in the database or false if not set.
    */
    public function blipper_widget_get_settings() {

      return get_option( 'blipper-widget-settings-oauth' );

    }

  /**
    * Return the name of the options key in the database
    * (see blipper_widget_admin_init)
    *
    * @since     0.0.2
    * @author    pandammonium
    * @return    string    The string used as the key in the database, which
    *                        stores the widget's OAuth settings.
    */
    public function blipper_widget_get_settings_db_name() {

      return 'blipper-widget-settings-oauth';

    }

  /**
   * What to do if the user doesn't have permission to acess the options.
   *
   * @since 1.2.1
   * @author pandammonium
   * @return void
   */
    private function blipper_widget_no_access_to_options( $plugin_data ) {

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
   * Gets the header information from the main plugin file.
   *
   * @since 1.2.1
   * @author pandammonium
   * @return array
   */
    private function blipper_widget_get_plugin_data() {

      $plugin_base = plugin_dir_path(__FILE__) . '../blipper-widget.php';
      $plugin_data = get_plugin_data($plugin_base, false, true);
      return $plugin_data;

    }

  }

}
