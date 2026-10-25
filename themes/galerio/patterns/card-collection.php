<?php
/**
 * Title: Collection card
 * Slug: galerio/card-collection
 * Inserter: no
 * Description: The content of the Collection card template part: a collection as a slide like the front page carousel's, with its image filling the card and a caption box holding the title, dates, categories and a button. Edit the template part in the Site Editor, and every place that uses it follows: the collections archive, Query Loops and Galerio Carousels. Its height is 420px unless --galerio-collection-card-height says otherwise.
 *
 * @package Galerio
 */

?>
<!-- wp:cover {"useFeaturedImage":true,"dimRatio":0,"isUserOverlayColor":true,"contentPosition":"bottom right","isDark":false,"className":"galerio-card galerio-card-collection galerio-collection-card","layout":{"type":"default"}} -->
<div class="wp-block-cover is-light has-custom-content-position is-position-bottom-right galerio-card galerio-card-collection galerio-collection-card"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:group {"className":"galerio-veil galerio-collection-card__caption","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"textColor":"contrast","layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
<div class="wp-block-group galerio-veil galerio-collection-card__caption has-contrast-color has-text-color" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)"><!-- wp:post-title {"level":2,"isLink":true,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"x-large"} /-->

<!-- wp:galerio/exhibition-dates {"format":"short","showStatus":true,"fontSize":"small"} /-->

<!-- wp:post-terms {"term":"galerio_collection_category","separator":" · ","style":{"typography":{"textTransform":"uppercase"}},"fontSize":"small"} /-->

<!-- wp:read-more {"content":<?php galerio_theme_attr( galerio_theme_text( /* translators: Button on a collection card. {collection} is the site's name for a collection, such as "exhibition". */ __( 'View {collection}', 'galerio' ) ) ); ?>,"className":"wp-element-button"} /--></div>
<!-- /wp:group --></div></div>
<!-- /wp:cover -->
