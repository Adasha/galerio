<?php
/**
 * Title: Single collection
 * Slug: galerio/template-single-collection
 * Inserter: no
 * Description: The main content of a single collection: its title, dates, standfirst, images, description, notes and related items. Used by the single-galerio_collection template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:post-title {"level":1} /-->

<!-- wp:post-terms {"term":"galerio_collection_category","className":"is-style-buttons"} /-->

<?php if ( galerio_theme_has_block( 'galerio/exhibition-dates' ) ) : ?>
<!-- wp:galerio/exhibition-dates {"showStatus":true,"fontSize":"medium"} /-->
<?php endif; ?>

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"collection.standfirst"}}},"name":"Standfirst"},"fontSize":"large"} -->
<p class="has-large-font-size"></p>
<!-- /wp:paragraph -->

<?php if ( galerio_theme_has_block( 'galerio/gallery' ) ) : ?>
<!-- wp:galerio/gallery {"field":"collection.images","metadata":{"name":"Images"},"className":"galerio-template-gallery"} /-->
<?php else : ?>
<!-- wp:galerio/field-display {"key":"collection.images"} /-->
<?php endif; ?>

<!-- wp:group {"className":"galerio-split","metadata":{"name":"Split"},"layout":{"type":"default"}} -->
<div class="wp-block-group galerio-split"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
<article class="wp-block-group"><!-- wp:galerio/field-display {"key":"collection.description"} /-->

<!-- wp:post-content /-->

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
<!-- /wp:columns --></div>
<!-- /wp:group -->

<?php if ( galerio_theme_has_related() ) : ?>
	<?php galerio_related_query( 'collection.profiles', galerio_theme_text( /* translators: Heading above the profiles a collection features. */ __( 'Featuring', 'galerio' ) ), 4 ); ?>

	<?php galerio_related_query( 'collection.works', '', 4 ); ?>

	<?php galerio_related_query( 'collection.articles', '', 1 ); ?>

	<?php galerio_related_query( 'collection.related_collections', '', 3 ); ?>
<?php else : ?>
<!-- wp:galerio/field-display {"key":"collection.profiles","label":<?php galerio_theme_attr( /* translators: Heading above the profiles a collection features. */ __( 'Featuring:', 'galerio' ) ); ?>,"layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.works","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.articles","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"collection.related_collections","label":"{field}:","layout":"list"} /-->
<?php endif; ?></main>
<!-- /wp:group -->
