<?php
/**
 * Pods package importer.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Pods;

use Pods_Migrate_Packages;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Imports a Pods package, then repairs what Pods' own importer leaves broken.
 *
 * Pods packages store each relationship's sister field as a numeric field ID
 * from the site that exported them. Pods imports those IDs unchanged, so on any
 * other site bidirectional relationships point at unrelated fields (and Pods
 * writes `sister_id` meta onto whichever posts happen to have those IDs). The
 * repair remaps sister fields by pod and field name.
 */
final class Importer {

	/**
	 * Import a package and repair it.
	 *
	 * @param array $package Decoded package.
	 * @param bool  $replace Replace existing pods, fields and templates instead of updating them.
	 * @return array|WP_Error Report: see repair().
	 */
	public static function import( array $package, $replace = false ) {
		if ( ! defined( 'PODS_DIR' ) ) {
			return new WP_Error( 'galerio_pods_missing', 'Pods is not active.' );
		}

		require_once PODS_DIR . 'components/Migrate-Packages/Migrate-Packages.php';

		// Pods stores its configuration as posts. Without the unfiltered_html
		// capability, kses would rewrite template code and escape names.
		$kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		kses_remove_filters();

		$imported = Pods_Migrate_Packages::import( $package, $replace );

		if ( $kses ) {
			kses_init_filters();
		}

		if ( ! $imported ) {
			return new WP_Error( 'galerio_import_failed', 'Pods package not imported.' );
		}

		return self::repair( $package );
	}

	/**
	 * Repair an imported package's relationships and template slugs.
	 *
	 * @param array $package Decoded package that was imported.
	 * @return array {
	 *     @type int      $linked   Relationship fields linked to their sister.
	 *     @type int      $cleared  Fields whose stale sister link was removed.
	 *     @type int      $stray    Stray sister_id entries removed from other posts.
	 *     @type int      $renamed  Pods Templates given admin-style slugs.
	 *     @type string[] $warnings Problems that were skipped.
	 * }
	 */
	public static function repair( array $package ) {
		$report            = self::repair_relationships( $package );
		$report['renamed'] = self::normalise_template_slugs( $package );

		return $report;
	}

	/**
	 * Point every relationship field at the correct sister field on this site.
	 *
	 * @param array $package Decoded package.
	 * @return array Report.
	 */
	private static function repair_relationships( array $package ) {
		global $wpdb;

		$api    = pods_api();
		$fields = Package::fields( $package );
		$report = array(
			'linked'   => 0,
			'cleared'  => 0,
			'stray'    => 0,
			'warnings' => array(),
		);

		// Map the exporting site's field IDs to "pod.field" names.
		$names = array();
		foreach ( $fields as $pod_name => $pod_fields ) {
			foreach ( $pod_fields as $field ) {
				$names[ (int) $field['id'] ] = $pod_name . '.' . $field['name'];
			}
		}

		foreach ( $fields as $pod_name => $pod_fields ) {
			foreach ( $pod_fields as $field ) {
				$local = $api->load_field(
					array(
						'pod'  => $pod_name,
						'name' => $field['name'],
					)
				);

				if ( ! $local ) {
					$report['warnings'][] = sprintf( 'Field %s.%s not found on this site.', $pod_name, $field['name'] );
					continue;
				}

				$old_sister = isset( $field['sister_id'] ) ? (int) $field['sister_id'] : 0;

				if ( ! $old_sister ) {
					if ( delete_post_meta( $local['id'], 'sister_id' ) ) {
						++$report['cleared'];
					}
					self::set_loaded_sister( $local, '' );
					continue;
				}

				if ( ! isset( $names[ $old_sister ] ) ) {
					$report['warnings'][] = sprintf( 'Sister of %s.%s (ID %d) is not in the package; leaving it unset.', $pod_name, $field['name'], $old_sister );
					delete_post_meta( $local['id'], 'sister_id' );
					self::set_loaded_sister( $local, '' );
					continue;
				}

				list( $sister_pod, $sister_name ) = explode( '.', $names[ $old_sister ], 2 );

				$sister = $api->load_field(
					array(
						'pod'  => $sister_pod,
						'name' => $sister_name,
					)
				);

				if ( ! $sister ) {
					$report['warnings'][] = sprintf( 'Sister field %s not found on this site.', $names[ $old_sister ] );
					continue;
				}

				update_post_meta( $local['id'], 'sister_id', (int) $sister['id'] );
				self::set_loaded_sister( $local, (int) $sister['id'] );
				++$report['linked'];
			}
		}

		// Remove sister_id meta that Pods wrote onto unrelated posts during import.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off repair.
		$report['stray'] = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE pm FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type <> %s",
				'sister_id',
				'_pods_field'
			)
		);

		$api->cache_flush_pods();

		return $report;
	}

	/**
	 * Update a field Pods has already loaded in this request.
	 *
	 * Pods keeps loaded fields in memory and doesn't reload them when their
	 * meta changes, so without this the rest of the request (such as an
	 * export straight after an import) would see the unrepaired sister.
	 *
	 * @param mixed      $field     Field loaded by the Pods API.
	 * @param int|string $sister_id New sister field ID, or an empty string.
	 */
	private static function set_loaded_sister( $field, $sister_id ) {
		if ( is_object( $field ) && method_exists( $field, 'set_arg' ) ) {
			$field->set_arg( 'sister_id', $sister_id );
		}
	}

	/**
	 * Give imported Pods Templates the slugs WordPress generates in the admin.
	 *
	 * The theme refers to templates by slug (e.g. "work-definitive-title"), as
	 * created through the Pods admin. Package imports produce underscored slugs
	 * instead ("work_definitive_title"), which the theme cannot find.
	 *
	 * @param array $package Decoded package.
	 * @return int Number of templates renamed.
	 */
	private static function normalise_template_slugs( array $package ) {
		$renamed = 0;

		foreach ( isset( $package['templates'] ) ? $package['templates'] : array() as $template ) {
			$posts = get_posts(
				array(
					'post_type'      => '_pods_template',
					'post_status'    => 'any',
					'title'          => $template['name'],
					'posts_per_page' => 1,
				)
			);

			if ( ! $posts ) {
				continue;
			}

			$slug = sanitize_title( $template['name'] );

			if ( $posts[0]->post_name !== $slug ) {
				wp_update_post(
					array(
						'ID'        => $posts[0]->ID,
						'post_name' => $slug,
					)
				);
				++$renamed;
			}
		}

		return $renamed;
	}
}
