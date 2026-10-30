<?php
/**
 * Title: Works archive
 * Slug: galerio/template-archive-works
 * Inserter: no
 * Description: The list of works. Used by the archive-galerio_work template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:galerio/archive-filters {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} /-->

<!-- wp:query {"queryId":22,"query":{"perPage":10,"pages":0,"offset":0,"postType":"galerio_work","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"categories":["posts"],"patternName":"core/query-medium-posts","name":"Image at left"}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"32rem"}} -->
<!-- wp:template-part {"slug":"card-work","tagName":"div"} /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous {"label":<?php galerio_theme_attr( /* translators: Link to the previous page of the list. */ __( 'Previous', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php galerio_theme_attr( /* translators: Link to the next page of the list. */ __( 'Next', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {works} as it is: it becomes this site's name for works. */ __( 'No {works} yet.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
