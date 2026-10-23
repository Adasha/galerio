<?php
/**
 * Title: Top header layout
 * Slug: galerio/top-header-layout
 * Categories: galerio
 * Template Types: index, home, front-page, archive, single, singular, page, search, 404, taxonomy, category, tag, author, date
 * Description: An alternative page layout: the site title and menu in a bar across the top, the content and footer below. Start new templates from it, or use it in place of the side menu layout.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
<header class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:template-part {"slug":"header-bar"} /--></header>
<!-- /wp:group -->

<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:paragraph {"placeholder":<?php galerio_theme_attr( __( 'Add this template\'s content here…', 'galerio' ) ); ?>} -->
<p></p>
<!-- /wp:paragraph --></main>
<!-- /wp:group -->

<!-- wp:group {"tagName":"footer","metadata":{"name":"Footer"},"style":{"spacing":{"padding":{"left":"var:preset|spacing|40","right":"var:preset|spacing|40"}}},"layout":{"type":"default"}} -->
<footer class="wp-block-group" style="padding-right:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:template-part {"slug":"footer"} /--></footer>
<!-- /wp:group -->
