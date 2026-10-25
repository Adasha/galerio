<?php
/**
 * A site's content, moved onto Galerio's templates and blocks.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Applies a site's decisions about its content rather than its fields:
 * page templates, per-page CSS, blocks from the old model, pages to retire,
 * menu items and Galerio's names and addresses for its content types.
 */
final class Site_Content {

	/**
	 * Galerio post type => field registry object, for Field block keys.
	 *
	 * @var array<string, string>
	 */
	const OBJECTS = array(
		'galerio_work'       => 'work',
		'galerio_profile'    => 'profile',
		'galerio_collection' => 'collection',
		'galerio_article'    => 'article',
	);

	/**
	 * Old field names a Pods field block showed => Galerio's, where they differ.
	 *
	 * @var array<string, string>
	 */
	const FIELD_KEYS = array(
		'opening_date' => 'start_date',
		'closing_date' => 'end_date',
	);

	/**
	 * The site's decisions.
	 *
	 * @var array
	 */
	private $site;

	/**
	 * What this run did.
	 *
	 * @var Report
	 */
	private $report;

	/**
	 * Report without writing.
	 *
	 * @var bool
	 */
	private $dry;

	/**
	 * Set up a run.
	 *
	 * @param array  $site   The site's decisions.
	 * @param Report $report Report to add to.
	 * @param bool   $dry    Report without writing.
	 */
	public function __construct( array $site, Report $report, $dry ) {
		$this->site   = $site + array(
			'post_types'     => array(),
			'page_templates' => array(),
			'presents'       => array(),
			'page_css'       => '',
			'drafts'         => array(),
			'menu_archives'  => array(),
			'navigation'     => 0,
			'aliases'        => array(),
		);
		$this->report = $report;
		$this->dry    = (bool) $dry;
	}

	/**
	 * Run every step.
	 */
	public function run() {
		$this->page_templates();
		$this->page_css();
		$this->blocks();
		$this->drafts();
		$this->menu_archives();
		$this->navigation();
		$this->aliases();
	}

	/**
	 * Swap old page templates for Galerio's, adding the "Site presents…" line
	 * to items whose old template showed one.
	 */
	private function page_templates() {
		global $wpdb;

		$step = 'Page templates';

		if ( ! $this->site['page_templates'] ) {
			return;
		}

		$in = implode( ', ', array_fill( 0, count( $this->site['page_templates'] ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off migration; $in is placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.post_id, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_wp_page_template' AND p.post_type <> 'revision' AND m.meta_value IN ($in)", array_keys( $this->site['page_templates'] ) ) );

		foreach ( $rows as $row ) {
			$id  = (int) $row->post_id;
			$new = $this->site['page_templates'][ $row->meta_value ];

			$this->report->count( $step, $this->dry ? 'would change' : 'changed' );
			$this->report->note( $step, sprintf( '%s (#%d): %s → %s.', get_the_title( $id ), $id, $row->meta_value, '' !== $new ? $new : 'default' ) );

			if ( in_array( $row->meta_value, $this->site['presents'], true ) ) {
				$this->add_presents( $id );
			}

			if ( $this->dry ) {
				continue;
			}

			if ( '' === $new ) {
				delete_post_meta( $id, '_wp_page_template' );
			} else {
				update_post_meta( $id, '_wp_page_template', $new );
			}
		}

		if ( ! $rows ) {
			$this->report->count( $step, 'already done' );
		}
	}

	/**
	 * Put the "Site presents…" pattern at the top of an item's content.
	 *
	 * @param int $id Post ID.
	 */
	private function add_presents( $id ) {
		$content = (string) get_post_field( 'post_content', $id, 'raw' );

		if ( false !== strpos( $content, 'galerio/presents' ) ) {
			return;
		}

		$this->report->count( 'Page templates', $this->dry ? 'would add "Site presents…"' : 'added "Site presents…"' );

		if ( ! $this->dry ) {
			$this->save_content( $id, '<!-- wp:pattern {"slug":"galerio/presents"} /-->' . "\n\n" . $content );
		}
	}

	/**
	 * Move per-item custom CSS from the old plugin's meta into Galerio's Page style.
	 */
	private function page_css() {
		global $wpdb;

		$step = 'Page style CSS';
		$key  = $this->site['page_css'];

		if ( '' === $key ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", $key ) );

		foreach ( $rows as $row ) {
			$id    = (int) $row->post_id;
			$css   = trim( (string) $row->meta_value );
			$style = get_post_meta( $id, 'galerio_page_style', true );
			$style = is_array( $style ) ? $style : array();

			if ( isset( $style['css'] ) && trim( $style['css'] ) === $css ) {
				$this->report->count( $step, 'already done' );
				continue;
			}

			if ( ! empty( $style['css'] ) ) {
				$this->report->count( $step, 'conflicts' );
				$this->report->note( $step, sprintf( '%s (#%d) already has Page style CSS that differs, so it was left alone.', get_the_title( $id ), $id ) );
				continue;
			}

			$this->report->count( $step, $this->dry ? 'would move' : 'moved' );
			$this->report->note( $step, sprintf( '%s (#%d): %d characters of CSS.', get_the_title( $id ), $id, strlen( $css ) ) );

			if ( ! $this->dry ) {
				$style['css'] = $css;
				$this->as_administrator(
					function () use ( $id, $style ) {
						update_post_meta( $id, 'galerio_page_style', $style );
					}
				);
			}
		}
	}

	/**
	 * Replace Pods blocks with Galerio's and core blocks.
	 */
	private function blocks() {
		global $wpdb;

		$step = 'Blocks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
		$posts = $wpdb->get_results( "SELECT ID, post_type, post_content FROM {$wpdb->posts} WHERE post_content LIKE '%<!-- wp:pods/%' AND post_type <> 'revision' AND post_status NOT IN ('auto-draft', 'trash')" );

		if ( ! $posts ) {
			$this->report->count( $step, 'already done' );
			return;
		}

		foreach ( $posts as $post ) {
			$type = isset( $this->site['post_types'][ $post->post_type ] ) ? $this->site['post_types'][ $post->post_type ] : $post->post_type;
			$kept = 0;

			$content = preg_replace_callback(
				'#<!-- wp:(pods/pods-block-(?:single|field)) (\{.*?\}) /-->#s',
				function ( $found ) use ( $post, $type, $step, &$kept ) {
					$attrs = json_decode( $found[2], true );
					$new   = $this->convert( $found[1], is_array( $attrs ) ? $attrs : array(), (int) $post->ID, $type );

					if ( null === $new ) {
						++$kept;
						$this->report->note( $step, sprintf( '%s (#%d): no replacement for %s, so it was kept.', get_the_title( $post->ID ), $post->ID, $found[0] ) );
						return $found[0];
					}

					$this->report->count( $step, $this->dry ? 'would replace' : 'replaced' );
					return $new;
				},
				$post->post_content
			);

			if ( $kept ) {
				$this->report->count( $step, 'kept', $kept );
			}

			if ( ! $this->dry && $content !== $post->post_content ) {
				$this->save_content( (int) $post->ID, $content );
			}
		}
	}

	/**
	 * The blocks that replace a Pods block.
	 *
	 * @param string $name  Block name.
	 * @param array  $attrs Block attributes.
	 * @param int    $id    The post the block is in.
	 * @param string $type  That post's type, as it is after the migration.
	 * @return string|null Block markup, or null when there's no replacement.
	 */
	private function convert( $name, array $attrs, $id, $type ) {
		$object = isset( self::OBJECTS[ $type ] ) ? self::OBJECTS[ $type ] : '';
		$align  = isset( $attrs['align'] ) && in_array( $attrs['align'], array( 'left', 'center', 'right' ), true ) ? array( 'className' => 'has-text-align-' . $attrs['align'] ) : array();

		if ( 'pods/pods-block-field' === $name ) {
			$field = isset( $attrs['field'] ) ? (string) $attrs['field'] : '';
			$field = isset( self::FIELD_KEYS[ $field ] ) ? self::FIELD_KEYS[ $field ] : $field;
			$key   = $object . '.' . $field;

			return $object && $field && galerio_fields()->get( $key ) ? self::block( 'galerio/field-display', array( 'key' => $key ) + $align ) : null;
		}

		$template = isset( $attrs['template']['value'] ) ? (string) $attrs['template']['value'] : '';
		$pod      = isset( $attrs['name']['value'] ) ? (string) $attrs['name']['value'] : '';

		switch ( $template ) {
			case 'category-buttons':
				return self::block(
					'post-terms',
					array(
						'term'      => 'galerio_collection_category',
						'className' => trim( 'is-style-buttons ' . ( isset( $align['className'] ) ? $align['className'] : '' ) ),
					)
				);

			case 'group-buttons':
				return self::block(
					'post-terms',
					array(
						'term'      => 'galerio_group',
						'className' => 'is-style-buttons',
					)
				);

			case 'exhibition-details':
				return self::block( 'galerio/field-display', array( 'key' => 'collection.location' ) ) . "\n\n" . self::block( 'galerio/exhibition-dates', array() );

			case 'linear-gallery':
				return $object ? self::block(
					'galerio/gallery',
					array(
						'field'  => $object . '.images',
						'layout' => 'linear',
					)
				) : null;

			case 'profile-buttons':
				if ( 'galerio_profile' === $pod ) {
					$this->report->note( 'Blocks', sprintf( "%s (#%d): its profile buttons were set to the profile type, so probably showed nothing; they now show the exhibition's profiles.", get_the_title( $id ), $id ) );
				}
				return self::block(
					'galerio/field-display',
					array(
						'key'       => 'collection.profiles',
						'layout'    => 'list',
						'className' => 'is-style-buttons',
					)
				);

			case 'profile-image':
				return self::block( 'post-featured-image', array( 'isLink' => true ) );

			case 'advanced-image':
				$image = isset( $attrs['slug'] ) ? absint( $attrs['slug'] ) : 0;
				$url   = $image ? wp_get_attachment_image_url( $image, 'large' ) : '';

				if ( ! $url ) {
					return null;
				}

				$alt = (string) get_post_meta( $image, '_wp_attachment_image_alt', true );

				return '<!-- wp:image ' . serialize_block_attributes(
					array(
						'id'       => $image,
						'sizeSlug' => 'large',
					)
				) . " -->\n" . sprintf( '<figure class="wp-block-image size-large"><img src="%s" alt="%s" class="wp-image-%d"/></figure>', esc_url( $url ), esc_attr( $alt ), $image ) . "\n<!-- /wp:image -->";
		}

		return null;
	}

	/**
	 * A self-closing block's markup.
	 *
	 * @param string $name  Block name; core blocks without "core/".
	 * @param array  $attrs Attributes.
	 * @return string
	 */
	private static function block( $name, array $attrs ) {
		return '<!-- wp:' . $name . ( $attrs ? ' ' . serialize_block_attributes( $attrs ) : '' ) . ' /-->';
	}

	/**
	 * Set pages to draft.
	 */
	private function drafts() {
		foreach ( $this->site['drafts'] as $id ) {
			$status = get_post_status( $id );

			if ( ! $status || 'draft' === $status ) {
				$this->report->count( 'Drafts', $status ? 'already done' : 'missing' );
				continue;
			}

			$this->report->count( 'Drafts', $this->dry ? 'would set' : 'set' );
			$this->report->note( 'Drafts', sprintf( '%s (#%d, %s) → draft.', get_the_title( $id ), $id, $status ) );

			if ( ! $this->dry ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
			}
		}
	}

	/**
	 * Point menu items at a content type's archive, keeping their titles.
	 */
	private function menu_archives() {
		foreach ( $this->site['menu_archives'] as $item_id => $post_type ) {
			$item = get_post( $item_id );
			$menu = wp_get_object_terms( $item_id, 'nav_menu', array( 'fields' => 'ids' ) );

			if ( ! $item || 'nav_menu_item' !== $item->post_type || ! $menu || is_wp_error( $menu ) ) {
				$this->report->count( 'Menu items', 'missing' );
				continue;
			}

			if ( 'post_type_archive' === get_post_meta( $item_id, '_menu_item_type', true ) && get_post_meta( $item_id, '_menu_item_object', true ) === $post_type ) {
				$this->report->count( 'Menu items', 'already done' );
				continue;
			}

			$item  = wp_setup_nav_menu_item( $item );
			$title = $item->title;

			$this->report->count( 'Menu items', $this->dry ? 'would repoint' : 'repointed' );
			$this->report->note( 'Menu items', sprintf( '"%s" → the %s archive.', $title, $post_type ) );

			if ( ! $this->dry ) {
				wp_update_nav_menu_item(
					(int) $menu[0],
					$item_id,
					array(
						'menu-item-title'     => $title,
						'menu-item-type'      => 'post_type_archive',
						'menu-item-object'    => $post_type,
						'menu-item-parent-id' => (int) $item->menu_item_parent,
						'menu-item-position'  => (int) $item->menu_order,
						'menu-item-status'    => 'publish',
					)
				);
			}
		}
	}

	/**
	 * Turn the site's classic menu into a block menu (wp_navigation), the
	 * newest one, so the theme's Navigation block, which names no menu, shows it.
	 */
	private function navigation() {
		$menu_id = (int) $this->site['navigation'];
		$menu    = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;

		if ( ! $menu ) {
			return;
		}

		$existing = get_posts(
			array(
				'post_type'   => 'wp_navigation',
				'post_status' => 'publish',
				'meta_key'    => '_galerio_from_menu', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off migration.
				'meta_value'  => $menu_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- One-off migration.
			)
		);

		if ( $existing ) {
			$this->report->count( 'Navigation', 'already done' );
			return;
		}

		$blocks = \WP_Classic_To_Block_Menu_Converter::convert( $menu );

		if ( is_wp_error( $blocks ) || '' === $blocks ) {
			$this->report->note( 'Navigation', sprintf( 'The menu "%s" could not be converted.', $menu->name ) );
			return;
		}

		$this->report->count( 'Navigation', $this->dry ? 'would convert' : 'converted' );
		$this->report->note( 'Navigation', sprintf( 'The classic menu "%s" (%d items) becomes the block menu the header shows.', $menu->name, $menu->count ) );

		if ( ! $this->dry ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'wp_navigation',
					'post_status'  => 'publish',
					'post_title'   => $menu->name,
					'post_content' => $blocks,
				)
			);

			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_galerio_from_menu', $menu_id );
			}
		}
	}

	/**
	 * Set Galerio's names and addresses for content types and taxonomies,
	 * keeping any the site has set already that the decisions don't name.
	 */
	private function aliases() {
		if ( ! $this->site['aliases'] ) {
			return;
		}

		$current = get_option( 'galerio_aliases', array() );
		$current = is_array( $current ) ? $current : array();
		$aliases = array_replace_recursive( $current, $this->site['aliases'] );

		if ( $aliases === $current ) {
			$this->report->count( 'Aliases', 'already done' );
			return;
		}

		$this->report->count( 'Aliases', $this->dry ? 'would set' : 'set' );

		foreach ( $this->site['aliases'] as $kind => $keys ) {
			foreach ( $keys as $key => $properties ) {
				foreach ( $properties as $property => $value ) {
					$this->report->note( 'Aliases', "$kind $key $property: $value." );
				}
			}
		}

		if ( ! $this->dry ) {
			update_option( 'galerio_aliases', $aliases );
		}
	}

	/**
	 * Save a post's content without a revision or Pods' syncing getting involved.
	 *
	 * @param int    $id      Post ID.
	 * @param string $content Content.
	 */
	private function save_content( $id, $content ) {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off migration.
		clean_post_cache( $id );
	}

	/**
	 * Run something as the site's first administrator, for what only they may
	 * save (custom CSS), since WP-CLI runs as nobody unless told otherwise.
	 *
	 * @param callable $callback What to run.
	 */
	private function as_administrator( callable $callback ) {
		$previous = get_current_user_id();

		if ( ! current_user_can( 'edit_css' ) ) {
			$admins = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
					'fields' => 'ID',
				)
			);
			wp_set_current_user( $admins ? (int) $admins[0] : 0 );
		}

		$callback();

		wp_set_current_user( $previous );
	}
}
