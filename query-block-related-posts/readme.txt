=== Query Block Related Posts ===
Contributors: bradhogan
Tags: gutenberg, query loop, query block, related posts, block editor
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends the core Query Loop block with options to hide the current post and show related posts based on the current post type and shared taxonomy terms.

== Description ==

Query Block Related Posts extends the core Query Loop block with two new settings in the block sidebar:

- Hide the current post
- Show related posts

When enabled on a Query Loop block inside a singular template, the plugin can:

- exclude the currently viewed post from the results
- limit results to the same post type as the current post
- match related posts using the current post's shared public taxonomy terms
- preserve the native Query Loop block rendering and frontend styles

This plugin works with:

- standard posts
- custom post types
- public taxonomies attached to those post types

== Installation ==

1. Upload the `query-block-related-posts` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Edit a template or post that contains a Query Loop block.
4. Select the Query Loop block and open the block settings sidebar.
5. Enable:
   - Hide the current post
   - Show related posts

== Frequently Asked Questions ==

= Does this work with custom post types? =

Yes. When "Show related posts" is enabled, the query is constrained to the current singular post's post type and matched against shared public taxonomies attached to that post type.

= What happens if the current post has no taxonomy terms? =

The block still works. If no related taxonomy terms are found, the query continues without adding related taxonomy filters.

= Does this change the Query Loop block markup? =

No. The plugin preserves the native Query Loop block rendering so frontend styles and layout options continue to work as expected.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added "Hide the current post" toggle.
* Added "Show related posts" toggle.
* Supports posts and custom post types.
* Preserves native Query Loop rendering and styles.

== License ==

This plugin is licensed under the GPLv2 or later.
