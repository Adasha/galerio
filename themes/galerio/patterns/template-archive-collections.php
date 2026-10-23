<?php
/**
 * Title: Collections archive
 * Slug: galerio/template-archive-collections
 * Inserter: no
 * Description: The list of collections. Used by the archive-galerio_collection template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:query {"queryId":31,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true},"metadata":{"categories":["posts"],"patternName":"core/fullwidth-posts-with-uppercase-titles","name":"Fullwidth posts with uppercase titles"},"align":"full","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull"><!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:post-template {"style":{"typography":{"textTransform":"none"}}} -->
<!-- wp:group {"style":{"border":{"bottom":{"color":"var:preset|color|contrast","width":"1px"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="border-bottom-color:var(--wp--preset--color--contrast);border-bottom-width:1px"><!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:post-date {"textAlign":"left","format":"M j, Y","style":{"spacing":{"margin":{"top":"0","right":"0","bottom":"0","left":"0"}},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}},"typography":{"textTransform":"uppercase"}},"textColor":"contrast","fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:post-terms {"term":"galerio_collection_category","prefix":"✴︎ ","className":"is-style-buttons","style":{"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}},"typography":{"textTransform":"uppercase"}},"textColor":"contrast"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--20)"><!-- wp:cover {"useFeaturedImage":true,"dimRatio":0,"isUserOverlayColor":true,"isDark":false,"style":{"border":{"radius":"20px"}},"layout":{"type":"default"}} -->
<div class="wp-block-cover is-light" style="border-radius:20px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"style":{"color":{"background":"#ffffffab"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30","right":"var:preset|spacing|30"},"margin":{"top":"var:preset|spacing|80"}},"border":{"width":"0.6rem","radius":"20px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-background" style="border-width:0.6rem;border-radius:20px;background-color:#ffffffab;margin-top:var(--wp--preset--spacing--80);padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)"><!-- wp:post-title {"isLink":true,"style":{"spacing":{"margin":{"top":"0","right":"0","bottom":"0","left":"0"}},"layout":{"selfStretch":"fit"},"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":"1","textTransform":"uppercase"},"elements":{"link":{"color":{"text":"var:preset|color|contrast"}}}},"textColor":"contrast","fontSize":"xx-large"} /--></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30","right":"var:preset|spacing|40","left":"var:preset|spacing|40"},"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="margin-top:0;margin-bottom:0;padding-top:var(--wp--preset--spacing--30);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--30);padding-left:var(--wp--preset--spacing--40)"><!-- wp:query-pagination {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left"}} -->
<!-- wp:query-pagination-previous {"label":<?php galerio_theme_attr( /* translators: Link to the previous page of the list. */ __( 'Previous', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:query-pagination-next {"label":<?php galerio_theme_attr( /* translators: Link to the next page of the list. */ __( 'Next', 'galerio' ) ); ?>,"style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:group --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->
