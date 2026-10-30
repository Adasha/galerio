<?php
/**
 * Filtering, sorting and page sizes for archive pages.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Archives;

use Galerio\Core\Fields\Registry;
use WP_Block;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Lets visitors narrow and sort the archives of Galerio's content types, and
 * sites set how many items each archive shows per page.
 *
 * Filters are in the page's address, e.g. /works/?medium=oil&sort=title, so
 * a filtered list can be shared and works without JavaScript. The keys are
 * the taxonomies' names without "galerio_" (medium, label, group, role,
 * collection_category, article_category) and "sort". They're applied to the
 * page's main query, so a Query Loop that inherits it follows, pages
 * included. Filtered and sorted pages ask search engines not to index them.
 *
 * Term pages, such as a medium's, can be sorted but not filtered.
 *
 * The `galerio/archive-filters` block shows the filters as dropdowns that
 * apply as soon as they change.
 */
final class Archives {

	/**
	 * The page size option: post type => items per page, 0 for WordPress's
	 * own setting.
	 */
	const PER_PAGE_OPTION = 'galerio_settings_per_page';

	/**
	 * The sort key in the address.
	 */
	const SORT = 'sort';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'query' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'render_block_core/query-no-results', array( __CLASS__, 'no_results' ), 10, 3 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Register the block from its metadata.
	 */
	public static function register() {
		register_block_type(
			GALERIO_CORE_DIR . 'blocks/archive-filters',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * The Galerio post type whose archive a query is, if any.
	 *
	 * @param WP_Query $query Query.
	 * @return string Post type, or empty.
	 */
	private static function archive_type( WP_Query $query ) {
		foreach ( Registry::OBJECTS as $object => $post_type ) {
			if ( 'attachment' !== $object && $query->is_post_type_archive( $post_type ) ) {
				return $post_type;
			}
		}

		return '';
	}

	/**
	 * Whether a query is a Galerio taxonomy's term page.
	 *
	 * @param WP_Query $query Query.
	 * @return bool
	 */
	private static function is_term_page( WP_Query $query ) {
		if ( ! $query->is_tax() ) {
			return false;
		}

		$term = $query->get_queried_object();

		return $term && isset( $term->taxonomy ) && in_array( $term->taxonomy, self::taxonomies(), true );
	}

	/**
	 * Galerio's public taxonomies.
	 *
	 * @return string[]
	 */
	private static function taxonomies() {
		return array_values(
			array_filter(
				get_taxonomies( array( 'public' => true ) ),
				function ( $taxonomy ) {
					return 0 === strpos( $taxonomy, 'galerio_' );
				}
			)
		);
	}

	/**
	 * The taxonomies a type's archive can be filtered by: key in the
	 * address => taxonomy.
	 *
	 * @param string $post_type Post type.
	 * @return array<string, string>
	 */
	public static function filters( $post_type ) {
		$filters = array();

		foreach ( self::taxonomies() as $taxonomy ) {
			if ( is_object_in_taxonomy( $post_type, $taxonomy ) ) {
				$filters[ substr( $taxonomy, strlen( 'galerio_' ) ) ] = $taxonomy;
			}
		}

		// The most telling first: what a work is made of, then its style.
		$order = array_flip( array( 'medium', 'role', 'collection_category', 'article_category', 'label', 'group' ) );
		uksort(
			$filters,
			function ( $a, $b ) use ( $order ) {
				return ( isset( $order[ $a ] ) ? $order[ $a ] : 99 ) - ( isset( $order[ $b ] ) ? $order[ $b ] : 99 );
			}
		);

		/**
		 * Filters the taxonomies a Galerio archive can be filtered by.
		 *
		 * @param array<string, string> $filters   Key in the address => taxonomy.
		 * @param string                $post_type Post type.
		 */
		return (array) apply_filters( 'galerio_archive_filters', $filters, $post_type );
	}

	/**
	 * The orders an archive can be sorted in: key => label. The first is the
	 * default.
	 *
	 * @param string $post_type Post type, or empty for a term page.
	 * @return array<string, string>
	 */
	public static function sorts( $post_type ) {
		$sorts = array(
			'newest' => __( 'Newest first', 'galerio-core' ),
			'oldest' => __( 'Oldest first', 'galerio-core' ),
			'title'  => __( 'Title, A to Z', 'galerio-core' ),
		);

		if ( 'galerio_work' === $post_type ) {
			$sorts['year'] = __( 'Year made, latest first', 'galerio-core' );
		}

		return $sorts;
	}

	/**
	 * The filters and sort the address asks for, checked: only known keys,
	 * and terms that exist.
	 *
	 * @param string $post_type Post type, or empty for a term page.
	 * @return array{terms: array<string, \WP_Term>, sort: string}
	 */
	public static function current( $post_type ) {
		$terms = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters in the address.
		foreach ( $post_type ? self::filters( $post_type ) : array() as $key => $taxonomy ) {
			$slug = isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_title( wp_unslash( $_GET[ $key ] ) ) : '';
			$term = '' !== $slug ? get_term_by( 'slug', $slug, $taxonomy ) : false;

			if ( $term ) {
				$terms[ $key ] = $term;
			}
		}

		$sort = isset( $_GET[ self::SORT ] ) && is_string( $_GET[ self::SORT ] ) ? sanitize_key( wp_unslash( $_GET[ self::SORT ] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return array(
			'terms' => $terms,
			'sort'  => isset( self::sorts( $post_type )[ $sort ] ) ? $sort : '',
		);
	}

	/**
	 * Items per page for a type's archive.
	 *
	 * @param string $post_type Post type.
	 * @return int 0 for WordPress's own setting.
	 */
	public static function per_page( $post_type ) {
		$sizes = (array) get_option( self::PER_PAGE_OPTION, array() );

		return isset( $sizes[ $post_type ] ) ? absint( $sizes[ $post_type ] ) : 0;
	}

	/**
	 * Apply the page size, filters and sort to an archive's main query.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function query( WP_Query $query ) {
		if ( is_admin() || ! $query->is_main_query() || $query->is_feed() ) {
			return;
		}

		$post_type = self::archive_type( $query );

		if ( ! $post_type && ! self::is_term_page( $query ) ) {
			return;
		}

		if ( $post_type && self::per_page( $post_type ) ) {
			$query->set( 'posts_per_page', self::per_page( $post_type ) );
		}

		$current = self::current( $post_type );

		if ( $current['terms'] ) {
			$tax_query = array_filter( (array) $query->get( 'tax_query' ) );

			foreach ( $current['terms'] as $term ) {
				$tax_query[] = array(
					'taxonomy' => $term->taxonomy,
					'terms'    => array( $term->term_id ),
				);
			}

			$query->set( 'tax_query', array_merge( array( 'relation' => 'AND' ), $tax_query ) );
		}

		switch ( $current['sort'] ) {
			case 'oldest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'ASC' );
				break;

			case 'title':
				$query->set( 'orderby', 'title' );
				$query->set( 'order', 'ASC' );
				break;

			case 'year':
				// Works without a year come last rather than being left out.
				$key = galerio_fields()->get( 'work.year' )->source;
				$query->set(
					'meta_query',
					array(
						'relation'     => 'OR',
						'galerio_year' => array(
							'key'     => $key,
							'compare' => 'EXISTS',
						),
						array(
							'key'     => $key,
							'compare' => 'NOT EXISTS',
						),
					)
				);
				$query->set(
					'orderby',
					array(
						'galerio_year' => 'DESC',
						'date'         => 'DESC',
					)
				);
				break;
		}
	}

	/**
	 * Filtered and sorted archives ask search engines not to index them, as
	 * they repeat the archive's items.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query || ( ! self::archive_type( $wp_query ) && ! self::is_term_page( $wp_query ) ) ) {
			return $robots;
		}

		$current = self::current( self::archive_type( $wp_query ) );

		if ( $current['terms'] || '' !== $current['sort'] ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}

		return $robots;
	}

	/**
	 * When filters leave an archive empty, its No results block says so,
	 * with a way back, rather than that there's nothing yet.
	 *
	 * @param string   $content  Rendered block.
	 * @param array    $block    Parsed block.
	 * @param WP_Block $instance Block instance.
	 * @return string
	 */
	public static function no_results( $content, $block, $instance ) {
		global $wp_query;

		if ( '' === trim( $content ) || empty( $instance->context['query']['inherit'] ) || ! $wp_query instanceof WP_Query ) {
			return $content;
		}

		$post_type = self::archive_type( $wp_query );

		if ( ! $post_type || ! self::current( $post_type )['terms'] ) {
			return $content;
		}

		return sprintf(
			'<div class="wp-block-query-no-results"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html__( 'Nothing matches these filters.', 'galerio-core' ),
			esc_url( get_post_type_archive_link( $post_type ) ),
			esc_html__( 'Show everything', 'galerio-core' )
		);
	}

	/**
	 * Render the block: the filters and sort for the archive being viewed.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Empty when the page isn't a Galerio archive.
	 */
	public static function render( $attributes ) {
		global $wp_query;

		if ( ! $wp_query instanceof WP_Query ) {
			return '';
		}

		$post_type = self::archive_type( $wp_query );

		if ( $post_type ) {
			$action = get_post_type_archive_link( $post_type );
		} elseif ( self::is_term_page( $wp_query ) ) {
			$action = get_term_link( $wp_query->get_queried_object() );
		} else {
			return '';
		}

		$current  = self::current( $post_type );
		$controls = array();

		if ( ! empty( $attributes['showFilters'] ) ) {
			foreach ( ( $post_type ? self::filters( $post_type ) : array() ) as $key => $taxonomy ) {
				$terms = self::terms_in_use( $taxonomy, $post_type );

				// A filter with one choice or none can't narrow anything.
				if ( count( $terms ) < 2 ) {
					continue;
				}

				$options = array( '' => get_taxonomy( $taxonomy )->labels->all_items );
				foreach ( $terms as $term ) {
					$options[ $term->slug ] = $term->name;
				}

				$selected   = isset( $current['terms'][ $key ] ) ? $current['terms'][ $key ]->slug : '';
				$controls[] = self::select( $key, get_taxonomy( $taxonomy )->labels->singular_name, $options, $selected );
			}
		}

		if ( ! empty( $attributes['showSort'] ) ) {
			$sorts      = self::sorts( $post_type );
			$default    = array_key_first( $sorts );
			$options    = array( '' => $sorts[ $default ] ) + array_diff_key( $sorts, array( $default => true ) );
			$controls[] = self::select( self::SORT, __( 'Sort', 'galerio-core' ), $options, $current['sort'] );
		}

		if ( ! $controls ) {
			return '';
		}

		$clear = $current['terms'] || '' !== $current['sort']
			? sprintf( '<a class="wp-block-galerio-archive-filters__clear" href="%1$s">%2$s</a>', esc_url( $action ), esc_html__( 'Clear', 'galerio-core' ) )
			: '';

		self::assets();

		$form = sprintf(
			'<form class="wp-block-galerio-archive-filters__form" method="get" action="%1$s" role="search" aria-label="%2$s">%3$s<button type="submit" class="wp-block-galerio-archive-filters__apply wp-element-button">%4$s</button>%5$s</form>',
			esc_url( $action ),
			esc_attr__( 'Filter and sort', 'galerio-core' ),
			implode( '', $controls ),
			esc_html__( 'Apply', 'galerio-core' ),
			$clear
		);

		if ( ! empty( $attributes['collapsible'] ) ) {
			$active = count( $current['terms'] ) + ( '' !== $current['sort'] ? 1 : 0 );
			$label  = '' !== trim( (string) $attributes['label'] ) ? $attributes['label'] : __( 'Filters', 'galerio-core' );
			$count  = $active
				? sprintf(
					' <span class="wp-block-galerio-archive-filters__count">%s</span>',
					/* translators: %s: how many filters and sorts are in use. */
					esc_html( sprintf( _n( '(%s in use)', '(%s in use)', $active, 'galerio-core' ), number_format_i18n( $active ) ) )
				)
				: '';

			// Open while something is chosen, so the visitor sees what.
			$form = sprintf(
				'<details class="wp-block-galerio-archive-filters__toggle"%1$s><summary class="wp-block-galerio-archive-filters__summary">%2$s%3$s</summary>%4$s</details>',
				$active ? ' open' : '',
				esc_html( $label ),
				$count,
				$form
			);
		}

		return sprintf( '<div %1$s>%2$s</div>', get_block_wrapper_attributes(), $form );
	}

	/**
	 * The terms of a taxonomy that published items of a type have, by name.
	 *
	 * @param string $taxonomy  Taxonomy.
	 * @param string $post_type Post type.
	 * @return \WP_Term[]
	 */
	public static function terms_in_use( $taxonomy, $post_type ) {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		// A taxonomy shared by several types counts every type's items, so
		// keep only the terms this type's items have.
		$used = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Only items with a term of this taxonomy.
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'operator' => 'EXISTS',
					),
				),
			)
		);
		$ids  = $used ? wp_get_object_terms( $used, $taxonomy, array( 'fields' => 'ids' ) ) : array();
		$ids  = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );

		return array_values(
			array_filter(
				$terms,
				function ( $term ) use ( $ids ) {
					return in_array( (int) $term->term_id, $ids, true );
				}
			)
		);
	}

	/**
	 * One dropdown.
	 *
	 * @param string $name     Key in the address.
	 * @param string $label    Label.
	 * @param array  $options  Value => text; the empty value is the default.
	 * @param string $selected Selected value.
	 * @return string
	 */
	private static function select( $name, $label, array $options, $selected ) {
		$id   = 'galerio-archive-' . $name;
		$html = '';

		foreach ( $options as $value => $text ) {
			$html .= sprintf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( (string) $value ), selected( (string) $value, $selected, false ), esc_html( $text ) );
		}

		return sprintf(
			'<p class="wp-block-galerio-archive-filters__field"><label for="%1$s">%2$s</label><select id="%1$s" name="%3$s">%4$s</select></p>',
			esc_attr( $id ),
			esc_html( $label ),
			esc_attr( $name ),
			$html
		);
	}

	/**
	 * The block's script and styles. The script applies a choice as soon as
	 * it's made and leaves defaults out of the address; without it, the
	 * Apply button does.
	 */
	private static function assets() {
		if ( wp_script_is( 'galerio-archive-filters' ) ) {
			return;
		}

		wp_register_script( 'galerio-archive-filters', false, array(), GALERIO_CORE_VERSION, array( 'in_footer' => true ) );
		wp_enqueue_script( 'galerio-archive-filters' );
		wp_add_inline_script(
			'galerio-archive-filters',
			'document.querySelectorAll( ".wp-block-galerio-archive-filters__form" ).forEach( function ( form ) {
				form.classList.add( "is-live" );
				var send = function () {
					form.querySelectorAll( "select" ).forEach( function ( select ) {
						select.disabled = "" === select.value;
					} );
				};
				form.addEventListener( "submit", send );
				form.addEventListener( "change", function () {
					send();
					form.submit();
				} );
			} );'
		);

		wp_register_style( 'galerio-archive-filters', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-archive-filters' );
		wp_add_inline_style(
			'galerio-archive-filters',
			// Layout only, at no specificity, so a theme's styles win.
			':where(.wp-block-galerio-archive-filters__form){display:flex;flex-wrap:wrap;align-items:flex-end;gap:0.75em 1.5em;}
			:where(.wp-block-galerio-archive-filters__toggle) > :where(.wp-block-galerio-archive-filters__form){margin-top:0.75em;}
			:where(.wp-block-galerio-archive-filters__field){margin:0;}
			:where(.wp-block-galerio-archive-filters__field label){display:block;margin-bottom:0.25em;}
			:where(.wp-block-galerio-archive-filters__field select){font:inherit;max-width:100%;}
			.wp-block-galerio-archive-filters__form.is-live .wp-block-galerio-archive-filters__apply{display:none;}
			:where(.wp-block-galerio-archive-filters__clear){align-self:center;}
			:where(.wp-block-galerio-archive-filters__summary){display:inline-flex;align-items:center;gap:0.5em;cursor:pointer;list-style:none;}
			.wp-block-galerio-archive-filters__summary::-webkit-details-marker{display:none;}
			:where(.wp-block-galerio-archive-filters__summary)::before{content:"";flex:none;width:0.4em;height:0.4em;border-right:2px solid currentColor;border-bottom:2px solid currentColor;transform:rotate(-45deg);transition:transform 0.15s ease;}
			:where(.wp-block-galerio-archive-filters__toggle[open]) > :where(.wp-block-galerio-archive-filters__summary)::before{transform:rotate(45deg);}
			@media (prefers-reduced-motion: reduce){:where(.wp-block-galerio-archive-filters__summary)::before{transition:none;}}'
		);
	}

	/**
	 * The page size settings on Settings → Reading.
	 */
	public static function register_settings() {
		register_setting(
			'reading',
			self::PER_PAGE_OPTION,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( __CLASS__, 'sanitize_per_page' ),
			)
		);

		add_settings_field(
			'galerio_per_page',
			__( 'Galerio archives show at most', 'galerio-core' ),
			array( __CLASS__, 'per_page_field' ),
			'reading',
			'default'
		);
	}

	/**
	 * Keep whole numbers for Galerio's types only.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<string, int>
	 */
	public static function sanitize_per_page( $value ) {
		$sizes = array();

		foreach ( self::types() as $post_type ) {
			$size = isset( $value[ $post_type ] ) ? absint( $value[ $post_type ] ) : 0;

			if ( $size ) {
				$sizes[ $post_type ] = min( $size, 100 );
			}
		}

		return $sizes;
	}

	/**
	 * The page size fields.
	 */
	public static function per_page_field() {
		echo '<fieldset>';

		foreach ( self::types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object ) {
				continue;
			}

			$id = 'galerio-per-page-' . $post_type;
			printf(
				'<label for="%1$s"><input type="number" min="0" max="100" step="1" class="small-text" id="%1$s" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s"> %6$s</label><br>',
				esc_attr( $id ),
				esc_attr( self::PER_PAGE_OPTION ),
				esc_attr( $post_type ),
				esc_attr( self::per_page( $post_type ) ? (string) self::per_page( $post_type ) : '' ),
				esc_attr( (string) get_option( 'posts_per_page' ) ),
				esc_html( $object->labels->name )
			);
		}

		printf( '<p class="description">%s</p></fieldset>', esc_html__( 'Items per page on each archive. Leave empty to use “Blog pages show at most”. A grid fills its rows with a number its columns divide, such as 12.', 'galerio-core' ) );
	}

	/**
	 * Galerio's post types with archives.
	 *
	 * @return string[]
	 */
	private static function types() {
		return array_values( array_diff( Registry::OBJECTS, array( 'attachment' ) ) );
	}
}
