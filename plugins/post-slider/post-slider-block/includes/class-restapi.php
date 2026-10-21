<?php
/**
 * REST API endpoints for the block editor inspector.
 *
 * @package PostSliderBlock
 */

declare( strict_types=1 );

namespace PostSliderBlock;

/**
 * Class RestApi
 */
class RestApi {

	private const NAMESPACE = 'post-slider-block/v1';

	/**
	 * Register REST routes.
	 */
	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	/**
	 * Register all routes.
	 */
	public static function register_routes(): void {
		// Fetch all public post types.
		register_rest_route(
			self::NAMESPACE,
			'/post-types',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_post_types' ],
				'permission_callback' => [ self::class, 'editor_permission' ],
			]
		);

		// Fetch taxonomies for a given post type.
		register_rest_route(
			self::NAMESPACE,
			'/taxonomies',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_taxonomies' ],
				'permission_callback' => [ self::class, 'editor_permission' ],
				'args'                => [
					'post_type' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		// Fetch terms for a given taxonomy.
		register_rest_route(
			self::NAMESPACE,
			'/terms',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_terms' ],
				'permission_callback' => [ self::class, 'editor_permission' ],
				'args'                => [
					'taxonomy' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'search'   => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					],
				],
			]
		);
	}

	/**
	 * Return all public post types.
	 */
	public static function get_post_types(): \WP_REST_Response {
		$types = get_post_types( [ 'public' => true ], 'objects' );

		$data = array_values(
			array_map(
				fn( \WP_Post_Type $t ) => [
					'value' => $t->name,
					'label' => $t->label,
				],
				$types
			)
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Return taxonomies registered to a post type.
	 */
	public static function get_taxonomies( \WP_REST_Request $request ): \WP_REST_Response {
		$post_type  = $request->get_param( 'post_type' );
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		$data = array_values(
			array_map(
				fn( \WP_Taxonomy $t ) => [
					'value' => $t->name,
					'label' => $t->label,
				],
				$taxonomies
			)
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Return terms for a taxonomy (with optional search).
	 */
	public static function get_terms( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$taxonomy = $request->get_param( 'taxonomy' );
		$search   = $request->get_param( 'search' );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_Error( 'invalid_taxonomy', __( 'Taxonomy not found.', 'post-slider-block' ), [ 'status' => 404 ] );
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 100,
				'search'     => $search,
			]
		);

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		$data = array_map(
			fn( \WP_Term $t ) => [
				'value' => $t->term_id,
				'label' => $t->name . ' (' . $t->count . ')',
			],
			$terms
		);

		return rest_ensure_response( array_values( $data ) );
	}

	/**
	 * Only users who can edit posts may call these endpoints.
	 */
	public static function editor_permission(): bool {
		return current_user_can( 'edit_posts' );
	}
}
