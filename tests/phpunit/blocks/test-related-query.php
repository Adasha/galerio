<?php
/**
 * Related items in Query Loops.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Related_Query;

/**
 * A Query Loop's Related items setting.
 *
 * @covers \Galerio\Blocks\Related_Query
 */
class Test_Related_Query extends Galerio_TestCase {

	/**
	 * Creator profile, with works saved as Tide, Bay, then Cliff (a draft).
	 *
	 * @var int
	 */
	private $profile;

	/**
	 * A profile with no works.
	 *
	 * @var int
	 */
	private $loner;

	/**
	 * Create the profiles and works, and a work related to no one.
	 */
	public function set_up() {
		parent::set_up();

		$fields = galerio_fields();
		$make   = function ( $type, $title, $status = 'publish' ) {
			return self::factory()->post->create(
				array(
					'post_type'   => $type,
					'post_title'  => $title,
					'post_status' => $status,
				)
			);
		};

		$this->profile = $make( 'galerio_profile', 'Ana Morgan' );
		$this->loner   = $make( 'galerio_profile', 'Tomos Price' );
		$works         = array( $make( 'galerio_work', 'Tide' ), $make( 'galerio_work', 'Bay' ), $make( 'galerio_work', 'Cliff', 'draft' ) );
		$make( 'galerio_work', 'Unrelated' );

		$fields->save( $fields->get( 'profile.works' ), $this->profile, $works );
	}

	/**
	 * Render a Query Loop of works on a profile's page.
	 *
	 * @param int    $profile Profile shown.
	 * @param array  $query   Extra query settings.
	 * @param string $after   Blocks after the Post Template, e.g. No results.
	 * @return string
	 */
	private function render( $profile, $query = array(), $after = '' ) {
		$this->go_to( get_permalink( $profile ) );
		the_post();

		$attrs = array(
			'queryId' => 1,
			'query'   => array_merge(
				array(
					'perPage'  => 10,
					'postType' => 'galerio_work',
					'order'    => 'desc',
					'orderBy'  => 'date',
					'inherit'  => false,
				),
				$query
			),
		);

		return do_blocks(
			'<!-- wp:query ' . wp_json_encode( $attrs ) . ' --><div class="wp-block-query"><!-- wp:heading --><h2 class="wp-block-heading">Works</h2><!-- /wp:heading -->'
			. '<!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->' . $after . '</div><!-- /wp:query -->'
		);
	}

	/**
	 * Titles in the order shown.
	 *
	 * @param string $html Rendered loop.
	 * @return string[]
	 */
	private function titles( $html ) {
		preg_match_all( '/wp-block-post-title">([^<]+)</', $html, $matches );
		return $matches[1];
	}

	/**
	 * The related items show as arranged on the item, without drafts or
	 * unrelated items.
	 */
	public function test_shows_related_items_in_saved_order() {
		$html = $this->render( $this->profile, array( 'galerioRelated' => 'profile.works' ) );

		$this->assertSame( array( 'Tide', 'Bay' ), $this->titles( $html ) );
		$this->assertStringContainsString( '<h2 class="wp-block-heading">Works</h2>', $html );
	}

	/**
	 * "query" keeps the Query Loop's own order.
	 */
	public function test_can_use_the_query_order() {
		$html = $this->render(
			$this->profile,
			array(
				'galerioRelated'      => 'profile.works',
				'galerioRelatedOrder' => 'query',
				'orderBy'             => 'title',
				'order'               => 'asc',
			)
		);

		$this->assertSame( array( 'Bay', 'Tide' ), $this->titles( $html ) );
	}

	/**
	 * "random" orders at random.
	 */
	public function test_random_order() {
		$this->go_to( get_permalink( $this->profile ) );
		the_post();

		$block = new WP_Block(
			array(
				'blockName' => 'core/post-template',
				'attrs'     => array(),
			),
			array(
				'query' => array(
					'galerioRelated'      => 'profile.works',
					'galerioRelatedOrder' => 'random',
				),
			)
		);

		$this->assertSame( 'rand', Related_Query::query_vars( array(), $block )['orderby'] );
	}

	/**
	 * With nothing related, the whole loop is left out, heading and all.
	 */
	public function test_hidden_when_nothing_is_related() {
		$this->assertSame( '', $this->render( $this->loner, array( 'galerioRelated' => 'profile.works' ) ) );
	}

	/**
	 * Still left out when another filter at the default priority returns
	 * null for every block, as the Gutenberg plugin's preset styles do.
	 */
	public function test_hidden_despite_a_filter_returning_null() {
		$null = function () {
			return null;
		};
		add_filter( 'pre_render_block', $null, 10, 2 );

		$html = $this->render( $this->loner, array( 'galerioRelated' => 'profile.works' ) );

		remove_filter( 'pre_render_block', $null, 10 );
		$this->assertSame( '', $html );
	}

	/**
	 * A No results block with something in it shows instead.
	 */
	public function test_no_results_message_shows() {
		$html = $this->render(
			$this->loner,
			array( 'galerioRelated' => 'profile.works' ),
			'<!-- wp:query-no-results --><!-- wp:paragraph --><p>No works yet.</p><!-- /wp:paragraph --><!-- /wp:query-no-results -->'
		);

		$this->assertStringContainsString( 'No works yet.', $html );
		$this->assertSame( array(), $this->titles( $html ) );
	}

	/**
	 * Loops without the setting are untouched.
	 */
	public function test_normal_loops_are_untouched() {
		$titles = $this->titles( $this->render( $this->profile ) );

		$this->assertContains( 'Unrelated', $titles );
	}
}
