<?php
/**
 * DotArt Theme header file
 */
?><!DOCTYPE html>

<html <?php language_attributes(); ?>>

    <head>

        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" >

        <?php wp_head(); ?>

    </head>


    <body <?php body_class(); ?>>

        <?php wp_body_open(); ?>

        <div id="page-wrap">

            <div id="header-wrap">

                <header class="site-header">

                    <div class="site-logo">
                        <?php
                        $custom_logo_id = get_theme_mod( 'custom_logo' );
                        $site_logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
                        $site_name = get_bloginfo( 'name' );
                        
                        if ( has_custom_logo() ) {
                            echo '<img src="' . esc_url( $site_logo[0] ) . '" alt="' . $site_name . '">';
                        } else {
                            echo '<h1>' . $site_name . '</h1>';
                        }
                        ?>
                    </div>

                </header>

                <nav class="site-nav">

                </nav>

            </div>