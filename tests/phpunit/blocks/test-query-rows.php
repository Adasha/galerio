<?php
/**
 * Query Loop rows tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Blocks\Query_Rows;

/**
 * A Query Loop with a row limit is marked for the script, which is loaded.
 *
 * @covers \Galerio\Blocks\Query_Rows
 */
class Test_Query_Rows extends Galerio_TestCase {

	/**
	 * Leave the scripts as they were.
	 */
	public function tear_down() {
		wp_dequeue_script( 'galerio-query-rows-view' );
		wp_dequeue_style( 'galerio-query-rows' );
		parent::tear_down();
	}

	/**
	 * A limited loop gets the class, the rows and the script.
	 */
	public function test_marks_limited_loop() {
		$html = Query_Rows::render(
			'<div class="wp-block-query"><ul></ul></div>',
			array(
				'blockName' => 'core/query',
				'attrs'     => array( 'galerioRows' => 2 ),
			)
		);

		$this->assertStringContainsString( 'has-galerio-rows', $html );
		$this->assertStringContainsString( 'data-galerio-rows="2"', $html );
		$this->assertTrue( wp_script_is( 'galerio-query-rows-view' ) );
	}

	/**
	 * A loop without a limit is left alone, and more rows than the panel
	 * offers are capped.
	 */
	public function test_leaves_others() {
		$html = '<div class="wp-block-query"><ul></ul></div>';
		$this->assertSame( $html, Query_Rows::render( $html, array( 'attrs' => array() ) ) );
		$this->assertFalse( wp_script_is( 'galerio-query-rows-view' ) );

		$this->assertStringContainsString( 'data-galerio-rows="10"', Query_Rows::render( $html, array( 'attrs' => array( 'galerioRows' => 50 ) ) ) );
	}

	/**
	 * The Query Loop has the attribute on the server.
	 */
	public function test_attributes() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'core/query' )->attributes;
		$this->assertSame( 0, $attributes['galerioRows']['default'] );
	}
}
