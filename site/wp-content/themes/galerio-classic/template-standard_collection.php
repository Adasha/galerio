<?php
/*
* Template Name: Standard Collection
* Template Post Type: collection
*/
?>
<?php get_header(); ?>
<?php $the_pod = pods( 'collection', get_the_ID() ); ?>


<div class="content-wrap">
	<main class="main main-fullwidth">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="entry-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>

				<div class="">

					<h2 class="entry-title"><?php the_title(); ?></h2>


					<?php if ( $the_pod->field( 'standfirst' ) )
					{ ?>
						<div class="standfirst"><?php echo $the_pod->display( 'standfirst' ); ?></div>
					<?php } ?>


					<div class="entry-meta">
						<?php the_terms( get_the_ID(), 'collection_category' ); ?>
					</div>

				</div>

					
				<div class="entry-content">

					<div>
						<?php echo $the_pod->display( 'description' ); ?>
					</div>


					<?php if ( $the_pod->field( 'notes' ) )
					{ ?>
						<div class="footnote-content"><?php echo $the_pod->display( 'notes' );  ?></div>
					<?php } ?>

				</div>


				<div class="entry-project-main">
					<div class="project-assets">
						<?php the_content(); ?>
					</div>
				</div>

			</article>
		<?php endwhile; ?>
	</main>
</div>

<?php get_footer(); ?>
