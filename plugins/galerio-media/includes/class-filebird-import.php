<?php
/**
 * Import of FileBird's folders.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Copies FileBird's folders and the files in them into Galerio's folders.
 *
 * FileBird keeps its folders in its own tables: `{prefix}fbv` (id, name,
 * parent, ord, created_by) and `{prefix}fbv_attachment_folder` (folder_id,
 * attachment_id). Folders with a `created_by` of 0 are shared; others belong
 * to that person and become their own folders. FileBird itself is left as it
 * was, so the import can be checked before FileBird is switched off.
 *
 * It can be run again: folders already imported are reused, and files are
 * only ever added to folders.
 */
final class FileBird_Import {

	/**
	 * Term meta: the FileBird folder a Galerio folder came from.
	 */
	const SOURCE = 'galerio_filebird_id';

	/**
	 * Whether this site has FileBird's tables.
	 *
	 * @return bool
	 */
	public static function available() {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking for another plugin's table.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}fbv" );
		$wpdb->suppress_errors( $suppress );

		return null !== $count;
	}

	/**
	 * Run the import.
	 *
	 * @param bool $dry_run Count what would happen without changing anything.
	 * @return array|WP_Error Report with `folders`, `reused`, `files`, `missing`,
	 *                        `people` and, for a dry run, the same counts as estimates.
	 */
	public static function run( $dry_run = false ) {
		global $wpdb;

		if ( ! self::available() ) {
			return new WP_Error( 'galerio_media_no_filebird', __( 'This site has no FileBird folders to import.', 'galerio-media' ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off import from another plugin's tables.
		$folders = $wpdb->get_results( "SELECT id, name, parent, ord, created_by FROM {$wpdb->prefix}fbv ORDER BY parent, ord, id" );
		$links   = $wpdb->get_results( "SELECT folder_id, attachment_id FROM {$wpdb->prefix}fbv_attachment_folder" );
		// phpcs:enable

		$report = array(
			'folders' => 0,
			'reused'  => 0,
			'files'   => 0,
			'missing' => 0,
			'people'  => array(),
		);

		$by_parent = array();
		foreach ( $folders as $folder ) {
			$by_parent[ (int) $folder->parent ][] = $folder;
		}

		$map    = array();
		$import = function ( $parent_id, $parent_term ) use ( &$import, &$map, &$report, $by_parent, $dry_run ) {
			foreach ( isset( $by_parent[ $parent_id ] ) ? $by_parent[ $parent_id ] : array() as $folder ) {
				$owner = (int) $folder->created_by;

				// Someone who has since left the site: keep their folders, as shared ones.
				if ( $owner && ! get_userdata( $owner ) ) {
					$owner = 0;
				}

				if ( $owner ) {
					$report['people'][ $owner ] = true;
				}

				$term_id = $dry_run ? 0 : self::folder( $folder, $owner, $parent_term ? $parent_term : ( $owner ? Folders::user_root( $owner, true ) : 0 ), $report );

				if ( $dry_run ) {
					++$report['folders'];
				}

				$map[ (int) $folder->id ] = $term_id;
				$import( (int) $folder->id, $term_id );
			}
		};
		$import( 0, 0 );

		$files = array();
		foreach ( $links as $link ) {
			if ( ! isset( $map[ (int) $link->folder_id ] ) ) {
				continue;
			}
			if ( 'attachment' !== get_post_type( (int) $link->attachment_id ) ) {
				++$report['missing'];
				continue;
			}
			$files[ (int) $link->attachment_id ][] = $map[ (int) $link->folder_id ];
		}

		foreach ( $files as $attachment_id => $terms ) {
			if ( ! $dry_run ) {
				wp_set_object_terms( $attachment_id, array_values( array_unique( $terms ) ), Folders::TAXONOMY, true );
			}
			++$report['files'];
		}

		if ( $report['people'] && ! $dry_run ) {
			update_option( Settings::PERSONAL_FOLDERS, true );
		}

		$report['people'] = count( $report['people'] );

		return $report;
	}

	/**
	 * Make or reuse the Galerio folder for a FileBird folder.
	 *
	 * @param object $folder    FileBird folder.
	 * @param int    $owner     Owner, or 0 for shared.
	 * @param int    $parent_id Parent Galerio folder, or 0.
	 * @param array  $report    Report, updated.
	 * @return int Term ID.
	 */
	private static function folder( $folder, $owner, $parent_id, &$report ) {
		$existing = get_terms(
			array(
				'taxonomy'    => Folders::TAXONOMY,
				'hide_empty'  => false,
				'fields'      => 'ids',
				'galerio_all' => true,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- A one-off import.
					array(
						'key'   => self::SOURCE,
						'value' => (int) $folder->id,
					),
				),
			)
		);

		if ( $existing && ! is_wp_error( $existing ) ) {
			++$report['reused'];
			return (int) $existing[0];
		}

		$term = wp_insert_term(
			wp_specialchars_decode( $folder->name ),
			Folders::TAXONOMY,
			array(
				'parent'              => $parent_id,
				'galerio_owner'       => $owner,
				'galerio_skip_checks' => true,
			)
		);

		if ( is_wp_error( $term ) ) {
			// A folder of the same name already in that place is reused.
			$term_id = (int) $term->get_error_data( 'term_exists' );
			if ( ! $term_id ) {
				return 0;
			}
			++$report['reused'];
		} else {
			$term_id = (int) $term['term_id'];
			++$report['folders'];
		}

		update_term_meta( $term_id, self::SOURCE, (int) $folder->id );
		update_term_meta( $term_id, Folders::OWNER, $owner );
		update_term_meta( $term_id, Folders::ORDER, (int) $folder->ord );

		return $term_id;
	}
}
