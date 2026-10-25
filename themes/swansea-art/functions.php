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
