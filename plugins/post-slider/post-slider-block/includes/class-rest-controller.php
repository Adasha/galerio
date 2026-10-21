<?php
/**
 * REST API endpoints for the block editor's dynamic controls.
 */

declare( strict_types=1 );

namespace PostSliderBlock;

class REST_Controller {

	private const NAMESPACE = 'post-slider-block/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		// Public post types.
		register_rest_route( self::NAMESPACE, '/post-types', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_post_types' ],
			'permission_callback' => [ __CLASS__, 'editor_permission' ],
		] );

		// Taxonomies for a given post type.
		register_rest_route( self::NAMESPACE, '/taxonomies', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_taxonomies' ],
			'permission_callback' => [ __CLASS__, 'editor_permission' ],
			'args'                => [
				'post_type' => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_key',
				],
			],
		] );

		// Terms for a given taxonomy.
		register_rest_route( self::NAMESPACE, '/terms', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_terms' ],
			'permission_callback' => [ __CLASS__, 'editor_permission' ],
			'args'                => [
				'taxonomy' => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				],
				'search'   => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public static function get_post_types( \WP_REST_Request $request ): \WP_REST_Response {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		$result     = [];

		foreach ( $post_types as $type ) {
			$result[] = [
				'slug'  => $type->name,
				'label' => $type->label,
			];
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public static function get_taxonomies( \WP_REST_Request $request ): \WP_REST_Response {
		$post_type  = $request->get_param( 'post_type' ) ?: '';
		$args       = [ 'public' => true ];

		$taxonomies = $post_type
			? get_object_taxonomies( $post_type, 'objects' )
			: get_taxonomies( $args, 'objects' );

		$result = [];
		foreach ( $taxonomies as $taxonomy ) {
			$result[] = [
				'slug'  => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public static function get_terms( \WP_REST_Request $request ): \WP_REST_Response {
		$taxonomy = $request->get_param( 'taxonomy' );
		$search   = $request->get_param( 'search' ) ?: '';

		$terms = get_terms( [
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => 100,
			'search'     => $search,
		] );

		if ( is_wp_error( $terms ) ) {
			return new \WP_REST_Response( [], 200 );
		}

		$result = array_map( fn( \WP_Term $t ) => [
			'id'    => $t->term_id,
			'label' => $t->name,
			'count' => $t->count,
		], $terms );

		return new \WP_REST_Response( $result, 200 );
	}

	public static function editor_permission(): bool {
		return current_user_can( 'edit_posts' );
	}
}
