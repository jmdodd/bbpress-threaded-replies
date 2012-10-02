=== bbPress Threaded Replies ===
Contributors: jmdodd
Tags: bbpress, replies, threaded, nested
Requires at least: WordPress 3.4, bbPress 2.0
Tested up to: 3.4
Stable tag: 0.4.2

Add threaded (nested) reply functionality to bbPress.

== Description ==

bbPress Threaded Replies is based on comment-display functions from WordPress
and topic-display functions from bbPress. Settings can be adjusted on the bbPress
Forums options page of WordPress. bbPress running as a WordPress plugin is required. 

The plugin checks first in the stylesheet and template directories before reverting 
to the default plugin templates.

== Installation ==

1. Upload the directory `bbpress-threaded-replies` and its contents to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Visit the bbPress Forums panel to adjust threading settings.

== Changelog ==

= 0.4.2 =
* Change constants STYLESHEETPATH and TEMPLATEPATH to corresponding functions.
* Fix divide-by-zero in replies_per_page.
* TinyMCE DOM movement fix (props cosmic_invaders).

= 0.4.1 =
* SVN fix.

= 0.4 =
* Update for bbPress 2.1; retain backwards compat to 2.0.
* Add separate settings section on Forums options page.
* Update cache layout for complete flush on settings update.
* Remove twentyeleven theme; rename templates to avoid collisions.
* Add support for theme-compat.
* Fix behavior of thread split.
* Fix default_replies_page reversal.

= 0.3 =
* Add caching for SQL operations.
* Use Walker_Comment instead of custom Walker class.
* Refactor default callback as a function.
* Template fix: improve validation of default templates.

= 0.2 =
* Template fix: delete leading empty line.
* SQL fix: change wp_postmeta to {$wpdb->postmeta} in queries.

= 0.1 =
* Initial release. 

== Upgrade Notice ==

= 0.4 =
* bbPress 2.0 and 2.1 compatible. Template files have been renamed to avoid filename collisions.

= 0.2 =
* SQL and template updates. Fixes missing pagination for blogs without default 'wp_' database prefix.

= 0.1 = 
* Initial release.

== Credits ==

Development funded, in part, by Ariel Meadow Stallings and the Offbeat Empire.
