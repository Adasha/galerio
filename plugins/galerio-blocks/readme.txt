=== Galerio Blocks ===
Contributors: adasha
Tags: carousel, slider, gallery, exhibition, art
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0-alpha.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks for galleries, artists and publishers: a carousel, a gallery, exhibition dates and related items, for exhibitions, works and creators.

== Description ==

Galerio Blocks adds blocks for presenting creative work. It goes with the Galerio theme and Galerio Core, but works with any block theme and any content type.

= Carousel =

Shows items one after another as slides. Slides can come from:

* **Items with their images:** the latest items of a content type, optionally only those in chosen terms, or items picked by hand in your own order. Each slide shows the item's featured image, with its title, an optional excerpt and an optional button over it. The caption can sit in any corner, the side or the middle.
* **A Query Loop:** each item of the Query Loop inside becomes a slide, laid out by the loop's own template. Several slides can be in view at once.

Slides move by sliding or fading, with arrows, dots and keyboard arrow keys. Autoplay pauses while the pointer or keyboard focus is on the carousel, and visitors get a pause button; it starts paused for visitors who prefer reduced motion. Slides out of view can't be tabbed into. Without JavaScript the slides scroll sideways instead.

Themes can restyle the carousel with custom properties such as `--galerio-carousel-caption-background` (the full list is at the top of `src/carousel/style.scss`). Developers can change the query with the `galerio_carousel_query_args` filter, add transitions with `galerio_carousel_transitions`, and adjust the carousel engine ([Embla](https://www.embla-carousel.com/)) through the `galerio.carousel.options`, `galerio.carousel.plugins` and `galerio.carousel.init` JavaScript hooks.

= Gallery =

Shows images as a grid (cropped to a chosen shape), a masonry wall, or one after another at full width. The images can be the current item's own, such as a collection's or a work's images through Galerio Core, so the same block suits a template, or images chosen in the block. Order can be as saved, random, or automatic, which follows a collection's "Shuffle images" setting. Captions can show the image's caption, or its title and caption.

Images open in a lightbox with previous and next buttons, arrow keys, swiping and Escape to close. Without JavaScript, each image links to its full-size file. Themes can restyle it with `--galerio-gallery-gap`, `--galerio-lightbox-background` and `--galerio-lightbox-color`, and `galerio_gallery_images` filters the images.

= Exhibition Dates =

Shows when an exhibition or other collection opens and closes: both dates in the site's date format, or a short range such as "19 May – 16 June 2023" or "19–23 May 2023". It can add an Upcoming, On now or Past label, worked out from today's date in the site's time zone. It's hidden when the collection has no dates.

= Related Items =

Shows the items a relationship of the current item points to (a creator's works, a collection's exhibitors or curators, a work's collections) as cards: a grid, or a list with the image beside the title. Each card has the item's image, its title and an optional detail such as a work's year or creator. The heading follows the relationship's name, and the whole block, heading included, is hidden when nothing is related. Only published items show.

Galerio is in alpha. Expect breaking changes.

== Changelog ==

= 0.1.0-alpha.1 =
* The Carousel block, merging the Post Slider and Query Carousel prototypes. Either prototype's blocks can be transformed into it while the prototype's plugin is active.
