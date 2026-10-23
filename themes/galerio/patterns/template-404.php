<?php
/**
 * Title: Page not found
 * Slug: galerio/template-404
 * Inserter: no
 * Description: The message shown when a page is not found. Used by the 404 template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( '404 - Page not found', 'galerio' ); ?></h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php esc_html_e( 'Sorry, the page you are looking for is not here.', 'galerio' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><?php printf( /* translators: %s: link to the home page, with the text "home page". */ esc_html__( 'Please try the site search to find your page, or return to the %s.', 'galerio' ), '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'home page', 'galerio' ) . '</a>' ); ?></p>
<!-- /wp:paragraph --></main>
<!-- /wp:group -->
