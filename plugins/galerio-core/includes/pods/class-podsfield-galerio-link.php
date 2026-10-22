<?php
/**
 * Pods field type for links that carry their own display text.
 *
 * @package Galerio\Core
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, PEAR.NamingConventions.ValidClassName.Invalid -- Pods loads field types by the class name PodsField_<Type>.

/**
 * A link, or a list of links, each stored as {url, label, offline}.
 *
 * Pods' own Link type is unfinished (it is left out of Pods' field type list,
 * and repeatable links are stored as nested arrays), so Galerio provides this
 * one. Values are stored as JSON in a single meta value, in the same shape a
 * native register_post_meta() array or object schema would use.
 */
class PodsField_Galerio_link extends PodsField {

	/**
	 * Field type group in the Pods admin.
	 *
	 * @var string
	 */
	public static $group = 'Text';

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public static $type = 'galerio_link';

	/**
	 * Field type label.
	 *
	 * @var string
	 */
	public static $label = 'Link';

	/**
	 * Placeholder for database queries.
	 *
	 * @var string
	 */
	public static $prepare = '%s';

	/**
	 * Translate the labels.
	 */
	public function setup() {
		static::$group = __( 'Text', 'galerio-core' );
		static::$label = __( 'Link (Galerio)', 'galerio-core' );
	}

	/**
	 * Field options in the Pods admin.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			static::$type . '_multiple' => array(
				'label'   => __( 'Allow several links', 'galerio-core' ),
				'default' => 0,
				'type'    => 'boolean',
			),
			static::$type . '_offline'  => array(
				'label'   => __( 'Ask whether the site is offline', 'galerio-core' ),
				'default' => 0,
				'type'    => 'boolean',
			),
		);
	}

	/**
	 * Column type for table storage.
	 *
	 * @param array|null $options Field options.
	 * @return string
	 */
	public function schema( $options = null ) {
		return 'LONGTEXT';
	}

	/**
	 * Decode a stored value into a list of links.
	 *
	 * @param mixed $value Stored JSON, a link, or a list of links.
	 * @return array[] Links, each with url, label and offline.
	 */
	public static function to_list( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : ( '' === $value ? array() : array( 'url' => $value ) );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		// A single link rather than a list.
		if ( isset( $value['url'] ) || isset( $value['label'] ) ) {
			$value = array( $value );
		}

		$links = array();

		foreach ( $value as $link ) {
			if ( ! is_array( $link ) || empty( $link['url'] ) ) {
				continue;
			}

			$url = esc_url_raw( trim( (string) $link['url'] ) );

			if ( '' === $url ) {
				continue;
			}

			$links[] = array(
				'url'     => $url,
				'label'   => isset( $link['label'] ) ? sanitize_text_field( (string) $link['label'] ) : '',
				'offline' => ! empty( $link['offline'] ),
			);
		}

		return $links;
	}

	/**
	 * Sanitise a submitted value and encode it for storage.
	 *
	 * @param mixed      $value   Submitted value.
	 * @param int|null   $id      Item ID.
	 * @param string     $name    Field name.
	 * @param array|null $options Field options.
	 * @param array|null $fields  Pod fields.
	 * @param mixed      $pod     Pod.
	 * @param array|null $params  Save parameters.
	 * @return string JSON, or an empty string when there are no links.
	 */
	public function pre_save( $value, $id = null, $name = null, $options = null, $fields = null, $pod = null, $params = null ) {
		$links = self::to_list( $value );

		if ( ! $links ) {
			return '';
		}

		if ( ! $this->is_multiple( $options ) ) {
			$links = $links[0];
		}

		return wp_json_encode( $links );
	}

	/**
	 * Render the value as HTML links.
	 *
	 * @param mixed      $value   Stored value.
	 * @param string     $name    Field name.
	 * @param array|null $options Field options.
	 * @param mixed      $pod     Pod.
	 * @param int|null   $id      Item ID.
	 * @return string
	 */
	public function display( $value = null, $name = null, $options = null, $pod = null, $id = null ) {
		$links = self::to_list( $value );

		if ( ! $links ) {
			return '';
		}

		$items = array_map(
			function ( $link ) {
				// The same words the field API shows: display text, else the service's name, else the address.
				$text = \Galerio\Core\Fields\Display::link_text( $link );

				if ( $link['offline'] ) {
					return esc_html( $text );
				}

				return sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link['url'] ), esc_html( $text ) );
			},
			$links
		);

		return 1 === count( $items ) ? $items[0] : '<ul><li>' . implode( '</li><li>', $items ) . '</li></ul>';
	}

	/**
	 * Render the editing form: one row per link, with a button to add more.
	 *
	 * @param string     $name    Input name.
	 * @param mixed      $value   Stored value.
	 * @param array|null $options Field options.
	 * @param mixed      $pod     Pod.
	 * @param int|null   $id      Item ID.
	 */
	public function input( $name, $value = null, $options = null, $pod = null, $id = null ) {
		// Pods meta boxes pass the bare field name and the "pods_meta_" prefix separately.
		$prefix = (string) pods_v( 'name_prefix', $options, '' );

		if ( '' !== $prefix && 0 !== strpos( $name, $prefix ) ) {
			$name = $prefix . $name;
		}

		$multiple = $this->is_multiple( $options );
		$offline  = 1 === (int) pods_v( static::$type . '_offline', $options, 0 );
		$links    = self::to_list( $value );

		if ( ! $links || $multiple ) {
			$links[] = array(
				'url'     => '',
				'label'   => '',
				'offline' => false,
			);
		}

		if ( ! $multiple ) {
			$links = array( $links[0] );
		}

		$labels = array(
			'url'     => __( 'URL', 'galerio-core' ),
			'label'   => __( 'Display text (optional)', 'galerio-core' ),
			'offline' => __( 'Site is offline', 'galerio-core' ),
			'remove'  => __( 'Remove', 'galerio-core' ),
			'add'     => __( 'Add link', 'galerio-core' ),
		);

		printf( '<div class="galerio-links" data-name="%s">', esc_attr( $name ) );

		foreach ( array_values( $links ) as $index => $link ) {
			$this->render_row( $name, $index, $link, $offline, $multiple, $labels );
		}

		if ( $multiple ) {
			printf( '<p><button type="button" class="button galerio-links__add">%s</button></p>', esc_html( $labels['add'] ) );
			echo '<template class="galerio-links__template">';
			$this->render_row(
				$name,
				'__index__',
				array(
					'url'     => '',
					'label'   => '',
					'offline' => false,
				),
				$offline,
				true,
				$labels
			);
			echo '</template>';
		}

		echo '</div>';

		if ( $multiple ) {
			$this->print_script();
		}
	}

	/**
	 * Render one link's inputs.
	 *
	 * @param string     $name     Input name.
	 * @param int|string $index    Row index.
	 * @param array      $link     Link.
	 * @param bool       $offline  Whether to show the offline checkbox.
	 * @param bool       $multiple Whether rows can be removed.
	 * @param string[]   $labels   Translated labels.
	 */
	private function render_row( $name, $index, array $link, $offline, $multiple, array $labels ) {
		$base = $multiple ? sprintf( '%s[%s]', $name, $index ) : $name;

		echo '<p class="galerio-links__row" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">';
		printf(
			'<input type="url" name="%1$s[url]" value="%2$s" placeholder="https://" aria-label="%3$s" class="regular-text" />',
			esc_attr( $base ),
			esc_attr( $link['url'] ),
			esc_attr( $labels['url'] )
		);
		printf(
			'<input type="text" name="%1$s[label]" value="%2$s" placeholder="%3$s" aria-label="%3$s" />',
			esc_attr( $base ),
			esc_attr( $link['label'] ),
			esc_attr( $labels['label'] )
		);

		if ( $offline ) {
			printf(
				'<label><input type="checkbox" name="%1$s[offline]" value="1" %2$s /> %3$s</label>',
				esc_attr( $base ),
				checked( $link['offline'], true, false ),
				esc_html( $labels['offline'] )
			);
		}

		if ( $multiple ) {
			printf( '<button type="button" class="button-link galerio-links__remove">%s</button>', esc_html( $labels['remove'] ) );
		}

		echo '</p>';
	}

	/**
	 * Print the script that adds and removes rows, once per page.
	 */
	private function print_script() {
		static $printed = false;

		if ( $printed ) {
			return;
		}

		$printed = true;
		?>
		<script>
		document.addEventListener( 'click', function ( event ) {
			var list = event.target.closest( '.galerio-links' );
			if ( ! list ) {
				return;
			}
			if ( event.target.classList.contains( 'galerio-links__remove' ) ) {
				event.target.closest( '.galerio-links__row' ).remove();
			} else if ( event.target.classList.contains( 'galerio-links__add' ) ) {
				var template = list.querySelector( '.galerio-links__template' );
				var html = template.innerHTML.split( '__index__' ).join( String( Date.now() ) );
				template.insertAdjacentHTML( 'beforebegin', html );
				event.target.closest( 'p' ).before( template.previousElementSibling );
			}
		} );
		</script>
		<?php
	}

	/**
	 * Whether the field holds several links.
	 *
	 * @param array|null $options Field options.
	 * @return bool
	 */
	private function is_multiple( $options ) {
		return 1 === (int) pods_v( static::$type . '_multiple', $options, 0 );
	}
}
