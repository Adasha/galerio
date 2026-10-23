<?php
/**
 * Term decisions for galerio.net.
 *
 * The site galerio.net is a demo of well-known paintings. Its Style terms and
 * work_categorisation values become Labels, using art-historical movement
 * names; its flat Medium list becomes disciplines with forms beneath.
 *
 * @package Galerio\Migrate
 */

defined( 'ABSPATH' ) || exit;

return array(

	/*
	 * Term names to change once terms are in their new taxonomy: taxonomy =>
	 * old name => new name. A new name of null deletes the term.
	 */
	'rename'         => array(
		// Van Gogh, the only artist filed under it, was a Post-Impressionist.
		'galerio_label'  => array(
			'Impressionism' => 'Post-Impressionism',
		),
		// Oil, oil paint and paint were always applied together; canvas is a
		// support, already in each work's free-text medium ("Oil on canvas").
		'galerio_medium' => array(
			'oil'       => 'Oil',
			'oil paint' => 'Oil',
			'paint'     => 'Oil',
			'canvas'    => null,
			'sculpture' => 'Sculpture',
		),
	),

	/*
	 * Medium hierarchy: discipline => forms beneath it. Terms not listed stay
	 * at the top level.
	 */
	'medium_tree'    => array(
		'Painting'  => array( 'Oil' ),
		'Sculpture' => array(),
	),

	/*
	 * work_categorisation values => Label names. Values are split at commas
	 * first; a value not listed becomes a Label of the same name.
	 */
	'categorisation' => array(
		'Impressionist' => 'Post-Impressionism',
	),

	/*
	 * Mediums for works that have none, from their free-text medium: regular
	 * expression => medium term.
	 */
	'infer_medium'   => array(
		'/\boil\b/i' => 'Oil',
	),
);
