/**
 * Mobile menu overlay: adds the prototype's brand head, collapsible submenus
 * and the two CTA buttons to core/navigation's responsive overlay.
 */
( function () {
	var PHONE_ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
	var CHEVRON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="6 9 12 15 18 9"/></svg>';

	function init( container ) {
		if ( container.dataset.bpMobileNav ) {
			return;
		}
		container.dataset.bpMobileNav = '1';
		container.classList.add( 'bp-mobile-nav' );

		var dialog = container.querySelector( '.wp-block-navigation__responsive-dialog' );
		var content = container.querySelector( '.wp-block-navigation__responsive-container-content' );
		if ( ! dialog || ! content ) {
			return;
		}

		// Head: the site brand, next to core's close button.
		var brand = document.querySelector( '.brightpath-header .bp-brand' );
		if ( brand ) {
			var head = document.createElement( 'div' );
			head.className = 'bp-mobile-nav__head';
			head.appendChild( brand.cloneNode( true ) );
			dialog.insertBefore( head, dialog.firstChild );
		}

		// Submenus: collapsed by default, a full-width row toggles each one.
		content.querySelectorAll( '.wp-block-navigation-item.has-child' ).forEach( function ( item, i ) {
			var link = item.querySelector( ':scope > .wp-block-navigation-item__content' );
			var sub = item.querySelector( ':scope > .wp-block-navigation__submenu-container' );
			if ( ! link || ! sub ) {
				return;
			}
			var id = sub.id || ( 'bp-mobile-sub-' + i );
			sub.id = id;
			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'bp-mobile-nav__toggle';
			btn.setAttribute( 'aria-expanded', 'false' );
			btn.setAttribute( 'aria-controls', id );
			btn.innerHTML = '<span></span>' + CHEVRON;
			btn.firstChild.textContent = link.textContent.trim();
			btn.addEventListener( 'click', function () {
				var open = btn.getAttribute( 'aria-expanded' ) !== 'true';
				btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				item.classList.toggle( 'bp-sub-open', open );
			} );
			item.insertBefore( btn, link );
		} );

		// Foot: the same two actions the prototype sheet ends with.
		var foot = document.createElement( 'div' );
		foot.className = 'bp-mobile-nav__foot';
		foot.innerHTML =
			'<a class="bp-mobile-nav__btn bp-mobile-nav__btn--accent" href="/request-support/">Request support</a>' +
			'<a class="bp-mobile-nav__btn bp-mobile-nav__btn--outline" href="tel:1300555018">' + PHONE_ICON + ' Call 1300 555 018</a>';
		content.appendChild( foot );
	}

	function run() {
		document.querySelectorAll( '.brightpath-header .wp-block-navigation__responsive-container' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
