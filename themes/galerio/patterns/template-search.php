<?php
/**
 * Title: Search results
 * Slug: galerio/template-search
 * Inserter: no
 * Description: The search form and what it found. Used by the search template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"search"} /-->

<!-- wp:search {"label":<?php galerio_theme_attr( __( 'Search', 'galerio' ) ); ?>,"showLabel":false,"buttonText":<?php galerio_theme_attr( __( 'Search', 'galerio' ) ); ?>} /-->

<!-- wp:query {"queryId":51,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"name":"Search results"}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:columns {"isStackedOnMobile":false,"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"border":{"bottom":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"}}}} -->
<div class="wp-block-columns is-not-stacked-on-mobile" style="border-bottom-color:var(--wp--preset--color--custom-lowlight);border-bottom-style:dotted;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)"><!-- wp:column {"width":"6rem"} -->
<div class="wp-block-column" style="flex-basis:6rem"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1"} /--></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:post-title {"level":2,"isLink":true,"fontSize":"medium","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} /-->

<!-- wp:post-excerpt {"excerptLength":30} /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Shown when a search finds nothing. */ __( 'Nothing matched your search. Try other words.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
