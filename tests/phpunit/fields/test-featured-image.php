<?php
/**
 * Featured image tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Featured_Image;

/**
 * Which image stands for an item: its featured image or its own.
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
	 * With both, the featured image is used until the work chooses its
	 * definitive image instead.
	 */
	public function test_both_featured_by_default_then_chosen() {
		$work       = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$featured   = $this->image();
		$definitive = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $definitive );
		set_post_thumbnail( $work, $featured );

		$this->assertSame( $featured, get_post_thumbnail_id( $work ) );

		update_post_meta( $work, Featured_Image::USE, true );
		$this->assertSame( $definitive, get_post_thumbnail_id( $work ) );
		$this->assertSame( $featured, Featured_Image::stored( $work ), 'The stored featured image is kept.' );
	}

	/**
	 * With only a featured image, it's used, whatever the setting says.
	 */
	public function test_only_featured() {
		$work     = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$featured = $this->image();
		set_post_thumbnail( $work, $featured );
		update_post_meta( $work, Featured_Image::USE, true );

		$this->assertSame( $featured, get_post_thumbnail_id( $work ) );
	}

	/**
	 * A featured image the same as the definitive image is saved as usual.
	 */
	public function test_saving_the_same_image() {
		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $image );

		$this->assertTrue( (bool) set_post_thumbnail( $work, $image ) );
		$this->assertSame( $image, Featured_Image::stored( $work ) );
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

		update_post_meta( $profile, Featured_Image::USE, true );
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
	 * A profile with both images uses its portrait only when it chooses to.
	 */
	public function test_profile_with_both() {
		$profile  = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$featured = $this->image();
		$portrait = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'profile.portrait' ), $profile, $portrait );
		galerio_fields()->save( galerio_fields()->get( 'profile.show_portrait' ), $profile, true );
		set_post_thumbnail( $profile, $featured );

		$this->assertSame( $featured, get_post_thumbnail_id( $profile ) );

		update_post_meta( $profile, Featured_Image::USE, true );
		$this->assertSame( $portrait, get_post_thumbnail_id( $profile ) );
	}

	/**
	 * A profile that turned its portrait on under the old setting keeps it
	 * while it has no featured image. The old setting's "off" for works no
	 * longer hides their definitive image.
	 */
	public function test_old_setting() {
		$profile  = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$portrait = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'profile.portrait' ), $profile, $portrait );
		galerio_fields()->save( galerio_fields()->get( 'profile.show_portrait' ), $profile, true );
		update_post_meta( $profile, Featured_Image::LEGACY, true );

		$this->assertSame( $portrait, get_post_thumbnail_id( $profile ) );

		$featured = $this->image();
		set_post_thumbnail( $profile, $featured );
		$this->assertSame( $featured, get_post_thumbnail_id( $profile ), 'The old setting applied only without a featured image.' );

		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$image = $this->image();
		galerio_fields()->save( galerio_fields()->get( 'work.definitive_image' ), $work, $image );
		update_post_meta( $work, Featured_Image::LEGACY, false );
		$this->assertSame( $image, get_post_thumbnail_id( $work ) );
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
