<?php
/*
 * Template Name: Homepage Template
 * Template Post Type: page
 */
?>
<?php get_header(); ?>

<div class="content-wrap content-wrap-cover">
	<main class="main main-home">

		<?php
			$q    = false;
			$args = false;

			if ( get_theme_mod( 'home_slider_postids' ) ) {
				$args = array(
					'post_type'      => array( 'collection', 'post', 'page' ),
					'post__in'       => explode( ',', get_theme_mod( 'home_slider_postids' ) ),
					'posts_per_page' => get_theme_mod( 'home_slider_limit', 5 ),
					'orderby'        => 'post__in',
				);
			} elseif ( get_theme_mod( 'home_slider_term' ) ) {
				$args = array(
					'post_type'      => 'collection',
					'tax_query'      => array(
						array(
							'taxonomy' => 'collection_category',
							'terms'    => get_theme_mod( 'home_slider_term' )
						),
					),
					'posts_per_page' => get_theme_mod( 'home_slider_limit', 5 ),
				);
			} else {
				$args = array(
					'post_type'      => 'collection',
					'posts_per_page' => get_theme_mod( 'home_slider_limit', 5 ),
				);
			}

			if ( $args !== false ) {
				$q = new WP_Query( $args );
			}

			$attributes = sprintf( 'data-slideshow="%s" data-animation="%s" data-slideshowspeed="%s" data-animationspeed="%s"',
				esc_attr( get_theme_mod( 'home_slider_slideshow', 1 ) ),
				esc_attr( get_theme_mod( 'home_slider_animation', 'fade' ) ),
				esc_attr( get_theme_mod( 'home_slider_slideshowSpeed', 3000 ) ),
				esc_attr( get_theme_mod( 'home_slider_animationSpeed', 600 ) )
			);
		?>
		<?php if ( $args !== false && $q !== false && $q->have_posts() ): ?>
			<div class="home-slider ci-slider" <?php echo $attributes; ?>>
				<ul class="slides">
					<?php while ( $q->have_posts() ): $q->the_post(); ?>
						<?php
							$style = '';
							$image = '';
							$image = eclecticon_get_image_src( get_post_thumbnail_id(), 'eclecticon_wide' );
							if ( ! empty( $image ) ) {
								$style = sprintf( 'background-image: url(%s);', esc_url( $image ) );
								$cover = get_post_meta( get_the_ID(), 'eclecticon_project_image_cover', true );
								$style .= ' background-size: cover;';
							}
						?>
						<li style="<?php echo esc_attr( $style ); ?>">
							<div class="item-details">
								<?php if ( $q->post_count > 1 ): ?>
									<div class="item-navigation">
										<a href="#" class="item-prev"><i class="fa fa-angle-left"></i></a>
										<a href="#" class="item-next"><i class="fa fa-angle-right"></i></a>
									</div>
								<?php endif; ?>

								<?php
									$title_style = '';
									$title_color = get_post_meta( get_the_ID(), 'eclecticon_project_title_color', true );
									if ( ! empty( $title_color ) ) {
										$title_style = sprintf( 'style="color: %s;"', esc_attr( $title_color ) );
									}
								?>
								<p class="item-title" <?php echo $title_style; ?>><?php the_title(); ?></p>

								<a href="<?php the_permalink(); ?>" class="item-more btn"><?php echo get_theme_mod( 'project_read_more', esc_html__( 'View Collection', 'eclecticon' ) ); ?></a>
							</div>
						</li>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				</ul>
			</div>
		<?php endif; ?>

	</main>
</div>

<?php get_footer(); ?>