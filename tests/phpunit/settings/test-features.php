<?php
/**
 * Display switch tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Featured_Image;
use Galerio\Core\Schema\Schema_Org;
use Galerio\Core\Settings\Features;

/**
 * The switches on Settings → Galerio keep things off the site's pages, while
 * the fields themselves stay usable.
 *
 * @covers \Galerio\Core\Settings\Features
 */
class Test_Features extends Galerio_TestCase {

	/**
	 * Turn a switch off.
	 *
	 * @param string $name Switch.
	 */
	private function off( $name ) {
		update_option( Features::PREFIX . $name, '0' );
	}

	/**
	 * Save a profile field.
	 *
	 * @param int    $profile Profile.
	 * @param string $name    Field name.
	 * @param mixed  $value   Value.
	 */
	private function save( $profile, $name, $value ) {
		galerio_fields()->save( galerio_fields()->get( 'profile.' . $name ), $profile, $value );
	}

	/**
	 * A switch never saved counts as on; a saved 0 turns it off.
	 */
	public function test_enabled() {
		$this->assertTrue( Features::enabled( 'show_featured_images' ) );

		$this->off( 'show_featured_images' );
		$this->assertFalse( Features::enabled( 'show_featured_images' ) );

		update_option( Features::PREFIX . 'show_featured_images', '1' );
		$this->assertTrue( Features::enabled( 'show_featured_images' ) );
	}

	/**
	 * Nationality and lifespan: each profile's own switch reads as off, and
	 * the values themselves stay readable.
	 */
	public function test_profile_switches() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$this->save( $profile, 'nationality', 'Welsh' );
		$this->save( $profile, 'show_nationality', true );
		$this->save( $profile, 'year_of_birth', 1980 );
		$this->save( $profile, 'show_lifespan', true );

		$this->off( 'show_nationality' );
		$this->off( 'show_birth_death_fields' );

		$this->assertFalse( galerio_get_field( 'profile.show_nationality', $profile ) );
		$this->assertFalse( galerio_get_field( 'profile.show_lifespan', $profile ) );
		$this->assertSame( 'Welsh', galerio_get_field( 'profile.nationality', $profile ), 'The field can still be used directly.' );
		$this->assertEquals( 1980, galerio_get_field( 'profile.year_of_birth', $profile ) );

		$data = Schema_Org::data( get_post( $profile ) );
		$this->assertArrayNotHasKey( 'nationality', $data );
		$this->assertArrayNotHasKey( 'birthDate', $data );

		delete_option( Features::PREFIX . 'show_nationality' );
		$this->assertTrue( (bool) galerio_get_field( 'profile.show_nationality', $profile ), 'The profile’s own choice comes back.' );
	}

	/**
	 * Portraits: a profile's own page leaves its portrait out; cards
	 * elsewhere keep it.
	 */
	public function test_portraits() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$image   = self::factory()->attachment->create_object(
			array(
				'file'           => 'portrait.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->save( $profile, 'portrait', $image );
		$this->save( $profile, 'show_portrait', true );
		update_post_meta( $profile, Featured_Image::USE, true );
		$this->assertSame( $image, get_post_thumbnail_id( $profile ) );

		$this->off( 'show_portraits' );
		$this->assertSame( $image, get_post_thumbnail_id( $profile ), 'Cards elsewhere keep it.' );

		$this->go_to( get_permalink( $profile ) );
		$this->assertFalse( has_post_thumbnail( $profile ), 'Its own page leaves it out.' );
		$this->assertSame( $image, galerio_get_field( 'profile.portrait', $profile ), 'The field can still be used directly.' );

		$other = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$this->save( $other, 'portrait', $image );
		$this->save( $other, 'show_portrait', true );
		update_post_meta( $other, Featured_Image::USE, true );
		$this->assertSame( $image, get_post_thumbnail_id( $other ), 'Another profile’s card on this page keeps it.' );
	}

	/**
	 * Collective fields: profile pages leave out blocks showing them; other
	 * pages and other fields are unaffected.
	 */
	public function test_collective_fields_on_profile_pages() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$members = array(
			'blockName' => 'galerio/field-display',
			'attrs'     => array( 'key' => 'profile.members' ),
		);
		$bound   = array(
			'blockName' => 'core/paragraph',
			'attrs'     => array(
				'metadata' => array(
					'bindings' => array(
						'content' => array(
							'source' => 'galerio/field',
							'args'   => array( 'key' => 'member_of' ),
						),
					),
				),
			),
		);
		$bio     = array(
			'blockName' => 'galerio/field-display',
			'attrs'     => array( 'key' => 'profile.bio' ),
		);

		$this->go_to( get_permalink( $profile ) );
		$this->assertSame( 'x', Features::profile_page_block( 'x', $members ), 'Shown while on.' );

		$this->off( 'show_collective_fields' );
		$this->assertSame( '', Features::profile_page_block( 'x', $members ) );
		$this->assertSame( '', Features::profile_page_block( 'x', $bound ), 'A bare field name counts too.' );
		$this->assertSame( 'x', Features::profile_page_block( 'x', $bio ) );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( get_permalink( $page ) );
		$this->assertSame( 'x', Features::profile_page_block( 'x', $members ), 'Other pages can still show them.' );
	}

	/**
	 * While off, an item's own page leaves out its featured image; other
	 * items' images, as in cards, stay.
	 */
	public function test_featured_images() {
		$article = self::factory()->post->create( array( 'post_type' => 'galerio_article' ) );
		$other   = self::factory()->post->create( array( 'post_type' => 'galerio_article' ) );
		$this->go_to( get_permalink( $article ) );

		$banner = '<div class="wp-block-cover"><img class="wp-block-cover__image-background" src="x.jpg" alt=""/><div class="wp-block-cover__inner-container"><p></p></div></div>';
		$titled = '<div class="wp-block-cover"><img class="wp-block-cover__image-background" src="x.jpg" alt=""/><div class="wp-block-cover__inner-container"><h1>Title</h1></div></div>';
		$cover  = array( 'attrs' => array( 'useFeaturedImage' => true ) );
		$own    = new WP_Block( array( 'blockName' => 'core/cover' ), array( 'postId' => $article ) );
		$card   = new WP_Block( array( 'blockName' => 'core/cover' ), array( 'postId' => $other ) );

		$this->assertSame( $banner, Features::cover( $banner, $cover, $own ), 'Shown while on.' );

		$this->off( 'show_featured_images' );
		$this->assertSame( '', Features::cover( $banner, $cover, $own ), 'A banner with nothing else goes.' );

		$kept = Features::cover( $titled, $cover, $own );
		$this->assertStringNotContainsString( '<img', $kept, 'A cover with content loses only its image.' );
		$this->assertStringContainsString( 'Title', $kept );
		$this->assertStringContainsString( 'galerio-cover-no-image', $kept );

		$this->assertSame( $banner, Features::cover( $banner, $cover, $card ), 'Another item’s cover keeps its image.' );
		$this->assertSame( '', Features::featured_image( '<figure>…</figure>', array(), $own ) );
		$this->assertSame( '<figure>…</figure>', Features::featured_image( '<figure>…</figure>', array(), $card ) );
	}
}
