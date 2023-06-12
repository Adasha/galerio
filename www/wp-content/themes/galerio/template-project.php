<?php
/*
* Template Name: Project Page
* Template Post Type: collection
*/
?>
<?php get_header(); ?>
<?php $the_pod = pods( 'collection', get_the_ID() ); ?>


<div class="content-wrap">
	<main class="main main-fullwidth">
		<?php while ( have_posts() ): the_post(); ?>
			<article id="entry-<?php the_ID(); ?>" <?php post_class( 'entry entry-project' ); ?>>

				<div class="entry-project-side">
					<h2 class="entry-title"><?php the_title(); ?></h2>


					<div class="entry-meta">
						<?php the_terms( get_the_ID(), 'collection_category' ); ?>
					</div>
					

					<?php if ( $the_pod->field( 'standfirst' ) )
					{ ?>
						<div class="standfirst"><?php echo $the_pod->display( 'standfirst' ); ?></div>
					<?php } ?>


					<div class="entry-content">

						<div>
							<?php echo $the_pod->display( 'description' ); ?>
						</div>

						<?php
							if ( $the_pod->field( 'website_url' ) )
							{
								$the_url = $the_pod->display( 'website_url' );
								if ( $the_pod->field( 'website_name' ) )
								{
									$the_webname = $the_pod->display( 'website_name' );
								}
								else
								{
									$the_webname = $the_pod->display( 'website_url' );
								}
						?>
						<dl class="web-link">
							<dt>Website:</dt>
							<dd<?php if ( $the_pod->field( 'dead_link' ) ) echo ' style="text-decoration: line-through"' ?>>
								<a href="<?php echo $the_url ?>" target="_blank" rel="noopener"><?php echo $the_webname; ?></a>
							</dd>
						</dl>
						<?php } ?>

						<?php if ( $the_pod->field( 'notes' ) )
						{ ?>
							<div class="footnote-content"><?php echo $the_pod->display( 'notes' );  ?></div>
						<?php } ?>

					</div>
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
