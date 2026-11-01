<?php
/**
 * Checks that stored fields can be read and kept without Pods.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Fields;

use Galerio\Core\Types\Content_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Compares what's stored with what Galerio reads, and looks for anything that
 * depends on Pods. It only reads.
 *
 * Stored values are read from the database directly, because Pods answers
 * get_post_meta() itself for its file and relationship fields.
 */
final class Check {

	/**
	 * Level of a result that needs looking at.
	 */
	const PROBLEM = 'problem';

	/**
	 * Level of a result that's for information.
	 */
	const NOTE = 'note';

	/**
	 * Fields and taxonomies deliberately left out of Galerio for now. Their
	 * data stays stored, so it's a note rather than a problem.
	 */
	const LEFT_OUT = array( 'attachment.intent', 'media_intent', 'user.profile' );

	/**
	 * Fields to check.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Adapter to compare stored values with, if any.
	 *
	 * @var Adapter|null
	 */
	private $adapter;

	/**
	 * Results so far.
	 *
	 * @var array[]
	 */
	private $results = array();

	/**
	 * Create a check.
	 *
	 * @param Registry     $registry Fields to check.
	 * @param Adapter|null $adapter  Adapter to compare stored values with.
	 */
	public function __construct( Registry $registry, ?Adapter $adapter = null ) {
		$this->registry = $registry;
		$this->adapter  = $adapter;
	}

	/**
	 * Run every check.
	 *
	 * @return array[] Results, each with level, check, item and detail.
	 */
	public function run() {
		$this->results = array();

		$fields = array_filter(
			$this->registry->all(),
			function ( Field $field ) {
				return ! $field->value_callback && isset( Registry::OBJECTS[ $field->object ] );
			}
		);

		if ( $this->adapter ) {
			$this->check_reads( $fields );
		}

		$this->check_relationships( $fields );
		$this->check_content();
		$this->check_definitions();
		$this->check_registration();

		return $this->results;
	}

	/**
	 * Add a result.
	 *
	 * @param string $level  Level.
	 * @param string $check  Which check.
	 * @param string $item   What it's about.
	 * @param string $detail What was found.
	 */
	private function add( $level, $check, $item, $detail ) {
		$this->results[] = array(
			'level'  => $level,
			'check'  => $check,
			'item'   => $item,
			'detail' => $detail,
		);
	}

	/**
	 * Add a result about data Galerio doesn't define.
	 *
	 * @param string $item   Field, type or taxonomy.
	 * @param string $detail What was found.
	 */
	private function add_left_behind( $item, $detail ) {
		if ( in_array( $item, self::LEFT_OUT, true ) ) {
			$this->add( self::NOTE, 'left out', $item, $detail . ', kept in the database' );
		} else {
			$this->add( self::PROBLEM, 'data left behind', $item, $detail );
		}
	}

	/**
	 * Each item of an object, whatever its status.
	 *
	 * @param string $object_name Object name.
	 * @return int[]
	 */
	private function items( $object_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", Registry::OBJECTS[ $object_name ] ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * The stored rows of a field, in the order WordPress reads them.
	 *
	 * @param Field $field Field.
	 * @return array<int, string[]> Rows by item.
	 */
	private function rows( Field $field ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pods answers get_post_meta() itself for these fields.
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id", $field->source ) );
		$rows  = array();

		foreach ( $found as $row ) {
			$rows[ (int) $row->post_id ][] = $row->meta_value;
		}

		return $rows;
	}

	/**
	 * A field's value as its stored rows give it.
	 *
	 * @param Field    $field Field.
	 * @param string[] $rows  Stored rows.
	 * @return mixed Normalised value.
	 */
	private function stored_value( Field $field, array $rows ) {
		if ( $field->multiple && 'link' !== $field->type ) {
			return $field->normalise( $rows );
		}

		return $field->normalise( $rows ? maybe_unserialize( $rows[0] ) : null );
	}

	/**
	 * Post IDs in a value, as a list.
	 *
	 * @param mixed $value Normalised value of a field holding IDs.
	 * @return int[]
	 */
	private static function ids( $value ) {
		return array_values( array_filter( array_map( 'intval', (array) $value ) ) );
	}

	/**
	 * Compare each field's stored value with what the adapter reads.
	 *
	 * Links to unpublished or missing items are expected to differ: Pods
	 * leaves them out of its reads, while the stored list keeps them.
	 *
	 * @param Field[] $fields Fields.
	 */
	private function check_reads( array $fields ) {
		foreach ( $fields as $field ) {
			$rows   = $this->rows( $field );
			$hidden = 0;

			foreach ( $this->items( $field->object ) as $id ) {
				$item_rows = isset( $rows[ $id ] ) ? $rows[ $id ] : array();

				// Only image and relationship fields are read from anywhere but these rows.
				if ( ! $item_rows && ! $field->holds_ids() ) {
					continue;
				}

				$read   = $field->normalise( $this->adapter->get( $field, $id ) );
				$stored = $this->stored_value( $field, $item_rows );

				if ( $read === $stored ) {
					continue;
				}

				if ( $field->holds_ids() ) {
					$visible = array_values(
						array_filter(
							self::ids( $stored ),
							function ( $target ) {
								return in_array( get_post_status( $target ), array( 'publish', 'inherit' ), true );
							}
						)
					);

					if ( self::ids( $read ) === $visible ) {
						++$hidden;
						continue;
					}
				}

				$this->add(
					self::PROBLEM,
					'reads differ',
					sprintf( '%s #%d', $field->key, $id ),
					sprintf( 'reads %s, stored %s', wp_json_encode( $read ), wp_json_encode( $stored ) )
				);
			}

			if ( $hidden ) {
				$this->add( self::NOTE, 'unpublished links', $field->key, sprintf( '%d item(s) link to something unpublished or missing, which Pods leaves out', $hidden ) );
			}
		}
	}

	/**
	 * Check that each side of a two-way relationship lists the other.
	 *
	 * @param Field[] $fields Fields.
	 */
	private function check_relationships( array $fields ) {
		$lists = array();
		$known = array();

		foreach ( $fields as $field ) {
			if ( 'relation' !== $field->type ) {
				continue;
			}

			$rows                 = $this->rows( $field );
			$lists[ $field->key ] = array();

			foreach ( $this->items( $field->object ) as $id ) {
				$known[ $field->object ][ $id ] = true;

				if ( isset( $rows[ $id ] ) ) {
					$lists[ $field->key ][ $id ] = self::ids( $rows[ $id ] );
				}
			}
		}

		foreach ( $fields as $field ) {
			if ( ! isset( $lists[ $field->key ] ) ) {
				continue;
			}

			$dangling = 0;

			foreach ( $lists[ $field->key ] as $id => $targets ) {
				foreach ( $targets as $target ) {
					if ( empty( $known[ $field->target ][ $target ] ) ) {
						++$dangling;
					} elseif ( $field->inverse && ( empty( $lists[ $field->inverse ][ $target ] ) || ! in_array( $id, $lists[ $field->inverse ][ $target ], true ) ) ) {
						$this->add(
							self::PROBLEM,
							'one-way link',
							sprintf( '%s #%d', $field->key, $id ),
							sprintf( 'lists #%d, but %s of #%d does not list it back', $target, $field->inverse, $target )
						);
					}
				}
			}

			if ( $dangling ) {
				$this->add( self::NOTE, 'missing links', $field->key, sprintf( '%d link(s) to items that no longer exist or are of another type', $dangling ) );
			}
		}
	}

	/**
	 * Look for Pods shortcodes and blocks in content and widgets.
	 */
	private function check_content() {
		global $wpdb;

		foreach ( array( '[pods', '<!-- wp:pods/' ) as $marker ) {
			$like = '%' . $wpdb->esc_like( $marker ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
			$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type <> 'revision' AND post_status NOT IN ( 'auto-draft', 'trash' )", $like ) );

			foreach ( $posts as $post ) {
				$this->add( self::PROBLEM, 'pods in content', sprintf( '%s #%d', $post->post_type, $post->ID ), sprintf( 'contains %s', $marker ) );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
			$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s", 'widget%', $like ) );

			foreach ( $options as $option ) {
				$this->add( self::PROBLEM, 'pods in content', $option, sprintf( 'contains %s', $marker ) );
			}
		}
	}

	/**
	 * Look for content in types, taxonomies and fields that this site's Pods
	 * configuration defines and Galerio doesn't, as they'd be left behind.
	 */
	private function check_definitions() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads Pods' definitions whether or not Pods is active.
		$pods = $wpdb->get_results(
			"SELECT p.ID, p.post_name, m.meta_value AS type FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = 'type'
			WHERE p.post_type = '_pods_pod'"
		);

		$taxonomies = Content_Types::taxonomies();

		foreach ( $pods as $pod ) {
			if ( 'post_type' === $pod->type && ! in_array( $pod->post_name, Registry::OBJECTS, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft'", $pod->post_name ) );

				if ( $count ) {
					$this->add_left_behind( $pod->post_name, sprintf( 'a post type Galerio does not define, with %d item(s)', $count ) );
				}
			} elseif ( 'taxonomy' === $pod->type && ! isset( $taxonomies[ $pod->post_name ] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $pod->post_name ) );

				if ( $count ) {
					$this->add_left_behind( $pod->post_name, sprintf( 'a taxonomy Galerio does not define, with %d term(s)', $count ) );
				}
			}

			$this->check_pod_fields( $pod );
		}
	}

	/**
	 * Look for settings of the registered types and taxonomies that Galerio's
	 * own definitions wouldn't keep.
	 */
	private function check_registration() {
		if ( Content_Types::active() ) {
			return;
		}

		foreach ( Content_Types::differences() as $difference ) {
			$this->add(
				self::PROBLEM,
				'registered differently',
				$difference['object'] . ' ' . $difference['property'],
				sprintf( 'is %s, would become %s', wp_json_encode( $difference['registered'] ), wp_json_encode( $difference['native'] ) )
			);
		}
	}

	/**
	 * Look for values in a pod's fields that Galerio doesn't define.
	 *
	 * @param object $pod Pod's ID, post_name and type.
	 */
	private function check_pod_fields( $pod ) {
		global $wpdb;

		$objects = array_flip( Registry::OBJECTS ) + array( 'media' => 'attachment' );

		if ( 'user' !== $pod->post_name && ! isset( $objects[ $pod->post_name ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads Pods' definitions whether or not Pods is active.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE post_type = '_pods_field' AND post_parent = %d", $pod->ID ) );

		foreach ( $names as $name ) {
			if ( 'user' === $pod->post_name ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A one-off check of what's stored.
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value NOT IN ( '', '0' )", $name ) );
				$key   = 'user.' . $name;
			} else {
				$object_name = $objects[ $pod->post_name ];
				$key         = $object_name . '.' . $name;

				if ( $this->registry->get( $key ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A one-off check of what's stored.
				$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = %s AND m.meta_value NOT IN ( '', '0' ) AND p.post_type = %s AND p.post_status <> 'auto-draft'", $name, Registry::OBJECTS[ $object_name ] ) );
			}

			if ( $count ) {
				$this->add_left_behind( $key, sprintf( 'a field Galerio does not define, with %d value(s)', $count ) );
			}
		}
	}
}
