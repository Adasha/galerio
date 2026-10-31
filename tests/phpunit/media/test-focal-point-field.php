<?php
/**
 * Focal point field tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Media\Focal_Point;
use Galerio\Core\Media\Focal_Point_Field;

/**
 * The Focal point field in an image's details.
 *
 * @covers \Galerio\Core\Media\Focal_Point_Field
 */
class Test_Focal_Point_Field extends Galerio_TestCase {

	/**
	 * An image attachment.
	 *
	 * @return int
	 */
	private function image() {
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * Log in as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Images get the field, with their point filled in; other files don't.
	 */
	public function test_field_for_images_only() {
		$image = $this->image();
		Focal_Point::save(
			$image,
			array(
				'x' => 0.25,
				'y' => 0.6,
			)
		);

		$fields = Focal_Point_Field::field( array(), get_post( $image ) );
		$this->assertArrayHasKey( 'galerio_focal_point', $fields );
		$this->assertStringContainsString( 'name="attachments[' . $image . '][galerio_focal_x]" value="25"', $fields['galerio_focal_point']['html'] );
		$this->assertStringContainsString( 'left:25%;top:60%', $fields['galerio_focal_point']['html'] );

		$pdf = self::factory()->attachment->create_object(
			array(
				'file'           => 'file.pdf',
				'post_mime_type' => 'application/pdf',
			)
		);
		$this->assertSame( array(), Focal_Point_Field::field( array(), get_post( $pdf ) ) );
	}

	/**
	 * An image without a point leaves the numbers empty.
	 */
	public function test_field_empty_without_point() {
		$image  = $this->image();
		$fields = Focal_Point_Field::field( array(), get_post( $image ) );

		$this->assertStringContainsString( '[galerio_focal_x]" value=""', $fields['galerio_focal_point']['html'] );
	}

	/**
	 * Saving the percentages stores the point; one left empty is the middle;
	 * both empty clears it.
	 */
	public function test_save() {
		$image = $this->image();
		$post  = array( 'ID' => $image );

		Focal_Point_Field::save(
			$post,
			array(
				'galerio_focal_x' => '30',
				'galerio_focal_y' => '',
			)
		);
		$this->assertSame(
			array(
				'x' => 0.3,
				'y' => 0.5,
			),
			Focal_Point::saved( $image )
		);

		Focal_Point_Field::save(
			$post,
			array(
				'galerio_focal_x' => '',
				'galerio_focal_y' => '',
			)
		);
		$this->assertNull( Focal_Point::saved( $image ) );
	}

	/**
	 * A form without the field leaves the point alone.
	 */
	public function test_save_without_field() {
		$image = $this->image();
		Focal_Point::save(
			$image,
			array(
				'x' => 0.1,
				'y' => 0.1,
			)
		);

		Focal_Point_Field::save( array( 'ID' => $image ), array( 'post_title' => 'Title' ) );

		$this->assertNotNull( Focal_Point::saved( $image ) );
	}

	/**
	 * Someone who can't edit the image can't change its point.
	 */
	public function test_save_needs_edit_permission() {
		$image = $this->image();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		Focal_Point_Field::save(
			array( 'ID' => $image ),
			array(
				'galerio_focal_x' => '10',
				'galerio_focal_y' => '10',
			)
		);

		$this->assertNull( Focal_Point::saved( $image ) );
	}
}
