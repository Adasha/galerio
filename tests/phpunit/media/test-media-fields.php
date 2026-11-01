<?php
/**
 * File details field tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Media\Media_Fields;

/**
 * A file's fields show in its details and save from them.
 *
 * @covers \Galerio\Core\Media\Media_Fields
 */
class Test_Media_Fields extends Galerio_TestCase {

	/**
	 * A file.
	 *
	 * @return int
	 */
	private function file() {
		return self::factory()->attachment->create_object(
			array(
				'file'           => 'image.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	/**
	 * The fields are offered with their values, and save.
	 */
	public function test_fields_show_and_save() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$id = $this->file();

		Media_Fields::save(
			array( 'ID' => $id ),
			array(
				'galerio_field_credit' => 'Photo: Sian',
				'galerio_field_year'   => '1999',
			)
		);

		$fields = Media_Fields::fields( array(), get_post( $id ) );

		$this->assertSame( 'Photo: Sian', $fields['galerio_field_credit']['value'] );
		$this->assertSame( '1999', $fields['galerio_field_year']['value'] );
		$this->assertArrayHasKey( 'galerio_field_copyright', $fields );
	}

	/**
	 * Fields not sent are left alone, and people who can't edit the file change nothing.
	 */
	public function test_save_limits() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$id = $this->file();
		Media_Fields::save( array( 'ID' => $id ), array( 'galerio_field_credit' => 'Kept' ) );
		Media_Fields::save( array( 'ID' => $id ), array( 'galerio_field_copyright' => 'Added' ) );

		$this->assertSame( 'Kept', galerio_get_field( 'attachment.credit', $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		Media_Fields::save( array( 'ID' => $id ), array( 'galerio_field_credit' => 'Changed' ) );

		$this->assertSame( 'Kept', galerio_get_field( 'attachment.credit', $id ) );
		$this->assertSame( array(), Media_Fields::fields( array(), get_post( $id ) ) );
	}
}
