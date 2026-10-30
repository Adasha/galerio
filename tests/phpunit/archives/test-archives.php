<?php
/**
 * Archive filtering, sorting and page size tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Archives\Archives;

/**
 * Visitors narrow and sort Galerio's archives through the address, and sites
 * set each archive's page size.
 *
 * @covers \Galerio\Core\Archives\Archives
 */
class Test_Archives extends Galerio_TestCase {

	/**
	 * Works by title: Clay (ceramics, 2019), Brass (sculpture, 2023),
	 * Ash (ceramics, no year).
	 *
	 * @var int[]
	 */
	private $works = array();

	/**
	 * Make the works, with Ash the newest and Clay the oldest.
	 */
	public function set_up() {
		parent::set_up();

		$make = function ( $title, $medium, $year, $date ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'galerio_work',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_date'   => $date,
				)
			);
			wp_set_object_terms( $id, $medium, 'galerio_medium' );
			if ( $year ) {
				galerio_fields()->save( galerio_fields()->get( 'work.year' ), $id, $year );
			}
			return $id;
		};

		$this->works = array(
			'clay'  => $make( 'Clay', 'ceramics', 2019, '2026-01-01 10:00:00' ),
			'brass' => $make( 'Brass', 'sculpture', 2023, '2026-02-01 10:00:00' ),
			'ash'   => $make( 'Ash', 'ceramics', null, '2026-03-01 10:00:00' ),
		);
	}

	/**
	 * Leave the settings as they were.
	 */
	public function tear_down() {
		delete_option( Archives::PER_PAGE_OPTION );
		wp_dequeue_script( 'galerio-archive-filters' );
		wp_dequeue_style( 'galerio-archive-filters' );
		parent::tear_down();
	}

	/**
	 * Visit the works archive with arguments in the address.
	 *
	 * @param array $args Query arguments.
	 * @return string[] Titles shown, in order.
	 */
	private function visit( array $args = array() ) {
		$this->go_to( add_query_arg( $args, get_post_type_archive_link( 'galerio_work' ) ) );

		return wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_title' );
	}

	/**
	 * A work archive is filtered by its taxonomies, medium first.
	 */
	public function test_filters() {
		$filters = Archives::filters( 'galerio_work' );

		$this->assertSame( 'galerio_medium', reset( $filters ) );
		$this->assertSame( 'medium', key( $filters ) );
		$this->assertArrayHasKey( 'label', $filters );
		$this->assertArrayNotHasKey( 'role', $filters );
	}

	/**
	 * A filter in the address narrows the archive; unknown terms and keys
	 * are ignored.
	 */
	public function test_filtering() {
		$this->assertSame( array( 'Ash', 'Clay' ), $this->visit( array( 'medium' => 'ceramics' ) ) );
		$this->assertTrue( is_post_type_archive( 'galerio_work' ) );
		$this->assertCount( 3, $this->visit( array( 'medium' => 'no-such-medium' ) ) );
		$this->assertCount( 3, $this->visit( array( 'colour' => 'blue' ) ) );
	}

	/**
	 * The archive sorts by date either way, by title, and works by year,
	 * those without a year last.
	 */
	public function test_sorting() {
		$this->assertSame( array( 'Ash', 'Brass', 'Clay' ), $this->visit() );
		$this->assertSame( array( 'Clay', 'Brass', 'Ash' ), $this->visit( array( 'sort' => 'oldest' ) ) );
		$this->assertSame( array( 'Ash', 'Brass', 'Clay' ), $this->visit( array( 'sort' => 'title' ) ) );
		$this->assertSame( array( 'Brass', 'Clay', 'Ash' ), $this->visit( array( 'sort' => 'year' ) ) );
		$this->assertSame(
			array( 'Clay', 'Ash' ),
			$this->visit(
				array(
					'medium' => 'ceramics',
					'sort'   => 'year',
				)
			)
		);
	}

	/**
	 * Each archive can have its own page size.
	 */
	public function test_per_page() {
		update_option( Archives::PER_PAGE_OPTION, array( 'galerio_work' => 2 ) );

		$this->assertCount( 2, $this->visit() );
		$this->assertSame( 2, $GLOBALS['wp_query']->max_num_pages );

		$this->assertSame(
			array( 'galerio_work' => 12 ),
			Archives::sanitize_per_page(
				array(
					'galerio_work'    => '12',
					'post'            => '5',
					'galerio_profile' => '',
				)
			)
		);
		$this->assertSame( array( 'galerio_work' => 100 ), Archives::sanitize_per_page( array( 'galerio_work' => 500 ) ) );
	}

	/**
	 * Filtered and sorted archives aren't indexed.
	 */
	public function test_robots() {
		$this->visit();
		$this->assertArrayNotHasKey( 'noindex', Archives::robots( array() ) );

		$this->visit( array( 'sort' => 'title' ) );
		$this->assertTrue( Archives::robots( array() )['noindex'] );
	}

	/**
	 * The block shows a dropdown for each taxonomy with two or more terms
	 * this type uses, and the sort, with the current choices selected.
	 */
	public function test_block() {
		// A label only a profile has isn't offered on the works archive.
		$profile = self::factory()->post->create( array( 'post_type' => 'galerio_profile' ) );
		wp_set_object_terms( $profile, array( 'portraits', 'landscape' ), 'galerio_label' );

		$this->visit( array( 'medium' => 'ceramics' ) );
		$html = do_blocks( '<!-- wp:galerio/archive-filters /-->' );

		$this->assertStringContainsString( 'name="medium"', $html );
		$this->assertStringContainsString( '<option value="ceramics" selected=\'selected\'>', $html );
		$this->assertStringNotContainsString( 'name="label"', $html );
		$this->assertStringContainsString( 'name="sort"', $html );
		$this->assertStringContainsString( 'value="year"', $html );
		$this->assertStringContainsString( 'wp-block-galerio-archive-filters__clear', $html );
		$this->assertTrue( wp_script_is( 'galerio-archive-filters' ) );

		$this->go_to( get_permalink( $this->works['clay'] ) );
		$this->assertSame( '', do_blocks( '<!-- wp:galerio/archive-filters /-->' ), 'Hidden away from archives.' );
	}

	/**
	 * When filters leave nothing, the No results block says so.
	 */
	public function test_no_results() {
		wp_set_object_terms( $this->works['brass'], 'abstract', 'galerio_label' );
		wp_set_object_terms( $this->works['clay'], 'figurative', 'galerio_label' );
		$this->visit(
			array(
				'medium' => 'sculpture',
				'label'  => 'figurative',
			)
		);

		$html = do_blocks(
			'<!-- wp:query {"queryId":1,"query":{"inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->'
			. '<!-- wp:query-no-results --><!-- wp:paragraph --><p>No works yet.</p><!-- /wp:paragraph --><!-- /wp:query-no-results --></div><!-- /wp:query -->'
		);

		$this->assertStringContainsString( 'Nothing matches these filters.', $html );
		$this->assertStringNotContainsString( 'No works yet.', $html );
	}
}
