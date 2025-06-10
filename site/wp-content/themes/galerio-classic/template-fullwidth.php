<?php
/*
* Template Name: Fullwidth Page
* Template Post Type: post, page, article, work, collection, profile
*/
?>

<?php
	get_header();

	$post_ID = get_the_ID();

	$suppress_thumbs_globally = get_option( 'artcms_settings_suppress_featured_images' );
?>

<div class="content-wrap">
	<main class="main main-fullwidth">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="entry-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
				<?php if ( has_post_thumbnail() && !$suppress_thumbs_globally): ?>
					<figure class="entry-thumb">
						<a class="ci-lightbox" href="<?php echo esc_url( wp_get_attachment_image_url( get_post_thumbnail_id(), 'large' ) ); ?>"><?php the_post_thumbnail( 'eclecticon_wide' ); ?></a>
					</figure>
				<?php endif; ?>

				<h1 class="entry-title"><?php the_title(); ?></h1>
				
				<div class="entry-content col-narrow">
					<?php the_content(); ?>
					<?php wp_link_pages(); ?>
				</div>

				<div class="col-narrow">
					<?php comments_template(); ?>
				</div>
			</article>
		<?php endwhile; ?>
	</main>
</div>

<?php get_footer(); ?>
