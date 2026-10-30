<?php
/**
 * Edit screen tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Edit_Screen;

/**
 * The meta box pane is named after the content type, and accolades start
 * as one line.
 *
 * @covers \Galerio\Core\Fields\Edit_Screen
 */
class Test_Edit_Screen extends Galerio_TestCase {

	/**
	 * Leave the screen and scripts as they were.
	 */
	public function tear_down() {
		wp_dequeue_script( 'galerio-edit-screen' );
		wp_deregister_script( 'galerio-edit-screen' );
		wp_dequeue_style( 'galerio-edit-screen' );
		wp_deregister_style( 'galerio-edit-screen' );
		set_current_screen( 'front' );
		parent::tear_down();
	}

	/**
	 * On a work's edit screen, "Meta Boxes" becomes "<the site's name for a work> settings".
	 */
	public function test_pane_label() {
		set_current_screen( 'galerio_work' );
		Edit_Screen::pane_label();

		$name   = get_post_type_object( 'galerio_work' )->labels->singular_name;
		$script = implode( '', wp_scripts()->get_data( 'galerio-edit-screen', 'after' ) );

		$this->assertTrue( wp_script_is( 'galerio-edit-screen' ) );
		$this->assertStringContainsString( '"Meta Boxes" === text', $script );
		$this->assertStringContainsString( wp_json_encode( $name . ' settings' ), $script );
	}

	/**
	 * Other post types keep WordPress's name for the pane.
	 */
	public function test_pane_label_elsewhere() {
		set_current_screen( 'page' );
		Edit_Screen::pane_label();

		$this->assertFalse( wp_script_is( 'galerio-edit-screen' ) );
	}

	/**
	 * The accolades style is added on a profile's edit screen only.
	 */
	public function test_styles() {
		set_current_screen( 'galerio_profile' );
		Edit_Screen::styles( 'edit.php' );
		$this->assertFalse( wp_style_is( 'galerio-edit-screen' ) );

		Edit_Screen::styles( 'post.php' );
		$this->assertTrue( wp_style_is( 'galerio-edit-screen' ) );
		$this->assertStringContainsString( 'pods_meta_accolades', implode( '', wp_styles()->get_data( 'galerio-edit-screen', 'after' ) ) );
	}
}
