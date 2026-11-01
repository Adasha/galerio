<?php
/**
 * Pods status notice tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Pods\Status_Notice;

/**
 * The admin notice shown when Galerio's content can't be read.
 *
 * @covers \Galerio\Core\Pods\Status_Notice
 */
class Test_Status_Notice extends Galerio_TestCase {

	/**
	 * The notice's output for the current user.
	 *
	 * @return string
	 */
	private function notice() {
		ob_start();
		Status_Notice::show();
		return ob_get_clean();
	}

	/**
	 * With Pods and the package in place there's nothing to say.
	 */
	public function test_silent_when_set_up() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertSame( '', Status_Notice::problem() );
		$this->assertSame( '', $this->notice() );
	}

	/**
	 * A missing content type means the package hasn't been imported.
	 */
	public function test_missing_types() {
		global $wp_post_types;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$type = $wp_post_types['galerio_article'];
		unset( $wp_post_types['galerio_article'] );

		try {
			$this->assertSame( 'types', Status_Notice::problem() );
			$this->assertStringContainsString( 'wp galerio pods import', $this->notice() );
		} finally {
			$wp_post_types['galerio_article'] = $type; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the type this test removed.
		}
	}

	/**
	 * Only people who can install plugins see it.
	 */
	public function test_hidden_from_editors() {
		global $wp_post_types;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$type = $wp_post_types['galerio_article'];
		unset( $wp_post_types['galerio_article'] );

		try {
			$this->assertSame( '', $this->notice() );
		} finally {
			$wp_post_types['galerio_article'] = $type; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the type this test removed.
		}
	}

	/**
	 * A site on native data doesn't need Pods, so there's nothing to warn about.
	 */
	public function test_no_problem_on_native_data() {
		add_filter( 'galerio_native_data', '__return_true' );
		$problem = Galerio\Core\Pods\Status_Notice::problem();
		remove_filter( 'galerio_native_data', '__return_true' );

		$this->assertSame( '', $problem );
	}
}
