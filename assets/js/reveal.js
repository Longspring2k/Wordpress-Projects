/**
 * Bright Path — entrance reveal.
 *
 * The prototype hook is [data-reveal]; the built site uses .bp-reveal, because
 * no core block emits an arbitrary data-* attribute. See BUILD-CONTRACT.md 10.
 *
 * rootMargin and threshold are contract values. Do not round them: the QA
 * pre-pass steps the viewport against exactly these numbers.
 */
( function () {
	'use strict';

	var nodes = document.querySelectorAll( '.bp-reveal' );
	if ( ! nodes.length ) {
		return;
	}

	function revealAll() {
		for ( var i = 0; i < nodes.length; i++ ) {
			nodes[ i ].classList.add( 'is-revealed' );
		}
	}

	var reduced =
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	if ( reduced || typeof window.IntersectionObserver !== 'function' ) {
		revealAll();
		return;
	}

	var observer = new window.IntersectionObserver(
		function ( entries ) {
			for ( var i = 0; i < entries.length; i++ ) {
				if ( entries[ i ].isIntersecting ) {
					entries[ i ].target.classList.add( 'is-revealed' );
					observer.unobserve( entries[ i ].target );
				}
			}
		},
		{
			rootMargin: '0px 0px -8% 0px',
			threshold: 0.08,
		}
	);

	for ( var j = 0; j < nodes.length; j++ ) {
		observer.observe( nodes[ j ] );
	}
} )();
