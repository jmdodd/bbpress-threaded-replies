=== bbPress Threaded Replies ===
Contributors: jmdodd
Tags: bbpress, replies, threaded, nested
Requires at least: 3.1.4
Tested up to: 3.3.1
Stable tag: 0.1

Add threaded (nested) reply functionality to bbPress.

== Description ==

bbPress Threaded Replies is based on comment-display functions from WordPress
and topic-display functions from bbPress. It currently gets all of its settings
from the Settings > Discussion panel of WordPress, inheriting comment threading
options. If comment threading (nesting) is not enabled, this plugin will not
load. bbPress running as a WordPress plugin is also required. 

Template files can be copied to theme directories. The TwentyEleven theme 
triggers a comment-style reply framework; its absence causes the plugin to
revert to the bbPress default table for each reply, indented. The plugin checks
first in the stylesheet and template directories before reverting to the default
plugin templates.

Filters are available for modification of plugin behavior. 

== Installation ==

1. Upload the directory `bbpress-threaded-replies` and its contents to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.

== Changelog ==

= 0.1 =
* Initial release. 

== Upgrade Notice ==

= 0.1 = 
* Initial release.

== Credits ==

Development funded, in part, by Ariel Meadow Stallings and the Offbeat Empire.
