<?php
/*
* Template Name: Custom Collection
* Template Post Type: collection
*/
?>
<?php get_header(); ?>

<div class="content-wrap">
	<main class="main main-fullwidth custom-container">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="entry-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>

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
