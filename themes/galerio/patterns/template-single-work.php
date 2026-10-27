<?php
/**
 * Title: Single work
 * Slug: galerio/template-single-work
 * Inserter: no
 * Description: The main content of a single work: its definitive image, title, details, description, other images and related items. Used by the single-galerio_work template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:group {"className":"is-style-image-box","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-image-box"><!-- wp:image {"sizeSlug":"large","metadata":{"bindings":{"url":{"source":"galerio/field","args":{"key":"work.definitive_image"}},"alt":{"source":"galerio/field","args":{"key":"work.definitive_image"}}},"name":"Definitive image"}} -->
<figure class="wp-block-image size-large"><img alt=""/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:heading {"level":1,"style":{"spacing":{"margin":{"bottom":"0"}}},"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.definitive_title","fallback":"title"}}},"name":"Definitive title"}} -->
<h1 class="wp-block-heading" style="margin-bottom:0"></h1>
<!-- /wp:heading -->

<!-- wp:group {"metadata":{"name":"Year and creator"},"style":{"spacing":{"blockGap":"var:preset|spacing|10","margin":{"top":"0","bottom":"0"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.year"}}}},"className":"galerio-comma-after","fontSize":"medium","fontFamily":"heading"} -->
<p class="galerio-comma-after has-heading-font-family has-medium-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.creator"}}}},"fontSize":"medium","fontFamily":"heading"} -->
<p class="has-heading-font-family has-medium-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"33%"} -->
<div class="wp-block-column" style="flex-basis:33%"><!-- wp:pattern {"slug":"galerio/work-details"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"67%","style":{"spacing":{"blockGap":"var:preset|spacing|60"}}} -->
<div class="wp-block-column" style="flex-basis:67%"><!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
<article class="wp-block-group"><!-- wp:post-content /-->

<?php if ( galerio_theme_has_block( 'galerio/gallery' ) ) : ?>
<!-- wp:galerio/gallery {"field":"work.images","columns":3,"metadata":{"name":"Other images"},"className":"galerio-template-gallery"} /-->
<?php else : ?>
<!-- wp:galerio/field-display {"key":"work.images"} /-->
<?php endif; ?>

<!-- wp:group {"tagName":"aside","className":"galerio-details","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap","justifyContent":"stretch"}} -->
<aside class="wp-block-group galerio-details"><!-- wp:columns {"verticalAlignment":"center","isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center is-not-stacked-on-mobile" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"center","width":"9rem"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:9rem"><!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {Labels} as it is: it becomes this site's name for its labels. */ __( '{Labels}:', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:post-terms {"term":"galerio_label","separator":"  ","className":"is-style-buttons"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"center","isStackedOnMobile":false,"style":{"border":{"top":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"}},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center is-not-stacked-on-mobile" style="border-top-color:var(--wp--preset--color--custom-lowlight);border-top-style:dotted;border-top-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"center","width":"9rem"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:9rem"><!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {Mediums} as it is: it becomes this site's name for its mediums. */ __( '{Mediums}:', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:post-terms {"term":"galerio_medium","separator":"  ","className":"is-style-buttons"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></aside>
<!-- /wp:group --></article>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<?php if ( galerio_theme_has_related() ) : ?>
	<?php galerio_related_query( 'work.related_works', '', 4 ); ?>

	<?php galerio_related_query( 'work.articles', '', 1 ); ?>
<?php else : ?>
<!-- wp:galerio/field-display {"key":"work.related_works","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"work.articles","label":"{field}:","layout":"list"} /-->
<?php endif; ?></main>
<!-- /wp:group -->
