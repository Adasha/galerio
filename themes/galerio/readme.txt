=== Galerio ===
Contributors: adasha
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Version: 3.0.0-alpha.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A block theme for artists, galleries, collectives and publishers.

== Description ==

Galerio presents creators, works, collections and articles: a page for each, lists of each, and the links between them, such as a creator's works or the collections a work is in.

* Requires the Galerio Core plugin, which provides the content and its fields. Galerio Core currently needs Pods.
* Two layouts: a side menu (the standard) or a top header.
* Style variations: Gallery White and Dark Room colours, and Editorial typography.
* Each page's content is a pattern, so a child theme can change one page without copying its template. See the Galerio Child Starter theme.
* A Collection card template part draws each collection on the collections page, and can draw the slides of a Galerio Carousel too, so one edit in the Site Editor changes both. Its height is set where it's used: `--galerio-collection-card-height` for a list, the carousel's own Height for slides.
* Images open in a lightbox. On works, collections, articles and profiles, so do images linked to their own file (WordPress's old "Link to: Media file"); links to pages stay, and a profile's portrait keeps its link. The `galerio_lightbox_post_types` filter changes which content types this covers.
* Translatable, and the wording follows the names a site gives its content types (e.g. Exhibitions for collections) under Settings → Galerio content types.

== Changelog ==

= 3.0.0-alpha.4 =
* A "Stays in view" style for Column blocks: the column stays in view as the page scrolls, and a column taller than the window first scrolls to its end. The Single-item collection (project) template's side panel uses it.
* The side menu stays still as the page scrolls, instead of moving up a little before it sticks.
* Roomier works archive: wider columns, and work cards split half image, half text.

= 3.0.0-alpha.3 =
* Card template parts in a new Card area: Work, Profile, Article and Collection cards, used by the archives, Galerio Query Loops and Carousel slide designs. Edit a card once and every list follows. The generic Card part shows each item with its own type's card, for lists that mix types.
* The single templates show the current item's gallery, exhibition dates and related items (with Galerio Blocks), as related Query Loops drawing each type's card. A template gallery steps aside when the item's content has its own.
* New templates: term archive, search and page, plus a Blank template for collections and articles designed in the editor. Archives link to their next pages. Content type and taxonomy templates are named after the site's types.
* New patterns: Collection layout (the standard collection page as blocks to rearrange), Site presents… (for guest collections) and Work details.
* Items without a featured image fall back to their own image, or show a plain colour band.
* Contrast fixes to the default palette; the default palette passes WCAG AA.
* Main menu highlight on hover, a tidier phone header (no tagline, the title gives way to the menu button), list titles without underlines, and Site Title styles to keep words whole or hyphenate.

= 3.0.0-alpha.2 =
* A Buttons style for the Galerio Field block, matching the term buttons, for lists such as a collection's exhibitors.

= 3.0.0-alpha.1 =
* Breaking: requires Galerio Core. The templates show fields through Galerio's block bindings and Field block instead of Pods shortcodes, blocks and Pods Templates.
* Breaking: the font presets are named for their role, heading (Arvo) and body (Lato). The arvo and lato presets remain for existing content, but child themes and custom styles should use heading and body.
* Each template's content is a translatable pattern (galerio/template-*), and the footer credit is its own pattern.
* Wording follows the site's content type names.
* Responsive layout: a 16rem menu column, a header bar on phones, and a work details table that fits narrow screens.
* New Top header layout pattern and header-bar template part.
* Gallery White, Dark Room and Editorial style variations.
* Spacing and font-size scales of the theme's own, under WordPress's slugs.
* Article templates, and templates for each content type's archive.
* The 404 page links to the site's own home page.

= 2.0.1a =
* Template improvements.

= 2.0.0a =
* Re-implemented as a block theme.

= 1.0.0 =
* Initial release.

== Copyright ==

Galerio, Copyright 2026 Adasha.
Galerio is distributed under the terms of the GNU GPL.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

Galerio began from the Full Site Editing starter theme by Carolina Nymark, https://fullsiteediting.com.

== Resources ==

* Arvo, Copyright 2010-2013 Anton Koovit. License: SIL Open Font License, 1.1, https://openfontlicense.org. Source: https://fonts.google.com/specimen/Arvo
* Lato, Copyright 2010-2014 tyPoland Łukasz Dziedzic. License: SIL Open Font License, 1.1, https://openfontlicense.org. Source: https://fonts.google.com/specimen/Lato
* Screenshot: demo content generated for Galerio. License: GPLv2 or later.
