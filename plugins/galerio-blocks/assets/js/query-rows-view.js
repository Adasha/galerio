/**
 * Query Loops limited to so many rows (the Rows panel): items after the last
 * row are hidden.
 *
 * Rows are counted as laid out, so the limit holds as the grid's columns
 * change with the screen's width. Without this script every item shows.
 *
 * It runs in the editor's canvas too, where Query Loops come and go and
 * their settings change, so there it watches the whole canvas.
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

		// The top of each row, in order. Items that aren't laid out are left
		// out, such as the editor's hidden copy of the item being edited.
		const shown = items.filter( ( item ) => item.getClientRects().length );
		const tops = Array.from( new Set( shown.map( top ) ) ).sort(
			( a, b ) => a - b
		);

		if ( tops.length <= rows ) {
			return;
		}

		const next = tops[ rows ];

		shown.forEach( ( item ) => {
			item.hidden = top( item ) >= next;
		} );
	}

	/**
	 * In the editor: fit every limited Query Loop whenever the canvas
	 * changes, and show every item again in one no longer limited.
	 */
	function initEditor() {
		let fitted = new Set();
		let frame = 0;

		const fitAll = () => {
			const lists = new Set();

			document
				.querySelectorAll( '.wp-block-query.has-galerio-rows' )
				.forEach( ( block ) => {
					const list = block.querySelector(
						'.wp-block-post-template'
					);
					const rows = parseInt( block.dataset.galerioRows, 10 );

					if ( list && rows ) {
						fit( list, rows );
						lists.add( list );
					}
				} );

			fitted.forEach( ( list ) => {
				if ( ! lists.has( list ) ) {
					Array.from( list.children ).forEach(
						( item ) => ( item.hidden = false )
					);
				}
			} );
			fitted = lists;
		};

		const refit = () => {
			window.cancelAnimationFrame( frame );
			frame = window.requestAnimationFrame( fitAll );
		};

		// Blocks added, removed or redrawn, the rows setting changed, and
		// styles added to the head, such as the grid's layout. Not `hidden`,
		// which is what fitting changes.
		new window.MutationObserver( refit ).observe(
			document.documentElement,
			{
				childList: true,
				subtree: true,
				characterData: true,
				attributes: true,
				attributeFilter: [ 'class', 'style', 'data-galerio-rows' ],
			}
		);
		window.addEventListener( 'resize', refit );
		document.addEventListener( 'load', refit, true );

		fitAll();
	}

	function init() {
		// Set by Query_Rows::canvas_assets(). The canvas's classes come too
		// late to tell.
		if ( window.galerioQueryRowsEditor ) {
			initEditor();
			return;
		}

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
