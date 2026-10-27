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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_list_view' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'query' ) );
		add_filter( 'plupload_default_params', array( __CLASS__, 'upload_params' ) );
		add_filter( 'upload_post_params', array( __CLASS__, 'upload_page_params' ) );
		add_action( 'add_attachment', array( __CLASS__, 'file_upload' ) );
	}

	/**
	 * Load the tree on the Media Library's list view too, which WordPress
	 * builds without the media views.
	 *
	 * @param string $hook Admin page.
	 */
	public static function enqueue_list_view( $hook ) {
		if ( 'upload.php' === $hook && 'list' === get_user_option( 'media_library_mode' ) ) {
			self::enqueue( array() );
		}
	}

	/**
	 * Load the tree's script and styles with the media views.
	 *
	 * @param array $deps Extra script dependencies; the media views by default.
	 */
	public static function enqueue( $deps = array( 'media-views' ) ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// wp_enqueue_media passes no arguments, but don't rely on it.
		$deps = is_array( $deps ) ? $deps : array( 'media-views' );

		$asset_file = GALERIO_MEDIA_DIR . 'build/folders.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'galerio-media-folders',
			plugins_url( 'build/folders.js', GALERIO_MEDIA_FILE ),
			array_merge( $asset['dependencies'], $deps ),
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The Media Library's query, nonce-checked by WordPress; parse_choice() sanitises.
		return Folders::parse_choice( isset( $_REQUEST['query']['galerio_folder'] ) ? wp_unslash( $_REQUEST['query']['galerio_folder'] ) : '' );
	}

	/**
	 * Narrow the Media Library's files to the chosen folder.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function query( $args ) {
		$tax_query = Folders::tax_query( self::chosen() );

		if ( $tax_query ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by folder is the point.
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
	 * On the Add New Media File page, opened from the list view with a folder
	 * open, send that folder with the uploads.
	 *
	 * @param array $params Upload parameters.
	 * @return array
	 */
	public static function upload_page_params( $params ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only preselects a folder; the upload checks it.
		$folder = isset( $_GET['galerio_folder'] ) ? absint( $_GET['galerio_folder'] ) : 0;

		if ( $folder && current_user_can( 'assign_term', $folder ) ) {
			$params['galerio_folder'] = $folder;
		}

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
