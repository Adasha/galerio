<?php
/**
 * Title: Profiles archive
 * Slug: galerio/template-archive-profiles
 * Inserter: no
 * Description: The list of creator profiles. Used by the archive-galerio_profile template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:galerio/archive-filters {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} /-->

<!-- wp:query {"queryId":26,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"categories":["posts"],"patternName":"core/query-standard-posts","name":"Standard"}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"14rem"}} -->
<!-- wp:template-part {"slug":"card-profile","tagName":"div"} /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous {"label":<?php galerio_theme_attr( /* translators: Link to the previous page of the list. */ __( 'Previous', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php galerio_theme_attr( /* translators: Link to the next page of the list. */ __( 'Next', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Keep {profiles} as it is: it becomes this site's name for profiles. */ __( 'No {profiles} yet.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
