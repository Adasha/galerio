<?php
/**
 * Rows for Query Loops.
 *
 * @package Galerio\Blocks
 */

namespace Galerio\Blocks;

use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a Query Loop show only so many rows of items, and either hide the rest
 * or scroll to them: the `galerioRows` and `galerioScroll` attributes, set in
 * the Rows panel.
 *
 * Rows are counted on the page by query-rows-view.js, as laid out, so the
 * limit holds when a grid has fewer columns on a phone.
 */
final class Query_Rows {

	/**
	 * Most rows the panel offers.
	 */
	const MAX = 10;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_assets' ) );
		add_filter( 'register_block_type_args', array( __CLASS__, 'attributes' ), 10, 2 );
		add_filter( 'render_block_core/query', array( __CLASS__, 'render' ), 10, 2 );
	}

	/**
	 * The Rows panel.
	 */
	public static function editor_assets() {
		wp_enqueue_script(
			'galerio-query-rows',
			plugins_url( 'assets/js/query-rows.js', GALERIO_BLOCKS_FILE ),
			array( 'wp-block-editor', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
			GALERIO_BLOCKS_VERSION,
			true
		);
		wp_set_script_translations( 'galerio-query-rows', 'galerio-blocks', GALERIO_BLOCKS_DIR . 'languages' );
	}

	/**
	 * Add the attributes to the Query Loop on the server too.
	 *
	 * @param array  $args Block type arguments.
	 * @param string $name Block name.
	 * @return array
	 */
	public static function attributes( $args, $name ) {
		if ( 'core/query' === $name ) {
			$args['attributes']['galerioRows']   = array(
				'type'    => 'number',
				'default' => 0,
			);
			$args['attributes']['galerioScroll'] = array(
				'type'    => 'boolean',
				'default' => false,
			);
		}

		return $args;
	}

	/**
	 * Mark a limited Query Loop for the script, and load it.
	 *
	 * @param string $content Rendered block.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function render( $content, $block ) {
		$rows = isset( $block['attrs']['galerioRows'] ) ? (int) $block['attrs']['galerioRows'] : 0;

		if ( $rows < 1 || '' === $content ) {
			return $content;
		}

		$tags = new WP_HTML_Tag_Processor( $content );
		if ( ! $tags->next_tag() ) {
			return $content;
		}

		$tags->add_class( 'has-galerio-rows' );
		if ( ! empty( $block['attrs']['galerioScroll'] ) ) {
			$tags->add_class( 'is-galerio-scroll' );
		}
		$tags->set_attribute( 'data-galerio-rows', (string) min( $rows, self::MAX ) );

		self::view_assets();

		return $tags->get_updated_html();
	}

	/**
	 * The script that counts the rows, and the styles it relies on.
	 */
	private static function view_assets() {
		if ( wp_script_is( 'galerio-query-rows-view' ) ) {
			return;
		}

		wp_enqueue_script(
			'galerio-query-rows-view',
			plugins_url( 'assets/js/query-rows-view.js', GALERIO_BLOCKS_FILE ),
			array(),
			GALERIO_BLOCKS_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_register_style( 'galerio-query-rows', false, array(), GALERIO_BLOCKS_VERSION );
		wp_enqueue_style( 'galerio-query-rows' );
		wp_add_inline_style(
			'galerio-query-rows',
			'.has-galerio-rows .wp-block-post-template > [hidden] { display: none !important; }
			.has-galerio-rows .wp-block-post-template.is-scrolling { overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; grid-auto-rows: max-content; }'
		);
	}
}
