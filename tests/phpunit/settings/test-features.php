<?php
/**
 * Feature switch tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Schema\Schema_Org;
use Galerio\Core\Settings\Features;

/**
 * The switches on Settings → Galerio turn features off site-wide.
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
	 * Birth/death fields read as empty while off, and keep their values.
	 */
	public function test_birth_death_fields() {
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		galerio_fields()->save( galerio_fields()->get( 'profile.year_of_birth' ), $profile, 1980 );
		galerio_fields()->save( galerio_fields()->get( 'profile.show_lifespan' ), $profile, true );

		$this->off( 'show_birth_death_fields' );
		$this->assertEmpty( galerio_get_field( 'profile.year_of_birth', $profile ) );
		$this->assertEmpty( galerio_get_field( 'profile.show_lifespan', $profile ) );
		$this->assertArrayNotHasKey( 'birthDate', Schema_Org::data( get_post( $profile ) ) );

		delete_option( Features::PREFIX . 'show_birth_death_fields' );
		$this->assertEquals( 1980, galerio_get_field( 'profile.year_of_birth', $profile ) );
	}

	/**
	 * Collective fields read as empty while off; other fields are unaffected.
	 */
	public function test_collective_fields() {
		$member  = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		galerio_fields()->save( galerio_fields()->get( 'profile.is_collective' ), $profile, true );
		galerio_fields()->save( galerio_fields()->get( 'profile.members' ), $profile, array( $member ) );
		galerio_fields()->save( galerio_fields()->get( 'profile.nationality' ), $profile, 'Welsh' );

		$this->off( 'show_collective_fields' );
		$this->assertEmpty( galerio_get_field( 'profile.is_collective', $profile ) );
		$this->assertEmpty( galerio_get_field( 'profile.members', $profile ) );
		$this->assertSame( 'Welsh', galerio_get_field( 'profile.nationality', $profile ) );
		$this->assertSame( 'Person', Schema_Org::data( get_post( $profile ) )['@type'] );
	}

	/**
	 * The editor's Pods panels leave out turned-off fields, and drop a panel
	 * left empty.
	 */
	public function test_editor_groups() {
		$groups = array(
			array(
				'name'   => 'details',
				'fields' => array(
					'year_of_birth' => array( 'name' => 'year_of_birth' ),
					'nationality'   => array( 'name' => 'nationality' ),
				),
			),
			array(
				'name'   => 'relationships',
				'fields' => array(
					'members'   => array( 'name' => 'members' ),
					'member_of' => array( 'name' => 'member_of' ),
				),
			),
		);

		$this->assertSame( $groups, Features::editor_groups( $groups, 'post_type', 'galerio_profile' ), 'Nothing hidden while on.' );

		$this->off( 'show_birth_death_fields' );
		$this->off( 'show_collective_fields' );
		$this->assertSame( $groups, Features::editor_groups( $groups, 'post_type', 'galerio_work' ), 'Other types are unaffected.' );

		$result = Features::editor_groups( $groups, 'post_type', 'galerio_profile' );
		$this->assertCount( 1, $result );
		$this->assertSame( array( 'nationality' ), array_keys( $result[0]['fields'] ) );
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
