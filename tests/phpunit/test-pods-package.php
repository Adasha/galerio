<?php
/**
 * Pods package consistency tests.
 *
 * @package Galerio\Tests
 */

/**
 * Checks data/pods/galerio.json for broken references, and that it agrees
 * with the field registry.
 *
 * @coversNothing
 */
class Test_Pods_Package extends WP_UnitTestCase {

	/**
	 * Registry field types each Pods field type may back.
	 *
	 * @var array<string, string[]>
	 */
	const COMPATIBLE_TYPES = array(
		'text'         => array( 'text' ),
		'paragraph'    => array( 'textarea' ),
		'wysiwyg'      => array( 'html' ),
		'website'      => array( 'url' ),
		'oembed'       => array( 'embed' ),
		'date'         => array( 'date', 'year' ),
		'boolean'      => array( 'boolean' ),
		'file'         => array( 'image' ),
		'pick'         => array( 'relation' ),
		'galerio_link' => array( 'link' ),
	);

	/**
	 * Magic tags Pods provides itself: post columns and loop values.
	 *
	 * @var string[]
	 */
	const BUILT_IN_TAGS = array( '_value', 'ID', 'guid', 'name', 'permalink', 'post_author', 'post_content', 'post_date', 'post_excerpt', 'post_name', 'post_status', 'post_thumbnail', 'post_thumbnail_url', 'post_title', 'post_type' );

	/**
	 * Decoded package.
	 *
	 * @var array
	 */
	private static $package;

	/**
	 * Load the package.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		self::$package = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/data/pods/galerio.json' ), true );
	}

	/**
	 * Fields of every pod, keyed "pod.field".
	 *
	 * @return array<string, array>
	 */
	private function fields() {
		$fields = array();

		foreach ( self::$package['pods'] as $pod ) {
			foreach ( $pod['groups'] as $group ) {
				foreach ( $group['fields'] as $field ) {
					$field['pod']                                  = $pod['name'];
					$fields[ $pod['name'] . '.' . $field['name'] ] = $field;
				}
			}
		}

		return $fields;
	}

	/**
	 * Names of the package's pods.
	 *
	 * @return string[]
	 */
	private function pod_names() {
		return wp_list_pluck( self::$package['pods'], 'name' );
	}

	/**
	 * Relationship fields point at pods or core types that exist.
	 */
	public function test_relationship_targets_exist() {
		$known = array_merge( $this->pod_names(), array( 'post', 'page', 'attachment', 'category', 'post_tag' ) );

		foreach ( $this->fields() as $key => $field ) {
			if ( 'pick' === $field['type'] && in_array( $field['pick_object'], array( 'post_type', 'taxonomy' ), true ) ) {
				$this->assertContains( $field['pick_val'], $known, "$key relates to an unknown type." );
			}
		}
	}

	/**
	 * Two-way relationships point at each other.
	 */
	public function test_sister_fields_are_mutual() {
		$fields = $this->fields();
		$by_id  = array();

		foreach ( $fields as $key => $field ) {
			$by_id[ (int) $field['id'] ] = $key;
		}

		foreach ( $fields as $key => $field ) {
			if ( 'pick' !== $field['type'] || empty( $field['sister_id'] ) ) {
				continue;
			}

			$this->assertArrayHasKey( (int) $field['sister_id'], $by_id, "$key has a sister field that is not in the package." );

			$sister = $fields[ $by_id[ (int) $field['sister_id'] ] ];

			$this->assertSame( (int) $field['id'], (int) $sister['sister_id'], "$key and its sister do not point at each other." );
			$this->assertSame( $field['pod'], $sister['pick_val'], "The sister of $key relates to a different pod." );
			$this->assertSame( $sister['pod'], $field['pick_val'], "$key relates to a different pod from its sister." );
		}
	}

	/**
	 * Pods Templates only use fields that exist.
	 */
	public function test_templates_use_existing_fields() {
		$names = array_merge( wp_list_pluck( $this->fields(), 'name' ), $this->pod_names(), self::BUILT_IN_TAGS );

		foreach ( self::$package['templates'] as $template ) {
			preg_match_all( '/\{@([a-z0-9_]+)|\[(?:each|if) (?:field=")?([a-z0-9_]+)/i', $template['code'], $matches );

			foreach ( array_filter( array_merge( $matches[1], $matches[2] ) ) as $name ) {
				$this->assertContains( $name, $names, "Template \"{$template['name']}\" uses unknown field \"$name\"." );
			}
		}
	}

	/**
	 * Every registry field is backed by a Pods field of a compatible type.
	 */
	public function test_registry_matches_package() {
		$fields = $this->fields();

		foreach ( galerio_fields()->all() as $field ) {
			$pod = 'attachment' === $field->object ? 'media' : Galerio\Core\Fields\Registry::OBJECTS[ $field->object ];
			$key = $pod . '.' . $field->source;

			$this->assertArrayHasKey( $key, $fields, "{$field->key} has no Pods field $key." );

			$pods_field = $fields[ $key ];

			$this->assertContains( $field->type, self::COMPATIBLE_TYPES[ $pods_field['type'] ], "{$field->key} has a different type from $key." );
			$this->assertSame( $field->multiple, $this->pods_field_is_multiple( $pods_field ), "{$field->key} and $key disagree on holding several values." );

			if ( 'relation' === $field->type ) {
				$this->assertSame( Galerio\Core\Fields\Registry::OBJECTS[ $field->target ], $pods_field['pick_val'], "{$field->key} relates to a different type from $key." );
			}
		}
	}

	/**
	 * Whether a Pods field holds several values.
	 *
	 * @param array $field Pods field definition.
	 * @return bool
	 */
	private function pods_field_is_multiple( array $field ) {
		switch ( $field['type'] ) {
			case 'pick':
				return 'multi' === $field['pick_format_type'];
			case 'file':
				return 'multi' === $field['file_format_type'];
			case 'galerio_link':
				return '1' === (string) $field['galerio_link_multiple'];
			default:
				return ! empty( $field[ $field['type'] . '_repeatable' ] ) || ! empty( $field['repeatable'] );
		}
	}
}
