<?php
/**
 * Uploads private to the people who made them.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * People whose roles are all among those chosen on Settings → Media (by
 * default, roles that can upload but can't edit others' posts, such as
 * students with the contributor role) see only their own uploads: in the
 * Media Library's grid and list, in the media picker, and in the block
 * editor's media tab. Everyone else, and administrators always, see everything.
 *
 * It only narrows what the admin lists show. Published files stay public on
 * the site, as WordPress has them.
 */
final class Own_Uploads {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'ajax_args' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'list_view' ) );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'rest_args' ) );
	}

	/**
	 * Whether the current person sees only their own uploads.
	 *
	 * @return bool
	 */
	public static function applies() {
		$roles = is_user_logged_in() ? wp_get_current_user()->roles : array();

		// A role outside the list, such as administrator, lets someone see everything.
		return $roles && ! array_diff( $roles, Settings::own_uploads_roles() );
	}

	/**
	 * The Media Library grid and the media picker.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function ajax_args( $args ) {
		if ( self::applies() ) {
			$args['author'] = get_current_user_id();
		}

		return $args;
	}

	/**
	 * The Media Library's list view.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function list_view( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) || ! self::applies() ) {
			return;
		}

		global $pagenow;
		if ( 'upload.php' === $pagenow ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * The REST API, which the block editor's media tab uses. Only people
	 * signed in are affected; visitors see published files as before.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function rest_args( $args ) {
		if ( self::applies() ) {
			$args['author'] = get_current_user_id();
		}

		return $args;
	}
}
