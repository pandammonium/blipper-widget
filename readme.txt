=== Blipper Widget ===
Stable tag: 1.1.3
Contributors: pandammonium
Donate link: https://pandammonium.org/donate/
Tags: photos,photo,blipfoto,widget,daily photo,photo display,image display,365 project,images,image
Requires at least: 4.3
Tested up to: 6.0.1
Requires PHP: 5.6.16
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Displays your latest blip in a widget. Requires a Blipfoto account.

== Description ==

Blipper Widget displays your latest entry (latest blip) on Blipfoto in a classic widget on your WordPress website.

***

NB Blipper Widget does not work with WordPress Gutenberg block widgets (included in WP 5.8 and later).

This may change in the future; until then, there are two ways to get Blipper Widget to work with block-enabled themes. The first is a workaround; the second uses existing Blipper Widget functionality:

1. Install [a plugin that enables classic widgets](https://en-gb.wordpress.org/plugins/search/classic+widgets/). This will allow you to add Blipper Widget to any widget-enabled location on your site.
2. Use the Blipper Widget shortcode (details below) in a WP [Shortcode block](https://wordpress.org/support/article/shortcode-block/).

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
* comment on or award stars or hearts to blips
* follow other users.

This plugin is subject to [Blipfoto's developer rules](https://www.blipfoto.com/developer/rules); hence by installing, activating and/or using Blipper Widget, you consent to it performing actions involving your Blipfoto account, including, but not limited to, obtaining your account details (but never your password) and publishing your blips on your WordPress website. You also consent to your username being displayed together with your image. Following [WordPress' restrictions in their developer information](https://wordpress.org/plugins/developers/), no links to Blipfoto (or other external links) are included without your explicit consent.

You, the Blipfoto account holder, are responsible for the images shown on any website using the Blipper Widget with your Blipfoto and Blipfoto OAuth credentials.

Use of Blipper Widget does not affect the copyright of the photo.

The Blipfoto PHP SDK is used under [the MIT Licence](https://opensource.org/licenses/MIT).

= GDPR compliance =

Only your Blipfoto username, which is public information, and your Blipfoto access token are required, collected and stored by Blipper Widget. Your Blipfoto username and access token will be retained by this plugin until you delete or uninstall the plugin. If you do not wish Blipper Widget to store your Blipfoto username, please do not use this plugin.

Your use of the plugin is not monitored by the plugin.

== Installation ==

Follow [WordPress' instructions for installation of plugins](https://wordpress.org/support/article/managing-plugins/) to install this plugin, then authenticate the plugin using OAuth.

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
* Amend any necessary data indicated by error messages and try again.
* If you have refreshed your OAuth app credentials or access token at Blipfoto, you will need to update those details on the Blipper Widget settings page.
* You might have hit [the rate limit set by Blipfoto](https://www.blipfoto.com/developer/api/guides/rate-limits); try again in fifteen minutes or so. Tip: hide any widget-enabled areas that show the Blipper Widget on pages and posts with the Blipper Widget shortcode to reduce the number of requests to Blipfoto.

= Can I display the blips from another account in my widget? =

No. You can only display the blips of one Blipfoto account, which must be your own account to which you have password access.

== Screenshots ==

1. The Blipper Widget settings page.
2. The widget form settings.
3. An example of the widget in use.

== Changelog ==

= 1.1.3 =

* Tests compatibility with WordPress 6.0.1. One of the following two methods must be used for Blipper Widget to work with block-enabled themes:
  * install [a plugin that enables classic widgets](https://en-gb.wordpress.org/plugins/search/classic+widgets/) to be used
  * use the shortcode.
* Tests Blipper Widget with the [Classic Widgets](https://en-gb.wordpress.org/plugins/classic-widgets/) plugin with block themes.
* Tests Blipper Widget shortcode with block themes.

[More details](https://pandammonium.org/?p=18422).

= 1.1.2 =

* Tests compatibility with WP 5.7.
* Improves error handling.

[More details](https://pandammonium.org/blipper-widget-1-1-2-is-released/).

= 1.1.1 =

* Optionally displays the blip's descriptive text, if there is any, for the shortcode version of Blipper Widget only (i.e. the descriptive text is not shown for the widget).
* Separates the styling of the widget and the shortcode so that the user can either use the settings on the widget form or CSS to style the widget version of Blipper Widget. The shortcode version always uses CSS.
* Adds classes to the blip tags so that they can be easily styled using the Additional CSS component of the Customiser or some other stylesheet.
* Fixes display of widgets styled by the widget settings form.
* Blipper Widget output checked and validated by [Nu Html Checker](https://validator.w3.org/nu/) by [W3C](https://www.w3.org/).
* Readme file checked and validated by [WP Readme](https://wpreadme.com/) by [@justnorris](https://twitter.com/justnorris).
* Tested for compatibility with WP 5.6.

[Previous version history](https://plugins.trac.wordpress.org/browser/blipper-widget/trunk/changelog.txt)

= Known issues =

[Known problems and enhancement requests](https://github.com/pandammonium/wp-blipper-widget/issues) are recorded on the Blipper Widget repository on GitHub. Please add bug reports and suggestions there.

In addition, this plugin is not fully developed for use with Gutenberg blocks. There are workarounds to this, so please consider using one of these as outlined above.

== Upgrade notice ==

= 1.1.3 =

Update now to ensure compatibility with WP 6.0.1 (via the Blipper Widget shortcode).

= 1.1 =

Update now to use the Blipper Widget shortcode in posts and pages. Please note the change in consent in the Disclaimer section of the readme file.

== Usage ==

In classic themes and in block themes with a classic-widgets plugin installed, Blipper Widget is added to widget areas in the same way as any other classic widget. Your latest blip will be styled either according to your CSS or to the widget's settings.

In block-enabled themes, Blipper Widget is added with the shortcode. It can be added in a Shortcode block or anywhere else a shortcode can be added in WP.

The shortcode is also used to place Blipper Widget outside widget areas, such as oosts and pages in both classic and block-enabled themes.

Using the shortcode will style tour latest blip according to your CSS.

= How to use the shortcode =

NB If you don't know how to use WordPress shortcodes, please refer to [the WordPress codex article on shortcodes](https://codex.wordpress.org/Shortcode). If you don't know how to use the Shortcode block, please refer to (the WordPress support article on Shortcode blocks)[https://wordpress.org/support/article/shortcode-block/].

The Blipper Widget shortcode, `[blipper_widget]`, can be used singly or as a pair of start and end shortcodes:

* single use: `[blipper_widget]`
* paired use: `[blipper_widget] … [/blipper_widget]`.

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
* `display-desc-text     = 'hide' ['show']`.

Here are some examples:

* Change the text and heading level of the title: `[blipper_widget title='The Daily Photo' title-level=h1]`
* Change the colour of the figure-caption text: `[blipper_widget color=red]`
* Display your journal title in the figure caption: `[blipper_widget display-journal-title=show]`
* Display your journal title and change the link colour in the figure caption: `[blipper_widget display-journal-title=show link-color=blue]`.

You can use the paired shortcodes to add text to your blip's caption:

* Add instructions: `[blipper_widget add-link-to-blip=show]Click on the photo to view this image on Blipfoto[/blipper_widget]`.
* Add information: `[blipper_widget]Blipfoto allows you to save your life -- with a daily photo.[/blipper_widget]`.

= How to use CSS classes to style Blipper Widget =

The shortcode version of Blipper Widget always uses CSS to style the blip. The classic widget can be styled using either CSS or the Blipper Widget settings form.

The following CSS selectors define the style and layout of the resulting figure:

* `div.bw-blip`: the outermost container for the blip
* `div.bw-blip > figure.bw-figure`: the figure containing the blip's photo and caption
* `div.bw-blip > figure.bw-figure > img.bw-image`: the blip's photo
* `div.bw-blip > figure.bw-figure > figcaption.bw-caption`: the blip's caption containing a header, some content and a footer (if all information is provided and its display is set to `show`)
* `div.bw-blip > figure.bw-figure > figcaption > header.bw-caption-header`: the caption header containing the date and title of the blip, and your Blipfoto username
* `div.bw-blip > figure.bw-figure > figcaption.bw-caption > div.bw-caption-content`: the caption content given by the text between the opening Blipper Widget shortcode and the closing Blipper Widget: `[blipper_widget]this is the content[/blipper_widget]`. Single-use shortcodes and the classic widget to not generate caption content
* `div.bw-blip > figure.bw-figure > figcaption > footer.bw-caption-footer`: the caltion footer containing the optional link back to your journal and the optional 'powered-by' link to the Blipfoto home page.
* `div.bw-blip > figure.bw-figure > figcaption.bw-caption-link`: the links to your Blipfoto journal and to the Blipfoto home page
* `div.bw-blip > div.bw-text`: the written component of the blip.

You can ignore these classes and allow your blip to be styled according to your theme or other changes you've made using the Customiser, a plugin or other stylesheet. Alternatively, you can use them to give your blips a unique look. Many blippers like to see their photos on a dark background, reflecting how Blipfoto's site looked originally.

== Credits ==

This plugin is loosely based on [BlipPress](https://wordpress.org/plugins/blippress/) by [Simon Blackbourne](https://twitter.com/lumpysimon). I very much appreciate having his work to guide me with the use of [the Blipfoto API](https://www.blipfoto.com/developer/api).

I used [Rotating Tweets](https://wordpress.org/plugins/rotatingtweets/) as a guide to implementing not only the settings page and the widget back-end, but also the shortcode.

In addition, I used [WP-Spamshield](https://wordpress.org/plugins/wp-spamshield/) as a model of how to implement uninstallation code.

I also had help from an anonymous person with some of the jQuery code.
