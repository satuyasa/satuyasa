/**
 * Typing tool interaktif + kalkulator lisensi untuk produk Font (Fase 2).
 *
 * Tidak pernah memuat file font asli lewat @font-face publik langsung —
 * setiap glyph yang dirender di sini datang dari REST endpoint
 * aksara/v1/font-preview(-batch), yang cuma mengirim subset terbatas
 * sesuai teks yang diketik (lihat services/font-preview-service/ &
 * includes/class-rest-controller.php). Harga yang ditampilkan di sini
 * murni untuk UX; harga final SELALU dihitung ulang di server saat
 * "Tambah ke Keranjang" ditekan (lihat Aksara_Cart_Handler::validate_combo()).
 */
( function () {
	'use strict';

	var WEIGHT_LABELS = {
		100: 'Thin', 200: 'ExtraLight', 300: 'Light', 400: 'Regular',
		500: 'Medium', 600: 'SemiBold', 700: 'Bold', 800: 'ExtraBold', 900: 'Black',
	};

	document.addEventListener( 'DOMContentLoaded', init );

	function init() {
		var root = document.getElementById( 'aksaraFontTool' );
		var config = window.aksaraFontTool;

		if ( ! root || ! config || ! config.styles || ! config.styles.length ) {
			return;
		}

		root.hidden = false;

		var state = {
			weight: config.styles[ 0 ].weight,
			italic: false,
			selectedStyles: new Set(),
			selectedLicense: null,
			fontFamilies: {}, // styleId -> nama family FontFace yang sedang aktif untuk teks saat ini.
			activeFaces: {}, // styleId -> objek FontFace aktif (buat dibersihkan sebelum diganti).
			lastFetchedText: null,
		};

		var el = {
			weightTabs: document.getElementById( 'aksaraWeightTabs' ),
			italicToggle: document.getElementById( 'aksaraItalicToggle' ),
			sizeSlider: document.getElementById( 'aksaraSizeSlider' ),
			sizeValue: document.getElementById( 'aksaraSizeValue' ),
			previewText: document.getElementById( 'aksaraPreviewText' ),
			previewStatus: document.getElementById( 'aksaraPreviewStatus' ),
			selectedCount: document.getElementById( 'aksaraSelectedCount' ),
			styleList: document.getElementById( 'aksaraStyleList' ),
			selectAll: document.getElementById( 'aksaraSelectAll' ),
			licenseList: document.getElementById( 'aksaraLicenseList' ),
			styleCountLabel: document.getElementById( 'aksaraStyleCountLabel' ),
			styleSubtotal: document.getElementById( 'aksaraStyleSubtotal' ),
			totalPrice: document.getElementById( 'aksaraTotalPrice' ),
			addToCart: document.getElementById( 'aksaraAddToCart' ),
			ctaMessage: document.getElementById( 'aksaraCtaMessage' ),
		};

		renderWeightTabs( config, state, el );
		renderStyleList( config, state, el );
		renderLicenseList( config, state, el );

		el.italicToggle.addEventListener( 'click', function () {
			state.italic = ! state.italic;
			el.italicToggle.classList.toggle( 'is-active', state.italic );
			el.italicToggle.setAttribute( 'aria-pressed', state.italic ? 'true' : 'false' );
			updateActivePreview( config, state, el );
		} );

		el.sizeSlider.addEventListener( 'input', function () {
			el.previewText.style.fontSize = el.sizeSlider.value + 'px';
			if ( el.sizeValue ) {
				el.sizeValue.textContent = el.sizeSlider.value;
			}
		} );

		el.selectAll.textContent = config.i18n.selectAll;
		el.selectAll.addEventListener( 'click', function () {
			var allPriced = pricedStyleIdsForLicense( config, state.selectedLicense );
			allPriced.forEach( function ( id ) {
				state.selectedStyles.add( id );
			} );
			syncStyleCheckboxes( el, state );
			updateSelectedCount( config, el, state );
			updatePriceSummary( config, state, el );
		} );

		el.previewText.textContent = config.defaultPreviewText;
		var debounced = debounce( function () {
			refreshAllPreviews( config, state, el );
		}, config.debounceMs || 1000 );

		el.previewText.addEventListener( 'input', function () {
			var text = el.previewText.textContent || '';
			if ( text.length > config.maxPreviewChars ) {
				text = text.slice( 0, config.maxPreviewChars );
				el.previewText.textContent = text;
				placeCaretAtEnd( el.previewText );
			}
			el.previewText.classList.add( 'is-loading' );
			el.previewStatus.textContent = config.i18n.loading;
			debounced();
		} );

		el.addToCart.addEventListener( 'click', function () {
			handleAddToCart( config, state, el );
		} );

		updateSelectedCount( config, el, state );
		el.previewText.classList.add( 'is-loading' );
		el.previewStatus.textContent = config.i18n.loading;
		refreshAllPreviews( config, state, el );
	}

	function debounce( fn, delay ) {
		var timer = null;
		return function () {
			clearTimeout( timer );
			timer = setTimeout( fn, delay );
		};
	}

	function pricedStyleIdsForLicense( config, licenseId ) {
		if ( ! licenseId ) {
			return [];
		}
		return Object.keys( config.prices )
			.filter( function ( styleId ) {
				return config.prices[ styleId ] && config.prices[ styleId ][ licenseId ];
			} )
			.map( Number );
	}

	function findStyleFor( config, weight, italic ) {
		var exact = config.styles.find( function ( s ) {
			return s.weight === weight && s.italic === italic;
		} );
		if ( exact ) {
			return exact;
		}
		var byWeight = config.styles.find( function ( s ) {
			return s.weight === weight;
		} );
		return byWeight || config.styles[ 0 ];
	}

	function renderWeightTabs( config, state, el ) {
		var weights = Array.from( new Set( config.styles.map( function ( s ) { return s.weight; } ) ) ).sort( function ( a, b ) { return a - b; } );

		el.weightTabs.innerHTML = '';
		weights.forEach( function ( weight ) {
			var isActive = weight === state.weight;
			var tab = document.createElement( 'button' );
			tab.type = 'button';
			tab.className = 'aksara-ft-weight-tab' + ( isActive ? ' is-active' : '' );
			tab.textContent = WEIGHT_LABELS[ weight ] || String( weight );
			tab.dataset.weight = weight;
			tab.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			tab.addEventListener( 'click', function () {
				state.weight = weight;
				el.weightTabs.querySelectorAll( '.aksara-ft-weight-tab' ).forEach( function ( t ) {
					t.classList.remove( 'is-active' );
					t.setAttribute( 'aria-pressed', 'false' );
				} );
				tab.classList.add( 'is-active' );
				tab.setAttribute( 'aria-pressed', 'true' );
				updateActivePreview( config, state, el );
			} );
			el.weightTabs.appendChild( tab );
		} );
	}

	function renderStyleList( config, state, el ) {
		el.styleList.innerHTML = '';
		config.styles.forEach( function ( style ) {
			var row = document.createElement( 'div' );
			row.className = 'aksara-ft-style-row';
			row.dataset.styleId = style.id;

			var checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';

			var name = document.createElement( 'div' );
			name.className = 'aksara-ft-style-name';
			name.textContent = style.name;

			var sample = document.createElement( 'div' );
			sample.className = 'aksara-ft-style-sample';
			sample.dataset.styleId = style.id;
			sample.textContent = style.name;

			var price = document.createElement( 'div' );
			price.className = 'aksara-ft-style-price';
			price.dataset.styleId = style.id;

			row.appendChild( checkbox );
			row.appendChild( name );
			row.appendChild( sample );
			row.appendChild( price );

			row.addEventListener( 'click', function ( evt ) {
				if ( evt.target !== checkbox ) {
					checkbox.checked = ! checkbox.checked;
				}
				if ( checkbox.checked ) {
					state.selectedStyles.add( style.id );
				} else {
					state.selectedStyles.delete( style.id );
				}
				updateSelectedCount( config, el, state );
				updatePriceSummary( config, state, el );
			} );

			el.styleList.appendChild( row );
		} );
	}

	function renderLicenseList( config, state, el ) {
		el.licenseList.innerHTML = '';
		el.licenseList.setAttribute( 'role', 'radiogroup' );
		el.licenseList.setAttribute( 'aria-label', config.i18n.selectLicense );

		config.licenses.forEach( function ( license ) {
			// <button> (bukan <div>) supaya bisa dijangkau & diaktifkan lewat
			// keyboard secara native, dengan role="radio" karena cuma 1 lisensi
			// yang bisa dipilih dalam satu waktu.
			var opt = document.createElement( 'button' );
			opt.type = 'button';
			opt.className = 'aksara-ft-license-opt';
			opt.dataset.licenseId = license.id;
			opt.setAttribute( 'role', 'radio' );
			opt.setAttribute( 'aria-checked', 'false' );

			var row = document.createElement( 'div' );
			row.className = 'aksara-ft-license-row';
			var label = document.createElement( 'b' );
			label.textContent = license.name;
			row.appendChild( label );
			opt.appendChild( row );

			opt.addEventListener( 'click', function () {
				state.selectedLicense = license.id;
				el.licenseList.querySelectorAll( '.aksara-ft-license-opt' ).forEach( function ( o ) {
					o.classList.remove( 'is-active' );
					o.setAttribute( 'aria-checked', 'false' );
				} );
				opt.classList.add( 'is-active' );
				opt.setAttribute( 'aria-checked', 'true' );
				updateStylePrices( config, state, el );
				updatePriceSummary( config, state, el );
			} );

			el.licenseList.appendChild( opt );
		} );
	}

	function updateStylePrices( config, state, el ) {
		config.styles.forEach( function ( style ) {
			var priceEl = el.styleList.querySelector( '.aksara-ft-style-price[data-style-id="' + style.id + '"]' );
			if ( ! priceEl ) {
				return;
			}
			var entry = state.selectedLicense && config.prices[ style.id ] ? config.prices[ style.id ][ state.selectedLicense ] : null;
			priceEl.textContent = entry ? entry.formatted : '—';
		} );
	}

	function updateSelectedCount( config, el, state ) {
		el.selectedCount.textContent = state.selectedStyles.size + ' / ' + config.styles.length;
		syncStyleCheckboxes( el, state );
	}

	function syncStyleCheckboxes( el, state ) {
		el.styleList.querySelectorAll( '.aksara-ft-style-row' ).forEach( function ( row ) {
			var id = Number( row.dataset.styleId );
			var checkbox = row.querySelector( 'input[type="checkbox"]' );
			checkbox.checked = state.selectedStyles.has( id );
		} );
	}

	function formatMoney( amount, currency ) {
		var decimals = typeof currency.decimals === 'number' ? currency.decimals : 2;
		var fixed = amount.toFixed( decimals );
		var parts = fixed.split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, currency.thousandSeparator || ',' );
		var number = decimals > 0 ? parts.join( currency.decimalSeparator || '.' ) : parts[ 0 ];
		return 'right' === currency.position || 'right_space' === currency.position
			? number + ' ' + currency.symbol
			: currency.symbol + number;
	}

	function updatePriceSummary( config, state, el ) {
		var hasSelection = state.selectedStyles.size > 0 && state.selectedLicense;
		var valid = hasSelection;
		var sum = 0;

		if ( hasSelection ) {
			state.selectedStyles.forEach( function ( styleId ) {
				var entry = config.prices[ styleId ] && config.prices[ styleId ][ state.selectedLicense ];
				if ( ! entry ) {
					valid = false;
					return;
				}
				sum += entry.price;
			} );
		}

		var isBundle = false;
		if ( valid ) {
			var allPriced = pricedStyleIdsForLicense( config, state.selectedLicense );
			isBundle = allPriced.length > 0 &&
				allPriced.length === state.selectedStyles.size &&
				allPriced.every( function ( id ) { return state.selectedStyles.has( id ); } );

			if ( isBundle && config.bundleDiscount > 0 ) {
				sum = sum * ( 1 - config.bundleDiscount / 100 );
			}
		}

		if ( ! valid ) {
			el.styleCountLabel.textContent = '—';
			el.styleSubtotal.textContent = '—';
			el.totalPrice.textContent = '—';
			el.addToCart.disabled = true;
			return;
		}

		var licenseName = config.licenses.find( function ( l ) { return l.id === state.selectedLicense; } );
		el.styleCountLabel.textContent = state.selectedStyles.size + ' style × ' + ( licenseName ? licenseName.name : '' ) + ( isBundle && config.bundleDiscount > 0 ? ' (−' + config.bundleDiscount + '%)' : '' );
		var formatted = formatMoney( sum, config.currency );
		el.styleSubtotal.textContent = formatted;
		el.totalPrice.textContent = formatted;
		el.addToCart.disabled = false;
	}

	/**
	 * Ambil pratinjau untuk SEMUA style sekaligus (1 batch request), lalu
	 * simpan nama FontFace per style supaya pindah weight-tab/italic-toggle
	 * tidak perlu request baru selama teksnya belum berubah.
	 */
	function refreshAllPreviews( config, state, el ) {
		var text = ( el.previewText.textContent || '' ).trim();
		if ( ! text ) {
			el.previewText.classList.remove( 'is-loading' );
			el.previewStatus.textContent = '';
			return;
		}

		state.lastFetchedText = text;
		var styleIds = config.styles.map( function ( s ) { return s.id; } );

		fetch( config.restUrl + '/font-preview-batch', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( { style_ids: styleIds, text: text } ),
		} )
			.then( function ( res ) { return res.ok ? res.json() : Promise.reject( res ); } )
			.then( function ( results ) {
				if ( text !== state.lastFetchedText ) {
					return; // Teks sudah berubah lagi sebelum respons ini datang — abaikan (hasil basi).
				}

				var loaders = Object.keys( results ).map( function ( styleId ) {
					return loadFontFace( state, styleId, results[ styleId ] );
				} );

				Promise.all( loaders ).then( function () {
					config.styles.forEach( function ( style ) {
						var sampleEl = el.styleList.querySelector( '.aksara-ft-style-sample[data-style-id="' + style.id + '"]' );
						if ( sampleEl ) {
							sampleEl.textContent = text;
							if ( state.fontFamilies[ style.id ] ) {
								sampleEl.style.fontFamily = state.fontFamilies[ style.id ];
							}
						}
					} );

					el.previewText.classList.remove( 'is-loading' );
					el.previewStatus.textContent = '';
					updateActivePreview( config, state, el );
				} );
			} )
			.catch( function () {
				el.previewText.classList.remove( 'is-loading' );
				applySpecimenFallback( config, state, el );
			} );
	}

	/**
	 * Saat microservice pratinjau tidak bisa dihubungi, jangan cuma
	 * menampilkan pesan error: pakai gambar specimen statis (dirender PHP
	 * di server, lihat class-specimen-image.php) supaya pengunjung tetap
	 * melihat wujud font aslinya. Yang hilang hanya kemampuan mengetik teks
	 * sendiri — itu yang dijelaskan lewat pesan status.
	 */
	function applySpecimenFallback( config, state, el ) {
		var anySpecimen = false;

		config.styles.forEach( function ( style ) {
			if ( ! style.specimen ) {
				return;
			}
			anySpecimen = true;

			var sampleEl = el.styleList.querySelector( '.aksara-ft-style-sample[data-style-id="' + style.id + '"]' );
			if ( sampleEl && ! sampleEl.querySelector( 'img' ) ) {
				sampleEl.textContent = '';
				sampleEl.appendChild( buildSpecimenImg( style, 24 ) );
			}
		} );

		var active = findStyleFor( config, state.weight, state.italic );
		if ( active && active.specimen ) {
			el.previewText.textContent = '';
			el.previewText.appendChild( buildSpecimenImg( active, 52 ) );
			el.previewText.setAttribute( 'contenteditable', 'false' );
		}

		el.previewStatus.textContent = anySpecimen
			? config.i18n.previewFallback
			: config.i18n.previewUnavailable;
	}

	function buildSpecimenImg( style, height ) {
		var img = document.createElement( 'img' );
		img.src = style.specimen;
		img.alt = style.name;
		img.style.height = height + 'px';
		img.style.width = 'auto';
		return img;
	}

	function loadFontFace( state, styleId, dataUri ) {
		var familyName = 'aksara-style-' + styleId;
		var face = new FontFace( familyName, 'url(' + dataUri + ')' );

		return face.load().then(
			function ( loaded ) {
				var previous = state.activeFaces[ styleId ];
				if ( previous ) {
					document.fonts.delete( previous );
				}
				document.fonts.add( loaded );
				state.activeFaces[ styleId ] = loaded;
				state.fontFamilies[ styleId ] = familyName;
			},
			function () {
				// Gagal memuat satu style tidak boleh menggagalkan style lain di batch ini.
				delete state.fontFamilies[ styleId ];
			}
		);
	}

	function updateActivePreview( config, state, el ) {
		var active = findStyleFor( config, state.weight, state.italic );
		if ( active && state.fontFamilies[ active.id ] ) {
			el.previewText.style.fontFamily = state.fontFamilies[ active.id ];
		}
	}

	function placeCaretAtEnd( el ) {
		var range = document.createRange();
		range.selectNodeContents( el );
		range.collapse( false );
		var sel = window.getSelection();
		sel.removeAllRanges();
		sel.addRange( range );
	}

	function handleAddToCart( config, state, el ) {
		el.addToCart.disabled = true;
		el.ctaMessage.className = 'aksara-ft-cta-note';
		el.ctaMessage.textContent = config.i18n.adding;

		fetch( config.restUrl + '/cart/add-font', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( {
				product_id: config.productId,
				style_ids: Array.from( state.selectedStyles ),
				license_id: state.selectedLicense,
			} ),
		} )
			.then( function ( res ) { return res.json().then( function ( body ) { return { ok: res.ok, body: body }; } ); } )
			.then( function ( result ) {
				if ( ! result.ok ) {
					throw new Error( result.body && result.body.message ? result.body.message : config.i18n.error );
				}
				el.ctaMessage.className = 'aksara-ft-cta-note is-success';
				el.ctaMessage.textContent = config.i18n.added;
				el.addToCart.disabled = false;
				document.dispatchEvent( new CustomEvent( 'aksara_added_to_cart', { detail: result.body } ) );
			} )
			.catch( function ( err ) {
				el.ctaMessage.className = 'aksara-ft-cta-note is-error';
				el.ctaMessage.textContent = err.message || config.i18n.error;
				el.addToCart.disabled = false;
			} );
	}
} )();
