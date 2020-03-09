=== Blipper Widget ===
Contributors: pandammonium
Donate link: https://pandammonium.org/donate/
Tags: photos,photo,blipfoto,widget,daily photo,photo display,image display,365 project,images,image
Requires at least: 4.3
Tested up to: 5.0.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display your latest blip in a widget.  Requires a Blipfoto account.

== Description ==

Displays the latest entry on Blipfoto by a given user in a widget on your WordPress website.  Note: you must have an account with Blipfoto to use this widget.  If you don't have one, you can [get one for free](https://www.blipfoto.com/account/signup).

Currently, Blipper Widget:

* displays the latest blip (image, title and date) in your Blipfoto account in a widget;
* takes you to the corresponding blip on the Blipfoto website if you click on the image or its title and date (optional);
* displays your journal name and a link to your Blipfoto account (optional); and
* displays a link to the Blipfoto website (optional).

The image in the blip is not stored on your server: the widget links to the image on Blipfoto.

= View the plugin =

If you'd like to see the plugin in action, you can visit [my WordPress site](https://pandammonium.org/) to see Blipper Widget showing my latest blip.

If you'd like to see the latest plugin code, [it's available on GitHub](https://github.com/pandammonium/blipper-widget).  Note that this might be ahead of the current release of Blipper Widget.  The code for the current release is available in WordPress' SVN repository.

= Languages =

Currently, only English is supported.  I'm afraid I don't yet know how to make other languages available.  If you'd like to help, let me know in the comments on [my Blipper Plugin page](https://pandammonium.org/wordpress-dev/blipper-widget/).

= About Blipfoto =

[Blipfoto](https://www.blipfoto.com/) is a photo journal service, allowing users to post one photo a day along with descriptive text and tags.  It uses OAuth 2.0 to ensure that your password is kept secure.  You will need to obtain these from Blipfoto.  This is a straightforward process and instructions are given.

This plugin is independent of and unendorsed by Blipfoto.  Use of this plugin means you consent to this plugin accessing your Blipfoto account and performing actions including publishing your blips on your WordPress website.

= Requirements =

* Blipfoto account
* WordPress 4.3
* PHP 5
* PHP [Client URL (cURL) library](http://php.net/manual/en/book.curl.php)

= Disclaimer =

By using this plugin, you consent to it performing actions involving your Blipfoto account, including, but not limited to, obtaining your account details (excluding your password).

You, the Blipfoto account holder, are responsible for the images shown on any website using the Blipper Widget with your OAuth credentials and access token.

The Blipfoto PHP SDK is used under [the MIT Licence](https://opensource.org/licenses/MIT).

= GDPR compliance =

Only your Blipfoto username, which is public information, is required, collected and stored by Blipper Widget.  Your Blipfoto username will be retained by this plugin until you delete or uninstall the plugin.  If you do not wish BLipper Widget to stor your Blipfoto username, please do not use this plugin.

== Frequently Asked Questions ==

= Does the widget need my Blipfoto username and password? =

The widget asks for your username for verification purposes, but does not require your password.  It does not ask for your password and it does not have access to your password.  The widget uses an OAuth 2.0 access token to authorise access to your Blipfoto account, eliminating the need for your password.

= Why doesn't the plugin seem to do anything? =
* If you haven't added any blips to your Blipfoto journal, you won't see any blips in your widget.  Please make sure you have at least one blip in your Blipfoto account.
* If you are logged in and are able to change your site's options and settings, you should see an error message indicating the problem.  It is most likely that you have mistyped your username or that you haven't copied your access token correctly.  Amend these details, and try again.
* If you have refreshed your OAuth app credentials or access token at Blipfoto, you will need to update these details on the Blipper Widget settings page.
* You might have hit the rate limit set by Blipfoto.  If this is the case, try again in fifteen minutes or so.

= Where can I get support for Blipper Widget? =

You can use [the Blipper Widget page](https://pandammonium.org/wordpress-dev/wp-blipper-widget/) on my website to ask questions and report problems.

= Is the image stored on my web server? =

No.  The image in the blip is not stored on your server: the widget links to the image on Blipfoto.

= Does the widget use the original image? =

The widget uses the URL of the best quality image made available to it; typically, this is standard resolution.  Standard resolution is normally good enough for display in a widget.

= Can I display the blips from another account with my access token in my widget? =

No.  The access token must belong to the account whose username is given in the widget settings.

== Installation ==

You can install this plugin either automatically or manually. The instructions for each method are given below.

= Automatic plugin installation =

1. Go to 'Plugins' > 'Add New'.
1. Search for 'Blipper Widget'. Find the plugin in the search results the search results.
1. Click 'Details' for more information about Blipper Widget and instructions you may wish to print or save to help set up Blipper Widget.
1. Click 'Install Now' to install Blipper Widget.
1. The resulting installation screen will list the installation as successful or note any problems during the install.
1. If successful, click 'Activate Plugin' to activate it, or 'Return to Plugin Installer' for further actions.

= Manual plugin installation =

Installation of a WordPress plugin manually requires FTP familiarity and the awareness that you may put your site at risk if you install a WordPress plugin incompatible with the current version or from an unreliable source. You must also have permission to access your server by FTP.

1. Backup your site completely before proceeding.
1. Download Blipper Widget to your computer, for example, to your desktop.
1. If downloaded as a zip archive, extract the plugin folder, wp-blipper-widget.
1. Read the read-me file thoroughly to ensure you understand any installation instructions properly.
1. With your FTP program, upload the plugin folder to the wp-content/plugins folder in your WordPress directory online.
1. Go to the plugins screen and find the newly uploaded plugin, Blipper Widget, in the list.
1. Click 'Activate' to activate it.
1. Check the read-me file for further instructions or information.

= OAuth 2.0 =

You'll need your Blipfoto username and an OAuth access token from Blipfoto to use the widget.
Your username is the username you use to sign in to Blipfoto.  Blipper Widget uses this information only to verify your account.  Blipper Widget does not have access to your Blipfoto password.

To obtain the access token, follow the instructions below:

1. Open [the Blipfoto apps page](https://www.blipfoto.com/developer/apps) in a new tab or window.
1. Press the 'Create new app' button.
1. In the 'Name' field, give your app any name you like, for example, 'My super-duper app'.
1. The 'Type' field should be set to 'Web application'.
1. Optionally, describe your app in the 'Description' field, so you know what it does.
1. In the 'Website' field, enter the URL of your website.
1. Leave the 'Redirect URI' field blank.
1. Indicate that you agree to the 'Developer rules'.
1. Press the 'Create a new app' button.
1. You should now see your Credentials (Client ID and Client Secret) and Access Token.  Copy and paste the access token into the corresponding field on the Blipper Widget settings page.

Note that if you refresh your access token, you must update it in Blipper Widget.

You can revoke access from Blipper Widget to your Blipfoto account easily:

1. Sign in to your Blipfoto account.
1. Go to [your Blipfoto app settings](https://www.blipfoto.com/settings/apps).
1. Select the app whose access you want to revoke, for example, 'My super-duper app'.
1. Press the 'Save changes' button.

*Note that your plugin will no longer work.*

Once installed and the OAuth credentials have been set successfully, add the widget to a widget-enabled area, and set up the settings on the widget form as you wish.  When you view your webpage, you should see your latest blip in the widget-enabled area.  If you can't see it, please check your OAuth settings carefully.

The widget settings are currently:

* Widget title: customisable. The default is 'My latest blip', but you can change it to what suits you or you can delete it and leave it blank.
* Include link to your latest blip: to link the displayed blip back to the corresponding entry on Blipfoto, tick the box.  The link has a rel="nofollow" attribute.  This option is unticked by default.
* Display journal title and link: to include a link back to your Blipfoto journal, tick the box. For my journal, the link will appear as 'From Panda’s Pics'.  The link has a rel="nofollow" attribute.  This option is unticked by default.
* Include a ‘powered by’ link: to include a 'Powered by Blipfoto' link to be displayed, tick the box.  The link has a rel="nofollow" attribute.  This option is unticked by default.

== Screenshots ==

1. The Blipper Widget settings page.
2. The widget form settings.
3. An example of the widget in use.

== Changelog ==

= 1.0.1 =

* Corrections to documentation.

= 1.0 =

* It's about time it got to a proper version number given that it seems pretty stable.  Let me know in the comments on [my Blipper Plugin page](https://pandammonium.org/wordpress-dev/blipper-widget/) if it isn't!

* Tested to ensure compatibility with WordPress 5.0.2.

= 0.0.10 =

* Tested to ensure compatibility with WordPress 4.9.8.

= 0.0.9 =

* Tested to ensure compatibility with WordPress 4.7.

= 0.0.8 =

* Tested to ensure compatibility with WordPress 4.6.1.

= 0.0.7 =

* Fixed: bug that added link from the image to the blip on Blipfoto whether it was set or not.

= 0.0.6 =

* Tested to ensure compatibility with WordPress 4.5.
* In accordance with the removal of the Polaroid brand from Blipfoto, all mentions of Polaroid have been removed from Blipper Widget (except in this change log entry, where mentions of Polaroid have been added).
* Changed the padding increment from half a pixel to a whole pixel.

= 0.0.5 =

* Added: styling!  You can now change the border of the widget, including the line style, the thickness and the colour.  You can also change the background colour and the text colour of the widget.
* Changed: the display of the date is now optional.  It is on by default for backwards compatibility.
* Improved: handling of options.
* Improved: exception handling.

= 0.0.4 =

* Updated: screenshots.

= 0.0.3 =

* Added: uninstallation code to remove settings pertaining to the Blipper Widget to be removed from the database, leaving no trace of itself.
* Replaced: screenshot-3.png with a screenshot of the widget in use on a site with the default twenty-fifteen theme with no modifications.
* Added donation link.

= 0.0.2 =

* Changed: the widget's settings have been divided into those that act behind the scenes (such as OAuth) and those that directly affect the appearance of the widget front end.
* Changed: the OAuth settings moved to Blipper Widget settings page under the general WordPress settings menu in the admin area.
* Added: settings affecting the widget's appearance to the widget form; specifically including links back to Blipfoto (the blip itself, the user's journal, Blipfoto).  By default, these links are not displayed; the user must opt in to their display.
* Renamed: the name of the widget from WP Blipper Widget to Blipper Widget, thus dropping the WP.

= 0.0.1 =

* Initial version.

== Upgrade notice ==

= 0.0.8 =

Update now to ensure compatability with WordPress 4.6.1.

= 0.0.7 =

Update now to fix a bug linking the image back to Blipfoto when not desired.

= 0.0.6 =

Update now to ensure compatability with WordPress 4.5 and the rebranding of the Blipfoto website.

= 0.0.5 =

Update now to style your widget.

You can also hide the date of your blip in the widget.  The date is shown by default for backwards compatibility.

== Known issues ==

There is [a list of known problems and enhancement requests](https://github.com/pandammonium/wp-blipper-widget/issues) on GitHub.  If you have a suggestion for how to improve Blipper Widget, please add it to GitHub.  Cheers!

== Credits ==

This plug-in is loosely based on [BlipPress](https://wordpress.org/plugins/blippress/) by [Simon Blackbourne](https://mobile.twitter.com/lumpysimon).  I very much appreciate having his work to guide me with the use of [the Blipfoto API](https://www.blipfoto.com/developer/api).

I also used the excellent [Rotating Tweets](https://wordpress.org/plugins/rotatingtweets/) plugin to guide me with how to implement the settings page and the widget back-end.

In addition, I used [WP-Spamshield](https://wordpress.org/plugins/wp-spamshield/) as a model of how to implement uninstallation code.
