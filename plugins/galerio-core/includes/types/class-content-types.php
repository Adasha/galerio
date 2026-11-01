<?php
/**
 * Galerio's own content types and taxonomies.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Types;

use Galerio\Core\Fields\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the types and taxonomies without Pods, once a site has moved to
 * native data. Until then the Pods package registers them, and these
 * definitions are only compared with what it registers, by the tests and by
 * `wp galerio fields check`.
 *
 * Aliases and type capabilities apply through the usual registration filters.
 */
final class Content_Types {

	/**
	 * Option that moves a site to native data.
	 */
	const OPTION = 'galerio_native_data';

	/**
	 * Labels that may differ from Pods', where Pods' own wording was wrong for
	 * these types: it speaks of pages, and puts a space before a colon.
	 */
	const REWORDED_LABELS = array( 'item_trashed', 'item_link', 'item_link_description', 'parent_item_colon' );

	/**
	 * Properties of a post type to compare.
	 */
	const POST_TYPE_PROPERTIES = array( 'can_export', 'capability_type', 'delete_with_user', 'exclude_from_search', 'has_archive', 'hierarchical', 'map_meta_cap', 'menu_icon', 'public', 'publicly_queryable', 'query_var', 'rest_base', 'rest_namespace', 'rewrite', 'show_in_admin_bar', 'show_in_menu', 'show_in_nav_menus', 'show_in_rest', 'show_ui', 'cap' );

	/**
	 * Properties of a taxonomy to compare. Not its capabilities: the package
	 * sets none, and plugins such as PublishPress Capabilities rename them
	 * after registration, however a taxonomy was registered.
	 */
	const TAXONOMY_PROPERTIES = array( 'hierarchical', 'public', 'publicly_queryable', 'query_var', 'rest_base', 'rest_namespace', 'rewrite', 'show_admin_column', 'show_in_menu', 'show_in_nav_menus', 'show_in_quick_edit', 'show_in_rest', 'show_tagcloud', 'show_ui', 'sort' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		if ( ! self::active() ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'before_delete_post', array( Relations::class, 'forget' ) );
	}

	/**
	 * Whether this site has moved to native data.
	 *
	 * @return bool
	 */
	public static function active() {
		/**
		 * Filters whether the site uses Galerio's native data rather than Pods.
		 *
		 * @param bool $active Whether it does.
		 */
		return (bool) apply_filters( 'galerio_native_data', (bool) get_option( self::OPTION ) );
	}

	/**
	 * Register the taxonomies and post types.
	 */
	public static function register() {
		foreach ( self::taxonomies() as $taxonomy => $definition ) {
			register_taxonomy( $taxonomy, $definition['object_type'], $definition['args'] );
		}

		foreach ( self::post_types() as $post_type => $args ) {
			register_post_type( $post_type, $args );
		}
	}

	/**
	 * The post types, in menu order.
	 *
	 * @return array<string, array> Registration arguments by post type.
	 */
	public static function post_types() {
		$types = array(
			'galerio_article'    => array(
				'label'      => array( __( 'Article', 'galerio-core' ), __( 'Articles', 'galerio-core' ) ),
				'slug'       => 'article',
				'archive'    => 'articles',
				'menu_icon'  => 'dashicons-welcome-write-blog',
				'supports'   => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'page-attributes', 'quick-edit', 'custom-fields' ),
				'taxonomies' => array( 'category', 'post_tag', 'galerio_article_category', 'galerio_label' ),
			),
			'galerio_collection' => array(
				'label'      => array( __( 'Collection', 'galerio-core' ), __( 'Collections', 'galerio-core' ) ),
				'slug'       => 'collection',
				'archive'    => 'collections',
				'menu_icon'  => 'dashicons-layout',
				'supports'   => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'revisions', 'page-attributes', 'quick-edit', 'custom-fields' ),
				'taxonomies' => array( 'galerio_collection_category', 'galerio_group', 'galerio_label', 'galerio_medium' ),
			),
			'galerio_profile'    => array(
				'label'            => array( __( 'Profile', 'galerio-core' ), __( 'Profiles', 'galerio-core' ) ),
				'slug'             => 'profile',
				'archive'          => 'profiles',
				'menu_icon'        => 'dashicons-id',
				'description'      => __( 'Profile page for creators.', 'galerio-core' ),
				'hierarchical'     => false,
				'delete_with_user' => true,
				'supports'         => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'quick-edit', 'custom-fields' ),
				'taxonomies'       => array( 'galerio_group', 'galerio_label', 'galerio_medium', 'galerio_role' ),
			),
			'galerio_work'       => array(
				'label'      => array( __( 'Work', 'galerio-core' ), __( 'Works', 'galerio-core' ) ),
				'slug'       => 'work',
				'archive'    => 'works',
				'menu_icon'  => 'dashicons-art',
				'supports'   => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions', 'page-attributes', 'quick-edit', 'custom-fields' ),
				'taxonomies' => array( 'galerio_label', 'galerio_medium' ),
			),
		);

		$args = array();

		foreach ( $types as $post_type => $type ) {
			list( $singular, $plural ) = $type['label'];

			$args[ $post_type ] = array(
				'label'            => $plural,
				'labels'           => self::post_type_labels( $singular, $plural ),
				'description'      => isset( $type['description'] ) ? $type['description'] : '',
				'public'           => true,
				'hierarchical'     => isset( $type['hierarchical'] ) ? $type['hierarchical'] : true,
				'show_in_rest'     => true,
				'rest_base'        => $post_type,
				'menu_icon'        => $type['menu_icon'],
				'capability_type'  => 'page',
				'map_meta_cap'     => true,
				'supports'         => $type['supports'],
				'taxonomies'       => $type['taxonomies'],
				'has_archive'      => $type['archive'],
				'rewrite'          => array(
					'slug'       => $type['slug'],
					'with_front' => false,
					'feeds'      => true,
					'pages'      => true,
				),
				'query_var'        => $post_type,
				'delete_with_user' => isset( $type['delete_with_user'] ) ? $type['delete_with_user'] : false,
			);
		}

		return $args;
	}

	/**
	 * The taxonomies.
	 *
	 * @return array<string, array> The object_type and args of each taxonomy.
	 */
	public static function taxonomies() {
		$taxonomies = array(
			'galerio_article_category'    => array(
				'label'        => array( __( 'Article category', 'galerio-core' ), __( 'Article categories', 'galerio-core' ) ),
				'slug'         => 'article-category',
				'description'  => __( 'The kind of article, such as Interview, Review or Opinion.', 'galerio-core' ),
				'object_type'  => array( 'galerio_article' ),
				'admin_column' => true,
			),
			'galerio_collection_category' => array(
				'label'        => array( __( 'Collection Category', 'galerio-core' ), __( 'Collection Categories', 'galerio-core' ) ),
				'slug'         => 'collection-category',
				'description'  => __( 'Category taxonomy for Galerio Collections.', 'galerio-core' ),
				'object_type'  => array( 'galerio_collection' ),
				'admin_column' => true,
			),
			'galerio_group'               => array(
				'label'        => array( __( 'Group', 'galerio-core' ), __( 'Groups', 'galerio-core' ) ),
				'slug'         => 'group',
				'description'  => __( 'Groups of which the profile owner is a member.', 'galerio-core' ),
				'object_type'  => array( 'post', 'galerio_collection', 'galerio_profile' ),
				'admin_column' => true,
			),
			'galerio_label'               => array(
				'label'        => array( __( 'Label', 'galerio-core' ), __( 'Labels', 'galerio-core' ) ),
				'slug'         => 'label',
				'description'  => __( 'Labels to define and categorise works & collections.', 'galerio-core' ),
				'object_type'  => array( 'galerio_article', 'galerio_collection', 'galerio_profile', 'galerio_work' ),
				'hierarchical' => false,
			),
			'galerio_medium'              => array(
				'label'       => array( __( 'Medium', 'galerio-core' ), __( 'Mediums', 'galerio-core' ) ),
				'slug'        => 'medium',
				'description' => __( 'Materials and processes used to create works.', 'galerio-core' ),
				'object_type' => array( 'galerio_collection', 'galerio_profile', 'galerio_work' ),
			),
			'galerio_role'                => array(
				'label'        => array( __( 'Role', 'galerio-core' ), __( 'Roles', 'galerio-core' ) ),
				'slug'         => 'role',
				'description'  => '',
				'object_type'  => array( 'galerio_profile' ),
				// Its addresses nest with its terms: /role/staff/technician/.
				'nested_slugs' => true,
			),
		);

		$definitions = array();

		foreach ( $taxonomies as $taxonomy => $definition ) {
			list( $singular, $plural ) = $definition['label'];

			$nested = ! empty( $definition['nested_slugs'] );

			$definitions[ $taxonomy ] = array(
				'object_type' => $definition['object_type'],
				'args'        => array(
					'label'             => $plural,
					'labels'            => self::taxonomy_labels( $singular, $plural ),
					'description'       => $definition['description'],
					'public'            => true,
					'hierarchical'      => isset( $definition['hierarchical'] ) ? $definition['hierarchical'] : true,
					'show_in_rest'      => true,
					'rest_base'         => $taxonomy,
					'show_admin_column' => ! empty( $definition['admin_column'] ),
					'sort'              => false,
					'query_var'         => $taxonomy,
					'rewrite'           => array(
						'slug'         => $definition['slug'],
						'with_front'   => $nested,
						'hierarchical' => $nested,
					),
				),
			);
		}

		return $definitions;
	}

	/**
	 * The labels of a post type.
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return array<string, string>
	 */
	private static function post_type_labels( $singular, $plural ) {
		/* phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment -- Each %s is the content type's name. */
		return array(
			'name'                     => $plural,
			'singular_name'            => $singular,
			'menu_name'                => $plural,
			'name_admin_bar'           => $singular,
			'add_new'                  => sprintf( __( 'Add New %s', 'galerio-core' ), $singular ),
			'add_new_item'             => sprintf( __( 'Add New %s', 'galerio-core' ), $singular ),
			'edit_item'                => sprintf( __( 'Edit %s', 'galerio-core' ), $singular ),
			'new_item'                 => sprintf( __( 'New %s', 'galerio-core' ), $singular ),
			'view_item'                => sprintf( __( 'View %s', 'galerio-core' ), $singular ),
			'view_items'               => sprintf( __( 'View %s', 'galerio-core' ), $plural ),
			'search_items'             => sprintf( __( 'Search %s', 'galerio-core' ), $plural ),
			'not_found'                => sprintf( __( 'No %s Found', 'galerio-core' ), $plural ),
			'not_found_in_trash'       => sprintf( __( 'No %s Found in Trash', 'galerio-core' ), $plural ),
			'parent_item_colon'        => sprintf( __( 'Parent %s:', 'galerio-core' ), $singular ),
			'all_items'                => sprintf( __( 'All %s', 'galerio-core' ), $plural ),
			'archives'                 => sprintf( __( '%s Archives', 'galerio-core' ), $singular ),
			'attributes'               => sprintf( __( '%s Attributes', 'galerio-core' ), $singular ),
			'insert_into_item'         => sprintf( __( 'Insert into %s', 'galerio-core' ), $singular ),
			'uploaded_to_this_item'    => sprintf( __( 'Uploaded to this %s', 'galerio-core' ), $singular ),
			'filter_items_list'        => sprintf( __( 'Filter %s lists', 'galerio-core' ), $plural ),
			'items_list_navigation'    => sprintf( __( '%s navigation', 'galerio-core' ), $plural ),
			'items_list'               => sprintf( __( '%s list', 'galerio-core' ), $plural ),
			'item_published'           => sprintf( __( '%s published', 'galerio-core' ), $singular ),
			'item_published_privately' => sprintf( __( '%s published privately', 'galerio-core' ), $singular ),
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft', 'galerio-core' ), $singular ),
			'item_trashed'             => sprintf( __( '%s trashed.', 'galerio-core' ), $singular ),
			'item_scheduled'           => sprintf( __( '%s scheduled', 'galerio-core' ), $singular ),
			'item_updated'             => sprintf( __( '%s updated', 'galerio-core' ), $singular ),
			'item_link'                => sprintf( __( '%s Link', 'galerio-core' ), $singular ),
			'item_link_description'    => sprintf( __( 'A link to a %s.', 'galerio-core' ), $singular ),
		);
		/* phpcs:enable */
	}

	/**
	 * The labels of a taxonomy.
	 *
	 * @param string $singular Singular name.
	 * @param string $plural   Plural name.
	 * @return array<string, string>
	 */
	private static function taxonomy_labels( $singular, $plural ) {
		/* phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment -- Each %s is the taxonomy's name. */
		return array(
			'name'                       => $plural,
			'singular_name'              => $singular,
			'menu_name'                  => $plural,
			'search_items'               => sprintf( __( 'Search %s', 'galerio-core' ), $plural ),
			'popular_items'              => sprintf( __( 'Popular %s', 'galerio-core' ), $plural ),
			'all_items'                  => sprintf( __( 'All %s', 'galerio-core' ), $plural ),
			'parent_item'                => sprintf( __( 'Parent %s', 'galerio-core' ), $singular ),
			'parent_item_colon'          => sprintf( __( 'Parent %s:', 'galerio-core' ), $singular ),
			'edit_item'                  => sprintf( __( 'Edit %s', 'galerio-core' ), $singular ),
			'view_item'                  => sprintf( __( 'View %s', 'galerio-core' ), $singular ),
			'update_item'                => sprintf( __( 'Update %s', 'galerio-core' ), $singular ),
			'add_new_item'               => sprintf( __( 'Add New %s', 'galerio-core' ), $singular ),
			'new_item_name'              => sprintf( __( 'New %s Name', 'galerio-core' ), $singular ),
			'separate_items_with_commas' => sprintf( __( 'Separate %s with commas', 'galerio-core' ), $plural ),
			'add_or_remove_items'        => sprintf( __( 'Add or remove %s', 'galerio-core' ), $plural ),
			'choose_from_most_used'      => sprintf( __( 'Choose from the most used %s', 'galerio-core' ), $plural ),
			'not_found'                  => sprintf( __( 'No %s found.', 'galerio-core' ), $plural ),
			'no_terms'                   => sprintf( __( 'No %s', 'galerio-core' ), $plural ),
			'filter_by_item'             => sprintf( __( 'Filter by %s', 'galerio-core' ), $plural ),
			'items_list_navigation'      => sprintf( __( '%s navigation', 'galerio-core' ), $plural ),
			'items_list'                 => sprintf( __( '%s list', 'galerio-core' ), $plural ),
		);
		/* phpcs:enable */
	}

	/**
	 * How what's registered differs from these definitions, as they'd be with
	 * this site's aliases and other registration filters applied. Anything
	 * listed is something the site would lose or change by moving to native data.
	 *
	 * @return array[] Each with the object, property, registered and native values.
	 */
	public static function differences() {
		$differences = array();

		foreach ( self::post_types() as $post_type => $args ) {
			$registered  = get_post_type_object( $post_type );
			$differences = array_merge( $differences, self::compare( $post_type, $registered, $registered ? new \WP_Post_Type( $post_type, $args ) : null, self::POST_TYPE_PROPERTIES, array_keys( $args['labels'] ) ) );

			if ( $registered ) {
				// WordPress adds autosave itself, and fields saved through the REST API need custom-fields.
				$supports = array_diff( array_keys( get_all_post_type_supports( $post_type ) ), array( 'autosave', 'custom-fields' ) );
				$native   = array_diff( $args['supports'], array( 'custom-fields' ) );

				if ( array_diff( $supports, $native ) || array_diff( $native, $supports ) ) {
					$differences[] = self::difference( $post_type, 'supports', array_values( $supports ), array_values( $native ) );
				}

				$taxonomies = get_object_taxonomies( $post_type );

				if ( array_diff( $taxonomies, $args['taxonomies'] ) || array_diff( $args['taxonomies'], $taxonomies ) ) {
					$differences[] = self::difference( $post_type, 'taxonomies', $taxonomies, $args['taxonomies'] );
				}
			}
		}

		foreach ( self::taxonomies() as $taxonomy => $definition ) {
			$registered  = get_taxonomy( $taxonomy );
			$differences = array_merge( $differences, self::compare( $taxonomy, $registered, $registered ? new \WP_Taxonomy( $taxonomy, $definition['object_type'], $definition['args'] ) : null, self::TAXONOMY_PROPERTIES, array_keys( $definition['args']['labels'] ) ) );

			if ( $registered && ( array_diff( $registered->object_type, $definition['object_type'] ) || array_diff( $definition['object_type'], $registered->object_type ) ) ) {
				$differences[] = self::difference( $taxonomy, 'object_type', array_values( $registered->object_type ), $definition['object_type'] );
			}
		}

		return $differences;
	}

	/**
	 * Compare a registered object's properties and labels with a native one's.
	 *
	 * @param string      $name       Object name.
	 * @param object|null $registered What's registered.
	 * @param object|null $native     Native definition, processed the same way.
	 * @param string[]    $properties Properties to compare.
	 * @param string[]    $labels     Labels Galerio words itself.
	 * @return array[]
	 */
	private static function compare( $name, $registered, $native, array $properties, array $labels ) {
		if ( ! $registered ) {
			return array( self::difference( $name, 'registered', false, true ) );
		}

		$differences = array();

		foreach ( $properties as $property ) {
			if ( (array) $registered->$property != (array) $native->$property ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Key order and "1" against true don't matter here.
				$differences[] = self::difference( $name, $property, $registered->$property, $native->$property );
			}
		}

		// Only the labels Galerio words itself: WordPress fills in the others.
		foreach ( array_diff( $labels, self::REWORDED_LABELS ) as $label ) {
			if ( isset( $registered->labels->$label ) && $registered->labels->$label !== $native->labels->$label ) {
				$differences[] = self::difference( $name, 'labels.' . $label, $registered->labels->$label, $native->labels->$label );
			}
		}

		return $differences;
	}

	/**
	 * One difference.
	 *
	 * @param string $name       Object name.
	 * @param string $property   Property.
	 * @param mixed  $registered Registered value.
	 * @param mixed  $native     Native value.
	 * @return array
	 */
	private static function difference( $name, $property, $registered, $native ) {
		return array(
			'object'     => $name,
			'property'   => $property,
			'registered' => $registered,
			'native'     => $native,
		);
	}
}
