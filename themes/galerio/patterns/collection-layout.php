<?php
/**
 * Title: Collection layout
 * Slug: galerio/collection-layout
 * Categories: galerio
 * Post Types: galerio_collection
 * Keywords: collection, exhibition, layout
 * Description: The standard collection page as blocks to rearrange: title, dates, standfirst, images, description, notes, details and related items. A starting point for a collection designed in the editor with the Blank template.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Collection layout"},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-title {"level":1} /-->

<!-- wp:post-terms {"term":"galerio_collection_category","className":"is-style-buttons"} /-->

<?php if ( galerio_theme_has_block( 'galerio/exhibition-dates' ) ) : ?>
<!-- wp:galerio/exhibition-dates {"showStatus":true,"fontSize":"medium"} /-->
<?php endif; ?>

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"collection.standfirst"}}},"name":"Standfirst"},"fontSize":"large"} -->
<p class="has-large-font-size"></p>
<!-- /wp:paragraph -->

<?php if ( galerio_theme_has_block( 'galerio/gallery' ) ) : ?>
<!-- wp:galerio/gallery {"field":"collection.images","metadata":{"name":"Images"}} /-->
<?php else : ?>
<!-- wp:galerio/field-display {"key":"collection.images"} /-->
<?php endif; ?>

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
<article class="wp-block-group"><!-- wp:galerio/field-display {"key":"collection.description"} /-->

<!-- wp:galerio/field-display {"key":"collection.notes"} /--></article>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%","layout":{"type":"default"}} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:group {"metadata":{"name":"Asides"},"className":"is-style-asides","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-asides"><!-- wp:galerio/field-display {"key":"collection.location","label":"{field}:"} /-->

<!-- wp:galerio/field-display {"key":"collection.curators","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.other_contributors","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.website","label":"{field}:"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<?php if ( galerio_theme_has_block( 'galerio/related-items' ) ) : ?>
<!-- wp:galerio/related-items {"field":"collection.profiles","heading":<?php galerio_theme_attr( /* translators: Heading above the profiles a collection features. */ __( 'Featuring', 'galerio' ) ); ?>,"columns":4,"aspectRatio":"1"} /-->

<!-- wp:galerio/related-items {"field":"collection.works","columns":4,"detail":"work.creator"} /-->

<!-- wp:galerio/related-items {"field":"collection.articles","columns":3} /-->

<!-- wp:galerio/related-items {"field":"collection.related_collections","columns":3} /-->
<?php else : ?>
<!-- wp:galerio/field-display {"key":"collection.profiles","label":<?php galerio_theme_attr( /* translators: Heading above the profiles a collection features. */ __( 'Featuring:', 'galerio' ) ); ?>,"layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.works","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.articles","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.related_collections","label":"{field}:","layout":"list"} /-->
<?php endif; ?></div>
<!-- /wp:group -->
