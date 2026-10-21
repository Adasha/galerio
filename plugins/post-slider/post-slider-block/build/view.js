/**
 * Post Slider Block – compiled view script.
 * This is the production-ready frontend script; it requires no bundler.
 * The src/view.js is the source; this file is what WordPress enqueues.
 */
( () => {
	'use strict';

	const ACTIVE = 'is-active';

	class PostSlider {
		constructor( el ) {
			this.el          = el;
			this.track       = el.querySelector( '.psb-track' );
			this.slides      = [ ...el.querySelectorAll( '.psb-slide' ) ];
			this.dots        = [ ...el.querySelectorAll( '.psb-dots__dot' ) ];
			this.prevBtn     = el.querySelector( '.psb-nav__btn--prev' );
			this.nextBtn     = el.querySelector( '.psb-nav__btn--next' );
			this.total       = this.slides.length;
			this.current     = 0;
			this.busy        = false;
			this.transition  = el.dataset.transition || 'fade';
			this.duration    = parseInt( getComputedStyle( el ).getPropertyValue( '--psb-duration' ) ) || 600;
			this.autoplay    = el.dataset.autoplay === 'true';
			this.interval    = parseInt( el.dataset.interval ) || 5000;
			this.pauseHover  = el.dataset.pauseHover !== 'false';
			this._timer      = null;
			if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) this.duration = 0;
			this._bindEvents();
			this._startAutoplay();
		}

		go( index ) {
			if ( this.busy || index === this.current || this.total < 2 ) return;
			this.busy = true;
			const from = this.current;
			const to   = ( ( index % this.total ) + this.total ) % this.total;
			const dir  = to > from ? 'next' : 'prev';
			const fn   = `_t_${ this.transition }`;
			const handler = typeof this[ fn ] === 'function' ? this[ fn ] : this._t_fade;
			handler.call( this, this.slides[ from ], this.slides[ to ], dir, () => {
				this.current = to; this.busy = false;
				this.dots.forEach( ( d, i ) => {
					d.classList.toggle( ACTIVE, i === to );
					d.setAttribute( 'aria-selected', i === to ? 'true' : 'false' );
				} );
				this._resetAutoplay();
			} );
		}

		next() { this.go( this.current + 1 ); }
		prev() { this.go( this.current - 1 ); }

		_activate( to )   { to.classList.add( ACTIVE );    to.setAttribute( 'aria-hidden', 'false' ); }
		_deactivate( fr ) { fr.classList.remove( ACTIVE ); fr.setAttribute( 'aria-hidden', 'true' ); }
		_clear( ...els )  { els.forEach( s => { s.style.cssText = ''; } ); }

		_t_fade( fr, to, _dir, done ) {
			this._activate( to );
			to.style.opacity = '0';
			requestAnimationFrame( () => {
				const d = `${ this.duration }ms ease`;
				to.style.transition = `opacity ${ d }`;
				fr.style.transition = `opacity ${ d }`;
				to.style.opacity = '1'; fr.style.opacity = '0';
				setTimeout( () => { this._deactivate( fr ); this._clear( fr, to ); done(); }, this.duration );
			} );
		}

		_t_slide( fr, to, dir, done ) {
			const s = dir === 'next' ? 1 : -1;
			to.style.transform  = `translateX(${ s * 100 }%)`;
			to.style.transition = 'none';
			this._activate( to );
			requestAnimationFrame( () => requestAnimationFrame( () => {
				const d = `transform ${ this.duration }ms cubic-bezier(.25,.46,.45,.94)`;
				to.style.transition = fr.style.transition = d;
				to.style.transform = 'translateX(0)'; fr.style.transform = `translateX(${ -s * 100 }%)`;
				setTimeout( () => { this._deactivate( fr ); this._clear( fr, to ); done(); }, this.duration );
			} ) );
		}

		_t_zoom( fr, to, _dir, done ) {
			this._activate( to );
			to.style.cssText = 'transform:scale(1.15);opacity:0;transition:none';
			requestAnimationFrame( () => requestAnimationFrame( () => {
				const d = `${ this.duration }ms ease`;
				to.style.transition = fr.style.transition = `transform ${ d }, opacity ${ d }`;
				to.style.transform = 'scale(1)'; to.style.opacity = '1';
				fr.style.transform = 'scale(.88)'; fr.style.opacity = '0';
				setTimeout( () => { this._deactivate( fr ); this._clear( fr, to ); done(); }, this.duration );
			} ) );
		}

		_t_flip( fr, to, dir, done ) {
			const half = this.duration / 2;
			const s    = dir === 'next' ? -1 : 1;
			this.track.style.perspective = '1200px';
			fr.style.transition = `transform ${ half }ms ease-in, opacity ${ half }ms ease-in`;
			fr.style.transform  = `rotateY(${ s * 90 }deg)`; fr.style.opacity = '0';
			setTimeout( () => {
				this._deactivate( fr ); this._clear( fr );
				this._activate( to );
				to.style.cssText = `transform:rotateY(${ -s * 90 }deg);opacity:0;transition:none`;
				requestAnimationFrame( () => requestAnimationFrame( () => {
					to.style.transition = `transform ${ half }ms ease-out, opacity ${ half }ms ease-out`;
					to.style.transform  = 'rotateY(0)'; to.style.opacity = '1';
					setTimeout( () => { this._clear( to ); this.track.style.perspective = ''; done(); }, half );
				} ) );
			}, half );
		}

		_t_cube( fr, to, dir, done ) {
			const s = dir === 'next' ? 1 : -1;
			this.track.style.perspective = '900px';
			this._activate( to );
			const d = `transform ${ this.duration }ms cubic-bezier(.5,0,.5,1), opacity ${ this.duration }ms ease`;
			fr.style.transition = d;
			fr.style.transform  = `translateX(${ -s*50 }%) rotateY(${ s*-90 }deg) translateX(${ -s*50 }%)`; fr.style.opacity = '0';
			to.style.cssText    = `transform:translateX(${ s*50 }%) rotateY(${ s*90 }deg) translateX(${ s*50 }%);opacity:0;transition:none`;
			requestAnimationFrame( () => requestAnimationFrame( () => {
				to.style.transition = d;
				to.style.transform  = 'translateX(0) rotateY(0) translateX(0)'; to.style.opacity = '1';
				setTimeout( () => { this._deactivate( fr ); this._clear( fr, to ); this.track.style.perspective = ''; done(); }, this.duration );
			} ) );
		}

		_t_blur( fr, to, _dir, done ) {
			this._activate( to );
			to.style.cssText = 'filter:blur(20px);opacity:0;transform:scale(1.04);transition:none';
			requestAnimationFrame( () => requestAnimationFrame( () => {
				const d = `${ this.duration }ms ease`;
				to.style.transition = fr.style.transition = `filter ${ d }, opacity ${ d }, transform ${ d }`;
				to.style.filter = 'blur(0)'; to.style.opacity = '1'; to.style.transform = 'scale(1)';
				fr.style.filter = 'blur(20px)'; fr.style.opacity = '0'; fr.style.transform = 'scale(.96)';
				setTimeout( () => { this._deactivate( fr ); this._clear( fr, to ); done(); }, this.duration );
			} ) );
		}

		_startAutoplay()  { if ( this.autoplay ) this._timer = setInterval( () => this.next(), this.interval ); }
		_stopAutoplay()   { clearInterval( this._timer ); }
		_resetAutoplay()  { if ( this.autoplay ) { this._stopAutoplay(); this._startAutoplay(); } }

		_bindEvents() {
			this.prevBtn?.addEventListener( 'click', () => this.prev() );
			this.nextBtn?.addEventListener( 'click', () => this.next() );
			this.dots.forEach( d => d.addEventListener( 'click', () => this.go( parseInt( d.dataset.index ) ) ) );
			this.el.setAttribute( 'tabindex', '0' );
			this.el.addEventListener( 'keydown', e => {
				if ( e.key === 'ArrowLeft'  ) { e.preventDefault(); this.prev(); }
				if ( e.key === 'ArrowRight' ) { e.preventDefault(); this.next(); }
			} );
			if ( this.pauseHover && this.autoplay ) {
				this.el.addEventListener( 'mouseenter', () => this._stopAutoplay() );
				this.el.addEventListener( 'mouseleave', () => this._startAutoplay() );
				this.el.addEventListener( 'focusin',    () => this._stopAutoplay() );
				this.el.addEventListener( 'focusout',   () => this._startAutoplay() );
			}
			let tx = 0;
			this.el.addEventListener( 'touchstart', e => { tx = e.changedTouches[0].clientX; }, { passive: true } );
			this.el.addEventListener( 'touchend',   e => {
				const diff = tx - e.changedTouches[0].clientX;
				if ( Math.abs( diff ) > 50 ) diff > 0 ? this.next() : this.prev();
			}, { passive: true } );
		}
	}

	function initAll() {
		document.querySelectorAll( '.psb-slider:not([data-psb-init])' ).forEach( el => {
			el.dataset.psbInit = '1';
			new PostSlider( el );
		} );
	}

	document.readyState === 'loading'
		? document.addEventListener( 'DOMContentLoaded', initAll )
		: initAll();

	window.addEventListener( 'psb:reinit', initAll );
} )();
