<?php get_header(); ?>

<div class="content-wrap">
	<main class="main main-fullwidth">
		<h2 class="page-title"><?php the_archive_title(); ?></h2>

		<?php while ( have_posts() ): the_post(); ?>
			<?php get_template_part( 'part', 'eclecticon_project-item' ); ?>
		<?php endwhile; ?>

		<?php eclecticon_pagination(); ?>
	</main>
</div>

<?php get_footer(); ?>
