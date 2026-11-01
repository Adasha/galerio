<?php
/**
 * Native content type tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Types\Content_Types;

/**
 * Galerio's own definitions match what the Pods package registers, so moving
 * to them changes nothing but the wording of a few labels.
 *
 * @covers \Galerio\Core\Types\Content_Types
 */
class Test_Content_Types extends Galerio_TestCase {

	/**
	 * Nothing differs from what the package registers.
	 */
	public function test_matches_the_package() {
		$this->assertSame( array(), Content_Types::differences() );
	}

	/**
	 * Sites stay on Pods until they move.
	 */
	public function test_not_active_by_default() {
		$this->assertFalse( Content_Types::active() );
	}

	/**
	 * Native definitions take the same aliases as the package's.
	 */
	public function test_aliases_apply() {
		update_option(
			Galerio\Core\Aliases\Aliases::OPTION,
			array(
				'types' => array(
					'work' => array(
						'singular' => 'Artwork',
						'plural'   => 'Artworks',
						'slug'     => 'artwork',
						'archive'  => 'artworks',
					),
				),
			)
		);

		$args = Content_Types::post_types()['galerio_work'];
		$type = new WP_Post_Type( 'galerio_work', $args );

		delete_option( Galerio\Core\Aliases\Aliases::OPTION );

		$this->assertSame( 'Artworks', $type->label );
		$this->assertSame( 'artwork', $type->rewrite['slug'] );
		$this->assertSame( 'artworks', $type->has_archive );
		$this->assertSame( 'Add New Artwork', $type->labels->add_new_item );
	}

	/**
	 * Every type and taxonomy's permissions are as Galerio sets them.
	 */
	public function test_type_capabilities_apply() {
		$type = new WP_Post_Type( 'galerio_profile', Content_Types::post_types()['galerio_profile'] );

		$this->assertSame( 'edit_galerio_profiles', $type->cap->edit_posts );
	}
}
