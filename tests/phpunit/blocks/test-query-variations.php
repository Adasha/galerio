<?php
/**
 * Query Loop variation tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Query_Variations;

/**
 * The exhibitions Query Loop's date filter.
 *
 * @covers \Galerio\Blocks\Query_Variations
 */
class Test_Query_Variations extends Galerio_TestCase {

	/**
	 * Collections by name.
	 *
	 * @var int[]
	 */
	private $collections = array();

	/**
	 * Create collections on each side of "today", 1 June 2023.
	 */
	public function set_up() {
		parent::set_up();

		add_filter(
			'galerio_exhibition_today',
			function () {
				return '2023-06-01';
			}
		);

		foreach ( array(
			'past'       => array( '2023-01-10', '2023-02-10' ),
			'older past' => array( '2022-01-10', '2022-02-10' ),
			'on now'     => array( '2023-05-19', '2023-06-16' ),
			'open-ended' => array( '2023-04-01', '' ),
			'upcoming'   => array( '2023-07-01', '2023-08-01' ),
			'later'      => array( '2023-09-01', '2023-10-01' ),
			'no dates'   => array( '', '' ),
		) as $name => $dates ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'galerio_collection',
					'post_title'  => $name,
					'post_status' => 'publish',
				)
			);
			galerio_fields()->save( galerio_fields()->get( 'collection.start_date' ), $id, $dates[0] );
			galerio_fields()->save( galerio_fields()->get( 'collection.end_date' ), $id, $dates[1] );
			$this->collections[ $name ] = $id;
		}
	}

	/**
	 * Remove the fixed "today".
	 */
	public function tear_down() {
		remove_all_filters( 'galerio_exhibition_today' );
		parent::tear_down();
	}

	/**
	 * The titles a status finds, in order.
	 *
	 * @param string $status Status.
	 * @return string[]
	 */
	private function titles( $status ) {
		$query = new WP_Query(
			array_merge(
				array(
					'posts_per_page' => 20,
					'post_status'    => 'publish',
				),
				Query_Variations::status_args( $status )
			)
		);

		return wp_list_pluck( $query->posts, 'post_title' );
	}

	/**
	 * Each filter finds the right collections, in date order.
	 */
	public function test_statuses() {
		$this->assertSame( array( 'open-ended', 'on now', 'upcoming', 'later' ), $this->titles( 'current-upcoming' ) );
		$this->assertSame( array( 'open-ended', 'on now' ), $this->titles( 'current' ) );
		$this->assertSame( array( 'upcoming', 'later' ), $this->titles( 'upcoming' ) );
		$this->assertSame( array( 'past', 'older past' ), $this->titles( 'past' ) );
	}

	/**
	 * A Query Loop with the filter shows only matching collections.
	 */
	public function test_query_loop() {
		$markup = '<!-- wp:query {"queryId":1,"query":{"perPage":10,"postType":"galerio_collection","inherit":false,"galerioStatus":"upcoming"},"namespace":"galerio/exhibitions"} -->'
			. '<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->';

		$html = do_blocks( $markup );

		$this->assertStringContainsString( '>upcoming<', $html );
		$this->assertStringContainsString( '>later<', $html );
		$this->assertStringNotContainsString( '>on now<', $html );
		$this->assertStringNotContainsString( '>past<', $html );
	}

	/**
	 * Other Query Loops are left alone.
	 */
	public function test_other_query_loops() {
		$markup = '<!-- wp:query {"queryId":2,"query":{"perPage":20,"postType":"galerio_collection","inherit":false}} -->'
			. '<div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->';

		$this->assertSame( 7, substr_count( do_blocks( $markup ), 'wp-block-post-title' ) );
	}
}
