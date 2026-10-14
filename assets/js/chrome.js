/**
 * Bright Path — page chrome: the scrolled header, back-to-top, and the mobile
 * sticky CTA.
 *
 * OWNER: Main.
 *
 * Design-QA measured ZERO fixed or sticky elements on the built site at every
 * width, against five on the prototype below 1200px. The header's stickiness is
 * CSS (foundation.css 7); these three behaviours are not expressible in CSS and
 * are why this file exists.
 *
 * Everything degrades to nothing without JavaScript: the two floating controls
 * are absent from the markup until this runs, so a page with the script blocked
 * is a page without them rather than a page with two dead buttons.
 */
( function () {
	'use strict';

	var REVEAL_AFTER = 600; // back-to-top threshold (prototype main.js 09)
	var CTA_AFTER = 520;    // mobile CTA bar threshold (prototype main.js 10)

	function onReady( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	function scrolledHeader() {
		var header = document.querySelector( '.brightpath-header__row' );
		if ( ! header ) {
			return null;
		}
		return function () {
			header.classList.toggle( 'is-scrolled', window.scrollY > 8 );
		};
	}

	function backToTop() {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'bp-scroll-top';
		btn.setAttribute( 'aria-label', 'Back to top' );
		btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 19V5M5 12l7-7 7 7"/></svg>';
		btn.addEventListener( 'click', function () {
			// Honour the same preference the reveal animation does.
			var reduce = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
			window.scrollTo( { top: 0, behavior: reduce ? 'auto' : 'smooth' } );
		} );
		document.body.appendChild( btn );

		return function () {
			btn.classList.toggle( 'is-visible', window.scrollY > REVEAL_AFTER );
		};
	}

	function stickyCta() {
		// The CTA mirrors the header's own call to action rather than inventing
		// copy or a destination.
		var source = document.querySelector( '.brightpath-header__cta a' );
		if ( ! source ) {
			return null;
		}

		var SVG = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';
		var bar = document.createElement( 'div' );
		bar.className = 'bp-sticky-cta';
		var link = document.createElement( 'a' );
		link.className = 'bp-sticky-cta__btn bp-sticky-cta__btn--accent';
		// Prototype copy/destination (index.html .sticky-cta), not the header CTA's.
		link.href = '/request-support/';
		link.textContent = 'Request support';
		bar.appendChild( link );

		// Prototype: an icon-only outline button that calls the office.
		var telSrc = document.querySelector( 'a[href^="tel:"]' );
		if ( telSrc ) {
			var num = telSrc.getAttribute( 'href' );
			var tel = document.createElement( 'a' );
			tel.className = 'bp-sticky-cta__btn bp-sticky-cta__btn--outline';
			tel.href = num;
			tel.setAttribute( 'aria-label', 'Call ' + num.replace( 'tel:', '' ).replace( /^(\d{4})(\d{3})(\d{3})$/, '$1 $2 $3' ) );
			tel.innerHTML = SVG + '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
			bar.appendChild( tel );
		}

		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'bp-sticky-cta__close';
		close.setAttribute( 'aria-label', 'Hide this bar' );
		close.innerHTML = SVG + '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
		close.addEventListener( 'click', function () {
			bar.remove();
			try {
				sessionStorage.setItem( 'bpStickyCtaDismissed', '1' );
			} catch ( e ) {
				// Private mode, blocked storage: the dismissal just does not persist.
			}
		} );
		bar.appendChild( close );

		var dismissed = false;
		try {
			dismissed = sessionStorage.getItem( 'bpStickyCtaDismissed' ) === '1';
		} catch ( e ) {}

		if ( dismissed ) {
			return null;
		}

		document.body.appendChild( bar );

		return function () {
			bar.classList.toggle( 'is-visible', window.scrollY > CTA_AFTER );
		};
	}


	/* Escape closes the desktop submenu. core/navigation only closes a submenu
	   on Escape when it was opened by click; one opened by hover or focus
	   stayed open. The close is a class the CSS honours (header CSS 22), so
	   it cannot fight the Interactivity API's own aria-expanded binding; it
	   is lifted as soon as the pointer or focus leaves that item. */
	function escapeClosesSubmenu() {
		var nav = document.querySelector( '.brightpath-header .wp-block-navigation' );
		if ( ! nav ) {
			return;
		}
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' && e.key !== 'Esc' ) {
				return;
			}
			nav.querySelectorAll( '.has-child' ).forEach( function ( li ) {
				var toggle = li.querySelector( ':scope > .wp-block-navigation-submenu__toggle' );
				var open = ( toggle && toggle.getAttribute( 'aria-expanded' ) === 'true' ) || li.matches( ':hover' ) || li.contains( document.activeElement );
				if ( ! open ) {
					return;
				}
				li.classList.add( 'bp-esc-closed' );
				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'false' );
					if ( li.contains( document.activeElement ) ) {
						toggle.focus();
					}
				}
			} );
		} );
		nav.querySelectorAll( '.has-child' ).forEach( function ( li ) {
			var lift = function () { li.classList.remove( 'bp-esc-closed' ); };
			li.addEventListener( 'pointerleave', lift );
			li.addEventListener( 'focusout', function ( e ) {
				if ( ! li.contains( e.relatedTarget ) ) {
					lift();
				}
			} );
			var toggle = li.querySelector( ':scope > .wp-block-navigation-submenu__toggle' );
			if ( toggle ) {
				toggle.addEventListener( 'click', lift );
			}
		} );
	}

	onReady( escapeClosesSubmenu );

	onReady( function () {
		var handlers = [ scrolledHeader(), backToTop(), stickyCta() ].filter( Boolean );
		if ( ! handlers.length ) {
			return;
		}

		var ticking = false;
		function onScroll() {
			if ( ticking ) {
				return;
			}
			ticking = true;
			window.requestAnimationFrame( function () {
				handlers.forEach( function ( fn ) { fn(); } );
				ticking = false;
			} );
		}

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		onScroll(); // set the initial state on a page loaded mid-scroll
	} );
}() );
