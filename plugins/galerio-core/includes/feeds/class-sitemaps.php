<?php
/**
 * What the site's sitemap lists.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Feeds;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a site leave content types, taxonomies and users (author pages) out of
 * WordPress's sitemap (wp-sitemap.xml), chosen on Settings → Reading. Nothing
 * is left out until chosen.
 */
final class Sitemaps {

	/**
	 * Option: what's left out, as "post:{type}", "taxonomy:{taxonomy}" and
	 * "users".
	 */
	const OPTION = 'galerio_settings_sitemap_exclude';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'provider' ), 10, 2 );
	}

	/**
	 * What's left out.
	 *
	 * @return string[]
	 */
	public static function excluded() {
		return array_values( array_filter( (array) get_option( self::OPTION, array() ), 'is_string' ) );
	}

	/**
	 * Keep only the values that name something the sitemap can list.
	 *
	 * @param mixed $value Submitted value.
	 * @return string[]
	 */
	public static function sanitize( $value ) {
		$choices = array_keys( self::choices() );

		return array_values( array_intersect( array_map( 'strval', (array) $value ), $choices ) );
	}

	/**
	 * What a site can leave out: the sitemap's content types, taxonomies and
	 * users, with the site's names for them.
	 *
	 * @return array Key => label.
	 */
	public static function choices() {
		$choices = array();

		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				$choices[ 'post:' . $type->name ] = $type->labels->name;
			}
		}

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			if ( 'post_format' !== $taxonomy->name ) {
				$choices[ 'taxonomy:' . $taxonomy->name ] = $taxonomy->labels->name;
			}
		}

		$choices['users'] = __( 'Users (author pages)', 'galerio-core' );

		return $choices;
	}

	/**
	 * The content types the sitemap lists.
	 *
	 * @param \WP_Post_Type[] $post_types By name.
	 * @return \WP_Post_Type[]
	 */
	public static function post_types( $post_types ) {
		foreach ( self::excluded() as $key ) {
			if ( 0 === strpos( $key, 'post:' ) ) {
				unset( $post_types[ substr( $key, 5 ) ] );
			}
		}

		return $post_types;
	}

	/**
	 * The taxonomies the sitemap lists.
	 *
	 * @param \WP_Taxonomy[] $taxonomies By name.
	 * @return \WP_Taxonomy[]
	 */
	public static function taxonomies( $taxonomies ) {
		foreach ( self::excluded() as $key ) {
			if ( 0 === strpos( $key, 'taxonomy:' ) ) {
				unset( $taxonomies[ substr( $key, 9 ) ] );
			}
		}

		return $taxonomies;
	}

	/**
	 * Leave out the users' sitemap, when chosen.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                      $name     Its name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	public static function provider( $provider, $name ) {
		return 'users' === $name && in_array( 'users', self::excluded(), true ) ? false : $provider;
	}
}
