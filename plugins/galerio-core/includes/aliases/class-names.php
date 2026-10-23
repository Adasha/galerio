<?php
/**
 * Type names in text.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Aliases;

defined( 'ABSPATH' ) || exit;

/**
 * Puts a site's names for its content types into text, through tokens.
 *
 * A token is a type's standard English name in braces, singular or plural,
 * and its case says how the name is written: "{Collections}" gives the
 * plural as the site names it ("Exhibitions"), "{collections}" the same in
 * lower case, for mid-sentence use, and "{Collection}" or "{collection}" the
 * singular. So "Featured {works}:" reads "Featured artworks:" on a site that
 * calls its works Artworks. Unknown tokens are left as they are.
 */
final class Names {

	/**
	 * Standard names, by kind and key: singular and plural.
	 *
	 * @return array{types: array, taxonomies: array}
	 */
	private static function standard() {
		return array(
			'types'      => array(
				'work'       => array( 'Work', 'Works' ),
				'profile'    => array( 'Profile', 'Profiles' ),
				'collection' => array( 'Collection', 'Collections' ),
				'article'    => array( 'Article', 'Articles' ),
			),
			'taxonomies' => array(
				'medium' => array( 'Medium', 'Mediums' ),
				'label'  => array( 'Label', 'Labels' ),
				'group'  => array( 'Group', 'Groups' ),
				'role'   => array( 'Role', 'Roles' ),
			),
		);
	}

	/**
	 * Translated fallbacks, for when a type hasn't been registered yet.
	 *
	 * @param string $kind 'types' or 'taxonomies'.
	 * @param string $key  Object or taxonomy short name.
	 * @return array{0: string, 1: string} Singular and plural.
	 */
	private static function fallback( $kind, $key ) {
		$names = array(
			'work'       => array( __( 'Work', 'galerio-core' ), __( 'Works', 'galerio-core' ) ),
			'profile'    => array( __( 'Profile', 'galerio-core' ), __( 'Profiles', 'galerio-core' ) ),
			'collection' => array( __( 'Collection', 'galerio-core' ), __( 'Collections', 'galerio-core' ) ),
			'article'    => array( __( 'Article', 'galerio-core' ), __( 'Articles', 'galerio-core' ) ),
			'medium'     => array( __( 'Medium', 'galerio-core' ), __( 'Mediums', 'galerio-core' ) ),
			'label'      => array( __( 'Label', 'galerio-core' ), __( 'Labels', 'galerio-core' ) ),
			'group'      => array( __( 'Group', 'galerio-core' ), __( 'Groups', 'galerio-core' ) ),
			'role'       => array( __( 'Role', 'galerio-core' ), __( 'Roles', 'galerio-core' ) ),
		);

		return isset( $names[ $key ] ) ? $names[ $key ] : self::standard()[ $kind ][ $key ];
	}

	/**
	 * Every token and the name it stands for.
	 *
	 * @return array<string, string> "{Token}" => name.
	 */
	public static function tokens() {
		$aliases = Aliases::get();
		$tokens  = array();

		foreach ( self::standard() as $kind => $keys ) {
			foreach ( $keys as $key => $standard ) {
				$fallback = self::fallback( $kind, $key );

				foreach ( array( 'singular', 'plural' ) as $index => $property ) {
					$name = Aliases::effective( $kind, $key, $property, $aliases );
					$name = '' !== $name ? $name : $fallback[ $index ];

					$tokens[ '{' . $standard[ $index ] . '}' ]               = $name;
					$tokens[ '{' . strtolower( $standard[ $index ] ) . '}' ] = self::lower( $name );
				}
			}
		}

		return $tokens;
	}

	/**
	 * Replace the name tokens in a text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function expand( $text ) {
		if ( ! is_string( $text ) || false === strpos( $text, '{' ) ) {
			return $text;
		}

		return strtr( $text, self::tokens() );
	}

	/**
	 * Lower-case a name.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}
}
