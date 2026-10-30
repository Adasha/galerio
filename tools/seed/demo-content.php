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
	delete_option( 'galerio_demo_more_seeded' );
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
 * @param bool   $dark    A dark background, for testing light text and covers over dark images.
 * @return int Attachment ID, or 0 if GD is unavailable.
 */
function galerio_demo_image( $title, $seed, $width = 1600, $height = 1200, $credit = '', $dark = false ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		WP_CLI::warning( 'GD is not available; skipping demo images.' );
		return 0;
	}

	mt_srand( $seed ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_seeding_mt_srand -- Deterministic demo images.

	$image = imagecreatetruecolor( $width, $height );
	$bg    = $dark
		? imagecolorallocate( $image, mt_rand( 10, 45 ), mt_rand( 10, 40 ), mt_rand( 20, 55 ) )
		: imagecolorallocate( $image, mt_rand( 200, 245 ), mt_rand( 195, 240 ), mt_rand( 185, 235 ) );
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
 * Block markup for an image, as the editor saves it.
 *
 * @param int    $id      Attachment ID.
 * @param string $caption Optional caption.
 * @return string
 */
function galerio_demo_image_block( $id, $caption = '' ) {
	if ( ! $id ) {
		return '';
	}

	return sprintf(
		'<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="%2$s" alt="%3$s" class="wp-image-%1$d"/>%4$s</figure><!-- /wp:image -->',
		$id,
		esc_url( wp_get_attachment_image_url( $id, 'large' ) ),
		esc_attr( get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
		$caption ? '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>' : ''
	);
}

/**
 * Block markup for a gallery of images.
 *
 * @param int[] $ids Attachment IDs.
 * @return string
 */
function galerio_demo_gallery_block( array $ids ) {
	return '<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">'
		. implode( '', array_map( 'galerio_demo_image_block', array_filter( $ids ) ) )
		. '</figure><!-- /wp:gallery -->';
}

/**
 * Block markup for a paragraph.
 *
 * @param string $text Plain text.
 * @return string
 */
function galerio_demo_paragraph( $text ) {
	return '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
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
				'notes'            => '<p>Portrait photograph by Tomos Price.</p>',
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

	$works  = array();
	$images = array();
	foreach ( $work_specs as $i => list( $title, $creator, $year, $medium, $category, $dimensions, $medium_term ) ) {
		$credit   = get_the_title( $profiles[ $creator ] );
		$image    = galerio_demo_image( $title, 100 + $i, 1600, 1200, $credit );
		$detail   = galerio_demo_image( $title . ' (detail)', 200 + $i, 1200, 1200, $credit );
		$images[] = $image;
		$works[]  = galerio_demo_add(
			'galerio_work',
			array(
				'post_title'       => $title,
				'post_content'     => galerio_demo_paragraph( 'A demo work by ' . $credit . '.' )
					. galerio_demo_image_block( $detail, 'Detail' ),
				'definitive_title' => $title,
				'creator'          => $profiles[ $creator ],
				'year'             => $year . '-01-01',
				'definitive_image' => $image,
				'images'           => array( $detail ),
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

	// Profiles show their work: a featured image of it, and an image in the main text.
	foreach ( array(
		'ana'   => 0,
		'tomos' => 2,
		'kiln'  => 4,
	) as $key => $index ) {
		if ( $images[ $index ] ) {
			set_post_thumbnail( $profiles[ $key ], $images[ $index ] );
		}
		wp_update_post(
			array(
				'ID'           => $profiles[ $key ],
				'post_content' => galerio_demo_paragraph( 'Recent work, shown in the studio.' )
					. galerio_demo_image_block( $images[ $index + 1 ], get_the_title( $works[ $index + 1 ] ) ),
			)
		);
	}

	// Collections: one current exhibition and one permanent collection.
	$exhibition_images = array_slice( $images, 0, 4 );
	$exhibition        = galerio_demo_add(
		'galerio_collection',
		array(
			'post_title'         => 'Shorelines',
			'post_content'       => galerio_demo_paragraph( 'Installation views from the opening week.' )
				. galerio_demo_gallery_block( $exhibition_images ),
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

	$ceramics = galerio_demo_add(
		'galerio_collection',
		array(
			'post_title'   => 'Studio Ceramics',
			'post_excerpt' => 'Work from the Kiln Collective.',
			'post_content' => galerio_demo_paragraph( 'Pieces from the collective\'s wood-fired kiln.' )
				. galerio_demo_gallery_block( array_slice( $images, 4 ) ),
			'profiles'     => array( $profiles['kiln'] ),
			'works'        => array_slice( $works, 4 ),
		),
		array( 'galerio_collection_category' => array( 'Permanent collection' ) )
	);

	if ( $images[0] ) {
		set_post_thumbnail( $exhibition, $images[0] );
	}
	if ( $images[4] ) {
		set_post_thumbnail( $ceramics, $images[4] );
	}

	// Article.
	$article = galerio_demo_add(
		'galerio_article',
		array(
			'post_title'   => 'In conversation: Shorelines',
			'post_excerpt' => 'The artists behind Shorelines talk about the coast.',
			'post_content' => galerio_demo_paragraph( 'A demo article about the Shorelines exhibition.' )
				. galerio_demo_image_block( $images[2], 'Copperworks I, Tomos Price' )
				. galerio_demo_paragraph( 'Both artists returned to the same stretch of coast over several years.' ),
			'standfirst'   => 'Ana Morgan and Tomos Price on painting and photographing the same place.',
			'authors'      => array( $profiles['tomos'] ),
			'profiles'     => array( $profiles['ana'], $profiles['tomos'] ),
			'works'        => array( $works[0], $works[2] ),
			'collections'  => array( $exhibition ),
		),
		array( 'galerio_article_category' => array( 'Interview' ) )
	);
	if ( $images[1] ) {
		set_post_thumbnail( $article, $images[1] );
	}

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

/**
 * More demo content, for trying blocks that need many items: Query Loops of
 * several rows, row limits and scrolling, carousels, and the exhibitions
 * lists. Four more creators, twelve works, twelve exhibitions (four on now,
 * four upcoming, four past, by today's date) and three articles. About half
 * the images are dark.
 *
 * Added with `tools/seed-demo.sh --more`, after the main demo content. The
 * test environment doesn't get it, as its tests count the demo items.
 */
function galerio_demo_more() {
	if ( get_option( 'galerio_demo_more_seeded' ) ) {
		WP_CLI::success( 'The extra demo content already exists. Run tools/seed-demo.sh --reset --more to recreate it.' );
		return;
	}

	$people = array(
		'rhian'  => array( 'Rhian Evans', 'Printmaker cutting large woodblocks of hill farms.', 'Printmaking', 'Printmaker', 1969 ),
		'owen'   => array( 'Owen Hughes', 'Sculptor working in reclaimed steel and slate.', 'Sculpture', 'Sculptor', 1990 ),
		'mei'    => array( 'Mei Chen', 'Textile artist weaving with dyes from the Tawe valley.', 'Textiles', 'Textile artist', 1987 ),
		'dafydd' => array( 'Dafydd Jones', 'Video artist filming tides and estuaries.', 'Video', 'Video artist', 1995 ),
	);

	$profiles = array();
	$seed     = 500;
	foreach ( $people as $key => list( $name, $excerpt, $medium, $role, $born ) ) {
		$profiles[ $key ] = galerio_demo_add(
			'galerio_profile',
			array(
				'post_title'       => $name,
				'post_excerpt'     => $excerpt,
				'nationality'      => 'Welsh',
				'show_nationality' => 1,
				'year_of_birth'    => $born . '-01-01',
				'show_lifespan'    => 1,
				'show_portrait'    => 1,
				'portrait'         => galerio_demo_image( $name . ' portrait', ++$seed, 800, 1000, '', 0 === $seed % 2 ),
				'bio'              => '<p>' . esc_html( $excerpt ) . '</p>',
			),
			array(
				'galerio_medium' => array( $medium ),
				'galerio_role'   => array( $role ),
				'galerio_group'  => array( 'Graduate Show 2026' ),
			)
		);
	}

	$titles  = array(
		'rhian'  => array( 'Hafod in Winter', 'Sheepfold, Mynydd Du', 'Bracken Line' ),
		'owen'   => array( 'Slate Column', 'Rivet', 'Weighbridge' ),
		'mei'    => array( 'Tawe Blue', 'Weir', 'Undercurrent' ),
		'dafydd' => array( 'Estuary, Twelve Hours', 'Low Water', 'Spring Tide' ),
	);
	$mediums = array(
		'rhian'  => array( 'Woodcut', 'Printmaking' ),
		'owen'   => array( 'Steel and slate', 'Sculpture' ),
		'mei'    => array( 'Hand-dyed wool', 'Textiles' ),
		'dafydd' => array( 'Single-channel video', 'Video' ),
	);

	$works  = array();
	$images = array();
	foreach ( $titles as $key => $list ) {
		foreach ( $list as $i => $title ) {
			$dark     = 0 === count( $images ) % 2;
			$credit   = $people[ $key ][0];
			$image    = galerio_demo_image( $title, 600 + count( $images ), 1600, 1200, $credit, $dark );
			$images[] = $image;
			$works[]  = galerio_demo_add(
				'galerio_work',
				array(
					'post_title'       => $title,
					'post_content'     => galerio_demo_paragraph( 'A demo work by ' . $credit . '.' ),
					'definitive_title' => $title,
					'creator'          => $profiles[ $key ],
					'year'             => ( 2020 + $i ) . '-01-01',
					'definitive_image' => $image,
					'medium'           => $mediums[ $key ][0],
					'dimensions'       => 'video' === strtolower( $mediums[ $key ][1] ) ? '' : ( 40 + 20 * $i ) . ' × ' . ( 30 + 15 * $i ) . ' cm',
				),
				array(
					'galerio_medium' => array( $mediums[ $key ][1] ),
					'galerio_label'  => array( 'Demo' ),
				)
			);
		}
	}

	foreach ( $profiles as $key => $id ) {
		$index = array_search( $key, array_keys( $profiles ), true ) * 3;
		if ( $images[ $index ] ) {
			set_post_thumbnail( $id, $images[ $index ] );
		}
	}

	// Exhibitions, by today's date: four on now, four upcoming, four past.
	// Each is [ title, start, end or '', category, location ].
	$exhibitions = array(
		array( 'Hill Farms', '-10 days', '+3 weeks', 'Exhibitions', 'Main Gallery' ),
		array( 'Steel and Slate', '-5 weeks', '+10 days', 'Exhibitions', 'Gallery 2' ),
		array( 'Graduate Show 2026', '-3 days', '+5 weeks', 'Graduate exhibition', 'Whole building' ),
		array( 'The Tawe in Thread', '-2 months', '', 'Exhibitions', 'Project Space' ),
		array( 'Tide Tables', '+2 weeks', '+8 weeks', 'Exhibitions', 'Main Gallery' ),
		array( 'Open Studios', '+5 weeks', '+6 weeks', 'Events', 'Studios' ),
		array( 'Print Fair', '+3 months', '+3 months +2 days', 'Events', 'Main Gallery' ),
		array( 'New Work: Owen Hughes', '+6 weeks', '+12 weeks', 'Exhibitions', 'Gallery 2' ),
		array( 'Weavers of the Valley', '-8 months', '-6 months', 'Exhibitions', 'Main Gallery' ),
		array( 'Graduate Show 2025', '-16 months', '-15 months', 'Graduate exhibition', 'Whole building' ),
		array( 'Moving Water', '-3 months', '-6 weeks', 'Exhibitions', 'Project Space' ),
		array( 'Small Works', '-13 months', '-12 months', 'Exhibitions', 'Gallery 2' ),
	);

	$keys = array_keys( $profiles );
	foreach ( $exhibitions as $i => list( $title, $start, $end, $category, $location ) ) {
		$shown  = array( $works[ $i ], $works[ ( $i + 5 ) % count( $works ) ], $works[ ( $i + 7 ) % count( $works ) ] );
		$artist = $profiles[ $keys[ $i % count( $keys ) ] ];
		$id     = galerio_demo_add(
			'galerio_collection',
			array(
				'post_title'   => $title,
				'post_content' => galerio_demo_paragraph( 'A demo exhibition.' )
					. galerio_demo_gallery_block( array( $images[ $i ], $images[ ( $i + 5 ) % count( $images ) ] ) ),
				'post_excerpt' => 'A demo exhibition in the ' . $location . '.',
				'standfirst'   => $title . ': new and recent work.',
				'location'     => $location,
				'start_date'   => gmdate( 'Y-m-d', strtotime( $start ) ),
				'end_date'     => '' === $end ? '' : gmdate( 'Y-m-d', strtotime( $end ) ),
				'profiles'     => array( $artist ),
				'works'        => $shown,
			),
			array( 'galerio_collection_category' => array( $category ) )
		);
		if ( $images[ $i ] ) {
			set_post_thumbnail( $id, $images[ $i ] );
		}
	}

	// Articles.
	foreach ( array(
		array( 'Studio visit: Rhian Evans', 'rhian', 'Interview' ),
		array( 'Making Steel and Slate', 'owen', 'Behind the scenes' ),
		array( 'Graduate Show 2026 opens', 'dafydd', 'News' ),
	) as $i => list( $title, $key, $category ) ) {
		$id = galerio_demo_add(
			'galerio_article',
			array(
				'post_title'   => $title,
				'post_content' => galerio_demo_paragraph( 'A demo article.' ),
				'standfirst'   => 'A demo article about ' . $people[ $key ][0] . '.',
				'authors'      => array( $profiles[ $key ] ),
				'profiles'     => array( $profiles[ $key ] ),
				'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 1 ) . ' days' ) ),
			),
			array( 'galerio_article_category' => array( $category ) )
		);
		if ( $images[ $i * 4 ] ) {
			set_post_thumbnail( $id, $images[ $i * 4 ] );
		}
	}

	update_option( 'galerio_demo_more_seeded', time() );

	WP_CLI::success( sprintf( 'Added %d creators, %d works, %d exhibitions and 3 articles.', count( $profiles ), count( $works ), count( $exhibitions ) ) );
}

/**
 * A draft page of Galerio's Query Loops and carousel set up to try their
 * settings: current and upcoming exhibitions in two rows that scroll, works
 * in one row with the rest hidden, and a carousel of exhibitions. Made once;
 * delete the page to have it made again.
 */
function galerio_demo_playground() {
	if ( get_page_by_path( 'block-playground', OBJECT, 'page' ) ) {
		return;
	}

	$loop    = function ( $variation, array $query, $card, array $extra ) {
		$attributes = array_merge(
			array(
				'queryId'   => wp_rand( 1, 9999 ),
				'namespace' => $variation,
				'query'     => array_merge(
					array(
						'perPage' => 12,
						'pages'   => 0,
						'offset'  => 0,
						'order'   => 'desc',
						'orderBy' => 'date',
						'inherit' => false,
					),
					$query
				),
			),
			$extra
		);

		return '<!-- wp:query ' . wp_json_encode( $attributes ) . ' --><div class="wp-block-query">'
			. '<!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->'
			. '<!-- wp:template-part {"slug":"' . $card . '","tagName":"div"} /-->'
			. '<!-- /wp:post-template --></div><!-- /wp:query -->';
	};
	$heading = function ( $text ) {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html( $text ) . '</h2><!-- /wp:heading -->';
	};

	$content = galerio_demo_paragraph( 'Galerio blocks set up to try their settings. Change them in the editor to see the effect.' )
		. $heading( 'On now and upcoming: two rows' )
		. $loop(
			'galerio/exhibitions',
			array(
				'postType'      => 'galerio_collection',
				'galerioStatus' => 'current-upcoming',
			),
			'card-collection',
			array( 'galerioRows' => 2 )
		)
		. $heading( 'Works: one row, the rest hidden' )
		. $loop( 'galerio/works', array( 'postType' => 'galerio_work' ), 'card-work', array( 'galerioRows' => 1 ) )
		. $heading( 'Past exhibitions' )
		. $loop(
			'galerio/exhibitions',
			array(
				'postType'      => 'galerio_collection',
				'galerioStatus' => 'past',
			),
			'card-collection',
			array()
		)
		. $heading( 'Carousel' )
		. '<!-- wp:galerio/carousel {"postType":"galerio_collection","perPage":8,"slidesPerView":2,"height":"60vh"} /-->';

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'draft',
			'post_title'   => 'Block playground',
			'post_name'    => 'block-playground',
			'post_content' => $content,
		)
	);
	update_post_meta( $id, GALERIO_DEMO_META, 1 );

	WP_CLI::success( sprintf( 'Made the draft page "Block playground": %s', admin_url( 'post.php?post=' . $id . '&action=edit' ) ) );
}

galerio_demo_seed( $args );

if ( in_array( 'more', $args, true ) ) {
	galerio_demo_more();
	galerio_demo_playground();
}
