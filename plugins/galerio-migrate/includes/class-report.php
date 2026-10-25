<?php
/**
 * What a migration run did.
 *
 * @package Galerio\Migrate
 */

namespace Galerio\Migrate;

defined( 'ABSPATH' ) || exit;

/**
 * Counts by step and outcome, and things for a person to look at.
 */
final class Report {

	/**
	 * Counts by step and outcome: step => outcome => count.
	 *
	 * @var array<string, array<string, int>>
	 */
	public $counts = array();

	/**
	 * Things for a person to look at, by step.
	 *
	 * @var array<string, string[]>
	 */
	public $notes = array();

	/**
	 * Count an outcome.
	 *
	 * @param string $step    Step.
	 * @param string $outcome Outcome.
	 * @param int    $by      Amount.
	 */
	public function count( $step, $outcome, $by = 1 ) {
		if ( ! isset( $this->counts[ $step ][ $outcome ] ) ) {
			$this->counts[ $step ][ $outcome ] = 0;
		}
		$this->counts[ $step ][ $outcome ] += $by;
	}

	/**
	 * Note something for a person to look at.
	 *
	 * @param string $step Step.
	 * @param string $text Note.
	 */
	public function note( $step, $text ) {
		$this->notes[ $step ][] = $text;
	}
}
