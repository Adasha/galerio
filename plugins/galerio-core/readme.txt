=== Galerio Core ===
Contributors: adasha
Tags: gallery, art, artist, exhibition, portfolio
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.0-alpha.1
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

= 0.3.0-alpha.1 =
* Field API: every field is declared once (`galerio_fields()`) and read with `galerio_get_field()` and `galerio_get_related()`, through a storage adapter. Pods is the adapter for now.
* `galerio/field` block bindings: Paragraph, Heading, Image and Button blocks show any field, with optional fallback, prefix, suffix, link and on/off switch.
* The Field block (`galerio/field-display`), for formatted text, lists and sections that hide when empty.
* The content model from the agreed proposal: renamed fields, `links` and `website` as a link list field type (`galerio_link`), `members` and `member_of`, and hierarchical media.
* `wp galerio pods export|diff|import` and `wp galerio fields list`, plus a generated field reference in the README.
* Content type aliases (Settings → Galerio content types): rename content types and taxonomies and change their addresses, with presets and redirects from old addresses.
* Name tokens such as `{Collections}` and `{works}` for labels, Field block headings and binding prefixes, so they use the site's names. `{field}` in a Field block heading is the field's translated label.
* `galerio_expand_names()`, for themes to put the site's type names into their own text.
* Bundled translations load from `languages/`.

= 0.2.0-alpha =
* Replace the original stub plugin, which registered post types that conflicted with the Pods package.
* Add `wp galerio pods import` and `wp galerio pods repair-relationships`.
