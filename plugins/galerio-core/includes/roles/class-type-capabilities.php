<?php
/**
 * Permissions of Galerio's content types.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Roles;

use Galerio\Core\Aliases\Aliases;

defined( 'ABSPATH' ) || exit;

/**
 * Gives each Galerio content type its own permissions, such as
 * `edit_galerio_profiles` and `publish_galerio_profiles`.
 *
 * The Pods package registers the types with page permissions, so anyone who
 * can edit pages could edit every type, and no one else could edit any. With
 * their own permissions, a role can be allowed to write profiles and works
 * without publishing them, or without touching pages: for example students
 * who submit their own profiles for review.
 *
 * The names are the ones PublishPress Capabilities uses for the same types, so
 * roles set up with it keep working, with or without it. Administrators and
 * editors are given every permission once, so they keep the access they had.
 */
final class Type_Capabilities {

	/**
	 * Option recording which version of the role grants has run.
	 */
	const VERSION_OPTION = 'galerio_type_capabilities';

	/**
	 * Version of the role grants. Raise it to grant again.
	 */
	const VERSION = 1;

	/**
	 * Roles given every permission.
	 */
	const FULL_ROLES = array( 'administrator', 'editor' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_post_type_args' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'grant' ), 20 );
	}

	/**
	 * Whether Galerio sets the types' permissions.
	 *
	 * @return bool
	 */
	public static function enabled() {
		/**
		 * Filters whether Galerio gives its content types their own permissions.
		 * Return false to keep the permissions they're registered with.
		 *
		 * @param bool $enabled Whether to use Galerio's permissions.
		 */
		return (bool) apply_filters( 'galerio_type_capabilities', true );
	}

	/**
	 * Give a Galerio post type its own permissions as it is registered.
	 *
	 * @param array  $args      Registration arguments.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function filter_post_type_args( $args, $post_type ) {
		if ( ! in_array( $post_type, Aliases::TYPES, true ) || ! self::enabled() ) {
			return $args;
		}

		$args['capability_type'] = array( $post_type, $post_type . 's' );
		$args['map_meta_cap']    = true;
		unset( $args['capabilities'] );

		return $args;
	}

	/**
	 * Every permission of a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	public static function capabilities( $post_type ) {
		$plural = $post_type . 's';
		$caps   = array();

		foreach ( array( 'edit', 'edit_others', 'edit_private', 'edit_published', 'publish', 'read_private', 'delete', 'delete_others', 'delete_private', 'delete_published' ) as $action ) {
			$caps[] = $action . '_' . $plural;
		}

		return $caps;
	}

	/**
	 * Give administrators and editors every permission, once per version.
	 */
	public static function grant() {
		if ( ! self::enabled() || (int) get_option( self::VERSION_OPTION ) >= self::VERSION ) {
			return;
		}

		foreach ( self::FULL_ROLES as $name ) {
			$role = get_role( $name );

			if ( ! $role ) {
				continue;
			}

			foreach ( Aliases::TYPES as $post_type ) {
				foreach ( self::capabilities( $post_type ) as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		update_option( self::VERSION_OPTION, self::VERSION );
	}
}
