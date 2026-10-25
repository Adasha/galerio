<?php
/**
 * Title: Work details
 * Slug: galerio/work-details
 * Categories: galerio
 * Block Types: core/post-content
 * Description: A work's details as a table: medium, style or genre, dimensions and the rest, from the work's fields. Rows with nothing in them are hidden. Used by the work template; place it anywhere a work's details are wanted, or override it in a child theme.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Details"},"className":"galerio-details","style":{"spacing":{"blockGap":"0","padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"}}},"backgroundColor":"custom-hint","layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap","justifyContent":"stretch"}} -->
<div class="wp-block-group galerio-details has-custom-hint-background-color has-background" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)"><!-- wp:columns {"verticalAlignment":"top","isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
<div class="wp-block-columns are-vertically-aligned-top is-not-stacked-on-mobile" style="padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"top","width":"9rem","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}}} -->
<div class="wp-block-column is-vertically-aligned-top" style="font-style:normal;font-weight:700;flex-basis:9rem"><!-- wp:paragraph -->
<p><?php /* translators: The medium a work is made in, e.g. "Oil on canvas". */ esc_html_e( 'Medium', 'galerio' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.medium"}}}}} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"top","isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}},"border":{"top":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"},"right":[],"bottom":[],"left":[]}}} -->
<div class="wp-block-columns are-vertically-aligned-top is-not-stacked-on-mobile" style="border-top-color:var(--wp--preset--color--custom-lowlight);border-top-style:dotted;border-top-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"top","width":"9rem","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}}} -->
<div class="wp-block-column is-vertically-aligned-top" style="font-style:normal;font-weight:700;flex-basis:9rem"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Style/genre', 'galerio' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:post-terms {"term":"galerio_label"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"top","isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}},"border":{"top":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"},"right":[],"bottom":[],"left":[]}}} -->
<div class="wp-block-columns are-vertically-aligned-top is-not-stacked-on-mobile" style="border-top-color:var(--wp--preset--color--custom-lowlight);border-top-style:dotted;border-top-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"top","width":"9rem","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"layout":{"type":"default"}} -->
<div class="wp-block-column is-vertically-aligned-top" style="font-style:normal;font-weight:700;flex-basis:9rem"><!-- wp:paragraph -->
<p><?php esc_html_e( 'Dimensions', 'galerio' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"work.dimensions"}}}}} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:columns {"verticalAlignment":"top","isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}},"border":{"top":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"},"right":[],"bottom":[],"left":[]}}} -->
<div class="wp-block-columns are-vertically-aligned-top is-not-stacked-on-mobile" style="border-top-color:var(--wp--preset--color--custom-lowlight);border-top-style:dotted;border-top-width:1px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20)"><!-- wp:column {"verticalAlignment":"top","width":"9rem","style":{"typography":{"fontStyle":"normal","fontWeight":"700"}}} -->
<div class="wp-block-column is-vertically-aligned-top" style="font-style:normal;font-weight:700;flex-basis:9rem"><!-- wp:paragraph -->
<p><?php /* translators: Heading beside the collections a work is in. */ esc_html_e( 'Featured in', 'galerio' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"top"} -->
<div class="wp-block-column is-vertically-aligned-top"><!-- wp:galerio/field-display {"key":"work.collections","layout":"list"} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
