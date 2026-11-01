<?php
/**
 * Title: Profile card
 * Slug: galerio/card-profile
 * Inserter: no
 * Description: A profile in a list: its portrait, name, roles and groups. Edit the template part in the Site Editor, or override parts/card-profile.html in a child theme, and every place that uses it follows: archives, Query Loops and Galerio Carousels.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Card"},"className":"galerio-card galerio-card-profile","layout":{"type":"default"}} -->
<div class="wp-block-group galerio-card galerio-card-profile"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","width":"","height":"","style":{"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|30"}}}} /-->

<!-- wp:post-title {"isLink":true,"fontSize":"medium"} /-->

<!-- wp:group {"metadata":{"name":"Roles and groups"},"style":{"spacing":{"blockGap":"var:preset|spacing|10","margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--30);margin-bottom:var(--wp--preset--spacing--30)"><!-- wp:post-terms {"term":"galerio_role","separator":" ","className":"is-style-buttons"} /-->

<!-- wp:post-terms {"term":"galerio_group","separator":" ","className":"is-style-buttons"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
