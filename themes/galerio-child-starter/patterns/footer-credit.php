<?php
/**
 * Title: Footer credit
 * Slug: galerio/footer-credit
 * Categories: galerio, footer
 * Description: The footer's credit line: copyright and site name. Replaces Galerio's "Powered by Galerio" line, because a child theme's pattern wins over the parent's with the same slug.
 *
 * @package Galerio_Child_Starter
 */

?>
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php printf( /* translators: 1: year, 2: site name. */ esc_html__( '© %1$s %2$s', 'galerio-child-starter' ), esc_html( wp_date( 'Y' ) ), esc_html( get_bloginfo( 'name' ) ) ); ?></p>
<!-- /wp:paragraph -->
