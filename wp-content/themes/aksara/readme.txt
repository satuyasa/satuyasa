=== Aksara ===
Contributors: aksara
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tema marketplace font, Canva Template & Canva Element untuk Aksara. Full-custom (bukan starter theme), dibangun langsung mengikuti `mockup-home.html` dan `mockup-font-product.html`. Butuh plugin **Aksara Marketplace** aktif untuk fitur penuh (masih tampil tanpa plugin, hanya kehilangan fitur khusus marketplace).

== Cakupan Fase 1 (implementasi saat ini) ==

* Palet & tipografi sesuai mockup (Fraunces untuk heading/branding, Inter untuk UI chrome — dimuat dari Google Fonts).
* Halaman **Home** (`front-page.php`): hero, 3 kartu kategori, daftar "Font pilihan" (statis, lihat catatan keamanan di bawah), grid Template/Element terbaru, trust section.
* `woocommerce.php`: wrapper standar yang membungkus SEMUA halaman WooCommerce bawaan (shop, single product, cart, checkout, my account) dengan header/footer & styling tema — tanpa override template WooCommerce satu per satu.
* Page template kustom (pilih lewat Page Attributes saat membuat Page baru):
  * **Aksara — Daftar Font** (`page-templates/template-fonts.php`)
  * **Aksara — Daftar Canva Template** (`page-templates/template-templates.php`)
  * **Aksara — Daftar Canva Element** (`page-templates/template-elements.php`)
  * **Aksara — Halaman License** (`page-templates/template-license.php`) — dirender otomatis dari data plugin (WooCommerce > Lisensi Font).
* Single product font/template/element: info produk (jumlah style / dimensi / kategori), harga (dari matriks style x lisensi untuk font), form pilih style+lisensi, tombol beli — semua lewat hook WooCommerce standar + template `single-product/add-to-cart/font.php` dari plugin.
* Blog & halaman generik (page.php, single.php, archive.php, search.php, 404.php, comments.php) dengan styling konsisten.

## Catatan keamanan penting: kenapa Home TIDAK merender font asli

Daftar "Font pilihan" di Home sengaja menampilkan nama produk dalam font UI tema (Fraunces), BUKAN dalam font asli yang dijual. Ini konsisten dengan seluruh premis marketplace: file font asli tidak boleh diakses publik sebelum dibeli. Pratinjau interaktif (typing tool, lihat `mockup-font-product.html`) baru masuk di **Fase 2**, dan akan memakai microservice subsetting (`services/font-preview-service/`, sudah ada sebagai POC Fase 0) yang hanya mengirim glyph terbatas dengan token kedaluwarsa — bukan `@font-face` publik ke file lengkap seperti pada mockup HTML statis.

## Yang BELUM ada di Fase 1 ini (menyusul di fase lanjut)

* Typing tool live preview & kalkulator multi-style/bundle (Fase 2).
* Halaman Cart/Checkout/My Account TERSTYLE lewat CSS bawaan `woocommerce.php`, belum ada template override kustom per halaman (cukup untuk Fase 1; polish lebih lanjut menyusul Fase 4).
* My Account > Downloads & License Certificates, Wishlist (Fase 3).
* Customizer UI untuk hero (saat ini teks hero memakai default dari mockup lewat `get_theme_mod()` — sudah bisa diubah lewat kode/nanti Customizer, belum ada panel UI).

== Instalasi ==

1. Aktifkan WooCommerce dan plugin **Aksara Marketplace** terlebih dahulu.
2. Unggah folder `aksara` ke `/wp-content/themes/`, lalu aktifkan.
3. Buat Page baru untuk tiap listing (Fonts/Templates/Elements/License), pilih template yang sesuai di Page Attributes.
4. Atur halaman-halaman itu di **WooCommerce > Pengaturan > Halaman Muka** / menu navigasi sesuai kebutuhan.
5. Buat produk lewat plugin Aksara Marketplace (lihat readme plugin) — otomatis muncul di listing & Home begitu dipublikasikan.
