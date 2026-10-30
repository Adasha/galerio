<?php
/**
 * Site-wide display switches.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Settings;

use Galerio\Core\Fields\Field;
use WP_Block;
use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * The switches on Settings → Galerio, each keeping something off the site's
 * pages. They change what is shown, not the fields: every field stays in the
 * editor, keeps its value and can still be used directly, e.g. in a Field
 * block or a query. A switch that was never saved counts as on.
 *
 * - Featured images: an item's own page leaves out its featured image (the
 *   banner at the top of profiles and articles, or a Post Featured Image
 *   block). Cards, archives and carousels still show them.
 * - Nationality and birth/death years: no profile shows them, whatever its
 *   own Show nationality or Show lifespan says. Everything that respects
 *   those (the profile template, Schema.org data) follows.
 * - Portraits: a profile's own page leaves out its portrait, whatever its
 *   Show portrait says, including the portrait standing in for its featured
 *   image. Cards elsewhere still show it.
 * - Collective fields: profile pages leave out Is a collective, Members and
 *   Member of, in Field blocks or bound blocks.
 *
 * The catalogue's switch is on the same page, but works the other way
 * round: it's off until a site turns it on (Galerio\Core\Catalogue).
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
	 * Switches that turn off a profile's own display switch: switch => field.
	 */
	const PROFILE_SWITCHES = array(
		'show_nationality'        => 'profile.show_nationality',
		'show_birth_death_fields' => 'profile.show_lifespan',
		'show_portraits'          => 'profile.show_portrait',
	);

	/**
	 * Fields profile pages leave out while a switch is off: switch => fields.
	 */
	const PROFILE_PAGE_FIELDS = array(
		'show_collective_fields' => array( 'profile.is_collective', 'profile.members', 'profile.member_of' ),
	);

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_filter( 'galerio_field_value', array( __CLASS__, 'field_value' ), 10, 3 );
		add_filter( 'render_block', array( __CLASS__, 'profile_page_block' ), 10, 2 );
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
		 * Filters whether a Galerio display switch is on.
		 *
		 * @param bool   $on   Whether the switch is on.
		 * @param string $name Switch, e.g. show_featured_images.
		 */
		return (bool) apply_filters( 'galerio_feature_enabled', $on, $name );
	}

	/**
	 * Switches that apply only on the profile's own page.
	 */
	const OWN_PAGE_ONLY = array( 'show_portraits' );

	/**
	 * A profile's own display switch reads as off while the site's is off.
	 *
	 * @param mixed $value     Value.
	 * @param Field $field     Field.
	 * @param int   $object_id Post ID.
	 * @return mixed
	 */
	public static function field_value( $value, $field, $object_id = 0 ) {
		$name = array_search( $field->key, self::PROFILE_SWITCHES, true );

		if ( false === $name || self::enabled( $name ) ) {
			return $value;
		}

		if ( in_array( $name, self::OWN_PAGE_ONLY, true ) && ! ( is_singular( 'galerio_profile' ) && (int) get_queried_object_id() === (int) $object_id ) ) {
			return $value;
		}

		return false;
	}

	/**
	 * Field keys profile pages leave out.
	 *
	 * @return string[]
	 */
	public static function hidden_on_profile_pages() {
		$keys = array();

		foreach ( self::PROFILE_PAGE_FIELDS as $name => $fields ) {
			if ( ! self::enabled( $name ) ) {
				$keys = array_merge( $keys, $fields );
			}
		}

		return $keys;
	}

	/**
	 * On a profile's page, leave out Field blocks and bound blocks that show
	 * a field the switches hide there.
	 *
	 * @param string $content Rendered block.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function profile_page_block( $content, $block ) {
		if ( ! is_singular( 'galerio_profile' ) ) {
			return $content;
		}

		$hidden = self::hidden_on_profile_pages();

		if ( ! $hidden ) {
			return $content;
		}

		$keys = array();

		if ( 'galerio/field-display' === $block['blockName'] && ! empty( $block['attrs']['key'] ) ) {
			$keys[] = $block['attrs']['key'];
		}

		foreach ( (array) ( $block['attrs']['metadata']['bindings'] ?? array() ) as $binding ) {
			if ( 'galerio/field' === ( $binding['source'] ?? '' ) && ! empty( $binding['args']['key'] ) ) {
				$keys[] = $binding['args']['key'];
			}
		}

		foreach ( $keys as $key ) {
			$key = false === strpos( $key, '.' ) ? 'profile.' . $key : $key;

			if ( in_array( $key, $hidden, true ) ) {
				return '';
			}
		}

		return $content;
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
