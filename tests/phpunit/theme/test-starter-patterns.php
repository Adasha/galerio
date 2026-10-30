<?php
/**
 * Starter pattern tests.
 *
 * @package Galerio\Tests
 */

/**
 * No Galerio pattern is offered for every new page and post.
 *
 * A pattern with the `core/post-content` block type and no post types is
 * offered in "Choose a pattern" whenever a page or post is made. The work
 * details pattern was, by mistake.
 *
 * @coversNothing
 */
class Test_Starter_Patterns extends Galerio_TestCase {

	/**
	 * The theme's patterns that would be starters are limited to post types.
	 */
	public function test_no_starter_for_everything() {
		$starters = array();
		foreach ( glob( dirname( __DIR__, 3 ) . '/themes/*/patterns/*.php' ) as $file ) {
			$header = get_file_data(
				$file,
				array(
					'blockTypes' => 'Block Types',
					'postTypes'  => 'Post Types',
				)
			);
			if ( false !== strpos( $header['blockTypes'], 'core/post-content' ) && '' === trim( $header['postTypes'] ) ) {
				$starters[] = basename( dirname( $file, 2 ) ) . '/' . basename( $file );
			}
		}

		$this->assertSame( array(), $starters );
	}
}
