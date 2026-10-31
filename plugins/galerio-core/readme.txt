=== Galerio Core ===
Contributors: adasha
Tags: gallery, art, artist, exhibition, portfolio
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.0-alpha.1
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

= 0.6.0-alpha.1 =
* Catalogue, for sites that sell: availability, price (or price on application), currency, editions, framed, inventory number and a dealer link on works, and Represented by on profiles. It's a switch on Settings → Galerio → Catalogue, off by default; while it's off the fields read as empty everywhere and their boxes leave the editor. Prices show only while a work is available or reserved, and go into Schema.org as an offer.
* Enquiries: an "Enquire about this work" form on works that are for sale, sent by email to the Enquiries email on Settings → Galerio (or the site's administration email). Nothing is stored. A hidden field and a minimum fill time keep out spam. Works sold through a dealer link to the dealer instead.
* Archives: a Filters panel with a text search, a dropdown per taxonomy and a sort order (newest, oldest, title, and year for works), applied as soon as it changes; page numbers; and a page size for each content type on Settings → Reading. Filtered pages aren't indexed. The new Archive Filters block draws the panel.
* Image precedence: when an item has both a featured image and its own image (a work's definitive image, a collection's first image), a switch under the Featured image panel chooses which one cards, archives, carousels, feeds, shared links and Schema.org use. It starts on the featured image. A profile's portrait is used only when chosen, and only while the profile shows its portrait. The work's own page always shows its definitive image.
* Feeds: each item carries its image, its creators, curators or authors as the author, and a line of key details. The lightbox's markup is removed from every feed. The exhibitions feed is in opening-date order. Settings → Reading can add articles to the main feed (off by default) and leave content types, taxonomies and author pages out of the sitemap.
* The switches on Settings → Galerio now work, as display switches: featured images on an item's own page, nationality, birth and death years, portraits on a profile's own page, and collective fields. Fields stay in the editor with their values.
* Field bindings take `"omitCurrent": true`: a list leaves out the item the page is about, such as a work card's creator on that creator's own profile.
* The editor's meta box pane is named after the content type, e.g. "Work settings", using the site's own names.
* Accolades fields start as one line and grow as you type.
* Settings → Galerio content types: narrower fields, and the tables stack on phones instead of scrolling sideways.
* Needs `wp galerio pods import` after updating, for the new fields and settings.

= 0.5.1-alpha.1 =
* Field bindings take `"fallback": "featured_image"`: an empty image field shows the post's featured image instead. The Galerio theme uses it for a work's main image.

= 0.5.0-alpha.1 =
* An item without a featured image falls back to its own image: a work's definitive image, a profile's portrait, or the first of a collection's or article's images. Each item has a switch for it; it starts off for profiles and on for the rest.

= 0.4.1-alpha.1 =
* The Field block is named "Galerio Field" in the inserter.
* A Field block in a theme's Buttons style always shows a list, one link per item.

= 0.4.0-alpha.1 =
* Page styles: a collection, article or page can replace the theme's fonts and palette colours on that page only, plus custom CSS for the rest. Fonts can come from the Font Library without activating them for the whole site. Set in a closed "Page style" panel in the editor's sidebar, previewed as you edit. Only people who can edit CSS can change the CSS.
* Galerio's content types have their own permissions (`edit_galerio_works`, `publish_galerio_profiles`, …), under the names PublishPress Capabilities uses, so roles can write without publishing, with or without that plugin. Administrators and editors get every permission once. `galerio_type_capabilities` switches it off.
* Schema.org data (JSON-LD) on works, profiles and collections. A work's type comes from its medium (Painting, Photograph, Music composition, Film, …), set on each medium's edit screen or guessed from its name. Profiles are a Person or, for collectives, an Organization, and leave out what they hide on the site. Collections with dates are an ExhibitionEvent. `galerio_schema_enabled` switches it off.
* New fields: `collection.curators`, and private valuation fields on works (`valuation`, `valuation_date`, `valued_by`, `valuation_notes`) that only editors and administrators see. The field registry has a `number` type.
* A notice tells administrators when Pods isn't active, or when Galerio's content types haven't been imported.
* The Pods package is included in the plugin at `package/galerio.json`, for `wp galerio pods import`.

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
