<?php
/**
 * Field reference documentation.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the registered fields for people: the README's field reference
 * and `wp galerio fields`.
 */
final class Reference {

	/**
	 * Section headings for each object.
	 *
	 * @var array<string, string>
	 */
	const HEADINGS = array(
		'work'       => 'Works',
		'profile'    => 'Profiles',
		'collection' => 'Collections',
		'article'    => 'Articles',
		'attachment' => 'Media',
	);

	/**
	 * Plain names for field types.
	 *
	 * @var array<string, string>
	 */
	const TYPE_NAMES = array(
		'text'     => 'Text',
		'textarea' => 'Plain text, several lines',
		'html'     => 'Formatted text',
		'url'      => 'URL',
		'embed'    => 'Embed URL',
		'date'     => 'Date',
		'year'     => 'Year',
		'number'   => 'Number',
		'boolean'  => 'On/off',
		'link'     => 'Link',
		'image'    => 'Image',
		'relation' => 'Relationship',
	);

	/**
	 * One row per field, for tables.
	 *
	 * @param Registry $registry    Registry.
	 * @param string   $object_name Object to list, or empty for all.
	 * @return array[] Rows with key, label, type and notes.
	 */
	public static function rows( Registry $registry, $object_name = '' ) {
		$rows = array();

		foreach ( array_keys( self::HEADINGS ) as $object ) {
			if ( $object_name && $object !== $object_name ) {
				continue;
			}

			foreach ( $registry->all( $object ) as $field ) {
				$rows[] = array(
					'key'   => $field->key,
					'label' => $field->label,
					'type'  => self::type( $field ),
					'notes' => self::notes( $field ),
				);
			}
		}

		return $rows;
	}

	/**
	 * The reference as Markdown: a table of fields for each object.
	 *
	 * @param Registry $registry Registry.
	 * @return string
	 */
	public static function markdown( Registry $registry ) {
		$sections = array();

		foreach ( self::HEADINGS as $object => $heading ) {
			$rows = self::rows( $registry, $object );

			if ( ! $rows ) {
				continue;
			}

			$lines = array(
				'#### ' . $heading,
				'',
				'| Key | Label | Type | Notes |',
				'| --- | --- | --- | --- |',
			);

			foreach ( $rows as $row ) {
				$lines[] = sprintf( '| `%s` | %s | %s | %s |', $row['key'], self::cell( $row['label'] ), self::cell( $row['type'] ), self::cell( $row['notes'] ) );
			}

			$sections[] = implode( "\n", $lines );
		}

		return implode( "\n\n", $sections ) . "\n";
	}

	/**
	 * A field's type in words, e.g. "Relationship to profiles (list)".
	 *
	 * @param Field $field Field.
	 * @return string
	 */
	private static function type( Field $field ) {
		$type = self::TYPE_NAMES[ $field->type ];

		if ( 'relation' === $field->type ) {
			$type .= ' to ' . strtolower( self::HEADINGS[ $field->target ] );
		}

		return $field->multiple ? $type . ' (list)' : $type;
	}

	/**
	 * Notes worth knowing about a field.
	 *
	 * @param Field $field Field.
	 * @return string
	 */
	private static function notes( Field $field ) {
		$notes = array();

		if ( ! $field->public ) {
			$notes[] = 'Private: not shown on the site, in REST or in block bindings.';
		}

		if ( $field->deprecated ) {
			$notes[] = 'Deprecated: ' . $field->deprecated;
		}

		return implode( ' ', $notes );
	}

	/**
	 * Escape text for a Markdown table cell.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function cell( $text ) {
		return str_replace( '|', '\\|', $text );
	}
}
