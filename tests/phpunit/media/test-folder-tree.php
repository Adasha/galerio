<?php
/**
 * Folder tree API and Media Library tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Media\Folders;
use Galerio\Media\Library;
use Galerio\Media\Settings;

/**
 * The REST API behind the folder tree, and the Media Library's folder filter
 * and uploads.
 *
 * @covers \Galerio\Media\Rest
 * @covers \Galerio\Media\Library
 */
class Test_Folder_Tree extends Galerio_TestCase {

	/**
	 * An editor.
	 *
	 * @var int
	 */
	private $editor;

	/**
	 * A contributor who can upload.
	 *
	 * @var int
	 */
	private $student;

	/**
	 * A shared folder.
	 *
	 * @var int
	 */
	private $shared;

	/**
	 * Create people and a shared folder, and allow personal folders.
	 */
	public function set_up() {
		parent::set_up();

		get_role( 'contributor' )->add_cap( 'upload_files' );
		update_option( Settings::PERSONAL_FOLDERS, true );

		$this->editor  = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->student = self::factory()->user->create( array( 'role' => 'contributor' ) );

		wp_set_current_user( $this->editor );
		$this->shared = wp_insert_term( 'Exhibitions', Folders::TAXONOMY )['term_id'];
	}

	/**
	 * Undo the role change and the request.
	 */
	public function tear_down() {
		get_role( 'contributor' )->remove_cap( 'upload_files' );
		unset( $_REQUEST['query'], $_POST['galerio_folder'] );
		parent::tear_down();
	}

	/**
	 * Make a REST request as the current user.
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 * @param array  $params Body parameters.
	 * @return WP_REST_Response
	 */
	private function request( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * A file uploaded by someone.
	 *
	 * @param int $author Uploader.
	 * @return int
	 */
	private function upload( $author ) {
		return self::factory()->attachment->create_object(
			'photo.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $author,
			)
		);
	}

	/**
	 * The tree lists what the person sees, with what they may do; counts are
	 * hidden from people who see only their own uploads.
	 */
	public function test_folders() {
		$data = $this->request( 'GET', '/galerio-media/v1/folders' )->get_data();
		$this->assertTrue( $data['canAddShared'] );
		$this->assertTrue( $data['folders'][0]['canEdit'] );
		$this->assertSame( 0, $data['folders'][0]['count'] );

		wp_set_current_user( $this->student );
		$data = $this->request( 'GET', '/galerio-media/v1/folders' )->get_data();
		$this->assertFalse( $data['canAddShared'] );
		$this->assertFalse( $data['folders'][0]['canEdit'] );
		$this->assertNull( $data['folders'][0]['count'] );

		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET', '/galerio-media/v1/folders' )->get_status() );
	}

	/**
	 * Students add folders to "My folders" only.
	 */
	public function test_create() {
		wp_set_current_user( $this->student );

		$this->assertSame( 400, $this->request( 'POST', '/galerio-media/v1/folders', array( 'name' => 'Shared?' ) )->get_status() );

		$response = $this->request(
			'POST',
			'/galerio-media/v1/folders',
			array(
				'name'     => 'Mine',
				'personal' => true,
			)
		);
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $this->student, Folders::owner( $response->get_data()['id'] ) );
		$this->assertSame( Folders::user_root( $this->student ), get_term( $response->get_data()['id'] )->parent );

		update_option( Settings::PERSONAL_FOLDERS, false );
		$this->assertSame(
			403,
			$this->request(
				'POST',
				'/galerio-media/v1/folders',
				array(
					'name'     => 'Off',
					'personal' => true,
				)
			)->get_status()
		);
	}

	/**
	 * People file only files they may edit, into folders they may use.
	 */
	public function test_file() {
		$own    = $this->upload( $this->student );
		$theirs = $this->upload( $this->editor );

		wp_set_current_user( $this->student );
		$response = $this->request(
			'POST',
			'/galerio-media/v1/file',
			array(
				'folder'      => $this->shared,
				'attachments' => array( $own, $theirs ),
			)
		);

		$this->assertSame( 1, $response->get_data()['filed'] );
		$this->assertSame( array( $this->shared ), wp_get_object_terms( $own, Folders::TAXONOMY, array( 'fields' => 'ids' ) ) );
		$this->assertSame( array(), wp_get_object_terms( $theirs, Folders::TAXONOMY, array( 'fields' => 'ids' ) ) );

		$root = Folders::user_root( $this->student, true );
		$this->assertSame(
			403,
			$this->request(
				'POST',
				'/galerio-media/v1/file',
				array(
					'folder'      => $root,
					'attachments' => array( $own ),
				)
			)->get_status(),
			'Nothing goes in the hidden root itself.'
		);
	}

	/**
	 * The Media Library shows a folder's files, or the files in no shared folder.
	 */
	public function test_query() {
		$_REQUEST['query'] = array( 'galerio_folder' => (string) $this->shared );
		$args              = Library::query( array() );
		$this->assertSame( $this->shared, $args['tax_query'][0]['terms'] );

		$_REQUEST['query'] = array( 'galerio_folder' => 'unfiled' );
		$args              = Library::query( array() );
		$this->assertSame( 'NOT IN', $args['tax_query'][0]['operator'] );
		$this->assertContains( $this->shared, $args['tax_query'][0]['terms'] );

		unset( $_REQUEST['query'] );
		$this->assertArrayNotHasKey( 'tax_query', Library::query( array() ) );
	}

	/**
	 * A file uploaded while a folder is open goes into it.
	 */
	public function test_upload_into_open_folder() {
		$_POST['galerio_folder'] = (string) $this->shared;
		$file                    = $this->upload( $this->editor );

		$this->assertSame( array( $this->shared ), wp_get_object_terms( $file, Folders::TAXONOMY, array( 'fields' => 'ids' ) ) );
	}
}
