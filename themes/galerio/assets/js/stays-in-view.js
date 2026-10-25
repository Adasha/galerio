/**
 * Column style "Stays in view": a column shorter than the window stays at the
 * top while the page scrolls; a taller one scrolls with the page until its end
 * is in view, then stays there. The CSS makes the column sticky; this sets how
 * far from the top it sticks, which needs the column's height.
 */
( function () {
	const columns = document.querySelectorAll(
		'.wp-block-column.is-style-stays-in-view'
	);

	if ( ! columns.length ) {
		return;
	}

	const place = ( column ) => {
		column.style.removeProperty( '--galerio-stays-in-view-top' );

		const style = window.getComputedStyle( column );

		// Not sticky, e.g. on phones where columns stack.
		if ( 'sticky' !== style.position ) {
			return;
		}

		const gap = parseFloat( style.top ) || 0;
		const top = Math.min(
			gap,
			window.innerHeight - column.offsetHeight - gap
		);

		column.style.setProperty( '--galerio-stays-in-view-top', top + 'px' );
	};

	const placeAll = () => columns.forEach( place );

	placeAll();
	window.addEventListener( 'resize', placeAll );

	if ( 'ResizeObserver' in window ) {
		const observer = new window.ResizeObserver( placeAll );
		columns.forEach( ( column ) => observer.observe( column ) );
	}
} )();
