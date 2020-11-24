=== Blipper Widget ===
Contributors: pandammonium
Donate link: https://pandammonium.org/donate/
Tags: photos,photo,blipfoto,widget,daily photo,photo display,image display,365 project,images,image
Requires at least: 4.3
Tested up to: 5.5.3
Stable tag: 1.1
Requires PHP: 5.6.16
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays your latest blip in a widget.  Requires a Blipfoto account.

== Description ==

Blipper Widget displays your latest entry (latest blip) on Blipfoto in a widget on your WordPress website.

Currently, Blipper Widget:

* displays the latest blip (image, title and username) in your Blipfoto account:
  * in a widget in a widget-enabled area
  * on a page
  * in a post
* displays the date of the blip (optional; on by default)
* includes a link from the image to the corresponding blip on the Blipfoto website (optional; off by default)
* displays your journal name with a link to your Blipfoto account (optional; off by default)
* displays a (powered by) link to the Blipfoto website (optional; off by default).

The image in the blip is not stored on your server: the widget links to the best quality image made available by Blipfoto.

= View the plugin =

If you'd like to see the plugin in action, you can visit [my WordPress site](https://pandammonium.org/) to see Blipper Widget showing my latest blip.

[The latest plugin code is available on GitHub](https://github.com/pandammonium/blipper-widget); note that this might be ahead of the current release of Blipper Widget.  The code for the current release is available in WordPress' SVN repository.

= Languages =

Currently, only (British) English is supported.  I'm afraid I don't yet know how to make other languages available.  If you'd like to help, let me know on [my Blipper Widget page on GitHub](https://github.com/pandammonium/blipper-widget).

= About Blipfoto =

[Blipfoto](https://www.blipfoto.com/) is a photo journal service, allowing users to post one photo a day along with descriptive text and tags; this constitutes a blip.  Users may comment on, star or favourite other users' blips.

= Additional requirements =

* Blipfoto account – [get one for free](https://www.blipfoto.com/account/signup)
* PHP [client URL (cURL) library](http://php.net/manual/en/book.curl.php)

= Disclaimer =

Blipper Widget is independent of and unendorsed by Blipfoto.

It does not allow you to:

* publish blips
* comment on, star or favourite blips
* follow other users.

This plugin is subject to [Blipfoto's developer rules](https://www.blipfoto.com/developer/rules); hence by installing, activating and/or using Blipper Widget, you consent to it performing actions involving your Blipfoto account, including, but not limited to, obtaining your account details (but never your password) and publishing your blips on your WordPress website.  You also consent to your username being displayed together with your image.  Following [WordPress' restrictions in their developer information](https://wordpress.org/plugins/developers/), no links to Blipfoto (or other external links) are included without your explicit consent.

You, the Blipfoto account holder, are responsible for the images shown on any website using the Blipper Widget with your Blipfoto or OAuth credentials.

Use of Blipper Widget does not affect the copyright of the photo.

The Blipfoto PHP SDK is used under [the MIT Licence](https://opensource.org/licenses/MIT).

= GDPR compliance =

Only your Blipfoto username, which is public information, is required, collected and stored by Blipper Widget.  Your Blipfoto username will be retained by this plugin until you delete or uninstall the plugin.  If you do not wish Blipper Widget to store your Blipfoto username, please do not use this plugin.

Your use of the plugin is not monitored by the plugin.

== How to use the shortcode ==

The Blipper Widget shortcode allows you to place your latest blip outside the widget areas in your theme, i.e. you can include it in a post or you can dedicate a whole page to it.

If you don't know how to use shortcodes, please refer to [the article on shortcodes in the WordPress codex](https://codex.wordpress.org/Shortcode).

The Blipper Widget shortcode, [blipper_widget], can be used singly or as a pair of start and end shortcodes:

* single use: `[blipper_widget]`
* paired use: `[blipper_widget] … [/blipper_widget]`.

The most straightforward use of the shortcode is the single use.  In the page or post editor, place the shortcode where you would like it to occur.  It will be displayed using the default Blipper Widget settings.

You can change some of the settings.  The settings you can change, with their default values [and alternative values where appropriate], are:

* `title                 = 'My latest blip'`
* `title-level           = 'h2'`
* `display-date          = 'show' ['hide']`
* `display-journal-title = 'hide' ['show']`
* `add-link-to-blip      = 'hide' ['show']`
* `display-powered-by    = 'hide' ['show']`
* `color                 = 'inherit'`
* `link-color            = 'initial'`

Here are some examples:

* Change the title text and heading level: `[blipper_widget title='The Daily Photo' title-level=h1]`
* Change the colour of the figure caption text: `[blipper_widget color=red]`
* Display your journal title in the figure caption: `[blipper_widget display-journal-title=show]`
* Display your journal title and change the link colour in the figure caption: `[blipper_widget display-journal-title=show link-color=blue]`

You can use the paired shortcodes to add text to your blip's figure caption:

* Insert text telling the user they can click on the image: `[blipper_widget add-link-to-blip=show]`Click on the photo to view this image on Blipfoto`[blipper_widget]`

== Installation ==

Follow [WordPress' instructions for installation of plugins](https://wordpress.org/support/article/managing-plugins/) to install this plugin.

= OAuth 2.0 authentication =

After installing and activating the Blipper Widget plugin, you will need to go to the plugin settings and follow the instructions to authenticate the plugin with Blipfoto.  The plugin will not work if you do not do this.

== Frequently Asked Questions ==

= Does the plugin need my Blipfoto username and password? =

The plugin needs your username so it knows which Blipfoto account to access.  OAuth 2.0 is used to authorise access to your Blipfoto account, which means Blipper Widget does not access your password.

= Why doesn't the plugin seem to do anything? =

* You need at least one blip in your Blipfoto account.
* Amend any necessary data indicated by error messages and try again.
* If you have refreshed your OAuth app credentials or access token at Blipfoto, you will need to update these details on the Blipper Widget settings page.
* You might have hit the rate limit set by Blipfoto; try again in fifteen minutes or so.  Tip: hide any widget-enabled areas that show the Blipper Widget on pages and posts with the Blipper Widget shortcode to reduce the number of requests to Blipfoto.

= Can I display the blips from another account in my widget? =

No.  You can only display the blips of one Blipfoto account, which must be your own account to which you have password access.
== Screenshots ==

1. The Blipper Widget settings page.
2. The widget form settings.
3. An example of the widget in use.

== Changelog ==

= 1.1 =

* Adds shortcode functionality: now you can show off your latest blip on a whole page or a post using a shortcode.
* Adds the Blipfoto username to the blip data in accordance with the Blipfoto developer rules.

= 1.0 =

* It's about time it got to a proper version number given that it seems pretty stable.  Let me know in the comments on [my Blipper Plugin GitHub page](https://github.com/pandammonium/blipper-widget) if it isn't!

== Upgrade notice ==

= 1.1 =

Update now to use the Blipper Widget shortcode in posts and pages.  Please note the change in consent in the Disclaimer section of the readme file.

== Known issues ==

[Known problems and enhancement requests](https://github.com/pandammonium/wp-blipper-widget/issues) are recorded on the Blipper Widget repository on GitHub.  Please add bug reports and suggestions there.

== Credits ==

This plugin is loosely based on [BlipPress](https://wordpress.org/plugins/blippress/) by [Simon Blackbourne](https://mobile.twitter.com/lumpysimon).  I very much appreciate having his work to guide me with the use of [the Blipfoto API](https://www.blipfoto.com/developer/api).

I used [Rotating Tweets](https://wordpress.org/plugins/rotatingtweets/) plugin to guide me with how to implement not only the settings page and the widget back-end but also the shortcode.

In addition, I used [WP-Spamshield](https://wordpress.org/plugins/wp-spamshield/) as a model of how to implement uninstallation code.
