<?php
/**
 * The Carousel block.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the `galerio/carousel` block.
 *
 * The block has two sources. Its own query shows the latest items of a
 * content type (optionally in chosen terms), or items picked by hand, each as
 * a slide with its featured image and a caption. Or it wraps a core Query
 * Loop and turns the loop's items into slides, laid out by the loop's own
 * template.
 *
 * Both render the same wrapper and controls, which view.js brings to life
 * with Embla. Without JavaScript the slides scroll sideways instead.
 */
final class Carousel_Block {

	/**
	 * Orders the block's own query accepts.
	 */
	const ORDER_BY = array( 'date', 'title', 'modified', 'menu_order', 'rand' );

	/**
	 * Most slides the block's own query shows.
	 */
	const MAX_ITEMS = 20;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Register the block from its compiled metadata.
	 */
	public static function register() {
		$dir = GALERIO_BLOCKS_DIR . 'build/carousel';

		if ( ! file_exists( $dir . '/block.json' ) ) {
			return;
		}

		register_block_type( $dir, array( 'render_callback' => array( __CLASS__, 'render' ) ) );
		wp_set_script_translations( 'galerio-carousel-editor-script', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );
	}

	/**
	 * The transitions offered in the editor.
	 *
	 * The block's wrapper gets an `is-transition-{value}` class and passes the
	 * value to view.js, so a theme or plugin can add its own transition with
	 * this filter plus the `galerio.carousel.plugins` JavaScript filter.
	 *
	 * @return array[] Items with `label` and `value`.
	 */
	public static function transitions() {
		/**
		 * Filters the carousel's transitions.
		 *
		 * @param array[] $transitions Items with `label` and `value`.
		 */
		return apply_filters(
			'galerio_carousel_transitions',
			array(
				array(
					'label' => __( 'Slide', 'galerio-blocks' ),
					'value' => 'slide',
				),
				array(
					'label' => __( 'Fade', 'galerio-blocks' ),
					'value' => 'fade',
				),
			)
		);
	}

	/**
	 * Pass the transitions to the editor script.
	 */
	public static function editor_data() {
		wp_add_inline_script(
			'galerio-carousel-editor-script',
			'window.galerioCarousel = ' . wp_json_encode( array( 'transitions' => self::transitions() ) ) . ';',
			'before'
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Inner blocks' HTML (the Query Loop, when that is the source).
	 * @return string Empty when there is nothing to show.
	 */
	public static function render( $attributes, $content ) {
		$attributes = self::with_defaults( $attributes );
		$from_loop  = 'loop' === $attributes['source'];

		if ( $from_loop ) {
			if ( '' === trim( $content ) ) {
				return '';
			}
			$slides = $content;
		} else {
			$posts = get_posts( self::query_args( $attributes ) );

			if ( ! $posts ) {
				return self::in_editor() ? '<p>' . esc_html__( 'No items to show yet. Choose another content type or other terms.', 'galerio-blocks' ) . '</p>' : '';
			}

			$slides = '';
			foreach ( array_values( $posts ) as $index => $post ) {
				$slides .= self::slide( $post, $index, $attributes );
			}
			$slides = '<div class="wp-block-galerio-carousel__track">' . $slides . '</div>';
		}

		$wrapper = get_block_wrapper_attributes(
			array(
				'class'                 => implode( ' ', self::classes( $attributes ) ),
				'style'                 => self::style( $attributes ),
				'role'                  => 'region',
				'aria-roledescription'  => __( 'carousel', 'galerio-blocks' ),
				'aria-label'            => '' !== trim( $attributes['label'] ) ? $attributes['label'] : __( 'Carousel', 'galerio-blocks' ),
				'data-galerio-carousel' => wp_json_encode( self::settings( $attributes ) ),
			)
		);

		$controls = '';
		if ( $attributes['autoplay'] ) {
			$controls .= sprintf(
				'<button type="button" class="wp-block-galerio-carousel__pause" aria-label="%s"><span aria-hidden="true"></span></button>',
				esc_attr__( 'Pause the carousel', 'galerio-blocks' )
			);
		}
		if ( $attributes['showArrows'] && ( $from_loop || 'sides' === $attributes['arrowsPosition'] ) ) {
			$controls .= self::arrows();
		}
		if ( $attributes['showDots'] ) {
			$controls .= sprintf(
				'<div class="wp-block-galerio-carousel__dots" role="group" aria-label="%s"></div>',
				esc_attr__( 'Choose a slide', 'galerio-blocks' )
			);
		}

		return sprintf(
			'<div %1$s><div class="wp-block-galerio-carousel__viewport">%2$s</div>%3$s</div>',
			$wrapper,
			$slides,
			$controls ? '<div class="wp-block-galerio-carousel__controls">' . $controls . '</div>' : ''
		);
	}

	/**
	 * The arguments of the block's own query.
	 *
	 * @param array $attributes Block attributes.
	 * @return array WP_Query arguments.
	 */
	public static function query_args( $attributes ) {
		$attributes = self::with_defaults( $attributes );
		$post_type  = $attributes['postType'];

		if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
			$post_type = 'post';
		}

		$args = array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'posts_per_page'      => max( 1, min( self::MAX_ITEMS, (int) $attributes['perPage'] ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'suppress_filters'    => false,
		);

		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $attributes['postIds'] ) ) ) );

		if ( $ids ) {
			// Chosen items win over everything else, in the order they were chosen.
			$args['post__in']       = array_slice( $ids, 0, self::MAX_ITEMS );
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = count( $args['post__in'] );
		} else {
			$args['orderby'] = in_array( $attributes['orderBy'], self::ORDER_BY, true ) ? $attributes['orderBy'] : 'date';
			$args['order']   = 'asc' === strtolower( $attributes['order'] ) ? 'ASC' : 'DESC';

			$terms = array_values( array_filter( array_map( 'absint', (array) $attributes['terms'] ) ) );
			if ( $terms && is_object_in_taxonomy( $post_type, $attributes['taxonomy'] ) ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- A short list, set by an editor.
					array(
						'taxonomy' => $attributes['taxonomy'],
						'field'    => 'term_id',
						'terms'    => $terms,
					),
				);
			}
		}

		/**
		 * Filters the query of a Carousel block that uses its own query.
		 *
		 * @param array $args       WP_Query arguments.
		 * @param array $attributes Block attributes.
		 */
		return apply_filters( 'galerio_carousel_query_args', $args, $attributes );
	}

	/**
	 * One slide of the block's own query.
	 *
	 * @param WP_Post $post       Item.
	 * @param int     $index      Position, from 0.
	 * @param array   $attributes Block attributes.
	 * @return string
	 */
	private static function slide( WP_Post $post, $index, $attributes ) {
		$url   = get_permalink( $post );
		$title = get_the_title( $post );
		$image = '';

		$image_id = get_post_thumbnail_id( $post );
		if ( $image_id ) {
			$per_view = self::per_view( $attributes );
			$attr     = array(
				'class'   => 'wp-block-galerio-carousel__image',
				'sizes'   => 1 === $per_view ? '100vw' : sprintf( '(max-width: 781px) 100vw, %dvw', ceil( 100 / $per_view ) ),
				'loading' => 'lazy',
			);

			// The first slide is in view on load; the rest wait until they're needed.
			if ( 0 === $index ) {
				$attr['loading']       = false;
				$attr['fetchpriority'] = 'high';
			}

			$image = wp_get_attachment_image( $image_id, $attributes['imageSize'], false, $attr );
		}

		$caption = '';
		$button  = '' !== trim( $attributes['buttonText'] );

		if ( $attributes['showTitle'] && '' !== $title ) {
			$level    = max( 1, min( 6, (int) $attributes['titleLevel'] ) );
			$text     = $button ? wp_kses_post( $title ) : sprintf( '<a href="%s">%s</a>', esc_url( $url ), wp_kses_post( $title ) );
			$caption .= sprintf( '<h%1$d class="wp-block-galerio-carousel__title">%2$s</h%1$d>', $level, $text );
		}

		if ( $attributes['showExcerpt'] && has_excerpt( $post ) ) {
			$caption .= '<p class="wp-block-galerio-carousel__excerpt">' . esc_html( wp_strip_all_tags( get_the_excerpt( $post ) ) ) . '</p>';
		}

		if ( $button ) {
			$caption .= sprintf(
				'<a class="wp-block-galerio-carousel__button wp-element-button" href="%1$s">%2$s<span class="screen-reader-text">: %3$s</span></a>',
				esc_url( $url ),
				esc_html( $attributes['buttonText'] ),
				esc_html( wp_strip_all_tags( $title ) )
			);
		}

		$nav = $attributes['showArrows'] && 'caption' === $attributes['arrowsPosition'] ? self::arrows() : '';

		if ( '' !== $caption || '' !== $nav ) {
			$caption = '<div class="wp-block-galerio-carousel__caption">' . $nav . $caption . '</div>';
		}

		return sprintf(
			'<div class="wp-block-galerio-carousel__slide%1$s">%2$s%3$s</div>',
			$image ? ' has-image' : '',
			$image,
			$caption
		);
	}

	/**
	 * The previous and next buttons.
	 *
	 * @return string
	 */
	private static function arrows() {
		$icon = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="%s" fill="currentColor"/></svg>';

		return sprintf(
			'<div class="wp-block-galerio-carousel__nav"><button type="button" class="wp-block-galerio-carousel__prev" aria-label="%1$s">%2$s</button><button type="button" class="wp-block-galerio-carousel__next" aria-label="%3$s">%4$s</button></div>',
			esc_attr__( 'Previous slide', 'galerio-blocks' ),
			sprintf( $icon, 'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z' ),
			esc_attr__( 'Next slide', 'galerio-blocks' ),
			sprintf( $icon, 'M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z' )
		);
	}

	/**
	 * Classes for the wrapper, describing the block's options to the stylesheet.
	 *
	 * @param array $attributes Block attributes.
	 * @return string[]
	 */
	private static function classes( $attributes ) {
		$classes = array(
			'is-source-' . ( 'loop' === $attributes['source'] ? 'loop' : 'query' ),
			'is-transition-' . sanitize_html_class( $attributes['transition'] ),
			'is-image-' . ( 'contain' === $attributes['imageFit'] ? 'contain' : 'cover' ),
			'is-position-' . str_replace( ' ', '-', self::position( $attributes['contentPosition'] ) ),
		);

		if ( self::per_view( $attributes ) > 1 ) {
			$classes[] = 'has-several-in-view';
		}

		if ( $attributes['showArrows'] ) {
			$classes[] = 'has-arrows-' . ( 'caption' === $attributes['arrowsPosition'] && 'loop' !== $attributes['source'] ? 'caption' : 'sides' );
		}

		return $classes;
	}

	/**
	 * The wrapper's custom properties.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	private static function style( $attributes ) {
		$style = '--galerio-carousel-per-view:' . self::per_view( $attributes ) . ';';

		if ( preg_match( '/^\d+(\.\d+)?(px|em|rem|vh|svh|lvh|dvh|vw|%)$/', trim( $attributes['height'] ) ) ) {
			$style .= '--galerio-carousel-height:' . trim( $attributes['height'] ) . ';';
		}

		return $style;
	}

	/**
	 * What view.js needs: the motion settings and the translated labels it adds.
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private static function settings( $attributes ) {
		return array(
			'source'     => 'loop' === $attributes['source'] ? 'loop' : 'query',
			'transition' => sanitize_html_class( $attributes['transition'] ),
			'duration'   => max( 10, min( 60, (int) $attributes['duration'] ) ),
			'loop'       => (bool) $attributes['loop'],
			'autoplay'   => (bool) $attributes['autoplay'],
			'interval'   => max( 2000, min( 30000, (int) $attributes['interval'] ) ),
			'perView'    => self::per_view( $attributes ),
			'labels'     => array(
				'role'  => __( 'slide', 'galerio-blocks' ),
				/* translators: %d: slide number. */
				'goTo'  => __( 'Go to slide %d', 'galerio-blocks' ),
				/* translators: 1: slide number, 2: number of slides. */
				'slide' => __( '%1$d of %2$d', 'galerio-blocks' ),
				'pause' => __( 'Pause the carousel', 'galerio-blocks' ),
				'play'  => __( 'Play the carousel', 'galerio-blocks' ),
			),
		);
	}

	/**
	 * How many slides are in view at once. A fade shows one.
	 *
	 * @param array $attributes Block attributes.
	 * @return int
	 */
	private static function per_view( $attributes ) {
		return 'fade' === $attributes['transition'] ? 1 : max( 1, min( 6, (int) $attributes['slidesPerView'] ) );
	}

	/**
	 * A valid "vertical horizontal" content position, such as "bottom right".
	 *
	 * @param string $position Position from the block.
	 * @return string
	 */
	private static function position( $position ) {
		$parts = explode( ' ', strtolower( trim( (string) $position ) ) );

		if ( 2 === count( $parts ) && in_array( $parts[0], array( 'top', 'center', 'bottom' ), true ) && in_array( $parts[1], array( 'left', 'center', 'right' ), true ) ) {
			return $parts[0] . ' ' . $parts[1];
		}

		return 'bottom right';
	}

	/**
	 * The block's attributes, with defaults for any that are missing.
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private static function with_defaults( $attributes ) {
		$defaults = array();
		$type     = \WP_Block_Type_Registry::get_instance()->get_registered( 'galerio/carousel' );

		if ( $type ) {
			foreach ( $type->attributes as $name => $schema ) {
				if ( array_key_exists( 'default', $schema ) ) {
					$defaults[ $name ] = $schema['default'];
				}
			}
		}

		return wp_parse_args( (array) $attributes, $defaults );
	}

	/**
	 * Whether the block is being previewed in the editor.
	 *
	 * @return bool
	 */
	private static function in_editor() {
		return wp_is_serving_rest_request() && current_user_can( 'edit_posts' );
	}
}
