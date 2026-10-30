<?php
/**
 * Title: Term archive
 * Slug: galerio/template-taxonomy
 * Inserter: no
 * Description: Everything with one term, such as a medium, label, role or category, whatever its type. Used by the taxonomy template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:term-description /-->

<!-- wp:galerio/archive-filters {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} /-->

<!-- wp:query {"queryId":50,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"name":"Term items"}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"14rem"}} -->
<!-- wp:template-part {"slug":"card","tagName":"div"} /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous {"label":<?php galerio_theme_attr( /* translators: Link to the previous page of the list. */ __( 'Previous', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next {"label":<?php galerio_theme_attr( /* translators: Link to the next page of the list. */ __( 'Next', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php echo esc_html( galerio_theme_text( /* translators: Shown on a medium, label, role or category page with no items. */ __( 'Nothing here yet.', 'galerio' ) ) ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
