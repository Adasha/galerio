<?php
/**
 * Title: Footer credit
 * Slug: galerio/footer-credit
 * Categories: galerio, footer
 * Description: A "Powered by Galerio" line for the footer.
 *
 * @package Galerio
 */

?>
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php printf( /* translators: %s: link to the Galerio website, with the text "Galerio". */ esc_html__( 'Powered by %s', 'galerio' ), '<a href="https://galerio.net" target="_blank" rel="noreferrer noopener nofollow">Galerio</a>' ); ?></p>
<!-- /wp:paragraph -->
