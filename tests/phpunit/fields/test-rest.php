<?php
/**
 * REST field write tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Rest;

/**
 * Saving fields through the galerio_fields REST property.
 *
 * @covers \Galerio\Core\Fields\Rest
 */
class Test_Rest extends Galerio_TestCase {

	/**
	 * Send a create request for a work.
	 *
	 * @param array $fields Field values.
	 * @return WP_REST_Response
	 */
	private function create_work( array $fields ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/galerio_work' );
		$request->set_body_params(
			array(
				'title'          => 'REST work',
				'status'         => 'publish',
				'galerio_fields' => $fields,
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Named fields are saved and read back.
	 */
	public function test_fields_are_saved() {
		$response = $this->create_work(
			array(
				'year'   => 1650,
				'medium' => 'Oil on canvas',
			)
		);
		$fields   = (array) $response->get_data()['galerio_fields'];

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 1650, $fields['year'] );
		$this->assertSame( 'Oil on canvas', $fields['medium'] );
	}

	/**
	 * Unknown fields and fields worked out from others are refused.
	 */
	public function test_unsaveable_fields_are_refused() {
		$this->assertSame( 400, $this->create_work( array( 'price_label' => 'x' ) )->get_status() );
		$this->assertSame( 400, $this->create_work( array( 'nonsense' => 'x' ) )->get_status() );
	}

	/**
	 * Links to unpublished items are hidden from people who can't edit the item.
	 */
	public function test_unpublished_links_are_for_editors() {
		$profile   = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		$published = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		$draft     = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_collection',
				'post_status' => 'draft',
			)
		);
		$many      = galerio_fields()->get( 'profile.collections' );
		$one       = galerio_fields()->get( 'work.creator' );

		wp_set_current_user( 0 );
		$this->assertSame( array( $published ), Rest::visible_value( $many, array( $draft, $published ), $profile ) );
		$this->assertSame( $one->empty_value(), Rest::visible_value( $one, $draft, $profile ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( array( $draft, $published ), Rest::visible_value( $many, array( $draft, $published ), $profile ) );
	}

	/**
	 * Read a work's fields through the REST API.
	 *
	 * @param int    $id      Work.
	 * @param string $context Request context.
	 * @return array
	 */
	private function read_work( $id, $context ) {
		$request = new WP_REST_Request( 'GET', '/wp/v2/galerio_work/' . $id );
		$request->set_param( 'context', $context );

		return (array) rest_get_server()->dispatch( $request )->get_data()['galerio_fields'];
	}

	/**
	 * Private fields are read and saved by people editing the item, and nobody else.
	 */
	public function test_private_fields_are_for_the_editor() {
		$response = $this->create_work( array( 'owner' => 'A collector' ) );
		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];

		$this->assertSame( 'A collector', $this->read_work( $id, 'edit' )['owner'] );
		$this->assertArrayNotHasKey( 'owner', $this->read_work( $id, 'view' ) );

		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( 'owner', $this->read_work( $id, 'view' ) );
	}

	/**
	 * A field limited to other roles can't be read or saved by this user.
	 */
	public function test_roles_limit_fields() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$author = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$field  = galerio_fields()->get( 'profile.year_of_birth' );

		$this->assertTrue( $field->can_edit() );
		$this->assertFalse( $field->can_edit( $author ) );
		$this->assertTrue( $field->can_edit( self::factory()->user->create( array( 'role' => 'administrator' ) ) ) );
		$this->assertTrue( galerio_fields()->get( 'profile.nationality' )->can_edit( $author ) );

		wp_set_current_user( $author );

		$request = new WP_REST_Request( 'POST', '/wp/v2/galerio_profile' );
		$request->set_body_params(
			array(
				'title'          => 'A student',
				'status'         => 'draft',
				'galerio_fields' => array( 'year_of_birth' => 1990 ),
			)
		);

		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}
}
