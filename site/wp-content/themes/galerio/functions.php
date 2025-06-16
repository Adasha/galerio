<?php
/**
 * 
 */

add_action( 'init', 'galerio_register_block_styles' );

function galerio_register_block_styles() {
	
  	register_block_style(
		'core/post-terms',
	 	[
	    	'name'       => 'buttons',
			'label'      => __( 'Buttons', 'galerio' )
	    ]
	);
	
  	register_block_style(
		'core/navigation',
	 	[
	    	'name'       => 'main-menu',
			'label'      => __( 'Main Menu', 'galerio' )
	    ]
	);

}

