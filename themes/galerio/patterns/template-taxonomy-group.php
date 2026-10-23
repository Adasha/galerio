<?php
/**
 * Title: Group archive
 * Slug: galerio/template-taxonomy-group
 * Inserter: no
 * Description: The items in a group. Used by the taxonomy-galerio_group template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:query {"queryId":26,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"categories":["posts"],"patternName":"core/query-standard-posts","name":"Standard"}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","width":"","height":"","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} /-->

<!-- wp:post-title {"isLink":true,"fontSize":"medium"} /-->

<!-- wp:post-terms {"term":"galerio_group","separator":" ","className":"is-style-buttons","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
