<?php
/**
 * Focal point tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Media\Focal_Point;

/**
 * An image's focal point keeps its subject in view wherever it's cropped.
 *
 * @covers \Galerio\Core\Media\Focal_Point
 */
class Test_Focal_Point extends Galerio_TestCase {

	/**
	 * An image attachment.
	 *
	 * @return int
	 */
	private function image() {
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * A focal point is kept between 0 and 1; anything else is none.
	 */
	public function test_sanitize() {
		$this->assertSame(
			array(
				'x' => 1.0,
				'y' => 0.25,
			),
			Focal_Point::sanitize(
				array(
					'x' => 3,
					'y' => '0.25',
				)
			)
		);
		$this->assertNull( Focal_Point::sanitize( array( 'x' => 0.5 ) ) );
		$this->assertNull( Focal_Point::sanitize( '' ) );
	}

	/**
	 * Saving none clears it.
	 */
	public function test_save_and_clear() {
		$image = $this->image();

		Focal_Point::save(
			$image,
			array(
				'x' => 0.2,
				'y' => 0.8,
			)
		);
		$this->assertSame( '20% 80%', Focal_Point::css( Focal_Point::get( $image ) ) );

		Focal_Point::save( $image, null );
		$this->assertNull( Focal_Point::saved( $image ) );
		$this->assertSame( Focal_Point::CENTRE, Focal_Point::get( $image ) );
	}

	/**
	 * A centred image's markup is left as it was.
	 */
	public function test_centred_image_unchanged() {
		$html = wp_get_attachment_image( $this->image(), 'full' );

		$this->assertStringNotContainsString( 'object-position', $html );
	}

	/**
	 * An image with a focal point is placed by it, wherever it's shown.
	 */
	public function test_image_placed_by_its_focal_point() {
		$image = $this->image();
		Focal_Point::save(
			$image,
			array(
				'x' => 0.25,
				'y' => 0.1,
			)
		);

		$html = wp_get_attachment_image( $image, 'full', false, array( 'style' => 'opacity:1' ) );

		$this->assertStringContainsString( 'style="opacity:1;object-position:25% 10%;"', $html );
	}

	/**
	 * Markup that places the image itself, such as a Cover block with its
	 * own focal point, keeps its place.
	 */
	public function test_own_position_kept() {
		$image = $this->image();
		Focal_Point::save(
			$image,
			array(
				'x' => 0.25,
				'y' => 0.1,
			)
		);

		$html = wp_get_attachment_image( $image, 'full', false, array( 'style' => 'object-position:0% 0%;' ) );

		$this->assertStringContainsString( 'style="object-position:0% 0%;"', $html );
	}

	/**
	 * A profile's image without a focal point is kept a little above the
	 * middle; another item's is centred.
	 */
	public function test_portrait_default() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$work    = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image   = $this->image();
		set_post_thumbnail( $profile, $image );
		set_post_thumbnail( $work, $image );

		$this->assertStringContainsString( 'object-position:50% 30%;', get_the_post_thumbnail( $profile ) );
		$this->assertStringNotContainsString( 'object-position', get_the_post_thumbnail( $work ) );
		$this->assertStringNotContainsString( 'object-position', wp_get_attachment_image( $image ), 'Outside the item, the image is centred.' );
	}

	/**
	 * The default can be changed.
	 */
	public function test_default_filter() {
		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image = $this->image();
		set_post_thumbnail( $work, $image );

		$filter = function ( $point, $post_type ) {
			return 'galerio_work' === $post_type ? array(
				'x' => 0.5,
				'y' => 0,
			) : $point;
		};
		add_filter( 'galerio_focal_point_default', $filter, 10, 2 );

		$this->assertStringContainsString( 'object-position:50% 0%;', get_the_post_thumbnail( $work ) );

		remove_filter( 'galerio_focal_point_default', $filter, 10 );
	}

	/**
	 * A Cover block showing the featured image takes the image's focal
	 * point, unless it has its own.
	 */
	public function test_cover_takes_focal_point() {
		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image = $this->image();
		set_post_thumbnail( $work, $image );
		Focal_Point::save(
			$image,
			array(
				'x' => 0.7,
				'y' => 0.2,
			)
		);

		$this->go_to( get_permalink( $work ) );
		the_post();

		$cover = '<!-- wp:cover {"useFeaturedImage":true,"dimRatio":0,"isDark":false} --><div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph --></div></div><!-- /wp:cover -->';
		$this->assertStringContainsString( 'object-position:70% 20%', do_blocks( $cover ) );

		$own = str_replace( '"isDark":false', '"isDark":false,"focalPoint":{"x":0,"y":1}', $cover );
		$this->assertStringContainsString( 'object-position:0% 100%', do_blocks( $own ) );

		$fixed = str_replace( '"isDark":false', '"isDark":false,"hasParallax":true', $cover );
		$this->assertStringContainsString( 'background-position:70% 20%', do_blocks( $fixed ) );
	}
}
