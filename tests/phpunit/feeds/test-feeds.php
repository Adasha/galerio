<?php
/**
 * Feed tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Feeds\Feeds;

/**
 * Feeds carry images, real creators, key details and clean content, and the
 * main feed can include articles.
 *
 * @covers \Galerio\Core\Feeds\Feeds
 */
class Test_Feeds extends Galerio_TestCase {

	/**
	 * Leave the setting as it was.
	 */
	public function tear_down() {
		delete_option( Feeds::ARTICLES_OPTION );
		parent::tear_down();
	}

	/**
	 * A work by a profile, in a given year.
	 *
	 * @return int[] Work and creator.
	 */
	private function work() {
		$creator = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_profile',
				'post_title' => 'Ana Morgan',
			)
		);
		$work    = self::factory()->post->create(
			array(
				'post_type'  => 'galerio_work',
				'post_title' => 'Low Tide',
			)
		);
		galerio_fields()->save( galerio_fields()->get( 'work.creator' ), $work, $creator );
		galerio_fields()->save( galerio_fields()->get( 'work.year' ), $work, 2019 );

		return array( $work, $creator );
	}

	/**
	 * Load a feed and step its loop to an item, as the feed template does.
	 *
	 * @param string $url  Feed.
	 * @param int    $post Item.
	 */
	private function in_feed( $url, $post ) {
		$this->go_to( $url );
		while ( have_posts() ) {
			the_post();
			if ( get_the_ID() === $post ) {
				return;
			}
		}
		$this->fail( 'The item isn’t in the feed.' );
	}

	/**
	 * Interactive markup is left out of feed content.
	 */
	public function test_clean() {
		$html = '<figure data-wp-interactive="core/image" class="wp-lightbox-container"><img data-wp-on--click="actions.showLightbox" src="a.jpg" alt="" /><button
			class="lightbox-trigger"
			type="button"
		><svg></svg></button><figcaption>Detail</figcaption></figure>';

		$clean = Feeds::clean( $html );

		$this->assertStringNotContainsString( 'data-wp-', $clean );
		$this->assertStringNotContainsString( '<button', $clean );
		$this->assertStringContainsString( '<img', $clean );
		$this->assertStringContainsString( 'Detail', $clean );
	}

	/**
	 * A work's details lead its content, and its creator is its dc:creator.
	 */
	public function test_work_item() {
		list( $work ) = $this->work();

		$this->in_feed( get_post_type_archive_feed_link( 'galerio_work' ), $work );
		$this->assertTrue( is_feed() );

		$details = Feeds::details( get_post( $work ) );
		$this->assertStringContainsString( 'Ana Morgan', $details );
		$this->assertStringContainsString( '2019', $details );
		$this->assertSame( 'Ana Morgan', apply_filters( 'the_author', 'someone' ) );
	}

	/**
	 * An item with nobody named is by the site, not the user who posted it.
	 */
	public function test_author_falls_back_to_site() {
		$collection = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );

		$this->in_feed( get_post_type_archive_feed_link( 'galerio_collection' ), $collection );

		$this->assertSame( get_bloginfo( 'name' ), apply_filters( 'the_author', 'someone' ) );
	}

	/**
	 * The featured image is the item's media:content.
	 */
	public function test_media() {
		list( $work ) = $this->work();
		$image        = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $work );
		set_post_thumbnail( $work, $image );

		$this->in_feed( get_post_type_archive_feed_link( 'galerio_work' ), $work );
		ob_start();
		Feeds::media();
		$xml = ob_get_clean();

		$this->assertStringContainsString( '<media:content url="', $xml );
		$this->assertStringContainsString( 'medium="image"', $xml );
		$this->assertStringContainsString( '<media:thumbnail', $xml );
	}

	/**
	 * The main feed lists articles only when the setting is on.
	 */
	public function test_articles_in_main_feed() {
		self::factory()->post->create( array( 'post_type' => 'galerio_article' ) );
		self::factory()->post->create();

		$this->go_to( get_feed_link() );
		$this->assertSame( array( 'post' ), array_values( array_unique( wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_type' ) ) ) );

		update_option( Feeds::ARTICLES_OPTION, true );
		$this->go_to( get_feed_link() );
		$types = wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_type' );
		$this->assertContains( 'galerio_article', $types );
		$this->assertContains( 'post', $types );

		// Other feeds are left alone.
		$this->go_to( get_post_type_archive_feed_link( 'galerio_work' ) );
		$this->assertSame( 'galerio_work', $GLOBALS['wp_query']->get( 'post_type' ) );
	}

	/**
	 * The exhibitions feed is in order of opening date, latest first, with
	 * undated ones last.
	 */
	public function test_exhibitions_order() {
		$start   = galerio_fields()->get( 'collection.start_date' );
		$early   = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		$late    = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		$undated = self::factory()->post->create( array( 'post_type' => 'galerio_collection' ) );
		galerio_fields()->save( $start, $early, '2020-01-01' );
		galerio_fields()->save( $start, $late, '2025-06-01' );

		$this->go_to( get_post_type_archive_feed_link( 'galerio_collection' ) );

		$this->assertSame( array( $late, $early, $undated ), array_values( array_intersect( wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), array( $early, $late, $undated ) ) ) );
	}
}
