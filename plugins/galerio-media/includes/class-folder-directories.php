<?php
/**
 * New uploads saved into their folder's directory.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

defined( 'ABSPATH' ) || exit;

/**
 * When Settings → Media asks for it, a file uploaded into a shared folder is
 * saved in a directory named after the folder's path, so the files on disk
 * are organised like the Media Library: a file uploaded into
 * collections › degree-shows › unpeeled is saved in
 * `uploads/collections/degree-shows/unpeeled/`.
 *
 * Personal folders keep WordPress's year and month directories, because their
 * directory would carry the person's name, and file addresses are public.
 *
 * Only new uploads are placed this way. Renaming or moving a folder later
 * doesn't move the files already saved in its directory.
 */
final class Folder_Directories {

	/**
	 * The upload_dir filter in use for the current upload.
	 *
	 * @var callable|null
	 */
	private static $filter = null;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'start' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( __CLASS__, 'start' ) );
		add_filter( 'wp_handle_upload', array( __CLASS__, 'stop' ) );
		add_filter( 'wp_handle_sideload', array( __CLASS__, 'stop' ) );
	}

	/**
	 * A shared folder's directory, below the uploads directory.
	 *
	 * @param int $term_id Folder.
	 * @return string Such as "/collections/degree-shows/unpeeled", or empty
	 *                for personal folders and folders that don't exist.
	 */
	public static function path( $term_id ) {
		$term = get_term( $term_id, Folders::TAXONOMY );

		if ( ! $term || is_wp_error( $term ) || Folders::owner( $term->term_id ) ) {
			return '';
		}

		$slugs = array( $term->slug );
		foreach ( get_ancestors( $term->term_id, Folders::TAXONOMY, 'taxonomy' ) as $ancestor ) {
			$slugs[] = get_term( $ancestor, Folders::TAXONOMY )->slug;
		}

		return '/' . implode( '/', array_map( 'sanitize_file_name', array_reverse( $slugs ) ) );
	}

	/**
	 * Before an upload is saved: if it's going into a shared folder, point the
	 * uploads directory at the folder's directory for this one file.
	 *
	 * @param array $file Uploaded file.
	 * @return array
	 */
	public static function start( $file ) {
		if ( ! Settings::folder_directories() ) {
			return $file;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Part of WordPress's checked upload request.
		$folder = isset( $_POST['galerio_folder'] ) ? absint( $_POST['galerio_folder'] ) : 0;
		$path   = $folder && current_user_can( 'assign_term', $folder ) ? self::path( $folder ) : '';

		if ( '' !== $path ) {
			$filter = function ( $dirs ) use ( $path ) {
				$dirs['subdir'] = $path;
				$dirs['path']   = $dirs['basedir'] . $path;
				$dirs['url']    = $dirs['baseurl'] . $path;
				return $dirs;
			};

			self::$filter = $filter;
			add_filter( 'upload_dir', $filter );
		}

		return $file;
	}

	/**
	 * After the upload is saved, put the uploads directory back.
	 *
	 * @param array $upload Saved upload.
	 * @return array
	 */
	public static function stop( $upload ) {
		if ( self::$filter ) {
			remove_filter( 'upload_dir', self::$filter );
			self::$filter = null;
		}

		return $upload;
	}
}
