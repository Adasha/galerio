<?php
/**
 * Content type permission tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Roles\Type_Capabilities;

/**
 * Galerio's content types have their own permissions.
 *
 * @covers \Galerio\Core\Roles\Type_Capabilities
 */
class Test_Type_Capabilities extends Galerio_TestCase {

	/**
	 * Each type's permissions are named after it, as PublishPress Capabilities names them.
	 */
	public function test_types_have_their_own_permissions() {
		$cap = get_post_type_object( 'galerio_profile' )->cap;

		$this->assertSame( 'edit_galerio_profiles', $cap->edit_posts );
		$this->assertSame( 'edit_published_galerio_profiles', $cap->edit_published_posts );
		$this->assertSame( 'publish_galerio_profiles', $cap->publish_posts );
		$this->assertSame( 'edit_galerio_profiles', $cap->create_posts );
		$this->assertSame( 'edit_galerio_collections', get_post_type_object( 'galerio_collection' )->cap->edit_posts );
	}

	/**
	 * Administrators and editors keep full access.
	 */
	public function test_administrators_and_editors_have_every_permission() {
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			$user = self::factory()->user->create( array( 'role' => $role ) );

			foreach ( Type_Capabilities::capabilities( 'galerio_work' ) as $cap ) {
				$this->assertTrue( user_can( $user, $cap ), "$role: $cap" );
			}
		}
	}

	/**
	 * A role can edit its own published profile without being able to publish,
	 * and without any access to pages.
	 */
	public function test_editing_without_publishing() {
		$role = add_role(
			'galerio_test_student',
			'Student',
			array(
				'read'                            => true,
				'edit_galerio_profiles'           => true,
				'edit_published_galerio_profiles' => true,
			)
		);

		try {
			$student = self::factory()->user->create( array( 'role' => 'galerio_test_student' ) );
			$profile = self::factory()->post->create(
				array(
					'post_type'   => 'galerio_profile',
					'post_author' => $student,
				)
			);
			$other   = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );

			$this->assertTrue( user_can( $student, 'edit_post', $profile ) );
			$this->assertFalse( user_can( $student, 'edit_post', $other ) );
			$this->assertFalse( user_can( $student, 'publish_galerio_profiles' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Galerio's own permission.
			$this->assertFalse( user_can( $student, 'edit_pages' ) );
		} finally {
			remove_role( $role->name );
		}
	}

	/**
	 * The grant runs once per version, so roles changed afterwards stay changed.
	 */
	public function test_grant_runs_once() {
		$editor = get_role( 'editor' );
		$editor->remove_cap( 'delete_others_galerio_works' );

		Type_Capabilities::grant();
		$this->assertFalse( get_role( 'editor' )->has_cap( 'delete_others_galerio_works' ) );

		delete_option( Type_Capabilities::VERSION_OPTION );
		Type_Capabilities::grant();
		$this->assertTrue( get_role( 'editor' )->has_cap( 'delete_others_galerio_works' ) );
	}
}
