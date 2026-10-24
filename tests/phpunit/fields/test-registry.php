<?php
/**
 * Field registry tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Field;
use Galerio\Core\Fields\Registry;

/**
 * Registry, Field and the public field functions.
 *
 * @covers \Galerio\Core\Fields\Registry
 * @covers \Galerio\Core\Fields\Field
 */
class Test_Registry extends Galerio_TestCase {

	/**
	 * A registry over the in-memory adapter.
	 *
	 * @param array $defaults Default field definitions.
	 * @return Registry
	 */
	private function registry( array $defaults = array() ) {
		return new Registry( new Galerio_Memory_Adapter(), $defaults );
	}

	/**
	 * Invalid definitions are rejected.
	 *
	 * @dataProvider data_invalid_definitions
	 *
	 * @param string $key  Field key.
	 * @param array  $args Definition.
	 */
	public function test_rejects_invalid_definitions( $key, $args ) {
		$this->expectException( InvalidArgumentException::class );
		$this->registry()->register( $key, $args );
	}

	/**
	 * Data for test_rejects_invalid_definitions.
	 *
	 * @return array[]
	 */
	public function data_invalid_definitions() {
		return array(
			'no object'          => array( 'year', array() ),
			'bad characters'     => array( 'work.Year!', array() ),
			'unknown type'       => array( 'work.year', array( 'type' => 'colour' ) ),
			'relation no target' => array( 'work.creator', array( 'type' => 'relation' ) ),
			'unknown object'     => array( 'exhibition.year', array() ),
		);
	}

	/**
	 * Defaults and the registration hook load on first use, once.
	 */
	public function test_loads_defaults_and_runs_hook_once() {
		$calls    = 0;
		$callback = function ( Registry $registry ) use ( &$calls ) {
			++$calls;
			$registry->register( 'work.catalogue_number', array( 'label' => 'Catalogue number' ) );
		};
		add_action( 'galerio_register_fields', $callback );

		$registry = $this->registry( array( 'work.year' => array( 'type' => 'year' ) ) );

		$this->assertSame( 0, $calls, 'The hook should not run until the registry is used.' );
		$this->assertNotNull( $registry->get( 'work.year' ) );
		$this->assertNotNull( $registry->get( 'work.catalogue_number' ) );
		$registry->all();
		$this->assertSame( 1, $calls );

		remove_action( 'galerio_register_fields', $callback );
	}

	/**
	 * Fields registered before first use are not overwritten by the defaults.
	 */
	public function test_early_registration_overrides_defaults() {
		$registry = $this->registry( array( 'work.year' => array( 'type' => 'year' ) ) );
		$registry->register( 'work.year', array( 'type' => 'text' ) );

		$this->assertSame( 'text', $registry->get( 'work.year' )->type );
	}

	/**
	 * Keys resolve against the post's own object.
	 */
	public function test_resolve() {
		$registry = $this->registry(
			array(
				'work.year'    => array( 'type' => 'year' ),
				'profile.year' => array( 'type' => 'year' ),
			)
		);
		$work     = self::factory()->post->create_and_get( array( 'post_type' => 'galerio_work' ) );
		$post     = self::factory()->post->create_and_get();

		$this->assertSame( 'work.year', $registry->resolve( 'year', $work )->key );
		$this->assertSame( 'work.year', $registry->resolve( 'work.year', $work )->key );
		$this->assertNull( $registry->resolve( 'profile.year', $work ), 'Fields of another object do not resolve.' );
		$this->assertNull( $registry->resolve( 'year', $post ), 'Posts of other types have no fields.' );
	}

	/**
	 * Values are normalised by type.
	 *
	 * @dataProvider data_normalise
	 *
	 * @param array $args     Field definition.
	 * @param mixed $value    Input.
	 * @param mixed $expected Normalised value.
	 */
	public function test_normalise( $args, $value, $expected ) {
		$field = new Field( 'work.test', $args );
		$this->assertSame( $expected, $field->normalise( $value ) );
	}

	/**
	 * Data for test_normalise.
	 *
	 * @return array[]
	 */
	public function data_normalise() {
		return array(
			'number as text'      => array( array(), 42, '42' ),
			'false as text'       => array( array(), false, '' ),
			'year from date'      => array( array( 'type' => 'year' ), '2018-01-01', 2018 ),
			'empty year'          => array( array( 'type' => 'year' ), '', null ),
			'zero date'           => array( array( 'type' => 'date' ), '0000-00-00', '' ),
			'datetime to date'    => array( array( 'type' => 'date' ), '2024-03-15 10:00:00', '2024-03-15' ),
			'number from text'    => array( array( 'type' => 'number' ), '1250.50', 1250.5 ),
			'whole number'        => array( array( 'type' => 'number' ), 12, 12.0 ),
			'empty number'        => array( array( 'type' => 'number' ), '', null ),
			'not a number'        => array( array( 'type' => 'number' ), 'about 100', null ),
			'boolean string zero' => array( array( 'type' => 'boolean' ), '0', false ),
			'boolean string one'  => array( array( 'type' => 'boolean' ), '1', true ),
			'post row to ID'      => array(
				array(
					'type'   => 'relation',
					'target' => 'profile',
				),
				array( 'ID' => '149' ),
				149,
			),
			'list for single'     => array( array( 'type' => 'image' ), array( 7, 8 ), 7 ),
			'scalar for multiple' => array(
				array(
					'type'     => 'image',
					'multiple' => true,
				),
				'7',
				array( 7 ),
			),
			'drops empty items'   => array(
				array( 'multiple' => true ),
				array( 'a', '', null, 'b' ),
				array( 'a', 'b' ),
			),
		);
	}

	/**
	 * The galerio_get_field() function reads through the shared registry.
	 */
	public function test_get_field() {
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		galerio_fields()->save( galerio_fields()->get( 'work.medium' ), $work, 'Oil & acrylic' );

		$this->assertSame( 'Oil & acrylic', galerio_get_field( 'medium', $work ) );
		$this->assertSame( 'Oil &amp; acrylic', galerio_get_field( 'work.medium', $work, 'display' ) );
		$this->assertNull( galerio_get_field( 'no_such_field', $work ) );
		$this->assertNull( galerio_get_field( 'medium', self::factory()->post->create() ) );
		$this->assertNull( galerio_get_field( 'medium', PHP_INT_MAX ) );
	}

	/**
	 * The current post is used by default.
	 */
	public function test_get_field_defaults_to_current_post() {
		$work = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		galerio_fields()->save( galerio_fields()->get( 'work.year' ), $work, 1999 );

		$this->go_to( get_permalink( $work ) );
		the_post();

		$this->assertSame( 1999, galerio_get_field( 'year' ) );
	}

	/**
	 * Reading a deprecated field warns developers.
	 */
	public function test_deprecated_field_warns() {
		$this->setExpectedIncorrectUsage( 'galerio_get_field' );

		galerio_fields()->register( 'work.old_note', array( 'deprecated' => 'Use work.description.' ) );
		galerio_get_field( 'old_note', self::factory()->post->create( array( 'post_type' => 'galerio_work' ) ) );
		galerio_fields()->unregister( 'work.old_note' );
	}

	/**
	 * Values are sanitised by type when saved.
	 *
	 * @dataProvider data_sanitise
	 *
	 * @param string $key      Field key.
	 * @param mixed  $value    Value to save.
	 * @param mixed  $expected Value read back.
	 */
	public function test_save_sanitises( $key, $value, $expected ) {
		$field    = galerio_fields()->get( $key );
		$registry = $this->registry();
		$work     = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );

		$registry->save( $field, $work, $value );

		$this->assertSame( $expected, $registry->adapter()->get( $field, $work ) );
	}

	/**
	 * Data for test_save_sanitises.
	 *
	 * @return array[]
	 */
	public function data_sanitise() {
		return array(
			'text loses markup'   => array( 'work.medium', "Oil <b>on</b>\ncanvas", 'Oil on canvas' ),
			'html keeps markup'   => array( 'work.description', '<p>A <strong>bold</strong> work.<script>alert(1)</script></p>', '<p>A <strong>bold</strong> work.alert(1)</p>' ),
			'unsafe link removed' => array( 'work.website', array( 'url' => 'javascript:alert(1)' ), null ),
			'link label cleaned'  => array(
				'work.website',
				array(
					'url'   => 'https://example.org',
					'label' => 'My <b>site</b>',
				),
				array(
					'url'     => 'https://example.org',
					'label'   => 'My site',
					'offline' => false,
				),
			),
		);
	}

	/**
	 * Display values are formatted and escaped by type.
	 */
	public function test_display() {
		$profile = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_profile',
				'post_title' => 'Tomos & Co',
			)
		);
		$draft   = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_collection',
				'post_status' => 'draft',
			)
		);
		$public  = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_collection',
				'post_title' => 'Shorelines',
			)
		);
		$work    = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$fields  = galerio_fields();

		$fields->save( $fields->get( 'work.creator' ), $work, $profile );
		$fields->save( $fields->get( 'work.collections' ), $work, array( $draft, $public ) );
		$fields->save( $fields->get( 'work.website' ), $work, array( 'url' => 'https://www.example.org/work/' ) );
		$fields->save( $fields->get( 'work.year' ), $work, 2018 );

		$this->assertSame(
			sprintf( '<a href="%s">Tomos &amp; Co</a>', get_permalink( $profile ) ),
			galerio_get_field( 'creator', $work, 'display' )
		);
		$this->assertSame(
			sprintf( '<a href="%s">Shorelines</a>', get_permalink( $public ) ),
			galerio_get_field( 'collections', $work, 'display' ),
			'Unpublished related posts are left out.'
		);
		$this->assertSame( '<a class="galerio-link" href="https://www.example.org/work/">example.org/work</a>', galerio_get_field( 'website', $work, 'display' ) );
		$this->assertSame( '2018', galerio_get_field( 'year', $work, 'display' ) );
		$this->assertSame( '', galerio_get_field( 'definitive_title', $work, 'display' ) );
	}

	/**
	 * Related posts come back in saved order, published only.
	 */
	public function test_get_related() {
		$work  = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$one   = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$two   = self::factory()->post->create( array( 'post_type' => 'galerio_work' ) );
		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'draft',
			)
		);

		galerio_fields()->save( galerio_fields()->get( 'work.related_works' ), $work, array( $two, $draft, $one ) );

		$this->assertSame( array( $two, $one ), wp_list_pluck( galerio_get_related( 'related_works', $work ), 'ID' ) );
		$this->assertSame( array(), galerio_get_related( 'definitive_title', $work ), 'Non-relation fields have no related posts.' );
	}

	/**
	 * REST responses carry public fields only.
	 */
	public function test_rest_field() {
		$work = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'work.medium' ), $work, 'Oil on canvas' );
		galerio_fields()->save( galerio_fields()->get( 'work.owner' ), $work, 'A private collector' );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/galerio_work/' . $work ) );
		$fields   = (array) $response->get_data()['galerio_fields'];

		$this->assertSame( 'Oil on canvas', $fields['medium'] );
		$this->assertArrayNotHasKey( 'owner', $fields );
	}
}
