<?php
/**
 * The folder tree in the Media Library and the media picker.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the folder tree wherever WordPress's media views run (the Media
 * Library grid and the media picker), and connects it to them: the folder
 * chosen in the tree narrows the files shown, and files uploaded while a
 * folder is open go into it.
 *
 * The choice travels as `query[galerio_folder]` with the Media Library's own
 * requests: a folder ID, "unfiled" for files in no shared folder, or nothing.
 */
final class Library {

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'wp_enqueue_media', array( __CLASS__, 'enqueue' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'query' ) );
		add_filter( 'plupload_default_params', array( __CLASS__, 'upload_params' ) );
		add_action( 'add_attachment', array( __CLASS__, 'file_upload' ) );
	}

	/**
	 * Load the tree's script and styles with the media views.
	 */
	public static function enqueue() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$asset_file = GALERIO_MEDIA_DIR . 'build/folders.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'galerio-media-folders',
			plugins_url( 'build/folders.js', GALERIO_MEDIA_FILE ),
			array_merge( $asset['dependencies'], array( 'media-views' ) ),
			$asset['version'],
			true
		);
		wp_set_script_translations( 'galerio-media-folders', 'galerio-media', GALERIO_MEDIA_DIR . 'languages' );
		wp_enqueue_style( 'galerio-media-folders', plugins_url( 'build/style-folders.css', GALERIO_MEDIA_FILE ), array( 'wp-components' ), $asset['version'] );
	}

	/**
	 * The folder chosen in the tree.
	 *
	 * @return int|string A folder ID, "unfiled", or 0 for all files.
	 */
	private static function chosen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The Media Library's query, nonce-checked by WordPress.
		$value = isset( $_REQUEST['query']['galerio_folder'] ) ? sanitize_key( wp_unslash( $_REQUEST['query']['galerio_folder'] ) ) : '';

		return 'unfiled' === $value ? 'unfiled' : absint( $value );
	}

	/**
	 * Narrow the Media Library's files to the chosen folder.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function query( $args ) {
		$folder = self::chosen();

		if ( 'unfiled' === $folder ) {
			$shared = get_terms(
				array(
					'taxonomy'    => Folders::TAXONOMY,
					'hide_empty'  => false,
					'fields'      => 'ids',
					'galerio_all' => true,
					'meta_key'    => Folders::OWNER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Shared folders.
					'meta_value'  => 0, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Shared folders.
				)
			);

			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by folder is the point.
				array(
					'taxonomy' => Folders::TAXONOMY,
					'terms'    => is_wp_error( $shared ) || ! $shared ? array( 0 ) : $shared,
					'operator' => 'NOT IN',
				),
			);
		} elseif ( $folder && current_user_can( 'assign_term', $folder ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by folder is the point.
				array(
					'taxonomy'         => Folders::TAXONOMY,
					'terms'            => $folder,
					'include_children' => false,
				),
			);
		}

		return $args;
	}

	/**
	 * Send the uploader's folder field with every upload; the tree sets it.
	 *
	 * @param array $params Upload parameters.
	 * @return array
	 */
	public static function upload_params( $params ) {
		$params['galerio_folder'] = 0;
		return $params;
	}

	/**
	 * File a new upload in the folder that was open when it was uploaded.
	 *
	 * @param int $attachment_id New file.
	 */
	public static function file_upload( $attachment_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Part of WordPress's checked upload request.
		$folder = isset( $_POST['galerio_folder'] ) ? absint( $_POST['galerio_folder'] ) : 0;

		if ( $folder && current_user_can( 'assign_term', $folder ) && ! get_term_meta( $folder, Folders::ROOT, true ) ) {
			Folders::file( $attachment_id, $folder );
		}
	}
}
