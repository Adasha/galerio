/**
 * The Exhibition Dates block: settings in the sidebar and a preview made in
 * the editor from the collection's `galerio_fields`, matching the server's
 * render.
 *
 * The preview sends no requests of its own. A Query Loop of collections has
 * already loaded their records, so a grid of cards costs nothing extra. (A
 * request per card, redone as the editor redraws, was enough to trip a
 * host's firewall.)
 */
import { registerBlockType } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl, ToggleControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { dateI18n, getSettings } from '@wordpress/date';

import metadata from './block.json';
import './style.scss';

/**
 * A date as Pods stores it, or empty. Pods stores an empty date as
 * "0000-00-00".
 *
 * @param {string|undefined} value Field value.
 * @return {string} "Y-m-d" or empty.
 */
function cleanDate( value ) {
	return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test( value )
		? value.slice( 0, 10 ).replace( /^0000-00-00$/, '' )
		: '';
}

/**
 * A date in a PHP date format. The date is read and shown as UTC (midnight,
 * with the Z: without it, it is read in the browser's time zone), so it
 * never moves a day.
 *
 * @param {string} date   "Y-m-d".
 * @param {string} format PHP date format.
 * @return {Element} A <time> element.
 */
function time( date, format ) {
	return (
		<time dateTime={ date }>
			{ dateI18n( format, `${ date }T00:00:00Z`, true ) }
		</time>
	);
}

/**
 * A translated string with its placeholders (%s, %1$s, %2$s) filled by
 * elements, in the order the translation puts them.
 *
 * @param {string}    template Translated string.
 * @param {Element[]} values   Elements for %1$s, %2$s (or %s).
 * @return {Element} The filled string.
 */
function fill( template, values ) {
	return (
		<>
			{ template.split( /(%(?:\d\$)?s)/ ).map( ( part, index ) => {
				const match = /^%(?:(\d)\$)?s$/.exec( part );

				return match ? (
					<span key={ index }>
						{ values[ match[ 1 ] ? match[ 1 ] - 1 : 0 ] }
					</span>
				) : (
					part
				);
			} ) }
		</>
	);
}

/**
 * The dates, as Exhibition_Dates_Block::text() writes them.
 *
 * @param {string} start  "Y-m-d" or empty.
 * @param {string} end    "Y-m-d" or empty.
 * @param {string} format "site" or "short".
 * @return {Element|null} The dates, or null when there are none.
 */
function datesText( start, end, format ) {
	if ( ! start && ! end ) {
		return null;
	}

	const site = getSettings().formats.date;

	if ( ! end || end === start ) {
		return end === start
			? time( start, site )
			: fill(
					/* translators: %s: the date an exhibition opens. */
					__( 'From %s', 'galerio-blocks' ),
					[ time( start, site ) ]
				);
	}

	if ( ! start ) {
		return fill(
			/* translators: %s: the date an exhibition closes. */
			__( 'Until %s', 'galerio-blocks' ),
			[ time( end, site ) ]
		);
	}

	let from = site;
	let to = site;
	/* translators: 1: opening date, 2: closing date. */
	let range = __( '%1$s – %2$s', 'galerio-blocks' );

	if ( format === 'short' ) {
		from = _x( 'j F Y', 'short exhibition date', 'galerio-blocks' );
		to = from;

		if ( start.slice( 0, 7 ) === end.slice( 0, 7 ) ) {
			from = _x(
				'j',
				'opening day, when an exhibition closes in the same month',
				'galerio-blocks'
			);
			/* translators: 1: opening day, 2: closing date, in the same month. */
			range = __( '%1$s–%2$s', 'galerio-blocks' );
		} else if ( start.slice( 0, 4 ) === end.slice( 0, 4 ) ) {
			from = _x(
				'j F',
				'opening date, when an exhibition closes in the same year',
				'galerio-blocks'
			);
		}
	}

	return fill( range, [ time( start, from ), time( end, to ) ] );
}

/**
 * A collection's status, as Exhibitions::status() works it out.
 *
 * @param {string} start "Y-m-d" or empty.
 * @param {string} end   "Y-m-d" or empty.
 * @return {string} "upcoming", "current", "past", or empty.
 */
function statusOf( start, end ) {
	if ( ! start ) {
		return '';
	}

	// Today in the site's time zone.
	const today = dateI18n( 'Y-m-d', new Date() );

	if ( start > today ) {
		return 'upcoming';
	}
	return end && end < today ? 'past' : 'current';
}

const LABELS = {
	upcoming: _x( 'Upcoming', 'exhibition status', 'galerio-blocks' ),
	current: _x( 'On now', 'exhibition status', 'galerio-blocks' ),
	past: _x( 'Past', 'exhibition status', 'galerio-blocks' ),
};

function Edit( { attributes, setAttributes, context } ) {
	const { format, showStatus } = attributes;
	const { postId, postType } = context;

	const fields = useSelect(
		( select ) =>
			postId && postType === 'galerio_collection'
				? select( coreStore ).getEntityRecord(
						'postType',
						postType,
						postId
					)?.galerio_fields
				: undefined,
		[ postId, postType ]
	);

	const start = cleanDate( fields?.start_date );
	const end = cleanDate( fields?.end_date );
	const text = datesText( start, end, format );
	const status = text && showStatus ? statusOf( start, end ) : '';

	const blockProps = useBlockProps( {
		className: status ? `is-${ status }` : undefined,
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dates', 'galerio-blocks' ) }>
					<SelectControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Format', 'galerio-blocks' ) }
						value={ format }
						options={ [
							{
								label: __(
									'Both dates in full (the site’s date format)',
									'galerio-blocks'
								),
								value: 'site',
							},
							{
								label: __(
									'Short range, e.g. 19 May – 16 June 2023',
									'galerio-blocks'
								),
								value: 'short',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { format: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show upcoming, on now or past',
							'galerio-blocks'
						) }
						checked={ showStatus }
						onChange={ ( value ) =>
							setAttributes( { showStatus: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<p { ...blockProps }>
				{ text ? (
					<>
						{ status && (
							<>
								<span
									className={ `wp-block-galerio-exhibition-dates__status is-${ status }` }
								>
									{ LABELS[ status ] }
								</span>{ ' ' }
							</>
						) }
						{ text }
					</>
				) : (
					<em>
						{ __(
							'Exhibition dates show here when the collection has them.',
							'galerio-blocks'
						) }
					</em>
				) }
			</p>
		</>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
