=== Shy Posts ===
Contributors: topher1kenobe
Tags: posts
Requires at least: 5.3
Requires PHP: 5.6
Tested up to: 7.1
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Provides a checkbox on a post admin page to allow you to say that THIS post should not appear on the homepage blog loop.

== Description ==

Provides a checkbox on a post admin page to allow you to say that THIS post should not appear on the homepage blog loop.

This only works if your blog is on your homepage.

Works in both the block editor (in the post status panel of the sidebar) and the Classic Editor (in the Publish box).

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the `shy-posts` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Create new or Edit a Post.

== Frequently Asked Questions ==

= Why don't you have more questions here? =

I haven't been asked any yet.  :)

== Screenshots ==

1. The checkbox in the Publish box on the Post editing page (Classic Editor)

== Changelog ==

= 2.0.0 =
* Block editor support: the checkbox now appears in the post status panel of the sidebar
* Register the shy_post meta for the REST API
* Keep the stored list in sync from post meta changes, so classic, block editor and deleted posts are all handled the same way
* Requires WordPress 5.3 or later
* Fix activation hook never firing, so existing shy posts are now picked up on activation
* Store shy post IDs in an option instead of a transient, so they can't be evicted by an object cache
* Rebuild the stored list from post meta automatically if it's missing (this also migrates from 1.3.x)
* Fix PHP notice when saving a post with the checkbox unchecked
* Fix malformed checkbox HTML
* Skip autosaves and revisions on save, and stop storing duplicate IDs
* Remove permanently deleted posts from the stored list
* Merge with existing post__not_in exclusions on the homepage instead of replacing them
* Remove unused composer.json

= 1.3.3 =
* Added load_plugin_textdomain to allow translations
* Check for WP 4.7

= 1.3.2 =
* Check for WP 4.0

= 1.3.1 =
* Minor bug fix regarding storage method

= 1.3 =
* Store shy post IDs in a transient
* Make exclusion query use transient
* New query is much MUCH faster
* Various input sanitizations

= 1.2 =
* Make the checkbox be in the Publish box instead of a custom meta box

= 1.1 =
* Change pre_get_posts filter to an action
* Change $query->is_home() to is_front_page()
* remove !is_admin() check (is_front_page takes care of that)

= 1.0 =
* Initial release.
