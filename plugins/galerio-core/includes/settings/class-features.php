<?php
/**
 * Site-wide feature switches.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Settings;

use Galerio\Core\Fields\Field;
use Galerio\Core\Fields\Registry;
use WP_Block;
use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * The switches on Settings → Galerio, each turning a feature off for the whole
 * site. A switch that was never saved counts as on, so a site only loses a
 * feature when someone turns it off.
 *
 * - Featured images: an item's own page leaves out its featured image (the
 *   banner at the top of profiles and articles, or a Post Featured Image
 *   block). Cards, archives and carousels still show them.
 * - Birth/death fields and collective fields: those profile fields leave the
 *   editor and read as empty everywhere else (templates, Field blocks, block
 *   bindings, Schema.org data). Saved values are kept, and come back when the
 *   switch is turned on again.
 *
 * The switches are stored by the Pods settings page galerio_settings, as
 * galerio_settings_{name} options.
 */
final class Features {

	/**
	 * Option prefix of the Pods settings page.
	 */
	const PREFIX = 'galerio_settings_';

	/**
	 * Fields each switch turns off: switch => field keys.
	 */
	const FIELDS = array(
		'show_birth_death_fields' => array( 'profile.year_of_birth', 'profile.year_of_death', 'profile.show_lifespan' ),
		'show_collective_fields'  => array( 'profile.is_collective', 'profile.members', 'profile.member_of' ),
	);

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'galerio_field_value', array( __CLASS__, 'field_value' ), 10, 2 );
		add_filter( 'pods_meta_groups_get', array( __CLASS__, 'editor_groups' ), 10, 3 );
		add_filter( 'render_block_core/cover', array( __CLASS__, 'cover' ), 10, 3 );
		add_filter( 'render_block_core/post-featured-image', array( __CLASS__, 'featured_image' ), 10, 3 );
	}

	/**
	 * Whether a switch is on.
	 *
	 * @param string $name Switch, e.g. show_featured_images.
	 * @return bool
	 */
	public static function enabled( $name ) {
		$value = get_option( self::PREFIX . $name, null );
		$on    = null === $value || '' === $value || (bool) (int) $value;

		/**
		 * Filters whether a Galerio feature is on.
		 *
		 * @param bool   $on   Whether the feature is on.
		 * @param string $name Switch, e.g. show_featured_images.
		 */
		return (bool) apply_filters( 'galerio_feature_enabled', $on, $name );
	}

	/**
	 * Field keys turned off by switches that are off.
	 *
	 * @return string[]
	 */
	public static function disabled_fields() {
		$keys = array();

		foreach ( self::FIELDS as $name => $fields ) {
			if ( ! self::enabled( $name ) ) {
				$keys = array_merge( $keys, $fields );
			}
		}

		return $keys;
	}

	/**
	 * A turned-off field reads as empty.
	 *
	 * @param mixed $value Value.
	 * @param Field $field Field.
	 * @return mixed
	 */
	public static function field_value( $value, $field ) {
		return in_array( $field->key, self::disabled_fields(), true ) ? $field->empty_value() : $value;
	}

	/**
	 * Leave turned-off fields out of the editor's Pods panels. Values not
	 * shown are not saved, so they stay as they were.
	 *
	 * @param array  $groups Field groups.
	 * @param string $type   Pod type.
	 * @param string $name   Pod name.
	 * @return array
	 */
	public static function editor_groups( $groups, $type, $name ) {
		if ( 'post_type' !== $type || ! is_array( $groups ) ) {
			return $groups;
		}

		$object = array_search( $name, Registry::OBJECTS, true );
		$hidden = array();

		foreach ( self::disabled_fields() as $key ) {
			list( $field_object, $field_name ) = explode( '.', $key, 2 );
			if ( $field_object === $object ) {
				$hidden[] = $field_name;
			}
		}

		if ( ! $hidden ) {
			return $groups;
		}

		foreach ( $groups as $index => $group ) {
			if ( empty( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}

			$fields = $group['fields'];

			foreach ( $fields as $field_index => $field ) {
				if ( in_array( (string) $field['name'], $hidden, true ) ) {
					unset( $fields[ $field_index ] );
				}
			}

			if ( ! $fields ) {
				unset( $groups[ $index ] );
				continue;
			}

			$group['fields']  = $fields;
			$groups[ $index ] = $group;
		}

		return $groups;
	}

	/**
	 * Whether a block shows the featured image of the page being viewed.
	 *
	 * @param WP_Block $instance Block instance.
	 * @return bool
	 */
	private static function is_own_featured_image( $instance ) {
		if ( ! is_singular() || self::enabled( 'show_featured_images' ) ) {
			return false;
		}

		$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

		return (int) get_queried_object_id() === $post_id;
	}

	/**
	 * A cover showing the page's own featured image loses the image, or goes
	 * altogether when it has nothing else to show (the banner at the top of
	 * profiles and articles).
	 *
	 * @param string   $content  Rendered cover.
	 * @param array    $block    Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function cover( $content, $block, $instance ) {
		if ( empty( $block['attrs']['useFeaturedImage'] ) || ! self::is_own_featured_image( $instance ) ) {
			return $content;
		}

		$content = preg_replace( '/<img\b[^>]*\bwp-block-cover__image-background\b[^>]*>/', '', $content );

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}

		$cover = new WP_HTML_Tag_Processor( $content );
		if ( $cover->next_tag( array( 'class_name' => 'wp-block-cover' ) ) ) {
			$cover->add_class( 'galerio-cover-no-image' );
		}

		return $cover->get_updated_html();
	}

	/**
	 * A Post Featured Image block showing the page's own item goes.
	 *
	 * @param string   $content  Rendered block.
	 * @param array    $block    Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function featured_image( $content, $block, $instance ) {
		return self::is_own_featured_image( $instance ) ? '' : $content;
	}
}
