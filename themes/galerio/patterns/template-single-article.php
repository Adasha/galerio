<?php
/**
 * Title: Single article
 * Slug: galerio/template-single-article
 * Inserter: no
 * Description: The main content of an article: its title, standfirst, byline, text and related items. Used by the single-galerio_article template; a child theme can override this pattern alone.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Main"},"layout":{"type":"default"}} -->
<main class="wp-block-group"><!-- wp:cover {"useFeaturedImage":true,"dimRatio":0,"overlayColor":"base","isUserOverlayColor":true,"contentPosition":"center center","isDark":false,"layout":{"type":"constrained"}} -->
<div class="wp-block-cover is-light"><span aria-hidden="true" class="wp-block-cover__background has-base-background-color has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","placeholder":<?php galerio_theme_attr( __( 'Write title…', 'galerio' ) ); ?>} -->
<p class="has-text-align-center"></p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->

<!-- wp:post-terms {"term":"galerio_article_category","className":"is-style-buttons"} /-->

<!-- wp:post-title {"level":1} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"article.standfirst"}}}},"fontSize":"large"} -->
<p class="has-large-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:group {"metadata":{"name":"Byline"},"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"article.authors","prefix":<?php galerio_theme_attr( /* translators: Before the author's name. */ __( 'By ', 'galerio' ) ); ?>}}}},"fontSize":"small"} -->
<p class="has-small-font-size"></p>
<!-- /wp:paragraph -->

<!-- wp:post-date {"fontSize":"small"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"galerio-split","metadata":{"name":"Split"},"layout":{"type":"default"}} -->
<div class="wp-block-group galerio-split"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:post-content /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%","layout":{"type":"default"}} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:group {"metadata":{"name":"Asides"},"className":"is-style-asides","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-asides"><!-- wp:galerio/field-display {"key":"article.profiles","label":<?php galerio_theme_attr( /* translators: Heading above the profiles an article features. */ __( 'Featuring:', 'galerio' ) ); ?>,"layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"article.works","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"article.collections","label":"{field}:","layout":"list"} /-->
<?php if ( ! galerio_theme_has_related() ) : ?>

<!-- wp:galerio/field-display {"key":"article.related_articles","label":"{field}:","layout":"list"} /-->
<?php endif; ?></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<?php if ( galerio_theme_has_related() ) : ?>
	<?php galerio_related_query( 'article.related_articles', '', 1 ); ?>
<?php endif; ?></main>
<!-- /wp:group -->
