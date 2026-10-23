<?php
/**
 * Title: Works archive
 * Slug: galerio/template-archive-works
 * Inserter: no
 * Description: The list of works. Used by the archive-galerio_work template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:query {"queryId":22,"query":{"perPage":10,"pages":0,"offset":0,"postType":"galerio_work","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"categories":["posts"],"patternName":"core/query-medium-posts","name":"Image at left"}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"22rem"}} -->
<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"auto","sizeSlug":"large"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:heading {"level":2,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.definitive_title","fallback":"title","link":true}}}}} -->
<h2 class="wp-block-heading"></h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.creator"}}}}} -->
<h3 class="wp-block-heading"></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.year"}}}}} -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:post-terms {"term":"galerio_medium","className":"is-style-buttons"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
