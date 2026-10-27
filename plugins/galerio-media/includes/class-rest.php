<?php
/**
 * REST API for the folder tree.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `galerio-media/v1` routes for the Media Library's folder tree:
 *
 * - `GET folders`: the folders a person sees, in tree order, with what they may do.
 * - `POST folders`: add a folder, shared or in "My folders".
 * - `POST folders/{id}/move`: move a folder into another, or to the top of its space.
 * - `POST file`: put files in a folder, or take them out of one space's folders.
 *
 * Renaming and deleting go through WordPress's own `wp/v2/media-folders`.
 */
final class Rest {

	/**
	 * Namespace.
	 */
	const NAMESPACE = 'galerio-media/v1';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function register() {
		$can_upload = function () {
			return current_user_can( 'upload_files' );
		};

		register_rest_route(
			self::NAMESPACE,
			'/folders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $can_upload,
					'callback'            => array( __CLASS__, 'folders' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => array( __CLASS__, 'create' ),
					'args'                => array(
						'name'     => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'parent'   => array(
							'type'    => 'integer',
							'default' => 0,
						),
						'personal' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/folders/(?P<id>\d+)/move',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $can_upload,
				'callback'            => array( __CLASS__, 'move' ),
				'args'                => array(
					'parent' => array(
						'type'    => 'integer',
						'default' => 0,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/file',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $can_upload,
				'callback'            => array( __CLASS__, 'file' ),
				'args'                => array(
					'folder'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'owner'       => array(
						'type'    => 'integer',
						'default' => 0,
					),
					'attachments' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * The folders the current person sees, and what they may do.
	 *
	 * Counts are of everyone's files, so they're left out for people who see
	 * only their own uploads.
	 *
	 * @return array
	 */
	public static function folders() {
		$user    = get_current_user_id();
		$counts  = ! Own_Uploads::applies();
		$folders = array();

		foreach ( Folders::tree( $user ) as $folder ) {
			$term      = get_term( $folder['id'], Folders::TAXONOMY );
			$folders[] = array_merge(
				$folder,
				array(
					'parent'    => (int) $term->parent,
					'count'     => $counts ? (int) $term->count : null,
					'canEdit'   => ! $folder['root'] && current_user_can( 'edit_term', $folder['id'] ),
					'canDelete' => ! $folder['root'] && current_user_can( 'delete_term', $folder['id'] ),
				)
			);
		}

		return array(
			'folders'      => $folders,
			'canAddShared' => Folders::can_manage_shared( $user ),
			'personal'     => Settings::personal_folders(),
			'user'         => $user,
		);
	}

	/**
	 * Add a folder.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error The new folder's ID.
	 */
	public static function create( WP_REST_Request $request ) {
		$parent = (int) $request['parent'];

		// A folder at the top of "My folders" goes under the person's hidden root.
		if ( ! $parent && $request['personal'] ) {
			if ( ! Settings::personal_folders() ) {
				return new WP_Error( 'galerio_media_no_personal_folders', __( 'Personal folders are switched off.', 'galerio-media' ), array( 'status' => 403 ) );
			}
			$parent = Folders::user_root( get_current_user_id(), true );
		}

		$term = wp_insert_term( $request['name'], Folders::TAXONOMY, array( 'parent' => $parent ) );

		if ( is_wp_error( $term ) ) {
			$term->add_data( array( 'status' => 400 ) );
			return $term;
		}

		return array( 'id' => (int) $term['term_id'] );
	}

	/**
	 * Move a folder into another, or to the top of its space.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error
	 */
	public static function move( WP_REST_Request $request ) {
		$result = Folders::move( (int) $request['id'], (int) $request['parent'] );

		return is_wp_error( $result ) ? $result : array( 'moved' => true );
	}

	/**
	 * Put files in a folder. Each file must be one the person may edit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error How many files were filed.
	 */
	public static function file( WP_REST_Request $request ) {
		$folder = (int) $request['folder'];
		$owner  = $folder ? Folders::owner( $folder ) : (int) $request['owner'];

		if ( $folder && ( ! current_user_can( 'assign_term', $folder ) || get_term_meta( $folder, Folders::ROOT, true ) ) ) {
			return new WP_Error( 'galerio_media_folder_forbidden', __( 'You can’t put files in that folder.', 'galerio-media' ), array( 'status' => 403 ) );
		}

		if ( $owner && get_current_user_id() !== $owner ) {
			return new WP_Error( 'galerio_media_folder_forbidden', __( 'Those folders belong to someone else.', 'galerio-media' ), array( 'status' => 403 ) );
		}

		$filed = 0;
		foreach ( array_unique( array_map( 'absint', (array) $request['attachments'] ) ) as $id ) {
			if ( 'attachment' === get_post_type( $id ) && current_user_can( 'edit_post', $id ) && Folders::file( $id, $folder, $owner ) ) {
				++$filed;
			}
		}

		return array( 'filed' => $filed );
	}
}
