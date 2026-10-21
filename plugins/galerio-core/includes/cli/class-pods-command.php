<?php
/**
 * WP-CLI commands for installing the Galerio Pods package.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\CLI;

use Pods_Migrate_Packages;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Import the Galerio Pods package and repair its bidirectional relationships.
 *
 * Pods packages store each relationship's sister field as a numeric field ID
 * from the site that exported them. Pods imports those IDs unchanged, so on any
 * other site bidirectional relationships point at unrelated fields (and Pods
 * writes `sister_id` meta onto whichever posts happen to have those IDs). These
 * commands remap sister fields by pod and field name after importing.
 */
class Pods_Command {

	/**
	 * Import a Pods package, then repair its relationships.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the Pods package JSON file.
	 *
	 * [--replace]
	 * : Replace existing pods, fields and templates instead of updating them.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio pods import wp-content/galerio-data/pods/galerio.json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		$package = $this->read_package( $args[0] );

		if ( ! class_exists( 'Pods_Migrate_Packages' ) ) {
			WP_CLI::error( 'The Pods "Migrate: Packages" component is not active. Run: wp pods-legacy-api activate-component --component=migrate-packages' );
		}

		$imported = Pods_Migrate_Packages::import( $package, ! empty( $assoc_args['replace'] ) );

		if ( empty( $imported ) ) {
			WP_CLI::error( 'Pods package not imported.' );
		}

		WP_CLI::log( 'Pods package imported.' );

		$this->repair( $package );
		$this->normalise_template_slugs( $package );
	}

	/**
	 * Give imported Pods Templates the slugs WordPress generates in the admin.
	 *
	 * The theme refers to templates by slug (e.g. "work-definitive-title"), as
	 * created through the Pods admin. Package imports produce underscored slugs
	 * instead ("work_definitive_title"), which the theme cannot find.
	 *
	 * @param array $package Decoded Pods package.
	 */
	private function normalise_template_slugs( array $package ) {
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

		WP_CLI::success( sprintf( 'Normalised %d Pods Template slugs.', $renamed ) );
	}

	/**
	 * Repair bidirectional relationships for an already-imported package.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the Pods package JSON file that was imported.
	 *
	 * ## EXAMPLES
	 *
	 *     wp galerio pods repair-relationships wp-content/galerio-data/pods/galerio.json
	 *
	 * @subcommand repair-relationships
	 *
	 * @param array $args Positional arguments.
	 */
	public function repair_relationships( $args ) {
		$package = $this->read_package( $args[0] );

		$this->repair( $package );
		$this->normalise_template_slugs( $package );
	}

	/**
	 * Decode a package file.
	 *
	 * @param string $file Path to the package.
	 * @return array
	 */
	private function read_package( $file ) {
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$package = json_decode( file_get_contents( $file ), true );

		if ( ! is_array( $package ) || empty( $package['pods'] ) ) {
			WP_CLI::error( sprintf( '%s is not a Pods package.', $file ) );
		}

		return $package;
	}

	/**
	 * Point every relationship field at the correct sister field on this site.
	 *
	 * @param array $package Decoded Pods package.
	 */
	private function repair( array $package ) {
		global $wpdb;

		$api = pods_api();

		// Map the exporting site's field IDs to "pod.field" names.
		$names = array();
		foreach ( $this->package_fields( $package ) as $pod_name => $fields ) {
			foreach ( $fields as $field ) {
				$names[ (int) $field['id'] ] = $pod_name . '.' . $field['name'];
			}
		}

		$fixed   = 0;
		$cleared = 0;

		foreach ( $this->package_fields( $package ) as $pod_name => $fields ) {
			foreach ( $fields as $field ) {
				$local = $api->load_field(
					array(
						'pod'  => $pod_name,
						'name' => $field['name'],
					)
				);

				if ( ! $local ) {
					WP_CLI::warning( sprintf( 'Field %s.%s not found on this site.', $pod_name, $field['name'] ) );
					continue;
				}

				$old_sister = isset( $field['sister_id'] ) ? (int) $field['sister_id'] : 0;

				if ( ! $old_sister ) {
					if ( delete_post_meta( $local['id'], 'sister_id' ) ) {
						++$cleared;
					}
					continue;
				}

				if ( ! isset( $names[ $old_sister ] ) ) {
					WP_CLI::warning( sprintf( 'Sister of %s.%s (ID %d) is not in the package; leaving it unset.', $pod_name, $field['name'], $old_sister ) );
					delete_post_meta( $local['id'], 'sister_id' );
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
					WP_CLI::warning( sprintf( 'Sister field %s not found on this site.', $names[ $old_sister ] ) );
					continue;
				}

				update_post_meta( $local['id'], 'sister_id', (int) $sister['id'] );
				++$fixed;
			}
		}

		// Remove sister_id meta that Pods wrote onto unrelated posts during import.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off repair.
		$stray = $wpdb->query(
			$wpdb->prepare(
				"DELETE pm FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type <> %s",
				'sister_id',
				'_pods_field'
			)
		);

		$api->cache_flush_pods();

		WP_CLI::success( sprintf( 'Linked %d relationship fields, cleared %d, removed %d stray entries.', $fixed, $cleared, (int) $stray ) );
	}

	/**
	 * List each pod's fields from a package, whether grouped or flat.
	 *
	 * @param array $package Decoded Pods package.
	 * @return array<string, array[]> Pod name => field definitions.
	 */
	private function package_fields( array $package ) {
		$result = array();

		foreach ( $package['pods'] as $pod ) {
			$fields = isset( $pod['fields'] ) ? array_values( (array) $pod['fields'] ) : array();

			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $group ) {
				$fields = array_merge( $fields, array_values( (array) $group['fields'] ) );
			}

			$result[ $pod['name'] ] = $fields;
		}

		return $result;
	}
}
