<?php
/**
 * Name token tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Aliases\Aliases;
use Galerio\Core\Aliases\Names;
use Galerio\Core\Fields\Field;
use Galerio\Core\Fields\Field_Block;

/**
 * Type names in labels, through tokens such as "{Collections}".
 *
 * @covers \Galerio\Core\Aliases\Names
 * @covers \Galerio\Core\Fields\Field_Block::label
 */
class Test_Names extends Galerio_TestCase {

	/**
	 * Clear the aliases after each test.
	 */
	public function tear_down() {
		delete_option( Aliases::OPTION );
		delete_option( Aliases::MOVED_OPTION );
		parent::tear_down();
	}

	/**
	 * Rename works and collections.
	 */
	private function alias() {
		update_option(
			Aliases::OPTION,
			array(
				'types'      => array(
					'work'       => array(
						'singular' => 'Artwork',
						'plural'   => 'Artworks',
					),
					'collection' => array( 'plural' => 'Exhibitions' ),
				),
				'taxonomies' => array(
					'medium' => array( 'plural' => 'Formats' ),
				),
			)
		);
	}

	/**
	 * Without aliases, tokens give the registered names.
	 */
	public function test_registered_names() {
		$this->assertSame( 'Collections: related works', Names::expand( '{Collections}: related {works}' ) );
	}

	/**
	 * Tokens give the site's names, in the token's case.
	 */
	public function test_aliased_names() {
		$this->alias();

		$this->assertSame( 'Exhibitions', Names::expand( '{Collections}' ) );
		$this->assertSame( 'Featured artworks:', Names::expand( 'Featured {works}:' ) );
		$this->assertSame( 'Artwork / artwork', Names::expand( '{Work} / {work}' ) );
		$this->assertSame( 'Formats', Names::expand( '{Mediums}' ) );
		$this->assertSame( 'Collection', Names::expand( '{Collection}' ), 'An unaliased singular keeps the registered name.' );
	}

	/**
	 * Themes reach the names through galerio_expand_names().
	 */
	public function test_expand_names_function() {
		$this->alias();

		$this->assertSame( 'No artworks yet.', galerio_expand_names( 'No {works} yet.' ) );
	}

	/**
	 * Unknown tokens and other text are left alone.
	 */
	public function test_unknown_tokens() {
		$this->assertSame( '{Things} and {field} {WORKS}', Names::expand( '{Things} and {field} {WORKS}' ) );
		$this->assertSame( 'No braces', Names::expand( 'No braces' ) );
	}

	/**
	 * Field labels use the site's names.
	 */
	public function test_field_labels() {
		$this->alias();
		$definitions = require GALERIO_CORE_DIR . 'includes/fields/default-fields.php';

		$this->assertSame( 'Exhibitions', ( new Field( 'work.collections', $definitions['work.collections'] ) )->label );
		$this->assertSame( 'Related artworks', ( new Field( 'work.related_works', $definitions['work.related_works'] ) )->label );
	}

	/**
	 * The Field block's heading takes "{field}" and name tokens.
	 */
	public function test_field_block_heading() {
		$this->alias();
		$field = new Field( 'profile.works', array( 'label' => '{Works}' ) );

		$this->assertSame( 'Artworks:', Field_Block::label( '{field}:', $field ) );
		$this->assertSame( 'Featured artworks:', Field_Block::label( 'Featured {works}:', $field ) );
		$this->assertSame( 'Featured in:', Field_Block::label( 'Featured in:', $field ) );
	}
}
