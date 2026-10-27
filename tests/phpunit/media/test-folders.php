<?php
/**
 * Media folder tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Media\Folders;
use Galerio\Media\List_View;
use Galerio\Media\Own_Uploads;
use Galerio\Media\Settings;

/**
 * Shared and personal folders, their permissions, filing, and private uploads.
 *
 * @covers \Galerio\Media\Folders
 * @covers \Galerio\Media\Own_Uploads
 * @covers \Galerio\Media\List_View
 */
class Test_Folders extends Galerio_TestCase {

	/**
	 * An editor.
	 *
	 * @var int
	 */
	private $editor;

	/**
	 * A contributor (a student).
	 *
	 * @var int
	 */
	private $student;

	/**
	 * Another contributor.
	 *
	 * @var int
	 */
	private $other;

	/**
	 * Create people, and allow personal folders.
	 */
	public function set_up() {
		parent::set_up();

		$this->editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->student = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$this->other   = self::factory()->user->create( array( 'role' => 'contributor' ) );

		// Contributors can't upload by default; swansea.art's can.
		get_role( 'contributor' )->add_cap( 'upload_files' );
		update_option( Settings::PERSONAL_FOLDERS, true );
	}

	/**
	 * Undo the role change.
	 */
	public function tear_down() {
		get_role( 'contributor' )->remove_cap( 'upload_files' );
		parent::tear_down();
	}

	/**
	 * Add a folder as someone.
	 *
	 * @param int    $user   Person.
	 * @param string $name   Folder name.
	 * @param int    $parent_id Parent folder.
	 * @return int|WP_Error Term ID, or the error.
	 */
	private function add_folder( $user, $name, $parent_id = 0 ) {
		wp_set_current_user( $user );
		$term = wp_insert_term( $name, Folders::TAXONOMY, array( 'parent' => $parent_id ) );

		return is_wp_error( $term ) ? $term : $term['term_id'];
	}

	/**
	 * Editors add shared folders; students can't.
	 */
	public function test_shared_folders() {
		$shared = $this->add_folder( $this->editor, 'Exhibitions' );
		$this->assertIsInt( $shared );
		$this->assertSame( 0, Folders::owner( $shared ) );

		$this->assertWPError( $this->add_folder( $this->student, 'Mine' ) );
		$this->assertWPError( $this->add_folder( $this->student, 'Inside', $shared ) );
	}

	/**
	 * Personal folders sit under each person's root, can reuse shared names,
	 * and are seen and managed by their owner alone.
	 */
	public function test_personal_folders() {
		$this->add_folder( $this->editor, 'Posts' );

		$root   = Folders::user_root( $this->student, true );
		$posts  = $this->add_folder( $this->student, 'Posts', $root );
		$others = $this->add_folder( $this->other, 'Drafts', Folders::user_root( $this->other, true ) );

		$this->assertIsInt( $posts, 'A personal folder may share a shared folder’s name.' );
		$this->assertSame( $this->student, Folders::owner( $posts ) );

		wp_set_current_user( $this->student );
		$names = wp_list_pluck( rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media-folders' ) )->get_data(), 'name' );
		$this->assertContains( 'Posts', $names );
		$this->assertNotContains( 'Drafts', $names, 'Other people’s folders are hidden.' );

		$this->assertTrue( current_user_can( 'edit_term', $posts ) );
		$this->assertFalse( current_user_can( 'edit_term', $others ) );
		$this->assertFalse( current_user_can( 'assign_term', $others ) );
		$this->assertFalse( current_user_can( 'delete_term', $root ), 'The hidden root can’t be deleted.' );
		$this->assertWPError( $this->add_folder( $this->student, 'Sneaky', $others ) );
	}

	/**
	 * With personal folders off, only shared folders are seen.
	 */
	public function test_personal_folders_off() {
		$this->add_folder( $this->student, 'Posts', Folders::user_root( $this->student, true ) );
		update_option( Settings::PERSONAL_FOLDERS, false );

		wp_set_current_user( $this->student );
		$this->assertSame( array(), rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media-folders' ) )->get_data() );
		$this->assertSame( array(), Folders::tree( $this->student ) );
	}

	/**
	 * Students can file into shared folders without managing them.
	 */
	public function test_students_file_into_shared_folders() {
		$shared = $this->add_folder( $this->editor, 'Exhibitions' );

		wp_set_current_user( $this->student );
		$this->assertTrue( current_user_can( 'assign_term', $shared ) );
		$this->assertFalse( current_user_can( 'edit_term', $shared ) );
		$this->assertFalse( current_user_can( 'delete_term', $shared ) );
	}

	/**
	 * Filing a file moves it within one space and leaves the others alone.
	 */
	public function test_filing() {
		$first  = $this->add_folder( $this->editor, 'First' );
		$second = $this->add_folder( $this->editor, 'Second' );
		$mine   = $this->add_folder( $this->student, 'Mine', Folders::user_root( $this->student, true ) );
		$file   = self::factory()->attachment->create_object( 'photo.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );

		Folders::file( $file, $first );
		Folders::file( $file, $mine );
		Folders::file( $file, $second );

		$in = wp_get_object_terms( $file, Folders::TAXONOMY, array( 'fields' => 'ids' ) );
		sort( $in );
		$this->assertSame( array( $second, $mine ), $in );

		Folders::file( $file, 0, 0 );
		$this->assertSame( array( $mine ), wp_get_object_terms( $file, Folders::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * The tree lists shared folders then the person's own, in order, with depth.
	 */
	public function test_tree() {
		$b    = $this->add_folder( $this->editor, 'B' );
		$a    = $this->add_folder( $this->editor, 'A' );
		$a1   = $this->add_folder( $this->editor, 'A1', $a );
		$root = Folders::user_root( $this->student, true );
		$this->add_folder( $this->student, 'Mine', $root );
		update_term_meta( $b, Folders::ORDER, 1 );
		update_term_meta( $a, Folders::ORDER, 2 );

		$tree = Folders::tree( $this->student );

		$this->assertSame( array( 'B', 'A', 'A1', 'My folders', 'Mine' ), wp_list_pluck( $tree, 'name' ) );
		$this->assertSame( 1, $tree[2]['depth'] );
		$this->assertTrue( $tree[3]['root'] );
		$this->assertSame( $a1, $tree[2]['id'] );
	}

	/**
	 * Students see only their own uploads; editors see everyone's.
	 */
	public function test_own_uploads() {
		wp_set_current_user( $this->student );
		$this->assertTrue( Own_Uploads::applies() );
		$this->assertSame( $this->student, Own_Uploads::ajax_args( array() )['author'] );
		$this->assertSame( $this->student, Own_Uploads::rest_args( array() )['author'] );

		wp_set_current_user( $this->editor );
		$this->assertFalse( Own_Uploads::applies() );
		$this->assertArrayNotHasKey( 'author', Own_Uploads::ajax_args( array() ) );

		update_option( Settings::OWN_UPLOADS, '0' ); // As the old settings page saved it.
		wp_set_current_user( $this->student );
		$this->assertFalse( Own_Uploads::applies(), 'The earlier switch, turned off, still turns it off.' );
	}

	/**
	 * The role lists on Settings → Media decide who sees only their own
	 * uploads and who manages the shared folders; administrators always see
	 * and manage everything.
	 */
	public function test_role_lists() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame( array( 'editor' ), Settings::folder_managers(), 'By default, roles that edit others’ posts manage shared folders.' );
		$this->assertContains( 'contributor', Settings::own_uploads_roles() );
		$this->assertNotContains( 'editor', Settings::own_uploads_roles() );

		update_option( Settings::FOLDER_MANAGERS, Settings::sanitize_roles( array( 'contributor', 'administrator', 'nobody' ) ) );
		update_option( Settings::OWN_UPLOADS_ROLES, Settings::sanitize_roles( array( 'editor' ) ) );
		$this->assertSame( array( 'contributor' ), Settings::folder_managers(), 'Only roles the list offers are kept.' );

		$this->assertTrue( Folders::can_manage_shared( $this->student ) );
		$this->assertFalse( Folders::can_manage_shared( $this->editor ) );
		$this->assertTrue( Folders::can_manage_shared( $admin ) );
		$this->assertIsInt( $this->add_folder( $this->student, 'Made by a student' ) );

		wp_set_current_user( $this->editor );
		$this->assertTrue( Own_Uploads::applies() );
		wp_set_current_user( $this->student );
		$this->assertFalse( Own_Uploads::applies() );

		wp_get_current_user()->add_role( 'editor' );
		update_option( Settings::OWN_UPLOADS_ROLES, array( 'contributor' ) );
		$this->assertFalse( Own_Uploads::applies(), 'A role outside the list lets someone see everything.' );

		update_option( Settings::OWN_UPLOADS_ROLES, array() );
		wp_set_current_user( $this->other );
		$this->assertFalse( Own_Uploads::applies(), 'An empty list turns it off.' );
	}

	/**
	 * Folders move within their space, by the people who may change them, and
	 * never into themselves.
	 */
	public function test_moving_folders() {
		$a      = $this->add_folder( $this->editor, 'A' );
		$b      = $this->add_folder( $this->editor, 'B' );
		$b1     = $this->add_folder( $this->editor, 'B1', $b );
		$root   = Folders::user_root( $this->student, true );
		$mine   = $this->add_folder( $this->student, 'Mine', $root );
		$inner  = $this->add_folder( $this->student, 'Inner', $mine );
		$parent = function ( $id ) {
			return (int) get_term_field( 'parent', $id, Folders::TAXONOMY );
		};

		wp_set_current_user( $this->editor );
		$this->assertTrue( Folders::move( $b, $a ) );
		$this->assertSame( $a, $parent( $b ) );
		$this->assertSame( $b, $parent( $b1 ), 'Folders inside move with it.' );
		$this->assertWPError( Folders::move( $a, $b1 ), 'Not into a folder inside itself.' );
		$this->assertWPError( Folders::move( $a, $a ) );
		$this->assertTrue( Folders::move( $b, 0 ) );
		$this->assertSame( 0, $parent( $b ) );

		wp_set_current_user( $this->student );
		$this->assertWPError( Folders::move( $b, $a ), 'Students can’t move shared folders.' );
		$this->assertWPError( Folders::move( $inner, $a ), 'Personal folders stay among their owner’s.' );
		$this->assertTrue( Folders::move( $inner, 0 ) );
		$this->assertSame( $root, $parent( $inner ), 'The top of “My folders” is the person’s root.' );
		$this->assertTrue( Folders::move( $inner, $mine ) );

		wp_set_current_user( $this->editor );
		$this->assertWPError( Folders::move( $inner, $mine ), 'Nobody else moves someone’s own folders.' );
		$this->assertWPError( Folders::move( $a, $root ), 'Shared folders don’t go into someone’s own.' );
	}

	/**
	 * Filing a file keeps it in other people's folders, which the person
	 * filing can't see.
	 */
	public function test_filing_keeps_hidden_folders() {
		$shared = $this->add_folder( $this->editor, 'Exhibitions' );
		$theirs = $this->add_folder( $this->other, 'Theirs', Folders::user_root( $this->other, true ) );
		$file   = self::factory()->attachment->create_object( 'photo.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		Folders::file( $file, $theirs );

		wp_set_current_user( $this->student );
		Folders::file( $file, $shared );

		$in = wp_get_object_terms(
			$file,
			Folders::TAXONOMY,
			array(
				'fields'      => 'ids',
				'galerio_all' => true,
			)
		);
		sort( $in );
		$this->assertSame( array( $shared, $theirs ), $in );
	}

	/**
	 * A file's details save its folders, but never into someone else's.
	 */
	public function test_details_save() {
		$shared = $this->add_folder( $this->editor, 'Exhibitions' );
		$others = $this->add_folder( $this->other, 'Theirs', Folders::user_root( $this->other, true ) );
		$file   = self::factory()->attachment->create_object( 'photo.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );

		wp_set_current_user( $this->student );
		List_View::save(
			array( 'ID' => $file ),
			array(
				'galerio_folder_0'               => $shared,
				'galerio_folder_' . $this->other => $others,
			)
		);

		$this->assertSame( array( $shared ), wp_get_object_terms( $file, Folders::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}

	/**
	 * A folder choice narrows files to that folder, or to files in no shared
	 * folder; other people's folders are ignored.
	 */
	public function test_folder_choice() {
		$shared = $this->add_folder( $this->editor, 'Exhibitions' );
		$mine   = $this->add_folder( $this->student, 'Mine', Folders::user_root( $this->student, true ) );
		$others = $this->add_folder( $this->other, 'Theirs', Folders::user_root( $this->other, true ) );
		$filed  = self::factory()->attachment->create_object( 'filed.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		$loose  = self::factory()->attachment->create_object( 'loose.jpg', 0, array( 'post_mime_type' => 'image/jpeg' ) );
		Folders::file( $filed, $shared );
		Folders::file( $loose, $mine );

		$this->assertSame( 'unfiled', Folders::parse_choice( 'unfiled' ) );
		$this->assertSame( $shared, Folders::parse_choice( (string) $shared ) );
		$this->assertSame( 0, Folders::parse_choice( array( 'x' ) ) );

		wp_set_current_user( $this->student );
		$files = function ( $choice ) {
			return get_posts(
				array(
					'post_type'   => 'attachment',
					'post_status' => 'inherit',
					'fields'      => 'ids',
					'tax_query'   => Folders::tax_query( $choice ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Testing the folder filter.
				)
			);
		};

		$this->assertSame( array( $filed ), $files( $shared ) );
		$this->assertSame( array( $loose ), $files( 'unfiled' ), 'A file only in a personal folder is in no shared folder.' );
		$this->assertNull( Folders::tax_query( $others ), 'Someone else’s folder doesn’t filter.' );
		$this->assertNull( Folders::tax_query( 0 ) );
	}
}
