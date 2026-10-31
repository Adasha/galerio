<?php
/**
 * Work date tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Schema\Schema_Org;

/**
 * A work's date: a year, an optional last year, and optional words.
 *
 * @covers \Galerio\Core\Fields\Dates
 */
class Test_Dates extends Galerio_TestCase {

	/**
	 * Create a work with the given date fields.
	 *
	 * @param array $values Field name => value, without the "work." prefix.
	 * @return int Work ID.
	 */
	private function work( array $values ) {
		$fields = galerio_fields();
		$work   = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
			)
		);

		foreach ( $values as $name => $value ) {
			$fields->save( $fields->get( 'work.' . $name ), $work, $value );
		}

		return $work;
	}

	/**
	 * What the date shows for each combination of fields.
	 *
	 * @return array[]
	 */
	public function data_labels() {
		return array(
			'nothing'                  => array( array(), '' ),
			'one year'                 => array( array( 'year' => 1653 ), '1653' ),
			'a range in one century'   => array(
				array(
					'year'    => 1830,
					'year_to' => 1832,
				),
				'1830–32',
			),
			'a range across centuries' => array(
				array(
					'year'    => 1899,
					'year_to' => 1901,
				),
				'1899–1901',
			),
			'a range ending first'     => array(
				array(
					'year'    => 1900,
					'year_to' => 1850,
				),
				'1900',
			),
			'the same year twice'      => array(
				array(
					'year'    => 1900,
					'year_to' => 1900,
				),
				'1900',
			),
			'an end with no start'     => array( array( 'year_to' => 1900 ), '' ),
			'words win over years'     => array(
				array(
					'year'      => 1834,
					'year_to'   => 1860,
					'date_text' => 'mid-19th century',
				),
				'mid-19th century',
			),
			'words alone'              => array( array( 'date_text' => '664–525 BCE' ), '664–525 BCE' ),
			'early years in full'      => array(
				array(
					'year'    => 1,
					'year_to' => 5,
				),
				'1–5',
			),
		);
	}

	/**
	 * The date as shown.
	 *
	 * @dataProvider data_labels
	 *
	 * @param array  $values   Fields to save.
	 * @param string $expected Date as shown.
	 */
	public function test_label( array $values, $expected ) {
		$this->assertSame( $expected, galerio_get_field( 'work.date_label', $this->work( $values ) ) );
	}

	/**
	 * The date as shown can't be saved: it is worked out.
	 */
	public function test_label_is_not_saved() {
		$fields = galerio_fields();
		$work   = $this->work( array( 'year' => 1700 ) );

		$this->assertFalse( $fields->save( $fields->get( 'work.date_label' ), $work, 'nonsense' ) );
		$this->assertSame( '1700', galerio_get_field( 'work.date_label', $work ) );
	}

	/**
	 * A range is marked up as an ISO interval; words are left out, as they
	 * aren't dates.
	 */
	public function test_schema_date() {
		$range = Schema_Org::data(
			get_post(
				$this->work(
					array(
						'year'      => 1830,
						'year_to'   => 1832,
						'date_text' => 'about 1830',
					)
				)
			)
		);
		$one   = Schema_Org::data( get_post( $this->work( array( 'year' => 1830 ) ) ) );
		$words = Schema_Org::data( get_post( $this->work( array( 'date_text' => 'mid-19th century' ) ) ) );

		$this->assertSame( '1830/1832', $range['dateCreated'] );
		$this->assertSame( '1830', $one['dateCreated'] );
		$this->assertArrayNotHasKey( 'dateCreated', $words );
	}

	/**
	 * The new fields can be written over REST, and the label read back.
	 */
	public function test_rest_round_trip() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/galerio_work' );
		$request->set_body_params(
			array(
				'title'          => 'Ranged work',
				'status'         => 'publish',
				'galerio_fields' => array(
					'year'    => 1852,
					'year_to' => 1855,
				),
			)
		);
		$fields = (array) rest_get_server()->dispatch( $request )->get_data()['galerio_fields'];

		$this->assertSame( 1855, $fields['year_to'] );
		$this->assertSame( '1852–55', $fields['date_label'] );
	}

	/**
	 * Send a work request over REST as an administrator.
	 *
	 * @param string $method Method.
	 * @param string $route  Route.
	 * @param array  $params Body parameters.
	 * @return WP_REST_Response
	 */
	private function send( $method, $route, array $params ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( $method, $route );
		$request->set_body_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * A request with a field that can't be saved is refused before anything is
	 * written, even when its other fields are fine.
	 */
	public function test_bad_request_changes_nothing() {
		$work = $this->work( array( 'year' => 1700 ) );

		$response = $this->send(
			'POST',
			'/wp/v2/galerio_work/' . $work,
			array(
				'title'          => 'Renamed',
				'galerio_fields' => array(
					'year'       => 1800,
					'date_label' => 'nonsense',
				),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertNotSame( 'Renamed', get_the_title( $work ) );
		$this->assertSame( 1700, galerio_get_field( 'work.year', $work ) );
	}

	/**
	 * Clearing a field, and saving a value that hasn't changed, both succeed.
	 */
	public function test_clearing_and_unchanged_values_save() {
		$work = $this->work(
			array(
				'year'      => 1700,
				'year_to'   => 1710,
				'date_text' => 'about 1700',
			)
		);

		$same    = $this->send( 'POST', '/wp/v2/galerio_work/' . $work, array( 'galerio_fields' => array( 'year' => 1700 ) ) );
		$cleared = $this->send(
			'POST',
			'/wp/v2/galerio_work/' . $work,
			array(
				'galerio_fields' => array(
					'year_to'   => null,
					'date_text' => '',
				),
			)
		);

		$this->assertSame( 200, $same->get_status() );
		$this->assertSame( 200, $cleared->get_status() );
		$this->assertSame( '1700', galerio_get_field( 'work.date_label', $work ) );
	}
}
