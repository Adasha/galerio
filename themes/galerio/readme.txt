=== Galerio ===
Contributors: adasha
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Version: 3.0.0-alpha.9
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

= 3.0.0-alpha.9 =
* The Documentation page's content is its own pattern (`galerio/template-documentation`), so it works in any layout, and a child theme can override it alone.
* The two-pane splits (an article beside its asides, a work beside its details, a project) stack when their own box is under 40rem wide, rather than at a fixed window width, so they suit a side menu and a top header alike. A "Stays in view" column stops sticking once stacked.
* Sticky columns and the side header add `--galerio-sticky-clearance` (default 0), so a child theme with a sticky top bar can keep them below it.

= 3.0.0-alpha.8 =
* The Main Menu style in the side menu shows submenus as a nested list under their item, indented a little and always open, instead of a pop-up. A menu across a header bar keeps its pop-ups.
* Search blocks match the theme's form fields: the body font, the buttons' rounded corners, a dark border, and a button as tall as the field.
* Empty related-item headings ("Featured in", "Authored articles"…) on sites running the Gutenberg plugin are fixed in Galerio Blocks 0.4.1-alpha.1.

= 3.0.0-alpha.7 =
* A work's date on its card and its own page is the date as shown: words such as "mid-19th century" or "600–800 CE" if the work has them, otherwise the year or range ("1830–32"). Needs Galerio Core 0.6.1-alpha.1 or later; update Core first. A card or page you've edited in the Site Editor keeps showing the plain year until you point its date at "Date as shown".

= 3.0.0-alpha.6 =
* Text is 18px on wide screens as well as phones, and the larger sizes are scaled down to match.
* Cards in mixed grids (a medium's or label's page) line up: narrow work cards get a square image and a smaller creator line, and empty lines are hidden. A work card leaves out the creator on that creator's own profile.
* The work page's terms table uses the details table's layout, so labels don't break on phones and empty rows are hidden. Long site titles hyphenate.
* The "Featured in" list on works lines up with its label. Images linked to their own file open in the lightbox on works, collections, articles and profiles.
* Archives get the Filters panel and page numbers, and works for sale show their price, availability and an enquiry button, with Galerio Core 0.6.0's catalogue switched on.
* Term buttons that wrap onto a second line no longer touch.
* The Work details pattern is no longer offered as a starter for every new page.
* Needs Galerio Core 0.6.0-alpha.1. A template or part saved in the Site Editor keeps its own copy: add the Archive Filters block to a saved archive template by hand.

= 3.0.0-alpha.5 =
* A work without a definitive image shows its featured image at the top of its page, instead of an empty box. Needs Galerio Core 0.5.1-alpha.1.

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
