/**
 * Bright Path — client-side archive filters.
 *
 * OWNER: Main.
 *
 * The user's decision (BUILD-CONTRACT.md §0.5) is that filtering stays
 * client-side over rendered DOM, as the prototype does it. This script is the
 * half that makes the controls do something; without it an archive renders its
 * filters and quietly changes nothing, which passes every structural check.
 *
 * The contract it implements is §16, and its whole point is that there is no
 * bespoke data contract to keep in sync: `get_post_class()` already emits
 * `{taxonomy}-{term-slug}` on every post-template item, so the items describe
 * their own axes. The controls name an axis by taxonomy name and a value by
 * term slug. Nothing here needs to know what "region" or "availability" mean.
 *
 *   items    <li class="sil_property sil_region-penrith sil_type-shared ...">
 *   select   <select class="bp-filter__axis" name="sil_region">
 *              <option value="">All regions</option>
 *              <option value="penrith">Penrith</option>
 *   chips    <button class="bp-filter__axis" data-axis="resource_category"
 *                    value="guides" aria-pressed="false">
 *
 * An item is shown when, for every axis with a non-empty value, it carries
 * `<taxonomy>-<term-slug>`.
 */
( function () {
	'use strict';

	/**
	 * Hide by the `hidden` attribute AND by inline display.
	 *
	 * `hidden` alone is defeated by any rule that sets `display` on the element
	 * -- and `core/post-template` items are flex or grid children, which is
	 * exactly that case. A filter that sets `hidden` and moves nothing on the
	 * page is a known failure shape on this project: a sibling check once read
	 * back the attribute it had just written and passed while the page was
	 * unchanged.
	 */
	function setShown( el, shown ) {
		if ( shown ) {
			el.removeAttribute( 'hidden' );
			el.style.removeProperty( 'display' );
		} else {
			el.setAttribute( 'hidden', '' );
			el.style.setProperty( 'display', 'none', 'important' );
		}
	}

	/** The item list a control group governs: the next post-template in document order. */
	function findItems( form ) {
		var all = Array.prototype.slice.call(
			document.querySelectorAll( '.wp-block-post-template, .bp-filterable' )
		);
		var after = all.filter( function ( list ) {
			return form.compareDocumentPosition( list ) & Node.DOCUMENT_POSITION_FOLLOWING;
		} );
		var list = after[ 0 ] || all[ 0 ];
		return list ? Array.prototype.slice.call( list.children ) : [];
	}

	function axisName( control ) {
		return control.getAttribute( 'data-axis' ) || control.getAttribute( 'name' ) || '';
	}

	function activeFilters( form ) {
		var out = {};

		form.querySelectorAll( 'select.bp-filter__axis' ).forEach( function ( sel ) {
			if ( sel.value ) {
				out[ axisName( sel ) ] = sel.value;
			}
		} );

		form.querySelectorAll( 'button.bp-filter__axis[aria-pressed="true"]' ).forEach( function ( chip ) {
			out[ axisName( chip ) ] = chip.value || chip.getAttribute( 'data-value' ) || '';
		} );

		return out;
	}

	function apply( form, items, count, empty ) {
		var filters = activeFilters( form );
		var axes = Object.keys( filters );
		var shownCount = 0;

		items.forEach( function ( item ) {
			var shown = axes.every( function ( axis ) {
				return item.classList.contains( axis + '-' + filters[ axis ] );
			} );
			setShown( item, shown );
			if ( shown ) {
				shownCount++;
			}
		} );

		if ( count ) {
			count.textContent = shownCount === items.length
				? String( items.length ) + ( items.length === 1 ? ' home' : ' results' )
				: String( shownCount ) + ' of ' + items.length;
		}

		if ( empty ) {
			setShown( empty, shownCount === 0 && items.length > 0 );
		}
	}

	function init( form ) {
		var items = findItems( form );
		if ( ! items.length ) {
			return;
		}

		// The empty-state element is a sibling of the form, not a child of it.
		var scope = form.parentElement || document;
		var count = form.querySelector( '.bp-filter__count' ) || scope.querySelector( '.bp-filter__count' );
		var empty = scope.querySelector( '.bp-filter__empty' );
		var run   = function () { apply( form, items, count, empty ); };

		form.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'bp-filter__axis' ) ) {
				run();
			}
		} );

		form.addEventListener( 'click', function ( e ) {
			var chip = e.target.closest( 'button.bp-filter__axis' );
			if ( chip ) {
				e.preventDefault();
				var axis = axisName( chip );
				var on   = chip.getAttribute( 'aria-pressed' ) === 'true';
				// One value per axis: pressing a chip releases its siblings.
				form.querySelectorAll( 'button.bp-filter__axis' ).forEach( function ( other ) {
					if ( axisName( other ) === axis ) {
						other.setAttribute( 'aria-pressed', 'false' );
					}
				} );
				chip.setAttribute( 'aria-pressed', on ? 'false' : 'true' );
				run();
				return;
			}

			if ( e.target.closest( '.bp-filter__reset' ) ) {
				e.preventDefault();
				form.querySelectorAll( 'select.bp-filter__axis' ).forEach( function ( sel ) { sel.value = ''; } );
				form.querySelectorAll( 'button.bp-filter__axis' ).forEach( function ( chip ) {
					chip.setAttribute( 'aria-pressed', 'false' );
				} );
				run();
			}
		} );

		run(); // paint the count on load, with nothing filtered
	}

	/**
	 * Mobile (< 768px): start the filter panel collapsed so the first home is
	 * near the top. The toggle click itself is handled in parity.js
	 * (filterToggle). Without JS the panel stays open (markup default).
	 */
	function collapseOnMobile() {
		if ( ! window.matchMedia || ! window.matchMedia( '(max-width: 767px)' ).matches ) {
			return;
		}
		document.querySelectorAll( '.bp-filter__toggle[aria-controls]' ).forEach( function ( btn ) {
			var body = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			if ( ! body ) { return; }
			btn.setAttribute( 'aria-expanded', 'false' );
			body.hidden = true;
		} );
	}

	function boot() {
		collapseOnMobile();
		document.querySelectorAll( '.bp-filter' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
