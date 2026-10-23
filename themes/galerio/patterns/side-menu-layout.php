<?php
/**
 * Title: Side menu layout
 * Slug: galerio/side-menu-layout
 * Categories: galerio
 * Template Types: index, home, front-page, archive, single, singular, page, search, 404, taxonomy, category, tag, author, date
 * Description: The site's standard page layout: the site menu in a sticky column on the left, the content and footer on the right. Start new templates from it.
 *
 * @package Galerio
 */

?>
<!-- wp:columns {"style":{"spacing":{"margin":{"top":"0","bottom":"0"},"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"blockGap":{"top":"0","left":"0"}}}} -->
<div class="wp-block-columns" style="margin-top:0;margin-bottom:0;padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:column {"width":"16rem","metadata":{"name":"Site Menu Column"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40);flex-basis:16rem"><!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<header class="wp-block-group"><!-- wp:template-part {"slug":"site-header"} /--></header>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"metadata":{"name":"Main Content Column"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}}} -->
<div class="wp-block-column" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:paragraph {"placeholder":<?php galerio_theme_attr( __( 'Add this template\'s content here…', 'galerio' ) ); ?>} -->
<p></p>
<!-- /wp:paragraph --></main>
<!-- /wp:group -->

<!-- wp:group {"tagName":"footer","metadata":{"name":"Footer"},"style":{"border":{"top":{"width":"1px"}}},"layout":{"type":"default"}} -->
<footer class="wp-block-group" style="border-top-width:1px"><!-- wp:template-part {"slug":"footer"} /--></footer>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
