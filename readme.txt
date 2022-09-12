=== Blipper Widget ===
Stable tag: 1.2.0
Contributors: pandammonium
Donate link: https://pandammonium.org/donate/
Tags: photos,photo,blipfoto,widget,daily photo,photo display,image display,365 project,images,image,shortcode,shortcodes
Requires at least: 4.3
Tested up to: 6.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays your latest blip in a widget. Requires a Blipfoto account.

== Description ==

Blipper Widget displays your latest entry (latest blip) on Blipfoto in a classic widget on your WordPress website.

***

NB Blipper Widget is a classic widget with a shortcode. Although there is no Blipper Widget block, Blipper Widget can still be used in block-enabled themes.

There are two ways to get Blipper Widget to work with block-enabled themes. The first is a workaround; the second uses existing Blipper Widget functionality:

1. Install [a plugin that enables classic widgets](https://en-gb.wordpress.org/plugins/search/classic+widgets/). This will allow you to add Blipper Widget to any widget-enabled location on your site.
2. Use the Blipper Widget shortcode in a WP [Shortcode block](https://wordpress.org/support/article/shortcode-block/). Example: [blipper_widget title='Blipper Widget' add-link-to-blip=show display-journal-title=show display-powered-by=show display-desc-text=show]

***

Currently, Blipper Widget:

* displays the latest blip (image, title and username) in your Blipfoto account:
  * in a widget in a widget-enabled area
  * on a page via a shortcode
  * in a post via a shortcode
* displays the date of the blip (optional; on by default)
* includes a link from the image to the corresponding blip on the Blipfoto website (optional; off by default)
* displays your journal name with a link to your Blipfoto account (optional; off by default)
* displays a (powered by) link to the Blipfoto website (optional; off by default).

The image in the blip is not stored on your server: the widget links to the best quality image made available by Blipfoto.

See the plugin in action on [my WordPress site](https://pandammonium.org/my-latest-photo/).

= About Blipfoto =

[Blipfoto](https://www.blipfoto.com/) is a photo journal service. It allows the user to post one photo a day along with descriptive text and tags; this constitutes a blip. The users may comment on other users' blips, depending in the user's settings, and award a star or heart to other users' blips.

Blipper Widget is independent of and unendorsed by Blipfoto.

It does not allow you to:

* publish blips
* comment on blips
* award stars or hearts to blips
* follow other users.

This plugin is subject to [Blipfoto's developer rules](https://www.blipfoto.com/developer/rules); hence by installing, activating and/or using Blipper Widget, you consent to it performing actions involving your Blipfoto account, including, but not limited to, obtaining your account details (but never your password) and publishing your blips on your WordPress website. You also consent to your username being displayed together with your image. Following [WordPress' restrictions in their developer information](https://wordpress.org/plugins/developers/), no links to Blipfoto (or other external links) are included without your explicit consent.

You, the Blipfoto account holder, are responsible for the images shown on any website using the Blipper Widget with your Blipfoto and Blipfoto OAuth credentials.

Use of Blipper Widget does not affect the copyright of the photo.

The Blipfoto PHP SDK is used under [the MIT Licence](https://opensource.org/licenses/MIT).

= GDPR compliance =

Only your Blipfoto username, which is public information, and your Blipfoto access token are required, collected and stored by Blipper Widget. Your Blipfoto username and access token will be retained by this plugin until you delete or uninstall the plugin. If you do not wish Blipper Widget to store your Blipfoto username, please do not use this plugin.

Your use of the plugin is not monitored by the plugin.

== Installation ==

Follow [WordPress' instructions for installation of plugins](https://wordpress.org/support/article/managing-plugins/#installing-plugins-1) to install this plugin, then authenticate the plugin using OAuth.

= OAuth 2.0 authentication =

After installing and activating the Blipper Widget plugin, you will need to go to the plugin settings and follow the instructions to authenticate the plugin with Blipfoto. The plugin will not work if you do not do this.

= Additional requirements =

* Blipfoto account – [get one for free](https://www.blipfoto.com/account/signup)
* PHP [client URL (cURL) library](http://php.net/manual/en/book.curl.php)

== Frequently Asked Questions ==

= Does the plugin need my Blipfoto username and password? =

Yes and no. The plugin needs your username so it knows which Blipfoto account to access. OAuth 2.0 is used to authorise access to your Blipfoto account, which means Blipper Widget does not access your password.

= Why doesn't the plugin seem to do anything? =

* You need to install [a plugin that enables classic widgets](https://en-gb.wordpress.org/plugins/search/classic+widgets/) to be used on your site.
* You need to add the Blipper Widget shortcode to a Shortcode block.
* You need at least one blip in your Blipfoto account.
* You may have received an error message. Amend any necessary data indicated and try again.
* If you have refreshed your OAuth app credentials or access token at Blipfoto, you will need to update those details on the Blipper Widget settings page.
* You might have hit [the rate limit set by Blipfoto](https://www.blipfoto.com/developer/api/guides/rate-limits); try again in fifteen minutes or so. Tip: hide any widget-enabled areas that show the Blipper Widget on pages and posts with the Blipper Widget shortcode to reduce the number of requests to Blipfoto.

= Can I display the blips from another account in my widget? =

No. You can only display the blips of one Blipfoto account, which must be your own account to which you have password access.

== Screenshots ==

1. The Blipper Widget settings page.
2. The widget form settings.
3. An example of the widget in use.

== Changelog ==

= 1.2.0 =

* Refactors the blipper widget code.
* Improves the readme text and formatting.

[More details](https://pandammonium.org/blipper-widget-1-2-0-is-released/)

[Previous version history](https://plugins.trac.wordpress.org/browser/blipper-widget/trunk/changelog.txt)

= Known issues =

[Known problems and enhancement requests](https://github.com/pandammonium/blipper-widget/issues) are recorded on the Blipper Widget repository on GitHub. Please add bug reports and suggestions there.

== Upgrade notice ==

= 1.1.3 =

Update now to ensure compatibility with WP 6.0.1 (via the Blipper Widget shortcode).

= 1.1 =

Update now to use the Blipper Widget shortcode in posts and pages. Please note the change in consent in the Disclaimer section of the readme file.

== Usage ==

In classic themes and in block themes with a classic-widgets plugin installed, Blipper Widget is added to widget areas in the same way as any other classic widget. Your latest blip will be styled either according to your CSS or to the widget's settings.

In block-enabled themes, Blipper Widget is added with the shortcode. It can be added in a Shortcode block or anywhere else a shortcode can be added in WP.

The shortcode is also used to place Blipper Widget outside widget areas, such as posts and pages in both classic and block-enabled themes.

Using the shortcode will style tour latest blip according to your CSS.

= How to use the shortcode =

NB If you don't know how to use WordPress shortcodes, please refer to [the WordPress codex article on shortcodes](https://codex.wordpress.org/Shortcode). If you don't know how to use the Shortcode block, please refer to [the WordPress support article on Shortcode blocks](https://wordpress.org/support/article/shortcode-block/).

The Blipper Widget shortcode `[blipper_widget]` can be used singly or as a pair of start and end shortcodes:

* single use: `[blipper_widget]`
* paired use: `[blipper_widget] … [/blipper_widget]`

The most straightforward use of the shortcode is single use. In the page or post editor, place the shortcode where you would like it to occur. Alternatively, place the shortcode in a Shortcode block.

There are settings you can change; these, with their default values [and alternative values where appropriate], are:

* `title                 = 'My latest blip'`
* `title-level           = 'h2'`
* `display-date          = 'show' ['hide']`
* `display-journal-title = 'hide' ['show']`
* `add-link-to-blip      = 'hide' ['show']`
* `display-powered-by    = 'hide' ['show']`
* `color                 = 'inherit'`
* `link-color            = 'initial'`
* `display-desc-text     = 'hide' ['show']`

**Examples**

*Change the text and heading level of the title:*

`[blipper_widget title='The Daily Photo' title-level=h1]`

*Change the colour of the figure-caption text:*

`[blipper_widget color=red]`

*Display your journal title in the figure caption:*

`[blipper_widget display-journal-title=show]`

*Display your journal title and change the link colour in the figure caption:*

`[blipper_widget display-journal-title=show link-color=blue]`

You can use the paired shortcodes to add text to your blip's caption like this:

`[blipper_widget]Blipfoto allows you to save your life – with a daily photo.[/blipper_widget]`

`[blipper_widget add-link-to-blip=show]Click on the photo to view this image on Blipfoto[/blipper_widget]`

= How to use CSS classes to style Blipper Widget =

The shortcode version of Blipper Widget always uses CSS to style the blip. The classic widget can be styled using either CSS or the Blipper Widget settings form. Blipper Widget provides various CSS selectors to style its HTML output.

A blip comprises a photo, an optional title, optional text and optional tags. Blipper Widget adds metadata including your username, but does not display the blip's tags. There is [an example of how to use CSS to style a blip](https://pandammonium.org/wordpress/wordpress-dev/blipper-widget/blipper-widget-example/#css-code) on [my Blipper Widget Example page](https://pandammonium.org/wordpress/wordpress-dev/blipper-widget/blipper-widget-example/). Blipfoto provides the HTML for the blip's text.

You can ignore the provided CSS selectors to allow your theme to style your blip. Alternatively, you can use them to give your blips a unique look. Many blippers like to see their photos on a dark background, reflecting how Blipfoto's site originally looked.

== Acknowledgements ==

This plugin is loosely based on [BlipPress](https://wordpress.org/plugins/blippress/) by [Simon Blackbourne](https://twitter.com/lumpysimon). I very much appreciate having his work to guide me with the use of [the Blipfoto API](https://www.blipfoto.com/developer/api).

I used [Rotating Tweets](https://wordpress.org/plugins/rotatingtweets/) as a guide to implementing not only the settings page and the widget back-end, but also the shortcode.

In addition, I used [WP-Spamshield](https://wordpress.org/plugins/wp-spamshield/) as a model of how to implement uninstallation code.

I had help from an anonymous person with some of the [jQuery](https://jquery.com/) code.

The [WP Readme](https://wpreadme.com) tool has been invaluable for checking the formatting of this readme file.
