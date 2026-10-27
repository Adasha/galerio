<?php
/**
 * Functions for swansea.art, a Galerio child theme.
 *
 * @package Swansea_Art
 */

/**
 * Load this theme's stylesheet after Galerio's styles. Block themes don't
 * load a child theme's style.css by themselves.
 */
function swansea_art_enqueue_styles() {
	$theme = wp_get_theme();
	wp_enqueue_style( 'swansea-art', get_stylesheet_uri(), array( 'global-styles' ), $theme->get( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'swansea_art_enqueue_styles' );

/**
 * The same stylesheet in the editor, so content looks as it will on the site.
 */
function swansea_art_editor_styles() {
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'swansea_art_editor_styles' );

/**
 * Kadence's Advanced Gallery on works, exhibitions, articles and profiles
 * opens its images in Kadence's lightbox unless one was chosen, like
 * Galerio's own galleries, rather than opening the bare file or nothing.
 *
 * @param array $block Parsed block.
 * @return array
 */
function swansea_art_kadence_gallery_lightbox( $block ) {
	if ( 'kadence/advancedgallery' !== $block['blockName'] || ! function_exists( 'galerio_lightbox_post_types' ) || ! in_array( get_post_type(), galerio_lightbox_post_types(), true ) ) {
		return $block;
	}

	$attrs = $block['attrs'];

	if ( ! empty( $attrs['lightbox'] ) && 'none' !== $attrs['lightbox'] ) {
		return $block;
	}

	// Images linked elsewhere, such as to pages, keep their links.
	if ( isset( $attrs['linkTo'] ) && ! in_array( $attrs['linkTo'], array( 'none', 'media' ), true ) ) {
		return $block;
	}

	$block['attrs']['linkTo']   = 'media';
	$block['attrs']['lightbox'] = 'magnific';

	return $block;
}
add_filter( 'render_block_data', 'swansea_art_kadence_gallery_lightbox' );
