<?php
/**
 * Title: Work card
 * Slug: galerio/card-work
 * Inserter: no
 * Description: A work in a list: its image beside its title, creator, year and mediums, stacking when the card is narrow. Edit the template part in the Site Editor, or override parts/card-work.html in a child theme, and every place that uses it follows: archives, Query Loops and Galerio Carousels.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Card"},"className":"galerio-card galerio-card-work","layout":{"type":"default"}} -->
<div class="wp-block-group galerio-card galerio-card-work"><!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"auto","sizeSlug":"large"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-column" style="flex-basis:50%"><!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.definitive_title","fallback":"title","link":true}}}}} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.creator","omitCurrent":true}}}}} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.year"}}}}} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:post-terms {"term":"galerio_medium","className":"is-style-buttons"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
