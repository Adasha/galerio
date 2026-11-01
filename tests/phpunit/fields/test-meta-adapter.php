<?php
/**
 * Post meta adapter tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Meta_Adapter;
use Galerio\Core\Fields\Pods_Adapter;

/**
 * Runs the adapter contract against plain post meta, with Pods' meta handling
 * out of the way as it will be once a site no longer uses it.
 *
 * @covers \Galerio\Core\Fields\Meta_Adapter
 */
class Test_Meta_Adapter extends Galerio_Adapter_Contract_TestCase {

	/**
	 * Create the adapter under test.
	 *
	 * @return Meta_Adapter
	 */
	protected function make_adapter() {
		return new Meta_Adapter();
	}

	/**
	 * Keep Pods out of post meta.
	 */
	public function set_up() {
		parent::set_up();
		pods_no_conflict_on( 'post' );
		pods_no_conflict_on( 'media' );
	}

	/**
	 * Let Pods back in.
	 */
	public function tear_down() {
		pods_no_conflict_off( 'post' );
		pods_no_conflict_off( 'media' );
		parent::tear_down();
	}

	/**
	 * The stored rows of a field, straight from the table.
	 *
	 * @param int    $id  Post.
	 * @param string $key Meta key.
	 * @return string[]
	 */
	private function rows( $id, $key ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking exactly what's stored.
		return $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id", $id, $key ) );
	}

	/**
	 * Both adapters store the same rows, so nothing needs converting at the
	 * switch, in either direction.
	 *
	 * @dataProvider data_round_trip
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Value to save.
	 */
	public function test_stores_what_pods_stores( $key, $value ) {
		$field = $this->field( $key );
		$mine  = $this->make_object( $field->object );
		$pods  = $this->make_object( $field->object );

		$this->adapter->set( $field, $mine, $value );

		pods_no_conflict_off( 'post' );
		pods_no_conflict_off( 'media' );
		( new Pods_Adapter() )->set( $field, $pods, $value );
		pods_no_conflict_on( 'post' );
		pods_no_conflict_on( 'media' );

		$this->assertSame( $this->rows( $pods, $field->source ), $this->rows( $mine, $field->source ) );
	}

	/**
	 * Lists of IDs are stored the same way too, in the same order.
	 */
	public function test_stores_ids_as_pods_stores() {
		$field  = $this->field( 'work.images' );
		$mine   = $this->make_object( 'work' );
		$pods   = $this->make_object( 'work' );
		$images = array_reverse( array( $this->make_object( 'attachment' ), $this->make_object( 'attachment' ) ) );

		$this->adapter->set( $field, $mine, $images );

		pods_no_conflict_off( 'post' );
		pods_no_conflict_off( 'media' );
		( new Pods_Adapter() )->set( $field, $pods, $images );
		pods_no_conflict_on( 'post' );
		pods_no_conflict_on( 'media' );

		$this->assertSame( $this->rows( $pods, 'images' ), $this->rows( $mine, 'images' ) );
		$this->assertSame( array_map( 'strval', $images ), $this->rows( $mine, 'images' ) );
	}

	/**
	 * Quotes and backslashes in values survive being saved.
	 */
	public function test_slashes_survive() {
		$text = $this->field( 'work.definitive_title' );
		$link = $this->field( 'work.website' );
		$id   = $this->make_object( 'work' );

		$this->adapter->set( $text, $id, 'A "quoted" back\\slash' );
		$this->adapter->set(
			$link,
			$id,
			array(
				'url'     => 'https://example.org/a',
				'label'   => 'The "best" one',
				'offline' => false,
			)
		);

		$this->assertSame( 'A "quoted" back\\slash', $this->adapter->get( $text, $id ) );
		$this->assertSame( 'The "best" one', $this->adapter->get( $link, $id )['label'] );
	}

	/**
	 * Saving a relation updates the other side, and unlinking removes it.
	 */
	public function test_relation_updates_other_side() {
		$collection = $this->make_object( 'collection' );
		$one        = $this->make_object( 'profile' );
		$two        = $this->make_object( 'profile' );

		$this->adapter->set( $this->field( 'collection.profiles' ), $collection, array( $one, $two ) );
		$this->assertSame( array( $collection ), $this->adapter->get( $this->field( 'profile.collections' ), $one ) );
		$this->assertSame( array( $collection ), $this->adapter->get( $this->field( 'profile.collections' ), $two ) );

		$this->adapter->set( $this->field( 'collection.profiles' ), $collection, array( $two ) );
		$this->assertSame( array(), $this->adapter->get( $this->field( 'profile.collections' ), $one ) );
		$this->assertSame( array( $collection ), $this->adapter->get( $this->field( 'profile.collections' ), $two ) );
	}

	/**
	 * Adding to a list keeps the other item's own order.
	 */
	public function test_relation_keeps_other_sides_order() {
		$profile = $this->make_object( 'profile' );
		$first   = $this->make_object( 'collection' );
		$second  = $this->make_object( 'collection' );
		$third   = $this->make_object( 'collection' );

		$this->adapter->set( $this->field( 'profile.collections' ), $profile, array( $second, $first ) );
		$this->adapter->set( $this->field( 'collection.profiles' ), $third, array( $profile ) );

		$this->assertSame( array( $second, $first, $third ), $this->adapter->get( $this->field( 'profile.collections' ), $profile ) );
	}

	/**
	 * A work has one creator, so giving it to another profile takes it from the first.
	 */
	public function test_single_side_moves() {
		$work  = $this->make_object( 'work' );
		$first = $this->make_object( 'profile' );
		$other = $this->make_object( 'profile' );

		$this->adapter->set( $this->field( 'work.creator' ), $work, $first );
		$this->assertSame( array( $work ), $this->adapter->get( $this->field( 'profile.works' ), $first ) );

		$this->adapter->set( $this->field( 'profile.works' ), $other, array( $work ) );

		$this->assertSame( $other, $this->adapter->get( $this->field( 'work.creator' ), $work ) );
		$this->assertSame( array(), $this->adapter->get( $this->field( 'profile.works' ), $first ) );
	}

	/**
	 * A related-items list links both ways within one field, and not to itself.
	 */
	public function test_symmetric_relation() {
		$one = $this->make_object( 'work' );
		$two = $this->make_object( 'work' );

		$this->adapter->set( $this->field( 'work.related_works' ), $one, array( $two, $one ) );

		$this->assertSame( array( $one ), $this->adapter->get( $this->field( 'work.related_works' ), $two ) );
	}

	/**
	 * Unpublished items stay linked, on both sides.
	 */
	public function test_unpublished_items_stay_linked() {
		$profile = $this->make_object( 'profile' );
		$draft   = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_collection',
				'post_status' => 'draft',
			)
		);

		$this->adapter->set( $this->field( 'profile.collections' ), $profile, array( $draft ) );

		$this->assertSame( array( $draft ), $this->adapter->get( $this->field( 'profile.collections' ), $profile ) );
		$this->assertSame( array( $profile ), $this->adapter->get( $this->field( 'collection.profiles' ), $draft ) );
	}

	/**
	 * A one-way relation leaves the other item alone.
	 */
	public function test_one_way_relation() {
		$collection = $this->make_object( 'collection' );
		$profile    = $this->make_object( 'profile' );

		$this->adapter->set( $this->field( 'collection.curators' ), $collection, array( $profile ) );

		$this->assertSame( array( $profile ), $this->adapter->get( $this->field( 'collection.curators' ), $collection ) );
		$this->assertSame( array(), $this->adapter->get( $this->field( 'profile.collections' ), $profile ) );
	}

	/**
	 * A deleted item leaves the lists of what it was linked to.
	 */
	public function test_forget() {
		$profile = $this->make_object( 'profile' );
		$gone    = $this->make_object( 'collection' );
		$kept    = $this->make_object( 'collection' );

		$this->adapter->set( $this->field( 'profile.collections' ), $profile, array( $gone, $kept ) );

		Galerio\Core\Fields\Relations::forget( $gone );

		$this->assertSame( array( $kept ), $this->adapter->get( $this->field( 'profile.collections' ), $profile ) );
	}
}
