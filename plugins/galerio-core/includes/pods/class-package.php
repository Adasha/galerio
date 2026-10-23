<?php
/**
 * Pods package reading, exporting and comparing.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Pods;

use Pods_Migrate_Packages;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Works with Pods packages: the JSON files Pods imports and exports, such as
 * data/pods/galerio.json.
 *
 * Packages exported by Pods carry the exporting site's database IDs and some
 * bookkeeping values, so two exports of the same configuration from different
 * sites differ. canonical() removes those differences, and diff() compares
 * packages by name rather than by ID.
 */
final class Package {

	/**
	 * Keys that describe the site a package came from, or one-off instructions
	 * Pods acts on when saving a pod but never stores.
	 *
	 * @var string[]
	 */
	const IGNORED_KEYS = array( '_edit_last', '_edit_lock', '_wp_old_slug', 'bypass_table_schema', 'overwrite_table_schema' );

	/**
	 * Key prefixes Pods uses for its own upgrade bookkeeping.
	 *
	 * @var string[]
	 */
	const SITE_KEY_PREFIXES = array( '_migrated_' );

	/**
	 * Pods settings that are site preferences rather than configuration.
	 *
	 * @var string[]
	 */
	const SITE_SETTINGS = array( 'wisdom_opt_out', 'wisdom_registered_setting', 'autocomplete_limit', 'show_display_callback_notices' );

	/**
	 * Read a package file.
	 *
	 * @param string $file Path to the package.
	 * @return array|WP_Error
	 */
	public static function from_file( $file ) {
		if ( ! is_readable( $file ) ) {
			return new WP_Error( 'galerio_package_unreadable', sprintf( 'Cannot read %s.', $file ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$package = json_decode( file_get_contents( $file ), true );

		if ( ! is_array( $package ) || empty( $package['pods'] ) ) {
			return new WP_Error( 'galerio_package_invalid', sprintf( '%s is not a Pods package.', $file ) );
		}

		return $package;
	}

	/**
	 * Export this site's Pods configuration as a package.
	 *
	 * @param string[] $pod_names Pods to include; all when empty. Templates are always included.
	 * @return array|WP_Error
	 */
	public static function from_site( array $pod_names = array() ) {
		if ( ! function_exists( 'pods_api' ) ) {
			return new WP_Error( 'galerio_pods_missing', 'Pods is not active.' );
		}

		require_once PODS_DIR . 'components/Migrate-Packages/Migrate-Packages.php';

		$pod_ids = true;

		if ( $pod_names ) {
			$pod_ids = array();

			foreach ( $pod_names as $name ) {
				$pod = pods_api()->load_pod( array( 'name' => $name ), false );

				if ( ! $pod ) {
					return new WP_Error( 'galerio_pod_missing', sprintf( 'Pod "%s" does not exist on this site.', $name ) );
				}

				$pod_ids[] = (int) $pod['id'];
			}
		}

		$json = Pods_Migrate_Packages::export(
			array(
				'settings'  => array( 'all' ),
				'pods'      => $pod_ids,
				'templates' => true,
			)
		);

		$package = $json ? json_decode( $json, true ) : null;

		return is_array( $package ) ? $package : new WP_Error( 'galerio_export_failed', 'Pods exported nothing.' );
	}

	/**
	 * Remove site-specific values from a package so exports are reproducible.
	 *
	 * Pods and templates are sorted by name, keys are put in a fixed order,
	 * and IDs are renumbered from 1.
	 * Relationship sister IDs are remapped to the new numbers, so the package
	 * stays internally consistent for the importer's relationship repair.
	 *
	 * @param array $package Package.
	 * @return array
	 */
	public static function canonical( array $package ) {
		$result = array(
			'@meta' => array( 'version' => isset( $package['@meta']['version'] ) ? $package['@meta']['version'] : '' ),
		);

		if ( isset( $package['settings'] ) ) {
			$result['settings'] = array_diff_key( $package['settings'], array_flip( self::SITE_SETTINGS ) );
			ksort( $result['settings'], SORT_STRING );
		}

		$pods = isset( $package['pods'] ) ? $package['pods'] : array();
		usort( $pods, array( __CLASS__, 'compare_names' ) );

		$next_id = 1;
		$new_ids = array();

		foreach ( $pods as $p => $pod ) {
			$pod       = self::strip( $pod );
			$pod['id'] = $next_id++;

			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $g => $group ) {
				$group           = self::strip( $group );
				$group['id']     = $next_id++;
				$group['fields'] = isset( $group['fields'] ) ? array_values( $group['fields'] ) : array();

				foreach ( $group['fields'] as $f => $field ) {
					$field = self::strip( $field );

					$new_ids[ (int) $field['id'] ] = $next_id;
					$field['id']                   = $next_id++;

					$group['fields'][ $f ] = $field;
				}

				$pod['groups'][ $g ] = $group;
			}

			$pods[ $p ] = $pod;
		}

		foreach ( $pods as $p => $pod ) {
			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $g => $group ) {
				foreach ( isset( $group['fields'] ) ? $group['fields'] : array() as $f => $field ) {
					$old = isset( $field['sister_id'] ) ? (int) $field['sister_id'] : 0;

					// Only relationships have sisters; drop the empty values other fields carry.
					unset( $field['sister_id'] );

					if ( $old && isset( $new_ids[ $old ] ) ) {
						$field['sister_id'] = (string) $new_ids[ $old ];
					}

					$pods[ $p ]['groups'][ $g ]['fields'][ $f ] = self::order_keys( $field );
				}
			}
		}

		foreach ( $pods as $p => $pod ) {
			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $g => $group ) {
				$pod['groups'][ $g ] = self::order_keys( $group );
			}

			$pods[ $p ] = self::order_keys( $pod );
		}

		$result['pods'] = $pods;

		if ( isset( $package['templates'] ) ) {
			$templates = $package['templates'];
			usort( $templates, array( __CLASS__, 'compare_names' ) );

			foreach ( $templates as $t => $template ) {
				$template        = self::strip( $template );
				$template['id']  = $next_id++;
				$templates[ $t ] = self::order_keys( $template );
			}

			$result['templates'] = $templates;
		}

		return $result;
	}

	/**
	 * Encode a package as JSON, in the format Pods itself exports.
	 *
	 * @param array $package Package.
	 * @return string
	 */
	public static function encode( array $package ) {
		return wp_json_encode( $package, JSON_PRETTY_PRINT ) . "\n";
	}

	/**
	 * Compare two packages by name.
	 *
	 * IDs and site-specific values are ignored, and relationships are compared
	 * by the name of the field they pair with.
	 *
	 * @param array $from Package to compare against, such as the repository's.
	 * @param array $to   Package to compare, such as the site's.
	 * @return array[] Changes, each with `change` ("added", "removed" or
	 *                 "changed"), `item`, `setting`, `from` and `to`.
	 */
	public static function diff( array $from, array $to ) {
		$from    = self::keyed( $from );
		$to      = self::keyed( $to );
		$changes = array();

		foreach ( array_unique( array_merge( array_keys( $from ), array_keys( $to ) ) ) as $item ) {
			if ( ! isset( $to[ $item ] ) ) {
				$changes[] = self::change( 'removed', $item, '', '', '' );
				continue;
			}

			if ( ! isset( $from[ $item ] ) ) {
				$changes[] = self::change( 'added', $item, '', '', '' );
				continue;
			}

			foreach ( array_unique( array_merge( array_keys( $from[ $item ] ), array_keys( $to[ $item ] ) ) ) as $setting ) {
				$old = array_key_exists( $setting, $from[ $item ] ) ? $from[ $item ][ $setting ] : null;
				$new = array_key_exists( $setting, $to[ $item ] ) ? $to[ $item ][ $setting ] : null;

				if ( self::same( $old, $new ) ) {
					continue;
				}

				$change    = null === $old ? 'added' : ( null === $new ? 'removed' : 'changed' );
				$changes[] = self::change( $change, $item, $setting, $old, $new );
			}
		}

		return $changes;
	}

	/**
	 * Each pod's fields from a package, whether grouped or flat.
	 *
	 * @param array $package Package.
	 * @return array<string, array[]> Pod name => field definitions, with each field's group name under `_group`.
	 */
	public static function fields( array $package ) {
		$result = array();

		foreach ( $package['pods'] as $pod ) {
			$fields = array();

			foreach ( isset( $pod['fields'] ) ? (array) $pod['fields'] : array() as $field ) {
				$fields[] = $field + array( '_group' => '' );
			}

			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $group ) {
				foreach ( (array) $group['fields'] as $field ) {
					$fields[] = $field + array( '_group' => $group['name'] );
				}
			}

			$result[ $pod['name'] ] = $fields;
		}

		return $result;
	}

	/**
	 * Flatten a package into items keyed by name, for comparison.
	 *
	 * Items are "settings", "pod <name>", "group <pod>/<group>",
	 * "field <pod>.<field>" and "template <name>".
	 *
	 * @param array $package Package.
	 * @return array<string, array> Item => settings.
	 */
	private static function keyed( array $package ) {
		$items = array();

		if ( isset( $package['settings'] ) ) {
			$items['settings'] = array_diff_key( $package['settings'], array_flip( self::SITE_SETTINGS ) );
		}

		$fields = self::fields( $package );
		$names  = array();

		foreach ( $fields as $pod => $pod_fields ) {
			foreach ( $pod_fields as $field ) {
				if ( isset( $field['id'] ) ) {
					$names[ (int) $field['id'] ] = $pod . '.' . $field['name'];
				}
			}
		}

		foreach ( $package['pods'] as $pod ) {
			$settings = self::strip( $pod );
			unset( $settings['id'], $settings['groups'], $settings['fields'] );
			$items[ 'pod ' . $pod['name'] ] = $settings;

			foreach ( isset( $pod['groups'] ) ? $pod['groups'] : array() as $group ) {
				$settings = self::strip( $group );
				unset( $settings['id'], $settings['fields'] );
				$items[ 'group ' . $pod['name'] . '/' . $group['name'] ] = $settings;
			}

			foreach ( $fields[ $pod['name'] ] as $field ) {
				$settings = self::strip( $field );
				unset( $settings['id'], $settings['sister_id'] );

				if ( isset( $field['type'] ) && 'pick' === $field['type'] && ! empty( $field['sister_id'] ) ) {
					$sister             = (int) $field['sister_id'];
					$settings['sister'] = isset( $names[ $sister ] ) ? $names[ $sister ] : '(missing)';
				}

				$items[ 'field ' . $pod['name'] . '.' . $field['name'] ] = $settings;
			}
		}

		foreach ( isset( $package['templates'] ) ? $package['templates'] : array() as $template ) {
			$settings = self::strip( $template );
			unset( $settings['id'] );

			if ( isset( $settings['code'] ) ) {
				$settings['code'] = str_replace( "\r\n", "\n", $settings['code'] );
			}

			$items[ 'template ' . $template['name'] ] = $settings;
		}

		return $items;
	}

	/**
	 * Put a definition's keys in a fixed order: identity first, nested lists
	 * last, everything else alphabetically. Pods exports keys in the order
	 * they were stored, which differs between sites.
	 *
	 * @param array $data Pod, group, field or template.
	 * @return array
	 */
	private static function order_keys( array $data ) {
		$first = array_intersect_key( array_flip( array( 'id', 'name', 'label', 'type' ) ), $data );
		$last  = array_intersect_key( array_flip( array( 'groups', 'fields' ) ), $data );
		$rest  = array_diff_key( $data, $first, $last );

		ksort( $rest, SORT_STRING );

		return array_merge( array_intersect_key( $data, $first ), $rest, array_intersect_key( $data, $last ) );
	}

	/**
	 * Remove site-specific keys from a pod, group, field or template.
	 *
	 * @param array $data Definition.
	 * @return array
	 */
	private static function strip( array $data ) {
		foreach ( array_keys( $data ) as $key ) {
			if ( in_array( $key, self::IGNORED_KEYS, true ) ) {
				unset( $data[ $key ] );
				continue;
			}

			foreach ( self::SITE_KEY_PREFIXES as $prefix ) {
				if ( 0 === strpos( (string) $key, $prefix ) ) {
					unset( $data[ $key ] );
				}
			}
		}

		return $data;
	}

	/**
	 * Whether two setting values are equivalent.
	 *
	 * Pods stores most values as strings, so scalars compare as strings, and
	 * a missing setting matches an empty one.
	 *
	 * @param mixed $a Value, or null when missing.
	 * @param mixed $b Value, or null when missing.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		if ( null === $a || null === $b ) {
			return '' === ( null === $a ? $b : $a ) || $a === $b;
		}

		if ( is_scalar( $a ) && is_scalar( $b ) ) {
			return (string) $a === (string) $b;
		}

		return $a === $b;
	}

	/**
	 * Build a change record.
	 *
	 * @param string $change  "added", "removed" or "changed".
	 * @param string $item    Item name.
	 * @param string $setting Setting name, or empty for the whole item.
	 * @param mixed  $from    Old value.
	 * @param mixed  $to      New value.
	 * @return array
	 */
	private static function change( $change, $item, $setting, $from, $to ) {
		return array(
			'change'  => $change,
			'item'    => $item,
			'setting' => $setting,
			'from'    => $from,
			'to'      => $to,
		);
	}

	/**
	 * Sort callback: by `name`.
	 *
	 * @param array $a Item.
	 * @param array $b Item.
	 * @return int
	 */
	private static function compare_names( $a, $b ) {
		return strcmp( $a['name'], $b['name'] );
	}
}
