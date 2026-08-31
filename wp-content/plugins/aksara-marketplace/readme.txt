=== Aksara Marketplace ===
Contributors: aksara
Tags: woocommerce, marketplace, fonts, digital downloads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace WooCommerce untuk Font (per-style, lisensi bertingkat), Canva Template, dan Canva Element. Status saat ini: **Fase 2 — preview engine & kalkulator lisensi** (lihat Breakdown Task Development Aksara).

Butuh `services/font-preview-service/` (microservice Python) berjalan di server yang sama untuk fitur typing tool — lihat readme di folder tersebut untuk cara menjalankannya bersama plugin ini.

== Cakupan Fase 1 (fondasi produk) ==

* 3 product type kustom terdaftar sebagai term taksonomi `product_type` WooCommerce (bukan CPT terpisah): `font`, `canva_template`, `canva_element`.
* Class `WC_Product_Font`, `WC_Product_Canva_Template`, `WC_Product_Canva_Element` — dua yang terakhir extend `WC_Product_Simple` (pakai UI harga/stok WooCommerce standar), `WC_Product_Font` menghitung harga dari matriks style x lisensi.
* Tabel database: `aksara_font_styles`, `aksara_font_licenses`, `aksara_license_tiers` (skema disiapkan, belum dipakai — keputusan produk saat ini flat price, bukan tier per-pageview), `aksara_style_prices`.
* Metabox admin **Font Styles**: bulk upload banyak file font sekaligus (weight & italic ditebak otomatis dari nama file), matriks harga per lisensi, hapus style.
* Metabox admin **Info Canva**: tautan Canva & dimensi untuk produk Canva Template/Element (kategori pakai taksonomi Product categories bawaan WooCommerce).
* Halaman admin **WooCommerce > Lisensi Font**: CRUD jenis lisensi (nama, slug, deskripsi legal — dipakai juga untuk halaman License di frontend).
* File font/template asli disimpan di folder privat (`wp-content/uploads/aksara-private/`, diblokir `.htaccess` — setup Nginx perlu aturan setara manual karena `.htaccess` tidak berlaku).

== Cakupan Fase 2 (preview engine & kalkulator, ditambahkan di atas Fase 1) ==

* **REST API `aksara/v1`** (`includes/class-rest-controller.php`):
  * `POST /font-preview` & `POST /font-preview-batch` — subset woff2 untuk 1 atau banyak style sekaligus, dikembalikan sebagai JSON berisi data URI base64 (bukan binary mentah — WP REST API selalu JSON-encode body, jadi binary mentah akan rusak; lihat komentar di kode).
  * `PUT /admin/style-prices` — update harga style x lisensi via API (perlu capability `manage_woocommerce`).
  * `POST /cart/add-font` — tambahkan kombinasi BANYAK style + 1 lisensi ke cart sebagai satu baris; ini jalur utama add-to-cart sejak Fase 2 (menggantikan form HTML biasa di Fase 1).
* **Rate limiting persisten** per-IP (transient, 40 request/menit) di endpoint preview — lapisan pertahanan utama terhadap scraping charset, di atas rate limit in-memory bawaan microservice yang cuma backstop.
* **Cache 10 menit** untuk hasil subset (`Aksara_Preview_Service_Client`) + cron harian `aksara_cleanup_preview_cache` (`Aksara_Cleanup_Jobs`) yang membersihkan baris transient kedaluwarsa secara proaktif.
* **Kalkulator multi-style**: `Aksara_Cart_Handler::validate_combo()` sekarang menerima array style_id (bukan cuma 1), menjumlah harga, dan menerapkan **diskon paket lengkap** (field baru `_aksara_bundle_discount_percent` di metabox Font Styles) kalau SELURUH style berharga untuk lisensi tsb dipilih.
* **Typing tool interaktif** (`assets/js/font-typing-tool.js` + `templates/single-product/add-to-cart/font.php`, dirender lewat hook `woocommerce_font_add_to_cart`): pilih weight/italic/ukuran, ketik teks sendiri (debounce ~1 detik), lihat live preview tiap style dalam font aslinya (lewat FontFace API dari subset yang diterima, bukan file lengkap), pilih beberapa style, pilih lisensi, lihat total harga real-time, tombol "Pilih Semua (Paket Lengkap)", tambah ke keranjang tanpa reload halaman.
* Harga yang tampil di kalkulator SELALU dihitung ulang & divalidasi di server (`validate_combo()`) saat add-to-cart — nilai dari klien tidak pernah dipercaya langsung.

## Yang BELUM ada (menyusul di fase lanjut sesuai Breakdown Task)

* Sistem download aman bertoken & invoice/sertifikat lisensi PDF (Fase 3).
* Wishlist, dashboard admin ringkas (Fase 3).
* Deployment produksi microservice (systemd/WSGI server) — lihat readme di `services/font-preview-service/`.

== Instalasi ==

1. Pastikan WooCommerce aktif terlebih dahulu.
2. Unggah folder `aksara-marketplace` ke `/wp-content/plugins/`.
3. Aktifkan plugin — tabel database & folder privat dibuat otomatis saat aktivasi.
4. Jalankan `services/font-preview-service/` (lihat readme-nya) dengan `AKSARA_FONT_STORAGE_DIR` mengarah ke `wp-content/uploads/aksara-private` situs ini — tanpa ini, typing tool akan menampilkan pesan "pratinjau tidak tersedia".
5. Buka **WooCommerce > Lisensi Font** untuk memeriksa/menyunting 5 jenis lisensi default yang sudah diisi otomatis (Desktop, Web, Aplikasi, E-book, Komersial Lanjutan).
6. Tambah produk baru → set **Product type** ke "Font (Aksara)" → isi style lewat metabox **Font Styles** → atur harga per lisensi → (opsional) atur diskon paket lengkap.
7. Untuk Canva Template/Element: set **Product type** sesuai, isi harga seperti simple product biasa, lalu lengkapi metabox **Info Canva**.

== Changelog ==

= 0.2.0 =
* Fase 2: REST API preview & cart, typing tool interaktif, kalkulator multi-style + diskon paket lengkap, rate limiting persisten, cron cleanup cache.

= 0.1.0 =
* Fase 1: fondasi product type, database, metabox admin, dan alur beli dasar.
