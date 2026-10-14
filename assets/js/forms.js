/*
 * Bright Path -- forms.
 *
 * OWNER: the forms track. Enqueued from inc/forms.php (there is no script-shard
 * list the way there is a style-shard list), deferred so the fields it binds to
 * are parsed before it runs.
 *
 * THIS SCRIPT IS AN ENHANCEMENT, NOT THE VALIDATOR. Every rule it applies is
 * applied again on the server against the same field schema. With JavaScript
 * off, every step is visible, nothing is hidden, and the form submits and
 * validates normally. Nothing here is load-bearing for correctness or for
 * privacy -- the anonymous-feedback rule is enforced server-side as well,
 * because a rule that only a script enforces is a rule an attacker declines.
 *
 * The one thing that IS only here is the session draft (see "session drafts"
 * below). It is an enhancement too: with the script off the referral behaves
 * exactly as it did before drafts existed.
 */
( function () {
	'use strict';

	/* =================================================================
	 * Session drafts
	 *
	 * Only a form that inc/forms.php opts in gets one, and today that is the
	 * referral alone -- 44 fields, seven steps, and the worst thing on this
	 * site to lose to a mis-tapped back button.
	 * ================================================================= */

	var DRAFT_PREFIX  = 'bpDraft:';
	var DRAFT_VERSION = 1;
	var DRAFT_MAX_AGE = 12 * 60 * 60 * 1000;

	/*
	 * sessionStorage, NOT localStorage, and that is a privacy decision rather
	 * than a convenience one. A session draft dies when the tab closes, which
	 * is the nearest thing the platform offers to "do not keep this" -- and a
	 * referral is routinely filled in on a shared clinic or library machine.
	 *
	 * Wrapped in try/catch because storage THROWS rather than returning null
	 * when it is disabled, partitioned or out of quota. An exception raised
	 * here would abort the whole IIFE and take validation, the stepper and the
	 * anonymous-feedback rule down with it.
	 */
	function store() {
		try {
			var s = window.sessionStorage;
			s.setItem( DRAFT_PREFIX + '_probe', '1' );
			s.removeItem( DRAFT_PREFIX + '_probe' );
			return s;
		} catch ( e ) {
			return null;
		}
	}

	function dropDraft( slug ) {
		var s = store();
		if ( ! s || ! slug ) {
			return;
		}
		try {
			s.removeItem( DRAFT_PREFIX + slug );
		} catch ( e ) {}
	}

	function readDraft( slug ) {
		var s = store();
		if ( ! s || ! slug ) {
			return null;
		}

		var raw;
		try {
			raw = s.getItem( DRAFT_PREFIX + slug );
		} catch ( e ) {
			return null;
		}
		if ( ! raw ) {
			return null;
		}

		var d;
		try {
			d = JSON.parse( raw );
		} catch ( e ) {
			dropDraft( slug );
			return null;
		}

		// A draft whose shape or age no longer matches is DROPPED, never
		// half-applied. A partly-understood clinical record restored into a
		// form is worse than no draft at all, because it looks complete.
		if ( ! d || d.v !== DRAFT_VERSION || ! d.values || typeof d.values !== 'object' ) {
			dropDraft( slug );
			return null;
		}
		if ( ! d.at || ( Date.now() - d.at ) > DRAFT_MAX_AGE ) {
			dropDraft( slug );
			return null;
		}
		if ( ! Object.keys( d.values ).length ) {
			dropDraft( slug );
			return null;
		}

		return d;
	}

	/*
	 * A successful submit is the ONE place a draft is dropped.
	 *
	 * It has to be the success panel rather than the submit event: a submission
	 * that fails validation on the server comes back as a re-rendered form with
	 * the visitor's answers and no success panel, and that is precisely the
	 * case the draft exists for. Clearing on submit would throw the draft away
	 * at the moment it is most needed.
	 */
	var success = document.querySelector( '[data-form-success]' );

	if ( success ) {
		dropDraft( success.getAttribute( 'data-form-success' ) );

		/*
		 * The visitor has just been redirected and needs to be told what
		 * happened without hunting for it.
		 *
		 * This used to live at the very END of this file, AFTER the
		 * `forms.length` early return below -- and a success page has no form
		 * on it, so the script always returned before reaching it and the
		 * panel was never announced or focused. Observed on the live site, not
		 * assumed. Moving it above the guard is what makes it run at all.
		 */
		/*
		 * Focused TWICE, deliberately.
		 *
		 * This script is deferred, so it runs before the document finishes
		 * loading. The first focus() genuinely lands -- a focusin fires on the
		 * panel -- and then the browser resets focus to <body> about a
		 * millisecond later when loading completes, with a focusout whose
		 * relatedTarget is null and no blur() anywhere in our code. Measured on
		 * the live success page by instrumenting HTMLElement.prototype.focus,
		 * not reasoned about: the panel is visible, has tabindex="-1", has no
		 * hidden ancestor, and re-focusing it by hand afterwards works.
		 *
		 * So the first call covers the case where loading is already complete,
		 * and the 'load' listener re-asserts it when it is not. Without the
		 * second call the announcement is silently lost every time.
		 */
		var focusSuccess = function () {
			success.focus();
		};

		focusSuccess();

		if ( 'complete' !== document.readyState ) {
			window.addEventListener( 'load', focusSuccess, { once: true } );
		}
	}

	var forms = document.querySelectorAll( '[data-bp-form]' );

	if ( ! forms.length ) {
		return;
	}

	/* ---------- helpers ---------- */

	function errorNode( field ) {
		return field.querySelector( '.field__error-text' );
	}

	function controls( field ) {
		return field.querySelectorAll( 'input, select, textarea' );
	}

	function labelFor( field ) {
		var l = field.querySelector( '.field__label, legend, .check__text' );
		if ( ! l ) {
			return 'this field';
		}
		// Strip the "*" the required marker adds, so a message never reads
		// 'Please enter "Email *"'.
		return l.textContent.replace( /\s*\*\s*$/, '' ).trim();
	}

	function setError( field, message ) {
		var node = errorNode( field );
		field.classList.toggle( 'field--invalid', !! message );

		controls( field ).forEach( function ( c ) {
			if ( message ) {
				c.setAttribute( 'aria-invalid', 'true' );
			} else {
				c.removeAttribute( 'aria-invalid' );
			}
		} );

		if ( node ) {
			node.textContent = message || '';
		}

		var p = field.querySelector( '.field__error' );
		if ( p ) {
			p.classList.toggle( 'is-visible', !! message );
		}
	}

	/* ---------- validation, mirroring the server's rules ---------- */

	function validateField( field ) {
		if ( field.hidden ) {
			return null;
		}

		var cs       = controls( field );
		var first    = cs[ 0 ];
		if ( ! first ) {
			return null;
		}

		var label    = labelFor( field );
		var type     = first.type;
		var required = first.hasAttribute( 'required' );
		var group    = field.querySelector( 'fieldset' );

		// radio / checkbox group
		if ( group ) {
			var checked = field.querySelectorAll( 'input:checked' ).length;
			var total   = field.querySelectorAll( 'input' ).length;
			var isConsent = first.name.indexOf( 'consent' ) === 0;

			if ( isConsent && checked < total ) {
				return 'Please confirm all three consent statements to continue.';
			}
			if ( required && ! checked ) {
				return 'Please choose an option for "' + label + '".';
			}
			return null;
		}

		if ( type === 'checkbox' ) {
			if ( required && ! first.checked ) {
				return 'Please tick "' + label + '" to continue.';
			}
			return null;
		}

		if ( type === 'file' ) {
			return null;
		}

		var value = ( first.value || '' ).trim();

		if ( required && ! value ) {
			if ( type === 'email' ) {
				return 'Please enter an email address so we can reply.';
			}
			if ( first.tagName === 'SELECT' ) {
				return 'Please choose an option for "' + label + '".';
			}
			if ( first.tagName === 'TEXTAREA' ) {
				return 'Please tell us about "' + label + '".';
			}
			return 'Please enter "' + label + '".';
		}

		if ( value && type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			return 'That email address does not look right. Please check it.';
		}

		if ( value && first.pattern && ! new RegExp( '^(?:' + first.pattern + ')$' ).test( value ) ) {
			return 'Please check "' + label + '".';
		}

		return null;
	}

	/* ---------- the error summary, which must RECEIVE FOCUS ---------- */

	function showSummary( form, failures ) {
		var wrap = form.closest( '.bp-form-wrap' ) || form;
		var box  = form.querySelector( '[data-form-summary]' );

		if ( ! box ) {
			box = document.createElement( 'div' );
			box.className = 'form-summary';
			box.setAttribute( 'role', 'alert' );
			box.setAttribute( 'tabindex', '-1' );
			box.setAttribute( 'data-form-summary', '' );
			form.insertBefore( box, form.firstChild );
		}

		var n = failures.length;
		var title = n === 1
			? 'There is 1 thing to fix before this can be sent.'
			: 'There are ' + n + ' things to fix before this can be sent.';

		box.innerHTML = '';

		var p = document.createElement( 'p' );
		p.className = 'form-summary__title';
		p.textContent = title;
		box.appendChild( p );

		var ul = document.createElement( 'ul' );
		ul.className = 'form-summary__list';

		failures.forEach( function ( f ) {
			var li = document.createElement( 'li' );
			var a  = document.createElement( 'a' );
			a.href = '#' + f.id;
			a.textContent = f.message;
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var target = document.getElementById( f.id );
				if ( target ) {
					// Reveal the step the field lives on before focusing it,
					// or focus lands on a display:none element and goes to
					// <body> instead.
					var step = target.closest( '.form-step' );
					if ( step ) {
						gotoStep( form, parseInt( step.getAttribute( 'data-step-index' ), 10 ) );
					}
					target.focus();
				}
			} );
			li.appendChild( a );
			ul.appendChild( li );
		} );

		box.appendChild( ul );

		// THE point of the summary. Not "the summary exists" -- focus must
		// actually land on it, or a screen reader user is told nothing and a
		// keyboard user is left where they were.
		box.focus();
	}

	function clearSummary( form ) {
		var box = form.querySelector( '[data-form-summary]' );
		if ( box && ! box.classList.contains( 'form-summary--fatal' ) ) {
			box.remove();
		}
	}

	/* ---------- dependent fields ---------- */

	/*
	 * A dependent field whose condition is not met is hidden AND its controls
	 * are DISABLED. Disabled is the load-bearing half: a disabled control is
	 * not submitted at all, so choosing "anonymous" means the name, email and
	 * phone never leave the browser. Hiding alone would still post them.
	 */
	function applyDependencies( form ) {
		form.querySelectorAll( '.field[data-depends]' ).forEach( function ( field ) {
			var name = field.getAttribute( 'data-depends' );
			var want = field.getAttribute( 'data-depends-value' );
			var src  = form.querySelector( '[name="' + name + '"]:checked' ) || form.querySelector( 'select[name="' + name + '"]' );
			var have = src ? src.value : '';
			var on   = have === want;

			field.hidden = ! on;

			controls( field ).forEach( function ( c ) {
				c.disabled = ! on;
				if ( ! on ) {
					if ( c.type === 'checkbox' || c.type === 'radio' ) {
						c.checked = false;
					} else {
						c.value = '';
					}
				}
			} );

			if ( ! on ) {
				setError( field, null );
			}
		} );
	}

	/* ---------- the stepper ---------- */

	function steps( form ) {
		return Array.prototype.slice.call( form.querySelectorAll( '.form-step' ) );
	}

	function gotoStep( form, index ) {
		var all = steps( form );
		if ( ! all.length ) {
			return;
		}

		index = Math.max( 1, Math.min( index, all.length ) );

		all.forEach( function ( s ) {
			s.classList.toggle( 'is-active', parseInt( s.getAttribute( 'data-step-index' ), 10 ) === index );
		} );

		var text = form.querySelector( '[data-stepper-text]' );
		if ( text ) {
			text.textContent = 'Step ' + index + ' of ' + all.length;
		}

		var fill = form.querySelector( '[data-stepper-fill]' );
		if ( fill ) {
			fill.style.inlineSize = ( index / all.length * 100 ) + '%';
		}

		form.querySelectorAll( '[data-stepper-dot]' ).forEach( function ( dot, i ) {
			dot.classList.toggle( 'is-current', i + 1 === index );
			dot.classList.toggle( 'is-done', i + 1 < index );
		} );

		form.setAttribute( 'data-current-step', index );
	}

	function validateStep( form, index ) {
		var step = form.querySelector( '.form-step[data-step-index="' + index + '"]' );
		if ( ! step ) {
			return true;
		}

		var failures = [];

		step.querySelectorAll( '.field' ).forEach( function ( field ) {
			var message = validateField( field );
			setError( field, message );

			if ( message ) {
				var c = controls( field )[ 0 ];
				failures.push( { id: c ? c.id : '', message: message } );
			}
		} );

		if ( failures.length ) {
			showSummary( form, failures );
			return false;
		}

		clearSummary( form );
		return true;
	}

	/* ---------- uploads: list the chosen files ---------- */

	function bindUploads( form ) {
		form.querySelectorAll( '[data-upload]' ).forEach( function ( box ) {
			var input = box.querySelector( 'input[type="file"]' );
			var list  = box.querySelector( '[data-upload-list]' );

			if ( ! input || ! list ) {
				return;
			}

			function render() {
				var names = Array.prototype.map.call( input.files || [], function ( f ) {
					return f.name + ' (' + Math.round( f.size / 1024 ) + ' KB)';
				} );
				list.textContent = names.length ? names.join( ', ' ) : '';
			}

			input.addEventListener( 'change', render );

			[ 'dragenter', 'dragover' ].forEach( function ( e ) {
				box.addEventListener( e, function ( ev ) {
					ev.preventDefault();
					box.classList.add( 'is-dragover' );
				} );
			} );

			[ 'dragleave', 'drop' ].forEach( function ( e ) {
				box.addEventListener( e, function ( ev ) {
					ev.preventDefault();
					box.classList.remove( 'is-dragover' );
				} );
			} );

			box.addEventListener( 'drop', function ( ev ) {
				if ( ev.dataTransfer && ev.dataTransfer.files.length ) {
					input.files = ev.dataTransfer.files;
					render();
				}
			} );
		} );
	}

	/* ---------- the session draft ---------- */

	/*
	 * Which controls may be written to a draft, and why each exclusion exists.
	 *
	 *   [data-field-enc]  Decision 8 encrypts these at rest and keeps them out
	 *                     of the notification email. sessionStorage is
	 *                     PLAINTEXT, readable by any script on the origin and
	 *                     written to disk by browsers that restore sessions --
	 *                     so persisting them would route health data straight
	 *                     around the encryption posture. inc/forms.php emits
	 *                     the marker from the schema, so this list can never
	 *                     drift from the schema the way a copy here would.
	 *   type=file         A File cannot be serialised, and a page is not
	 *                     allowed to re-attach one to an input anyway.
	 *   type=hidden       Nothing in a hidden input is an ANSWER. It is the
	 *                     nonce, the timing token, the redirect target, the
	 *                     honeypot, or a value the server derived from the
	 *                     record (the SIL property, the job title). Restoring a
	 *                     stale nonce or timing token would make a recovered
	 *                     draft fail the spam checks and look like a broken
	 *                     form. MEASURED: without this rule, wp_nonce_field's
	 *                     own `_wp_http_referer` input ended up in the draft --
	 *                     which also meant an EMPTY form still wrote a
	 *                     non-empty draft, so Discard never really discarded.
	 *   name^=bp_ or _    Defence in depth for the same set, in case one of
	 *                     them ever stops being a hidden input.
	 *   name^=consent     Consent is an act, not an answer. Re-ticking it on
	 *                     someone's behalf from storage would be a consent
	 *                     record nobody actually gave.
	 *   disabled          A dependent field currently switched off. The
	 *                     anonymous-feedback rule clears AND disables these;
	 *                     a draft must not resurrect what that rule removed.
	 */
	function draftable( c, allowDisabled ) {
		if ( ! c.name ) {
			return false;
		}
		if ( ! allowDisabled && c.disabled ) {
			return false;
		}
		if ( c.type === 'file' || c.type === 'hidden' ) {
			return false;
		}
		if ( c.name.indexOf( 'bp_' ) === 0
			|| c.name.indexOf( '_' ) === 0
			|| c.name.indexOf( 'consent' ) === 0 ) {
			return false;
		}
		return ! ( c.closest && c.closest( '.field[data-field-enc]' ) );
	}

	function draftControls( form, allowDisabled ) {
		return Array.prototype.filter.call(
			form.querySelectorAll( 'input, select, textarea' ),
			function ( c ) { return draftable( c, allowDisabled ); }
		);
	}

	function collectDraft( form ) {
		var values = {};

		draftControls( form, false ).forEach( function ( c ) {
			if ( c.type === 'checkbox' || c.type === 'radio' ) {
				if ( ! c.checked ) {
					return;
				}
				if ( c.name.slice( -2 ) === '[]' ) {
					values[ c.name ] = ( values[ c.name ] || [] ).concat( [ c.value ] );
				} else {
					values[ c.name ] = c.value;
				}
			} else if ( c.value !== '' ) {
				values[ c.name ] = c.value;
			}
		} );

		return values;
	}

	function applyDraft( form, values ) {
		// allowDisabled: a dependent control may still be switched off at this
		// instant. Fill it, then let applyDependencies() decide -- doing it the
		// other way round drops answers the visitor really did give.
		draftControls( form, true ).forEach( function ( c ) {
			if ( ! Object.prototype.hasOwnProperty.call( values, c.name ) ) {
				return;
			}
			var v = values[ c.name ];
			if ( c.type === 'checkbox' || c.type === 'radio' ) {
				c.checked = Array.isArray( v ) ? v.indexOf( c.value ) !== -1 : String( v ) === c.value;
			} else {
				c.value = v;
			}
		} );
	}

	// The labels of the fields the draft deliberately did NOT keep. The banner
	// names them, because "we restored your draft" while seven answers are
	// quietly blank is a worse lie than not restoring at all.
	function withheldLabels( form ) {
		return Array.prototype.map.call(
			form.querySelectorAll( '.field[data-field-enc]' ),
			function ( f ) { return labelFor( f ); }
		);
	}

	function ago( t ) {
		var m = Math.round( ( Date.now() - t ) / 60000 );
		if ( m < 1 ) { return 'less than a minute ago'; }
		if ( m === 1 ) { return 'a minute ago'; }
		if ( m < 60 ) { return m + ' minutes ago'; }
		var h = Math.round( m / 60 );
		return h === 1 ? 'about an hour ago' : 'about ' + h + ' hours ago';
	}

	/*
	 * The banner styles itself from the theme's existing custom properties
	 * rather than from a class.
	 *
	 * `.form-summary` was the obvious class to reuse and is the WRONG one: it
	 * is painted in --wp--custom--status--error, and a recovered draft is not
	 * an error. The permanent home for these rules is assets/css/forms.css,
	 * which this change was scoped out of; every value below is a token, so
	 * moving them there later is a copy, not a redesign.
	 */
	function draftStyle( el, css ) {
		el.style.cssText = css;
		return el;
	}

	function draftButton( label, primary ) {
		var b = document.createElement( 'button' );
		b.type = 'button';
		b.textContent = label;
		return draftStyle( b,
			'font: inherit;' +
			'cursor: pointer;' +
			'padding: var(--wp--preset--spacing--10) var(--wp--preset--spacing--30);' +
			'border-radius: var(--wp--custom--radius--sm);' +
			'border: 2px solid var(--wp--custom--color--info);' +
			( primary
				? 'background: var(--wp--custom--color--info); color: var(--wp--custom--color--text-invert);'
				: 'background: transparent; color: var(--wp--custom--color--info);' )
		);
	}

	function bindDraft( form ) {
		var slug = form.getAttribute( 'data-draft-scope' );

		if ( ! slug || ! store() ) {
			return;
		}

		var pending = readDraft( slug );
		var timer   = null;

		function write() {
			var s = store();
			if ( ! s ) {
				return;
			}

			/*
			 * While a recovered draft is on screen and unresolved, NOTHING is
			 * written. Writing here would overwrite the only copy of the thing
			 * the banner is currently offering -- which is exactly the
			 * data-loss the draft exists to prevent, caused by the draft.
			 *
			 * No buffer is needed to make that safe: the answers the visitor
			 * types meanwhile are in the DOM, and resolve() snapshots the DOM.
			 */
			if ( pending ) {
				return;
			}

			var values = collectDraft( form );

			if ( ! Object.keys( values ).length ) {
				dropDraft( slug );
				return;
			}

			try {
				s.setItem( DRAFT_PREFIX + slug, JSON.stringify( {
					v:      DRAFT_VERSION,
					slug:   slug,
					at:     Date.now(),
					step:   parseInt( form.getAttribute( 'data-current-step' ), 10 ) || 1,
					values: values
				} ) );
			} catch ( e ) {
				// Quota or a disabled store. A draft is a nicety; the form is not.
			}
		}

		function schedule() {
			window.clearTimeout( timer );
			timer = window.setTimeout( write, 400 );
		}

		function note( message ) {
			var old = form.querySelector( '[data-draft-note]' );
			if ( old ) {
				old.remove();
			}

			var p = draftStyle( document.createElement( 'p' ),
				'margin: 0 0 var(--wp--preset--spacing--30);' +
				'color: var(--wp--custom--color--text-body);'
			);
			p.setAttribute( 'role', 'status' );
			p.setAttribute( 'tabindex', '-1' );
			p.setAttribute( 'data-draft-note', '' );
			p.textContent = message;
			form.insertBefore( p, form.firstChild );
			p.focus();
		}

		function resolve( restore ) {
			var box  = form.querySelector( '[data-draft-banner]' );
			var snap = pending;

			// Saving resumes from here whichever button was pressed.
			pending = null;

			if ( restore ) {
				applyDraft( form, snap.values );
				applyDependencies( form );
				if ( steps( form ).length ) {
					gotoStep( form, snap.step || 1 );
				}
			}

			if ( box ) {
				box.remove();
			}

			// Either way the live form is now the draft: restored answers when
			// they were kept, whatever was typed meanwhile when they were not.
			write();

			var withheld = withheldLabels( form ).length;

			note( restore
				? ( withheld
					? 'Your answers were restored. The ' + withheld + ' fields listed as not saved are still blank and need to be filled in again.'
					: 'Your answers were restored.' )
				: 'Draft discarded. This form is being saved again as you go.' );
		}

		function banner() {
			var box = draftStyle( document.createElement( 'div' ),
				'margin: 0 0 var(--wp--preset--spacing--40);' +
				'padding: var(--wp--preset--spacing--30);' +
				'border: 2px solid var(--wp--custom--color--info);' +
				'border-radius: var(--wp--custom--radius--md);' +
				'background-color: var(--wp--custom--color--info-bg);' +
				'color: var(--wp--custom--color--text);'
			);
			// role=status, not alert: a recovered draft is good news, and the
			// error summary already owns `alert` on this form.
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'tabindex', '-1' );
			box.setAttribute( 'data-draft-banner', '' );

			var title = draftStyle( document.createElement( 'p' ),
				'margin: 0 0 var(--wp--preset--spacing--10); font-weight: 700;' );
			title.textContent = 'You have an unfinished referral from this browsing session, saved ' + ago( pending.at ) + '.';
			box.appendChild( title );

			var withheld = withheldLabels( form );
			var body = draftStyle( document.createElement( 'p' ),
				'margin: 0 0 var(--wp--preset--spacing--20);' );
			body.textContent = withheld.length
				? 'Nothing you type is being saved until you choose. For privacy, these answers were never saved and will need to be entered again: ' + withheld.join( ', ' ) + '.'
				: 'Nothing you type is being saved until you choose.';
			box.appendChild( body );

			var actions = draftStyle( document.createElement( 'p' ),
				'margin: 0; display: flex; flex-wrap: wrap; gap: var(--wp--preset--spacing--20);' );

			var restore = draftButton( 'Restore my answers', true );
			restore.addEventListener( 'click', function () { resolve( true ); } );

			var discard = draftButton( 'Discard it and start again', false );
			discard.addEventListener( 'click', function () { resolve( false ); } );

			actions.appendChild( restore );
			actions.appendChild( discard );
			box.appendChild( actions );

			return box;
		}

		if ( pending ) {
			var box = banner();
			form.insertBefore( box, form.firstChild );
			// Same rule the error summary is held to: the element must actually
			// RECEIVE focus, or a screen-reader user is never told the draft is
			// there and the recovery may as well not exist.
			box.focus();
		}

		form.addEventListener( 'input', schedule );
		form.addEventListener( 'change', schedule );

		// A step change is written immediately rather than debounced: it is the
		// thing a restored draft needs in order to put the visitor back where
		// they were, and it is the moment they are most likely to navigate.
		form.addEventListener( 'click', function ( e ) {
			if ( e.target.hasAttribute( 'data-step-next' ) || e.target.hasAttribute( 'data-step-prev' ) ) {
				window.setTimeout( write, 0 );
			}
		} );

		// pagehide, not unload: unload is ignored in the bfcache path that a
		// back-button navigation actually takes.
		window.addEventListener( 'pagehide', function () {
			window.clearTimeout( timer );
			write();
		} );
	}

	/* ---------- wire each form ---------- */

	forms.forEach( function ( form ) {
		applyDependencies( form );
		bindUploads( form );

		form.addEventListener( 'change', function ( e ) {
			if ( ! e.target.hasAttribute( 'name' ) ) {
				return;
			}

			applyDependencies( form );

			// Clear a field's error as soon as the visitor fixes it. A <select>
			// and a radio never fire `blur` on the path most people take -- they
			// click an option and move straight to the next control -- so
			// relying on blur alone left a corrected field still showing its
			// error message. Only fields ALREADY marked invalid are touched, so
			// this never nags someone who has not submitted yet.
			var field = e.target.closest ? e.target.closest( '.field' ) : null;

			if ( field && field.classList.contains( 'field--invalid' ) ) {
				setError( field, validateField( field ) );
			}
		} );

		/*
		 * Re-validate a field the visitor has already been told about -- never
		 * one they have not submitted yet, which would be nagging.
		 *
		 * `input` as well as `blur`, and the `input` half is load-bearing.
		 * MEASURED: with blur alone, correcting the LAST field of a step and
		 * clicking Continue misses the button. Blur fires on the click, the
		 * error message collapses from a line of text to nothing, every control
		 * below it shifts up by that line, and the button moves out from under
		 * the pointer between mousedown and mouseup -- the click lands on the
		 * <form>. Clearing as they type means the layout has already settled by
		 * the time they reach the button.
		 */
		var revalidate = function ( e ) {
			var field = e.target.closest ? e.target.closest( '.field' ) : null;

			if ( field && field.classList.contains( 'field--invalid' ) ) {
				setError( field, validateField( field ) );
			}
		};

		form.addEventListener( 'input', revalidate );
		form.addEventListener( 'blur', revalidate, true );

		if ( steps( form ).length ) {
			gotoStep( form, 1 );

			form.addEventListener( 'click', function ( e ) {
				if ( e.target.hasAttribute( 'data-step-next' ) ) {
					var current = parseInt( form.getAttribute( 'data-current-step' ), 10 ) || 1;
					if ( validateStep( form, current ) ) {
						gotoStep( form, current + 1 );
						form.scrollIntoView( { block: 'start' } );
					}
				}

				if ( e.target.hasAttribute( 'data-step-prev' ) ) {
					var c = parseInt( form.getAttribute( 'data-current-step' ), 10 ) || 1;
					gotoStep( form, c - 1 );
					form.scrollIntoView( { block: 'start' } );
				}
			} );
		}

		form.addEventListener( 'submit', function ( e ) {
			applyDependencies( form );

			var failures = [];

			form.querySelectorAll( '.field' ).forEach( function ( field ) {
				var message = validateField( field );
				setError( field, message );

				if ( message ) {
					var c = controls( field )[ 0 ];
					failures.push( { id: c ? c.id : '', message: message } );
				}
			} );

			if ( failures.length ) {
				e.preventDefault();
				showSummary( form, failures );
				return;
			}

			clearSummary( form );
		} );

		// LAST in the wiring, deliberately: bindDraft may call gotoStep() while
		// restoring, and gotoStep( form, 1 ) above must already have run and set
		// data-current-step before that happens.
		bindDraft( form );
	} );

	/*
	 * A server-rendered error summary wants focus on arrival.
	 *
	 * `[data-form-success]` is NOT in this selector any more -- it is handled
	 * above the `forms.length` guard, because a success page contains no form
	 * and never reaches this line. See the note there.
	 *
	 * A draft banner outranks it: if one is on screen the visitor has a choice
	 * to make before the field errors mean anything.
	 */
	if ( ! document.querySelector( '[data-draft-banner]' ) ) {
		var announce = document.querySelector( '[data-form-summary]' );
		if ( announce ) {
			announce.focus();
		}
	}
}() );
