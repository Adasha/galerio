<?php

function dotart_custom_logo_setup() {
    $logo_width  = 100;
	$logo_height = 400;

    if ( get_theme_mod( 'retina_logo', false ) ) {
		$logo_width  = floor( $logo_width * 2 );
		$logo_height = floor( $logo_height * 2 );
	}

    $defaults = array(
        'height'               => $logo_width,
        'width'                => $logo_height,
        'flex-height'          => true,
        'flex-width'           => true,
        'header-text'          => array( 'site-title', 'site-description' ),
        'unlink-homepage-logo' => true, 
    );
 
    add_theme_support( 'custom-logo', $defaults );
}


function dotart_post_thumbnail_setup() {
    add_theme_support( 'post-thumbnails' );
    set_post_thumbnail_size( 1200, 9999 );
}


function dotart_theme_support() {
    dotart_custom_logo_setup();

    dotart_post_thumbnail_setup();

    add_theme_support( 'title-tag' );

    $html5_elements = array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' );
    add_theme_support( 'html5', $html5_elements );

    add_theme_support( 'align-wide' );

    add_theme_support( 'custom-background' );

    add_theme_support( 'responsive-embeds' );

    add_theme_support( 'automatic-feed-links' );
}


add_action( 'after_setup_theme', 'dotart_theme_support' );

?>