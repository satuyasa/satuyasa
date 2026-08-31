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

== Cakupan Fase 3 (ditambahkan di atas Fase 1 & 2) ==

* 3 tab My Account baru (Unduhan Saya, Sertifikat Lisensi, Wishlist) otomatis tampil di sidebar navigasi My Account bawaan WooCommerce — implementasinya di plugin (`class-account-endpoints.php`), tema tidak perlu perubahan template karena `woocommerce.php` sudah membungkus seluruh halaman My Account.
* Tombol wishlist (ikon hati, `aksara_wishlist_button()`) tampil di kartu produk (`template-parts/asset-card.php`), baris spesimen font (`template-parts/font-specimen-row.php`), dan ringkasan single product (hook `woocommerce_single_product_summary`) — hanya untuk user yang sudah login.
* `.asset-card` diubah dari `<a>` menjadi `<div>` berisi `<a>` di dalamnya (bukan lagi seluruh kartu jadi link) supaya tombol wishlist (elemen interaktif) tidak bersarang di dalam link lain — nesting interactive content di dalam `<a>` tidak valid HTML dan bikin klik ambigu.

== Cakupan Fase 4 (ditambahkan di atas Fase 1-3) ==

* **SEO** (`inc/seo.php`): meta description & Open Graph tags. Structured data Product, sitemap XML, dan meta `<title>`/`rel=canonical` **tidak ditambahkan di sini** karena WordPress core & WooCommerce core sudah mencetaknya otomatis tanpa kode tambahan — lihat penjelasan lengkap di komentar `inc/seo.php`.
* **Performa**: `aksara_count_products_by_type()` (3x query WP_Query tiap Home dibuka) & `aksara_get_listing_url()` sekarang di-cache pakai transient (lihat `inc/woocommerce-helpers.php`), auto-flush saat produk/halaman terkait disimpan.
* **Aksesibilitas**:
  * Warna `--ochre` (dipakai untuk teks harga di mana-mana) digelapkan dari `#BE7A2E` ke `#835420` — versi asli mockup gagal kontras WCAG AA (2,9-3,5:1 terhadap latar situs, syarat minimal 4,5:1), versi baru 5,9-6,5:1.
  * Indikator fokus keyboard (`:focus-visible`, outline indigo 2px) ditambahkan secara global, plus perbaikan pada elemen yang sebelumnya `outline:none` tanpa pengganti (kotak pencarian hero, kotak pratinjau font di plugin).
* **Responsif**: tabel WooCommerce (cart, checkout, tab My Account) bisa di-scroll horizontal di layar sempit alih-alih merusak lebar halaman; baris "Font pilihan" di Home ditata ulang jadi 2 baris di mobile (≤640px) alih-alih 4 kolom sempit berdesakan.

## Yang BELUM ada (menyusul di fase lanjut)

* Halaman Cart/Checkout TERSTYLE lewat CSS bawaan `woocommerce.php`, belum ada template override kustom per halaman.
* Customizer UI untuk hero (saat ini teks hero memakai default dari mockup lewat `get_theme_mod()` — sudah bisa diubah lewat kode/nanti Customizer, belum ada panel UI).
* Typing tool butuh `services/font-preview-service/` berjalan (lihat readme-nya) — tanpa itu, area pratinjau akan menampilkan pesan "pratinjau tidak tersedia" tapi kalkulator harga & tambah-ke-keranjang tetap berfungsi (keduanya tidak bergantung pada microservice).
* Testing end-to-end sungguhan di browser (responsive, keyboard, screen reader) — lihat checklist di `docs/QA-TEST-PLAN.md` (perlu staging WordPress aktif, tidak bisa dijalankan otomatis di environment development ini).

== Instalasi ==

1. Aktifkan WooCommerce dan plugin **Aksara Marketplace** terlebih dahulu.
2. Unggah folder `aksara` ke `/wp-content/themes/`, lalu aktifkan.
3. Buat Page baru untuk tiap listing (Fonts/Templates/Elements/License), pilih template yang sesuai di Page Attributes.
4. Atur halaman-halaman itu di **WooCommerce > Pengaturan > Halaman Muka** / menu navigasi sesuai kebutuhan.
5. Buat produk lewat plugin Aksara Marketplace (lihat readme plugin) — otomatis muncul di listing & Home begitu dipublikasikan.
