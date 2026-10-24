/**
 * Front end of the Gallery block: a lightbox for galleries that have one.
 *
 * Each image already links to its full-size file, so without this script
 * visitors still get the large image. With it, the link opens a dialog with
 * the image, its caption, previous and next buttons, arrow keys, swipes and
 * Escape to close. One dialog serves every gallery on the page.
 */
const BLOCK = 'wp-block-galerio-gallery';

let dialog = null;

/**
 * Put numbers into a translated label such as "%1$d of %2$d".
 *
 * @param {string}    label   Label.
 * @param {...number} numbers Numbers, in order.
 * @return {string} Label.
 */
function format( label, ...numbers ) {
	return label.replace( /%(\d)\$d/g, ( match, position ) =>
		String( numbers[ position - 1 ] )
	);
}

/**
 * Make an icon button for the dialog.
 *
 * @param {string} className Modifier class.
 * @param {string} label     Accessible name.
 * @param {string} path      SVG path.
 * @return {HTMLButtonElement} Button.
 */
function button( className, label, path ) {
	const element = document.createElement( 'button' );
	element.type = 'button';
	element.className = `galerio-lightbox__${ className }`;
	element.setAttribute( 'aria-label', label );
	element.innerHTML = `<svg viewBox="0 0 24 24" width="28" height="28" aria-hidden="true" focusable="false"><path d="${ path }" fill="currentColor"/></svg>`;
	return element;
}

/**
 * The page's lightbox dialog, made the first time it's needed.
 *
 * @param {Object} labels Translated labels.
 * @return {Object} The dialog and its parts.
 */
function lightbox( labels ) {
	if ( dialog ) {
		return dialog;
	}

	const element = document.createElement( 'dialog' );
	element.className = 'galerio-lightbox';
	element.setAttribute( 'aria-label', labels.dialog );

	const figure = document.createElement( 'figure' );
	figure.className = 'galerio-lightbox__figure';
	const image = document.createElement( 'img' );
	image.className = 'galerio-lightbox__image';
	image.alt = '';
	const caption = document.createElement( 'figcaption' );
	caption.className = 'galerio-lightbox__caption';
	figure.append( image, caption );

	const count = document.createElement( 'p' );
	count.className = 'galerio-lightbox__count';
	count.setAttribute( 'aria-live', 'polite' );

	const close = button(
		'close',
		labels.close,
		'M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z'
	);
	const prev = button(
		'prev',
		labels.prev,
		'M15.41 7.41 14 6l-6 6 6 6 1.41-1.41L10.83 12z'
	);
	const next = button(
		'next',
		labels.next,
		'M10 6 8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z'
	);

	element.append( figure, count, prev, next, close );
	document.body.append( element );

	dialog = {
		element,
		image,
		caption,
		count,
		prev,
		next,
		links: [],
		index: 0,
		labels,
	};

	close.addEventListener( 'click', () => element.close() );
	prev.addEventListener( 'click', () => show( dialog.index - 1 ) );
	next.addEventListener( 'click', () => show( dialog.index + 1 ) );

	// A click on the backdrop, outside the image, closes it.
	element.addEventListener( 'click', ( event ) => {
		if ( event.target === element ) {
			element.close();
		}
	} );

	element.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowLeft' ) {
			show( dialog.index - 1 );
		} else if ( event.key === 'ArrowRight' ) {
			show( dialog.index + 1 );
		}
	} );

	let startX = null;
	element.addEventListener(
		'touchstart',
		( event ) => {
			startX = event.changedTouches[ 0 ].clientX;
		},
		{ passive: true }
	);
	element.addEventListener(
		'touchend',
		( event ) => {
			if ( startX === null ) {
				return;
			}
			const distance = event.changedTouches[ 0 ].clientX - startX;
			if ( Math.abs( distance ) > 50 ) {
				show( dialog.index + ( distance < 0 ? 1 : -1 ) );
			}
			startX = null;
		},
		{ passive: true }
	);

	// Focus goes back to the image that opened the lightbox.
	element.addEventListener( 'close', () => {
		document.documentElement.classList.remove( 'has-galerio-lightbox' );
		dialog.links[ dialog.index ]?.focus();
	} );

	return dialog;
}

/**
 * Show one of the current gallery's images, wrapping round at the ends.
 *
 * @param {number} index Image position.
 */
function show( index ) {
	const { links } = dialog;
	const total = links.length;
	dialog.index = ( ( index % total ) + total ) % total;

	const link = links[ dialog.index ];
	const thumbnail = link.querySelector( 'img' );
	const caption = link
		.closest( `.${ BLOCK }__figure` )
		?.querySelector( `.${ BLOCK }__caption` );

	dialog.image.src = link.href;
	dialog.image.alt = thumbnail ? thumbnail.alt : '';
	dialog.image.width = Number( link.dataset.width ) || 0;
	dialog.image.height = Number( link.dataset.height ) || 0;
	dialog.caption.innerHTML = caption ? caption.innerHTML : '';
	dialog.caption.hidden = ! caption;
	dialog.count.textContent =
		total > 1 ? format( dialog.labels.count, dialog.index + 1, total ) : '';
	dialog.prev.hidden = total < 2;
	dialog.next.hidden = total < 2;
}

function init( gallery ) {
	let labels;
	try {
		labels = JSON.parse( gallery.dataset.galerioLightbox );
	} catch {
		return;
	}

	const links = Array.from( gallery.querySelectorAll( `.${ BLOCK }__link` ) );

	links.forEach( ( link, index ) => {
		link.addEventListener( 'click', ( event ) => {
			if (
				event.metaKey ||
				event.ctrlKey ||
				event.shiftKey ||
				event.button !== 0
			) {
				return;
			}
			event.preventDefault();

			const box = lightbox( labels );
			box.links = links;
			show( index );
			document.documentElement.classList.add( 'has-galerio-lightbox' );
			box.element.showModal();
		} );
	} );
}

/**
 * Whether this page is the block editor, which loads front-end scripts too.
 * Its previews stay still, so blocks can be selected.
 *
 * @return {boolean} True in the editor.
 */
function inEditor() {
	const { classList } = document.body;
	return (
		classList.contains( 'block-editor-iframe__body' ) ||
		classList.contains( 'block-editor-page' )
	);
}

function initAll() {
	if ( inEditor() ) {
		return;
	}

	document
		.querySelectorAll( `.${ BLOCK }[data-galerio-lightbox]` )
		.forEach( init );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAll );
} else {
	initAll();
}
