/**
 * Progressive enhancement untuk form pilih style+lisensi produk Font.
 *
 * Form tetap berfungsi tanpa JS (submit apa adanya, validasi harga
 * dilakukan ulang di server oleh Aksara_Cart_Handler) — script ini
 * hanya menyaring dropdown lisensi ke yang benar-benar punya harga
 * untuk style terpilih, dan menampilkan harga sebelum checkout.
 */
( function () {
	'use strict';

	var form = document.querySelector( '.aksara-font-purchase-form' );
	if ( ! form ) {
		return;
	}

	var matrix = {};
	try {
		matrix = JSON.parse( form.getAttribute( 'data-price-matrix' ) || '{}' );
	} catch ( e ) {
		matrix = {};
	}

	var styleSelect   = form.querySelector( '#aksara_style_id' );
	var licenseSelect = form.querySelector( '#aksara_license_id' );
	var priceDisplay  = form.querySelector( '#aksara_selected_price' );
	var licenseLabels = {};

	Array.prototype.forEach.call( licenseSelect.options, function ( option ) {
		if ( option.value ) {
			licenseLabels[ option.value ] = option.textContent;
		}
	} );

	function formatPrice( amount ) {
		if ( window.aksaraFontForm && window.aksaraFontForm.priceFormat ) {
			return window.aksaraFontForm.priceFormat.replace( '%s', amount );
		}
		return amount;
	}

	function refreshLicenseOptions() {
		var styleId = styleSelect.value;
		var prices  = matrix[ styleId ] || {};

		licenseSelect.innerHTML = '';

		var placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = styleId ? '— Pilih jenis lisensi —' : '— Pilih style dulu —';
		licenseSelect.appendChild( placeholder );

		Object.keys( prices ).forEach( function ( licenseId ) {
			var option = document.createElement( 'option' );
			option.value = licenseId;
			option.textContent = licenseLabels[ licenseId ] || licenseId;
			option.dataset.price = prices[ licenseId ];
			licenseSelect.appendChild( option );
		} );

		priceDisplay.textContent = '—';
	}

	function refreshPriceDisplay() {
		var selected = licenseSelect.options[ licenseSelect.selectedIndex ];
		priceDisplay.textContent = selected && selected.dataset.price
			? formatPrice( selected.dataset.price )
			: '—';
	}

	styleSelect.addEventListener( 'change', refreshLicenseOptions );
	licenseSelect.addEventListener( 'change', refreshPriceDisplay );

	if ( styleSelect.value ) {
		refreshLicenseOptions();
	}
} )();
