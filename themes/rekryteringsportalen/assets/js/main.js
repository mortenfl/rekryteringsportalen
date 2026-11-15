/**
 * Rekryteringsportalen front-end. Vanilla JS, no dependencies.
 * Everything here is progressive enhancement: the site works fully without JS.
 */
( () => {
	'use strict';

	document.documentElement.classList.add( 'js' );

	/* Mobile navigation */
	const toggle = document.querySelector( '.site-nav__toggle' );
	const nav = document.getElementById( 'huvudmeny' );
	if ( toggle && nav ) {
		toggle.addEventListener( 'click', () => {
			const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! open ) );
			nav.classList.toggle( 'is-open', ! open );
		} );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
				toggle.click();
				toggle.focus();
			}
		} );
	}

	/* Collapse filters by default on small screens */
	const mq = window.matchMedia( '(max-width: 959px)' );
	document.querySelectorAll( '.rp-filters__details' ).forEach( ( d ) => {
		if ( mq.matches ) {
			d.removeAttribute( 'open' );
		}
	} );

	/* Live filtering: fetch the same URL and swap #rp-results. URL is updated so views are shareable. */
	const cleanParams = ( form ) => {
		const params = new URLSearchParams();
		new FormData( form ).forEach( ( value, key ) => {
			const v = String( value ).trim();
			if ( v !== '' && ! ( key === 'min_betyg' && v === '0' ) && key !== 'rp_webbadress' ) {
				params.append( key, v );
			}
		} );
		return params;
	};

	const swapResults = async ( url, push ) => {
		const current = document.getElementById( 'rp-results' );
		if ( ! current ) {
			window.location.href = url;
			return;
		}
		current.setAttribute( 'aria-busy', 'true' );
		try {
			const res = await fetch( url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' } );
			if ( ! res.ok ) {
				throw new Error( String( res.status ) );
			}
			const doc = new DOMParser().parseFromString( await res.text(), 'text/html' );
			const next = doc.getElementById( 'rp-results' );
			if ( ! next ) {
				throw new Error( 'no results' );
			}
			current.replaceWith( next );
			if ( push ) {
				history.pushState( { rp: true }, '', url );
			}
			document.title = doc.title;
		} catch ( e ) {
			window.location.href = url;
		}
	};

	document.querySelectorAll( 'form[data-rp-filter]' ).forEach( ( form ) => {
		let timer;
		const run = () => {
			const params = cleanParams( form );
			const base = form.getAttribute( 'action' ).split( '?' )[ 0 ];
			const url = base + ( params.toString() ? '?' + params.toString() : '' );
			swapResults( url, true );
		};
		form.addEventListener( 'change', run );
		form.addEventListener( 'input', ( e ) => {
			if ( e.target.type === 'search' ) {
				clearTimeout( timer );
				timer = setTimeout( run, 350 );
			}
		} );
		form.addEventListener( 'submit', ( e ) => {
			e.preventDefault();
			run();
		} );
	} );

	// Pagination links inside results stay in-page.
	document.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( '#rp-results .rp-pagination a' );
		if ( link && document.querySelector( 'form[data-rp-filter]' ) ) {
			e.preventDefault();
			swapResults( link.href, true );
			document.getElementById( 'rp-results' )?.scrollIntoView( { block: 'start' } );
		}
	} );

	window.addEventListener( 'popstate', () => {
		if ( document.querySelector( 'form[data-rp-filter]' ) ) {
			window.location.reload();
		}
	} );

	/* Select that navigates (top list category filter) */
	document.querySelectorAll( 'select[data-rp-navigate]' ).forEach( ( select ) => {
		select.addEventListener( 'change', () => {
			if ( select.value ) {
				window.location.href = select.value;
			}
		} );
	} );

	/* Star input: announce the chosen label */
	document.querySelectorAll( '.rp-star-input' ).forEach( ( fieldset ) => {
		const out = fieldset.querySelector( '.rp-star-input__label' );
		fieldset.addEventListener( 'change', ( e ) => {
			const label = fieldset.querySelector( `label[for="${ e.target.id }"]` );
			if ( out && label ) {
				out.textContent = label.getAttribute( 'title' );
			}
		} );
	} );

	/* Review text counter */
	document.querySelectorAll( '.rp-review-form textarea[name="rp_kommentar"]' ).forEach( ( textarea ) => {
		const counter = textarea.parentElement.querySelector( '.rp-counter' );
		if ( ! counter ) {
			return;
		}
		const min = parseInt( counter.dataset.min, 10 ) || 0;
		const update = () => {
			const n = textarea.value.trim().length;
			counter.textContent = n < min ? `(${ min - n } kvar)` : '✓';
			counter.classList.toggle( 'is-ok', n >= min );
		};
		textarea.addEventListener( 'input', update );
		update();
	} );

	/* Active section in company tabs */
	const tabs = document.querySelectorAll( '.company-tabs a[href^="#"]' );
	if ( tabs.length && 'IntersectionObserver' in window ) {
		const byId = new Map( [ ...tabs ].map( ( a ) => [ a.getAttribute( 'href' ).slice( 1 ), a ] ) );
		const observer = new IntersectionObserver(
			( entries ) => {
				entries.forEach( ( entry ) => {
					if ( entry.isIntersecting ) {
						tabs.forEach( ( a ) => a.classList.remove( 'is-active' ) );
						byId.get( entry.target.id )?.classList.add( 'is-active' );
					}
				} );
			},
			{ rootMargin: '-40% 0px -55% 0px' }
		);
		byId.forEach( ( _, id ) => {
			const el = document.getElementById( id );
			if ( el ) {
				observer.observe( el );
			}
		} );
	}

	/* Count outbound job portal clicks (fire-and-forget) */
	document.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( 'a[data-rp-portal]' );
		if ( link && window.rpTheme && navigator.sendBeacon ) {
			navigator.sendBeacon( `${ window.rpTheme.rest }klick/${ link.dataset.rpPortal }` );
		}
	} );
} )();
