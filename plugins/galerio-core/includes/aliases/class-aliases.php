<?php
/**
 * Content type aliases.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Aliases;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a site rename Galerio's content types and taxonomies, and move their
 * addresses, e.g. Collections → Exhibitions at /exhibitions/.
 *
 * Aliases are stored in Galerio's own option, not in Pods, and applied while
 * the types are registered, so they work whoever registers them. Internal
 * names and field keys never change, so no data moves. An empty alias keeps
 * the registered value. When a slug changes, the old address is recorded and
 * redirected to the new one.
 */
final class Aliases {

	/**
	 * Option holding the aliases.
	 */
	const OPTION = 'galerio_aliases';

	/**
	 * Option holding the old slugs to redirect, old => new.
	 */
	const MOVED_OPTION = 'galerio_alias_redirects';

	/**
	 * Aliasable post types, by object name.
	 *
	 * @var array<string, string>
	 */
	const TYPES = array(
		'work'       => 'galerio_work',
		'profile'    => 'galerio_profile',
		'collection' => 'galerio_collection',
		'article'    => 'galerio_article',
	);

	/**
	 * Aliasable taxonomies, by short name.
	 *
	 * @var array<string, string>
	 */
	const TAXONOMIES = array(
		'medium'              => 'galerio_medium',
		'label'               => 'galerio_label',
		'group'               => 'galerio_group',
		'role'                => 'galerio_role',
		'collection_category' => 'galerio_collection_category',
		'article_category'    => 'galerio_article_category',
	);

	/**
	 * Alias properties of a post type.
	 *
	 * @var string[]
	 */
	const TYPE_PROPERTIES = array( 'singular', 'plural', 'slug', 'archive' );

	/**
	 * Alias properties of a taxonomy.
	 *
	 * @var string[]
	 */
	const TAXONOMY_PROPERTIES = array( 'singular', 'plural', 'slug' );

	/**
	 * Registered values before aliasing, by kind ('types' or 'taxonomies') and key.
	 *
	 * @var array
	 */
	private static $originals = array(
		'types'      => array(),
		'taxonomies' => array(),
	);

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_post_type_args' ), 20, 2 );
		add_filter( 'register_taxonomy_args', array( __CLASS__, 'filter_taxonomy_args' ), 20, 2 );
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'added' ), 10, 2 );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'updated' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 5 );
	}

	/**
	 * The stored aliases, with every property present.
	 *
	 * @param mixed $value Option value to read instead of the stored one.
	 * @return array{types: array, taxonomies: array, redirect: bool}
	 */
	public static function get( $value = null ) {
		$value = null === $value ? get_option( self::OPTION, array() ) : $value;
		$value = is_array( $value ) ? $value : array();

		$aliases = array(
			'types'      => array(),
			'taxonomies' => array(),
			'redirect'   => ! isset( $value['redirect'] ) || (bool) $value['redirect'],
		);

		foreach ( self::kinds() as $kind => $keys ) {
			foreach ( $keys as $key => $name ) {
				foreach ( self::properties( $kind ) as $property ) {
					$aliases[ $kind ][ $key ][ $property ] = isset( $value[ $kind ][ $key ][ $property ] ) && is_string( $value[ $kind ][ $key ][ $property ] )
						? $value[ $kind ][ $key ][ $property ]
						: '';
				}
			}
		}

		return $aliases;
	}

	/**
	 * The aliasable post types and taxonomies, by kind.
	 *
	 * @return array{types: array<string, string>, taxonomies: array<string, string>}
	 */
	public static function kinds() {
		return array(
			'types'      => self::TYPES,
			'taxonomies' => self::TAXONOMIES,
		);
	}

	/**
	 * The alias properties of a kind.
	 *
	 * @param string $kind 'types' or 'taxonomies'.
	 * @return string[]
	 */
	public static function properties( $kind ) {
		return 'types' === $kind ? self::TYPE_PROPERTIES : self::TAXONOMY_PROPERTIES;
	}

	/**
	 * The registered value of a property before aliasing.
	 *
	 * @param string $kind     'types' or 'taxonomies'.
	 * @param string $key      Object or taxonomy short name.
	 * @param string $property Property.
	 * @return string Empty when the type isn't registered, or has no such address.
	 */
	public static function original( $kind, $key, $property ) {
		return isset( self::$originals[ $kind ][ $key ][ $property ] ) ? self::$originals[ $kind ][ $key ][ $property ] : '';
	}

	/**
	 * The value of a property in use: the alias, or else the registered value.
	 *
	 * @param string     $kind     'types' or 'taxonomies'.
	 * @param string     $key      Object or taxonomy short name.
	 * @param string     $property Property.
	 * @param array|null $aliases  Aliases to read, from self::get(). Defaults to the stored ones.
	 * @return string
	 */
	public static function effective( $kind, $key, $property, $aliases = null ) {
		$aliases = null === $aliases ? self::get() : $aliases;
		$alias   = isset( $aliases[ $kind ][ $key ][ $property ] ) ? $aliases[ $kind ][ $key ][ $property ] : '';

		return '' !== $alias ? $alias : self::original( $kind, $key, $property );
	}

	/**
	 * Apply aliases to a Galerio post type as it is registered.
	 *
	 * @param array  $args      Registration arguments.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function filter_post_type_args( $args, $post_type ) {
		$key = array_search( $post_type, self::TYPES, true );

		if ( false === $key ) {
			return $args;
		}

		$labels  = isset( $args['labels'] ) ? (array) $args['labels'] : array();
		$slug    = isset( $args['rewrite']['slug'] ) ? (string) $args['rewrite']['slug'] : '';
		$archive = '';

		if ( ! empty( $args['has_archive'] ) ) {
			$archive = is_string( $args['has_archive'] ) ? $args['has_archive'] : ( $slug ? $slug : $post_type );
		}

		self::$originals['types'][ $key ] = array(
			'singular' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
			'plural'   => isset( $labels['name'] ) ? (string) $labels['name'] : ( isset( $args['label'] ) ? (string) $args['label'] : '' ),
			'slug'     => $slug,
			'archive'  => $archive,
		);

		$aliases = self::get();
		$args    = self::apply_labels( $args, 'types', $key, $aliases );

		if ( $slug && '' !== $aliases['types'][ $key ]['slug'] ) {
			$args['rewrite']['slug'] = $aliases['types'][ $key ]['slug'];
		}

		if ( $archive && '' !== $aliases['types'][ $key ]['archive'] ) {
			$args['has_archive'] = $aliases['types'][ $key ]['archive'];
		} elseif ( $archive && true === $args['has_archive'] ) {
			// An archive at the single slug moves with it.
			$args['has_archive'] = self::effective( 'types', $key, 'slug', $aliases );
		}

		return $args;
	}

	/**
	 * Apply aliases to a Galerio taxonomy as it is registered.
	 *
	 * @param array  $args     Registration arguments.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	public static function filter_taxonomy_args( $args, $taxonomy ) {
		$key = array_search( $taxonomy, self::TAXONOMIES, true );

		if ( false === $key ) {
			return $args;
		}

		$labels = isset( $args['labels'] ) ? (array) $args['labels'] : array();
		$slug   = isset( $args['rewrite']['slug'] ) ? (string) $args['rewrite']['slug'] : '';

		self::$originals['taxonomies'][ $key ] = array(
			'singular' => isset( $labels['singular_name'] ) ? (string) $labels['singular_name'] : '',
			'plural'   => isset( $labels['name'] ) ? (string) $labels['name'] : ( isset( $args['label'] ) ? (string) $args['label'] : '' ),
			'slug'     => $slug,
		);

		$aliases = self::get();
		$args    = self::apply_labels( $args, 'taxonomies', $key, $aliases );

		if ( $slug && '' !== $aliases['taxonomies'][ $key ]['slug'] ) {
			$args['rewrite']['slug'] = $aliases['taxonomies'][ $key ]['slug'];
		}

		return $args;
	}

	/**
	 * Rename a type in its labels.
	 *
	 * Sets the name labels, and replaces the old names in every other label
	 * the registration provides ("Add New Work" → "Add New Artwork"), in
	 * capitalised and lower case.
	 *
	 * @param array  $args    Registration arguments.
	 * @param string $kind    'types' or 'taxonomies'.
	 * @param string $key     Object or taxonomy short name.
	 * @param array  $aliases Aliases, from self::get().
	 * @return array
	 */
	private static function apply_labels( array $args, $kind, $key, array $aliases ) {
		$singular = $aliases[ $kind ][ $key ]['singular'];
		$plural   = $aliases[ $kind ][ $key ]['plural'];

		if ( '' === $singular && '' === $plural ) {
			return $args;
		}

		$replace = array();

		foreach ( array(
			'singular' => $singular,
			'plural'   => $plural,
		) as $property => $alias ) {
			$old = self::original( $kind, $key, $property );

			if ( '' === $alias || '' === $old ) {
				continue;
			}

			$replace[ $old ]                = $alias;
			$replace[ self::lower( $old ) ] = self::lower( $alias );
			$replace[ self::upper( $old ) ] = self::upper( $alias );
		}

		$labels = isset( $args['labels'] ) ? (array) $args['labels'] : array();

		if ( $replace ) {
			// Whole words only, longest first, so plurals win over singulars.
			$words = array_keys( $replace );
			usort(
				$words,
				function ( $a, $b ) {
					return strlen( $b ) - strlen( $a );
				}
			);
			$pattern = '/\\b(?:' . implode( '|', array_map( 'preg_quote', $words, array_fill( 0, count( $words ), '/' ) ) ) . ')\\b/u';

			foreach ( $labels as $label => $text ) {
				if ( is_string( $text ) && '' !== $text ) {
					$labels[ $label ] = preg_replace_callback(
						$pattern,
						function ( $found ) use ( $replace ) {
							return $replace[ $found[0] ];
						},
						$text
					);
				}
			}
		}

		if ( '' !== $plural ) {
			$labels['name'] = $plural;
			$args['label']  = $plural;

			if ( isset( $labels['menu_name'] ) || 'types' === $kind ) {
				$labels['menu_name'] = $plural;
			}
		}

		if ( '' !== $singular ) {
			$labels['singular_name'] = $singular;
		}

		$args['labels'] = $labels;

		return $args;
	}

	/**
	 * Lower-case a name, for mid-sentence use.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function lower( $text ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	/**
	 * Upper-case a name, for labels in capitals.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function upper( $text ) {
		return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text );
	}

	/**
	 * Record moved slugs when the aliases are first saved.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 */
	public static function added( $option, $value ) {
		self::updated( array(), $value );
	}

	/**
	 * Record moved slugs and refresh the rewrite rules when the aliases change.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value     New value.
	 */
	public static function updated( $old_value, $value ) {
		$old_aliases = self::get( $old_value );
		$new_aliases = self::get( $value );
		$moved       = self::moved();

		foreach ( self::kinds() as $kind => $keys ) {
			foreach ( array_keys( $keys ) as $key ) {
				foreach ( array( 'slug', 'archive' ) as $property ) {
					if ( ! in_array( $property, self::properties( $kind ), true ) || '' === self::original( $kind, $key, $property ) ) {
						continue;
					}

					$from = self::effective( $kind, $key, $property, $old_aliases );
					$to   = self::effective( $kind, $key, $property, $new_aliases );

					if ( $from !== $to ) {
						$moved = self::move( $moved, $from, $to );
					}
				}
			}
		}

		update_option( self::MOVED_OPTION, $moved, false );

		// Rebuilt on the next request, once the types register with the new slugs.
		delete_option( 'rewrite_rules' );
	}

	/**
	 * The old slugs that redirect, old => new.
	 *
	 * @return array<string, string>
	 */
	public static function moved() {
		$moved = get_option( self::MOVED_OPTION, array() );

		return is_array( $moved ) ? array_filter( $moved, 'is_string' ) : array();
	}

	/**
	 * Add a slug move to the redirect map.
	 *
	 * Earlier moves to the old slug follow it to the new one, so a slug
	 * renamed twice still redirects in one step. A slug that is back in use
	 * stops redirecting.
	 *
	 * @param array<string, string> $moved Redirect map, old => new.
	 * @param string                $from  Old slug.
	 * @param string                $to    New slug.
	 * @return array<string, string>
	 */
	public static function move( array $moved, $from, $to ) {
		foreach ( $moved as $old => $new ) {
			if ( $new === $from ) {
				$moved[ $old ] = $to;
			}
		}

		$moved[ $from ] = $to;
		unset( $moved[ $to ] );

		return array_filter(
			$moved,
			function ( $to_slug, $from_slug ) {
				return (string) $from_slug !== $to_slug;
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * The new address for a path under an old slug.
	 *
	 * @param string                $path  Request path relative to the home URL, without slashes at either end.
	 * @param array<string, string> $moved Redirect map, old => new.
	 * @return string|null New path, or null when the path isn't under an old slug.
	 */
	public static function moved_path( $path, array $moved ) {
		// Longest first, so "collection-category" isn't caught by "collection".
		uksort(
			$moved,
			function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);

		foreach ( $moved as $old => $new ) {
			$old = (string) $old;

			if ( $path === $old || 0 === strpos( $path, $old . '/' ) ) {
				return $new . substr( $path, strlen( $old ) );
			}
		}

		return null;
	}

	/**
	 * Redirect a request for an old address to the new one.
	 *
	 * Only requests that would otherwise be "not found" are redirected, so
	 * anything now living at an old slug wins.
	 */
	public static function redirect() {
		if ( ! is_404() || ! self::get()['redirect'] ) {
			return;
		}

		$moved = self::moved();

		if ( ! $moved || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$request = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		$home    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path    = isset( $request['path'] ) ? rawurldecode( $request['path'] ) : '';

		if ( 0 !== strpos( $path, $home ) ) {
			return;
		}

		$new = self::moved_path( trim( substr( $path, strlen( $home ) ), '/' ), $moved );

		if ( null === $new ) {
			return;
		}

		$url = user_trailingslashit( home_url( $new ) );

		if ( ! empty( $request['query'] ) ) {
			$url .= '?' . $request['query'];
		}

		wp_safe_redirect( $url, 301, 'Galerio' );
		exit;
	}
}
