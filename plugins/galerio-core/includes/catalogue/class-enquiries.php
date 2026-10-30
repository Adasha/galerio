<?php
/**
 * Enquiries about works for sale.
 *
 * @package Galerio\Core
 */

namespace Galerio\Core\Catalogue;

use Galerio\Core\Aliases\Names;
use Galerio\Core\Settings\Features;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * The `galerio/enquiry` block: a form on a work's page for asking about it,
 * sent by email.
 *
 * The form shows while the catalogue is on, the work is available or
 * reserved, and no dealer sells it (a work with Available from links there
 * instead). It goes to the Enquiries email on Settings → Galerio, else the
 * site's administration email, with the visitor's address to reply to.
 * Nothing is kept on the site.
 *
 * Spam is turned away by a hidden field that people leave empty and a
 * minimum time between the form being shown and sent, signed so it can't be
 * changed. Turned-away enquiries look sent, so nothing tells a robot to try
 * again. The form has no nonce, so pages cached for every visitor still work.
 */
final class Enquiries {

	/**
	 * The admin-post action the form sends to.
	 */
	const ACTION = 'galerio_enquiry';

	/**
	 * The query argument the visitor comes back with: sent or invalid.
	 */
	const RESULT = 'galerio_enquiry';

	/**
	 * The form's anchor on the page.
	 */
	const ANCHOR = 'galerio-enquiry';

	/**
	 * Fewest seconds between showing the form and sending it.
	 */
	const MIN_SECONDS = 3;

	/**
	 * Longest name and message, in characters.
	 */
	const MAX_NAME    = 200;
	const MAX_MESSAGE = 5000;

	/**
	 * Hook into WordPress.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Register the block from its metadata.
	 */
	public static function register() {
		register_block_type(
			GALERIO_CORE_DIR . 'blocks/enquiry',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Where enquiries go.
	 *
	 * @return string Email address.
	 */
	public static function recipient() {
		$email = sanitize_email( (string) get_option( Features::PREFIX . 'enquiry_email', '' ) );

		/**
		 * Filters where enquiries about works are sent.
		 *
		 * @param string $email Email address.
		 */
		return (string) apply_filters( 'galerio_enquiry_email', is_email( $email ) ? $email : get_option( 'admin_email' ) );
	}

	/**
	 * Whether a work takes enquiries: the catalogue is on, it's for sale,
	 * and no dealer sells it.
	 *
	 * @param int $post_id Work.
	 * @return bool
	 */
	public static function open( $post_id ) {
		return 'galerio_work' === get_post_type( $post_id )
			&& 'publish' === get_post_status( $post_id )
			&& Catalogue::for_sale( $post_id )
			&& ! galerio_get_field( 'work.available_from', $post_id );
	}

	/**
	 * Render the block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner content (unused).
	 * @param WP_Block $block      Block instance.
	 * @return string Empty when the work doesn't take enquiries.
	 */
	public static function render( $attributes, $content, $block ) {
		$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();

		if ( ! $post_id || ! self::open( $post_id ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only picks which message to show.
		$result  = isset( $_GET[ self::RESULT ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT ] ) ) : '';
		$summary = '' !== trim( (string) $attributes['buttonText'] )
			? Names::expand( $attributes['buttonText'] )
			/* translators: Keep {work} as it is: it becomes this site's name for a work, in lower case. */
			: Names::expand( __( 'Enquire about this {work}', 'galerio-core' ) );

		self::styles();

		if ( 'sent' === $result ) {
			$inner = sprintf(
				'<p class="wp-block-galerio-enquiry__message is-sent" role="status">%s</p>',
				esc_html__( 'Thank you. Your enquiry has been sent, and we’ll reply by email.', 'galerio-core' )
			);
		} else {
			$error = 'invalid' === $result
				? sprintf(
					'<p class="wp-block-galerio-enquiry__message is-error" role="alert">%s</p>',
					esc_html__( 'Your enquiry wasn’t sent. Please give your name, a valid email address and a message, then try again.', 'galerio-core' )
				)
				: '';

			$inner = sprintf(
				'<details%1$s><summary class="wp-block-galerio-enquiry__summary wp-element-button">%2$s</summary>%3$s%4$s</details>',
				$error ? ' open' : '',
				esc_html( $summary ),
				$error,
				self::form( $post_id )
			);
		}

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( array( 'id' => self::ANCHOR ) ),
			$inner
		);
	}

	/**
	 * The form.
	 *
	 * @param int $post_id Work.
	 * @return string
	 */
	private static function form( $post_id ) {
		$time = time();
		$id   = function ( $name ) {
			return esc_attr( self::ANCHOR . '-' . $name );
		};

		ob_start();
		?>
<form class="wp-block-galerio-enquiry__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
	<input type="hidden" name="work" value="<?php echo esc_attr( (string) $post_id ); ?>">
	<input type="hidden" name="shown" value="<?php echo esc_attr( $time . ':' . self::sign( $time, $post_id ) ); ?>">
	<p class="wp-block-galerio-enquiry__field">
		<label for="<?php echo $id( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"><?php esc_html_e( 'Your name', 'galerio-core' ); ?></label>
		<input type="text" id="<?php echo $id( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" name="name" required maxlength="<?php echo esc_attr( (string) self::MAX_NAME ); ?>" autocomplete="name">
	</p>
	<p class="wp-block-galerio-enquiry__field">
		<label for="<?php echo $id( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"><?php esc_html_e( 'Your email address', 'galerio-core' ); ?></label>
		<input type="email" id="<?php echo $id( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" name="email" required autocomplete="email">
	</p>
	<p class="wp-block-galerio-enquiry__field">
		<label for="<?php echo $id( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"><?php esc_html_e( 'Message', 'galerio-core' ); ?></label>
		<textarea id="<?php echo $id( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" name="message" rows="5" required maxlength="<?php echo esc_attr( (string) self::MAX_MESSAGE ); ?>"></textarea>
	</p>
	<p class="wp-block-galerio-enquiry__trap" aria-hidden="true">
		<label for="<?php echo $id( 'website' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>"><?php esc_html_e( 'Leave this empty', 'galerio-core' ); ?></label>
		<input type="text" id="<?php echo $id( 'website' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>" name="website" tabindex="-1" autocomplete="off">
	</p>
	<p class="wp-block-galerio-enquiry__note"><?php esc_html_e( 'Your details are sent by email only, so we can reply. They aren’t kept on this site.', 'galerio-core' ); ?></p>
	<p class="wp-block-galerio-enquiry__actions">
		<button type="submit" class="wp-element-button"><?php esc_html_e( 'Send enquiry', 'galerio-core' ); ?></button>
	</p>
</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Sign the time a form was shown, for one work.
	 *
	 * @param int $time    Unix time.
	 * @param int $post_id Work.
	 * @return string
	 */
	private static function sign( $time, $post_id ) {
		return hash_hmac( 'sha256', $time . '|' . $post_id, wp_salt( 'nonce' ) );
	}

	/**
	 * Handle a sent form and go back to the work.
	 */
	public static function handle() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- No nonce, so cached pages work: see the class.
		$post_id = isset( $_POST['work'] ) ? absint( $_POST['work'] ) : 0;

		if ( ! $post_id || ! self::open( $post_id ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$fields = array(
			'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'message' => isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '',
			'website' => isset( $_POST['website'] ) ? sanitize_text_field( wp_unslash( $_POST['website'] ) ) : '',
			'shown'   => isset( $_POST['shown'] ) ? sanitize_text_field( wp_unslash( $_POST['shown'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = self::process( $post_id, $fields );

		wp_safe_redirect( add_query_arg( self::RESULT, $result, get_permalink( $post_id ) ) . '#' . self::ANCHOR );
		exit;
	}

	/**
	 * Check an enquiry and send it.
	 *
	 * @param int   $post_id Work.
	 * @param array $fields  Sanitised name, email, message, website (the
	 *                       hidden field) and shown (the signed time).
	 * @return string "sent", or "invalid" when something is missing or
	 *                wrong. Spam and a failed email also give "sent", so
	 *                robots learn nothing; a failed email is logged.
	 */
	public static function process( $post_id, array $fields ) {
		if ( self::is_spam( $post_id, $fields ) ) {
			return 'sent';
		}

		$name    = mb_substr( trim( $fields['name'] ), 0, self::MAX_NAME );
		$email   = is_email( $fields['email'] ) ? $fields['email'] : '';
		$message = mb_substr( trim( $fields['message'] ), 0, self::MAX_MESSAGE );

		if ( '' === $name || '' === $email || '' === $message ) {
			return 'invalid';
		}

		if ( ! wp_mail( self::recipient(), self::subject( $post_id ), self::body( $post_id, $name, $email, $message ), array( sprintf( 'Reply-To: %s <%s>', str_replace( array( '"', '<', '>' ), '', $name ), $email ) ) ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- The site owner needs to know an enquiry was lost.
			error_log( sprintf( 'Galerio: an enquiry about work %d could not be emailed to %s.', $post_id, self::recipient() ) );
		}

		return 'sent';
	}

	/**
	 * Whether a sent form looks like spam: the hidden field is filled in, or
	 * the form came back too soon or without a valid signed time.
	 *
	 * @param int   $post_id Work.
	 * @param array $fields  Sanitised fields.
	 * @return bool
	 */
	private static function is_spam( $post_id, array $fields ) {
		if ( '' !== $fields['website'] ) {
			return true;
		}

		$parts = explode( ':', $fields['shown'], 2 );
		$time  = (int) $parts[0];

		if ( 2 !== count( $parts ) || ! hash_equals( self::sign( $time, $post_id ), $parts[1] ) ) {
			return true;
		}

		return time() - $time < self::MIN_SECONDS;
	}

	/**
	 * The email's subject.
	 *
	 * @param int $post_id Work.
	 * @return string
	 */
	private static function subject( $post_id ) {
		$reference = galerio_get_field( 'work.inventory_number', $post_id );
		$title     = wp_strip_all_tags( get_the_title( $post_id ) );

		return '' !== $reference
			/* translators: 1: a work's title. 2: its inventory number. */
			? sprintf( __( 'Enquiry: %1$s (%2$s)', 'galerio-core' ), $title, $reference )
			/* translators: %s: a work's title. */
			: sprintf( __( 'Enquiry: %s', 'galerio-core' ), $title );
	}

	/**
	 * The email's text.
	 *
	 * @param int    $post_id Work.
	 * @param string $name    Visitor's name.
	 * @param string $email   Visitor's email address.
	 * @param string $message Their message.
	 * @return string Plain text.
	 */
	private static function body( $post_id, $name, $email, $message ) {
		$lines = array(
			/* translators: %s: the site's name. */
			sprintf( __( 'An enquiry from %s:', 'galerio-core' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			'',
			/* translators: %s: a work's title. */
			sprintf( __( 'Work: %s', 'galerio-core' ), wp_strip_all_tags( get_the_title( $post_id ) ) ),
			get_permalink( $post_id ),
		);

		$reference = galerio_get_field( 'work.inventory_number', $post_id );
		if ( '' !== $reference ) {
			/* translators: %s: a work's inventory number. */
			$lines[] = sprintf( __( 'Reference: %s', 'galerio-core' ), $reference );
		}

		$price = galerio_get_field( 'work.price_label', $post_id );
		if ( '' !== $price ) {
			/* translators: %s: a work's price as shown, e.g. "£1,250". */
			$lines[] = sprintf( __( 'Price: %s', 'galerio-core' ), $price );
		}

		$lines[] = '';
		/* translators: 1: a visitor's name. 2: their email address. */
		$lines[] = sprintf( __( 'From: %1$s <%2$s>', 'galerio-core' ), $name, $email );
		$lines[] = '';
		$lines[] = $message;
		$lines[] = '';
		$lines[] = __( 'Reply to this email to answer them. Nothing about this enquiry is kept on the site.', 'galerio-core' );

		return implode( "\n", $lines );
	}

	/**
	 * The form's styles: fields one above another, the hidden field out of
	 * sight. Buttons take the theme's button style.
	 */
	private static function styles() {
		if ( wp_style_is( 'galerio-enquiry' ) ) {
			return;
		}

		wp_register_style( 'galerio-enquiry', false, array(), GALERIO_CORE_VERSION );
		wp_enqueue_style( 'galerio-enquiry' );
		wp_add_inline_style(
			'galerio-enquiry',
			'.wp-block-galerio-enquiry summary{display:inline-block;cursor:pointer;list-style:none;}
			.wp-block-galerio-enquiry summary::-webkit-details-marker{display:none;}
			.wp-block-galerio-enquiry__form{margin-top:1em;}
			.wp-block-galerio-enquiry__field label{display:block;margin-bottom:0.25em;font-weight:600;}
			.wp-block-galerio-enquiry__field :is(input,textarea){box-sizing:border-box;width:100%;max-width:100%;padding:0.5em;font:inherit;}
			.wp-block-galerio-enquiry__note{font-size:0.875em;}
			.wp-block-galerio-enquiry__trap{position:absolute !important;left:-9999px;width:1px;height:1px;overflow:hidden;}'
		);
	}
}
