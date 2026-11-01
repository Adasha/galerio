<?php
/**
 * Settings page tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Settings\Features;
use Galerio\Core\Settings\Settings_Page;

/**
 * The page matches the Pods settings it replaces, and stores what Features reads.
 *
 * @covers \Galerio\Core\Settings\Settings_Page
 */
class Test_Settings_Page extends Galerio_TestCase {

	/**
	 * Every setting in the Pods package is on the page, with its default.
	 */
	public function test_matches_the_package() {
		$package = json_decode( file_get_contents( dirname( __DIR__, 3 ) . '/data/pods/galerio.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local test file.
		$actual  = array();

		foreach ( Settings_Page::sections() as $section ) {
			foreach ( $section['settings'] as $name => $setting ) {
				$actual[ $name ] = (string) $setting['default'];
			}
		}

		$expected = array();

		foreach ( $package['pods'] as $pod ) {
			if ( 'galerio_settings' === $pod['name'] ) {
				foreach ( $pod['groups'] as $group ) {
					foreach ( $group['fields'] as $field ) {
						$expected[ $field['name'] ] = (string) ( $field['default_value'] ?? '' );
					}
				}
			}
		}

		$this->assertEqualSets( array_keys( $expected ), array_keys( $actual ) );
		$this->assertSame( $expected['catalogue'], $actual['catalogue'] );
		$this->assertSame( $expected['show_portraits'], $actual['show_portraits'] );
		$this->assertSame( $expected['currency'], $actual['currency'] );
	}

	/**
	 * A switch left unticked is saved as off, not as missing, which would read as on.
	 */
	public function test_switch_off_stays_off() {
		update_option( Features::PREFIX . 'show_portraits', Settings_Page::sanitize( null, 'switch' ) );
		$this->assertFalse( Features::enabled( 'show_portraits' ) );

		update_option( Features::PREFIX . 'show_portraits', Settings_Page::sanitize( '1', 'switch' ) );
		$this->assertTrue( Features::enabled( 'show_portraits' ) );

		delete_option( Features::PREFIX . 'show_portraits' );
	}

	/**
	 * Other values are cleaned.
	 */
	public function test_sanitize() {
		$this->assertSame( 'EUR', Settings_Page::sanitize( 'EUR', 'currency' ) );
		$this->assertSame( 'GBP', Settings_Page::sanitize( 'nonsense', 'currency' ) );
		$this->assertSame( 'a@example.org', Settings_Page::sanitize( 'a@example.org', 'email' ) );
		$this->assertSame( '', Settings_Page::sanitize( 'not an email', 'email' ) );
	}
}
