/**
 * Toggle menu navigasi pada tampilan mobile.
 *
 * @package Aksara
 */
( function () {
	'use strict';

	var toggle = document.querySelector( '.menu-toggle' );
	var nav = document.getElementById( 'site-navigation' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', ! expanded );
			nav.classList.toggle( 'is-open' );
		} );
	}

	document.querySelectorAll( '[data-font-gallery]' ).forEach( function ( gallery ) {
		var track = gallery.querySelector( '[data-gallery-track]' );
		var previous = gallery.querySelector( '[data-gallery-prev]' );
		var next = gallery.querySelector( '[data-gallery-next]' );
		var slides = track ? track.querySelectorAll( '.font-archive-gallery__slide' ) : [];

		if ( ! track || ! slides.length ) {
			return;
		}

		function updateControls() {
			var maxScroll = Math.max( 0, track.scrollWidth - track.clientWidth - 2 );
			if ( previous ) previous.hidden = maxScroll <= 2;
			if ( next ) next.hidden = maxScroll <= 2;
		}

		function move( direction ) {
			var firstWidth = slides[ 0 ].getBoundingClientRect().width;
			var gap = parseFloat( window.getComputedStyle( track ).columnGap ) || 0;
			var maxScroll = Math.max( 0, track.scrollWidth - track.clientWidth );
			var target = track.scrollLeft + direction * ( firstWidth + gap );
			if ( direction < 0 && track.scrollLeft <= 2 ) target = maxScroll;
			if ( direction > 0 && track.scrollLeft >= maxScroll - 2 ) target = 0;
			track.scrollTo( { left: target, behavior: 'smooth' } );
		}

		if ( previous ) previous.addEventListener( 'click', function () { move( -1 ); } );
		if ( next ) next.addEventListener( 'click', function () { move( 1 ); } );
		track.addEventListener( 'scroll', updateControls, { passive: true } );
		window.addEventListener( 'resize', updateControls, { passive: true } );
		updateControls();
	} );
} )();
