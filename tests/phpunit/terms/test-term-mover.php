<?php
/**
 * Term moving tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Terms\Term_Mover;

/**
 * A term moves with the terms under it, keeping its ID and its items.
 *
 * @covers \Galerio\Core\Terms\Term_Mover
 */
class Test_Term_Mover extends Galerio_TestCase {

	/**
	 * Group term IDs by slug: staff › academics, staff › fellows, alumni.
	 *
	 * @var int[]
	 */
	private $groups = array();

	/**
	 * Profile IDs: holly (academics), owain (fellows, alumni).
	 *
	 * @var int[]
	 */
	private $profiles = array();

	/**
	 * Make the groups and profiles.
	 */
	public function set_up() {
		parent::set_up();

		$staff        = self::factory()->term->create(
			array(
				'taxonomy' => 'galerio_group',
				'name'     => 'Staff',
				'slug'     => 'staff',
			)
		);
		$this->groups = array(
			'staff'     => $staff,
			'academics' => self::factory()->term->create(
				array(
					'taxonomy' => 'galerio_group',
					'name'     => 'Academics',
					'slug'     => 'academics',
					'parent'   => $staff,
				)
			),
			'fellows'   => self::factory()->term->create(
				array(
					'taxonomy' => 'galerio_group',
					'name'     => 'Fellows',
					'slug'     => 'fellows',
					'parent'   => $staff,
				)
			),
			'alumni'    => self::factory()->term->create(
				array(
					'taxonomy' => 'galerio_group',
					'name'     => 'Alumni',
					'slug'     => 'alumni',
				)
			),
		);

		$profile        = function ( $title, $groups ) {
			$id = self::factory()->post->create(
				array(
					'post_type'  => 'galerio_profile',
					'post_title' => $title,
				)
			);
			wp_set_object_terms( $id, $groups, 'galerio_group' );
			return $id;
		};
		$this->profiles = array(
			'holly' => $profile( 'Holly', array( $this->groups['staff'], $this->groups['academics'] ) ),
			'owain' => $profile( 'Owain', array( $this->groups['fellows'], $this->groups['alumni'] ) ),
		);
	}

	/**
	 * Slugs of a profile's terms in a taxonomy.
	 *
	 * @param int    $id       Profile ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string[]
	 */
	private function slugs( $id, $taxonomy ) {
		$slugs = wp_get_object_terms( $id, $taxonomy, array( 'fields' => 'slugs' ) );
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Moving to another taxonomy takes the terms under it, keeps IDs, parents
	 * and items, and leaves the other terms where they were.
	 */
	public function test_moves_a_branch_to_another_taxonomy() {
		$rows = Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_role' );

		$this->assertIsArray( $rows );
		$this->assertSame( array( 'Staff', 'Academics', 'Fellows' ), wp_list_pluck( $rows, 'name' ) );

		$academics = get_term( $this->groups['academics'] );
		$this->assertSame( 'galerio_role', $academics->taxonomy );
		$this->assertSame( $this->groups['staff'], (int) $academics->parent );
		$this->assertSame( 0, (int) get_term( $this->groups['staff'] )->parent );

		$this->assertSame( array( 'academics', 'staff' ), $this->slugs( $this->profiles['holly'], 'galerio_role' ) );
		$this->assertSame( array(), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );
		$this->assertSame( array( 'fellows' ), $this->slugs( $this->profiles['owain'], 'galerio_role' ) );
		$this->assertSame( array( 'alumni' ), $this->slugs( $this->profiles['owain'], 'galerio_group' ) );

		$this->assertSame(
			array( 'Alumni' ),
			get_terms(
				array(
					'taxonomy'   => 'galerio_group',
					'hide_empty' => false,
					'fields'     => 'names',
				)
			)
		);
		$this->assertNotSame( $rows[1]['old_url'], $rows[1]['new_url'] );
		$this->assertStringContainsString( 'academics', $rows[1]['new_url'] );
	}

	/**
	 * A term can leave its parent for the top level of its own taxonomy.
	 */
	public function test_moves_to_the_top_level() {
		Term_Mover::move( get_term( $this->groups['fellows'] ), 'galerio_group', 0 );

		$this->assertSame( 0, (int) get_term( $this->groups['fellows'] )->parent );
		$this->assertSame( array( 'alumni', 'fellows' ), $this->slugs( $this->profiles['owain'], 'galerio_group' ) );
	}

	/**
	 * A term can move under a parent in the taxonomy it moves to.
	 */
	public function test_moves_under_a_parent() {
		$people = self::factory()->term->create(
			array(
				'taxonomy' => 'galerio_role',
				'name'     => 'People',
				'slug'     => 'people',
			)
		);

		Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_role', $people );

		$this->assertSame( $people, (int) get_term( $this->groups['staff'] )->parent );
		$this->assertSame( $this->groups['staff'], (int) get_term( $this->groups['academics'] )->parent );
	}

	/**
	 * A dry run changes nothing.
	 */
	public function test_dry_run_changes_nothing() {
		$rows = Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_role', 0, true );

		$this->assertCount( 3, $rows );
		$this->assertSame( '', $rows[0]['new_url'] );
		$this->assertSame( 'galerio_group', get_term( $this->groups['academics'] )->taxonomy );
		$this->assertSame( array( 'academics', 'staff' ), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );
	}

	/**
	 * A slug already in the target taxonomy stops the move before anything changes.
	 */
	public function test_refuses_a_slug_clash() {
		self::factory()->term->create(
			array(
				'taxonomy' => 'galerio_role',
				'name'     => 'Fellows',
				'slug'     => 'fellows',
			)
		);

		$result = Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_role' );

		$this->assertWPError( $result );
		$this->assertSame( 'galerio_group', get_term( $this->groups['staff'] )->taxonomy );
	}

	/**
	 * Items of a type the target taxonomy isn't used for stop the move.
	 */
	public function test_refuses_items_the_taxonomy_does_not_cover() {
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		wp_set_object_terms( $work, array( $this->groups['academics'] ), 'galerio_group' );

		$this->assertWPError( Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_role' ) );
	}

	/**
	 * A term can't go under itself or one of its children.
	 */
	public function test_refuses_a_loop() {
		$this->assertWPError( Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_group', $this->groups['academics'] ) );
	}
}
