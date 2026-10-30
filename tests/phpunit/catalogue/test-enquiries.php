<?php
/**
 * Enquiry tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Catalogue\Enquiries;

/**
 * The enquiry form on works for sale, sent by email.
 *
 * @covers \Galerio\Core\Catalogue\Enquiries
 */
class Test_Enquiries extends Galerio_TestCase {

	/**
	 * Turn the catalogue on and catch mail.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'galerio_settings_catalogue', '1' );
		reset_phpmailer_instance();
	}

	/**
	 * Leave the settings as they were.
	 */
	public function tear_down() {
		delete_option( 'galerio_settings_catalogue' );
		delete_option( 'galerio_settings_enquiry_email' );
		reset_phpmailer_instance();
		parent::tear_down();
	}

	/**
	 * A published work with catalogue values.
	 *
	 * @param array $values Field name => value.
	 * @return int Work ID.
	 */
	private function work( array $values ) {
		$work = self::factory()->post->create(
			array(
				'post_type'   => 'galerio_work',
				'post_status' => 'publish',
				'post_title'  => 'Spring Tide',
			)
		);

		foreach ( $values as $name => $value ) {
			galerio_fields()->save( galerio_fields()->get( "work.$name" ), $work, $value );
		}

		return $work;
	}

	/**
	 * The block as rendered for a work.
	 *
	 * @param int $work Work ID.
	 * @return string
	 */
	private function render( $work ) {
		$block = new WP_Block(
			array(
				'blockName' => 'galerio/enquiry',
				'attrs'     => array(),
			),
			array( 'postId' => $work )
		);

		return $block->render();
	}

	/**
	 * A signed time the form was shown at.
	 *
	 * @param int $work    Work ID.
	 * @param int $seconds How long ago.
	 * @return string
	 */
	private function shown( $work, $seconds ) {
		$sign = new ReflectionMethod( Enquiries::class, 'sign' );
		$sign->setAccessible( true );
		$time = time() - $seconds;

		return $time . ':' . $sign->invoke( null, $time, $work );
	}

	/**
	 * A filled-in form.
	 *
	 * @param int   $work  Work ID.
	 * @param array $extra Fields that differ.
	 * @return array
	 */
	private function fields( $work, array $extra = array() ) {
		return array_merge(
			array(
				'name'    => 'Sam Jones',
				'email'   => 'sam@example.com',
				'message' => 'Is it still available?',
				'website' => '',
				'shown'   => $this->shown( $work, 30 ),
			),
			$extra
		);
	}

	/**
	 * The form shows only for works for sale with no dealer, while the
	 * catalogue is on.
	 */
	public function test_shown_for_works_for_sale() {
		$available = $this->work( array( 'availability' => 'available' ) );
		$html      = $this->render( $available );

		$this->assertStringContainsString( '<form', $html );
		$this->assertStringContainsString( 'Enquire about this work', $html );
		$this->assertStringContainsString( 'name="website"', $html );

		$this->assertSame( '', $this->render( $this->work( array( 'availability' => 'sold' ) ) ) );
		$this->assertSame( '', $this->render( $this->work( array() ) ) );
		$this->assertSame(
			'',
			$this->render(
				$this->work(
					array(
						'availability'   => 'available',
						'available_from' => array( 'url' => 'https://shop.example' ),
					)
				)
			),
			'A work sold through a dealer links there instead.'
		);

		update_option( 'galerio_settings_catalogue', '0' );
		$this->assertSame( '', $this->render( $available ) );
	}

	/**
	 * After sending, the visitor sees a thank-you or what went wrong.
	 */
	public function test_result_messages() {
		$work = $this->work( array( 'availability' => 'available' ) );

		$_GET['galerio_enquiry'] = 'sent';
		$html                    = $this->render( $work );
		$this->assertStringContainsString( 'Your enquiry has been sent', $html );
		$this->assertStringNotContainsString( '<form', $html );

		$_GET['galerio_enquiry'] = 'invalid';
		$html                    = $this->render( $work );
		$this->assertStringContainsString( 'role="alert"', $html );
		$this->assertStringContainsString( '<details open', $html );

		unset( $_GET['galerio_enquiry'] );
	}

	/**
	 * An enquiry is emailed with the work's details, to answer by replying.
	 */
	public function test_sends_email() {
		update_option( 'galerio_settings_enquiry_email', 'sales@gallery.example' );
		$work = $this->work(
			array(
				'availability'     => 'available',
				'price'            => 1250,
				'inventory_number' => 'GD-0225',
			)
		);

		$this->assertSame( 'sent', Enquiries::process( $work, $this->fields( $work ) ) );

		$mail = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'sales@gallery.example', $mail->to[0][0] );
		$this->assertSame( 'Enquiry: Spring Tide (GD-0225)', $mail->subject );
		$this->assertStringContainsString( 'Reply-To: Sam Jones <sam@example.com>', $mail->header );
		$this->assertStringContainsString( 'Price: £1,250', $mail->body );
		$this->assertStringContainsString( 'Is it still available?', $mail->body );
		$this->assertStringContainsString( get_permalink( $work ), $mail->body );
	}

	/**
	 * Without an address of its own, enquiries go to the site's admin.
	 */
	public function test_recipient_fallback() {
		$this->assertSame( get_option( 'admin_email' ), Enquiries::recipient() );

		update_option( 'galerio_settings_enquiry_email', 'not an address' );
		$this->assertSame( get_option( 'admin_email' ), Enquiries::recipient() );
	}

	/**
	 * Spam looks sent but isn't: the hidden field filled in, sent too soon,
	 * or a time that wasn't signed for this work.
	 */
	public function test_spam_is_dropped() {
		$work  = $this->work( array( 'availability' => 'available' ) );
		$other = $this->work( array( 'availability' => 'available' ) );

		foreach (
			array(
				'hidden field' => array( 'website' => 'https://spam.example' ),
				'too soon'     => array( 'shown' => $this->shown( $work, 1 ) ),
				'unsigned'     => array( 'shown' => ( time() - 30 ) . ':abc' ),
				'missing time' => array( 'shown' => '' ),
				'other work'   => array( 'shown' => $this->shown( $other, 30 ) ),
			) as $case => $extra
		) {
			reset_phpmailer_instance();
			$this->assertSame( 'sent', Enquiries::process( $work, $this->fields( $work, $extra ) ), $case );
			$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent(), "$case was emailed." );
		}
	}

	/**
	 * Missing or wrong details are sent back.
	 */
	public function test_invalid() {
		$work = $this->work( array( 'availability' => 'available' ) );

		$this->assertSame( 'invalid', Enquiries::process( $work, $this->fields( $work, array( 'email' => 'not-an-email' ) ) ) );
		$this->assertSame( 'invalid', Enquiries::process( $work, $this->fields( $work, array( 'message' => '  ' ) ) ) );
		$this->assertSame( 'invalid', Enquiries::process( $work, $this->fields( $work, array( 'name' => '' ) ) ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}
}
