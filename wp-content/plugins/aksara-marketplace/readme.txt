=== Aksara Marketplace ===
Contributors: aksara
Tags: woocommerce, marketplace, fonts, digital downloads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace WooCommerce untuk Font (per-style, lisensi bertingkat), Canva Template, dan Canva Element. Status saat ini: **Fase 1 — fondasi produk** (lihat Breakdown Task Development Aksara).

== Cakupan Fase 1 (implementasi saat ini) ==

* 3 product type kustom terdaftar sebagai term taksonomi `product_type` WooCommerce (bukan CPT terpisah): `font`, `canva_template`, `canva_element`.
* Class `WC_Product_Font`, `WC_Product_Canva_Template`, `WC_Product_Canva_Element` — dua yang terakhir extend `WC_Product_Simple` (pakai UI harga/stok WooCommerce standar), `WC_Product_Font` menghitung harga dari matriks style x lisensi.
* Tabel database: `aksara_font_styles`, `aksara_font_licenses`, `aksara_license_tiers` (skema disiapkan, belum dipakai — keputusan produk saat ini flat price, bukan tier per-pageview), `aksara_style_prices`.
* Metabox admin **Font Styles**: bulk upload banyak file font sekaligus (weight & italic ditebak otomatis dari nama file), matriks harga per lisensi, hapus style.
* Metabox admin **Info Canva**: tautan Canva & dimensi untuk produk Canva Template/Element (kategori pakai taksonomi Product categories bawaan WooCommerce).
* Halaman admin **WooCommerce > Lisensi Font**: CRUD jenis lisensi (nama, slug, deskripsi legal — dipakai juga untuk halaman License di frontend).
* File font/template asli disimpan di folder privat (`wp-content/uploads/aksara-private/`, diblokir `.htaccess` — setup Nginx perlu aturan setara manual karena `.htaccess` tidak berlaku).
* Alur beli dasar untuk produk font: pilih 1 style + 1 jenis lisensi di halaman produk → harga otomatis dari tabel `aksara_style_prices` → tambah ke keranjang → checkout WooCommerce standar. Data style & lisensi tersimpan sebagai meta pada order line item.

## Yang BELUM ada di Fase 1 ini (menyusul di fase lanjut sesuai Breakdown Task)

* Typing tool live preview & subsetting font interaktif di halaman produk (Fase 2 — microservice POC sudah ada di `services/font-preview-service/`).
* Kalkulator multi-style + bundle "beli paket lengkap" (Fase 2).
* Sistem download aman bertoken & invoice/sertifikat lisensi PDF (Fase 3).
* Wishlist, dashboard admin ringkas (Fase 3).

== Instalasi ==

1. Pastikan WooCommerce aktif terlebih dahulu.
2. Unggah folder `aksara-marketplace` ke `/wp-content/plugins/`.
3. Aktifkan plugin — tabel database & folder privat dibuat otomatis saat aktivasi.
4. Buka **WooCommerce > Lisensi Font** untuk memeriksa/menyunting 5 jenis lisensi default yang sudah diisi otomatis (Desktop, Web, Aplikasi, E-book, Komersial Lanjutan).
5. Tambah produk baru → set **Product type** ke "Font (Aksara)" → isi style lewat metabox **Font Styles** → atur harga per lisensi.
6. Untuk Canva Template/Element: set **Product type** sesuai, isi harga seperti simple product biasa, lalu lengkapi metabox **Info Canva**.

== Changelog ==

= 0.1.0 =
* Fase 1: fondasi product type, database, metabox admin, dan alur beli dasar.
