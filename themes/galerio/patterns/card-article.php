<?php
/**
 * Title: Article card
 * Slug: galerio/card-article
 * Inserter: no
 * Description: An article in a list: its image beside its categories, title, standfirst, date and authors, stacking when the card is narrow. Edit the template part in the Site Editor, or override parts/card-article.html in a child theme, and every place that uses it follows: archives, Query Loops and Galerio Carousels.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Card"},"className":"galerio-card galerio-card-article","layout":{"type":"default"}} -->
<div class="wp-block-group galerio-card galerio-card-article"><!-- wp:columns {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}},"border":{"bottom":{"color":"var:preset|color|custom-lowlight","style":"dotted","width":"1px"}}}} -->
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
<!-- /wp:columns --></div>
<!-- /wp:group -->
