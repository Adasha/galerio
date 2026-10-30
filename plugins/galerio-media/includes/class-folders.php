<?php
/**
 * Media folders.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_Error;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Folders for the Media Library, as a hierarchical taxonomy on attachments.
 *
 * There are two kinds:
 *
 * - Shared folders, which everyone who can upload sees, and editors manage.
 * - Personal folders, when Settings → Media allows them: each person's own,
 *   seen and managed by them alone. They sit under a hidden root folder per
 *   person, so two people (or a person and the shared tree) can use the same
 *   folder names side by side.
 *
 * A file can be in one shared folder and in one of each person's own folders.
 * Folders only organise the library; files stay where they are on disk.
 */
final class Folders {

	/**
	 * Taxonomy.
	 */
	const TAXONOMY = 'galerio_media_folder';

	/**
	 * Term meta: the folder's owner, a user ID, or 0 for shared folders.
	 */
	const OWNER = 'galerio_owner';

	/**
	 * Term meta: marks a person's hidden root folder.
	 */
	const ROOT = 'galerio_root';

	/**
	 * Term meta: the folder's position among its siblings.
	 */
	const ORDER = 'galerio_order';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'get_terms_args', array( __CLASS__, 'scope_admin_screen' ), 10, 2 );
		add_filter( 'rest_' . self::TAXONOMY . '_query', array( __CLASS__, 'scope' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'map_meta_cap' ), 10, 4 );
		add_filter( 'pre_insert_term', array( __CLASS__, 'check_new' ), 10, 3 );
		add_action( 'created_' . self::TAXONOMY, array( __CLASS__, 'set_owner' ), 10, 3 );
	}

	/**
	 * Register the taxonomy and its term meta.
	 */
	public static function register() {
		register_taxonomy(
			self::TAXONOMY,
			'attachment',
			array(
				'labels'                => array(
					'name'          => __( 'Folders', 'galerio-media' ),
					'singular_name' => __( 'Folder', 'galerio-media' ),
					'add_new_item'  => __( 'Add folder', 'galerio-media' ),
					'edit_item'     => __( 'Edit folder', 'galerio-media' ),
					'search_items'  => __( 'Search folders', 'galerio-media' ),
					'all_items'     => __( 'All folders', 'galerio-media' ),
					'parent_item'   => __( 'Parent folder', 'galerio-media' ),
					'not_found'     => __( 'No folders yet.', 'galerio-media' ),
				),
				'hierarchical'          => true,
				'public'                => false,
				'show_ui'               => true,
				'show_in_menu'          => false,
				'show_admin_column'     => false,
				'show_in_rest'          => true,
				'rest_base'             => 'media-folders',
				'query_var'             => false,
				'rewrite'               => false,
				'update_count_callback' => '_update_generic_term_count',
				'capabilities'          => array(
					'manage_terms' => 'upload_files',
					'edit_terms'   => 'upload_files',
					'delete_terms' => 'upload_files',
					'assign_terms' => 'upload_files',
				),
			)
		);

		foreach ( array( self::OWNER, self::ROOT, self::ORDER ) as $key ) {
			register_term_meta(
				self::TAXONOMY,
				$key,
				array(
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
					'auth_callback'     => '__return_false',
				)
			);
		}
	}

	/**
	 * Whether a person can manage the shared folders.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_manage_shared( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return false;
		}

		// Administrators always can; otherwise the roles chosen on Settings → Media.
		return in_array( 'administrator', $user->roles, true ) || (bool) array_intersect( $user->roles, Settings::folder_managers() );
	}

	/**
	 * A folder's owner: a user ID, or 0 for a shared folder.
	 *
	 * @param int $term_id Folder.
	 * @return int
	 */
	public static function owner( $term_id ) {
		return (int) get_term_meta( $term_id, self::OWNER, true );
	}

	/**
	 * A person's hidden root folder, which holds their own folders.
	 *
	 * @param int  $user_id User.
	 * @param bool $create  Whether to make it if it doesn't exist.
	 * @return int Term ID, or 0.
	 */
	public static function user_root( $user_id, $create = false ) {
		$user_id = (int) $user_id;

		if ( ! $user_id ) {
			return 0;
		}

		$roots = get_terms(
			array(
				'taxonomy'    => self::TAXONOMY,
				'hide_empty'  => false,
				'fields'      => 'ids',
				'galerio_all' => true,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One small lookup.
					array(
						'key'   => self::ROOT,
						'value' => $user_id,
					),
				),
			)
		);

		if ( $roots && ! is_wp_error( $roots ) ) {
			return (int) $roots[0];
		}

		if ( ! $create ) {
			return 0;
		}

		$term = wp_insert_term(
			'galerio-user-' . $user_id,
			self::TAXONOMY,
			array(
				'slug'                => 'galerio-user-' . $user_id,
				'galerio_owner'       => $user_id,
				'galerio_skip_checks' => true,
			)
		);

		if ( is_wp_error( $term ) ) {
			return 0;
		}

		update_term_meta( $term['term_id'], self::ROOT, $user_id );
		update_term_meta( $term['term_id'], self::OWNER, $user_id );

		return (int) $term['term_id'];
	}

	/**
	 * Show people only the shared folders and their own, in folder lists:
	 * the REST API (which the folder tree uses) and the folders screen.
	 *
	 * Only lists are narrowed. Lookups by ID, such as which folders a file is
	 * in, see every folder, so filing never drops anyone else's.
	 *
	 * @param array $args get_terms() or REST query arguments.
	 * @return array
	 */
	public static function scope( $args ) {
		$owners = array( 0 );
		if ( Settings::personal_folders() && get_current_user_id() ) {
			$owners[] = get_current_user_id();
		}

		$scope = array(
			'relation' => 'OR',
			array(
				'key'     => self::OWNER,
				'value'   => $owners,
				'compare' => 'IN',
			),
			array(
				'key'     => self::OWNER,
				'compare' => 'NOT EXISTS',
			),
		);

		$args['meta_query'] = empty( $args['meta_query'] ) // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Folder lists are short.
			? $scope
			: array(
				'relation' => 'AND',
				$args['meta_query'],
				$scope,
			);

		return $args;
	}

	/**
	 * Scope the folder list on the folders admin screen.
	 *
	 * @param array    $args       get_terms() arguments.
	 * @param string[] $taxonomies Taxonomies.
	 * @return array
	 */
	public static function scope_admin_screen( $args, $taxonomies ) {
		global $pagenow;

		if ( is_admin() && in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) && array( self::TAXONOMY ) === array_values( (array) $taxonomies ) && empty( $args['object_ids'] ) && empty( $args['include'] ) ) {
			return self::scope( $args );
		}

		return $args;
	}

	/**
	 * Per-folder permissions: shared folders are managed by the roles chosen on
	 * Settings → Media (editors by default), personal
	 * ones by their owners only, and anyone who can upload can file into the
	 * folders they can see.
	 *
	 * @param string[] $caps    Primitive capabilities.
	 * @param string   $cap     Capability asked for.
	 * @param int      $user_id User.
	 * @param array    $args    Extra arguments; the term ID first.
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_term', 'delete_term', 'assign_term' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$term = get_term( (int) $args[0] );

		if ( ! $term instanceof WP_Term || self::TAXONOMY !== $term->taxonomy ) {
			return $caps;
		}

		$owner = self::owner( $term->term_id );

		if ( $owner && (int) $user_id !== $owner ) {
			return array( 'do_not_allow' );
		}

		if ( get_term_meta( $term->term_id, self::ROOT, true ) && 'assign_term' !== $cap ) {
			return array( 'do_not_allow' );
		}

		if ( ! $owner && 'assign_term' !== $cap ) {
			return self::can_manage_shared( $user_id ) ? array( 'upload_files' ) : array( 'do_not_allow' );
		}

		return array( 'upload_files' );
	}

	/**
	 * Before a folder is added: personal folders must be allowed, and a shared
	 * folder needs someone who manages them.
	 *
	 * @param string|WP_Error $term     Folder name.
	 * @param string          $taxonomy Taxonomy.
	 * @param array           $args     Arguments, including `parent` and Galerio's `galerio_owner`.
	 * @return string|WP_Error
	 */
	public static function check_new( $term, $taxonomy, $args = array() ) {
		if ( self::TAXONOMY !== $taxonomy || is_wp_error( $term ) || ! empty( $args['galerio_skip_checks'] ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return $term;
		}

		$parent = isset( $args['parent'] ) ? (int) $args['parent'] : 0;
		$owner  = $parent ? self::owner( $parent ) : 0;
		$user   = get_current_user_id();

		if ( $owner && $owner !== $user ) {
			return new WP_Error( 'galerio_media_folder_owner', __( 'That folder belongs to someone else.', 'galerio-media' ) );
		}

		if ( ! $owner && ! self::can_manage_shared( $user ) ) {
			return new WP_Error( 'galerio_media_shared_folder', __( 'You can’t add shared folders.', 'galerio-media' ) );
		}

		return $term;
	}

	/**
	 * A new folder takes its parent's owner.
	 *
	 * @param int   $term_id Folder.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param array $args    Arguments passed to wp_insert_term().
	 */
	public static function set_owner( $term_id, $tt_id, $args = array() ) {
		if ( isset( $args['galerio_owner'] ) ) {
			$owner = (int) $args['galerio_owner'];
		} else {
			$parent = (int) get_term_field( 'parent', $term_id, self::TAXONOMY );
			$owner  = $parent ? self::owner( $parent ) : 0;
		}

		update_term_meta( $term_id, self::OWNER, $owner );
	}

	/**
	 * Put a file in a folder, taking it out of any other folder in the same
	 * space (the shared folders, or the folder owner's own).
	 *
	 * @param int $attachment_id File.
	 * @param int $term_id       Folder, or 0 to take it out of the shared folders.
	 * @param int $owner         With $term_id 0: whose folders to take it out of.
	 * @return bool
	 */
	public static function file( $attachment_id, $term_id, $owner = 0 ) {
		$owner = $term_id ? self::owner( $term_id ) : (int) $owner;

		// All the file's folders, including other people's, so theirs are kept.
		$current = wp_get_object_terms(
			$attachment_id,
			self::TAXONOMY,
			array(
				'fields'      => 'ids',
				'galerio_all' => true,
			)
		);

		if ( is_wp_error( $current ) ) {
			return false;
		}

		$keep = array_filter(
			$current,
			function ( $id ) use ( $owner ) {
				return self::owner( $id ) !== $owner;
			}
		);

		if ( $term_id ) {
			$keep[] = (int) $term_id;
		}

		return ! is_wp_error( wp_set_object_terms( $attachment_id, array_values( array_map( 'intval', $keep ) ), self::TAXONOMY ) );
	}

	/**
	 * Move a folder, with the folders inside it, into another folder of the
	 * same space, or to the top of its space with $parent_id 0.
	 *
	 * Shared folders move within the shared folders, by the people who manage
	 * them; personal folders within their owner's, by their owner.
	 *
	 * @param int $term_id   Folder.
	 * @param int $parent_id New parent folder, or 0 for the top of the folder's space.
	 * @return true|WP_Error
	 */
	public static function move( $term_id, $parent_id ) {
		$term = get_term( $term_id, self::TAXONOMY );

		if ( ! $term instanceof WP_Term || ! current_user_can( 'edit_term', $term_id ) ) {
			return new WP_Error( 'galerio_media_folder_forbidden', __( 'You can’t move that folder.', 'galerio-media' ), array( 'status' => 403 ) );
		}

		$owner = self::owner( $term_id );

		if ( ! $parent_id ) {
			$parent_id = $owner ? self::user_root( $owner, true ) : 0;
		} elseif ( self::owner( $parent_id ) !== $owner || ! get_term( $parent_id, self::TAXONOMY ) instanceof WP_Term || ( ! $owner && get_term_meta( $parent_id, self::ROOT, true ) ) ) {
			return new WP_Error( 'galerio_media_folder_space', __( 'A folder can only move among the folders it’s with: shared folders among the shared ones, your own among yours.', 'galerio-media' ), array( 'status' => 400 ) );
		}

		// Not into itself, nor into a folder inside it.
		for ( $id = (int) $parent_id; $id; $id = (int) get_term_field( 'parent', $id, self::TAXONOMY ) ) {
			if ( (int) $term_id === $id ) {
				return new WP_Error( 'galerio_media_folder_loop', __( 'A folder can’t go inside itself.', 'galerio-media' ), array( 'status' => 400 ) );
			}
		}

		if ( (int) $term->parent === (int) $parent_id ) {
			return true;
		}

		$result = wp_update_term( $term_id, self::TAXONOMY, array( 'parent' => (int) $parent_id ) );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return true;
	}

	/**
	 * A tax query narrowing files to a folder the current person may use, or to
	 * files in no shared folder.
	 *
	 * @param int|string $folder A folder ID, "unfiled", or 0 for all files.
	 * @return array|null Tax query, or null to leave the files as they are.
	 */
	public static function tax_query( $folder ) {
		if ( 'unfiled' === $folder ) {
			$shared = get_terms(
				array(
					'taxonomy'    => self::TAXONOMY,
					'hide_empty'  => false,
					'fields'      => 'ids',
					'galerio_all' => true,
					'meta_key'    => self::OWNER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Shared folders.
					'meta_value'  => 0, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Shared folders.
				)
			);

			return array(
				array(
					'taxonomy' => self::TAXONOMY,
					'terms'    => is_wp_error( $shared ) || ! $shared ? array( 0 ) : $shared,
					'operator' => 'NOT IN',
				),
			);
		}

		$folder = absint( $folder );
		if ( ! $folder || ! current_user_can( 'assign_term', $folder ) ) {
			return null;
		}

		return array(
			array(
				'taxonomy'         => self::TAXONOMY,
				'terms'            => $folder,
				'include_children' => false,
			),
		);
	}

	/**
	 * A folder choice from a request: "unfiled", or a folder ID (0 for none).
	 *
	 * @param mixed $value Raw value.
	 * @return int|string
	 */
	public static function parse_choice( $value ) {
		$value = is_scalar( $value ) ? sanitize_key( (string) $value ) : '';

		return 'unfiled' === $value ? 'unfiled' : absint( $value );
	}

	/**
	 * The folders a person sees, as a flat list in tree order, each with its
	 * depth, for dropdowns.
	 *
	 * @param int $user_id User.
	 * @return array[] Each with `id`, `name`, `depth` and `owner`.
	 */
	public static function tree( $user_id ) {
		$owners = array( 0 );
		if ( Settings::personal_folders() && $user_id ) {
			$owners[] = (int) $user_id;
		}

		$terms = get_terms(
			array(
				'taxonomy'    => self::TAXONOMY,
				'hide_empty'  => false,
				'galerio_all' => true,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$children = array();
		foreach ( $terms as $term ) {
			if ( in_array( self::owner( $term->term_id ), $owners, true ) ) {
				$children[ $term->parent ][] = $term;
			}
		}

		foreach ( $children as $parent => $list ) {
			usort(
				$list,
				function ( $a, $b ) {
					// A person's own folders come before the shared ones.
					$root = (int) (bool) get_term_meta( $b->term_id, self::ROOT, true ) - (int) (bool) get_term_meta( $a->term_id, self::ROOT, true );
					if ( $root ) {
						return $root;
					}
					$order = (int) get_term_meta( $a->term_id, self::ORDER, true ) - (int) get_term_meta( $b->term_id, self::ORDER, true );
					return $order ? $order : strnatcasecmp( $a->name, $b->name );
				}
			);
			$children[ $parent ] = $list;
		}

		$flat = array();
		$walk = function ( $parent_id, $depth ) use ( &$walk, &$flat, $children ) {
			foreach ( isset( $children[ $parent_id ] ) ? $children[ $parent_id ] : array() as $term ) {
				$root   = (int) get_term_meta( $term->term_id, self::ROOT, true );
				$flat[] = array(
					'id'    => $term->term_id,
					'name'  => $root ? __( 'My folders', 'galerio-media' ) : $term->name,
					'depth' => $depth,
					'owner' => self::owner( $term->term_id ),
					'root'  => (bool) $root,
				);
				$walk( $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );

		return $flat;
	}
}
