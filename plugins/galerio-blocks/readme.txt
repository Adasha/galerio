=== Galerio Blocks ===
Contributors: adasha
Tags: carousel, slider, gallery, exhibition, art
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0-alpha.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks for galleries, artists and publishers, starting with a carousel of exhibitions, works or any other items.

== Description ==

Galerio Blocks adds blocks for presenting creative work. It goes with the Galerio theme and Galerio Core, but works with any block theme and any content type.

= Carousel =

Shows items one after another as slides. Slides can come from:

* **Items with their images:** the latest items of a content type, optionally only those in chosen terms, or items picked by hand in your own order. Each slide shows the item's featured image, with its title, an optional excerpt and an optional button over it. The caption can sit in any corner, the side or the middle.
* **A Query Loop:** each item of the Query Loop inside becomes a slide, laid out by the loop's own template. Several slides can be in view at once.

Slides move by sliding or fading, with arrows, dots and keyboard arrow keys. Autoplay pauses while the pointer or keyboard focus is on the carousel, and visitors get a pause button; it starts paused for visitors who prefer reduced motion. Slides out of view can't be tabbed into. Without JavaScript the slides scroll sideways instead.

Themes can restyle the carousel with custom properties such as `--galerio-carousel-caption-background` (the full list is at the top of `src/carousel/style.scss`). Developers can change the query with the `galerio_carousel_query_args` filter, add transitions with `galerio_carousel_transitions`, and adjust the carousel engine ([Embla](https://www.embla-carousel.com/)) through the `galerio.carousel.options`, `galerio.carousel.plugins` and `galerio.carousel.init` JavaScript hooks.

Galerio is in alpha. Expect breaking changes.

== Changelog ==

= 0.1.0-alpha.1 =
* The Carousel block, merging the Post Slider and Query Carousel prototypes. Either prototype's blocks can be transformed into it while the prototype's plugin is active.
