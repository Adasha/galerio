<?php
/**
 * Title: Site presents…
 * Slug: galerio/presents
 * Categories: galerio
 * Keywords: presents, guest, introduction
 * Description: A line introducing the page as the site's presentation, e.g. "swansea.art presents…", for guest collections. It shows the current site title.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Presents"},"className":"galerio-presents","style":{"spacing":{"blockGap":"0.3em","margin":{"bottom":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
<div class="wp-block-group galerio-presents" style="margin-bottom:var(--wp--preset--spacing--40)"><!-- wp:site-title {"level":0,"isLink":false,"style":{"typography":{"fontWeight":"700"}},"fontSize":"medium"} /-->

<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size"><?php echo esc_html( /* translators: Follows the site's name, as in "swansea.art presents…". */ __( 'presents…', 'galerio' ) ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
