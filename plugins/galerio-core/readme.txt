=== Galerio Core ===
Contributors: adasha
Tags: gallery, art, artist, exhibition, portfolio
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0-alpha
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Data layer for Galerio: creators, works, collections and articles for galleries, artists and publishers.

== Description ==

Galerio Core provides the content model behind the Galerio theme and blocks: creator profiles, works, collections (including exhibitions) and articles, with the relationships between them.

The content types are currently defined by a Pods package. Galerio Core includes a WP-CLI importer that installs that package correctly:

`wp galerio pods import <file>`

The importer repairs bidirectional relationships, which Pods otherwise links to the wrong fields when a package is imported onto a different site.

Galerio is in alpha. Expect breaking changes.

== Changelog ==

= Unreleased =
* Add content type aliases (Settings → Galerio content types): rename content types and taxonomies and change their addresses, with presets and redirects from old addresses.
* Add name tokens such as `{Collections}` and `{works}` for labels, Field block headings and binding prefixes, so they use the site's names. `{field}` in a Field block heading is the field's translated label.

= 0.2.0-alpha =
* Replace the original stub plugin, which registered post types that conflicted with the Pods package.
* Add `wp galerio pods import` and `wp galerio pods repair-relationships`.
