<?php
/**
 * Valuation field tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Display;

/**
 * A work's valuation: private catalogue fields for editors and administrators.
 *
 * @coversNothing
 */
class Test_Valuation extends Galerio_TestCase {

	/**
	 * Valuation fields round-trip through Pods.
	 */
	public function test_values_round_trip() {
		$fields = galerio_fields();
		$work   = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );

		$fields->save( $fields->get( 'work.valuation' ), $work, 1250.5 );
		$fields->save( $fields->get( 'work.valuation_date' ), $work, '2026-03-01' );
		$fields->save( $fields->get( 'work.valued_by' ), $work, 'Morgan & Price' );

		$this->assertSame( 1250.5, galerio_get_field( 'work.valuation', $work ) );
		$this->assertSame( '2026-03-01', galerio_get_field( 'work.valuation_date', $work ) );
		$this->assertSame( 'Morgan & Price', galerio_get_field( 'work.valued_by', $work ) );

		$fields->save( $fields->get( 'work.valuation' ), $work, null );
		$this->assertNull( galerio_get_field( 'work.valuation', $work ) );
	}

	/**
	 * Numbers show with decimals only when they have them.
	 */
	public function test_number_display() {
		$field = galerio_fields()->get( 'work.valuation' );

		$this->assertSame( '1,250', Display::render( $field, 1250.0, 0 ) );
		$this->assertSame( '1,250.50', Display::render( $field, 1250.5, 0 ) );
	}

	/**
	 * The valuation is private: never in public REST responses.
	 */
	public function test_private() {
		foreach ( array( 'valuation', 'valuation_date', 'valued_by', 'valuation_notes' ) as $name ) {
			$this->assertFalse( galerio_fields()->get( "work.$name" )->public, $name );
		}

		$work = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'work.valuation' ), $work, 900 );

		$response = rest_do_request( new WP_REST_Request( 'GET', "/wp/v2/galerio_work/$work" ) );
		$this->assertArrayNotHasKey( 'valuation', (array) $response->get_data()['galerio_fields'] );
	}

	/**
	 * Pods shows the fields in the editor to editors and administrators only.
	 */
	public function test_hidden_from_other_roles() {
		$field = pods_api()->load_field(
			array(
				'pod'  => 'galerio_work',
				'name' => 'valuation',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( pods_permission( $field ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$this->assertFalse( pods_permission( $field ) );
	}
}
