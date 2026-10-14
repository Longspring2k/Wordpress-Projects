/**
 * Marks the seam between adjacent full-width bands that share a background
 * (see assets/css/band-rhythm.css). Only bands padded with the standard
 * section spacing are touched, so heroes and strips keep their own rhythm.
 */
( function () {
	var WRAPPERS = [ 'entry-content', 'wp-block-post-content', 'bp-content-bands' ];

	function isWrapper( el ) {
		return el.tagName === 'MAIN' || WRAPPERS.some( function ( c ) { return el.classList.contains( c ); } );
	}

	function bands( el ) {
		var out = [];
		Array.prototype.forEach.call( el.children, function ( child ) {
			if ( isWrapper( child ) ) {
				out = out.concat( bands( child ) );
			} else if ( child.offsetHeight > 0 ) {
				out.push( child );
			}
		} );
		return out;
	}

	function background( el ) {
		while ( el ) {
			var c = getComputedStyle( el ).backgroundColor;
			if ( c && c !== 'transparent' && c !== 'rgba(0, 0, 0, 0)' ) {
				return c;
			}
			el = el.parentElement;
		}
		return '';
	}

	function sectionY() {
		var probe = document.createElement( 'div' );
		probe.style.paddingTop = 'var(--wp--preset--spacing--section-y)';
		document.body.appendChild( probe );
		var v = getComputedStyle( probe ).paddingTop;
		probe.remove();
		return parseFloat( v );
	}

	function run() {
		var main = document.querySelector( 'main' );
		if ( ! main ) {
			return;
		}
		var y = sectionY();
		var list = bands( main );
		var close = function ( a, b ) { return Math.abs( a - b ) < 1; };

		for ( var i = 1; i < list.length; i++ ) {
			var a = list[ i - 1 ], b = list[ i ];
			if ( background( a ) !== background( b ) ) {
				continue;
			}
			if ( close( parseFloat( getComputedStyle( a ).paddingBottom ), y ) ) {
				a.classList.add( 'bp-seam-bottom' );
			}
			if ( close( parseFloat( getComputedStyle( b ).paddingTop ), y ) ) {
				b.classList.add( 'bp-seam-top' );
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
