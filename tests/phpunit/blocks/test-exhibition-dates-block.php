<?php
/**
 * Exhibition Dates block tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Exhibition_Dates_Block;
use Galerio\Blocks\Exhibitions;

/**
 * A collection's dates and status.
 *
 * @covers \Galerio\Blocks\Exhibition_Dates_Block
 * @covers \Galerio\Blocks\Exhibitions
 */
class Test_Exhibition_Dates_Block extends Galerio_TestCase {

	/**
	 * Collection used by most tests.
	 *
	 * @var int
	 */
	private $collection;

	/**
	 * Create a collection open from 19 May to 16 June 2023, and fix "today".
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'date_format', 'F j, Y' );
		$this->collection = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_collection',
				'post_status' => 'publish',
			)
		);
		$this->set_dates( '2023-05-19', '2023-06-16' );
		$this->set_today( '2023-06-01' );
	}

	/**
	 * Remove the fixed "today".
	 */
	public function tear_down() {
		remove_all_filters( 'galerio_exhibition_today' );
		parent::tear_down();
	}

	/**
	 * Set the collection's dates.
	 *
	 * @param string $start Opening date, or empty.
	 * @param string $end   Closing date, or empty.
	 */
	private function set_dates( $start, $end ) {
		galerio_fields()->save( galerio_fields()->get( 'collection.start_date' ), $this->collection, $start );
		galerio_fields()->save( galerio_fields()->get( 'collection.end_date' ), $this->collection, $end );
	}

	/**
	 * Pretend today is a given date.
	 *
	 * @param string $date "Y-m-d".
	 */
	private function set_today( $date ) {
		remove_all_filters( 'galerio_exhibition_today' );
		add_filter(
			'galerio_exhibition_today',
			function () use ( $date ) {
				return $date;
			}
		);
	}

	/**
	 * Render the block in the collection's context.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function render( $attributes = array() ) {
		$this->go_to( get_permalink( $this->collection ) );
		the_post();

		return do_blocks( '<!-- wp:galerio/exhibition-dates ' . wp_json_encode( (object) $attributes ) . ' /-->' );
	}

	/**
	 * Both dates in the site's format, as <time> elements.
	 */
	public function test_site_format() {
		$this->assertSame(
			'<time datetime="2023-05-19">May 19, 2023</time> – <time datetime="2023-06-16">June 16, 2023</time>',
			Exhibition_Dates_Block::text( '2023-05-19', '2023-06-16', 'site' )
		);
	}

	/**
	 * A short range leaves out what the two dates share.
	 *
	 * @dataProvider data_short_format
	 *
	 * @param string $start    Opening date.
	 * @param string $end      Closing date.
	 * @param string $expected Text without markup.
	 */
	public function test_short_format( $start, $end, $expected ) {
		$this->assertSame( $expected, wp_strip_all_tags( Exhibition_Dates_Block::text( $start, $end, 'short' ) ) );
	}

	/**
	 * Data for test_short_format.
	 *
	 * @return array[]
	 */
	public function data_short_format() {
		return array(
			'same month'     => array( '2023-05-19', '2023-05-23', '19–23 May 2023' ),
			'same year'      => array( '2023-05-19', '2023-06-16', '19 May – 16 June 2023' ),
			'different year' => array( '2023-12-19', '2024-01-05', '19 December 2023 – 5 January 2024' ),
		);
	}

	/**
	 * One date: a single day, open-ended, or closing only.
	 */
	public function test_single_dates() {
		$this->assertSame( 'May 19, 2023', wp_strip_all_tags( Exhibition_Dates_Block::text( '2023-05-19', '2023-05-19', 'site' ) ) );
		$this->assertSame( 'From May 19, 2023', wp_strip_all_tags( Exhibition_Dates_Block::text( '2023-05-19', '', 'site' ) ) );
		$this->assertSame( 'Until June 16, 2023', wp_strip_all_tags( Exhibition_Dates_Block::text( '', '2023-06-16', 'site' ) ) );
		$this->assertSame( '', Exhibition_Dates_Block::text( '', '', 'site' ) );
	}

	/**
	 * Upcoming before the opening, on now through the closing day, past after.
	 */
	public function test_status() {
		foreach ( array(
			'2023-05-18' => 'upcoming',
			'2023-05-19' => 'current',
			'2023-06-16' => 'current',
			'2023-06-17' => 'past',
		) as $today => $status ) {
			$this->set_today( $today );
			$this->assertSame( $status, Exhibitions::status( $this->collection ), $today );
		}

		$this->set_dates( '2023-05-19', '' );
		$this->set_today( '2030-01-01' );
		$this->assertSame( 'current', Exhibitions::status( $this->collection ), 'Open-ended exhibitions stay on.' );

		$this->set_dates( '', '' );
		$this->assertSame( '', Exhibitions::status( $this->collection ) );
	}

	/**
	 * The block shows the status label when asked, and nothing without dates.
	 */
	public function test_render() {
		$html = $this->render( array( 'showStatus' => true ) );
		$this->assertStringContainsString( 'class="is-current wp-block-galerio-exhibition-dates"', $html );
		$this->assertStringContainsString( '<span class="wp-block-galerio-exhibition-dates__status is-current">On now</span>', $html );

		$this->assertStringNotContainsString( '__status', $this->render() );

		$this->set_dates( '', '' );
		$this->assertSame( '', trim( $this->render() ) );
	}
}
