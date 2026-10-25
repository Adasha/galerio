<?php
/**
 * Title: Single profile
 * Slug: galerio/template-single-profile
 * Inserter: no
 * Description: The main content of a creator profile: portrait, name, details, biography and related items. Used by the single-galerio_profile template; a child theme can override this pattern alone.
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

<!-- wp:post-title {"level":1} /-->

<!-- wp:post-terms {"term":"galerio_group","separator":" ","className":"is-style-buttons"} /-->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column {"width":"66.66%","style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:group {"tagName":"article","layout":{"type":"default"}} -->
<article class="wp-block-group"><!-- wp:group {"metadata":{"name":"Nationality and lifespan"},"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":4,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"profile.nationality","if":"show_nationality"}}}},"className":"galerio-comma-after"} -->
<h4 class="wp-block-heading galerio-comma-after"></h4>
<!-- /wp:heading -->

<!-- wp:heading {"level":4,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"profile.year_of_birth","if":"show_lifespan","prefix":<?php galerio_theme_attr( /* translators: Before a year of birth or founding: "born". */ __( 'b. ', 'galerio' ) ); ?>}}}}} -->
<h4 class="wp-block-heading"></h4>
<!-- /wp:heading -->

<!-- wp:heading {"level":4,"metadata":{"bindings":{"content":{"source":"galerio/field","args":{"key":"profile.year_of_death","if":"show_lifespan","prefix":<?php galerio_theme_attr( /* translators: Before a year of death or closing: "died". */ __( '– d. ', 'galerio' ) ); ?>}}}}} -->
<h4 class="wp-block-heading"></h4>
<!-- /wp:heading --></div>
<!-- /wp:group -->

<!-- wp:galerio/field-display {"key":"profile.bio"} /-->

<!-- wp:galerio/field-display {"key":"profile.notes"} /--></article>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%","layout":{"type":"default"}} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:group {"metadata":{"name":"Asides"},"className":"is-style-asides","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-asides"><!-- wp:galerio/field-display {"key":"profile.members","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"profile.member_of","label":"{field}:","layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"profile.accolades","label":"{field}:","layout":"list"} /-->

<?php if ( ! galerio_theme_has_related() ) : ?>
<!-- wp:galerio/field-display {"key":"profile.collections","label":<?php galerio_theme_attr( /* translators: Heading above the collections a profile is in. */ __( 'Featured in:', 'galerio' ) ); ?>,"layout":"list"} /-->

<!-- wp:galerio/field-display {"key":"profile.works","label":<?php galerio_theme_attr( /* translators: Keep {works} as it is: it becomes this site's name for works. */ __( 'Featured {works}:', 'galerio' ) ); ?>,"layout":"list"} /-->

<?php endif; ?>
<!-- wp:galerio/field-display {"key":"profile.links","label":<?php galerio_theme_attr( /* translators: Heading above a profile's links. */ __( 'More links:', 'galerio' ) ); ?>,"showIf":"show_links"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:separator {"className":"is-style-dots"} -->
<hr class="wp-block-separator has-alpha-channel-opacity is-style-dots"/>
<!-- /wp:separator -->

<!-- wp:post-content /-->

<?php if ( galerio_theme_has_related() ) : ?>
	<?php galerio_related_query( 'profile.works', galerio_theme_text( /* translators: Heading above a profile's works. Keep {works} as it is: it becomes this site's name for works. */ __( 'Featured {works}', 'galerio' ) ), 4 ); ?>

	<?php galerio_related_query( 'profile.collections', galerio_theme_text( /* translators: Heading above the collections a profile is in. */ __( 'Featured in', 'galerio' ) ), 3 ); ?>

	<?php galerio_related_query( 'profile.authored_articles', '', 1 ); ?>

	<?php galerio_related_query( 'profile.article_mentions', '', 1 ); ?>
<?php endif; ?></main>
<!-- /wp:group -->
