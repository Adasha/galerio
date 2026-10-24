<?php
/**
 * Related Items block tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Related_Items_Block;

/**
 * Cards for the items related to the current one.
 *
 * @covers \Galerio\Blocks\Related_Items_Block
 */
class Test_Related_Items_Block extends Galerio_TestCase {

	/**
	 * Creator profile.
	 *
	 * @var int
	 */
	private $profile;

	/**
	 * The creator's works, in saved order: Tide (2019), Bay (2021), and Cliff (unpublished).
	 *
	 * @var int[]
	 */
	private $works = array();

	/**
	 * Create a profile with three works.
	 */
	public function set_up() {
		parent::set_up();

		$fields        = galerio_fields();
		$this->profile = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_profile',
				'post_title'  => 'Ana Morgan',
				'post_status' => 'publish',
			)
		);

		foreach ( array(
			array( 'Tide', 2019, 'publish' ),
			array( 'Bay', 2021, 'publish' ),
			array( 'Cliff', 2022, 'draft' ),
		) as $work ) {
			$id = self::factory()->post->create(
				array(
					'post_type'   => 'galerio_work',
					'post_title'  => $work[0],
					'post_status' => $work[2],
				)
			);
			$fields->save( $fields->get( 'work.year' ), $id, $work[1] );
			$fields->save( $fields->get( 'work.creator' ), $id, $this->profile );
			$this->works[] = $id;
		}

		$fields->save( $fields->get( 'profile.works' ), $this->profile, $this->works );
	}

	/**
	 * Render the block in the profile's context.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private function render( $attributes = array() ) {
		$this->go_to( get_permalink( $this->profile ) );
		the_post();

		return do_blocks( '<!-- wp:galerio/related-items ' . wp_json_encode( (object) $attributes ) . ' /-->' );
	}

	/**
	 * The block registers from its compiled metadata.
	 */
	public function test_block_is_registered() {
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'galerio/related-items' ), 'Run `npm run build` before the tests.' );
	}

	/**
	 * Published related items, in saved order, under the relationship's name.
	 */
	public function test_cards() {
		$html = $this->render( array( 'field' => 'profile.works' ) );

		$this->assertStringContainsString( '<h2 class="wp-block-galerio-related-items__heading">Works</h2>', $html );
		$this->assertSame( 2, substr_count( $html, 'class="wp-block-galerio-related-items__item"' ) );
		$this->assertLessThan( strpos( $html, '>Bay<' ), strpos( $html, '>Tide<' ) );
		$this->assertStringNotContainsString( 'Cliff', $html, 'Unpublished items are left out.' );
		$this->assertStringContainsString( 'href="' . get_permalink( $this->works[0] ) . '"', $html );
	}

	/**
	 * Without a chosen field, the item's first relationship is used.
	 */
	public function test_relation_for() {
		$this->assertSame( 'galerio_work', Related_Items_Block::relation_for( 'profile.works', 'galerio_profile' )['target'] );
		$this->assertSame( 'galerio_profile', Related_Items_Block::relation_for( '', 'galerio_profile' )['postType'] );
		$this->assertNull( Related_Items_Block::relation_for( 'profile.works', 'galerio_work' ), 'A field of another type isn’t used.' );
	}

	/**
	 * A detail line under each title.
	 */
	public function test_detail() {
		$html = $this->render(
			array(
				'field'  => 'profile.works',
				'detail' => 'work.year',
			)
		);
		$this->assertStringContainsString( '<p class="wp-block-galerio-related-items__detail">2019</p>', $html );

		$html = $this->render(
			array(
				'field'  => 'profile.works',
				'detail' => 'work.creator',
			)
		);
		$this->assertStringContainsString( '<p class="wp-block-galerio-related-items__detail">Ana Morgan</p>', $html );

		$html = $this->render(
			array(
				'field'  => 'profile.works',
				'detail' => 'work.owner',
			)
		);
		$this->assertStringNotContainsString( '__detail', $html, 'Private fields never show.' );
	}

	/**
	 * Order, limit, heading text and level.
	 */
	public function test_options() {
		$html = $this->render(
			array(
				'field'        => 'profile.works',
				'order'        => 'title',
				'limit'        => 1,
				'heading'      => 'More {works}',
				'headingLevel' => 3,
			)
		);

		$this->assertStringContainsString( '<h3 class="wp-block-galerio-related-items__heading">More works</h3>', $html );
		$this->assertStringContainsString( '>Bay<', $html );
		$this->assertStringNotContainsString( '>Tide<', $html );

		$this->assertStringNotContainsString( '__heading', $this->render( array( 'heading' => '' ) ) );
	}

	/**
	 * Nothing at all, heading included, when nothing is related.
	 */
	public function test_empty() {
		galerio_fields()->save( galerio_fields()->get( 'profile.works' ), $this->profile, array() );

		$this->assertSame( '', trim( $this->render( array( 'field' => 'profile.works' ) ) ) );
	}
}
