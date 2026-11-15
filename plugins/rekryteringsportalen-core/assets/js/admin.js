/* Rekryteringsportalen admin: quick edit of packages, media pickers, import mapping helpers. */
( function ( $ ) {
	'use strict';

	// Quick edit: pre-fill package + end date from the hidden inline data in the "Paket" column.
	if ( window.inlineEditPost ) {
		const original = window.inlineEditPost.edit;
		window.inlineEditPost.edit = function ( id ) {
			original.apply( this, arguments );
			const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : id;
			if ( ! postId ) {
				return;
			}
			const data = $( '#post-' + postId ).find( '.rp-inline' );
			const row = $( '#edit-' + postId );
			if ( data.length ) {
				row.find( 'select[name="rp_paket"]' ).val( data.data( 'paket' ) || 'inget' );
				row.find( 'input[name="rp_paket_giltigt_till"]' ).val( data.data( 'till' ) || '' );
			}
		};
	}

	// Media pickers (logo, hero, gallery).
	$( document ).on( 'click', '.rp-media__pick', function ( e ) {
		e.preventDefault();
		const wrap = $( this ).closest( '.rp-media' );
		const multiple = wrap.data( 'multiple' ) === 1 || wrap.data( 'multiple' ) === '1';
		const frame = wp.media( {
			title: multiple ? 'Välj bilder' : 'Välj bild',
			library: { type: 'image' },
			multiple: multiple ? 'add' : false,
			button: { text: 'Använd' },
		} );
		frame.on( 'open', function () {
			const selection = frame.state().get( 'selection' );
			String( wrap.find( '.rp-media__input' ).val() )
				.split( ',' )
				.filter( Boolean )
				.forEach( function ( id ) {
					const att = wp.media.attachment( id );
					att.fetch();
					selection.add( att );
				} );
		} );
		frame.on( 'select', function () {
			const items = frame.state().get( 'selection' ).toJSON();
			const preview = wrap.find( '.rp-media__preview' ).empty();
			items.forEach( function ( item ) {
				const url = item.sizes && item.sizes.thumbnail ? item.sizes.thumbnail.url : item.url;
				$( '<img>', { src: url, alt: '' } ).appendTo( preview );
			} );
			wrap.find( '.rp-media__input' ).val( items.map( ( i ) => i.id ).join( ',' ) );
			wrap.find( '.rp-media__clear' ).prop( 'hidden', items.length === 0 );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.rp-media__clear', function ( e ) {
		e.preventDefault();
		const wrap = $( this ).closest( '.rp-media' );
		wrap.find( '.rp-media__input' ).val( '' );
		wrap.find( '.rp-media__preview' ).empty();
		$( this ).prop( 'hidden', true );
	} );

	// Import mapping: warn when two columns map to the same field.
	$( document ).on( 'change', '.rp-map-select', function () {
		const counts = {};
		$( '.rp-map-select' ).each( function () {
			const v = $( this ).val();
			if ( v ) {
				counts[ v ] = ( counts[ v ] || 0 ) + 1;
			}
		} );
		$( '.rp-map-select' ).each( function () {
			const v = $( this ).val();
			$( this ).toggleClass( 'rp-map-select--dupe', !! v && counts[ v ] > 1 );
		} );
	} );

	// Import: load a saved mapping template.
	$( document ).on( 'change', '#rp-mapping-template', function () {
		const raw = $( this ).find( ':selected' ).attr( 'data-mapping' );
		if ( ! raw ) {
			return;
		}
		const mapping = JSON.parse( raw );
		$( '.rp-map-select' ).each( function () {
			const header = $( this ).data( 'header' );
			if ( Object.prototype.hasOwnProperty.call( mapping, header ) ) {
				$( this ).val( mapping[ header ] );
			}
		} );
		$( '.rp-map-select' ).first().trigger( 'change' );
	} );
} )( jQuery );
