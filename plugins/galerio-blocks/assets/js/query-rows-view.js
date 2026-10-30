/**
 * Query Loops limited to so many rows (the Rows panel): items after the last
 * row are hidden.
 *
 * Rows are counted as laid out, so the limit holds as the grid's columns
 * change with the screen's width. Without this script every item shows.
 */
( function () {
	/**
	 * Fit one Query Loop's list to its rows.
	 *
	 * @param {HTMLElement} list The Post Template's list.
	 * @param {number}      rows Rows to show.
	 */
	function fit( list, rows ) {
		const items = Array.from( list.children );

		items.forEach( ( item ) => ( item.hidden = false ) );

		const top = ( item ) => Math.round( item.getBoundingClientRect().top );

		// The top of each row, in order.
		const tops = Array.from( new Set( items.map( top ) ) ).sort(
			( a, b ) => a - b
		);

		if ( tops.length <= rows ) {
			return;
		}

		const next = tops[ rows ];

		items.forEach( ( item ) => {
			item.hidden = top( item ) >= next;
		} );
	}

	function init() {
		document
			.querySelectorAll( '.wp-block-query.has-galerio-rows' )
			.forEach( ( block ) => {
				const list = block.querySelector( '.wp-block-post-template' );
				const rows = parseInt( block.dataset.galerioRows, 10 );

				if ( ! list || ! rows ) {
					return;
				}

				let frame = 0;
				let width = 0;
				const refit = () => {
					window.cancelAnimationFrame( frame );
					frame = window.requestAnimationFrame( () =>
						fit( list, rows )
					);
				};

				if ( window.ResizeObserver ) {
					// The columns change with the width. Only the width: the
					// block's height is what this script changes.
					new window.ResizeObserver( () => {
						if ( block.clientWidth !== width ) {
							width = block.clientWidth;
							refit();
						}
					} ).observe( block );
				} else {
					window.addEventListener( 'resize', refit );
				}

				// Images that load late change the rows' heights.
				list.addEventListener( 'load', refit, true );

				fit( list, rows );
			} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
