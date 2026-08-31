=== Aksara Marketplace ===
Contributors: aksara
Tags: woocommerce, marketplace, fonts, digital downloads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace WooCommerce untuk Font (per-style, lisensi bertingkat), Canva Template, dan Canva Element. Status saat ini: **Fase 4 — SEO, performa, aksesibilitas, monitoring** (lihat Breakdown Task Development Aksara).

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

== Cakupan Fase 3 (ditambahkan di atas Fase 1 & 2) ==

* **Token unduh aman** (`aksara_download_tokens`, `class-download-manager.php`): dibuat otomatis saat order berstatus *completed*/*processing* — satu token per style font yang dibeli, satu token per produk Canva. Token adalah kredensial pembawa acak (48 karakter hex, pola sama seperti `download_permissions` bawaan WooCommerce), dicabut otomatis kalau order di-refund/dibatalkan.
* **`GET /aksara/v1/download/{token}`**: stream berkas font ASLI (bukan subset pratinjau) untuk token font, atau redirect ke tautan Canva untuk token Canva. Endpoint ini sengaja TIDAK mengembalikan `WP_REST_Response` biasa untuk kasus stream — langsung kirim header + `readfile()` lalu `exit`, supaya browser dapat Save-As dialog yang benar (lihat komentar di kode kenapa pola JSON-envelope REST biasa tidak cocok untuk ini).
* **Sertifikat lisensi PDF** (`class-pdf-writer.php` + `class-invoice-generator.php`): satu PDF per order (bukan per item), berisi seluruh style+lisensi font yang dibeli di order itu. PDF ditulis manual tanpa dependency eksternal (bukan dompdf/mpdf) — cukup untuk kebutuhan 1 halaman teks, memakai font standar Helvetica yang wajib didukung semua pembaca PDF. Divalidasi dengan pypdf saat development (lihat riwayat commit).
* **My Account, 3 tab baru** (`class-account-endpoints.php`, lewat rewrite endpoint resmi WooCommerce): **Unduhan Saya** (daftar token + sisa kuota), **Sertifikat Lisensi** (unduh ulang PDF), **Wishlist**.
* **Wishlist**: `POST /aksara/v1/wishlist/toggle` (perlu login) + tombol hati (`aksara_wishlist_button()`, template tag global untuk tema) di kartu produk & single product.
* **Email order otomatis**: tautan unduh disisipkan ke email *customer completed/processing order* lewat `woocommerce_email_after_order_table` (bukan override template), sertifikat PDF dilampirkan lewat `woocommerce_email_attachments` — tidak perlu login untuk menerima akses, cocok untuk guest checkout.
* **Dashboard admin**: widget WP Dashboard "Font Terlaris (30 Hari)", dihitung dari order item lewat `wc_get_orders()` (aman untuk HPOS, tidak query tabel order langsung), di-cache 12 jam.
* Cron kedua `aksara_cleanup_download_tokens` (bersama `aksara_cleanup_preview_cache` yang sudah ada) — saat ini biasanya no-op karena token tidak diberi kedaluwarsa otomatis (lisensi yang sudah dibeli tidak semestinya berhenti bisa diunduh), tapi siap dipakai begitu ada kebijakan retensi.

== Cakupan Fase 4 (ditambahkan di atas Fase 1-3) ==

* **SEO**: meta description & Open Graph tags (tema, `inc/seo.php`) — meta `<title>`, `rel=canonical`, sitemap XML, dan structured data Product untuk semua jenis produk (termasuk font, lewat `WC_Product_Font::get_price()` yang sudah kompatibel) ternyata **sudah otomatis** ditangani WordPress core & WooCommerce core sejak Fase 1, tidak butuh kode tambahan — didokumentasikan di `inc/seo.php` supaya tidak ada yang mencoba membangunnya ulang.
* **Performa**: `aksara_count_products_by_type()` & `aksara_get_listing_url()` (dipanggil tiap load Home) sekarang di-cache pakai transient, dibersihkan otomatis saat produk/halaman terkait disimpan.
* **Aksesibilitas**: warna `--ochre` untuk teks harga digelapkan (gagal WCAG AA 2.9-3.5:1 di versi mockup asli → sekarang 5.9-6.5:1), indikator fokus keyboard (`:focus-visible`) konsisten di seluruh situs, kontrol typing tool (weight tabs, italic toggle, pilihan lisensi) diberi `aria-pressed`/`role="radio"`/label yang sesuai — opsi lisensi diubah dari `<div>` jadi `<button>` supaya benar-benar bisa dioperasikan lewat keyboard.
* **Responsif**: tabel WooCommerce (cart/checkout/My Account) bisa di-scroll horizontal alih-alih merusak layout di layar sempit; sidebar kalkulator lisensi berhenti "menempel" (sticky) saat ditumpuk ke 1 kolom di mobile.
* **Monitoring** (`class-error-logger.php`): setiap `WP_Error` dari endpoint `aksara/v1` & setiap order yang jatuh ke status *failed* otomatis dicatat (error_log + action hook `aksara_error`) — titik ekstensi siap pakai untuk Sentry PHP SDK atau layanan monitoring lain tanpa plugin ini membundel SDK apa pun.
* **Load test nyata** terhadap `font-preview-service` (lihat readme-nya): ditemukan & diperbaiki bug performa (dev server Flask single-threaded bikin request antre), dan didokumentasikan kenapa deployment produksi butuh WSGI multi-process (gunicorn), bukan sekadar multi-thread.
* Draft konten blog awal (font pairing, tips memilih font, tutorial Canva, panduan lisensi) di `content/blog/` — siap disalin ke Posts, environment ini tidak punya database WordPress aktif untuk diisi langsung.
* Rencana uji manual (`docs/QA-TEST-PLAN.md`) untuk item testing yang butuh WordPress+WooCommerce+PayPal sungguhan (kombinasi style/lisensi, kuota unduhan, alur pembayaran) — tidak bisa dijalankan otomatis di environment development ini.

## Yang BELUM ada (menyusul di fase lanjut sesuai Breakdown Task)

* Testing end-to-end sungguhan di browser (lihat `docs/QA-TEST-PLAN.md` — perlu staging WordPress+WooCommerce+PayPal aktif).
* Multi-vendor, integrasi Canva API resmi, multi-bahasa (Fase 5, opsional sesuai keputusan produk).
* Deployment produksi microservice sebagai systemd unit — lihat readme di `services/font-preview-service/` (perintah gunicorn-nya sudah diverifikasi lewat load test).

== Instalasi ==

1. Pastikan WooCommerce aktif terlebih dahulu.
2. Unggah folder `aksara-marketplace` ke `/wp-content/plugins/`.
3. Aktifkan plugin — tabel database & folder privat (termasuk `certificates/`) dibuat otomatis saat aktivasi.
4. Jalankan `services/font-preview-service/` (lihat readme-nya) dengan `AKSARA_FONT_STORAGE_DIR` mengarah ke `wp-content/uploads/aksara-private` situs ini — tanpa ini, typing tool akan menampilkan pesan "pratinjau tidak tersedia".
5. Buka **WooCommerce > Lisensi Font** untuk memeriksa/menyunting 5 jenis lisensi default yang sudah diisi otomatis (Desktop, Web, Aplikasi, E-book, Komersial Lanjutan).
6. Tambah produk baru → set **Product type** ke "Font (Aksara)" → isi style lewat metabox **Font Styles** → atur harga per lisensi → (opsional) atur diskon paket lengkap.
7. Untuk Canva Template/Element: set **Product type** sesuai, isi harga seperti simple product biasa, lalu lengkapi metabox **Info Canva**.
8. Setelah plugin diperbarui dari versi sebelumnya (bukan instalasi baru), buka **Pengaturan > Permalink** sekali dan klik Simpan supaya tab My Account baru (Unduhan Saya, dst.) langsung bisa diakses tanpa 404 — plugin sudah mencoba melakukan ini otomatis, langkah ini cuma jaring pengaman.

== Changelog ==

= 0.4.0 =
* Fase 4: SEO (meta description/OG, verifikasi schema & sitemap bawaan), cache performa, perbaikan aksesibilitas (kontras, focus-visible, keyboard), tabel responsif, logging monitoring dengan titik ekstensi Sentry, load test microservice, draft konten blog, rencana uji manual.

= 0.3.0 =
* Fase 3: token unduh aman, sertifikat lisensi PDF (penulis PDF sendiri, tanpa dependency), 3 tab My Account baru, wishlist, tautan unduh & lampiran PDF di email order, widget dashboard admin, cron cleanup token.

= 0.2.0 =
* Fase 2: REST API preview & cart, typing tool interaktif, kalkulator multi-style + diskon paket lengkap, rate limiting persisten, cron cleanup cache.

= 0.1.0 =
* Fase 1: fondasi product type, database, metabox admin, dan alur beli dasar.
