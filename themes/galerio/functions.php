<?php
/**
 * Galerio theme functions.
 *
 * @package Galerio
 */

add_action( 'init', 'galerio_register_block_styles' );

/**
 * Register custom block styles.
 */
function galerio_register_block_styles() {

	$styles = array(
		'core/post-terms' => array(
			array(
				'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			),
		),
		'core/navigation' => array(
			array(
				'name'  => 'main-menu',
				'label' => __( 'Main Menu', 'galerio' ),
			),
		),
		'core/group'      => array(
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
