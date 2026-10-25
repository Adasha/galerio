<?php
/**
 * Title: Site header
 * Slug: swansea-art/site-header
 * Inserter: no
 * Description: The side column's header: the wordmark, tagline, main menu, log in link, search and social links. Used by the site-header template part.
 *
 * @package Swansea_Art
 */

?>
<!-- wp:group {"className":"galerio-site-header","style":{"spacing":{"blockGap":"var:preset|spacing|60"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","flexWrap":"nowrap"}} -->
<div class="wp-block-group galerio-site-header"><!-- wp:group {"metadata":{"name":"Title Group"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:site-logo {"width":200,"shouldSyncIcon":false} /-->

<!-- wp:site-tagline /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:navigation {"icon":"menu","className":"is-style-main-menu","style":{"layout":{"selfStretch":"fit","flexSize":null}},"layout":{"type":"flex","flexWrap":"nowrap","orientation":"vertical","justifyContent":"left"}} /--></div>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Visitor Tools"},"className":"swansea-art-visitor-tools","style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch","flexWrap":"nowrap"}} -->
<div class="wp-block-group swansea-art-visitor-tools"><!-- wp:loginout {"fontSize":"small"} /-->

<!-- wp:search {"label":<?php galerio_theme_attr( __( 'Search', 'swansea-art' ) ); ?>,"showLabel":false,"placeholder":<?php galerio_theme_attr( __( 'Search…', 'swansea-art' ) ); ?>,"buttonPosition":"no-button"} /-->

<!-- wp:social-links {"iconColor":"contrast","iconColorValue":"#252525","size":"has-normal-icon-size","className":"is-style-logos-only","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<ul class="wp-block-social-links has-normal-icon-size has-icon-color is-style-logos-only"><!-- wp:social-link {"url":"https://www.facebook.com/FineArtSwanseaCollegeofArtUWTSD/","service":"facebook"} /-->

<!-- wp:social-link {"url":"https://twitter.com/fineartswansea","service":"x"} /-->

<!-- wp:social-link {"url":"https://www.instagram.com/fineartatswansea/","service":"instagram"} /-->

<!-- wp:social-link {"url":"https://www.youtube.com/@swanseaart","service":"youtube"} /--></ul>
<!-- /wp:social-links --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
