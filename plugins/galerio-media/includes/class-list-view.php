<?php
/**
 * Folders in the Media Library's list view and in a file's details.
 *
 * @package Galerio\Media
 */

namespace Galerio\Media;

use WP_Post;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * The list view's folder filter (the folder tree replaces its dropdown when
 * JavaScript runs) and Folders column, and the folder dropdowns in each file's
 * details, which file it in a shared folder and, when people have their own,
 * in one of theirs.
 */
final class List_View {

	/**
	 * Query parameter of the list view's filter.
	 */
	const PARAM = 'galerio_folder';

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'apply_filter' ) );
		add_filter( 'manage_media_columns', array( __CLASS__, 'column' ) );
		add_action( 'manage_media_custom_column', array( __CLASS__, 'column_value' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( __CLASS__, 'fields' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * A dropdown of the folders a person sees.
	 *
	 * @param string $name     Field name.
	 * @param int    $selected Selected folder.
	 * @param array  $folders  Folders from Folders::tree().
	 * @param string $none     Label of the empty choice.
	 * @return string
	 */
	private static function dropdown( $name, $selected, $folders, $none ) {
		$options = sprintf( '<option value="0">%s</option>', esc_html( $none ) );

		foreach ( $folders as $folder ) {
			$options .= sprintf(
				'<option value="%1$d"%2$s%3$s>%4$s%5$s</option>',
				$folder['id'],
				selected( $selected, $folder['id'], false ),
				disabled( $folder['root'], true, false ),
				str_repeat( '&nbsp;&nbsp;&nbsp;', $folder['depth'] ),
				esc_html( $folder['name'] )
			);
		}

		return sprintf( '<select name="%1$s" id="%2$s">%3$s</select>', esc_attr( $name ), esc_attr( sanitize_html_class( $name ) ), $options );
	}

	/**
	 * The folder filter above the list view.
	 *
	 * @param string $post_type Post type of the list.
	 */
	public static function filter( $post_type ) {
		if ( 'attachment' !== $post_type ) {
			return;
		}

		$folders = Folders::tree( get_current_user_id() );
		if ( ! $folders ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A list filter.
		$selected = isset( $_GET[ self::PARAM ] ) ? absint( $_GET[ self::PARAM ] ) : 0;

		printf( '<label class="screen-reader-text" for="%1$s">%2$s</label>', esc_attr( self::PARAM ), esc_html__( 'Filter by folder', 'galerio-media' ) );
		echo self::dropdown( self::PARAM, $selected, $folders, __( 'All folders', 'galerio-media' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in dropdown().
	}

	/**
	 * Apply the folder filter.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function apply_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- A list filter; parse_choice() sanitises.
		$tax_query = Folders::tax_query( Folders::parse_choice( isset( $_GET[ self::PARAM ] ) ? wp_unslash( $_GET[ self::PARAM ] ) : '' ) );

		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Filtering by folder is the point.
		}
	}

	/**
	 * Add a Folders column.
	 *
	 * @param string[] $columns Columns.
	 * @return string[]
	 */
	public static function column( $columns ) {
		$columns['galerio_folders'] = __( 'Folders', 'galerio-media' );
		return $columns;
	}

	/**
	 * The folders a file is in, among those the person sees.
	 *
	 * @param string $column        Column.
	 * @param int    $attachment_id File.
	 */
	public static function column_value( $column, $attachment_id ) {
		if ( 'galerio_folders' !== $column ) {
			return;
		}

		$names = array();
		foreach ( wp_get_object_terms( $attachment_id, Folders::TAXONOMY ) as $term ) {
			if ( current_user_can( 'assign_term', $term->term_id ) ) {
				$names[] = esc_html( $term->name );
			}
		}

		echo $names ? implode( ', ', $names ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
	}

	/**
	 * Folder dropdowns in a file's details: a shared folder, and one of the
	 * person's own when they have them.
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   File.
	 * @return array
	 */
	public static function fields( $fields, $post ) {
		unset( $fields[ Folders::TAXONOMY ] );

		$user    = get_current_user_id();
		$folders = Folders::tree( $user );
		$current = wp_get_object_terms( $post->ID, Folders::TAXONOMY, array( 'fields' => 'ids' ) );
		$current = is_wp_error( $current ) ? array() : $current;
		$spaces  = array( 0 => __( 'Folder', 'galerio-media' ) );

		if ( Settings::personal_folders() && $user ) {
			$spaces[ $user ] = __( 'My folder', 'galerio-media' );
		}

		foreach ( $spaces as $owner => $label ) {
			$own = array_values(
				array_filter(
					$folders,
					function ( $folder ) use ( $owner ) {
						return $owner === $folder['owner'];
					}
				)
			);
			$in  = array_values(
				array_filter(
					$current,
					function ( $id ) use ( $owner ) {
						return Folders::owner( $id ) === $owner;
					}
				)
			);

			if ( ! $own ) {
				continue;
			}

			$fields[ 'galerio_folder_' . $owner ] = array(
				'label' => $label,
				'input' => 'html',
				'html'  => self::dropdown( "attachments[{$post->ID}][galerio_folder_{$owner}]", $in ? (int) $in[0] : 0, $own, __( 'None', 'galerio-media' ) ),
			);
		}

		return $fields;
	}

	/**
	 * Save the folder dropdowns.
	 *
	 * @param array $post       Attachment data.
	 * @param array $attachment Submitted fields.
	 * @return array
	 */
	public static function save( $post, $attachment ) {
		foreach ( $attachment as $key => $value ) {
			if ( ! preg_match( '/^galerio_folder_(\d+)$/', $key, $match ) ) {
				continue;
			}

			$owner  = (int) $match[1];
			$folder = absint( $value );

			if ( $owner && get_current_user_id() !== $owner ) {
				continue;
			}

			if ( ! $folder || ( current_user_can( 'assign_term', $folder ) && Folders::owner( $folder ) === $owner ) ) {
				Folders::file( (int) $post['ID'], $folder, $owner );
			}
		}

		return $post;
	}
}
