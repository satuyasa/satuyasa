=== Aksara ===
Contributors: aksara
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tema marketplace font, Canva Template & Canva Element untuk Aksara. Full-custom (bukan starter theme), dibangun langsung mengikuti `mockup-home.html` dan `mockup-font-product.html`. Butuh plugin **Aksara Marketplace** aktif untuk fitur penuh (masih tampil tanpa plugin, hanya kehilangan fitur khusus marketplace).

== Cakupan Fase 1 (fondasi) ==

* Palet & tipografi awal mengikuti mockup (Fraunces + Inter, palet hangat). DIGANTI di v0.5.0 — lihat bagian "Sistem visual" di bawah.
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

## Catatan keamanan: bagaimana Home menampilkan font asli dengan aman

Daftar "Font pilihan" di Home & halaman Fonts kini menampilkan nama font dalam font ASLINYA — tapi sebagai **gambar hasil render server** (PHP GD, lihat `aksara_font_specimen()` dari plugin), bukan dengan memuat berkas font ke browser lewat `@font-face`. Yang sampai ke pengunjung cuma piksel; berkas fontnya tidak pernah meninggalkan server.

Ini berbeda dari versi awal tema yang sengaja memakai font tema karena saat itu belum ada mekanisme render gambar yang aman. Yang tetap TIDAK boleh dilakukan: memasang berkas font produk lewat `@font-face` publik di listing — itu membocorkannya utuh tanpa melewati mekanisme perlindungan apa pun.

Kalau specimen tidak bisa dibuat (style diunggah sebagai .woff2 yang tidak terbaca FreeType, atau GD tidak tersedia di server), baris listing otomatis mundur ke teks biasa dalam font tema.

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
* Produk font dan preview membutuhkan plugin Authentype. Preview archive dikirim sebagai raster PNG/canvas; font asli tidak dimuat oleh theme.
* Testing end-to-end sungguhan di browser (responsive, keyboard, screen reader) — lihat checklist di `docs/QA-TEST-PLAN.md` (perlu staging WordPress aktif, tidak bisa dijalankan otomatis di environment development ini).

== Instalasi ==

1. Aktifkan WooCommerce, Authentype, dan plugin **Aksara Marketplace** terlebih dahulu.
2. Unggah folder `aksara` ke `/wp-content/themes/`, lalu aktifkan.
3. Archive font dibuat otomatis oleh CPT Authentype (default `/font-shop/`). Page manual hanya diperlukan untuk Templates/Elements/License.
4. Atur halaman-halaman itu di **WooCommerce > Pengaturan > Halaman Muka** / menu navigasi sesuai kebutuhan.
5. Buat font lewat Authentype; buat Canva Template/Element lewat Aksara Marketplace. Keduanya otomatis muncul di storefront yang sesuai.

== Sistem visual (v0.5.0) — monokrom, mengikuti DESIGN.md ==

Tema ini sekarang mengikuti DESIGN.md ("Studio Few — Style Reference"), yang
menggantikan bahasa visual hangat dari mockup awal. Aturan yang dipegang:

* **Tanpa warna kromatik sama sekali.** Hanya tinta (#000) & kertas (#fff),
  ditambah abu-abu netral untuk hairline. Palet lama (indigo #33417A, ochre
  #835420, teal, paper #EDEBE3) sudah dihapus seluruhnya — termasuk fallback
  `var(--indigo, ...)` di CSS plugin, yang kalau dibiarkan justru akan aktif
  dan mengembalikan warna itu karena tokennya tidak lagi didefinisikan tema.
  Diverifikasi dengan memotret halaman di browser lalu mengaudit pikselnya:
  0 piksel berwarna setelah antialiasing subpiksel dimatikan.
* **Kedalaman dari hairline, bukan shadow.** Tidak ada box-shadow elevasi,
  gradient, atau card. Baris spesimen dipisah garis 1px.
* **Radius tepat dua nilai**: 6px untuk tombol, 2px untuk sisanya.
* **Satu pola tombol**: `.btn-trial` (outline, tidak pernah terisi bahkan saat
  hover) berpasangan dengan `.btn-view` (isi tinta penuh).
* **Tipografi**: satu keluarga UI. Sterling disebut lebih dulu di `--font-ui`
  supaya otomatis mengambil alih kalau berkasnya dipasang; Work Sans dimuat
  sebagai failsafe sesuai perannya di DESIGN.md.

Penyimpangan yang disengaja dari DESIGN.md, beserta alasannya:

* Token Ash `#858585` TIDAK dipakai untuk teks kecil. Di atas kertas putih ia
  cuma 3,69:1, sedangkan DESIGN.md menugaskannya ke label 12-14px — persis
  kategori yang WCAG AA minta 4,5:1. Teks redup memakai `--color-ash-text`
  (#767676 = 4,54:1); Ash tetap tersedia untuk elemen non-teks.
* Status sukses/gagal tidak dibedakan lewat warna (DESIGN.md melarang merah &
  hijau). Pembedanya: garis tinta di kiri + berat huruf. Ini juga yang diminta
  WCAG 1.4.1, jadi bukan kompromi.
* Spesimen di listing dirender 115px (token `--text-display`), bukan 158px.
  Alasannya bukan selera: gambarnya PNG hasil render server, dan pada 158px
  dengan skala 2x satu nama font panjang menghasilkan PNG ~4000px (≈90 KB)
  per baris — diukur, bukan diperkirakan. Pada 115px jadi 2968px/51 KB.
* Ukuran display memakai clamp() dengan batas bawah 40px. DESIGN.md menyebut
  skala tetap, tapi teks hero bisa disunting admin lewat Customizer sehingga
  panjang katanya tidak bisa dijamin; batas bawah menjaga kata terpanjang
  tetap muat di layar sempit.

Diverifikasi di browser (Chromium headless) pada lebar 375px, 768px & 1440px:
`scrollWidth == clientWidth` di ketiganya, jadi tidak ada scroll horizontal.

== v0.9.1 — halaman blog, cart, checkout, My Account ==

Sampai versi sebelumnya, seluruh halaman transaksi dan halaman blog masih
memakai markup + CSS bawaan WooCommerce/WordPress: tombol ungu #a46497,
kotak abu-abu #f7f6f7, badge diskon bulat hijau, dan blog tanpa tata letak
sidebar sama sekali. Semuanya bertabrakan dengan sistem monokrom.

Dikerjakan lewat CSS saja — woocommerce.php tetap membungkus
woocommerce_content() dan TIDAK ada template WooCommerce yang di-fork
(override template rapuh antar versi WC; keputusan lama ini dipertahankan).

* **Cart** — dua kolom (item kiri, ringkasan total kanan), tabel hairline,
  tombol hapus jadi glyph tinta, kolom kuantitas, baris kupon, tombol
  checkout penuh.
* **Checkout** — dua kolom (data pembeli kiri, ringkasan + pembayaran
  kanan), field form dengan label kapital kecil, daftar metode pembayaran
  hairline, select2 disamakan dengan input biasa.
* **My Account** — navigasi sidebar hairline dengan status aktif, area
  konten, form login/register.
* **Order received** — daftar ringkasan order sebagai baris hairline.
* **Arsip toko** — result count, dropdown urutan, paginasi, badge "Sale"
  monokrom (bukan lingkaran hijau).
* **Blog** — tata letak dua kolom saat sidebar aktif (class .has-sidebar
  sudah dicetak single.php sejak lama tapi belum pernah punya aturan CSS),
  ritme tipografi isi artikel (h2/h3/list/blockquote/kode/tabel/figure),
  navigasi antar-artikel, komentar bertingkat, dan widget.

=== Tiga bug tata letak yang ketahuan lewat pemotretan browser ===

1. **Konten menempel ke tepi layar di SEMUA halaman.** `.content-area` dan
   `.aksara-product-summary` memakai shorthand `padding: X 0 Y`, yang
   menulis ulang padding horizontal jadi 0 — padahal elemen yang sama
   membawa class `.wrap` yang seharusnya memberi gutter 24px. Diganti
   `padding-block`.
2. **Tabel keranjang cuma memakai ~45% lebar di desktop.** Aturan
   responsif lama `display:block; overflow-x:auto` berlaku di semua lebar;
   pada `<table>`, `display:block` mematikan layout tabel sehingga
   menyusut mengikuti isi. Sekarang hanya aktif di bawah 782px.
3. **Halaman cart meluber horizontal di 375px.** Kolom grid `1fr` berarti
   `minmax(auto, 1fr)` — tidak boleh lebih sempit dari min-content isinya,
   jadi tabel lebar mendorong seluruh halaman. Diganti `minmax(0, 1fr)`.

Diverifikasi di Chromium headless dengan markup WooCommerce asli:
`scrollWidth == clientWidth` untuk cart, checkout, dan blog pada 375px,
768px, dan 1440px. Palet tetap 0 warna kromatik.

== v1.0.0 — konversi penuh ke block theme (FSE) ==

Tema ini sekarang block theme. Struktur halaman pindah dari template PHP ke
`templates/*.html` + `parts/*.html`, token desain ke `theme.json`, dan bagian
yang isinya query jadi blok dinamis. Home, Page, Single, Archive, Search, 404,
serta halaman font Authentype semuanya bisa disusun lewat Site Editor.

=== Yang membuat konversi ini tidak merusak desain ===

`theme.json` mengunci sistem desainnya, bukan sekadar mendeklarasikannya:
`color.custom`, `customGradient`, `customDuotone`, dan `defaultPalette`
semuanya `false`, dan palet hanya berisi tujuh nilai akromatik. Artinya
Site Editor TIDAK bisa menyisipkan warna di luar palet — aturan "0% warna
kromatik" dari DESIGN.md jadi ditegakkan oleh sistem, bukan hanya oleh CSS
yang bisa ditimpa. `customFontSize` dan preset shadow juga dimatikan.

CSS 2.000 baris yang sudah diuji di browser TIDAK ditulis ulang. Template blok
memakai `className` yang sama persis dengan template PHP lama (`.wrap`,
`.hero`, `.specimen-list`, `.section-head`, `.entry-card`, dst.), jadi seluruh
gaya — termasuk perbaikan cart/checkout/blog di 0.9.1 — langsung berlaku.

=== Blok dinamis (inc/blocks.php) ===

Bagian yang isinya berasal dari query tidak bisa jadi blok statis. Delapan
blok merendernya di server, jadi bisa disisipkan lewat inserter tapi datanya
selalu terkini:

* Aksara: Hero — judul/subjudul kini atribut blok (bisa disunting di editor,
  menggantikan get_theme_mod() yang hanya bisa lewat Customizer), hitungan
  katalog tetap dinamis
* Aksara: Category row, Font specimen list, Canva asset grid
* Aksara: Font library (katalog Authentype + pencarian + paginasi)
* Aksara: Font product body (halaman font tunggal)
* Aksara: License list, Header actions (keranjang dengan jumlah item)

Markup render part-nya dipindahkan APA ADANYA dari template PHP lama, bukan
ditulis ulang — supaya tidak ada perilaku yang diam-diam berubah.

Sisi editornya (`assets/js/blocks.js`) ditulis dalam JavaScript biasa memakai
global `wp.*` — tanpa JSX, webpack, atau npm — konsisten dengan konvensi
proyek ini yang tidak memakai build step. Pratinjau di editor memakai
ServerSideRender, jadi yang terlihat adalah hasil render sesungguhnya.

=== Satu bug yang ditemukan saat konversi ===

`aksara_get_listing_url()` mencari halaman lewat meta `_wp_page_template`,
dan bentuk nilainya BERBEDA antara classic dan block theme
(`page-templates/template-elements.php` vs `page-elements`). Situs yang sudah
berjalan masih menyimpan nilai lama, dan nilai itu tidak ikut berubah saat
tema dikonversi — tanpa penanganan, seluruh tautan listing di hero dan kartu
kategori berubah jadi `#` setelah upgrade, rusak tanpa pesan error apa pun.
Sekarang kedua bentuk diterima.

=== Yang dihapus ===

Seluruh template PHP klasik (header/footer/index/single/page/archive/search/
404/comments/sidebar/woocommerce/front-page/single-ath_font/archive-ath_font),
`page-templates/`, `template-parts/content*.php`, `inc/template-tags.php`, dan
`assets/js/navigation.js`. WordPress mengabaikan semuanya begitu `templates/`
ada, jadi meninggalkannya hanya menyesatkan pembaca berikutnya.
`register_nav_menus()` dan `register_sidebar()` juga dilepas — menu kini blok
Navigation, widget kini blok biasa.

=== Belum bisa diuji di sini ===

Tanpa WordPress aktif, render sesungguhnya belum bisa dibuktikan. Yang sudah
diverifikasi otomatis: seluruh PHP & JS lolos lint, `theme.json` valid, markup
blok di 15 berkas template seimbang pasangan buka/tutupnya dengan JSON atribut
yang valid, seluruh `customTemplates` & `templateParts` punya berkas yang
sesuai, dan tidak ada referensi menggantung ke berkas yang dihapus.

Halaman WooCommerce (cart, checkout, My Account) kini dilayani template blok
milik WooCommerce sendiri, bukan `woocommerce.php`. CSS commerce dari 0.9.1
tetap berlaku karena berbasis class WooCommerce, tapi ini WAJIB dicek di
staging — ini perubahan perilaku terbesar dari konversi ini.

== Audit preview teks (per halaman) ==

Peta siapa yang merender preview sekarang: Authentype. Tema TIDAK lagi
memakai mesin GD di plugin aksara-marketplace (Aksara_Specimen_Image) —
mesin itu kini tidak dipanggil dari tema sama sekali. Baris spesimen memakai
<canvas class="ath-server-canvas"> yang diisi specimen.js lewat satu POST
ke admin-ajax (action ath_specimen_render_preview) yang mengembalikan PNG.

Halaman yang PUNYA preview teks:

| Halaman              | Mekanisme                          | Status |
|----------------------|------------------------------------|--------|
| Home                 | blok Font specimen list -> canvas  | ada    |
| Font library/archive | blok Font library -> canvas        | ada    |
| Halaman font tunggal | shortcode authentype_font_specimen | ada    |

Halaman yang BUTUH tapi BELUM punya:

* **Related font families** (di halaman font tunggal). font-product-card.php
  cuma menampilkan gambar galeri, atau judul biasa kalau tidak ada gambar.
  Di halaman produk font, justru di situlah pembeli membandingkan wujud
  huruf — dan tidak ada satu pun huruf yang ditampilkan.
* **Hasil pencarian.** CPT ath_font public dan ikut terindeks pencarian,
  tapi templates/search.html memakai blok core (judul + kutipan), jadi
  mencari nama font menghasilkan daftar teks polos tanpa wujud hurufnya.

Yang sudah diverifikasi BENAR (diperiksa, bukan diasumsikan):

* Handle aset 'authentype-font-specimen' yang di-enqueue tema memang handle
  yang didaftarkan Authentype.
* Konfigurasi AthSpecimen versi tema jauh lebih kecil daripada versi
  shortcode (4 kunci vs 13), dan itu TIDAK masalah: jalur render canvas di
  specimen.js hanya membaca ajaxUrl, renderNonce, i18n.loading,
  i18n.renderFailed, dan i18n.failed — persis yang disediakan tema. Kunci
  harga/keranjang hanya dipakai widget di halaman produk, tempat Authentype
  melokalkan konfigurasinya sendiri.
* Render malas aktif (canvasNearViewport, margin 420px), jadi arsip berisi
  20 baris tidak menembakkan 20 request sekaligus.
* Tinggi canvas dipesan lebih dulu (112px desktop, 84px mobile), jadi
  pergeseran tata letak saat render selesai terbatas.

Yang diperbaiki di sini:

* **Tanpa JavaScript, baris spesimen benar-benar kosong.** Cadangan
  <span class="sp-specimen-fallback"> hanya dirender kalau font tidak punya
  token — bukan kalau JS gagal. Karena spesimen adalah satu-satunya isi
  visual baris itu, JS yang diblokir membuat seluruh daftar font tampak
  kosong. Ditambahkan cadangan <noscript>. (Versi sebelum Authentype memakai
  <img> hasil render server yang selalu tampil; ketergantungan pada JS ini
  hal baru.)
* **Warna tinta menyimpang.** Baris spesimen mengirim
  data-text-color="#111111", padahal DESIGN.md menetapkan tinta hanya
  #000000 — dan cadangan .sp-specimen-fallback memakai var(--ink) = #000.
  Jadi saat canvas gagal dan cadangan muncul, warnanya berubah. Disamakan
  ke #000000.
* **CSS mati yang menyesatkan.** Aturan .sp-specimen img beserta komentar
  panjangnya masih menjelaskan Aksara_Specimen_Image::get_img_tag() yang
  tidak lagi dipakai; !important-nya melawan height inline yang sudah tidak
  ada. Diluruskan.

Yang DILAPORKAN, belum diubah, karena butuh keputusan:

* **renderNonce + cache halaman penuh.** Nonce ditanam di HTML dan berumur
  12-24 jam. Di situs dengan page cache (lazim untuk toko WooCommerce), HTML
  yang di-cache bertahan lebih lama daripada nonce-nya — begitu kedaluwarsa,
  SETIAP preview katalog gagal dengan "Preview unavailable" sampai cache
  dibersihkan, tanpa gejala lain. Perbaikannya menyangkut postur keamanan
  endpoint render (mis. melepas nonce untuk preview katalog publik, atau
  menyegarkan nonce lewat endpoint ringan), jadi itu keputusan Anda.
  -> DITUTUP di v1.0.2 dengan cara ketiga: umur nonce diperpanjang khusus
  untuk action ini, tanpa melepas nonce sama sekali.

== v1.0.1 — dua celah preview teks ditutup ==

Menindaklanjuti audit preview teks, dua halaman yang butuh preview tapi
belum punya kini punya:

* **Related font families** (halaman font tunggal). Kartu di
  `font-product-card.php` sebelumnya cuma menampilkan gambar galeri, atau
  judul dalam font TEMA kalau gambar itu tidak ada — jadi tidak ada satu pun
  huruf keluarga terkait yang benar-benar terlihat, padahal di situlah
  pembeli membandingkan. Ditambahkan baris spesimen ringkas memakai mesin
  render yang sama dengan katalog (canvas + token Authentype), dengan tinggi
  dipesan lebih dulu supaya kartu tidak melompat. Canvas diberi
  `aria-hidden` karena nama keluarganya sudah dibacakan lewat `<h3>` di
  atasnya.

* **Hasil pencarian.** Blok Query Loop bawaan merender satu markup yang sama
  untuk semua hasil, jadi mencari nama font menghasilkan daftar teks polos.
  Blok baru `aksara/search-results` memakai query utama lalu memilih markup
  per jenis: font memakai baris spesimen yang sama dengan katalog, sisanya
  kartu entri biasa, dengan judul pengelompokan dan paginasi yang tetap
  mengikuti hitungan query utama.

Blok baru ini ikut terdaftar di inserter (kategori Aksara), jadi bisa
dipindah atau dipakai ulang lewat Site Editor seperti blok tema lainnya.


== v1.0.2 - umur nonce preview & perbaikan clobber AthSpecimen ==

Dua perbaikan, keduanya sepenuhnya di sisi tema. Tidak ada file plugin
Authentype yang disentuh, supaya update plugin tidak menimpanya.

**1. Preview katalog gagal di situs dengan full-page cache.**

Ini temuan terakhir dari audit v1.0.1 yang tadinya hanya dilaporkan.
renderNonce dicetak ke dalam HTML halaman. Nonce WordPress default berumur
1 hari dan efektifnya 12-24 jam (verifikasi menerima tick sekarang + tick
sebelumnya). Full-page cache seperti WP Rocket, LiteSpeed, Varnish, atau
Cloudflare APO menyajikan HTML yang sama jauh lebih lama dari itu, sehingga
setiap canvas preview di katalog gagal dengan "Preview unavailable" sampai
cache dibersihkan manual - tanpa error lain yang terlihat.

Perbaikannya satu filter nonce_life di inc/authentype-integration.php yang
menyaring berdasarkan $action:

* ath_specimen_render_preview -> 30 hari (jaminan minimum 15 hari), bisa
  diubah lewat filter aksara_specimen_nonce_ttl.
* ath_specimen_cart -> 7 hari, bisa diubah lewat filter
  aksara_specimen_cart_nonce_ttl. Nonce keranjang kena masalah cache yang
  sama: tombol Add to cart gagal diam-diam di halaman produk yang di-cache.
  Umurnya sengaja jauh lebih pendek karena ini action yang mengubah state.
* Action lain (wp_rest, nonce admin, dan seterusnya) tidak tersentuh.

Kenapa memperpanjang umur nonce render aman: nonce itu sudah dicetak di HTML
publik pada setiap halaman katalog, jadi ia tidak pernah rahasia; endpointnya
read-only (hanya menghasilkan PNG); dan endpoint tetap punya pertahanan yang
tidak bergantung nonce, yaitu rate limit per-IP plus pemeriksaan post bertipe
ath_font berstatus publish. Yang melebar hanya jendela replay endpoint gambar
publik.

Kenapa filternya harus berlaku untuk pembuatan DAN verifikasi: ada dua tempat
pembuatan nonce render (tema, dan shortcode plugin) dan tiga tempat
verifikasi. Kalau umurnya hanya dilonggarkan saat request AJAX, pembuatan
tetap pakai umur default sementara verifikasi pakai umur panjang; ticknya
tidak akan pernah cocok dan SEMUA preview langsung gagal. Simulasi tick
mengonfirmasi ini gagal pada hari ke-0. Karena filter nonce_life bersifat
global dan hanya disaring lewat $action, kedua tempat pembuatan dan ketiga
tempat verifikasi otomatis sepakat.

Catatan versi WordPress, disebut apa adanya: $action baru diteruskan ke
filter nonce_life pada WordPress yang wp_nonce_tick()-nya menerima argumen.
Versi persisnya tidak bisa diverifikasi dari lingkungan kerja ini (akses
wordpress.org diblokir), jadi kodenya tidak menebak nomor versi - ia
memeriksa runtime lewat ReflectionFunction. Pada WordPress lama filter ini
mengembalikan umur apa adanya (fail-safe: tidak ada yang rusak, tapi bug
cache di atas juga belum tertutup). Statusnya dilaporkan di
Tools > Site Health > Info > Aksara theme, lengkap dengan saran menurunkan
TTL page cache di bawah 12 jam kalau tidak didukung.

**2. Data AthSpecimen milik plugin tertimpa oleh tema (regresi v1.0.1).**

Ditemukan saat menelusuri jalur nonce di atas. wp_localize_script() tidak
menggabungkan data: ia merangkai ulang blok sebelumnya lalu menambahkan
"var AthSpecimen = {...};" yang baru, jadi deklarasi terakhir menang.

Di halaman produk font, shortcode [authentype_font_specimen] melokalisasi
lebih dulu (blok authentype-single baris 62), lalu kartu "Related font
families" (baris 63) - yang baru ditambahkan di v1.0.1 - memanggil
aksara_authentype_enqueue_preview() dan melokalisasi lagi. Objek tema hanya
berisi ajaxUrl, renderNonce, dan i18n preview, sehingga nonce keranjang,
cartUrl, format mata uang, batas multi-style, dan seluruh i18n keranjang
milik plugin ikut hilang. Akibatnya tombol Add to cart gagal di SETIAP
halaman produk font.

Sekarang tema memeriksa dulu apakah AthSpecimen sudah dilokalisasi pada
handle skrip itu; kalau sudah, tema tidak menyentuhnya (data plugin sudah
memuat renderNonce yang dibutuhkan). Di halaman katalog, arsip, dan hasil
pencarian - tempat plugin tidak ikut jalan - tema tetap mengisinya sendiri.

**Verifikasi.** Keduanya diuji dengan harness PHP yang meniru mekanika
aslinya: simulasi tick nonce membuktikan preview bertahan 20 hari lalu
kedaluwarsa di hari ke-31, nonce keranjang bertahan 3 hari lalu kedaluwarsa
di hari ke-8, action lain tetap 1 hari, dan pemanggilan tanpa $action tidak
mengubah apa pun. Simulasi semantik WP_Scripts::localize() membuktikan nonce
keranjang memang HILANG tanpa guard dan kembali utuh dengannya, pada ketiga
urutan pemanggilan. Yang belum bisa diuji dari sini tetap sama seperti
sebelumnya: tidak ada runtime WordPress/WooCommerce/MySQL, jadi render
sebenarnya masih perlu dicek di staging.


== v1.0.3 - hasil audit menyeluruh ==

Audit seluruh repo di delapan dimensi. Yang bersih dan tidak disentuh:
escaping (nol echo tanpa esc_* di seluruh tema), SQL (semua nilai dinamis
lewat $wpdb->prepare), palet monokrom (dua hex non-akromatik yang tersisa
cuma ada di dalam komentar), string UI (tema sudah penuh Inggris), registrasi
blok (9 blok cocok persis antara PHP, blocks.js, dan templates), referensi
template-part, invalidasi transient (keempatnya punya hook), dan izin REST.

Yang diperbaiki di tema:

* **Requires at least dinaikkan 6.0 -> 6.6.** theme.json memakai version 3,
  yang baru dimengerti WordPress 6.6. Menyatakan 6.0 berarti tema ini boleh
  dipasang di WordPress yang tidak akan menerapkan sebagian sistem desainnya.
  Diubah di style.css dan readme.txt ini; Tested up to memang sudah 6.6.
* **Kategori pattern 'aksara' tidak pernah didaftarkan.** patterns/section.php
  dan patterns/trust.php sama-sama mendeklarasikan "Categories: aksara", tapi
  yang ada di functions.php cuma filter block_categories_all - registry
  kategori BLOK, bukan kategori PATTERN. Keduanya registry terpisah, jadi
  kedua pattern tidak pernah muncul sebagai tab tersendiri di inserter.
  Ditambahkan register_block_pattern_category() di hook init.
* **Tombol "Sign in" tetap muncul untuk pengunjung yang sudah login.**
  header-actions.php sekarang menampilkan "My account" bila sudah login.
* **Komentar cache di header-actions.php keliru.** Ia mengklaim jumlah item
  keranjang "tidak boleh ikut ter-cache halaman penuh" karena jadi blok
  dinamis. Blok dinamis dirender di server dan hasilnya TETAP masuk HTML yang
  di-cache; menjadi dinamis sama sekali tidak melindunginya. Yang benar-benar
  menjaga angka itu adalah cookie WooCommerce yang membuat page cache
  di-bypass - dan itu syarat konfigurasi, bukan sifat bawaan. Komentarnya
  diganti dengan penjelasan yang benar beserta konsekuensinya di
  Varnish/Nginx/Cloudflare yang aturannya harus ditulis sendiri.

Satu temuan audit DIBATALKAN setelah diperiksa ulang: dugaan "tidak ada skip
link". Setiap template punya tepat satu landmark <main> - termasuk
archive-ath_font.html, single-ath_font.html, dan page-fonts.html yang
mendapatkannya dari blok font-library/authentype-single - dan WordPress
menyuntikkan skip link sendiri untuk block theme. Menambahkan skip link tema
justru akan menggandakannya, jadi tidak ada yang diubah.

Satu temuan DILAPORKAN, belum diubah, karena butuh verifikasi staging:

* **Cart & Checkout belum ditata untuk blok WooCommerce.** style.css punya 25
  selektor .shop_table, 8 .cart-collaterals, dan 17 .checkout - semuanya
  markup KLASIK. Selektor .wc-block-* / .wp-block-woocommerce-*: nol. Sejak
  tema jadi block theme dan WooCommerce 8.3+ memakai blok Cart/Checkout
  secara default untuk instalasi baru, dua halaman terpenting toko berpotensi
  tampil di luar sistem desain. Perlu dicek dulu di staging: halaman Cart dan
  Checkout Anda isinya shortcode [woocommerce_cart]/[woocommerce_checkout]
  atau blok. Kalau blok, ini pekerjaan CSS baru yang tidak kecil.


== v1.0.4 - katalog tetap terbaca saat render specimen gagal ==

Ini DEGRADASI TAMPILAN, bukan penyembuhan penyebab "Preview unavailable".
Penyebabnya masih perlu didiagnosis di server (lihat catatan diagnosis di
bawah); yang diperbaiki di sini adalah akibatnya yang terlalu parah.

Sebelumnya, kalau render gagal, specimen.js menandai canvas dengan class
.has-error lalu MELUKIS pesan errornya ke dalam canvas itu. Karena baris
spesimen adalah satu-satunya isi visual baris katalog, hasilnya adalah kotak
kosong bertuliskan "Preview unavailable." - nol huruf dari keluarga font yang
sedang dijual, di halaman yang tujuannya justru memperlihatkan huruf.

Sekarang canvas yang gagal ditukar dengan nama keluarga font dalam font tema,
memakai span cadangan yang sama dengan jalur <noscript>. Bukan spesimen
sungguhan, tapi katalog tetap terbaca dan tetap bisa diklik. Di kartu "Related
font families" canvas yang gagal cukup disembunyikan, karena nama keluarganya
sudah tampil di <h3> tepat di atasnya.

Canvas-nya sengaja TIDAK dihapus dari DOM, hanya display:none. Class
.has-error tetap bisa diperiksa di DevTools dan request admin-ajax yang gagal
tetap terlihat di tab Network, jadi sinyal diagnosisnya tidak hilang.

Catatan diagnosis - apa arti pesan yang muncul:

specimen.js membaca pesan error dari respons JSON endpoint dan menampilkannya
apa adanya. Semua kegagalan yang berasal DARI DALAM handler render
mengembalikan JSON dengan pesannya sendiri: "Font preview is not available."
(post bukan ath_font atau belum publish), "Too many preview requests." (rate
limit), "Preview font could not be resolved." (token tidak resolve), "Imagick
is not available.", "GD FreeType is not available.", "GD fallback requires a
TTF preview font." (server tidak punya mesin render yang cocok).

Teks generik "Preview unavailable." adalah cadangan terakhir milik TEMA, dan
hanya muncul kalau responsnya BUKAN JSON sama sekali. Artinya requestnya tidak
pernah sampai ke handler render, atau mati sebelum sempat menjawab dalam
bentuk JSON. Dua kemungkinan nyata: nonce ditolak (check_ajax_referer menjawab
"-1" sebagai teks biasa dengan status 403), atau admin-ajax.php tidak
menjawab normal sama sekali (fatal error PHP -> HTML 500, atau diblokir
WAF/proxy/security plugin -> 403/404). Cek status dan isi respons request
admin-ajax.php di tab Network untuk memisahkan keduanya.


== v1.0.5 - akar masalah "Preview unavailable" sesudah FSE ==

Gejalanya: preview teks katalog jalan sebelum konversi block theme, dan hilang
sesudahnya. Perbaikan umur nonce di 1.0.2 tidak menyentuhnya sama sekali,
karena penyebabnya bukan nonce.

Akar masalahnya ada di baris 4 assets/specimen.js milik plugin Authentype:

    const cfg = window.AthSpecimen || {};

Baris itu dieksekusi saat skripnya DI-PARSE, bukan saat DOMContentLoaded.
Kalau window.AthSpecimen belum ada pada detik itu, cfg menjadi objek kosong
yang TERPUTUS PERMANEN dari window.AthSpecimen - deklarasi
"var AthSpecimen = {...}" yang tercetak belakangan tidak akan pernah sampai ke
cfg. Rantai akibatnya persis gejala yang terlihat: cfg.ajaxUrl undefined ->
fetch(undefined) meminta URL relatif "undefined" -> server menjawab halaman
404 dalam HTML, bukan image/png -> res.json() gagal -> pesan mundur ke teks
generik "Preview unavailable."

Satu akar masalah, dua gejala: preview gagal DAN deretan permintaan 404 ke URL
"undefined" di log server. Itu menjelaskan kenapa "Page Not Found" muncul
berbarengan.

Kenapa baru muncul sesudah FSE: WordPress mencetak data itu sebagai blok
<script id="...-js-extra"> tepat sebelum tag skripnya, tapi hanya kalau
wp_localize_script() dipanggil sebelum handle-nya dicetak. Tema memanggilnya
dari dalam template part (font-specimen-row.php dan font-product-card.php).
Di tema classic, template PHP dirender lebih dulu sehingga pemanggilan itu
selalu cukup awal. Di block theme, isi halaman dirender lewat render_callback
blok, dan tema tidak lagi memegang kendali atas kapan itu terjadi relatif
terhadap pencetakan skrip. Lokalisasi yang datang setelah handle-nya tercetak
tidak menghasilkan apa-apa dan tidak melaporkan error apa pun.

Perbaikannya: pasang dan lokalisasi aset specimen di hook wp_enqueue_scripts,
yang dijamin berjalan sebelum apa pun dicetak. Prioritas 20, supaya registrasi
handle oleh plugin (prioritas 10) sudah selesai lebih dulu - wp_enqueue_script()
dan wp_localize_script() sama-sama mensyaratkan handle terdaftar dan diam-diam
tidak melakukan apa-apa kalau belum. Cakupannya: front page, blog, hasil
pencarian, semua Page, arsip ath_font, dan halaman ath_font tunggal; bisa
disesuaikan lewat filter aksara_preload_specimen_assets.

Pemanggilan dari dalam template part TIDAK dihapus. Blok bisa disisipkan lewat
Site Editor ke halaman yang tidak tercakup daftar di atas, dan di sana jalur
lama tetap satu-satunya kesempatan. Guard "static $done" membuatnya jadi no-op
kalau preload sudah menanganinya.

Diverifikasi dengan harness PHP yang meniru semantik cetak WP_Scripts:
lokalisasi sesudah handle tercetak menghasilkan cfg kosong (bug terbukti);
lokalisasi di wp_enqueue_scripts mencetak blok -js-extra tepat sebelum tag
skrip sehingga cfg terisi; dan prioritas yang terlalu awal (sebelum plugin
mendaftarkan handle) juga menghasilkan cfg kosong - itulah alasan prioritas 20
dipilih, bukan angka sembarang.

Yang masih perlu dikonfirmasi di situs Anda: buka View Source halaman katalog
lalu cari "AthSpecimen". Kalau sesudah memasang versi ini blok
<script id="authentype-font-specimen-js-extra"> sudah muncul TEPAT SEBELUM
<script src=".../specimen.js">, urutannya sudah benar.

