=== Aksara Marketplace ===
Contributors: aksara
Tags: woocommerce, marketplace, fonts, digital downloads
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Marketplace WooCommerce pendamping Authentype untuk Canva Template, Canva Element, wishlist, akun, dan storefront bersama. Sejak 0.8.0, Authentype adalah satu-satunya pemilik katalog, preview, harga, variation, cart, dan delivery produk font.

Engine font lama Aksara tetap tersimpan untuk kompatibilitas data historis, tetapi tidak dimuat pada mode default 0.8.0. Ini mencegah dua generator font mengelola produk yang sama. Python Font Preview Service tidak diperlukan pada mode Authentype karena preview dirender menjadi PNG oleh Authentype di server.

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
* **Aksesibilitas**: warna `--ochre` untuk teks harga digelapkan (gagal WCAG AA 2.9-3.5:1 di versi mockup asli → 5.9-6.5:1; sejak v0.5.0 palet berwarna diganti monokrom, lihat di bawah), indikator fokus keyboard (`:focus-visible`) konsisten di seluruh situs, kontrol typing tool (weight tabs, italic toggle, pilihan lisensi) diberi `aria-pressed`/`role="radio"`/label yang sesuai — opsi lisensi diubah dari `<div>` jadi `<button>` supaya benar-benar bisa dioperasikan lewat keyboard.
* **Responsif**: tabel WooCommerce (cart/checkout/My Account) bisa di-scroll horizontal alih-alih merusak layout di layar sempit; sidebar kalkulator lisensi berhenti "menempel" (sticky) saat ditumpuk ke 1 kolom di mobile.
* **Monitoring** (`class-error-logger.php`): setiap `WP_Error` dari endpoint `aksara/v1` & setiap order yang jatuh ke status *failed* otomatis dicatat (error_log + action hook `aksara_error`) — titik ekstensi siap pakai untuk Sentry PHP SDK atau layanan monitoring lain tanpa plugin ini membundel SDK apa pun.
* **Load test nyata** terhadap `font-preview-service` (lihat readme-nya): ditemukan & diperbaiki bug performa (dev server Flask single-threaded bikin request antre), dan didokumentasikan kenapa deployment produksi butuh WSGI multi-process (gunicorn), bukan sekadar multi-thread.
* Draft konten blog awal (font pairing, tips memilih font, tutorial Canva, panduan lisensi) di `content/blog/` — siap disalin ke Posts, environment ini tidak punya database WordPress aktif untuk diisi langsung.
* Rencana uji manual (`docs/QA-TEST-PLAN.md`) untuk item testing yang butuh WordPress+WooCommerce+PayPal sungguhan (kombinasi style/lisensi, kuota unduhan, alur pembayaran) — tidak bisa dijalankan otomatis di environment development ini.

## Yang BELUM ada (menyusul di fase lanjut sesuai Breakdown Task)

* Testing end-to-end sungguhan di browser (lihat `docs/QA-TEST-PLAN.md` — perlu staging WordPress+WooCommerce+PayPal aktif).
* Multi-vendor, integrasi Canva API resmi, multi-bahasa (Fase 5, opsional sesuai keputusan produk).
* Deployment produksi microservice sebagai systemd unit — lihat readme di `services/font-preview-service/` (perintah gunicorn-nya sudah diverifikasi lewat load test).

== Optimasi ketergantungan microservice (pasca Fase 4) ==

Sebelumnya seluruh tampilan font bergantung pada microservice Python; kalau mati, halaman produk cuma menampilkan pesan "pratinjau tidak tersedia". Sekarang:

* **`class-specimen-image.php`** merender teks memakai berkas font asli jadi PNG lewat PHP GD + FreeType — **tanpa Python sama sekali**. Aman ditaruh di folder publik karena gambar raster tidak mengandung data font (ini yang diminta PRD Bagian 4.3 poin 3 untuk mode display, dan sebelumnya terlewat).
* **Listing & Home kini menampilkan nama font dalam font aslinya** sebagai gambar — sesuai maksud mockup, tanpa pernah mengirim berkas font ke browser.
* **Typing tool punya fallback**: kalau microservice tidak bisa dihubungi, pengunjung melihat gambar specimen statis + keterangan, bukan pesan error kosong.
* **Specimen dibuat saat admin mengunggah style**, bukan saat pengunjung pertama membuka halaman (render GD makan puluhan milidetik per style — untuk family belasan style, itu tidak pantas ditanggung satu pengunjung).
* **Halaman WooCommerce > Status Layanan Aksara** + notice admin memakai `is_healthy()` yang sejak Fase 2 didefinisikan tapi tidak pernah dipanggil dari mana pun.
* Berkas .woff2/.woff tidak bisa dirender FreeType — style yang diunggah dalam format itu otomatis mundur ke teks biasa (bukan error).

== Instalasi ==

1. Aktifkan WooCommerce.
2. Instal dan aktifkan **Authentype Font Specimen Commerce**.
3. Instal dan aktifkan **Aksara Marketplace 0.8.0**.
4. Instal dan aktifkan **Aksara Theme 0.8.0**.
5. Buat semua produk font melalui menu **Athtyp** dan gunakan Build/Pricing/Woo Sync Authentype.
6. Buat produk Canva melalui WooCommerce dengan type Canva Template/Element (Aksara).
7. Simpan ulang **Settings > Permalinks** sekali. Katalog font canonical tersedia pada archive `ath_font` (default `/font-shop/`).
8. Jangan membuat produk baru dengan legacy type `Font (Aksara)`; type tersebut sengaja tidak didaftarkan pada mode Authentype.

== Changelog ==

= 0.8.0 =
* Added automatic Authentype mode: Authentype becomes the sole font catalog/commerce/preview owner while Aksara continues Canva commerce, preventing duplicate font product types and admin workflows.

= 0.7.0 =
* Product editor fixes, publish-readiness validation, verified font uploads, recoverable preview fallback, explicit preview-service errors, atomic download limits, reliable token generation, Canva host validation, and product-file lifecycle cleanup.

= 0.4.0 =
* Fase 4: SEO (meta description/OG, verifikasi schema & sitemap bawaan), cache performa, perbaikan aksesibilitas (kontras, focus-visible, keyboard), tabel responsif, logging monitoring dengan titik ekstensi Sentry, load test microservice, draft konten blog, rencana uji manual.

= 0.3.0 =
* Fase 3: token unduh aman, sertifikat lisensi PDF (penulis PDF sendiri, tanpa dependency), 3 tab My Account baru, wishlist, tautan unduh & lampiran PDF di email order, widget dashboard admin, cron cleanup token.

= 0.2.0 =
* Fase 2: REST API preview & cart, typing tool interaktif, kalkulator multi-style + diskon paket lengkap, rate limiting persisten, cron cleanup cache.

= 0.1.0 =
* Fase 1: fondasi product type, database, metabox admin, dan alur beli dasar.

== v0.5.0 — UI monokrom & perbaikan sistem admin ==

=== Sisi etalase (mengikuti DESIGN.md) ===

* CSS typing tool & wishlist ditulis ulang monokrom. Fallback `var(--indigo,
  #33417A)` / `var(--ochre, #835420)` dari palet lama dihapus: karena tema
  v0.5.0 tidak lagi mendefinisikan token itu, SETIAP fallback tersebut akan
  aktif dan mengembalikan warna yang justru baru dihapus — bug diam-diam,
  bukan sekadar kode mati.
* **Tombol wishlist**: glifnya kini berbeda antar status (hati penuh vs hati
  kosong) dan membawa `aria-pressed`, bukan lagi dibedakan warna merah saja.
  Tanpa ini statusnya jadi tidak terlihat sama sekali di palet monokrom;
  bentuk juga lebih baik daripada warna untuk pengguna buta warna.
* **Slider ukuran** di typing tool sekarang menampilkan nilainya (`<output>`).
  Sebelumnya tidak ada pembacaan nilai sama sekali.
* Pesan sukses/gagal tidak lagi memakai warna sebagai pembeda — memakai garis
  tinta di kiri + berat huruf (sekaligus memenuhi WCAG 1.4.1).

=== Sisi admin ===

wp-admin SENGAJA tidak diubah jadi monokrom: DESIGN.md adalah acuan etalase,
sementara dasbor punya bahasa visual WordPress yang sudah dikenal admin.
Yang diperbaiki di sini fungsinya, bukan gayanya.

* **PERBAIKAN BUG KRITIS — bulk upload font tidak pernah berfungsi.** Form
  editor post bawaan WordPress dicetak tanpa `enctype="multipart/form-data"`,
  sehingga browser hanya mengirim NAMA berkas dan `$_FILES` selalu kosong.
  Admin memilih berkas, menekan Update, halaman tersimpan tanpa error, dan
  tidak ada satu pun style bertambah. Diperbaiki lewat hook
  `post_edit_form_tag` di `Aksara_Admin_UI`.
* **Kegagalan unggah kini dilaporkan.** Sebelumnya setiap kondisi gagal
  memakai `continue` polos: berkas kebesaran atau format salah menghilang
  tanpa jejak. Sekarang tiap berkas gagal disebut beserta sebabnya (termasuk
  batas ukuran server yang sebenarnya), dan jumlah yang berhasil dikonfirmasi.
* **Pratinjau spesimen di tabel style.** Admin melihat wujud tiap style
  memakai mesin render yang sama dengan etalase — termasuk saat sebuah berkas
  TIDAK bisa dirender (mis. .woff2), yang dulu baru ketahuan setelah terbit.
* **Style tanpa harga ditandai.** Style yang tidak punya harga untuk lisensi
  mana pun tidak akan pernah bisa dibeli, dan sebelumnya kondisi itu tidak
  terlihat sama sekali dari tabel yang penuh kotak kosong.
* **Notice setelah simpan.** Halaman Lisensi Font dulu redirect tanpa kabar
  apa pun; sekarang ada konfirmasi tersimpan/terhapus, dan validasi gagal
  dicetak inline (bukan diantrekan — `render_page()` berjalan setelah hook
  `admin_notices`, jadi notice yang diantrekan akan tertunda satu halaman).
* **Seluruh style inline dipindah** ke `assets/css/admin.css` (bisa di-cache
  browser, tidak lagi dikirim ulang di tiap pemuatan layar editor).

== v0.5.1 — kuota codepoint per style (menutup serangan gabung-subset) ==

Rate limit per menit ternyata tidak cukup melindungi berkas font. Setiap
respons pratinjau adalah subset berisi glyph yang diketik; masing-masing tidak
berguna, tapi beberapa subset bisa DIGABUNG kembali jadi font yang makin
lengkap. Diukur dengan font contoh di repo (Bricolage Grotesque, 527
codepoint): pada batas 100 karakter/request, seluruh charset bisa dipanen
hanya dengan 6 request — sekitar 9 detik di bawah rate limit 40/menit.

`Aksara_Rest_Controller::check_codepoint_budget()` sekarang membatasi jumlah
codepoint BERBEDA yang boleh diterima satu klien untuk satu style dalam 24
jam (default 120). Angkanya dipilih dari pengukuran, bukan tebakan:

  kalimat pendek biasa ........ 13 codepoint unik
  pangram Inggris ............. 28
  pangram + KAPITAL + angka ... 50
  seluruh ASCII tercetak ...... 95
  ------------------------------------
  batas .......................120
  seluruh charset font ........527  <- ini yang dilindungi

Detail perilaku:

* Disimpan sebagai gabungan himpunan, bukan penghitung — mengetik ulang teks
  yang sama tidak menambah kuota sama sekali (diuji: 20x ketik ulang, kuota
  tetap 55/120). Yang dihitung hanya karakter yang benar-benar baru.
* Kuota terpisah per style DAN per klien.
* Diperiksa sebelum cache subset, karena cache bersifat global lintas
  pengunjung — klien yang kena cache hit tetap baru pertama kali menerima
  glyph tersebut.
* Endpoint batch mengembalikan error kuota hanya kalau TIDAK ADA satu pun
  style yang bisa dilayani. Kalau ikut dilewati `continue` seperti error lain,
  responsnya jadi 200 dengan hasil kosong dan typing tool diam tanpa
  penjelasan — terlihat seperti kerusakan.
* Typing tool TIDAK dikunci saat kuota habis: karakter yang sudah pernah
  dipakai tetap bisa dirender, jadi mengunci `contenteditable` akan mengambil
  kemampuan yang sebenarnya masih ada. Yang tampil hanya pesan penjelas.
* Bisa disetel/dimatikan lewat filter `aksara_preview_codepoint_budget`.

Hasil: pemanen hanya mendapat ~19% charset per IP per hari (dari sebelumnya
100% dalam 9 detik), dan 432 codepoint di luar ASCII — aksen, simbol, mata
uang, yang justru jadi nilai jual font — tetap di luar jangkauan.

== v0.6.0 — audit alur pembuatan produk + seluruh UI jadi bahasa Inggris ==

=== Bahasa ===

Seluruh teks yang dilihat pengunjung & admin (233 string) diterjemahkan ke
bahasa Inggris: label, tombol, pesan error, notice admin, email order,
sertifikat PDF, dan header plugin. Komentar kode & dokumen internal tetap
bahasa Indonesia sesuai keputusan. Bentuk jamak `_n()` ikut diperbaiki —
beberapa sebelumnya memakai teks yang sama untuk tunggal & jamak, jadi
"2 style" tidak akan pernah jadi "2 styles".

=== Hasil audit alur pembuatan produk ===

Dua di antaranya membuat alur pembuatan produk TIDAK BISA diselesaikan sama
sekali:

1. **Produk Canva tidak bisa diberi harga (kritis).** WooCommerce menampilkan
   dan menyembunyikan panel "Product data" murni lewat class `show_if_<type>`,
   dan core hanya memasang class itu untuk type bawaannya. Kelompok harga
   ditandai `show_if_simple show_if_external`, jadi untuk slug `canva_template`
   / `canva_element` field Regular price TIDAK PERNAH muncul — admin secara
   harfiah tidak bisa memasang harga, dan produknya tidak bisa dibeli.
   Diperbaiki lewat `assets/js/admin-product.js`.

2. **Tidak ada produk yang virtual (kritis).** `WC_Product::needs_shipping()`
   hanyalah `!is_virtual()`, dan checkbox "Virtual" yang biasanya mengatur itu
   juga bertanda `show_if_simple` — jadi tidak ada yang bisa mencentangnya.
   Akibatnya checkout meminta alamat pengiriman untuk berkas yang dikirim lewat
   tautan unduh. Sekarang ketiga product type meng-override `get_virtual()` &
   `needs_shipping()` di PHP: lebih jujur daripada checkbox, karena tidak ada
   konfigurasi di mana produk ini butuh pengiriman. Sengaja TIDAK ditandai
   downloadable — mekanisme unduhan WooCommerce memang dilewati demi token
   di `Aksara_Download_Manager`.

3. **Jalan buntu di produk baru.** Metabox membaca product type dari term yang
   TERSIMPAN. Di "Add new product" term itu belum ada, dan mengubah dropdown
   tidak memunculkan metabox sampai disimpan — sementara pesannya berbunyi
   seolah cukup mengubah dropdown. Pesannya kini menyebut langkah simpannya.

4. **Perubahan type pertama kali bisa hilang diam-diam.** WordPress memicu
   `save_post_{post_type}` SEBELUM `save_post`, sedangkan WooCommerce menulis
   term product_type dari handler `save_post`-nya sendiri. Jadi metabox yang
   menyimpan di `save_post_product` masih membaca type LAMA.
   `get_current_product_type()` kini mendahulukan `$_POST['product-type']`.

5. **Jalan buntu tanpa jalan keluar.** Pesan "belum ada jenis lisensi" kini
   disertai tombol menuju halamannya, bukan menyuruh admin mencari sendiri.

6. **Produk bisa terbit tanpa isi.** Produk font yang publish tanpa satu pun
   harga tidak bisa dibeli, dan produk Canva yang publish tanpa tautan membuat
   pembeli membayar lalu tidak menerima apa pun. Keduanya kini diberi peringatan
   di metabox — sebelumnya sama sekali tidak ada tanda di wp-admin.

Juga: panel Product data untuk type Font kini menjelaskan bahwa harga font
memang tidak diatur di situ, melainkan dari matriks style x lisensi di bawah.

== v0.8.1 — dua perbaikan audit dipasang ulang ==

Kedua hal ini pernah ada di repo lalu tidak ikut terbawa ke paket 0.8.0.
Dipasang ulang di commit terpisah supaya mudah dibedakan dari kode lain.

* **Token unduhan tidak lagi tertulis ke log.** Aksara_Error_Logger mencatat
  rute REST apa adanya, dan rute unduhan berisi token bearer 48-hex —
  kredensial yang cukup untuk mengunduh berkas. Bukan hanya token mati:
  error `aksara_missing_resource` justru terjadi pada token yang MASIH
  berlaku. Docblock log() di berkas yang sama sudah lama meminta "jangan
  kirim token mentah"; rute inilah yang diam-diam melanggarnya. Kini
  diredaksi sebelum masuk debug.log maupun hook `aksara_error` (Sentry).

* **Keterbukaan folder privat kini benar-benar diuji, bukan diasumsikan.**
  Proteksinya hanya .htaccess, yang Nginx abaikan sepenuhnya. Di stack
  Nginx berkas font berbayar bisa diunduh siapa saja yang menebak URL-nya,
  tanpa gejala apa pun dari dalam WordPress — kondisi paling merusak di
  sistem ini sekaligus yang paling sunyi. Komentar peringatan di
  class-file-storage.php sudah ada sejak awal dan tidak menolong siapa pun.
  `Aksara_Service_Health::is_private_dir_exposed()` menulis berkas umpan
  lalu MENGAMBILNYA lewat URL publik; kalau isinya kembali, admin diberi
  notice error di seluruh layar wp-admin plus aturan Nginx siap salin di
  halaman Status. Loopback yang gagal dilaporkan "tidak bisa dipastikan",
  bukan "aman".

Diuji dengan stub WordPress + reflection: redaksi token untuk rute unduhan
(dan rute lain tidak ikut berubah), probe keterbukaan untuk kelima kondisi
(200+umpan, 403, 404, 200 isi lain, loopback gagal), serta perilaku cache.
Penjaga traversal di get_absolute_path() versi 0.8.0 ikut diuji dan lolos
untuk empat pola path.
