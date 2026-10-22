<?php
/**
 * Featured image fallback tests.
 *
 * @package Galerio\Tests
 */

/**
 * A work's featured image falls back to its definitive image.
 *
 * @covers \Galerio\Core\Fields\Featured_Image
 */
class Test_Featured_Image extends Galerio_TestCase {

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
	 * A work without a featured image uses its definitive image.
	 */
	public function test_falls_back_to_definitive_image() {
		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $image );

		$this->assertSame( $image, get_post_thumbnail_id( $work ) );
		$this->assertTrue( has_post_thumbnail( $work ) );
	}

	/**
	 * A featured image set by hand wins.
	 */
	public function test_featured_image_wins() {
		$work     = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$featured = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $this->image() );
		set_post_thumbnail( $work, $featured );

		$this->assertSame( $featured, get_post_thumbnail_id( $work ) );
	}

	/**
	 * Works without either image, and other post types, are unaffected.
	 */
	public function test_no_fallback_elsewhere() {
		$work    = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		galerio_fields()->save( galerio_fields()->get( 'profile.portrait' ), $profile, $this->image() );

		$this->assertFalse( has_post_thumbnail( $work ) );
		$this->assertFalse( has_post_thumbnail( $profile ), 'A portrait is not a featured image.' );
	}
}
