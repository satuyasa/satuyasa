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
== Aksara 0.9.1 ==

Font Library now shows the linked WooCommerce featured image and complete
product gallery in a responsive horizontal slider: four visible columns on
desktop, two on tablet, and a swipeable preview on mobile. Arrow controls,
scroll snapping, keyboard focus, and reduced-motion behavior are included.

== Aksara 0.9.2 ==

The Font Library gallery now follows the supplied visual reference: four
large image tiles in one uninterrupted carousel, rounded image corners,
hidden scrollbar, and dark circular previous/next controls overlaid on the
left and right edges. The controls wrap at either end; touch and trackpad
scrolling remain available.

== Aksara 0.9.3 ==

Carousel arrows were reduced from 56px to 40px on desktop and to 36px on
mobile, with smaller glyphs and a lighter shadow so controls no longer cover
important product artwork.

== Aksara 0.9.4 ==

Gallery arrows are hidden by default and fade in only when the gallery is
hovered or receives keyboard focus. Swipe, trackpad, and native scrolling
continue to work while the controls are hidden.

== Aksara 0.9.5 ==

Font Library specimen padding was tightened to 8px above and below the
server-rendered preview. The separation before the gallery was increased to
48px. Gallery arrows are now an understated 32px on desktop and 30px on
mobile, with a lighter shadow.

== Aksara 0.9.6 ==

The complete WooCommerce featured image and gallery set now uses the same
four-column carousel on the homepage, Font Library archive, and single-font
page. Legacy three-column grid rules are normalized without changing the
native WooCommerce gallery used by non-font products.

== Aksara 0.9.7 ==

On single-font pages the overview separator now sits between the gallery and
description, with 48px of breathing room below the images instead of touching
their lower edge.

== Aksara 0.9.8 ==

Introduces a dedicated editorial Journal system inspired by DESIGN-2: a
featured-story posts page, asymmetric archive grid, large serif single-post
hero, reading time, author block, related stories, editorial navigation,
capsule imagery, and a blog-only dark footer CTA with yellow highlight.


== v0.9.9 - satu suara tipografi di seluruh tema ==

Sebelum ini tema memakai DUA keluarga: --font-ui (Sterling/Work Sans, sans)
untuk seluruh situs, dan --font-editorial ('Playfair Display', serif) untuk
sembilan tempat di halaman editorial/blog yang masuk di 0.9.8.

Yang menentukan mana yang menang bukan selera, tapi DESIGN.md:

  "Work Sans - Failsafe ... never the primary voice, but THE ONLY GOOGLE FACE
   IN THE SYSTEM."
  "Do not use Work Sans or any Google fallback as a visible UI face; Sterling
   is the only voice."

Playfair Display tidak disebut sama sekali di DESIGN.md, dan ia adalah
keluarga Google KEDUA - langsung bertentangan dengan "the only Google face in
the system". Jadi ia yang dilepas, bukan Sterling.

Yang diubah:

* --font-editorial kini bernilai var(--font-ui). Tokennya sengaja
  DIPERTAHANKAN, bukan dihapus lalu sembilan pemakaiannya ditulis ulang,
  supaya peran "teks editorial" tetap punya nama kalau suatu saat diberi
  keluarga tersendiri lagi. Pola ini sama persis dengan --font-display yang
  memang sudah begitu sejak awal.
* Playfair Display dilepas dari permintaan Google Fonts di functions.php.
  Sekarang hanya Work Sans - satu request, bukan dua. Docblock di atas fungsi
  itu sebetulnya SUDAH mengklaim "cuma SATU keluarga (Work Sans), bukan dua";
  Playfair Display masuk di 0.9.8 tanpa memperbarui komentarnya, jadi kode dan
  komentarnya saling bertentangan. Sekarang cocok.
* font-family: Arial, sans-serif di baris ~1108 diganti var(--font-ui). Itu
  satu-satunya font-family di tema yang menembus sistem token. Tempatnya
  tombol bulat "x"; glyph itu ada di semua keluarga, jadi tidak ada alasan
  teknis untuk mengecualikannya.
* Tombol "ff" pembuka menu fitur OpenType milik plugin Authentype disetel
  Georgia, serif oleh specimen.css. Berkas plugin tidak diedit (akan tertimpa
  saat update), jadi diseragamkan lewat override di style.css tema.
  Selektornya memakai button.… (0,1,1) dan .ath-specimen-v7 button.… (0,2,1)
  supaya menang atas aturan plugin (0,1,0 dan 0,2,0) TANPA !important, dan
  menang tanpa bergantung urutan cetak stylesheet - urutan itu tidak dijamin
  karena specimen.css baru di-enqueue saat shortcode/template part berjalan.

Yang SENGAJA dibiarkan berbeda: chip tag fitur (.ath-feature-chip) tetap
monospace. Isinya tag OpenType mentah seperti "liga" dan "dlig"; monospace di
situ fungsional, bukan dekoratif.

Diverifikasi dengan pemindai yang membaca setiap deklarasi font-family di tema
dan kedua plugin lalu menelusuri resolusi tokennya: 18 deklarasi di tema
seluruhnya var(--font-*), ketiga token (--font-ui, --font-display,
--font-editorial) bermuara ke satu stack yang sama, dan permintaan Google
Fonts tinggal ['Work+Sans'].

CATATAN, di luar cakupan perubahan ini: bagian editorial juga memasukkan warna
KROMATIK, padahal DESIGN.md menetapkan 0% warna kromatik. Yang paling mencolok
#fef199 (kuning, delta 101) pada border .editorial-footer-cta di baris ~1596,
ditambah keluarga hitam kebiruan #111118 / #33333b / #292d35 (delta 7-12) di
sekitar baris 1489-1602. Belum diubah karena ini soal warna, bukan font.

