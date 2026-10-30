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

<!-- wp:galerio/archive-filters {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} /-->

<!-- wp:query {"queryId":40,"query":{"perPage":10,"pages":0,"offset":0,"postType":"galerio_article","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"name":"Articles"}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:template-part {"slug":"card-article","tagName":"div"} /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous {"label":<?php galerio_theme_attr( /* translators: Link to the previous page of the list. */ __( 'Previous', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php galerio_theme_attr( /* translators: Link to the next page of the list. */ __( 'Next', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {articles} as it is: it becomes this site's name for articles. */ __( 'No {articles} yet.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
