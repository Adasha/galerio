<?php
/**
 * Folder directory tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Media\Folder_Directories;
use Galerio\Media\Folders;
use Galerio\Media\Settings;

/**
 * New uploads saved into their shared folder's directory.
 *
 * @covers \Galerio\Media\Folder_Directories
 */
class Test_Folder_Directories extends Galerio_TestCase {

	/**
	 * The collections › visions folder.
	 *
	 * @var int
	 */
	private $visions;

	/**
	 * Saved files, removed afterwards.
	 *
	 * @var string[]
	 */
	private $saved = array();

	/**
	 * Create a nested shared folder, as an editor, with the setting on.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$collections   = wp_insert_term( 'Collections', Folders::TAXONOMY )['term_id'];
		$this->visions = wp_insert_term( 'Visions', Folders::TAXONOMY, array( 'parent' => $collections ) )['term_id'];
		update_option( Settings::FOLDER_DIRECTORIES, true );
	}

	/**
	 * Remove saved files and the request.
	 */
	public function tear_down() {
		foreach ( $this->saved as $file ) {
			wp_delete_file( $file );
		}
		unset( $_POST['galerio_folder'] );
		parent::tear_down();
	}

	/**
	 * Save a copy of the test image as an upload, with a folder chosen.
	 *
	 * @param int $folder Folder, or 0.
	 * @return string Saved file path.
	 */
	private function save( $folder ) {
		$_POST['galerio_folder'] = (string) $folder;

		$tmp = wp_tempnam( 'canola.jpg' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $tmp );

		$file   = array(
			'name'     => 'canola.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		);
		$upload = wp_handle_sideload( $file, array( 'test_form' => false ) );

		$this->assertArrayHasKey( 'file', $upload, isset( $upload['error'] ) ? $upload['error'] : '' );
		$this->saved[] = $upload['file'];

		return $upload['file'];
	}

	/**
	 * A folder's directory follows its path.
	 */
	public function test_path() {
		$this->assertSame( '/collections/visions', Folder_Directories::path( $this->visions ) );
	}

	/**
	 * An upload into a shared folder is saved in its directory, and later
	 * uploads go back to the usual one.
	 */
	public function test_upload_into_folder_directory() {
		$uploads = wp_upload_dir();

		$this->assertSame( $uploads['basedir'] . '/collections/visions/canola.jpg', $this->save( $this->visions ) );
		$this->assertSame( $uploads['path'], wp_upload_dir()['path'], 'The uploads directory is put back.' );
		$this->assertStringStartsWith( $uploads['path'] . '/', $this->save( 0 ) );
	}

	/**
	 * Personal folders keep the year and month directories.
	 */
	public function test_personal_folders_keep_dated_directories() {
		update_option( Settings::PERSONAL_FOLDERS, true );
		$mine = wp_insert_term( 'Mine', Folders::TAXONOMY, array( 'parent' => Folders::user_root( get_current_user_id(), true ) ) )['term_id'];

		$this->assertSame( '', Folder_Directories::path( $mine ) );
		$this->assertStringStartsWith( wp_upload_dir()['path'] . '/', $this->save( $mine ) );
	}

	/**
	 * With the setting off, nothing changes.
	 */
	public function test_setting_off() {
		update_option( Settings::FOLDER_DIRECTORIES, false );

		$this->assertStringStartsWith( wp_upload_dir()['path'] . '/', $this->save( $this->visions ) );
	}

	/**
	 * Folders the person can't use are ignored.
	 */
	public function test_folder_not_allowed() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertStringStartsWith( wp_upload_dir()['path'] . '/', $this->save( $this->visions ) );
	}
}
