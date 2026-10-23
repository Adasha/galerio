<?php
/**
 * Field reference tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Reference;

/**
 * The README's field reference, generated from the registry.
 *
 * @covers \Galerio\Core\Fields\Reference
 */
class Test_Reference extends Galerio_TestCase {

	/**
	 * The README's field reference matches the registry.
	 *
	 * Run `npm run docs:fields` to regenerate it after changing fields.
	 */
	public function test_readme_is_up_to_date() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$readme = file_get_contents( dirname( __DIR__, 3 ) . '/README.md' );

		$this->assertMatchesRegularExpression( '/<!-- field-reference:start -->\s*(.*?)\s*<!-- field-reference:end -->/s', $readme );
		preg_match( '/<!-- field-reference:start -->\s*(.*?)\s*<!-- field-reference:end -->/s', $readme, $section );

		$this->assertSame(
			trim( Reference::markdown( galerio_fields() ) ),
			trim( $section[1] ),
			'The README field reference is out of date. Run: npm run docs:fields'
		);
	}

	/**
	 * Every registered field is listed, with its type in words.
	 */
	public function test_rows() {
		$rows = array_column( Reference::rows( galerio_fields() ), null, 'key' );

		$this->assertCount( count( galerio_fields()->all() ), $rows );
		$this->assertSame( 'Relationship to profiles', $rows['work.creator']['type'] );
		$this->assertSame( 'Link (list)', $rows['profile.links']['type'] );
		$this->assertStringStartsWith( 'Private', $rows['work.owner']['notes'] );
		$this->assertSame( '', $rows['work.medium']['notes'] );
	}

	/**
	 * Rows can be limited to one object.
	 */
	public function test_rows_for_one_object() {
		$keys = wp_list_pluck( Reference::rows( galerio_fields(), 'attachment' ), 'key' );

		$this->assertSame( array( 'attachment.year', 'attachment.credit', 'attachment.copyright' ), $keys );
	}
}
