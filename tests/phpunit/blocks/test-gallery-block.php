<?php
/**
 * Gallery block tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Gallery_Block;

/**
 * The Gallery block's images, order and markup.
 *
 * @covers \Galerio\Blocks\Gallery_Block
 */
class Test_Gallery_Block extends Galerio_TestCase {

	/**
	 * Images, in saved order.
	 *
	 * @var int[]
	 */
	private $images = array();

	/**
	 * Collection holding the images.
	 *
	 * @var int
	 */
	private $collection;

	/**
	 * Create three images and a collection that shows them.
	 */
	public function set_up() {
		parent::set_up();

		foreach ( array( 'Shore', 'Harbour', 'Lighthouse' ) as $title ) {
			$id = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
			wp_update_post(
				array(
					'ID'           => $id,
					'post_title'   => $title,
					'post_excerpt' => "$title at dawn",
				)
			);
			$this->images[] = $id;
		}

		$this->collection = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_collection',
				'post_status' => 'publish',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'collection.images' ), $this->collection, $this->images );
	}

	/**
	 * Render the block in the collection's context.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function render( $attributes = array() ) {
		$this->go_to( get_permalink( $this->collection ) );
		the_post();

		return do_blocks( '<!-- wp:galerio/gallery ' . wp_json_encode( (object) $attributes ) . ' /-->' );
	}

	/**
	 * The block registers from its compiled metadata.
	 */
	public function test_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'galerio/gallery' ), 'Run `npm run build` before the tests.' );
	}

	/**
	 * By default a gallery shows the current item's images, in saved order.
	 */
	public function test_current_items_images() {
		$html = $this->render();

		$this->assertSame( 3, substr_count( $html, 'class="wp-block-galerio-gallery__item"' ) );
		$this->assertLessThan( strpos( $html, 'Lighthouse at dawn' ), strpos( $html, 'Shore at dawn' ) );
		$this->assertStringContainsString( 'is-layout-grid', $html );
		$this->assertStringContainsString( '--galerio-gallery-columns:3', $html );
		$this->assertStringContainsString( 'href="' . wp_get_attachment_image_url( $this->images[0], 'full' ) . '"', $html );
	}

	/**
	 * The field follows the item's type, and a chosen field wins.
	 */
	public function test_field_for() {
		$this->assertSame( 'collection.images', Gallery_Block::field_for( '', 'galerio_collection' ) );
		$this->assertSame( 'work.images', Gallery_Block::field_for( '', 'galerio_work' ) );
		$this->assertSame( 'work.images', Gallery_Block::field_for( 'work.images', 'galerio_collection' ) );
		$this->assertSame( '', Gallery_Block::field_for( 'no.such_field', 'page' ) );
	}

	/**
	 * Images chosen in the block, in their order; anything not an image is dropped.
	 */
	public function test_chosen_images() {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$ids  = Gallery_Block::image_ids(
			array(
				'source' => 'media',
				'ids'    => array( $this->images[2], $page, $this->images[0] ),
				'order'  => 'saved',
			),
			0
		);

		$this->assertSame( array( $this->images[2], $this->images[0] ), $ids );
	}

	/**
	 * Automatic order follows the collection's Shuffle images switch.
	 */
	public function test_shuffle() {
		$attributes = array(
			'source' => 'field',
			'field'  => '',
			'order'  => 'auto',
		);

		$this->assertSame( $this->images, Gallery_Block::image_ids( $attributes, $this->collection ) );

		galerio_fields()->save( galerio_fields()->get( 'collection.shuffle_images' ), $this->collection, true );
		$orders = array();
		for ( $i = 0; $i < 20; $i++ ) {
			$orders[ implode( ',', Gallery_Block::image_ids( $attributes, $this->collection ) ) ] = true;
		}
		$this->assertGreaterThan( 1, count( $orders ), 'Shuffled galleries vary.' );

		$attributes['order'] = 'saved';
		$this->assertSame( $this->images, Gallery_Block::image_ids( $attributes, $this->collection ) );
	}

	/**
	 * Captions: none, the caption, or the title and caption.
	 */
	public function test_captions() {
		$this->assertStringNotContainsString( '__caption', $this->render( array( 'caption' => 'none' ) ) );
		$this->assertStringContainsString( '<figcaption class="wp-block-galerio-gallery__caption">Shore at dawn</figcaption>', $this->render() );
		$this->assertStringContainsString( '<strong class="wp-block-galerio-gallery__title">Shore</strong> Shore at dawn', $this->render( array( 'caption' => 'title' ) ) );
	}

	/**
	 * Layout settings are checked, and linear galleries are one column.
	 */
	public function test_layouts() {
		$html = $this->render(
			array(
				'layout'      => 'linear',
				'columns'     => 4,
				'aspectRatio' => '16/9',
			)
		);
		$this->assertStringContainsString( 'is-layout-linear', $html );
		$this->assertStringContainsString( '--galerio-gallery-columns:1', $html );
		$this->assertStringNotContainsString( 'galerio-gallery-ratio', $html, 'Only grids crop.' );

		$html = $this->render( array( 'aspectRatio' => '1;background:red' ) );
		$this->assertMatchesRegularExpression( '/--galerio-gallery-ratio:1[;"]/', $html );
		$this->assertStringNotContainsString( 'background:red', $html );
	}

	/**
	 * The lightbox is switchable, and carries its translated labels.
	 */
	public function test_lightbox() {
		$this->assertStringContainsString( 'data-galerio-lightbox=', $this->render() );
		$this->assertStringNotContainsString( 'data-galerio-lightbox=', $this->render( array( 'lightbox' => false ) ) );
	}

	/**
	 * Nothing shows without images.
	 */
	public function test_empty() {
		galerio_fields()->save( galerio_fields()->get( 'collection.images' ), $this->collection, array() );

		$this->assertSame( '', trim( $this->render() ) );
	}
}
