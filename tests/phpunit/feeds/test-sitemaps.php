<?php
/**
 * Sitemap tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Feeds\Sitemaps;

/**
 * Sites can leave content types, taxonomies and users out of the sitemap.
 *
 * @covers \Galerio\Core\Feeds\Sitemaps
 */
class Test_Sitemaps extends Galerio_TestCase {

	/**
	 * Leave the setting as it was.
	 */
	public function tear_down() {
		delete_option( Sitemaps::OPTION );
		parent::tear_down();
	}

	/**
	 * What's chosen is left out; the rest stays.
	 */
	public function test_exclusions() {
		update_option( Sitemaps::OPTION, array( 'post:galerio_profile', 'taxonomy:galerio_role', 'users' ) );

		$types = Sitemaps::post_types(
			array(
				'galerio_profile' => 1,
				'galerio_work'    => 1,
			)
		);
		$this->assertSame( array( 'galerio_work' ), array_keys( $types ) );

		$taxonomies = Sitemaps::taxonomies(
			array(
				'galerio_role'  => 1,
				'galerio_group' => 1,
			)
		);
		$this->assertSame( array( 'galerio_group' ), array_keys( $taxonomies ) );

		$this->assertFalse( Sitemaps::provider( 'provider', 'users' ) );
		$this->assertSame( 'provider', Sitemaps::provider( 'provider', 'posts' ) );
	}

	/**
	 * Nothing is left out until chosen.
	 */
	public function test_nothing_by_default() {
		$this->assertSame( array( 'galerio_profile' => 1 ), Sitemaps::post_types( array( 'galerio_profile' => 1 ) ) );
		$this->assertSame( 'provider', Sitemaps::provider( 'provider', 'users' ) );
	}

	/**
	 * Only things the sitemap can list are kept, under the site's names.
	 */
	public function test_sanitize_and_choices() {
		$this->assertSame( array( 'users', 'post:galerio_work' ), Sitemaps::sanitize( array( 'users', 'post:galerio_work', 'post:nope', '' ) ) );

		$choices = Sitemaps::choices();
		$this->assertSame( get_post_type_object( 'galerio_work' )->labels->name, $choices['post:galerio_work'] );
		$this->assertArrayHasKey( 'taxonomy:galerio_group', $choices );
		$this->assertArrayNotHasKey( 'post:attachment', $choices );
	}
}
