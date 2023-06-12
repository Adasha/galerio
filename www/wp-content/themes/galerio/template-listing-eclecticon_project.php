<?php
	/*
	 * Template Name: Projects Listing
	 */
?>
<?php get_header(); ?>

	<div class="content-wrap">
		<main class="main main-fullwidth">
			<?php while ( have_posts() ): the_post(); ?>
				<h2 class="page-title"><?php the_title(); ?></h2>

				<?php
				$cpt           = 'collection';
				$cpt_taxonomy  = 'collection_category';
				$base_category = '';

				$args = array(
					'paged'     => eclecticon_get_page_var(),
					'post_type' => $cpt,
				);

				$args_tax = array(
					'tax_query' => array(
						array(
							'taxonomy'         => $cpt_taxonomy,
							'field'            => 'term_id',
							'terms'            => intval( $base_category ),
							'include_children' => true
						)
					)
				);

				if ( empty( $base_category ) || $base_category < 1 ) {
					$q = new WP_Query( $args );
				} else {
					$q = new WP_Query( array_merge( $args, $args_tax ) );
				}
				?>

				<?php while ( $q->have_posts() ): $q->the_post(); ?>
					<?php get_template_part( 'part', 'eclecticon_project-item' ); ?>
				<?php endwhile; ?>

			<?php endwhile; ?>

			<?php eclecticon_pagination( array(), $q ); ?>
		</main>
	</div>

<?php get_footer(); ?>