<?php
/**
 * Pods package export and comparison tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Pods\Package;

/**
 * Package::canonical(), Package::diff() and exports from a site.
 *
 * @covers \Galerio\Core\Pods\Package
 */
class Test_Package extends Galerio_TestCase {

	/**
	 * Path to the repository's package.
	 *
	 * @return string
	 */
	private function file() {
		return dirname( __DIR__, 2 ) . '/data/pods/galerio.json';
	}

	/**
	 * A small package: two pods related to each other, and a template.
	 *
	 * @param int $offset Added to every ID, to imitate another site's IDs.
	 * @return array
	 */
	private function package( $offset = 0 ) {
		return array(
			'@meta'     => array(
				'version' => '3.3.9',
				'build'   => 1700000000 + $offset,
			),
			'pods'      => array(
				array(
					'id'     => 10 + $offset,
					'name'   => 'work',
					'label'  => 'Works',
					'groups' => array(
						array(
							'id'     => 11 + $offset,
							'name'   => 'details',
							'fields' => array(
								array(
									'id'        => 12 + $offset,
									'name'      => 'creator',
									'type'      => 'pick',
									'pick_val'  => 'profile',
									'sister_id' => (string) ( 22 + $offset ),
								),
							),
						),
					),
				),
				array(
					'id'           => 20 + $offset,
					'name'         => 'profile',
					'label'        => 'Profiles',
					'_migrated_28' => (string) ( $offset % 2 ),
					'groups'       => array(
						array(
							'id'     => 21 + $offset,
							'name'   => 'details',
							'fields' => array(
								array(
									'id'        => 22 + $offset,
									'name'      => 'works',
									'type'      => 'pick',
									'pick_val'  => 'work',
									'sister_id' => (string) ( 12 + $offset ),
								),
							),
						),
					),
				),
			),
			'templates' => array(
				array(
					'id'         => 30 + $offset,
					'name'       => 'Work box',
					'code'       => "<div>\r\n{@post_title}\r\n</div>",
					'_edit_lock' => (string) $offset,
				),
			),
		);
	}

	/**
	 * Canonical packages from different sites are identical.
	 */
	public function test_canonical_ignores_site_values() {
		$this->assertSame( Package::canonical( $this->package() ), Package::canonical( $this->package( 500 ) ) );
	}

	/**
	 * Canonical packages are sorted, renumbered and still internally linked.
	 */
	public function test_canonical_renumbers_consistently() {
		$canonical = Package::canonical( $this->package( 500 ) );

		$this->assertSame( array( 'version' => '3.3.9' ), $canonical['@meta'] );
		$this->assertSame( array( 'profile', 'work' ), wp_list_pluck( $canonical['pods'], 'name' ) );

		$works   = $canonical['pods'][0]['groups'][0]['fields'][0];
		$creator = $canonical['pods'][1]['groups'][0]['fields'][0];

		$this->assertSame( 1, $canonical['pods'][0]['id'] );
		$this->assertSame( (string) $creator['id'], $works['sister_id'] );
		$this->assertSame( (string) $works['id'], $creator['sister_id'] );
		$this->assertArrayNotHasKey( '_migrated_28', $canonical['pods'][0] );
		$this->assertArrayNotHasKey( '_edit_lock', $canonical['templates'][0] );
	}

	/**
	 * Canonicalising twice changes nothing.
	 */
	public function test_canonical_is_idempotent() {
		$once = Package::canonical( Package::from_file( $this->file() ) );

		$this->assertSame( $once, Package::canonical( $once ) );
	}

	/**
	 * The repository's package means the same once canonical.
	 */
	public function test_canonical_preserves_meaning() {
		$package = Package::from_file( $this->file() );

		$this->assertSame( array(), Package::diff( $package, Package::canonical( $package ) ) );
	}

	/**
	 * Packages that differ only in IDs and site values have no differences.
	 */
	public function test_diff_ignores_ids_and_site_values() {
		$this->assertSame( array(), Package::diff( $this->package(), $this->package( 500 ) ) );
	}

	/**
	 * Changed, added and removed settings are reported.
	 */
	public function test_diff_reports_setting_changes() {
		$to = $this->package();

		$to['pods'][0]['label']                         = 'Artworks';
		$to['pods'][0]['groups'][0]['fields'][0]['req'] = '1';
		unset( $to['pods'][1]['label'] );

		$this->assertEqualSets(
			array(
				array(
					'change'  => 'changed',
					'item'    => 'pod work',
					'setting' => 'label',
					'from'    => 'Works',
					'to'      => 'Artworks',
				),
				array(
					'change'  => 'added',
					'item'    => 'field work.creator',
					'setting' => 'req',
					'from'    => null,
					'to'      => '1',
				),
				array(
					'change'  => 'removed',
					'item'    => 'pod profile',
					'setting' => 'label',
					'from'    => 'Profiles',
					'to'      => null,
				),
			),
			Package::diff( $this->package(), $to )
		);
	}

	/**
	 * Added and removed items are reported once, not setting by setting.
	 */
	public function test_diff_reports_added_and_removed_items() {
		$to = $this->package();

		$to['templates'][] = array(
			'name' => 'Profile box',
			'code' => '',
		);
		unset( $to['pods'][0]['groups'][0]['fields'][0] );

		$changes = Package::diff( $this->package(), $to );

		$this->assertEqualSets(
			array( 'removed field work.creator', 'added template Profile box', 'changed field profile.works' ),
			array_map(
				function ( $change ) {
					return $change['change'] . ' ' . $change['item'];
				},
				$changes
			)
		);
	}

	/**
	 * Relationships are compared by the field they pair with.
	 */
	public function test_diff_compares_sisters_by_name() {
		$to = $this->package();
		$to['pods'][1]['groups'][0]['fields'][0]['sister_id'] = '';

		$changes = Package::diff( $this->package(), $to );

		$this->assertCount( 1, $changes );
		$this->assertSame( 'field profile.works', $changes[0]['item'] );
		$this->assertSame( 'sister', $changes[0]['setting'] );
		$this->assertSame( 'work.creator', $changes[0]['from'] );
	}

	/**
	 * Missing settings match empty ones, scalars compare as strings, and
	 * template line endings are ignored.
	 */
	public function test_diff_tolerates_equivalent_values() {
		$to = $this->package();

		$to['pods'][0]['description'] = '';
		$to['pods'][0]['id']          = '10';
		$to['templates'][0]['code']   = "<div>\n{@post_title}\n</div>";

		$this->assertSame( array(), Package::diff( $this->package(), $to ) );
	}

	/**
	 * A site that imported the package exports the same configuration.
	 */
	public function test_site_matches_imported_package() {
		$site = Package::from_site();

		$this->assertIsArray( $site );

		$changes = array_filter(
			Package::diff( Package::from_file( $this->file() ), $site ),
			function ( $change ) {
				// Pods versions add settings with their own defaults.
				return 'settings' !== $change['item'];
			}
		);

		$this->assertSame( array(), array_values( $changes ) );
	}

	/**
	 * Exports can be limited to some pods.
	 */
	public function test_export_selected_pods() {
		$package = Package::from_site( array( 'galerio_work', 'galerio_profile' ) );

		$this->assertEqualSets( array( 'galerio_work', 'galerio_profile' ), wp_list_pluck( $package['pods'], 'name' ) );
		$this->assertWPError( Package::from_site( array( 'no_such_pod' ) ) );
	}
}
