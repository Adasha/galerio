<?php
/**
 * Native field editor tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Field_Editor;

/**
 * What the editor is given to draw.
 *
 * @covers \Galerio\Core\Fields\Field_Editor
 */
class Test_Field_Editor extends Galerio_TestCase {

	/**
	 * The names of the fields a user sees on an object.
	 *
	 * @param string $object_name Object name.
	 * @param string $role        User's role.
	 * @return string[]
	 */
	private function visible( $object_name, $role ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

		$names = array();

		foreach ( Field_Editor::groups( $object_name ) as $group ) {
			$names = array_merge( $names, wp_list_pluck( $group['fields'], 'name' ) );
		}

		return $names;
	}

	/**
	 * Fields are in the groups' order, and worked-out fields aren't editable.
	 */
	public function test_groups() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$groups = Field_Editor::groups( 'work' );

		$this->assertSame( array( 'details', 'metadata', 'relationships', 'cataloging' ), wp_list_pluck( $groups, 'name' ) );
		$this->assertSame( 'definitive_title', $groups[0]['fields'][0]['name'] );
		$this->assertNotContains( 'date_label', $this->visible( 'work', 'administrator' ) );
	}

	/**
	 * A student doesn't see what's limited to other roles, and an editor does.
	 */
	public function test_roles() {
		$student = $this->visible( 'profile', 'contributor' );

		$this->assertContains( 'bio', $student );
		$this->assertNotContains( 'year_of_birth', $student );
		$this->assertNotContains( 'collections', $student );

		$this->assertContains( 'year_of_birth', $this->visible( 'profile', 'editor' ) );
		$this->assertNotContains( 'valuation', $this->visible( 'work', 'author' ) );
		$this->assertContains( 'valuation', $this->visible( 'work', 'editor' ) );
	}

	/**
	 * Unpublished items can be picked only by people who can edit that type.
	 */
	public function test_relationship_statuses() {
		$statuses = function ( $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );

			foreach ( Field_Editor::groups( 'work' )[0]['fields'] as $field ) {
				if ( 'creator' === $field['name'] ) {
					return $field['statuses'];
				}
			}
		};

		$this->assertContains( 'draft', $statuses( 'editor' ) );
		$this->assertSame( array( 'publish' ), $statuses( 'subscriber' ) );
	}

	/**
	 * Fields of a unit are next to each other in one group, so they stay together.
	 */
	public function test_units_stay_together() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'galerio_settings_catalogue', '1' );

		foreach ( array( 'work', 'profile', 'collection', 'article' ) as $object_name ) {
			$seen = array();

			foreach ( Field_Editor::groups( $object_name ) as $group ) {
				$previous = '';

				foreach ( $group['fields'] as $field ) {
					if ( $field['unit'] && $field['unit'] !== $previous ) {
						$this->assertNotContains( $field['unit'], $seen, "$object_name: {$field['name']}" );
						$seen[] = $field['unit'];
					}

					$previous = $field['unit'];
				}
			}
		}

		delete_option( 'galerio_settings_catalogue' );

		$names = wp_list_pluck( Field_Editor::groups( 'profile' )[0]['fields'], 'name' );

		$this->assertSame( array_search( 'nationality', $names, true ) + 1, array_search( 'show_nationality', $names, true ) );
	}
}
