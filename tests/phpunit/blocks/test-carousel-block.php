<?php
/**
 * Carousel block tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Carousel_Block;

/**
 * The Carousel block's query and markup.
 *
 * @covers \Galerio\Blocks\Carousel_Block
 */
class Test_Carousel_Block extends Galerio_TestCase {

	/**
	 * Collections, oldest first.
	 *
	 * @var int[]
	 */
	private $collections = array();

	/**
	 * Create three collections a day apart, each with a featured image.
	 */
	public function set_up() {
		parent::set_up();

		$image = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );

		foreach ( array( 'Shore', 'Harbour', 'Lighthouse' ) as $day => $title ) {
			$id = self::factory()->post->create(
				array(
					'post_type'  => 'galerio_collection',
					'post_title' => $title,
					'post_date'  => sprintf( '2026-05-0%d 10:00:00', $day + 1 ),
				)
			);
			set_post_thumbnail( $id, $image );
			$this->collections[] = $id;
		}
	}

	/**
	 * Render the block from its attributes and, for a Query Loop, inner markup.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $inner      Inner blocks' markup.
	 * @return string
	 */
	private function render( $attributes, $inner = '' ) {
		$json = wp_json_encode( $attributes );

		return do_blocks( $inner ? "<!-- wp:galerio/carousel $json -->$inner<!-- /wp:galerio/carousel -->" : "<!-- wp:galerio/carousel $json /-->" );
	}

	/**
	 * The block registers from its compiled metadata.
	 */
	public function test_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'galerio/carousel' ), 'Run `npm run build` before the tests.' );
	}

	/**
	 * By default the block shows the latest items, newest first.
	 */
	public function test_latest_items_newest_first() {
		$html = $this->render( array( 'perPage' => 2 ) );

		$this->assertSame( 2, substr_count( $html, 'wp-block-galerio-carousel__slide' ) );
		$this->assertLessThan( strpos( $html, 'Harbour' ), strpos( $html, 'Lighthouse' ) );
		$this->assertStringNotContainsString( 'Shore', $html );
		$this->assertStringContainsString( 'wp-block-galerio-carousel__image', $html );
	}

	/**
	 * Chosen items show in the order chosen, whatever the other settings say.
	 */
	public function test_chosen_items_keep_their_order() {
		list( $shore, $harbour, $lighthouse ) = $this->collections;

		$args = Carousel_Block::query_args(
			array(
				'postIds' => array( $harbour, $shore ),
				'perPage' => 1,
				'orderBy' => 'title',
			)
		);

		$this->assertSame( array( $harbour, $shore ), $args['post__in'] );
		$this->assertSame( 'post__in', $args['orderby'] );
		$this->assertSame( 2, $args['posts_per_page'] );

		$html = $this->render( array( 'postIds' => array( $harbour, $shore ) ) );
		$this->assertLessThan( strpos( $html, 'Shore' ), strpos( $html, 'Harbour' ) );
		$this->assertStringNotContainsString( 'Lighthouse', $html );
	}

	/**
	 * Settings from the block are checked before they reach the query.
	 */
	public function test_query_settings_are_checked() {
		$args = Carousel_Block::query_args(
			array(
				'postType' => 'no_such_type',
				'perPage'  => 500,
				'orderBy'  => 'meta_value',
				'order'    => 'sideways',
			)
		);

		$this->assertSame( 'post', $args['post_type'] );
		$this->assertSame( Carousel_Block::MAX_ITEMS, $args['posts_per_page'] );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
	}

	/**
	 * Terms filter the items only when their taxonomy belongs to the content type.
	 */
	public function test_terms_filter_by_the_types_taxonomy() {
		$taxonomy = get_object_taxonomies( 'galerio_collection' )[0];

		$args = Carousel_Block::query_args(
			array(
				'taxonomy' => $taxonomy,
				'terms'    => array( 7, '8' ),
			)
		);
		$this->assertSame( $taxonomy, $args['tax_query'][0]['taxonomy'] );
		$this->assertSame( array( 7, 8 ), $args['tax_query'][0]['terms'] );

		$args = Carousel_Block::query_args(
			array(
				'taxonomy' => 'post_tag',
				'terms'    => array( 7 ),
			)
		);
		$this->assertArrayNotHasKey( 'tax_query', $args );
	}

	/**
	 * With a button, the title is plain and the button's name includes it;
	 * without one, the title is the link.
	 */
	public function test_button_or_linked_title() {
		$link = get_permalink( $this->collections[2] );

		$html = $this->render(
			array(
				'perPage'    => 1,
				'buttonText' => 'View Collection',
			)
		);
		$this->assertStringContainsString( 'View Collection<span class="screen-reader-text">: Lighthouse</span>', $html );
		$this->assertStringNotContainsString( '<a href="' . $link . '">Lighthouse</a>', $html );

		$html = $this->render( array( 'perPage' => 1 ) );
		$this->assertStringContainsString( '<a href="' . $link . '">Lighthouse</a>', $html );
		$this->assertStringNotContainsString( 'wp-block-galerio-carousel__button', $html );
	}

	/**
	 * The wrapper describes itself to assistive technology and to view.js.
	 */
	public function test_wrapper_and_controls() {
		$html = $this->render(
			array(
				'autoplay'       => true,
				'interval'       => 6000,
				'transition'     => 'fade',
				'slidesPerView'  => 3,
				'arrowsPosition' => 'caption',
				'label'          => 'Latest exhibitions',
				'height'         => '60vh',
			)
		);

		$this->assertStringContainsString( 'role="region"', $html );
		$this->assertStringContainsString( 'aria-label="Latest exhibitions"', $html );
		$this->assertStringContainsString( 'wp-block-galerio-carousel__pause', $html );
		$this->assertStringContainsString( '--galerio-carousel-height:60vh', $html );
		// A fade shows one slide at a time.
		$this->assertStringContainsString( '--galerio-carousel-per-view:1', $html );
		// Arrows sit on each slide's caption instead of at the sides.
		$this->assertSame( 3, substr_count( $html, 'wp-block-galerio-carousel__caption"><div class="wp-block-galerio-carousel__nav"' ) );
		$this->assertStringContainsString( 'has-arrows-caption', $html );

		preg_match( '/data-galerio-carousel="([^"]+)"/', $html, $match );
		$settings = json_decode( html_entity_decode( $match[1] ), true );
		$this->assertSame( 'fade', $settings['transition'] );
		$this->assertTrue( $settings['autoplay'] );
		$this->assertSame( 6000, $settings['interval'] );
	}

	/**
	 * A height that isn't a plain CSS length is ignored.
	 */
	public function test_unsafe_height_is_ignored() {
		$html = $this->render( array( 'height' => '1px;background:url(x)' ) );

		$this->assertStringNotContainsString( '--galerio-carousel-height', $html );
		$this->assertStringNotContainsString( 'url(x)', $html );
	}

	/**
	 * Nothing shows when there are no items, or no Query Loop inside.
	 */
	public function test_nothing_to_show() {
		$this->assertSame( '', trim( $this->render( array( 'postType' => 'page' ) ) ) );
		$this->assertSame( '', trim( $this->render( array( 'source' => 'loop' ) ) ) );
	}

	/**
	 * A Query Loop's output goes inside the carousel's viewport.
	 */
	public function test_query_loop_source() {
		$html = $this->render( array( 'source' => 'loop' ), '<div class="wp-block-query"><ul class="wp-block-post-template"><li>One</li></ul></div>' );

		$this->assertStringContainsString( 'is-source-loop', $html );
		$this->assertStringContainsString( '__viewport"><div class="wp-block-query"><ul class="wp-block-post-template"><li>One</li>', $html );
		$this->assertStringNotContainsString( 'wp-block-galerio-carousel__track', $html );
	}

	/**
	 * A template part draws each slide, for its own item; a part that doesn't
	 * exist falls back to the built-in caption.
	 */
	public function test_slide_part() {
		// A part saved in the Site Editor, as the theme's own would be.
		$part = self::factory()->post->create(
			array(
				'post_type'    => 'wp_template_part',
				'post_name'    => 'card',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:post-title {"className":"test-card"} /-->',
			)
		);
		wp_set_object_terms( $part, get_stylesheet(), 'wp_theme' );
		wp_set_object_terms( $part, 'uncategorized', 'wp_template_part_area' );

		$html = $this->render( array( 'slidePart' => 'card' ) );

		$this->assertStringContainsString( 'has-slide-part', $html );
		$this->assertSame( 3, substr_count( $html, 'wp-block-galerio-carousel__slide is-part' ) );
		foreach ( array( 'Shore', 'Harbour', 'Lighthouse' ) as $title ) {
			$this->assertMatchesRegularExpression( "/test-card[^>]*>$title</", $html );
		}
		$this->assertStringNotContainsString( 'wp-block-galerio-carousel__caption', $html );

		$html = $this->render( array( 'slidePart' => 'no-such-part' ) );
		$this->assertStringNotContainsString( 'has-slide-part', $html );
		$this->assertStringContainsString( 'wp-block-galerio-carousel__caption', $html );
	}

	/**
	 * A carousel can show the current item's related items, as arranged on
	 * the item or in another order, and is hidden when there are none.
	 */
	public function test_related_items() {
		$fields  = galerio_fields();
		$make    = function ( $type, $title ) {
			return self::factory()->post->create(
				array(
					'post_type'   => $type,
					'post_title'  => $title,
					'post_status' => 'publish',
				)
			);
		};
		$profile = $make( 'galerio_profile', 'Ana Morgan' );
		$loner   = $make( 'galerio_profile', 'Tomos Price' );
		$tide    = $make( 'galerio_work', 'Tide' );
		$bay     = $make( 'galerio_work', 'Bay' );
		$fields->save( $fields->get( 'profile.works' ), $profile, array( $tide, $bay ) );

		$this->go_to( get_permalink( $profile ) );
		the_post();

		$args = Carousel_Block::query_args(
			array(
				'related' => 'profile.works',
				'orderBy' => 'post__in',
				'order'   => 'asc',
				'postIds' => array( $bay ),
			)
		);
		$this->assertSame( 'galerio_work', $args['post_type'] );
		$this->assertSame( array( $tide, $bay ), $args['post__in'] );
		$this->assertSame( 'post__in', $args['orderby'] );

		$html = $this->render(
			array(
				'related' => 'profile.works',
				'orderBy' => 'title',
				'order'   => 'asc',
			)
		);
		$this->assertLessThan( strpos( $html, 'Tide' ), strpos( $html, 'Bay' ) );

		$this->go_to( get_permalink( $loner ) );
		the_post();
		$this->assertSame( '', trim( $this->render( array( 'related' => 'profile.works' ) ) ) );
	}

	/**
	 * Without a relationship, "as arranged" isn't an order.
	 */
	public function test_saved_order_needs_a_relationship() {
		$args = Carousel_Block::query_args( array( 'orderBy' => 'post__in' ) );

		$this->assertSame( 'date', $args['orderby'] );
	}
}
