<?php
/**
 * Galerio Media's settings, on Settings → Media.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

defined( 'ABSPATH' ) || exit;

/**
 * A Galerio section of Settings → Media:
 *
 * - whether each person can have their own folders as well as the shared ones;
 * - which roles manage the shared folders (add, rename, move and delete them);
 * - whether uploads into shared folders go into folder directories;
 * - which roles see only their own uploads;
 * - whether attachments have their own pages (WordPress's own option).
 *
 * Administrators always manage the shared folders and see every upload. Until
 * a role list is saved, it follows the roles' capabilities: roles that can
 * edit others' posts manage the folders, and roles that can upload but can't
 * edit others' posts see only their own uploads.
 */
final class Settings {

	/**
	 * Option: each person can have their own folders.
	 */
	const PERSONAL_FOLDERS = 'galerio_media_personal_folders';

	/**
	 * Option, before role lists: people who can't edit others' posts see only
	 * their own uploads. Read only as the default of OWN_UPLOADS_ROLES.
	 */
	const OWN_UPLOADS = 'galerio_media_own_uploads';

	/**
	 * Option: roles that see only their own uploads.
	 */
	const OWN_UPLOADS_ROLES = 'galerio_media_own_uploads_roles';

	/**
	 * Option: roles that manage the shared folders.
	 */
	const FOLDER_MANAGERS = 'galerio_media_folder_managers';

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
		foreach ( array( self::FOLDER_MANAGERS, self::OWN_UPLOADS_ROLES ) as $option ) {
			register_setting(
				'media',
				$option,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_roles' ),
				)
			);
		}
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
	 * The roles that can upload, other than administrators: those the role
	 * lists choose from.
	 *
	 * @return string[] Role names, by slug.
	 */
	public static function upload_roles() {
		$roles = array();

		foreach ( wp_roles()->role_objects as $slug => $role ) {
			if ( 'administrator' !== $slug && $role->has_cap( 'upload_files' ) ) {
				$roles[ $slug ] = translate_user_role( wp_roles()->role_names[ $slug ] );
			}
		}

		return $roles;
	}

	/**
	 * Keep only roles the lists offer.
	 *
	 * @param mixed $value Submitted roles.
	 * @return string[]
	 */
	public static function sanitize_roles( $value ) {
		return array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), array_keys( self::upload_roles() ) ) );
	}

	/**
	 * Roles that can upload, split by whether they can edit others' posts.
	 *
	 * @param bool $editors True for those that can.
	 * @return string[] Role slugs.
	 */
	private static function roles_by_editing( $editors ) {
		return array_values(
			array_filter(
				array_keys( self::upload_roles() ),
				function ( $slug ) use ( $editors ) {
					return wp_roles()->get_role( $slug )->has_cap( 'edit_others_posts' ) === $editors;
				}
			)
		);
	}

	/**
	 * Roles that manage the shared folders, besides administrators.
	 *
	 * @return string[] Role slugs.
	 */
	public static function folder_managers() {
		$roles = get_option( self::FOLDER_MANAGERS, null );

		return is_array( $roles ) ? $roles : self::roles_by_editing( true );
	}

	/**
	 * Roles that see only their own uploads.
	 *
	 * @return string[] Role slugs.
	 */
	public static function own_uploads_roles() {
		$roles = get_option( self::OWN_UPLOADS_ROLES, null );

		if ( is_array( $roles ) ) {
			return $roles;
		}

		// The earlier on/off switch, when it was turned off.
		return get_option( self::OWN_UPLOADS, true ) ? self::roles_by_editing( false ) : array();
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
			self::FOLDER_MANAGERS,
			__( 'Shared folders', 'galerio-media' ),
			function () {
				self::role_list( self::FOLDER_MANAGERS, self::folder_managers(), __( 'These roles add, rename, move and delete the shared folders. Everyone who can upload files puts files in them, and arranges their own folders as they like. Administrators always can.', 'galerio-media' ) );
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
			self::OWN_UPLOADS_ROLES,
			__( 'Own uploads only', 'galerio-media' ),
			function () {
				self::role_list( self::OWN_UPLOADS_ROLES, self::own_uploads_roles(), __( 'These roles see only the files they uploaded, in the Media Library and when choosing media. Someone with another role as well sees everything. Files on the site itself stay public. Administrators always see everything.', 'galerio-media' ) );
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
	 * Checkboxes for a role list.
	 *
	 * @param string   $name     Option name.
	 * @param string[] $selected Chosen roles.
	 * @param string   $help     Description.
	 */
	private static function role_list( $name, $selected, $help ) {
		echo '<fieldset>';
		foreach ( self::upload_roles() as $slug => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s[]" value="%2$s"%3$s> %4$s</label><br>',
				esc_attr( $name ),
				esc_attr( $slug ),
				checked( in_array( $slug, $selected, true ), true, false ),
				esc_html( $label )
			);
		}
		printf( '<p class="description">%s</p></fieldset>', esc_html( $help ) );
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
