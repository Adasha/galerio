<?php
/**
 * Galerio theme functions.
 *
 * @package Galerio
 */

add_action( 'after_setup_theme', 'galerio_load_textdomain' );
add_action( 'init', 'galerio_register_pattern_category' );
add_action( 'init', 'galerio_register_block_styles' );

/**
 * Load the theme's bundled translations, from languages/.
 */
function galerio_load_textdomain() {
	load_theme_textdomain( 'galerio', get_template_directory() . '/languages' );
}

/**
 * Register the pattern category for the theme's own patterns.
 */
function galerio_register_pattern_category() {
	register_block_pattern_category(
		'galerio',
		array( 'label' => __( 'Galerio', 'galerio' ) )
	);
}

/**
 * Register custom block styles.
 */
function galerio_register_block_styles() {

	$styles = array(
		'core/post-terms'       => array(
			array(
				'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			),
		),
		'galerio/field-display' => array(
			array(
				'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			),
		),
		'core/navigation'       => array(
			array(
				'name'  => 'main-menu',
				'label' => __( 'Main Menu', 'galerio' ),
			),
		),
		'core/group'            => array(
			array(
				'name'  => 'asides',
				'label' => __( 'Asides', 'galerio' ),
			),
			array(
				'name'  => 'image-box',
				'label' => __( 'Image Box', 'galerio' ),
			),
		),
	);

	foreach ( $styles as $block => $block_styles ) {
		foreach ( $block_styles as $style_props ) {
			register_block_style( $block, $style_props );
		}
	}
}

if ( ! function_exists( 'galerio_theme_text' ) ) {
	/**
	 * Prepare a translated text from a pattern: name tokens such as
	 * "{Collections}" become the site's names for its content types.
	 *
	 * Without Galerio Core the tokens' braces are dropped, leaving the
	 * standard names.
	 *
	 * @param string $text Translated text.
	 * @return string
	 */
	function galerio_theme_text( $text ) {
		return function_exists( 'galerio_expand_names' )
			? galerio_expand_names( $text )
			: preg_replace( '/\{([A-Za-z]+)\}/', '$1', $text );
	}
}

if ( ! function_exists( 'galerio_theme_attr' ) ) {
	/**
	 * Echo a translated text as a quoted string for a block attribute in a
	 * pattern's block comment, escaped as the block editor saves it.
	 *
	 * Name tokens are kept, for the block to replace as it renders.
	 *
	 * @param string $text Translated text.
	 */
	function galerio_theme_attr( $text ) {
		// serialize_block_attributes() escapes for a block comment: quotes, <, >, & and "--".
		echo substr( serialize_block_attributes( array( (string) $text ) ), 1, -1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for a block comment.
	}
}
