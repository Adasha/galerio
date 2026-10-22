<?php
/**
 * Pods adapter tests.
 *
 * @package Galerio\Tests
 */

use Galerio\Core\Fields\Pods_Adapter;

/**
 * Runs the adapter contract against Pods.
 *
 * @covers \Galerio\Core\Fields\Pods_Adapter
 */
class Test_Pods_Adapter extends Galerio_Adapter_Contract_TestCase {

	/**
	 * Create the adapter under test.
	 *
	 * @return Pods_Adapter
	 */
	protected function make_adapter() {
		return new Pods_Adapter();
	}

	/**
	 * Values saved through Pods' own API read back through the adapter.
	 */
	public function test_reads_values_saved_by_pods() {
		$id      = $this->make_object( 'work' );
		$profile = $this->make_object( 'profile' );

		pods( 'galerio_work', $id )->save(
			array(
				'medium'  => 'Oil on canvas',
				'year'    => '2018-01-01',
				'creator' => $profile,
			)
		);

		$this->assertSame( 'Oil on canvas', $this->adapter->get( $this->field( 'work.medium' ), $id ) );
		$this->assertSame( 2018, $this->adapter->get( $this->field( 'work.year' ), $id ) );
		$this->assertSame( $profile, $this->adapter->get( $this->field( 'work.creator' ), $id ) );
	}
}
