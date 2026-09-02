/**
 * Foundry — penyelaras warna spesimen & tombol reset untuk halaman Free Font.
 *
 * Skrip ini SENGAJA sangat kecil. Seluruh mesin tester — debounce, sinkronisasi
 * kontrol, antrian render, cache — sudah ada di specimen.js milik Authentype,
 * dan tema hanya menyediakan markup dengan kontrak yang benar. Yang tersisa
 * cuma dua hal yang tidak bisa diselesaikan lewat markup saja.
 *
 * 1. WARNA
 *
 * initRoot() di specimen.js membuka dirinya dengan dua baris ini:
 *
 *     root.dataset.textColor = "#111111";
 *     root.dataset.bgColor   = "#ffffff";
 *
 * Tanpa syarat, menimpa apa pun yang ditulis di markup. Nilai itu dikirim ke
 * endpoint render, jadi PNG-nya jadi tinta nyaris hitam di atas putih — di
 * kanvas Foundry yang hitam hasilnya blok putih menyala, bukan spesimen.
 * Karena berkas plugin tidak boleh diedit (akan tertimpa saat update),
 * warnanya dipasang ulang dari sini SESUDAH init.
 *
 * Kenapa urutannya dijamin, bukan sekadar untung-untungan: specimen.js
 * mendaftarkan listener DOMContentLoaded-nya saat di-parse, dan skrip ini
 * mendeklarasikan handle plugin sebagai dependency sehingga selalu dicetak
 * SESUDAHNYA — jadi listener ini juga terdaftar dan berjalan sesudahnya.
 * Render pertama sendiri dipicu IntersectionObserver, yang callback-nya selalu
 * dikirim asinkron setelah layout; jadi penyetelan warna di sini pasti sudah
 * selesai sebelum request render pertama dibuat.
 *
 * Satu pengecualian yang diakui: kalau IntersectionObserver tidak ada,
 * specimen.js me-render seluruh canvas secara SINKRON di dalam initRoot, dan
 * di situ skrip ini memang terlambat. Browser tanpa IntersectionObserver
 * sudah di luar dukungan; konsekuensinya hanya warna spesimen yang keliru,
 * bukan halaman yang rusak.
 *
 * 2. RESET
 *
 * Plugin punya tombol .ath-reset, tapi handler-nya mengembalikan warna ke
 * #111111 — persis masalah di atas. Jadi tombol reset di sini memakai kelas
 * sendiri dan bekerja dengan cara yang paling tidak invasif: ia menulis nilai
 * ke input milik plugin lalu men-dispatch event "input", sehingga yang
 * benar-benar mengerjakan render tetap listener milik plugin.
 */
( function () {
	'use strict';

	var TEXT = '#efefef';
	var BG   = '#121212';

	function paint( root ) {
		root.dataset.textColor = TEXT;
		root.dataset.bgColor   = BG;
	}

	function fire( input, value ) {
		if ( ! input ) {
			return;
		}
		input.value = value;
		// bubbles:true supaya perilakunya sama dengan ketikan sungguhan, dan
		// listener mana pun yang dipasang di leluhur ikut menerimanya.
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	function init() {
		var roots = document.querySelectorAll( '.foundry .ath-specimen-v7' );

		Array.prototype.forEach.call( roots, function ( root ) {
			paint( root );

			var reset = root.querySelector( '.foundry-tester__reset' );
			if ( ! reset ) {
				return;
			}

			reset.addEventListener( 'click', function () {
				var text = root.querySelector( '.ath-master-text' );
				var size = root.querySelector( '.ath-size' );

				// Warna dipasang ulang sebelum memicu render: handler ukuran
				// milik plugin membaca root.dataset saat render, bukan saat
				// klik, tapi menyetelnya lebih dulu membuat urutannya tidak
				// bergantung pada detail implementasi plugin.
				paint( root );

				fire( size, reset.dataset.resetSize || '150' );
				fire( text, reset.dataset.resetText || '' );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
