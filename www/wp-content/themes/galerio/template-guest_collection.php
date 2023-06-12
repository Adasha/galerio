<?php
/*
* Template Name: Guest Collection
* Template Post Type: collection
*/
?>
<?php get_header(); ?>

<div class="content-wrap">
	<main class="main main-fullwidth">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="entry-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>

				<div class="guest-header">
					<strong>swansea.art</strong> presents...
				</div>

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
