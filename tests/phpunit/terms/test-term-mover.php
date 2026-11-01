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

	/**
	 * A term with terms under it can't go to a taxonomy without parents.
	 */
	public function test_refuses_a_branch_into_a_flat_taxonomy() {
		$this->assertWPError( Term_Mover::move( get_term( $this->groups['staff'] ), 'galerio_label' ) );

		Term_Mover::move( get_term( $this->groups['fellows'] ), 'galerio_label' );
		$this->assertSame( 'galerio_label', get_term( $this->groups['fellows'] )->taxonomy );
		$this->assertSame( 0, (int) get_term( $this->groups['fellows'] )->parent );
	}

	/**
	 * A merge gives the term's items and children to the other term and
	 * deletes it.
	 */
	public function test_merges_into_another_term() {
		$visiting = self::factory()->term->create(
			array(
				'taxonomy' => 'galerio_group',
				'name'     => 'Visiting',
				'slug'     => 'visiting',
				'parent'   => $this->groups['academics'],
			)
		);

		$rows = Term_Mover::merge( get_term( $this->groups['academics'] ), get_term( $this->groups['alumni'] ) );

		$this->assertSame( 1, $rows[0]['items'] );
		$this->assertSame( 'Visiting', $rows[0]['children'] );
		$this->assertNull( get_term( $this->groups['academics'] ) );
		$this->assertSame( $this->groups['alumni'], (int) get_term( $visiting )->parent );
		$this->assertSame( array( 'alumni', 'staff' ), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );
	}

	/**
	 * A dry run of a merge changes nothing, and a term can't merge into one
	 * under it or into another taxonomy.
	 */
	public function test_merge_dry_run_and_refusals() {
		Term_Mover::merge( get_term( $this->groups['academics'] ), get_term( $this->groups['alumni'] ), true );
		$this->assertSame( array( 'academics', 'staff' ), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );

		$this->assertWPError( Term_Mover::merge( get_term( $this->groups['staff'] ), get_term( $this->groups['academics'] ) ) );

		$role = self::factory()->term->create(
			array(
				'taxonomy' => 'galerio_role',
				'name'     => 'Tutor',
			)
		);
		$this->assertWPError( Term_Mover::merge( get_term( $this->groups['alumni'] ), get_term( $role ) ) );
	}

	/**
	 * A tidy drops a tag when the item has a term under it, and leaves items
	 * without one alone.
	 */
	public function test_tidies_redundant_parents() {
		$only_staff = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		wp_set_object_terms( $only_staff, array( $this->groups['staff'] ), 'galerio_group' );

		$dry = Term_Mover::tidy( 'galerio_group', true );
		$this->assertCount( 1, $dry );
		$this->assertSame( 'Staff', $dry[0]['removed'] );
		$this->assertSame( 'Academics', $dry[0]['kept'] );
		$this->assertSame( array( 'academics', 'staff' ), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );

		Term_Mover::tidy( 'galerio_group' );
		$this->assertSame( array( 'academics' ), $this->slugs( $this->profiles['holly'], 'galerio_group' ) );
		$this->assertSame( array( 'alumni', 'fellows' ), $this->slugs( $this->profiles['owain'], 'galerio_group' ) );
		$this->assertSame( array( 'staff' ), $this->slugs( $only_staff, 'galerio_group' ) );
	}
}
