<?php
/**
 * Public field API.
 *
 * @package Galerio\Core
 */

use Galerio\Core\Fields\Display;
use Galerio\Core\Fields\Meta_Adapter;
use Galerio\Core\Fields\Pods_Adapter;
use Galerio\Core\Types\Content_Types;
use Galerio\Core\Fields\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * The shared field registry.
 *
 * @return Registry
 */
function galerio_fields() {
	static $registry = null;

	if ( null === $registry ) {
		/**
		 * Filters the storage adapter fields are read through.
		 *
		 * @param Galerio\Core\Fields\Adapter $adapter Adapter. Defaults to post meta on sites that have moved to native data, and to Pods on the others.
		 */
		$adapter  = apply_filters( 'galerio_field_adapter', Content_Types::active() ? new Meta_Adapter() : new Pods_Adapter() );
		$registry = new Registry( $adapter, require __DIR__ . '/default-fields.php' );
	}

	return $registry;
}

/**
 * Get a field's value for a post.
 *
 * Raw values are normalised by type: strings for text, "Y-m-d" for dates,
 * integers for years, booleans, and post IDs for images and relations. Fields
 * that hold several values return lists. Display values are escaped HTML.
 *
 * @param string           $key     Field key, e.g. "work.year", or a bare name such as "year" for the post's own type.
 * @param int|WP_Post|null $post    Post, defaulting to the current one.
 * @param string           $context "raw" or "display".
 * @return mixed Null when the field does not exist for this post.
 */
function galerio_get_field( $key, $post = null, $context = 'raw' ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return null;
	}

	$field = galerio_fields()->resolve( $key, $post );

	if ( ! $field ) {
		return null;
	}

	if ( $field->deprecated ) {
		_doing_it_wrong( __FUNCTION__, esc_html( sprintf( '%s: %s', $field->key, $field->deprecated ) ), '0.2.0' );
	}

	$raw = galerio_fields()->value( $field, $post->ID );

	return 'display' === $context ? Display::render( $field, $raw, $post->ID ) : $raw;
}

/**
 * Get the posts an image or relationship field points to, in their saved order.
 *
 * Unpublished related posts are left out.
 *
 * @param string           $key  Field key or bare name, as for galerio_get_field().
 * @param int|WP_Post|null $post Post, defaulting to the current one.
 * @return WP_Post[]
 */
function galerio_get_related( $key, $post = null ) {
	$post  = get_post( $post );
	$field = $post ? galerio_fields()->resolve( $key, $post ) : null;

	if ( ! $field || ! $field->holds_ids() ) {
		return array();
	}

	$ids = array_filter( (array) galerio_fields()->value( $field, $post->ID ) );

	if ( ! $ids ) {
		return array();
	}

	$is_image = 'image' === $field->type;

	return get_posts(
		array(
			'post_type'           => $is_image ? 'attachment' : Registry::OBJECTS[ $field->target ],
			'post_status'         => $is_image ? 'inherit' : 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/**
 * Put the site's names for its content types into a text.
 *
 * Tokens such as "{Collections}" or "{works}" become the names set under
 * Settings → Galerio content types. See Galerio\Core\Aliases\Names.
 *
 * @param string $text Text with name tokens.
 * @return string
 */
function galerio_expand_names( $text ) {
	return Galerio\Core\Aliases\Names::expand( $text );
}
