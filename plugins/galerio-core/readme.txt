=== Galerio Core ===
Contributors: adasha
Tags: gallery, art, artist, exhibition, portfolio
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.9.0-alpha.1
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

= 0.9.0-alpha.1 =
* Groundwork for moving off Pods: Galerio's own definitions of its content types and taxonomies, not yet in use. Sites keep running on Pods. Pods is no longer a required plugin in WordPress's eyes, so it can be switched off when the time comes; a notice still warns while a site depends on it.
* `wp galerio fields check` also reports where what's registered differs from those definitions, and where a field's role limits do.
* `wp galerio fields repair` fixes two-way relationships whose sides disagree. It only reports while a site is on Pods.
* A native Settings → Galerio page and fields in a file's details, for sites on native data. They use the same stored values as Pods' page and fields.
* A native field editor in the pane under the editor, for sites on native data. Sites on Pods keep Pods' fields.
* Unpublished items can be chosen in relationship fields by people who can edit them, and say so.
* Fields have a group and a list of roles, matching the editor's boxes and limits today. The REST API refuses edits to a field limited to other roles, and lets people who can edit an item read and save its private fields.

= 0.8.2-alpha.1 =
* `wp galerio fields check` lists `media_intent` and the user's link to a profile as notes rather than problems. They're left out of Galerio for now, and their data stays in the database.

= 0.8.1-alpha.1 =
* The editor's pane of fields is now called "Work details", "Profile details" and so on, rather than "settings", and its title is larger and bolder.
* Removed the Focal point panel from the editor's sidebar. It set the same point as the image's own details, but saved straight to the image rather than with the item, which was easy to mistake for part of the item. Set an image's focal point in its details: in the Media Library, or in the pop-up that opens from the editor's Featured image.
* `wp galerio fields check` reports whether a site's stored fields can be read and kept without Pods. It changes nothing.

= 0.8.0-alpha.1 =
* `wp galerio terms move` moves a term, with the terms under it, to another taxonomy or under another parent, e.g. a site's staff types from Groups to Roles. The terms keep their IDs, names, slugs and items. `--dry-run` shows what would move. It stops before changing anything if a slug is taken in the other taxonomy, or if some items are of a type the other taxonomy isn't used for. The old and new addresses are listed, for redirects.
* `wp galerio terms merge` folds one term into another in the same taxonomy: its items and the terms under it go to the other, and it's deleted.
* `wp galerio terms tidy` drops a tag when the same item has a term under it, e.g. "Exhibition" on an item that's also a "Graduate Exhibition". The upper term's page still lists the item.
* Moving a term with terms under it to a taxonomy without parents (such as Labels) is refused. Item counts in the move's output include drafts and private items.

= 0.7.1-alpha.1 =
* Fixed: an item could appear on two pages of an archive, and another be missing, when several items had the same date or title, as items added by one import do. Items that sort equally now stay in a fixed order from page to page.

= 0.7.0-alpha.1 =
* Image focal points: each image can keep a point in view when it's cropped to fill a box, such as a card, a cover, a carousel slide or a grid tile. The point is kept with the image, so it holds wherever the image is shown. An image without one is centred, except as a profile's image: a portrait is kept a little above the middle, where a face usually is. A Cover block with its own focal point keeps it. Themes and plugins can change the defaults with the `galerio_focal_point_default` filter.
* A Focal point field in an image's details, in the Media Library, the media pop-up and the image's own edit screen: click the image where it should stay in view, or type how far across and down. Clear goes back to the default.
* A Focal point panel in the editor's sidebar, for the image that stands for the item (its featured image, or its own image as the item's settings choose). It's saved to the image straight away, not with the item, as the point belongs to the image wherever it's shown.

= 0.6.1-alpha.1 =
* Works take a date range and a date in words: Year to, and Date in words for things like "mid-19th century" or "600–800 CE". The date as shown (the words if there are any, otherwise the year or range such as 1830–32) is the new Date as shown field, which the work card, the single work pattern and feeds use; Schema.org gets a range as an interval. BCE years aren't held by the year fields yet, so write those as words.
* The `galerio_fields` property of the REST API can now be written as well as read, for importing content. A request that names a field that doesn't exist or is worked out from others is refused before anything is saved.
* Needs `wp galerio pods import` after updating, for the two new fields. Update Core before the theme: the theme's work patterns now show the date as shown.

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
