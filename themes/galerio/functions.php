<?php
/**
 * Galerio theme functions.
 *
 * @package Galerio
 */

add_action( 'after_setup_theme', 'galerio_load_textdomain' );
add_action( 'init', 'galerio_register_pattern_category' );
add_action( 'init', 'galerio_register_block_styles' );
add_filter( 'render_block_core/cover', 'galerio_mark_imageless_cover', 10, 3 );

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
		'core/site-title'       => array(
			array(
				'name'  => 'keep-words',
				'label' => __( 'Keep words whole', 'galerio' ),
			),
			array(
				'name'  => 'hyphenate',
				'label' => __( 'Hyphenate', 'galerio' ),
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

/**
 * Mark a cover that shows its item's featured image but finds none, so the
 * theme can show a plain colour band in its place instead of nothing.
 *
 * Galerio Core first falls back to the item's own image (a work's
 * definitive image, a profile's portrait…), so this is for items with no
 * image at all, or whose fallback is turned off.
 *
 * @param string   $content Rendered cover.
 * @param array    $block   Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function galerio_mark_imageless_cover( $content, $block, $instance ) {
	if ( empty( $block['attrs']['useFeaturedImage'] ) ) {
		return $content;
	}

	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		return $content;
	}

	$cover = new WP_HTML_Tag_Processor( $content );
	if ( $cover->next_tag( array( 'class_name' => 'wp-block-cover' ) ) ) {
		$cover->add_class( 'galerio-cover-no-image' );
	}

	return $cover->get_updated_html();
}
