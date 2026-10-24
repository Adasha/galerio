<?php
/**
 * A page's own style: fonts, colours and CSS for one collection, article or page.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Styles;

use WP_Post;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an exhibition, an article or any page look different from the rest of the site.
 *
 * A page's style replaces some of the site's style presets (its fonts and
 * palette colours) on that page only, so the theme's layouts restyle
 * themselves. Custom CSS covers whatever the presets can't. It's for the
 * occasional exhibition with its own identity, so the editor keeps it out of
 * the way: one closed sidebar panel that opens a window.
 *
 * Stored in the `galerio_page_style` post meta as
 * `{ fonts: { preset: family }, colors: { preset: colour }, css: string }`.
 */
final class Page_Style {

	/**
	 * Post meta key.
	 */
	const META = 'galerio_page_style';

	/**
	 * Most characters of custom CSS.
	 */
	const MAX_CSS = 100000;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'guard_css' ), 10, 4 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'guard_new_css' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
	}

	/**
	 * Post types that can have their own style.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		/**
		 * Filters the post types that can have their own style.
		 *
		 * @param string[] $post_types Post types. Defaults to collections, articles and pages.
		 */
		return (array) apply_filters( 'galerio_page_style_post_types', array( 'galerio_collection', 'galerio_article', 'page' ) );
	}

	/**
	 * Register the meta for each post type, readable and writable over REST.
	 */
	public static function register_meta() {
		$map = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'string' ),
		);

		foreach ( self::post_types() as $post_type ) {
			// The editor only loads meta of types that support custom fields.
			add_post_type_support( $post_type, 'custom-fields' );

			register_post_meta(
				$post_type,
				self::META,
				array(
					'type'              => 'object',
					'single'            => true,
					'default'           => array(),
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
					'show_in_rest'      => array(
						'schema' => array(
							'type'                 => 'object',
							'properties'           => array(
								'fonts'  => $map,
								'colors' => $map,
								'css'    => array( 'type' => 'string' ),
							),
							'additionalProperties' => false,
						),
					),
				)
			);
		}
	}

	/**
	 * Clean a style: known font families, plain colour values, CSS without markup.
	 *
	 * @param mixed $style Style as submitted.
	 * @return array
	 */
	public static function sanitize( $style ) {
		$style    = is_array( $style ) ? $style : array();
		$families = self::font_families();
		$clean    = array();

		foreach ( array( 'fonts', 'colors' ) as $group ) {
			foreach ( isset( $style[ $group ] ) && is_array( $style[ $group ] ) ? $style[ $group ] : array() as $preset => $value ) {
				$preset = (string) $preset;
				$value  = trim( (string) $value );

				if ( ! preg_match( '/^[a-z0-9-]+$/', $preset ) || '' === $value ) {
					continue;
				}

				if ( 'fonts' === $group && isset( $families[ $value ] ) ) {
					$clean['fonts'][ $preset ] = $value;
				} elseif ( 'colors' === $group && self::is_colour( $value ) ) {
					$clean['colors'][ $preset ] = $value;
				}
			}
		}

		$css = isset( $style['css'] ) ? (string) $style['css'] : '';
		$css = trim( substr( str_ireplace( '</style', '', wp_strip_all_tags( $css ) ), 0, self::MAX_CSS ) );

		if ( '' !== $css ) {
			$clean['css'] = $css;
		}

		return $clean;
	}

	/**
	 * Whether a value is a colour this feature accepts: hex, rgb(a) or hsl(a).
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_colour( $value ) {
		return (bool) preg_match( '/^(#[0-9a-f]{3,8}|(rgb|hsl)a?\([0-9.,%\s\/-]+\))$/i', $value );
	}

	/**
	 * Keep the stored CSS when someone who can't edit CSS saves the style, so
	 * they can still change its fonts and colours.
	 *
	 * @param null|bool $check     Whether to short-circuit.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $value     New value.
	 * @return null|bool
	 */
	public static function guard_css( $check, $object_id, $meta_key, $value ) {
		if ( self::META !== $meta_key || current_user_can( 'edit_css' ) || ! is_array( $value ) ) {
			return $check;
		}

		$stored = get_post_meta( $object_id, self::META, true );
		$old    = is_array( $stored ) && isset( $stored['css'] ) ? $stored['css'] : '';
		$new    = isset( $value['css'] ) ? $value['css'] : '';

		if ( $old === $new ) {
			return $check;
		}

		if ( '' === $old ) {
			unset( $value['css'] );
		} else {
			$value['css'] = $old;
		}

		// Save the corrected value instead, without coming back through this check.
		remove_filter( 'update_post_metadata', array( __CLASS__, 'guard_css' ), 10 );
		update_post_meta( $object_id, self::META, $value );
		add_filter( 'update_post_metadata', array( __CLASS__, 'guard_css' ), 10, 4 );

		return true;
	}

	/**
	 * The same check for a style saved for the first time.
	 *
	 * @param null|bool $check     Whether to short-circuit.
	 * @param int       $object_id Post ID.
	 * @param string    $meta_key  Meta key.
	 * @param mixed     $value     New value.
	 * @return null|bool
	 */
	public static function guard_new_css( $check, $object_id, $meta_key, $value ) {
		if ( self::META !== $meta_key || current_user_can( 'edit_css' ) || ! is_array( $value ) || ! isset( $value['css'] ) ) {
			return $check;
		}

		unset( $value['css'] );

		remove_filter( 'add_post_metadata', array( __CLASS__, 'guard_new_css' ), 10 );
		add_post_meta( $object_id, self::META, $value, true );
		add_filter( 'add_post_metadata', array( __CLASS__, 'guard_new_css' ), 10, 4 );

		return true;
	}

	/**
	 * Font families a page can use, by slug: the theme's and the Font Library's.
	 *
	 * @return array[] Each with `name`, `fontFamily` and, for Font Library
	 *                 fonts, the `faces` to load.
	 */
	public static function font_families() {
		$families = array();
		$settings = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );

		foreach ( array( 'theme', 'custom' ) as $origin ) {
			foreach ( isset( $settings[ $origin ] ) ? (array) $settings[ $origin ] : array() as $family ) {
				if ( ! empty( $family['slug'] ) && ! empty( $family['fontFamily'] ) && ! isset( $families[ $family['slug'] ] ) ) {
					$families[ $family['slug'] ] = array(
						'name'       => isset( $family['name'] ) ? $family['name'] : $family['slug'],
						'fontFamily' => $family['fontFamily'],
						'faces'      => array(),
					);
				}
			}
		}

		// Fonts in the Font Library needn't be activated for the site to be used on one page.
		$library = get_posts(
			array(
				'post_type'      => 'wp_font_family',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		foreach ( $library as $post ) {
			$family = json_decode( $post->post_content, true );
			$slug   = $post->post_name;

			if ( ! is_array( $family ) || empty( $family['fontFamily'] ) || isset( $families[ $slug ] ) ) {
				continue;
			}

			$faces = array();
			foreach ( get_children(
				array(
					'post_parent' => $post->ID,
					'post_type'   => 'wp_font_face',
				)
			) as $face_post ) {
				$face = json_decode( $face_post->post_content, true );
				if ( is_array( $face ) && ! empty( $face['src'] ) ) {
					$faces[] = $face;
				}
			}

			$families[ $slug ] = array(
				'name'       => isset( $family['name'] ) ? $family['name'] : $post->post_title,
				'fontFamily' => $family['fontFamily'],
				'faces'      => $faces,
			);
		}

		return $families;
	}

	/**
	 * The theme's presets a page can replace.
	 *
	 * @return array With `fonts` and `colors`, each a list of `slug`, `name` and `value`.
	 */
	public static function presets() {
		$presets = array(
			'fonts'  => array(),
			'colors' => array(),
		);
		$fonts   = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) );
		$colors  = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );

		foreach ( (array) $fonts as $font ) {
			if ( ! empty( $font['slug'] ) ) {
				$presets['fonts'][] = array(
					'slug'  => $font['slug'],
					'name'  => isset( $font['name'] ) ? $font['name'] : $font['slug'],
					'value' => isset( $font['fontFamily'] ) ? $font['fontFamily'] : '',
				);
			}
		}

		foreach ( (array) $colors as $color ) {
			if ( ! empty( $color['slug'] ) ) {
				$presets['colors'][] = array(
					'slug'  => $color['slug'],
					'name'  => isset( $color['name'] ) ? $color['name'] : $color['slug'],
					'value' => isset( $color['color'] ) ? $color['color'] : '',
				);
			}
		}

		return $presets;
	}

	/**
	 * The CSS for a style: the fonts it loads, the presets it replaces, then
	 * its own CSS.
	 *
	 * @param array $style Sanitized style.
	 * @return string
	 */
	public static function css( $style ) {
		$families = self::font_families();
		$css      = '';
		$vars     = '';

		foreach ( isset( $style['fonts'] ) ? $style['fonts'] : array() as $preset => $slug ) {
			if ( isset( $families[ $slug ] ) ) {
				$css  .= self::font_faces( $families[ $slug ]['faces'] );
				$vars .= sprintf( '--wp--preset--font-family--%s:%s;', $preset, $families[ $slug ]['fontFamily'] );
			}
		}

		foreach ( isset( $style['colors'] ) ? $style['colors'] : array() as $preset => $colour ) {
			$vars .= sprintf( '--wp--preset--color--%s:%s;', $preset, $colour );
		}

		// On body rather than :root, so the page's values reach everything in
		// it whichever of this and the site's styles loads last.
		if ( '' !== $vars ) {
			$css .= 'body{' . $vars . '}';
		}

		if ( ! empty( $style['css'] ) ) {
			$css .= "\n" . $style['css'];
		}

		return $css;
	}

	/**
	 * The font-face rules for a Font Library family's faces.
	 *
	 * @param array[] $faces Font faces, as the Font Library stores them.
	 * @return string
	 */
	private static function font_faces( $faces ) {
		$css = '';

		foreach ( $faces as $face ) {
			$src = array();
			foreach ( (array) $face['src'] as $url ) {
				$format = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
				$src[]  = sprintf( 'url("%s")', esc_url_raw( $url ) ) . ( $format ? sprintf( ' format("%s")', 'ttf' === $format ? 'truetype' : ( 'otf' === $format ? 'opentype' : $format ) ) : '' );
			}

			$rules = array( 'font-family:' . self::quote_family( isset( $face['fontFamily'] ) ? $face['fontFamily'] : '' ), 'src:' . implode( ',', $src ), 'font-display:' . ( isset( $face['fontDisplay'] ) ? $face['fontDisplay'] : 'swap' ) );

			foreach ( array(
				'fontStyle'    => 'font-style',
				'fontWeight'   => 'font-weight',
				'unicodeRange' => 'unicode-range',
			) as $key => $property ) {
				if ( ! empty( $face[ $key ] ) ) {
					$rules[] = $property . ':' . $face[ $key ];
				}
			}

			$css .= '@font-face{' . implode( ';', $rules ) . '}';
		}

		return $css;
	}

	/**
	 * A family name as a font-face rule needs it: one name, quoted.
	 *
	 * @param string $family Family, possibly with fallbacks.
	 * @return string
	 */
	private static function quote_family( $family ) {
		$name = trim( explode( ',', $family )[0], " \t\"'" );
		return '"' . str_replace( '"', '', $name ) . '"';
	}

	/**
	 * A post's style, sanitized again in case the fonts it used have gone.
	 *
	 * @param WP_Post|int $post Post.
	 * @return array
	 */
	public static function get( $post ) {
		$style = get_post_meta( is_object( $post ) ? $post->ID : (int) $post, self::META, true );

		return is_array( $style ) ? self::sanitize( $style ) : array();
	}

	/**
	 * Add the style to a page that has one, after the site's styles.
	 */
	public static function enqueue() {
		if ( ! is_singular( self::post_types() ) ) {
			return;
		}

		$css = self::css( self::get( get_queried_object_id() ) );

		if ( '' === $css ) {
			return;
		}

		// Block themes have registered the site's styles by now; classic themes add them later, in the footer.
		$deps = wp_style_is( 'global-styles', 'registered' ) ? array( 'global-styles' ) : array();

		wp_register_style( 'galerio-page-style', false, $deps, GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-page-style' );
		wp_add_inline_style( 'galerio-page-style', $css );
	}

	/**
	 * The editor's Page style panel, on post types that can have one.
	 */
	public static function editor_assets() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			'galerio-page-style',
			GALERIO_CORE_URL . 'assets/js/page-style.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			GALERIO_CORE_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-page-style', 'galerio-core', GALERIO_CORE_DIR . 'languages' );

		wp_register_style( 'galerio-page-style-editor', false, array( 'wp-components' ), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-page-style-editor' );
		wp_add_inline_style(
			'galerio-page-style-editor',
			'.galerio-page-style__colors{margin:0;padding:0;list-style:none}'
			. '.galerio-page-style__color{gap:8px;width:100%}'
			. '.galerio-page-style__color em{margin-left:auto;color:#757575}'
			. '.galerio-page-style__css textarea{font-family:Menlo,Consolas,monospace;font-size:13px}'
			. '.galerio-page-style .components-tab-panel__tab-content{padding-top:16px;display:grid;gap:16px}'
			. '.galerio-page-style__actions{display:flex;justify-content:space-between;margin-top:24px}'
		);

		$families = array();
		foreach ( self::font_families() as $slug => $family ) {
			$families[] = array(
				'slug' => $slug,
				'name' => $family['name'],
			);
		}

		wp_add_inline_script(
			'galerio-page-style',
			'window.galerioPageStyle = ' . wp_json_encode(
				array(
					'meta'       => self::META,
					'presets'    => self::presets(),
					'families'   => $families,
					'canEditCss' => current_user_can( 'edit_css' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * The editor's preview endpoint: a style in, its CSS out.
	 */
	public static function register_route() {
		register_rest_route(
			'galerio/v1',
			'/page-style/css',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'style' => array(
						'type'     => 'object',
						'required' => true,
					),
				),
				'callback'            => function ( WP_REST_Request $request ) {
					return array( 'css' => self::css( self::sanitize( $request['style'] ) ) );
				},
			)
		);
	}
}
