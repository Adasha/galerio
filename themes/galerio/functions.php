<?php
/**
 * Galerio theme functions.
 *
 * @package Galerio
 */

add_action( 'after_setup_theme', 'galerio_load_textdomain' );
add_action( 'init', 'galerio_register_pattern_category' );
add_action( 'init', 'galerio_register_block_styles' );
add_filter( 'render_block_core/cover', 'galerio_mark_imageless_cover', 10, 3 );
add_filter( 'render_block_galerio/gallery', 'galerio_skip_template_gallery', 10, 3 );
add_filter( 'default_wp_template_part_areas', 'galerio_template_part_areas' );
add_filter( 'render_block_data', 'galerio_card_for_item' );
add_filter( 'default_template_types', 'galerio_template_types' );
add_filter( 'get_block_templates', 'galerio_name_saved_templates' );
add_filter( 'pre_get_block_template', 'galerio_name_saved_template_early', 10, 3 );

/**
 * Load the theme's bundled translations, from languages/.
 */
function galerio_load_textdomain() {
	load_theme_textdomain( 'galerio', get_template_directory() . '/languages' );
}

/**
 * Register the pattern category for the theme's own patterns.
 */
function galerio_register_pattern_category() {
	register_block_pattern_category(
		'galerio',
		array( 'label' => __( 'Galerio', 'galerio' ) )
	);
}

/**
 * Register custom block styles.
 */
function galerio_register_block_styles() {

	$styles = array(
		'core/post-terms'       => array(
			array(
				'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			),
		),
		'galerio/field-display' => array(
			array(
				'name'  => 'buttons',
				'label' => __( 'Buttons', 'galerio' ),
			),
		),
		'core/site-title'       => array(
			array(
				'name'  => 'keep-words',
				'label' => __( 'Keep words whole', 'galerio' ),
			),
			array(
				'name'  => 'hyphenate',
				'label' => __( 'Hyphenate', 'galerio' ),
			),
		),
		'core/navigation'       => array(
			array(
				'name'  => 'main-menu',
				'label' => __( 'Main Menu', 'galerio' ),
			),
		),
		'core/group'            => array(
			array(
				'name'  => 'asides',
				'label' => __( 'Asides', 'galerio' ),
			),
			array(
				'name'  => 'image-box',
				'label' => __( 'Image Box', 'galerio' ),
			),
		),
	);

	foreach ( $styles as $block => $block_styles ) {
		foreach ( $block_styles as $style_props ) {
			register_block_style( $block, $style_props );
		}
	}
}

if ( ! function_exists( 'galerio_theme_text' ) ) {
	/**
	 * Prepare a translated text from a pattern: name tokens such as
	 * "{Collections}" become the site's names for its content types.
	 *
	 * Without Galerio Core the tokens' braces are dropped, leaving the
	 * standard names.
	 *
	 * @param string $text Translated text.
	 * @return string
	 */
	function galerio_theme_text( $text ) {
		return function_exists( 'galerio_expand_names' )
			? galerio_expand_names( $text )
			: preg_replace( '/\{([A-Za-z]+)\}/', '$1', $text );
	}
}

if ( ! function_exists( 'galerio_theme_has_block' ) ) {
	/**
	 * Whether a block is available, such as one from the Galerio Blocks
	 * plugin, so a pattern can fall back to another when it isn't.
	 *
	 * @param string $name Block name, e.g. "galerio/gallery".
	 * @return bool
	 */
	function galerio_theme_has_block( $name ) {
		return WP_Block_Type_Registry::get_instance()->is_registered( $name );
	}
}

if ( ! function_exists( 'galerio_theme_attr' ) ) {
	/**
	 * Echo a translated text as a quoted string for a block attribute in a
	 * pattern's block comment, escaped as the block editor saves it.
	 *
	 * Name tokens are kept, for the block to replace as it renders.
	 *
	 * @param string $text Translated text.
	 */
	function galerio_theme_attr( $text ) {
		// serialize_block_attributes() escapes for a block comment: quotes, <, >, & and "--".
		echo substr( serialize_block_attributes( array( (string) $text ) ), 1, -1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped for a block comment.
	}
}

/**
 * Name the templates WordPress picks by content type or taxonomy, such as
 * single-galerio_work, from the type's own labels.
 *
 * Without this the Site Editor shows their file names, and WordPress offers
 * them in every item's Template menu as if they were custom templates.
 *
 * @param array $types Template types: slug => title and description.
 * @return array
 */
function galerio_template_types( $types ) {
	$own = array(
		'public'   => true,
		'_builtin' => false,
	);

	foreach ( get_post_types( $own, 'objects' ) as $post_type ) {
		$types += array(
			"single-{$post_type->name}"  => array(
				/* translators: %s: A content type's name, such as Work. */
				'title'       => sprintf( __( '%s page', 'galerio' ), $post_type->labels->singular_name ),
				/* translators: %s: A content type's name, such as Work. */
				'description' => sprintf( __( 'Shows one item of the type %s.', 'galerio' ), $post_type->labels->singular_name ),
			),
			"archive-{$post_type->name}" => array(
				/* translators: %s: A content type's plural name, such as Works. */
				'title'       => sprintf( __( '%s archive', 'galerio' ), $post_type->labels->name ),
				/* translators: %s: A content type's plural name, such as Works. */
				'description' => sprintf( __( 'Lists the %s.', 'galerio' ), $post_type->labels->name ),
			),
		);
	}

	foreach ( get_taxonomies( $own, 'objects' ) as $taxonomy ) {
		$types += array(
			"taxonomy-{$taxonomy->name}" => array(
				/* translators: %s: A taxonomy's name, such as Group. */
				'title'       => sprintf( __( '%s archive', 'galerio' ), $taxonomy->labels->singular_name ),
				/* translators: %s: A taxonomy's plural name, such as Groups. */
				'description' => sprintf( __( 'Lists the items in one of the %s.', 'galerio' ), $taxonomy->labels->name ),
			),
		);
	}

	return $types;
}

/**
 * Give an edited template still titled with its file name, as the Site
 * Editor saves it, the name from galerio_template_types().
 *
 * @param WP_Block_Template|null $template Template.
 * @return WP_Block_Template|null
 */
function galerio_name_saved_template( $template ) {
	if ( $template && 'wp_template' === $template->type && $template->title === $template->slug ) {
		$types = get_default_block_template_types();

		if ( isset( $types[ $template->slug ] ) ) {
			$template->title       = $types[ $template->slug ]['title'];
			$template->description = $template->description ? $template->description : $types[ $template->slug ]['description'];
		}
	}

	return $template;
}

/**
 * Name an edited template fetched on its own, as the Site Editor does:
 * get_block_template() returns one saved in the database unfiltered, so
 * look it up here first; see galerio_name_saved_template().
 *
 * @param WP_Block_Template|null $template      Template found so far.
 * @param string                 $id            Template ID, theme//slug.
 * @param string                 $template_type wp_template or wp_template_part.
 * @return WP_Block_Template|null
 */
function galerio_name_saved_template_early( $template, $id, $template_type ) {
	static $looking = false;

	if ( null !== $template || $looking || 'wp_template' !== $template_type ) {
		return $template;
	}

	$looking  = true;
	$template = get_block_template( $id, $template_type );
	$looking  = false;

	return galerio_name_saved_template( $template );
}

/**
 * Name edited templates in a list; see galerio_name_saved_template().
 *
 * @param WP_Block_Template[] $templates Templates.
 * @return WP_Block_Template[]
 */
function galerio_name_saved_templates( $templates ) {
	return array_map( 'galerio_name_saved_template', $templates );
}

/**
 * Mark a cover that shows its item's featured image but finds none, so the
 * theme can show a plain colour band in its place instead of nothing.
 *
 * Galerio Core first falls back to the item's own image (a work's
 * definitive image, a profile's portrait…), so this is for items with no
 * image at all, or whose fallback is turned off.
 *
 * @param string   $content Rendered cover.
 * @param array    $block   Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function galerio_mark_imageless_cover( $content, $block, $instance ) {
	if ( empty( $block['attrs']['useFeaturedImage'] ) ) {
		return $content;
	}

	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		return $content;
	}

	$cover = new WP_HTML_Tag_Processor( $content );
	if ( $cover->next_tag( array( 'class_name' => 'wp-block-cover' ) ) ) {
		$cover->add_class( 'galerio-cover-no-image' );
	}

	return $cover->get_updated_html();
}

/**
 * Leave out a template's gallery (one with the galerio-template-gallery
 * class) when the item's own content already has a gallery, so the images
 * aren't shown twice. Older sites often placed galleries in the content.
 *
 * @param string   $content  Rendered gallery.
 * @param array    $block    Parsed block.
 * @param WP_Block $instance Block instance.
 * @return string
 */
function galerio_skip_template_gallery( $content, $block, $instance ) {
	if ( empty( $block['attrs']['className'] ) || ! in_array( 'galerio-template-gallery', explode( ' ', $block['attrs']['className'] ), true ) ) {
		return $content;
	}

	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : (int) get_the_ID();
	$post    = $post_id ? get_post( $post_id ) : null;

	if ( $post && ( has_block( 'galerio/gallery', $post ) || has_block( 'core/gallery', $post ) ) ) {
		return '';
	}

	return $content;
}

/**
 * Add a Card area for template parts, so the Site Editor keeps the cards
 * (card, card-work, card-collection…) apart from headers and footers.
 *
 * @param array $areas Template part areas.
 * @return array
 */
function galerio_template_part_areas( $areas ) {
	$areas[] = array(
		'area'        => 'galerio-card',
		'label'       => _x( 'Card', 'template part area', 'galerio' ),
		'description' => __( 'How one item looks in a list, such as an archive, a Query Loop or a carousel. Edit a card and every list using it follows.', 'galerio' ),
		'icon'        => 'layout',
		'area_tag'    => 'div',
	);

	return $areas;
}

/**
 * Draw each item in a list using the Card part with its own type's card,
 * such as card-work for a work, when the theme or the site has one. Lists
 * that mix types (term archives, carousels) then match each type's archive.
 *
 * The type's card is "card-" and the type's name without "galerio_", so a
 * site can add one for another type, e.g. card-post, in the Site Editor.
 *
 * @param array $block Parsed block.
 * @return array
 */
function galerio_card_for_item( $block ) {
	if ( 'core/template-part' !== $block['blockName'] || ! isset( $block['attrs']['slug'] ) || 'card' !== $block['attrs']['slug'] ) {
		return $block;
	}

	if ( isset( $block['attrs']['theme'] ) && get_stylesheet() !== $block['attrs']['theme'] ) {
		return $block;
	}

	$post_type = get_post_type();
	if ( ! $post_type ) {
		return $block;
	}

	$slug = galerio_card_slug( $post_type );
	if ( $slug ) {
		$block['attrs']['slug'] = $slug;
	}

	return $block;
}

/**
 * The card template part for a content type, if there is one.
 *
 * @param string $post_type Post type.
 * @return string Part slug, or empty.
 */
function galerio_card_slug( $post_type ) {
	static $slugs = array();

	if ( ! isset( $slugs[ $post_type ] ) ) {
		$slug                = 'card-' . preg_replace( '/^galerio_/', '', $post_type );
		$slugs[ $post_type ] = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' ) ? $slug : '';
	}

	return $slugs[ $post_type ];
}
