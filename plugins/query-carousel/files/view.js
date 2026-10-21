/**
 * src/view.js
 *
 * Frontend script. Loaded only on pages that contain the
 * custom/query-carousel block (controlled by the `viewScript` field in
 * block.json, which is automatically dequeued on pages without the block).
 *
 * Responsibilities:
 *  1. Wait for DOM ready.
 *  2. Find every .wp-block-custom-query-carousel instance on the page.
 *  3. Read configuration from data-* attributes on each wrapper.
 *  4. Locate the Embla scroll container (the inner post template element).
 *  5. Inject accessible navigation arrows and pagination dots if requested.
 *  6. Initialise an independent Embla Carousel instance per wrapper.
 *  7. Apply autoplay, loop, fade, and any custom effects registered via hooks.
 *  8. Handle keyboard and focus-based accessibility (WCAG 2.1 AA).
 *  9. Expose a public hook registry (window.queryCarouselHooks) so third-party
 *     developers can register custom Embla plugins or transition handlers.
 *
 * Embla Carousel is a zero-dependency, unstyled, bare-bones library.
 * Install via: npm install embla-carousel
 * Docs: https://www.embla-carousel.com/
 */

import EmblaCarousel from 'embla-carousel';

/* ─────────────────────────────────────────────────────────────────────────────
   1. PUBLIC HOOK REGISTRY
   Allows third-party JS (e.g. in a child theme's functions.js or another
   plugin) to register custom Embla plugins or to intercept initialisation.

   Usage:
     window.queryCarouselHooks = window.queryCarouselHooks || {};
     window.queryCarouselHooks.plugins = window.queryCarouselHooks.plugins || [];
     window.queryCarouselHooks.plugins.push( myCustomEmblaPlugin() );

   Or use the WordPress @wordpress/hooks package if it is available:
     wp.hooks.addFilter(
       'query_carousel.embla_options',
       'my-plugin/custom-options',
       ( options, wrapper ) => ({ ...options, dragFree: true })
     );
───────────────────────────────────────────────────────────────────────────── */

/**
 * Retrieve any additional Embla plugins registered by third parties.
 * Returns an empty array if none are registered.
 *
 * @returns {Array} Array of Embla plugin instances.
 */
function getRegisteredPlugins() {
	return window?.queryCarouselHooks?.plugins ?? [];
}

/**
 * Allow third parties to filter the Embla options object before the instance
 * is created. Uses @wordpress/hooks if available, with a plain callback
 * fallback.
 *
 * @param {Object}      options Base Embla options.
 * @param {HTMLElement} wrapper The carousel wrapper element.
 * @returns {Object} Potentially modified options.
 */
function applyOptionsFilters( options, wrapper ) {
	// Use the WordPress hooks API if wp.hooks is loaded on the page.
	if ( window?.wp?.hooks?.applyFilters ) {
		return window.wp.hooks.applyFilters(
			'query_carousel.embla_options',
			options,
			wrapper
		);
	}

	// Plain fallback: iterate over registered filter functions.
	const filters = window?.queryCarouselHooks?.optionFilters ?? [];
	return filters.reduce(
		( acc, fn ) => ( typeof fn === 'function' ? fn( acc, wrapper ) : acc ),
		options
	);
}

/* ─────────────────────────────────────────────────────────────────────────────
   2. UTILITY HELPERS
───────────────────────────────────────────────────────────────────────────── */

/**
 * Parse a data-* attribute string to a boolean.
 *
 * @param {string}  value        The raw dataset string value.
 * @param {boolean} defaultValue Fallback if the attribute is absent/invalid.
 * @returns {boolean}
 */
function parseBoolAttr( value, defaultValue = false ) {
	if ( value === undefined || value === null || value === '' ) {
		return defaultValue;
	}
	return value === 'true';
}

/**
 * Parse a data-* attribute string to a positive integer.
 *
 * @param {string} value        The raw dataset string value.
 * @param {number} defaultValue Fallback if the attribute is absent/invalid.
 * @returns {number}
 */
function parseIntAttr( value, defaultValue = 0 ) {
	const parsed = parseInt( value, 10 );
	return Number.isFinite( parsed ) ? parsed : defaultValue;
}

/**
 * Create an accessible navigation button (prev/next arrow).
 *
 * @param {'prev'|'next'} direction Direction the button scrolls.
 * @param {string}        label     Localised accessible label.
 * @returns {HTMLButtonElement}
 */
function createArrowButton( direction, label ) {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = `query-carousel__arrow query-carousel__arrow--${ direction }`;
	button.setAttribute( 'aria-label', label );

	// Accessible SVG arrow icon (inline, no external file dependency).
	// The path draws a simple chevron. Fill is set to `currentColor` so themes
	// can colour it via CSS without any JS changes.
	const svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'aria-hidden', 'true' );
	svg.setAttribute( 'focusable', 'false' );
	svg.setAttribute( 'width', '24' );
	svg.setAttribute( 'height', '24' );

	const path = document.createElementNS( 'http://www.w3.org/2000/svg', 'path' );
	path.setAttribute(
		'd',
		direction === 'prev'
			? 'M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z'
			: 'M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z'
	);
	path.setAttribute( 'fill', 'currentColor' );

	svg.appendChild( path );
	button.appendChild( svg );

	return button;
}

/**
 * Create the pagination dots container.
 *
 * @param {number} slideCount Number of slides (dots to render).
 * @returns {{ container: HTMLDivElement, dots: HTMLButtonElement[] }}
 */
function createDots( slideCount ) {
	const container = document.createElement( 'div' );
	container.className = 'query-carousel__dots';
	container.setAttribute( 'role', 'tablist' );
	container.setAttribute(
		'aria-label',
		/* translators: ARIA label for carousel pagination dots. */
		'Carousel pagination'
	);

	const dots = [];

	for ( let i = 0; i < slideCount; i++ ) {
		const dot = document.createElement( 'button' );
		dot.type = 'button';
		dot.className = 'query-carousel__dot';
		dot.setAttribute( 'role', 'tab' );
		dot.setAttribute(
			'aria-label',
			/* translators: %d is replaced with the slide number. */
			`Go to slide ${ i + 1 }`
		);
		dot.setAttribute( 'aria-selected', i === 0 ? 'true' : 'false' );
		dot.dataset.index = String( i );

		if ( i === 0 ) {
			dot.classList.add( 'is-active' );
		}

		container.appendChild( dot );
		dots.push( dot );
	}

	return { container, dots };
}

/* ─────────────────────────────────────────────────────────────────────────────
   3. FADE TRANSITION HANDLER
   When `data-transition-effect="fade"` is set, we apply CSS opacity
   transitions instead of Embla's native slide movement.
   The slide container needs `overflow: visible` and each slide needs
   `position: absolute` for stacking — see style.scss.
───────────────────────────────────────────────────────────────────────────── */

/**
 * Apply a fade effect to a carousel wrapper by toggling CSS classes on slides.
 * The actual CSS animation is defined in style.scss so themes can override it.
 *
 * @param {Object}      embla          The Embla instance.
 * @param {NodeList}    slideNodes     All slide elements.
 * @param {HTMLElement} viewportEl     The Embla viewport element.
 */
function setupFadeEffect( embla, slideNodes, viewportEl ) {
	viewportEl.classList.add( 'is-fade-effect' );

	const updateFade = () => {
		const selectedIndex = embla.selectedScrollSnap();
		slideNodes.forEach( ( slide, index ) => {
			if ( index === selectedIndex ) {
				slide.classList.add( 'is-fade-in' );
				slide.classList.remove( 'is-fade-out' );
				slide.setAttribute( 'aria-hidden', 'false' );
			} else {
				slide.classList.remove( 'is-fade-in' );
				slide.classList.add( 'is-fade-out' );
				slide.setAttribute( 'aria-hidden', 'true' );
			}
		} );
	};

	embla.on( 'select', updateFade );
	embla.on( 'init', updateFade );
	updateFade();
}

/* ─────────────────────────────────────────────────────────────────────────────
   4. AUTOPLAY MODULE
   A lightweight autoplay implementation that does NOT use Embla's official
   AutoPlay plugin (to avoid an extra package dependency) but is compatible
   with it if the developer chooses to swap it in via the plugin registry.
───────────────────────────────────────────────────────────────────────────── */

/**
 * Create a simple autoplay controller.
 *
 * @param {Object} embla          The Embla instance.
 * @param {number} intervalMs     Milliseconds between transitions.
 * @param {boolean} loop          Whether the carousel loops.
 * @returns {{ play: Function, stop: Function }}
 */
function createAutoplay( embla, intervalMs, loop ) {
	let timer = null;

	const play = () => {
		stop();
		timer = setInterval( () => {
			if ( loop || embla.canScrollNext() ) {
				embla.scrollNext();
			} else {
				embla.scrollTo( 0 );
			}
		}, intervalMs );
	};

	const stop = () => {
		if ( timer !== null ) {
			clearInterval( timer );
			timer = null;
		}
	};

	return { play, stop };
}

/* ─────────────────────────────────────────────────────────────────────────────
   5. MAIN INITIALISATION FUNCTION
───────────────────────────────────────────────────────────────────────────── */

/**
 * Initialise a single Query Carousel instance.
 *
 * @param {HTMLElement} wrapper The `.wp-block-custom-query-carousel` element.
 */
function initCarousel( wrapper ) {
	// ── Read configuration from data-* attributes ──────────────────────────
	const {
		transitionEffect = 'slide',
		autoplay: autoplayAttr = 'false',
		autoplaySpeed: autoplaySpeedAttr = '3000',
		loop: loopAttr = 'true',
		slidesToScroll: slidesToScrollAttr = 'auto',
		showArrows: showArrowsAttr = 'true',
		showDots: showDotsAttr = 'true',
	} = wrapper.dataset;

	const shouldAutoplay    = parseBoolAttr( autoplayAttr, false );
	const autoplaySpeed     = parseIntAttr( autoplaySpeedAttr, 3000 );
	const shouldLoop        = parseBoolAttr( loopAttr, true );
	const shouldShowArrows  = parseBoolAttr( showArrowsAttr, true );
	const shouldShowDots    = parseBoolAttr( showDotsAttr, true );
	const slidesToScroll    =
		slidesToScrollAttr === 'auto' ? 'auto' : parseIntAttr( slidesToScrollAttr, 1 );

	// ── Locate Embla structural elements ──────────────────────────────────
	// The Query Loop block renders its posts inside .wp-block-post-template.
	// We use that element as the Embla scroll container (the "slides" parent).
	// If the theme overrides this structure, developers can add a custom
	// `.query-carousel__viewport` element via theme.json extraProps or a
	// block.json `selectors` override.
	const viewport = wrapper.querySelector( '.wp-block-query' );
	const slideContainer = wrapper.querySelector( '.wp-block-post-template' );

	if ( ! viewport || ! slideContainer ) {
		// The inner Query Loop hasn't rendered any posts, or the DOM structure
		// is non-standard. Bail gracefully to avoid a JS error.
		console.warn(
			'[Query Carousel] Could not find expected Query Loop structure inside:',
			wrapper
		);
		return;
	}

	// ── Add structural CSS classes expected by style.scss ─────────────────
	// We add these dynamically so they are only present when JS is active
	// (progressive enhancement). Without JS, posts render as a normal list.
	viewport.classList.add( 'query-carousel__viewport' );
	slideContainer.classList.add( 'query-carousel__container' );

	const slideNodes = Array.from( slideContainer.children );
	slideNodes.forEach( ( slide ) => {
		slide.classList.add( 'query-carousel__slide' );
	} );

	const slideCount = slideNodes.length;

	if ( slideCount === 0 ) {
		return; // No posts to carousel.
	}

	// ── Build base Embla options ───────────────────────────────────────────
	let emblaOptions = {
		loop: shouldLoop,
		slidesToScroll,
		// Ensure the active slide is accessible to screen readers.
		// We manually handle aria-hidden on non-visible slides below.
		skipSnaps: false,
		dragFree: false,
	};

	// Allow third-party option filters before creating the instance.
	emblaOptions = applyOptionsFilters( emblaOptions, wrapper );

	// ── Initialise Embla ───────────────────────────────────────────────────
	// Merge any third-party plugins into the instance.
	const extraPlugins = getRegisteredPlugins();
	const embla = EmblaCarousel( viewport, emblaOptions, extraPlugins );

	// ── Fade transition ────────────────────────────────────────────────────
	if ( transitionEffect === 'fade' ) {
		setupFadeEffect( embla, slideNodes, viewport );
	}

	// ── Navigation Arrows ─────────────────────────────────────────────────
	if ( shouldShowArrows && slideCount > 1 ) {
		const prevBtn = createArrowButton( 'prev', 'Previous slide' );
		const nextBtn = createArrowButton( 'next', 'Next slide' );

		// Insert arrows into the carousel wrapper, outside the scroll area.
		wrapper.appendChild( prevBtn );
		wrapper.appendChild( nextBtn );

		prevBtn.addEventListener( 'click', () => embla.scrollPrev() );
		nextBtn.addEventListener( 'click', () => embla.scrollNext() );

		// Disable arrows at boundary when loop is off.
		const updateArrowState = () => {
			if ( shouldLoop ) return;
			prevBtn.disabled = ! embla.canScrollPrev();
			nextBtn.disabled = ! embla.canScrollNext();
			prevBtn.setAttribute( 'aria-disabled', String( ! embla.canScrollPrev() ) );
			nextBtn.setAttribute( 'aria-disabled', String( ! embla.canScrollNext() ) );
		};

		embla.on( 'select', updateArrowState );
		embla.on( 'init', updateArrowState );
		updateArrowState();
	}

	// ── Pagination Dots ────────────────────────────────────────────────────
	if ( shouldShowDots && slideCount > 1 ) {
		const { container: dotsContainer, dots } = createDots( slideCount );
		wrapper.appendChild( dotsContainer );

		// Update active dot on slide change.
		const updateDots = () => {
			const selectedIndex = embla.selectedScrollSnap();
			dots.forEach( ( dot, index ) => {
				const isActive = index === selectedIndex;
				dot.classList.toggle( 'is-active', isActive );
				dot.setAttribute( 'aria-selected', String( isActive ) );
			} );
		};

		embla.on( 'select', updateDots );
		embla.on( 'init', updateDots );

		// Clicking a dot scrolls to that slide.
		dots.forEach( ( dot ) => {
			dot.addEventListener( 'click', () => {
				embla.scrollTo( parseInt( dot.dataset.index, 10 ) );
			} );
		} );
	}

	// ── ARIA live region for slide announcements ───────────────────────────
	// Announces slide changes to screen reader users.
	const liveRegion = document.createElement( 'div' );
	liveRegion.className = 'query-carousel__live-region screen-reader-text';
	liveRegion.setAttribute( 'aria-live', 'polite' );
	liveRegion.setAttribute( 'aria-atomic', 'true' );
	wrapper.appendChild( liveRegion );

	const announceSlide = () => {
		const selected = embla.selectedScrollSnap() + 1;
		liveRegion.textContent = `Slide ${ selected } of ${ slideCount }`;
	};

	embla.on( 'select', announceSlide );

	// ── Slide aria-hidden management ───────────────────────────────────────
	// Hides non-active slides from screen readers so assistive technology
	// doesn't traverse off-screen content.
	const updateSlideAria = () => {
		const selectedIndex = embla.selectedScrollSnap();
		slideNodes.forEach( ( slide, index ) => {
			const isVisible = index === selectedIndex;
			slide.setAttribute( 'aria-hidden', String( ! isVisible ) );
			// Prevent tabbing into hidden slides.
			const focusables = slide.querySelectorAll(
				'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'
			);
			focusables.forEach( ( el ) => {
				if ( isVisible ) {
					el.removeAttribute( 'data-carousel-tabindex' );
					const saved = el.getAttribute( 'data-carousel-original-tabindex' );
					if ( saved !== null ) {
						el.setAttribute( 'tabindex', saved );
						el.removeAttribute( 'data-carousel-original-tabindex' );
					}
				} else {
					// Save existing tabindex if any.
					const currentTabindex = el.getAttribute( 'tabindex' );
					if ( currentTabindex !== null ) {
						el.setAttribute( 'data-carousel-original-tabindex', currentTabindex );
					}
					el.setAttribute( 'tabindex', '-1' );
				}
			} );
		} );
	};

	embla.on( 'select', updateSlideAria );
	embla.on( 'init', updateSlideAria );
	updateSlideAria();

	// ── Keyboard navigation ────────────────────────────────────────────────
	// When the carousel wrapper (or any child) has focus, left/right arrow
	// keys navigate between slides. This is additive to any native arrow
	// key handling inside inner blocks.
	wrapper.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'ArrowLeft' ) {
			event.stopPropagation();
			embla.scrollPrev();
		} else if ( event.key === 'ArrowRight' ) {
			event.stopPropagation();
			embla.scrollNext();
		}
	} );

	// Ensure the wrapper is focusable so keydown events fire from keyboard navigation.
	if ( ! wrapper.hasAttribute( 'tabindex' ) ) {
		wrapper.setAttribute( 'tabindex', '0' );
	}
	wrapper.setAttribute( 'role', 'region' );
	wrapper.setAttribute( 'aria-label', 'Posts carousel' );
	wrapper.setAttribute( 'aria-roledescription', 'carousel' );

	// ── Autoplay ───────────────────────────────────────────────────────────
	if ( shouldAutoplay ) {
		const autoplay = createAutoplay( embla, autoplaySpeed, shouldLoop );
		autoplay.play();

		// Pause when the user interacts with the carousel directly.
		wrapper.addEventListener( 'mouseenter', autoplay.stop );
		wrapper.addEventListener( 'focusin', autoplay.stop );

		// Resume when the user leaves.
		wrapper.addEventListener( 'mouseleave', autoplay.play );
		wrapper.addEventListener( 'focusout', ( event ) => {
			// Only resume if focus has left the carousel entirely.
			if ( ! wrapper.contains( event.relatedTarget ) ) {
				autoplay.play();
			}
		} );

		// Stop autoplay when the page is hidden (e.g. tab switch).
		document.addEventListener( 'visibilitychange', () => {
			if ( document.hidden ) {
				autoplay.stop();
			} else {
				autoplay.play();
			}
		} );
	}

	// ── Expose the Embla instance on the DOM element ───────────────────────
	// Allows theme developers / other scripts to access the Embla API directly:
	//   const el = document.querySelector('.wp-block-custom-query-carousel');
	//   el.__embla.scrollNext();
	wrapper.__embla = embla;

	// ── Trigger initialisation callback hook ──────────────────────────────
	// Allow third parties to run code after each carousel is set up.
	if ( window?.wp?.hooks?.doAction ) {
		window.wp.hooks.doAction( 'query_carousel.init', embla, wrapper );
	}
	const onInitCallbacks = window?.queryCarouselHooks?.onInit ?? [];
	onInitCallbacks.forEach( ( cb ) => {
		if ( typeof cb === 'function' ) cb( embla, wrapper );
	} );
}

/* ─────────────────────────────────────────────────────────────────────────────
   6. DOM READY ENTRY POINT
───────────────────────────────────────────────────────────────────────────── */

/**
 * Find every carousel block on the page and initialise them.
 * Runs after the DOM is fully parsed.
 */
function initAllCarousels() {
	const carouselWrappers = document.querySelectorAll(
		'.wp-block-custom-query-carousel'
	);

	if ( carouselWrappers.length === 0 ) {
		return; // No carousels on this page.
	}

	carouselWrappers.forEach( ( wrapper ) => {
		try {
			initCarousel( wrapper );
		} catch ( error ) {
			// Log but don't crash the entire page if one carousel fails.
			console.error( '[Query Carousel] Initialisation error:', error, wrapper );
		}
	} );
}

// Use DOMContentLoaded if the script loads before DOM is ready.
// If the script loads with `defer` (default for viewScript), the DOM
// will already be ready but this guard is kept for robustness.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllCarousels );
} else {
	initAllCarousels();
}
