<?php
/**
 * Title: Profile card
 * Slug: galerio/card-profile
 * Inserter: no
 * Description: A profile in a list: its portrait, name and groups. Edit the template part in the Site Editor, or override parts/card-profile.html in a child theme, and every place that uses it follows: archives, Query Loops and Galerio Carousels.
 *
 * @package Galerio
 */

?>
<!-- wp:group {"metadata":{"name":"Card"},"className":"galerio-card galerio-card-profile","layout":{"type":"default"}} -->
<div class="wp-block-group galerio-card galerio-card-profile"><!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1","width":"","height":"","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} /-->

<!-- wp:post-title {"isLink":true,"fontSize":"medium"} /-->

<!-- wp:post-terms {"term":"galerio_group","separator":" ","className":"is-style-buttons","style":{"spacing":{"margin":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}}} /--></div>
<!-- /wp:group -->
