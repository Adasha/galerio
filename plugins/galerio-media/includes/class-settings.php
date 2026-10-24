<?php
/**
 * Galerio Media's settings, on Settings → Media.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

defined( 'ABSPATH' ) || exit;

/**
 * Three switches in a Galerio section of Settings → Media:
 *
 * - whether each person can have their own folders as well as the shared ones;
 * - whether people who can't edit others' posts see only their own uploads;
 * - whether attachments have their own pages (WordPress's own option).
 */
final class Settings {

	/**
	 * Option: each person can have their own folders.
	 */
	const PERSONAL_FOLDERS = 'galerio_media_personal_folders';

	/**
	 * Option: people who can't edit others' posts see only their own uploads.
	 */
	const OWN_UPLOADS = 'galerio_media_own_uploads';

	/**
	 * Option: new uploads into shared folders are saved in the folder's directory.
	 */
	const FOLDER_DIRECTORIES = 'galerio_media_folder_directories';

	/**
	 * WordPress's option for attachment pages (since 6.4).
	 */
	const ATTACHMENT_PAGES = 'wp_attachment_pages_enabled';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'register_options' ) );
	}

	/**
	 * Register the options, with their defaults.
	 */
	public static function register_options() {
		register_setting(
			'media',
			self::PERSONAL_FOLDERS,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'show_in_rest'      => true,
			)
		);
		register_setting(
			'media',
			self::OWN_UPLOADS,
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'media',
			self::FOLDER_DIRECTORIES,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			'media',
			self::ATTACHMENT_PAGES,
			array(
				'type'              => 'integer',
				'sanitize_callback' => function ( $value ) {
					return $value ? 1 : 0;
				},
			)
		);
	}

	/**
	 * Whether each person can have their own folders.
	 *
	 * @return bool
	 */
	public static function personal_folders() {
		return (bool) get_option( self::PERSONAL_FOLDERS, false );
	}

	/**
	 * Whether new uploads into shared folders go into the folder's directory.
	 *
	 * @return bool
	 */
	public static function folder_directories() {
		return (bool) get_option( self::FOLDER_DIRECTORIES, false );
	}

	/**
	 * Whether uploads are private to the people who made them.
	 *
	 * @return bool
	 */
	public static function own_uploads() {
		return (bool) get_option( self::OWN_UPLOADS, true );
	}

	/**
	 * Add the Galerio section to Settings → Media.
	 */
	public static function register() {
		add_settings_section(
			'galerio-media',
			__( 'Galerio', 'galerio-media' ),
			'__return_false',
			'media'
		);

		add_settings_field(
			self::PERSONAL_FOLDERS,
			__( 'Folders', 'galerio-media' ),
			function () {
				self::checkbox( self::PERSONAL_FOLDERS, self::personal_folders(), __( 'Each person can have their own folders as well as the shared ones', 'galerio-media' ) );
			},
			'media',
			'galerio-media'
		);

		add_settings_field(
			self::FOLDER_DIRECTORIES,
			__( 'Folder directories', 'galerio-media' ),
			function () {
				self::checkbox( self::FOLDER_DIRECTORIES, self::folder_directories(), __( 'Save files uploaded into a shared folder in a directory named after the folder, such as uploads/collections/visions/, instead of year and month directories', 'galerio-media' ) );
			},
			'media',
			'galerio-media'
		);

		add_settings_field(
			self::OWN_UPLOADS,
			__( 'Uploads', 'galerio-media' ),
			function () {
				self::checkbox( self::OWN_UPLOADS, self::own_uploads(), __( 'People who can’t edit others’ posts, such as contributors, see only their own uploads', 'galerio-media' ) );
			},
			'media',
			'galerio-media'
		);

		add_settings_field(
			self::ATTACHMENT_PAGES,
			__( 'Attachment pages', 'galerio-media' ),
			function () {
				self::checkbox( self::ATTACHMENT_PAGES, (bool) get_option( self::ATTACHMENT_PAGES, false ), __( 'Give each uploaded file its own page. Off, links to a file go to the file itself.', 'galerio-media' ) );
			},
			'media',
			'galerio-media'
		);
	}

	/**
	 * A checkbox for an option.
	 *
	 * @param string $name    Option name.
	 * @param bool   $checked Current value.
	 * @param string $label   Label.
	 */
	private static function checkbox( $name, $checked, $label ) {
		printf(
			'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}
}
