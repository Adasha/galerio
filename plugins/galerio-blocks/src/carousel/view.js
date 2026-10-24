/**
 * Front end of the Carousel block: turns the server's markup into an Embla
 * carousel with arrows, dots, autoplay and a pause button.
 *
 * Settings and translated labels come from the wrapper's
 * `data-galerio-carousel` attribute. Without this script the slides scroll
 * sideways, so nothing is lost.
 *
 * Themes and plugins can change a carousel through `wp.hooks` (load your
 * script with the `wp-hooks` dependency):
 *
 * - `galerio.carousel.options` filters Embla's options ( options, element, settings ).
 * - `galerio.carousel.plugins` filters Embla's plugins ( plugins, element, settings ),
 *   for example to add a transition offered through the PHP
 *   `galerio_carousel_transitions` filter.
 * - `galerio.carousel.init` runs once a carousel is ready ( embla, element ).
 */
import EmblaCarousel from 'embla-carousel';
import Fade from 'embla-carousel-fade';

const BLOCK = 'wp-block-galerio-carousel';

/**
 * Run a `wp.hooks` filter when hooks are loaded on the page.
 *
 * @param {string}     name  Filter name.
 * @param {unknown}    value Value to filter.
 * @param {...unknown} args  Extra arguments.
 * @return {unknown} Filtered value.
 */
function applyFilters( name, value, ...args ) {
	const hooks = window.wp?.hooks;
	return hooks ? hooks.applyFilters( name, value, ...args ) : value;
}

/**
 * Put a number into a translated label such as "Go to slide %d".
 *
 * @param {string}    label   Label with `%d` or `%1$d`, `%2$d` placeholders.
 * @param {...number} numbers Numbers, in order.
 * @return {string} Label.
 */
function format( label, ...numbers ) {
	let next = 0;
	return label.replace( /%(?:(\d)\$)?d/g, ( match, position ) =>
		String( numbers[ position ? position - 1 : next++ ] )
	);
}

/**
 * Bring one carousel to life.
 *
 * @param {HTMLElement} root The block's wrapper.
 */
function init( root ) {
	let settings;
	try {
		settings = JSON.parse( root.dataset.galerioCarousel || '{}' );
	} catch {
		return;
	}

	const viewport = root.querySelector( `.${ BLOCK }__viewport` );
	const container =
		settings.source === 'loop'
			? viewport?.querySelector( '.wp-block-post-template' )
			: viewport?.querySelector( `.${ BLOCK }__track` );

	if ( ! container || container.children.length < 2 ) {
		root.classList.add( 'is-single' );
		return;
	}

	const labels = settings.labels || {};
	const slides = Array.from( container.children );
	const reducedMotion = window.matchMedia(
		'(prefers-reduced-motion: reduce)'
	).matches;

	container.classList.add( `${ BLOCK }__track` );
	slides.forEach( ( slide, index ) => {
		slide.classList.add( `${ BLOCK }__slide` );
		slide.setAttribute( 'role', 'group' );
		slide.setAttribute( 'aria-roledescription', labels.role || 'slide' );
		if ( labels.slide ) {
			slide.setAttribute(
				'aria-label',
				format( labels.slide, index + 1, slides.length )
			);
		}
	} );

	const plugins = settings.transition === 'fade' ? [ Fade() ] : [];
	const options = {
		container,
		// A slide counts as in view once half of it shows.
		inViewThreshold: 0.5,
		loop: !! settings.loop,
		duration: settings.duration || 25,
		align: 'start',
		slidesToScroll: 1,
	};

	const embla = EmblaCarousel(
		viewport,
		applyFilters( 'galerio.carousel.options', options, root, settings ),
		applyFilters( 'galerio.carousel.plugins', plugins, root, settings )
	);

	const prevButtons = root.querySelectorAll( `.${ BLOCK }__prev` );
	const nextButtons = root.querySelectorAll( `.${ BLOCK }__next` );
	const dotsBox = root.querySelector( `.${ BLOCK }__dots` );
	const pauseButton = root.querySelector( `.${ BLOCK }__pause` );
	let dots = [];

	prevButtons.forEach( ( button ) =>
		button.addEventListener( 'click', () => embla.scrollPrev() )
	);
	nextButtons.forEach( ( button ) =>
		button.addEventListener( 'click', () => embla.scrollNext() )
	);

	// One dot per stop, which is one per slide unless several are in view.
	function buildDots() {
		if ( ! dotsBox ) {
			return;
		}
		dotsBox.replaceChildren();
		dots = embla.scrollSnapList().map( ( snap, index ) => {
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = `${ BLOCK }__dot`;
			if ( labels.goTo ) {
				dot.setAttribute(
					'aria-label',
					format( labels.goTo, index + 1 )
				);
			}
			dot.addEventListener( 'click', () => embla.scrollTo( index ) );
			dotsBox.appendChild( dot );
			return dot;
		} );
	}

	// The dots and arrows follow the selected stop.
	function update() {
		const selected = embla.selectedScrollSnap();

		dots.forEach( ( dot, index ) => {
			if ( index === selected ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
		prevButtons.forEach( ( button ) => {
			button.disabled = ! embla.canScrollPrev();
		} );
		nextButtons.forEach( ( button ) => {
			button.disabled = ! embla.canScrollNext();
		} );
	}

	// Slides out of view can't be tabbed into or read out. Faded slides all
	// sit in the same place, so there only the selected one counts.
	function hideOutOfView() {
		const inView = new Set(
			settings.transition === 'fade'
				? [ embla.selectedScrollSnap() ]
				: embla.slidesInView()
		);

		slides.forEach( ( slide, index ) => {
			slide.inert = ! inView.has( index );
		} );
	}

	// Autoplay waits while the pointer or keyboard focus is on the carousel,
	// while the tab is hidden, and for good once someone presses pause. It
	// starts paused for people who prefer reduced motion.
	let timer = null;
	let hovering = false;
	let focused = false;
	let paused = reducedMotion;

	function playing() {
		return (
			settings.autoplay &&
			! paused &&
			! hovering &&
			! focused &&
			! document.hidden
		);
	}

	function schedule() {
		window.clearTimeout( timer );
		container.setAttribute( 'aria-live', playing() ? 'off' : 'polite' );
		if ( playing() ) {
			timer = window.setTimeout( () => {
				if ( embla.canScrollNext() ) {
					embla.scrollNext();
				} else {
					embla.scrollTo( 0 );
				}
			}, settings.interval || 6000 );
		}
	}

	function showPaused() {
		if ( ! pauseButton ) {
			return;
		}
		pauseButton.classList.toggle( 'is-paused', paused );
		pauseButton.setAttribute(
			'aria-label',
			paused ? labels.play : labels.pause
		);
	}

	if ( settings.autoplay ) {
		root.addEventListener( 'pointerenter', () => {
			hovering = true;
			schedule();
		} );
		root.addEventListener( 'pointerleave', () => {
			hovering = false;
			schedule();
		} );
		root.addEventListener( 'focusin', () => {
			focused = true;
			schedule();
		} );
		root.addEventListener( 'focusout', ( event ) => {
			if ( ! root.contains( event.relatedTarget ) ) {
				focused = false;
				schedule();
			}
		} );
		document.addEventListener( 'visibilitychange', schedule );
		pauseButton?.addEventListener( 'click', () => {
			paused = ! paused;
			showPaused();
			schedule();
		} );
		showPaused();
	}

	root.addEventListener( 'keydown', ( event ) => {
		if ( event.target.closest( 'input, textarea, select' ) ) {
			return;
		}
		if ( event.key === 'ArrowLeft' ) {
			embla.scrollPrev();
		} else if ( event.key === 'ArrowRight' ) {
			embla.scrollNext();
		}
	} );

	embla.on( 'select', () => {
		update();
		schedule();
		if ( settings.transition === 'fade' ) {
			hideOutOfView();
		}
	} );
	embla.on( 'slidesInView', hideOutOfView );
	embla.on( 'reInit', () => {
		buildDots();
		update();
		hideOutOfView();
	} );

	buildDots();
	update();
	hideOutOfView();
	schedule();
	root.classList.add( 'is-ready' );

	window.wp?.hooks?.doAction( 'galerio.carousel.init', embla, root );
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
		.querySelectorAll( `.${ BLOCK }[data-galerio-carousel]` )
		.forEach( ( root ) => {
			if ( ! root.classList.contains( 'is-ready' ) ) {
				init( root );
			}
		} );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAll );
} else {
	initAll();
}
