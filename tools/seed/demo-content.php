<?php
/**
 * Create Galerio demo content through the Pods API.
 *
 * Run with: wp eval-file wp-content/galerio-tools/seed/demo-content.php [reset]
 *
 * Every item created here is tagged with the `_galerio_demo` meta key, so it
 * can be found and removed again with the reset argument. Images are generated
 * with GD, so there are no licensing concerns.
 *
 * @package Galerio\Tools
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'pods' ) ) {
	WP_CLI::error( 'Pods is not active.' );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

const GALERIO_DEMO_META = '_galerio_demo';

/**
 * Delete every post and attachment previously created by this script.
 */
function galerio_demo_reset() {
	$ids         = get_posts(
		array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => GALERIO_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
	$attachments = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => GALERIO_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);

	foreach ( array_merge( $ids, $attachments ) as $id ) {
		wp_delete_post( $id, true );
	}

	delete_option( 'galerio_demo_seeded' );
	WP_CLI::log( sprintf( 'Removed %d demo items.', count( $ids ) + count( $attachments ) ) );
}

/**
 * Generate an abstract placeholder artwork and add it to the media library.
 *
 * @param string $title   Attachment title.
 * @param int    $seed    Random seed, so the same image is produced every run.
 * @param int    $width   Image width in pixels.
 * @param int    $height  Image height in pixels.
 * @param string $credit  Free-text credit for the image's creator.
 * @return int Attachment ID, or 0 if GD is unavailable.
 */
function galerio_demo_image( $title, $seed, $width = 1600, $height = 1200, $credit = '' ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		WP_CLI::warning( 'GD is not available; skipping demo images.' );
		return 0;
	}

	mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Deterministic demo images.

	$image = imagecreatetruecolor( $width, $height );
	$bg    = imagecolorallocate( $image, mt_rand( 200, 245 ), mt_rand( 195, 240 ), mt_rand( 185, 235 ) );
	imagefill( $image, 0, 0, $bg );

	for ( $i = 0; $i < 14; $i++ ) {
		$colour = imagecolorallocatealpha( $image, mt_rand( 0, 255 ), mt_rand( 0, 200 ), mt_rand( 0, 160 ), mt_rand( 20, 80 ) );
		if ( $i % 2 ) {
			imagefilledellipse( $image, mt_rand( 0, $width ), mt_rand( 0, $height ), mt_rand( 80, 700 ), mt_rand( 80, 700 ), $colour );
		} else {
			$x = mt_rand( 0, $width );
			$y = mt_rand( 0, $height );
			imagefilledrectangle( $image, $x, $y, $x + mt_rand( 60, 600 ), $y + mt_rand( 60, 500 ), $colour );
		}
	}

	$tmp = wp_tempnam( sanitize_title( $title ) . '.jpg' );
	imagejpeg( $image, $tmp, 85 );

	// Give attachments their own slug so they never take a work's permalink.
	$slug = sanitize_title( $title ) . '-image';
	$file = array(
		'name'     => $slug . '.jpg',
		'tmp_name' => $tmp,
	);
	$id   = media_handle_sideload( $file, 0, $title, array( 'post_name' => $slug ) );

	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( $id->get_error_message() );
		return 0;
	}

	update_post_meta( $id, GALERIO_DEMO_META, 1 );
	update_post_meta( $id, '_wp_attachment_image_alt', $title );
	if ( $credit ) {
		pods( 'media', $id )->save( array( 'credit' => $credit ) );
	}

	return $id;
}

/**
 * Add an item to a pod and tag it as demo content.
 *
 * @param string $pod   Pod name.
 * @param array  $data  Post fields and Pods fields.
 * @param array  $terms Taxonomy => term names.
 * @return int Post ID.
 */
function galerio_demo_add( $pod, array $data, array $terms = array() ) {
	$data = array_merge( array( 'post_status' => 'publish' ), $data );
	$id   = (int) pods( $pod )->add( $data );

	if ( ! $id ) {
		WP_CLI::error( sprintf( 'Could not create %s "%s".', $pod, $data['post_title'] ) );
	}

	update_post_meta( $id, GALERIO_DEMO_META, 1 );

	foreach ( $terms as $taxonomy => $names ) {
		wp_set_object_terms( $id, $names, $taxonomy );
	}

	return $id;
}

/**
 * Create the demo content.
 *
 * @param string[] $args Positional arguments passed to `wp eval-file`.
 */
function galerio_demo_seed( array $args ) {
	if ( in_array( 'reset', $args, true ) ) {
		galerio_demo_reset();
	}

	if ( get_option( 'galerio_demo_seeded' ) ) {
		WP_CLI::success( 'Demo content already exists. Run tools/seed-demo.sh --reset to recreate it.' );
		return;
	}

	// Creators.
	$profiles = array(
		'ana'   => galerio_demo_add(
			'galerio_profile',
			array(
				'post_title'       => 'Ana Morgan',
				'post_excerpt'     => 'Painter working with colour fields and landscape.',
				'nationality'      => 'Welsh',
				'show_nationality' => 1,
				'year_of_birth'    => '1978-01-01',
				'show_lifespan'    => 1,
				'show_portrait'    => 1,
				'portrait'         => galerio_demo_image( 'Ana Morgan portrait', 11, 800, 1000 ),
				'bio'              => '<p>Ana Morgan paints the light of the Gower coast in large, layered colour fields.</p>',
				'accolades'        => array( 'Shortlisted, National Eisteddfod Gold Medal for Fine Art (2019)', 'Residency, Elysium Gallery (2021)' ),
				'show_links'       => 1,
				'links'            => array(
					array(
						'url'   => 'https://example.com/ana-morgan',
						'label' => 'Artist\'s website',
					),
					array( 'url' => 'https://instagram.com/example' ),
				),
			),
			array(
				'galerio_medium' => array( 'Oil', 'Acrylic' ),
				'galerio_role'   => array( 'Painter' ),
				'galerio_group'  => array( 'Resident artists' ),
			)
		),
		'tomos' => galerio_demo_add(
			'galerio_profile',
			array(
				'post_title'       => 'Tomos Price',
				'post_excerpt'     => 'Photographer documenting post-industrial places.',
				'nationality'      => 'Welsh',
				'show_nationality' => 1,
				'year_of_birth'    => '1985-01-01',
				'show_lifespan'    => 1,
				'show_portrait'    => 1,
				'portrait'         => galerio_demo_image( 'Tomos Price portrait', 12, 800, 1000 ),
				'bio'              => '<p>Tomos Price photographs the copperworks and docks of the lower Swansea valley.</p>',
			),
			array(
				'galerio_medium' => array( 'Photography' ),
				'galerio_role'   => array( 'Photographer' ),
				'galerio_group'  => array( 'Resident artists' ),
			)
		),
		'kiln'  => galerio_demo_add(
			'galerio_profile',
			array(
				'post_title'    => 'Kiln Collective',
				'post_excerpt'  => 'A ceramics collective sharing a studio and kiln.',
				'is_collective' => 1,
				'year_of_birth' => '2016-01-01',
				'show_lifespan' => 1,
				'bio'           => '<p>Kiln Collective is a group of makers who share a studio, a wood-fired kiln and an annual show.</p>',
			),
			array(
				'galerio_medium' => array( 'Ceramics' ),
				'galerio_group'  => array( 'Collectives' ),
			)
		),
	);

	pods( 'galerio_profile', $profiles['kiln'] )->save( array( 'members' => array( $profiles['ana'] ) ) );

	// Works.
	$work_specs = array(
		array( 'Low Tide, Rhossili', 'ana', '2019', 'Oil on canvas', 'Landscape', '120 × 90 cm', 'Oil' ),
		array( 'Bracelet Bay at Dusk', 'ana', '2021', 'Acrylic on board', 'Landscape', '60 × 60 cm', 'Acrylic' ),
		array( 'Copperworks I', 'tomos', '2018', 'Archival pigment print', 'Documentary', '50 × 40 cm', 'Photography' ),
		array( 'Copperworks II', 'tomos', '2018', 'Archival pigment print', 'Documentary', '50 × 40 cm', 'Photography' ),
		array( 'Ash Glaze Vessel', 'kiln', '2022', 'Wood-fired stoneware', 'Functional', '32 × 18 × 18 cm', 'Ceramics' ),
		array( 'Tidal Bowls (set of five)', 'kiln', '2023', 'Porcelain', 'Installation', 'Various', 'Ceramics' ),
	);

	$works = array();
	foreach ( $work_specs as $i => list( $title, $creator, $year, $medium, $category, $dimensions, $medium_term ) ) {
		$image   = galerio_demo_image( $title, 100 + $i, 1600, 1200, get_the_title( $profiles[ $creator ] ) );
		$works[] = galerio_demo_add(
			'galerio_work',
			array(
				'post_title'       => $title,
				'post_content'     => '<!-- wp:paragraph --><p>A demo work by ' . esc_html( get_the_title( $profiles[ $creator ] ) ) . '.</p><!-- /wp:paragraph -->',
				'definitive_title' => $title,
				'creator'          => $profiles[ $creator ],
				'year'             => $year . '-01-01',
				'definitive_image' => $image,
				'medium'           => $medium,
				'dimensions'       => $dimensions,
				'location'         => 'Artist\'s studio',
			),
			array(
				'galerio_medium' => array( $medium_term ),
				'galerio_label'  => array( 'Demo', $category ),
			)
		);
		// No featured image: works fall back to their definitive image.
	}

	// Collections: one current exhibition and one permanent collection.
	$exhibition_images = array_filter( array_map( 'get_post_thumbnail_id', $works ) );
	$exhibition        = galerio_demo_add(
		'galerio_collection',
		array(
			'post_title'         => 'Shorelines',
			'post_excerpt'       => 'Paintings and photographs of the Swansea coast.',
			'standfirst'         => 'Two artists look at the same coastline.',
			'location'           => 'Main Gallery',
			'start_date'         => gmdate( 'Y-m-d', strtotime( '-2 weeks' ) ),
			'end_date'           => gmdate( 'Y-m-d', strtotime( '+6 weeks' ) ),
			'description'        => '<p>Shorelines brings together paintings by Ana Morgan and photographs by Tomos Price.</p>',
			'website'            => array(
				'url'   => 'https://example.com/shorelines',
				'label' => 'Shorelines exhibition site',
			),
			'images'             => array_values( $exhibition_images ),
			'profiles'           => array( $profiles['ana'], $profiles['tomos'] ),
			'other_contributors' => array( 'Curated by Sian Rees' ),
			'works'              => array_slice( $works, 0, 4 ),
		),
		array( 'galerio_collection_category' => array( 'Exhibitions' ) )
	);

	galerio_demo_add(
		'galerio_collection',
		array(
			'post_title'   => 'Studio Ceramics',
			'post_excerpt' => 'Work from the Kiln Collective.',
			'profiles'     => array( $profiles['kiln'] ),
			'works'        => array_slice( $works, 4 ),
		),
		array( 'galerio_collection_category' => array( 'Permanent collection' ) )
	);

	if ( get_post_thumbnail_id( $works[0] ) ) {
		set_post_thumbnail( $exhibition, get_post_thumbnail_id( $works[0] ) );
	}

	// Article.
	galerio_demo_add(
		'galerio_article',
		array(
			'post_title'   => 'In conversation: Shorelines',
			'post_excerpt' => 'The artists behind Shorelines talk about the coast.',
			'post_content' => '<!-- wp:paragraph --><p>A demo article about the Shorelines exhibition.</p><!-- /wp:paragraph -->',
			'standfirst'   => 'Ana Morgan and Tomos Price on painting and photographing the same place.',
			'profiles'     => array( $profiles['ana'], $profiles['tomos'] ),
			'works'        => array( $works[0], $works[2] ),
			'collections'  => array( $exhibition ),
		),
		array( 'galerio_article_category' => array( 'Interview' ) )
	);

	// Front page.
	$home_page = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Home',
			'post_content' => '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Galerio Demo</h1><!-- /wp:heading -->'
				. '<!-- wp:paragraph --><p>Browse <a href="/works/">works</a>, <a href="/profiles/">creators</a> and <a href="/collections/">collections</a>.</p><!-- /wp:paragraph -->',
		)
	);
	update_post_meta( $home_page, GALERIO_DEMO_META, 1 );
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_page );

	update_option( 'galerio_demo_seeded', time() );

	WP_CLI::success( sprintf( 'Created %d creators, %d works, 2 collections and 1 article.', count( $profiles ), count( $works ) ) );
}

galerio_demo_seed( $args );
