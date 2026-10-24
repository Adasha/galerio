<?php
/**
 * FileBird import tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Media\FileBird_Import;
use Galerio\Media\Folders;
use Galerio\Media\Settings;

/**
 * Copying FileBird's folders and files into Galerio's folders.
 *
 * @covers \Galerio\Media\FileBird_Import
 */
class Test_FileBird_Import extends Galerio_TestCase {

	/**
	 * Someone with their own FileBird folders.
	 *
	 * @var int
	 */
	private $person;

	/**
	 * Files, by name.
	 *
	 * @var int[]
	 */
	private $files = array();

	/**
	 * Make FileBird's tables and fill them like swansea.art's.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();

		$this->person = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array( 'visions', 'posts' ) as $name ) {
			$this->files[ $name ] = self::factory()->attachment->create_object( "$name.jpg", 0, array( 'post_mime_type' => 'image/jpeg' ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Test fixtures.
		$wpdb->query( "CREATE TABLE {$wpdb->prefix}fbv (id int, name varchar(250), parent int, type int, ord int, created_by int)" );
		$wpdb->query( "CREATE TABLE {$wpdb->prefix}fbv_attachment_folder (folder_id int, attachment_id bigint)" );

		$folders = array(
			array( 1, 'posts', 0, 0, 0 ),
			array( 2, 'exhibitions', 0, 1, 0 ),
			array( 3, 'visions', 2, 0, 0 ),
			array( 4, 'posts', 0, 0, $this->person ),
			array( 5, 'drafts', 4, 0, $this->person ),
			array( 6, 'old', 0, 2, 999999 ),
		);
		foreach ( $folders as $f ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}fbv VALUES (%d, %s, %d, 0, %d, %d)", $f[0], $f[1], $f[2], $f[3], $f[4] ) );
		}

		foreach ( array(
			array( 3, $this->files['visions'] ),
			array( 1, $this->files['posts'] ),
			array( 4, $this->files['posts'] ),
			array( 1, 987654 ),
		) as $link ) {
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->prefix}fbv_attachment_folder VALUES (%d, %d)", $link[0], $link[1] ) );
		}
		// phpcs:enable
	}

	/**
	 * Drop FileBird's tables.
	 */
	public function tear_down() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Test fixtures.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}fbv, {$wpdb->prefix}fbv_attachment_folder" );
		parent::tear_down();
	}

	/**
	 * The Galerio folder imported from a FileBird folder.
	 *
	 * @param int $id FileBird folder ID.
	 * @return WP_Term|null
	 */
	private function imported( $id ) {
		$terms = get_terms(
			array(
				'taxonomy'    => Folders::TAXONOMY,
				'hide_empty'  => false,
				'galerio_all' => true,
				'meta_key'    => FileBird_Import::SOURCE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Test lookup.
				'meta_value'  => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Test lookup.
			)
		);

		return $terms ? $terms[0] : null;
	}

	/**
	 * Folders keep their tree, names, order and owners; files are filed.
	 */
	public function test_import() {
		$this->assertTrue( FileBird_Import::available() );

		$report = FileBird_Import::run();

		$this->assertSame( 6, $report['folders'] );
		$this->assertSame( 2, $report['files'] );
		$this->assertSame( 1, $report['missing'] );
		$this->assertSame( 1, $report['people'] );

		$visions = $this->imported( 3 );
		$this->assertSame( $this->imported( 2 )->term_id, $visions->parent );
		$this->assertSame( 0, Folders::owner( $visions->term_id ) );
		$this->assertSame( 1, (int) get_term_meta( $this->imported( 2 )->term_id, Folders::ORDER, true ) );

		$own = $this->imported( 4 );
		$this->assertSame( 'posts', $own->name, 'A personal folder keeps a shared folder’s name.' );
		$this->assertSame( $this->person, Folders::owner( $own->term_id ) );
		$this->assertSame( Folders::user_root( $this->person ), $own->parent );
		$this->assertSame( $this->person, Folders::owner( $this->imported( 5 )->term_id ) );

		$this->assertSame( 0, Folders::owner( $this->imported( 6 )->term_id ), 'Folders of people who have left become shared.' );

		$in = wp_get_object_terms(
			$this->files['posts'],
			Folders::TAXONOMY,
			array(
				'fields'      => 'ids',
				'galerio_all' => true,
			)
		);
		sort( $in );
		$expected = array( $this->imported( 1 )->term_id, $own->term_id );
		sort( $expected );
		$this->assertSame( $expected, $in, 'A file keeps both its shared and its personal folder.' );

		$this->assertTrue( Settings::personal_folders(), 'Personal folders are switched on.' );
	}

	/**
	 * A second run reuses everything.
	 */
	public function test_import_again() {
		FileBird_Import::run();
		$report = FileBird_Import::run();

		$this->assertSame( 0, $report['folders'] );
		$this->assertSame( 6, $report['reused'] );
		$this->assertCount( 1, wp_get_object_terms( $this->files['visions'], Folders::TAXONOMY ) );
	}

	/**
	 * A dry run counts without changing anything.
	 */
	public function test_dry_run() {
		update_option( Settings::PERSONAL_FOLDERS, false );
		$report = FileBird_Import::run( true );

		$this->assertSame( 6, $report['folders'] );
		$this->assertSame( 2, $report['files'] );
		$this->assertNull( $this->imported( 1 ) );
		$this->assertFalse( Settings::personal_folders() );
	}
}
