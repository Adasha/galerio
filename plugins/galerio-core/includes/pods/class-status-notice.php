<?php
/**
 * Admin notice when Galerio's content can't be read.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Pods;

use Galerio\Core\Types\Content_Types;

defined( 'ABSPATH' ) || exit;

/**
 * Tells administrators when Pods is missing, or when Pods is active but
 * Galerio's content types haven't been set up.
 *
 * Without either, Galerio Core reads every field as empty rather than
 * failing, so the site would otherwise just look bare with no explanation.
 */
final class Status_Notice {

	/**
	 * Content types the Galerio package registers.
	 */
	const TYPES = array( 'galerio_work', 'galerio_profile', 'galerio_collection', 'galerio_article' );

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'show' ) );
	}

	/**
	 * What's wrong, if anything.
	 *
	 * @return string '' when all is well, 'pods' when Pods isn't active,
	 *                'types' when the content types are missing.
	 */
	public static function problem() {
		if ( Content_Types::active() ) {
			return '';
		}

		if ( ! function_exists( 'pods' ) ) {
			return 'pods';
		}

		foreach ( self::TYPES as $type ) {
			if ( ! post_type_exists( $type ) ) {
				return 'types';
			}
		}

		return '';
	}

	/**
	 * Show the notice to people who can fix it.
	 */
	public static function show() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$problem = self::problem();

		if ( 'pods' === $problem ) {
			$message = sprintf(
				/* translators: %s: link to the Plugins screen. */
				__( 'Galerio Core needs the Pods plugin to read works, profiles and collections. Install and activate Pods on the %s screen.', 'galerio-core' ),
				sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'plugins.php' ) ), esc_html__( 'Plugins', 'galerio-core' ) )
			);
		} elseif ( 'types' === $problem ) {
			$message = sprintf(
				/* translators: %s: the WP-CLI command. */
				__( 'Galerio’s content types aren’t set up yet. Import the Galerio Pods package with %s.', 'galerio-core' ),
				'<code>wp galerio pods import &lt;file&gt;</code>'
			);
		} else {
			return;
		}

		wp_admin_notice(
			$message,
			array(
				'type'           => 'warning',
				'paragraph_wrap' => true,
			)
		);
	}
}
