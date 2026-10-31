<?php
/**
 * REST field write tests.
 *
 * @package Galerio\Tests
 */

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
}
