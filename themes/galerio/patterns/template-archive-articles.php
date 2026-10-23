<?php
/**
 * Title: Articles archive
 * Slug: galerio/template-archive-articles
 * Inserter: no
 * Description: The list of articles. Used by the archive-galerio_article template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:query {"queryId":40,"query":{"perPage":10,"pages":0,"offset":0,"postType":"galerio_article","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"name":"Articles"}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:columns {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"border":{"bottom":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"}}}} -->
<div class="wp-block-columns" style="border-bottom-color:var(--wp--preset--color--custom-lowlight);border-bottom-style:dotted;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)"><!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:post-terms {"term":"galerio_article_category","className":"is-style-buttons"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"0"}}}} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"article.standfirst"}}}},"fontSize":"medium"} -->
<p class="has-medium-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:post-date {"fontSize":"small"} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"article.authors","prefix":<?php galerio_theme_attr( /* translators: Before the author's name in a list of articles. */ __( '· by ', 'galerio' ) ); ?>}}}},"fontSize":"small"} -->
<p class="has-small-font-size"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {articles} as it is: it becomes this site's name for articles. */ __( 'No {articles} yet.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
