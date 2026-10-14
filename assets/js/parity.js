/**
 * Bright Path — design parity behaviours (theme agent C).
 *  1. FAQ search: filters accordion items on pages with a long FAQ, with a
 *     live count and an empty state.
 *  2. SIL filter toggle: "Show or hide filters".
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) { fn(); } else { document.addEventListener( 'DOMContentLoaded', fn ); }
	}

	function faqSearch() {
		var items = Array.prototype.slice.call( document.querySelectorAll( 'main .wp-block-accordion-item' ) );
		if ( items.length < 10 || document.querySelector( '[data-faq-search]' ) ) { return; }
		var first = items[ 0 ].closest( '.wp-block-accordion' );
		var host = first.parentElement;
		// Insert above the first FAQ group heading when there is one.
		var anchor = first;
		var prev = first.previousElementSibling;
		if ( prev && /^H2$/.test( prev.tagName ) ) { anchor = prev; }

		var wrap = document.createElement( 'div' );
		wrap.className = 'field bp-faq-search';
		wrap.innerHTML = '<label class="field__label" for="faq-search">Search questions</label>' +
			'<input class="input" type="search" id="faq-search" data-faq-search placeholder="Try &quot;rent&quot;, &quot;cancellation&quot; or &quot;worker&quot;" aria-describedby="faq-search-status">' +
			'<p class="field__hint" id="faq-search-status" role="status"><strong data-faq-count>' + items.length + '</strong> questions shown</p>';
		var empty = document.createElement( 'div' );
		empty.className = 'empty-state bp-faq-empty';
		empty.hidden = true;
		empty.innerHTML = '<h2 class="empty-state__title">No questions match that search</h2>' +
			'<p class="empty-state__text">Try a different word, or ask us directly — we would rather answer it properly than have you guess.</p>' +
			'<p><a class="wp-element-button" href="/contact/">Ask us directly</a></p>';

		// Put the search at the top of the section that holds the FAQ groups.
		// Design (faq.html): search sits above the chip row.
		var chips = anchor.parentNode && anchor.parentNode.querySelector( ':scope > .chip-row' );
		if ( chips && ( chips.compareDocumentPosition( anchor ) & Node.DOCUMENT_POSITION_FOLLOWING ) ) { anchor = chips; }
		var section = anchor.closest( '.bp-slot, .wp-block-post-content, main' ) || host;
		var firstChild = section === host ? anchor : section.firstElementChild;
		if ( firstChild && firstChild.classList && firstChild.classList.contains( 'wp-block-breadcrumbs' ) ) { firstChild = anchor; }
		anchor.parentNode.insertBefore( wrap, anchor );
		wrap.parentNode.insertBefore( empty, wrap.nextSibling );

		var input = wrap.querySelector( 'input' );
		var count = wrap.querySelector( '[data-faq-count]' );
		input.addEventListener( 'input', function () {
			var q = input.value.trim().toLowerCase();
			var shown = 0;
			items.forEach( function ( it ) {
				var ok = ! q || it.textContent.toLowerCase().indexOf( q ) !== -1;
				it.hidden = ! ok;
				it.style.display = ok ? '' : 'none';
				if ( ok ) { shown++; }
			} );
			// Hide group headings / accordions with nothing left in them.
			document.querySelectorAll( 'main .wp-block-accordion' ).forEach( function ( acc ) {
				var any = acc.querySelector( '.wp-block-accordion-item:not([hidden])' );
				acc.style.display = any ? '' : 'none';
				var h = acc.previousElementSibling;
				if ( h && h.tagName === 'H2' ) { h.style.display = any ? '' : 'none'; }
			} );
			count.textContent = shown;
			empty.hidden = shown !== 0;
		} );
	}

	function filterToggle() {
		document.querySelectorAll( '.bp-filter__toggle' ).forEach( function ( btn ) {
			var body = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			if ( ! body ) { return; }
			btn.addEventListener( 'click', function () {
				var open = btn.getAttribute( 'aria-expanded' ) === 'true';
				btn.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
				body.hidden = open;
			} );
		} );
	}

	function chipState() {
		document.querySelectorAll( '[data-bp-chips] a' ).forEach( function ( a ) {
			var path = new URL( a.href, location.href ).pathname;
			if ( path === location.pathname ) { a.setAttribute( 'aria-current', 'page' ); a.setAttribute( 'aria-pressed', 'true' ); }
		} );
	}

	ready( function () { faqSearch(); filterToggle(); chipState(); } );
}() );
