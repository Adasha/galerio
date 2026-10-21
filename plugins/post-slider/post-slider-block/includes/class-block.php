<?php
/**
 * Block registration and server-side rendering.
 *
 * @package PostSliderBlock
 */

declare( strict_types=1 );

namespace PostSliderBlock;

/**
 * Class Block
 */
class Block {

	/**
	 * Register the block and its assets.
	 */
	public static function register(): void {
		register_block_type(
			PSB_PLUGIN_DIR . 'build',
			[
				'render_callback' => [ self::class, 'render' ],
			]
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block inner content.
	 *
	 * @return string  Rendered HTML.
	 */
	public static function render( array $attributes, string $content ): string {
		$atts = wp_parse_args( $attributes, self::defaults() );

		$posts = self::fetch_posts( $atts );

		if ( empty( $posts ) ) {
			return '<p class="psb-no-posts">' . esc_html__( 'No posts found.', 'post-slider-block' ) . '</p>';
		}

		/* ------------------------------------------------------------------ */
		/* Inline CSS custom properties from block attributes                  */
		/* ------------------------------------------------------------------ */
		$css_vars = self::build_css_vars( $atts );

		/* ------------------------------------------------------------------ */
		/* Build the wrapper                                                   */
		/* ------------------------------------------------------------------ */
		$wrapper_attributes = get_block_wrapper_attributes(
			[
				'class'           => 'psb-slider psb-transition--' . esc_attr( $atts['transition'] ),
				'style'           => $css_vars,
				'data-autoplay'   => $atts['autoplay'] ? 'true' : 'false',
				'data-interval'   => (int) $atts['autoplayInterval'],
				'data-transition' => esc_attr( $atts['transition'] ),
				'data-show-nav'   => $atts['showNavigation'] ? 'true' : 'false',
				'data-show-dots'  => $atts['showDots'] ? 'true' : 'false',
				'data-pause-hover'=> $atts['pauseOnHover'] ? 'true' : 'false',
			]
		);

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; // phpcs:ignore ?>>
			<div class="psb-track">
				<?php foreach ( $posts as $index => $post ) : ?>
					<?php self::render_slide( $post, $atts, $index ); ?>
				<?php endforeach; ?>
			</div>

			<?php if ( $atts['showNavigation'] ) : ?>
			<div class="psb-nav" aria-label="<?php esc_attr_e( 'Slider navigation', 'post-slider-block' ); ?>">
				<button class="psb-nav__btn psb-nav__btn--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'post-slider-block' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
						<polyline points="15 18 9 12 15 6"/>
					</svg>
				</button>
				<button class="psb-nav__btn psb-nav__btn--next" aria-label="<?php esc_attr_e( 'Next slide', 'post-slider-block' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
						<polyline points="9 18 15 12 9 6"/>
					</svg>
				</button>
			</div>
			<?php endif; ?>

			<?php if ( $atts['showDots'] ) : ?>
			<div class="psb-dots" role="tablist" aria-label="<?php esc_attr_e( 'Slide indicators', 'post-slider-block' ); ?>">
				<?php foreach ( $posts as $index => $post ) : ?>
					<button
						class="psb-dots__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %d', 'post-slider-block' ), $index + 1 ) ); ?>"
						data-index="<?php echo (int) $index; ?>"
					></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Private helpers                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Render a single slide.
	 */
	private static function render_slide( \WP_Post $post, array $atts, int $index ): void {
		$title        = get_the_title( $post );
		$permalink    = get_permalink( $post );
		$excerpt      = self::get_excerpt( $post, (int) $atts['excerptLength'] );
		$has_image    = $atts['showFeaturedImage'] && has_post_thumbnail( $post );
		$image_url    = $has_image ? get_the_post_thumbnail_url( $post, $atts['imageSize'] ) : '';
		$date         = get_the_date( '', $post );
		$author_name  = get_the_author_meta( 'display_name', (int) $post->post_author );
		$terms_html   = self::get_terms_html( $post, $atts );
		?>
		<div
			class="psb-slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
			role="tabpanel"
			aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>"
			data-index="<?php echo (int) $index; ?>"
		>
			<?php if ( $has_image ) : ?>
				<div class="psb-slide__media">
					<img
						src="<?php echo esc_url( $image_url ); ?>"
						alt="<?php echo esc_attr( $title ); ?>"
						loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
					/>
					<?php if ( $atts['overlayOpacity'] > 0 ) : ?>
						<div class="psb-slide__overlay" aria-hidden="true"></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="psb-slide__content">
				<?php if ( $atts['showTerms'] && $terms_html ) : ?>
					<div class="psb-slide__terms"><?php echo $terms_html; // phpcs:ignore ?></div>
				<?php endif; ?>

				<<?php echo tag_escape( $atts['headingTag'] ); ?> class="psb-slide__title">
					<?php if ( $atts['linkTitle'] ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $title ); ?>
					<?php endif; ?>
				</<?php echo tag_escape( $atts['headingTag'] ); ?>>

				<?php if ( $atts['showMeta'] ) : ?>
					<div class="psb-slide__meta">
						<time datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>"><?php echo esc_html( $date ); ?></time>
						<?php if ( $atts['showAuthor'] ) : ?>
							<span class="psb-slide__author"><?php echo esc_html( $author_name ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $atts['showExcerpt'] && $excerpt ) : ?>
					<p class="psb-slide__excerpt"><?php echo esc_html( $excerpt ); ?></p>
				<?php endif; ?>

				<?php if ( $atts['showReadMore'] ) : ?>
					<a class="psb-slide__cta" href="<?php echo esc_url( $permalink ); ?>">
						<?php echo esc_html( $atts['readMoreText'] ); ?>
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
							<line x1="5" y1="12" x2="19" y2="12"/>
							<polyline points="12 5 19 12 12 19"/>
						</svg>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Query posts based on block attributes.
	 *
	 * @return \WP_Post[]
	 */
	private static function fetch_posts( array $atts ): array {
		$args = [
			'post_type'           => $atts['postType'] ?: 'post',
			'posts_per_page'      => max( 1, min( 50, (int) $atts['numberOfPosts'] ) ),
			'post_status'         => 'publish',
			'orderby'             => $atts['orderBy'],
			'order'               => $atts['order'],
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		];

		// Taxonomy filter.
		if ( ! empty( $atts['selectedTerms'] ) && ! empty( $atts['taxonomy'] ) ) {
			$args['tax_query'] = [
				[
					'taxonomy' => sanitize_key( $atts['taxonomy'] ),
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', (array) $atts['selectedTerms'] ),
				],
			];
		}

		// Exclude current post on single views.
		if ( is_singular() ) {
			$args['post__not_in'] = [ get_the_ID() ];
		}

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Truncate excerpt to a given word count.
	 */
	private static function get_excerpt( \WP_Post $post, int $length ): string {
		if ( $length <= 0 ) {
			return '';
		}
		$excerpt = $post->post_excerpt ?: wp_strip_all_tags( $post->post_content );
		$words   = explode( ' ', $excerpt );

		if ( count( $words ) <= $length ) {
			return $excerpt;
		}

		return implode( ' ', array_slice( $words, 0, $length ) ) . '&hellip;';
	}

	/**
	 * Build linked term pills.
	 */
	private static function get_terms_html( \WP_Post $post, array $atts ): string {
		$taxonomy = $atts['taxonomy'] ?: 'category';
		$terms    = get_the_terms( $post, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$html = '';
		foreach ( array_slice( $terms, 0, 3 ) as $term ) {
			$html .= sprintf(
				'<a class="psb-slide__term" href="%s">%s</a>',
				esc_url( get_term_link( $term ) ),
				esc_html( $term->name )
			);
		}

		return $html;
	}

	/**
	 * Convert design attributes into CSS custom properties.
	 */
	private static function build_css_vars( array $atts ): string {
		$vars = [];

		if ( ! empty( $atts['accentColor'] ) ) {
			$vars[] = '--psb-accent: ' . sanitize_hex_color( $atts['accentColor'] );
		}
		if ( ! empty( $atts['textColor'] ) ) {
			$vars[] = '--psb-text: ' . sanitize_hex_color( $atts['textColor'] );
		}
		if ( ! empty( $atts['backgroundColor'] ) ) {
			$vars[] = '--psb-bg: ' . sanitize_hex_color( $atts['backgroundColor'] );
		}
		if ( isset( $atts['overlayOpacity'] ) ) {
			$vars[] = '--psb-overlay-opacity: ' . (float) $atts['overlayOpacity'];
		}
		if ( ! empty( $atts['overlayColor'] ) ) {
			$vars[] = '--psb-overlay-color: ' . sanitize_hex_color( $atts['overlayColor'] );
		}
		if ( ! empty( $atts['slideHeight'] ) ) {
			$vars[] = '--psb-height: ' . (int) $atts['slideHeight'] . 'px';
		}
		if ( ! empty( $atts['borderRadius'] ) ) {
			$vars[] = '--psb-radius: ' . (int) $atts['borderRadius'] . 'px';
		}
		if ( isset( $atts['transitionDuration'] ) ) {
			$vars[] = '--psb-duration: ' . (int) $atts['transitionDuration'] . 'ms';
		}
		if ( ! empty( $atts['contentPosition'] ) ) {
			$vars[] = '--psb-content-align: ' . esc_attr( $atts['contentPosition'] );
		}

		return $vars ? implode( '; ', $vars ) . ';' : '';
	}

	/**
	 * Default attribute values (mirrors block.json).
	 */
	private static function defaults(): array {
		return [
			'postType'          => 'post',
			'numberOfPosts'     => 5,
			'taxonomy'          => '',
			'selectedTerms'     => [],
			'orderBy'           => 'date',
			'order'             => 'DESC',
			'transition'        => 'fade',
			'transitionDuration'=> 600,
			'autoplay'          => false,
			'autoplayInterval'  => 5000,
			'pauseOnHover'      => true,
			'showNavigation'    => true,
			'showDots'          => true,
			'showFeaturedImage' => true,
			'imageSize'         => 'large',
			'overlayOpacity'    => 0.4,
			'overlayColor'      => '#000000',
			'showExcerpt'       => true,
			'excerptLength'     => 20,
			'showMeta'          => true,
			'showAuthor'        => false,
			'showTerms'         => true,
			'showReadMore'      => true,
			'readMoreText'      => 'Read More',
			'linkTitle'         => true,
			'headingTag'        => 'h3',
			'contentPosition'   => 'left',
			'accentColor'       => '#3b82f6',
			'textColor'         => '#ffffff',
			'backgroundColor'   => '#1e293b',
			'slideHeight'       => 520,
			'borderRadius'      => 12,
		];
	}
}
