<?php
/**
 * Featured image fallback tests.
 *
 * @package Galerio\Tests
 */

/**
 * An item without a featured image uses one of its own images.
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
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse( has_post_thumbnail( $work ) );
		$this->assertFalse( has_post_thumbnail( $page ) );
	}

	/**
	 * A profile uses its portrait only when someone turns that on, and only
	 * while the profile shows its portrait.
	 */
	public function test_profile_portrait() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$image   = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'profile.portrait' ), $profile, $image );
		galerio_fields()->save( galerio_fields()->get( 'profile.show_portrait' ), $profile, true );

		$this->assertFalse( has_post_thumbnail( $profile ), 'Off by default for profiles.' );

		update_post_meta( $profile, Galerio\Core\Fields\Featured_Image::USE, true );
		$this->assertSame( $image, get_post_thumbnail_id( $profile ) );

		galerio_fields()->save( galerio_fields()->get( 'profile.show_portrait' ), $profile, false );
		$this->assertFalse( has_post_thumbnail( $profile ), 'A hidden portrait isn’t shown as an image either.' );
	}

	/**
	 * A collection uses its first image.
	 */
	public function test_collection_first_image() {
		$collection = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		$first      = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'collection.images' ), $collection, array( $first, $this->image() ) );

		$this->assertSame( $first, get_post_thumbnail_id( $collection ) );
	}

	/**
	 * Each item can turn the fallback off.
	 */
	public function test_turned_off() {
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $this->image() );
		update_post_meta( $work, Galerio\Core\Fields\Featured_Image::USE, false );

		$this->assertFalse( has_post_thumbnail( $work ) );
	}

	/**
	 * The REST API reports only a featured image that was actually set, so
	 * the editor isn't shown the fallback as if it were.
	 */
	public function test_rest_reports_the_real_featured_image() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$work = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $this->image() );

		$response = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/galerio_work/$work" ) );

		// Don't leave this REST server for later tests: it caches routes.
		$GLOBALS['wp_rest_server'] = null;

		$this->assertSame( 0, $response->get_data()['featured_media'] );
		$this->assertTrue( has_post_thumbnail( $work ), 'The site still uses the fallback.' );
	}
}
