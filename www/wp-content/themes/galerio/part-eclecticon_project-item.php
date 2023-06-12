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
<div class="item" style="<?php echo esc_attr( $style ); ?>">
	<div class="item-details">
		<?php
			$title_style = '';
			$title_color = get_post_meta( get_the_ID(), 'eclecticon_project_title_color', true );
			if ( ! empty( $title_color ) ) {
				$title_style = sprintf( 'style="color: %s;"', esc_attr( $title_color ) );
			}
		?>
		<p class="item-title" <?php echo $title_style; ?>><?php the_title(); ?></p>

		<a href="<?php the_permalink(); ?>" class="item-more btn"><?php echo get_theme_mod( 'project_read_more', esc_html__( 'View Project', 'eclecticon' ) ); ?></a>
	</div>
</div>