<?php
/**
 * Stored field check tests.
 *
 * @package Galerio\Tests
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- The tests change the stored rows behind Pods' back.

use Galerio\Core\Fields\Check;
use Galerio\Core\Fields\Pods_Adapter;

/**
 * The check finds what would stop a site's data being read without Pods.
 *
 * @covers \Galerio\Core\Fields\Check
 */
class Test_Check extends Galerio_TestCase {

	/**
	 * Make an item.
	 *
	 * @param string $object_name Object name.
	 * @param string $status      Post status.
	 * @return int
	 */
	private function item( $object_name, $status = 'publish' ) {
		return self::factory()->post->create(
			array(
				'post_type'   => 'galerio_' . $object_name,
				'post_status' => $status,
			)
		);
	}

	/**
	 * Save a field.
	 *
	 * @param string $key   Field key.
	 * @param int    $id    Item.
	 * @param mixed  $value Value.
	 */
	private function save( $key, $id, $value ) {
		$this->assertTrue( galerio_fields()->save( galerio_fields()->get( $key ), $id, $value ) );
	}

	/**
	 * Run the check and return the results of one kind.
	 *
	 * @param string $check Which check.
	 * @return array[]
	 */
	private function results( $check ) {
		return wp_list_filter( ( new Check( galerio_fields(), new Pods_Adapter() ) )->run(), array( 'check' => $check ) );
	}

	/**
	 * A list saved through Pods reads the same from what's stored, and both sides agree.
	 */
	public function test_saved_values_pass() {
		$work       = $this->item( 'work' );
		$collection = $this->item( 'collection' );
		$profiles   = array( $this->item( 'profile' ), $this->item( 'profile' ), $this->item( 'profile' ) );

		$this->save( 'work.definitive_title', $work, 'A title' );
		$this->save( 'work.year', $work, 1984 );
		$this->save( 'work.creator', $work, $profiles[0] );
		$this->save( 'collection.profiles', $collection, array( $profiles[2], $profiles[0], $profiles[1] ) );

		$this->assertSame( array(), $this->results( 'reads differ' ) );
		$this->assertSame( array(), $this->results( 'one-way link' ) );
	}

	/**
	 * A stored value that differs from what Pods reads is a problem.
	 */
	public function test_reads_differ() {
		$collection = $this->item( 'collection' );
		$profiles   = array( $this->item( 'profile' ), $this->item( 'profile' ) );

		$this->save( 'collection.profiles', $collection, $profiles );

		// Directly in the table, as Pods would otherwise handle the write.
		global $wpdb;
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'post_id'    => $collection,
				'meta_key'   => 'profiles', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $profiles[0], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		$items = wp_list_pluck( $this->results( 'reads differ' ), 'item' );

		$this->assertContains( "collection.profiles #$collection", $items );
	}

	/**
	 * Links to unpublished items are a note, not a difference.
	 */
	public function test_unpublished_links_are_notes() {
		$profile = $this->item( 'profile' );
		$draft   = $this->item( 'collection', 'draft' );

		$this->save( 'profile.collections', $profile, array( $draft ) );

		$this->assertSame( array(), $this->results( 'reads differ' ) );
		$this->assertContains( 'profile.collections', wp_list_pluck( $this->results( 'unpublished links' ), 'item' ) );
	}

	/**
	 * A list the other side doesn't return is a problem.
	 */
	public function test_one_way_link() {
		$work    = $this->item( 'work' );
		$profile = $this->item( 'profile' );

		$this->save( 'work.creator', $work, $profile );

		global $wpdb;
		$wpdb->delete(
			$wpdb->postmeta,
			array(
				'post_id'  => $profile,
				'meta_key' => 'works', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$this->assertContains( "work.creator #$work", wp_list_pluck( $this->results( 'one-way link' ), 'item' ) );
	}

	/**
	 * Pods shortcodes and blocks in content are problems.
	 */
	public function test_pods_in_content() {
		$shortcode = self::factory()->post->create( array( 'post_content' => '[pods name="x"]' ) );
		$block     = self::factory()->post->create( array( 'post_content' => '<!-- wp:pods/pods-block-field /-->' ) );
		self::factory()->post->create( array( 'post_content' => 'A [podcast] page.' ) );

		$items = wp_list_pluck( $this->results( 'pods in content' ), 'item' );

		$this->assertContains( "post #$shortcode", $items );
		$this->assertContains( "post #$block", $items );
		$this->assertCount( 2, $items );
	}

	/**
	 * Values in the package's fields that Galerio doesn't define are reported,
	 * and those fields are quiet while they're empty.
	 */
	public function test_data_left_behind() {
		$this->assertSame( array(), $this->results( 'data left behind' ) );

		$user = self::factory()->user->create();
		$file = self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);

		global $wpdb;
		// phpcs:disable WordPress.DB.SlowDBQuery
		$wpdb->insert(
			$wpdb->usermeta,
			array(
				'user_id'    => $user,
				'meta_key'   => 'profile',
				'meta_value' => '5',
			)
		);
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $file,
				'meta_key'   => 'intent',
				'meta_value' => '7',
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery
		wp_insert_term( 'Artwork', 'media_intent' );

		$items = wp_list_pluck( $this->results( 'data left behind' ), 'item' );

		$this->assertContains( 'user.profile', $items );
		$this->assertContains( 'attachment.intent', $items );
		$this->assertContains( 'media_intent', $items );
	}

	/**
	 * Without an adapter, reads aren't compared.
	 */
	public function test_without_adapter() {
		$results = ( new Check( galerio_fields() ) )->run();

		$this->assertSame( array(), wp_list_filter( $results, array( 'check' => 'reads differ' ) ) );
	}
}
