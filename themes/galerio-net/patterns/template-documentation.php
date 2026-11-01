<?php
/**
 * Title: Documentation
 * Slug: galerio-net/template-documentation
 * Inserter: no
 * Description: A documentation page: a menu column on the left and the item's content beside it. Used by the wp-custom-template-documentation template.
 *
 * @package Galerio_Net
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:group {"className":"galerio-split","metadata":{"name":"Split"},"layout":{"type":"default"}} -->
<div class="wp-block-group galerio-split"><!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"0"}}}} -->
<div class="wp-block-columns"><!-- wp:column {"width":"25%","metadata":{"name":"Nav Menu Column"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"backgroundColor":"custom-highlight","layout":{"type":"default"}} -->
<div class="wp-block-column has-custom-highlight-background-color has-background" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);flex-basis:25%"><!-- wp:group {"tagName":"header","style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<header class="wp-block-group"><!-- wp:navigation {"openSubmenusOnClick":true,"overlayMenu":"never","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} /--></header>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"75%","metadata":{"name":"Content Column"},"style":{"spacing":{"padding":{"top":"0","bottom":"0","left":"var:preset|spacing|40","right":"0"}}}} -->
<div class="wp-block-column" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:var(--wp--preset--spacing--40);flex-basis:75%"><!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
<article class="wp-block-group"><!-- wp:post-content /--></article>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->
