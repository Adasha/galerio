<?php
/**
 * Tests every storage adapter must pass.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Adapter;
use Galerio\Core\Fields\Field;

/**
 * Round-trips values of every field type through an adapter.
 *
 * Subclasses provide the adapter. The fields used here exist in the Galerio
 * Pods package, so storage-backed adapters can save them for real.
 */
abstract class Galerio_Adapter_Contract_TestCase extends Galerio_TestCase {

	/**
	 * Adapter under test.
	 *
	 * @var Adapter
	 */
	protected $adapter;

	/**
	 * Create the adapter under test.
	 *
	 * @return Adapter
	 */
	abstract protected function make_adapter();

	/**
	 * Set up the adapter.
	 */
	public function set_up() {
		parent::set_up();
		$this->adapter = $this->make_adapter();
	}

	/**
	 * Build a field from the default definitions.
	 *
	 * @param string $key Field key.
	 * @return Field
	 */
	protected function field( $key ) {
		$definitions = require GALERIO_CORE_DIR . 'includes/fields/default-fields.php';
		return new Field( $key, $definitions[ $key ] );
	}

	/**
	 * Create a post for a field's object.
	 *
	 * @param string $object_name Object name.
	 * @return int
	 */
	protected function make_object( $object_name ) {
		if ( 'attachment' === $object_name ) {
			return self::factory()->attachment->create_object(
				array(
					'file'           => 'image.jpg',
					'post_mime_type' => 'image/jpeg',
				)
			);
		}

		return self::factory()->post->create(
			array(
				'post_type'   => 'galerio_' . $object_name,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * The adapter reports that it can be used.
	 */
	public function test_is_available() {
		$this->assertTrue( $this->adapter->is_available() );
	}

	/**
	 * Values of each type come back in canonical form.
	 *
	 * @dataProvider data_round_trip
	 *
	 * @param string $key      Field key.
	 * @param mixed  $value    Value to save.
	 * @param mixed  $expected Expected value; defaults to $value.
	 */
	public function test_round_trip( $key, $value, $expected = null ) {
		$field = $this->field( $key );
		$id    = $this->make_object( $field->object );

		$this->assertTrue( $this->adapter->set( $field, $id, $value ), 'set() should report success.' );
		$this->assertSame( null === $expected ? $value : $expected, $this->adapter->get( $field, $id ) );
	}

	/**
	 * Data for test_round_trip.
	 *
	 * @return array[]
	 */
	public function data_round_trip() {
		return array(
			'text'                => array( 'work.definitive_title', 'Copperworks II' ),
			'html'                => array( 'collection.description', '<p>Paintings of the <strong>coast</strong>.</p>' ),
			'url'                 => array( 'work.website_url', 'https://example.org/work' ),
			'repeatable url'      => array( 'profile.other_websites', array( 'https://example.org/a', 'https://example.org/b' ) ),
			'repeatable textarea' => array( 'profile.accolades', array( 'First prize', 'Second prize' ) ),
			'date'                => array( 'collection.opening_date', '2024-03-15' ),
			'year'                => array( 'work.year', 2018 ),
			'boolean true'        => array( 'collection.dead_link', true ),
			'boolean false'       => array( 'collection.dead_link', false ),
			'attachment text'     => array( 'attachment.copyright', '© Tomos Price' ),
			'attachment year'     => array( 'attachment.year', 1999 ),
		);
	}

	/**
	 * A single image is stored and returned as an attachment ID.
	 */
	public function test_single_image() {
		$field = $this->field( 'work.principal_image' );
		$id    = $this->make_object( 'work' );
		$image = $this->make_object( 'attachment' );

		$this->adapter->set( $field, $id, $image );

		$this->assertSame( $image, $this->adapter->get( $field, $id ) );
	}

	/**
	 * Multiple images keep their order.
	 */
	public function test_multiple_images_keep_order() {
		$field  = $this->field( 'work.additional_images' );
		$id     = $this->make_object( 'work' );
		$images = array( $this->make_object( 'attachment' ), $this->make_object( 'attachment' ), $this->make_object( 'attachment' ) );
		$images = array_reverse( $images );

		$this->adapter->set( $field, $id, $images );

		$this->assertSame( $images, $this->adapter->get( $field, $id ) );
	}

	/**
	 * A single relation is stored and returned as a post ID.
	 */
	public function test_single_relation() {
		$field   = $this->field( 'work.creator' );
		$id      = $this->make_object( 'work' );
		$profile = $this->make_object( 'profile' );

		$this->adapter->set( $field, $id, $profile );

		$this->assertSame( $profile, $this->adapter->get( $field, $id ) );
	}

	/**
	 * Multiple relations keep their order.
	 */
	public function test_multiple_relations_keep_order() {
		$field   = $this->field( 'work.in_collections' );
		$id      = $this->make_object( 'work' );
		$related = array( $this->make_object( 'collection' ), $this->make_object( 'collection' ) );
		$related = array_reverse( $related );

		$this->adapter->set( $field, $id, $related );

		$this->assertSame( $related, $this->adapter->get( $field, $id ) );
	}

	/**
	 * Fields that were never saved read as their empty value.
	 *
	 * @dataProvider data_unsaved_fields
	 *
	 * @param string $key Field key.
	 */
	public function test_unsaved_field_is_empty( $key ) {
		$field = $this->field( $key );
		$id    = $this->make_object( $field->object );

		$this->assertSame( $field->empty_value(), $this->adapter->get( $field, $id ) );
	}

	/**
	 * Data for test_unsaved_field_is_empty.
	 *
	 * @return array[]
	 */
	public function data_unsaved_fields() {
		return array(
			'text'              => array( 'work.definitive_title' ),
			'year'              => array( 'work.year' ),
			'date'              => array( 'collection.opening_date' ),
			'boolean'           => array( 'collection.dead_link' ),
			'image'             => array( 'work.principal_image' ),
			'multiple images'   => array( 'work.additional_images' ),
			'relation'          => array( 'work.creator' ),
			'multiple relation' => array( 'work.in_collections' ),
			'repeatable url'    => array( 'profile.other_websites' ),
		);
	}

	/**
	 * Saving an empty value clears a field.
	 */
	public function test_saving_empty_value_clears_field() {
		$field = $this->field( 'work.year' );
		$id    = $this->make_object( 'work' );

		$this->adapter->set( $field, $id, 2020 );
		$this->adapter->set( $field, $id, null );

		$this->assertNull( $this->adapter->get( $field, $id ) );
	}

	/**
	 * Values belong to one post only.
	 */
	public function test_values_are_per_post() {
		$field = $this->field( 'work.definitive_title' );
		$one   = $this->make_object( 'work' );
		$two   = $this->make_object( 'work' );

		$this->adapter->set( $field, $one, 'One' );
		$this->adapter->set( $field, $two, 'Two' );

		$this->assertSame( 'One', $this->adapter->get( $field, $one ) );
		$this->assertSame( 'Two', $this->adapter->get( $field, $two ) );
	}

	/**
	 * Reading from a post that does not exist returns the empty value.
	 */
	public function test_missing_post_is_empty() {
		$field = $this->field( 'work.definitive_title' );

		$this->assertSame( '', $this->adapter->get( $field, PHP_INT_MAX ) );
		$this->assertFalse( $this->adapter->set( $field, PHP_INT_MAX, 'x' ) );
	}
}
