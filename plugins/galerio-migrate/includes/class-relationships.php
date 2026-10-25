<?php
/**
 * Pods' relationship table, rebuilt from the lists the site shows.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Makes Pods' relationship table (wp_podsrel) match each item's own lists.
 *
 * Pods keeps a relationship or image list twice: in the item's meta, which
 * the site shows, and in wp_podsrel, which Pods reads when it syncs two-way
 * relationships. On an old site the two drift apart, and moving items to
 * another post type leaves wp_podsrel pointing at the old type's fields, so
 * the next edit through Pods could rebuild a list from the wrong copy.
 *
 * First the two sides of each two-way relationship are made to agree: when
 * an exhibition lists a profile but the profile doesn't list the exhibition,
 * the profile gains it, so nothing shown anywhere is lost. Then each list is
 * written to wp_podsrel as it stands in meta, without Pods' syncing, and rows
 * for fields that no longer exist are removed.
 */
final class Relationships {

	const STEP = 'Relationships';

	/**
	 * What this run did.
	 *
	 * @var Report
	 */
	private $report;

	/**
	 * Report without writing.
	 *
	 * @var bool
	 */
	private $dry;

	/**
	 * Set up a run.
	 *
	 * @param Report $report Report to add to.
	 * @param bool   $dry    Report without writing.
	 */
	public function __construct( Report $report, $dry ) {
		$this->report = $report;
		$this->dry    = (bool) $dry;
	}

	/**
	 * Reconcile two-way lists, then rewrite wp_podsrel from them.
	 */
	public function rebuild() {
		if ( ! function_exists( 'pods_podsrel_enabled' ) || ! pods_podsrel_enabled() ) {
			$this->report->note( self::STEP, "Pods' relationship table is off, so there was nothing to rebuild." );
			return;
		}

		$fields = $this->fields();

		$this->reconcile( $fields );

		foreach ( $fields as $field ) {
			$this->write_rows( $field, $fields );
		}

		$this->remove_orphans();
	}

	/**
	 * The relationship and file fields of the site's post type, media and user pods, by field ID.
	 *
	 * @return array<int, array{id: int, name: string, pod_id: int, pod: string, type: string, sister: int, single: bool}>
	 */
	private function fields() {
		$fields = array();

		foreach ( pods_api()->load_pods() as $pod ) {
			if ( ! in_array( $pod['type'], array( 'post_type', 'media', 'user' ), true ) ) {
				continue;
			}

			foreach ( $pod['fields'] as $field ) {
				$is_pick = 'pick' === $field['type'] && ! in_array( (string) $field['pick_object'], array( '', 'custom-simple' ), true );

				if ( ! $is_pick && 'file' !== $field['type'] ) {
					continue;
				}

				$format = 'file' === $field['type'] ? $field['file_format_type'] : $field['pick_format_type'];

				$fields[ (int) $field['id'] ] = array(
					'id'     => (int) $field['id'],
					'name'   => (string) $field['name'],
					'pod_id' => (int) $pod['id'],
					'pod'    => (string) $pod['name'],
					'type'   => (string) $pod['type'],
					'sister' => $is_pick ? (int) $field['sister_id'] : 0,
					'single' => 'single' === $format,
				);
			}
		}

		return $fields;
	}

	/**
	 * Make both sides of each two-way relationship list each other.
	 *
	 * @param array $fields Fields, from fields().
	 */
	private function reconcile( array $fields ) {
		foreach ( $fields as $id => $field ) {
			$sister = isset( $fields[ $field['sister'] ] ) ? $fields[ $field['sister'] ] : null;

			// Each pair once, from its lower field ID; both directions are checked.
			if ( ! $sister || $id > $sister['id'] || 'user' === $field['type'] || 'user' === $sister['type'] ) {
				continue;
			}

			$this->reconcile_side( $field, $sister );
			$this->reconcile_side( $sister, $field );
		}
	}

	/**
	 * Add each item of a field's lists to the list of its sister on the other item.
	 *
	 * @param array $field  Field whose lists are read.
	 * @param array $sister Its sister, whose lists gain what's missing.
	 */
	private function reconcile_side( array $field, array $sister ) {
		foreach ( $this->items( $field ) as $item ) {
			foreach ( $this->list_of( $field, $item ) as $related ) {
				if ( get_post_type( $related ) !== ( 'media' === $sister['type'] ? 'attachment' : $sister['pod'] ) ) {
					$this->report->count( self::STEP, 'links to deleted or other items, left alone' );
					continue;
				}

				$theirs = $this->list_of( $sister, $related );

				if ( in_array( $item, $theirs, true ) ) {
					continue;
				}

				$label = sprintf( '%s (#%d) %s.%s gains %s (#%d), which lists it in %s.%s', get_the_title( $related ), $related, $sister['pod'], $sister['name'], get_the_title( $item ), $item, $field['pod'], $field['name'] );

				if ( $sister['single'] && $theirs ) {
					$this->report->count( self::STEP, 'one-sided, left for a person' );
					$this->report->note( self::STEP, "Not done, as it already holds one: $label." );
					continue;
				}

				$this->report->count( self::STEP, $this->dry ? 'one-sided, would add' : 'one-sided, added' );
				$this->report->note( self::STEP, "$label." );

				if ( ! $this->dry ) {
					$theirs[] = $item;
					$this->save_list( $sister, $related, $theirs );
				}
			}
		}
	}

	/**
	 * Rewrite a field's wp_podsrel rows from each item's list, where they differ.
	 *
	 * @param array $field  Field.
	 * @param array $fields All fields, from fields().
	 */
	private function write_rows( array $field, array $fields ) {
		global $wpdb;

		$table         = $wpdb->prefix . 'podsrel';
		$sister        = isset( $fields[ $field['sister'] ] ) ? $fields[ $field['sister'] ] : null;
		$related_pod   = $sister ? $sister['pod_id'] : 0;
		$related_field = $sister ? $sister['id'] : 0;
		$already       = 0;
		$done          = 0;

		foreach ( $this->items( $field ) as $item ) {
			$ids = $this->list_of( $field, $item );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off migration; $table is the prefixed Pods table.
			$current = array_map(
				'intval',
				$wpdb->get_col( $wpdb->prepare( "SELECT related_item_id FROM $table WHERE pod_id = %d AND field_id = %d AND item_id = %d ORDER BY weight, id", $field['pod_id'], $field['id'], $item ) )
			);
			$linked  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE pod_id = %d AND field_id = %d AND item_id = %d AND (related_pod_id <> %d OR related_field_id <> %d)", $field['pod_id'], $field['id'], $item, $related_pod, $related_field ) );

			if ( $current === $ids && ! $linked ) {
				++$already;
				continue;
			}

			++$done;

			if ( $this->dry ) {
				continue;
			}

			$wpdb->delete(
				$table,
				array(
					'pod_id'   => $field['pod_id'],
					'field_id' => $field['id'],
					'item_id'  => $item,
				)
			);

			foreach ( $ids as $weight => $related ) {
				$wpdb->insert(
					$table,
					array(
						'pod_id'           => $field['pod_id'],
						'field_id'         => $field['id'],
						'item_id'          => $item,
						'related_pod_id'   => $related_pod,
						'related_field_id' => $related_field,
						'related_item_id'  => $related,
						'weight'           => $weight,
					)
				);
			}
			// phpcs:enable
		}

		if ( $already ) {
			$this->report->count( self::STEP, 'lists already matching', $already );
		}
		if ( $done ) {
			$this->report->count( self::STEP, $this->dry ? 'lists to rewrite' : 'lists rewritten', $done );
		}
	}

	/**
	 * Remove wp_podsrel rows for fields or pods that no longer exist, such as the old post type's.
	 */
	private function remove_orphans() {
		global $wpdb;

		$table = $wpdb->prefix . 'podsrel';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off migration; $table is the prefixed Pods table.
		$where  = "field_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = '_pods_field') OR pod_id NOT IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = '_pods_pod')";
		$orphan = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );

		if ( ! $orphan ) {
			return;
		}

		$this->report->count( self::STEP, $this->dry ? 'rows of removed fields to delete' : 'rows of removed fields deleted', $orphan );

		if ( ! $this->dry ) {
			$wpdb->query( "DELETE FROM $table WHERE $where" );
		}
		// phpcs:enable
	}

	/**
	 * The items a field's pod holds: posts of its type, attachments or users.
	 *
	 * @param array $field Field.
	 * @return int[]
	 */
	private function items( array $field ) {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration.
		if ( 'user' === $field['type'] ) {
			return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key IN (%s, %s)", $field['name'], '_pods_' . $field['name'] ) ) );
		}

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' ORDER BY ID", 'media' === $field['type'] ? 'attachment' : $field['pod'] ) ) );
		// phpcs:enable
	}

	/**
	 * An item's list for a field, as the site shows it: Pods' ordered list, or else every stored value.
	 *
	 * @param array $field Field.
	 * @param int   $item  Item ID.
	 * @return int[]
	 */
	private function list_of( array $field, $item ) {
		global $wpdb;

		$table = 'user' === $field['type'] ? $wpdb->usermeta : $wpdb->postmeta;
		$col   = 'user' === $field['type'] ? 'user_id' : 'post_id';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-off migration, reading past Pods' filters; $table and $col are core table and column names.
		$ordered = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $table WHERE $col = %d AND meta_key = %s LIMIT 1", $item, '_pods_' . $field['name'] ) );
		$values  = null !== $ordered && is_array( maybe_unserialize( $ordered ) )
			? maybe_unserialize( $ordered )
			: $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM $table WHERE $col = %d AND meta_key = %s ORDER BY meta_id", $item, $field['name'] ) );
		// phpcs:enable

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $values ) ) ) );
	}

	/**
	 * Store an item's list for a field in meta, the way Pods does: one row per
	 * item plus its ordered list. Written directly, so Pods doesn't sync it.
	 *
	 * @param array $field Field.
	 * @param int   $item  Item ID.
	 * @param int[] $ids  IDs.
	 */
	private function save_list( array $field, $item, array $ids ) {
		global $wpdb;

		$table = 'user' === $field['type'] ? $wpdb->usermeta : $wpdb->postmeta;
		$col   = 'user' === $field['type'] ? 'user_id' : 'post_id';
		$ids   = array_values( array_map( 'intval', $ids ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- One-off migration; Pods' own meta functions would sync the change.
		$wpdb->delete(
			$table,
			array(
				$col       => $item,
				'meta_key' => $field['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
			)
		);
		$wpdb->delete(
			$table,
			array(
				$col       => $item,
				'meta_key' => '_pods_' . $field['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
			)
		);

		foreach ( $ids as $related ) {
			$wpdb->insert(
				$table,
				array(
					$col         => $item,
					'meta_key'   => $field['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
					'meta_value' => (string) $related, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off migration.
				)
			);
		}

		$wpdb->insert(
			$table,
			array(
				$col         => $item,
				'meta_key'   => '_pods_' . $field['name'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
				'meta_value' => maybe_serialize( $ids ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off migration.
			)
		);
		// phpcs:enable

		wp_cache_delete( $item, 'user' === $field['type'] ? 'user_meta' : 'post_meta' );
	}
}
