=== Aksara ===
Contributors: aksara
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tema marketplace font, Canva Template & Canva Element untuk Aksara. Full-custom (bukan starter theme), dibangun langsung mengikuti `mockup-home.html` dan `mockup-font-product.html`. Butuh plugin **Aksara Marketplace** aktif untuk fitur penuh (masih tampil tanpa plugin, hanya kehilangan fitur khusus marketplace).

== Cakupan Fase 1 (fondasi) ==

* Palet & tipografi sesuai mockup (Fraunces untuk heading/branding, Inter untuk UI chrome — dimuat dari Google Fonts).
* Halaman **Home** (`front-page.php`): hero, 3 kartu kategori, daftar "Font pilihan" (statis, lihat catatan keamanan di bawah), grid Template/Element terbaru, trust section.
* `woocommerce.php`: wrapper standar yang membungkus SEMUA halaman WooCommerce bawaan (shop, single product, cart, checkout, my account) dengan header/footer & styling tema — tanpa override template WooCommerce satu per satu.
* Page template kustom (pilih lewat Page Attributes saat membuat Page baru):
  * **Aksara — Daftar Font** (`page-templates/template-fonts.php`)
  * **Aksara — Daftar Canva Template** (`page-templates/template-templates.php`)
  * **Aksara — Daftar Canva Element** (`page-templates/template-elements.php`)
  * **Aksara — Halaman License** (`page-templates/template-license.php`) — dirender otomatis dari data plugin (WooCommerce > Lisensi Font).
* Single product font/template/element: info produk (jumlah style / dimensi / kategori), harga, tombol beli — semua lewat hook WooCommerce standar.
* Blog & halaman generik (page.php, single.php, archive.php, search.php, 404.php, comments.php) dengan styling konsisten.

== Cakupan Fase 2 (ditambahkan di atas Fase 1) ==

* Single product **Font** kini menampilkan typing tool interaktif penuh (weight tabs, italic toggle, slider ukuran, grid style dengan pratinjau live per baris, sidebar lisensi + kalkulator harga real-time, tombol "Pilih Semua/Paket Lengkap") — implementasinya ada di plugin (`aksara-marketplace/templates/single-product/add-to-cart/font.php` + `assets/js/font-typing-tool.js`), tema hanya menyediakan hook & styling dasar (`.aksara-product-summary`).
* CSS widget typing tool memakai custom property yang sama dengan tema (`var(--indigo, ...)` dst.) supaya otomatis mengikuti palet tema tanpa dependency langsung ke class PHP tema.

## Catatan keamanan penting: kenapa Home TIDAK merender font asli

Daftar "Font pilihan" di Home tetap sengaja menampilkan nama produk dalam font UI tema (Fraunces), BUKAN dalam font asli yang dijual — beda dengan halaman single product yang sudah punya typing tool aman (lewat subset terbatas). Merender font asli langsung di listing lewat `@font-face` publik akan membocorkannya tanpa perlu lewat mekanisme subsetting sama sekali.

## Yang BELUM ada (menyusul di fase lanjut)

* Halaman Cart/Checkout/My Account TERSTYLE lewat CSS bawaan `woocommerce.php`, belum ada template override kustom per halaman (cukup untuk saat ini; polish lebih lanjut menyusul Fase 4).
* My Account > Downloads & License Certificates, Wishlist (Fase 3).
* Customizer UI untuk hero (saat ini teks hero memakai default dari mockup lewat `get_theme_mod()` — sudah bisa diubah lewat kode/nanti Customizer, belum ada panel UI).
* Typing tool butuh `services/font-preview-service/` berjalan (lihat readme-nya) — tanpa itu, area pratinjau akan menampilkan pesan "pratinjau tidak tersedia" tapi kalkulator harga & tambah-ke-keranjang tetap berfungsi (keduanya tidak bergantung pada microservice).

== Instalasi ==

1. Aktifkan WooCommerce dan plugin **Aksara Marketplace** terlebih dahulu.
2. Unggah folder `aksara` ke `/wp-content/themes/`, lalu aktifkan.
3. Buat Page baru untuk tiap listing (Fonts/Templates/Elements/License), pilih template yang sesuai di Page Attributes.
4. Atur halaman-halaman itu di **WooCommerce > Pengaturan > Halaman Muka** / menu navigasi sesuai kebutuhan.
5. Buat produk lewat plugin Aksara Marketplace (lihat readme plugin) — otomatis muncul di listing & Home begitu dipublikasikan.
