<?php
/**
 * Title: Single-item collection
 * Slug: galerio/template-single-item-collection
 * Inserter: no
 * Description: The main content of a collection presented as a single project. Used by the wp-custom-template-single-item-collection-project template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"75%"} -->
<div class="wp-block-column" style="flex-basis:75%"><!-- wp:post-content /-->

<!-- wp:galerio/field-display {"key":"collection.images"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"25%"} -->
<div class="wp-block-column" style="flex-basis:25%"><!-- wp:post-title {"style":{"spacing":{"margin":{"bottom":"0"}}}} /-->

<!-- wp:post-terms {"term":"galerio_collection_category","className":"is-style-default","style":{"spacing":{"margin":{"top":"0"}}}} /-->

<!-- wp:galerio/field-display {"key":"collection.description"} /-->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:galerio/field-display {"key":"collection.notes"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->
