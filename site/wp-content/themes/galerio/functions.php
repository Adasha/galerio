<?php
/**
 * Galerio Theme functions.php
 * v0.4.1a
 */


add_action( 'init', 'galerio_register_block_styles' );

/**
 * Register custom block styles.
 */
function galerio_register_block_styles() {
	
	$styles = [
		//post-terms
		'core/post-terms' => [
			[
	  		  	'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			]
		],
		//navigation
		'core/navigation' => [
			[
	    		'name'  => 'main-menu',
				'label' => __( 'Main Menu', 'galerio' ),
			]
		],
		//group
		'core/group'      => [
			[
	    		'name'  => 'asides',
				'label' => __( 'Asides', 'galerio' ),
			]
		],
	];

	foreach ( $styles as $block => $block_styles ) {
		foreach ( $block_styles as $style_props ) {
			register_block_style( $block, $style_props );
		}
	}

}

