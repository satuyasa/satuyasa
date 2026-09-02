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


== v0.9.10 - placeholder specimen yang jujur + umur nonce preview ==

Dua perbaikan yang sempat hilang saat mengadopsi master 0.9.8, dipasang ulang
dalam bentuk yang sudah disesuaikan.

**1. Placeholder baris specimen (menggantikan versi asli yang ditolak)**

Versi aslinya menukar canvas yang gagal dengan nama keluarga font, dicetak
sebesar spesimen sungguhnya dalam tinta penuh. Itu ditolak dengan alasan yang
benar: ini toko huruf. "Honic" yang dicetak 158px dalam font TEMA di kotak
yang seharusnya berisi spesimen Honic bisa membuat pembeli mengira itulah
wujud Honic - kesan percaya diri yang salah, lebih merugikan daripada mengaku
terus terang.

Versi yang dipakai sekarang sengaja TERBACA SEBAGAI PLACEHOLDER:
* ukurannya clamp(26px, 4.5vw, 56px), jauh di bawah spesimen sungguhan yang
  115-158px, jadi tidak pernah menempati peran visual yang sama;
* warnanya --muted (#767676), bukan tinta penuh;
* selalu disertai keterangan kecil yang menyebut apa yang sedang terjadi.

Tiga keadaan ditangani dengan komponen yang sama tapi keterangan berbeda:
* render gagal -> "Preview unavailable" (muncul saat specimen.js menandai
  canvas dengan .has-error; canvas-nya disembunyikan CSS);
* JavaScript mati -> "Preview needs JavaScript" lewat <noscript>. Master 0.9.8
  kehilangan cadangan ini SAMA SEKALI, jadi pengunjung yang JS-nya diblokir
  melihat katalog kosong total - itu diperbaiki sekalian;
* font belum punya token preview -> "Preview not ready".

Keadaan ketiga tadinya memakai .sp-specimen-fallback yang punya masalah
menyesatkan yang persis sama (nama keluarga, 158px, tinta penuh), jadi ia ikut
diseragamkan. Aturan CSS .sp-specimen-fallback jadi tidak terpakai dan
dihapus, bukan ditinggal sebagai CSS mati.

Canvas yang gagal sengaja TIDAK dihapus dari DOM, hanya display:none, supaya
.has-error dan request admin-ajax yang gagal tetap bisa didiagnosis di
DevTools. Ini degradasi tampilan, bukan penyembuhan penyebabnya.

**2. Umur nonce preview - HANYA preview, bukan keranjang**

renderNonce ditanam di HTML dan efektif hanya 12-24 jam. Di situs dengan
full-page cache, HTML katalog disajikan lebih lama dari itu sehingga setiap
preview gagal sampai cache dibersihkan. Tanpa page cache, bug ini tidak akan
pernah muncul.

Filter nonce_life yang disaring per $action memperpanjang
ath_specimen_render_preview jadi 30 hari (jaminan minimum 15 hari), bisa
diubah lewat filter aksara_specimen_nonce_ttl.

Nonce keranjang (ath_specimen_cart) SENGAJA TIDAK ikut dilonggarkan meski
kena masalah cache yang sama, karena ia action yang mengubah state:
memperpanjangnya berarti memperlebar jendela CSRF add-to-cart. Kalau suatu
saat tombol Add to cart terbukti gagal di halaman ter-cache, cabangnya
ditambahkan secara sadar - bukan disamaratakan.

Filternya harus berlaku untuk pembuatan DAN verifikasi: ada dua tempat
pembuatan nonce (tema dan shortcode plugin) dan tiga tempat verifikasi. Kalau
umurnya hanya dilonggarkan saat request AJAX, tick pembuatan dan verifikasi
tidak akan pernah cocok dan semua preview langsung gagal.

$action baru diteruskan ke nonce_life pada WordPress yang wp_nonce_tick()-nya
menerima argumen. Versi persisnya tidak bisa diverifikasi dari lingkungan
kerja ini, jadi kodenya memeriksa runtime lewat ReflectionFunction, bukan
menebak nomor versi. Pada WordPress lama filternya mengembalikan umur apa
adanya (fail-safe) dan statusnya dilaporkan di Tools > Site Health > Info >
Aksara theme, lengkap dengan saran menurunkan TTL page cache di bawah 12 jam.

Diverifikasi dengan harness PHP yang meniru mekanika tick nonce: preview valid
di hari ke-14 dan kedaluwarsa di hari ke-31; nonce keranjang tetap kedaluwarsa
di hari ke-1,5 seperti default; wp_rest tidak tersentuh; dan pemanggilan tanpa
$action tidak mengubah apa pun.


== v0.9.11 - audit responsif: batas kiri/kanan halaman ==

Diaudit dengan merender tema di Chromium headless, bukan dengan membaca CSS.
15 jenis halaman (front page, blog, arsip, single post, page, search, 404,
arsip & single ath_font, halaman WooCommerce, dan empat page template) x 10
lebar viewport (320, 360, 375, 414, 768, 900, 950, 1024, 1280, 1440), masing-
masing dirender dalam iframe selebar itu supaya media query benar-benar
dievaluasi, lalu diukur scrollWidth vs clientWidth plus bounding box tiap
elemen.

KOREKSI METODOLOGI. Putaran pertama melaporkan overflow di hampir semua
halaman. Itu SALAH: fixture-nya menyuntikkan kata buatan sepanjang 43 huruf
tanpa spasi ke setiap heading, dan pada clamp(..., 13vw, 160px) kata itu
memang tidak mungkin muat. Angkanya mengukur fixture, bukan tema. Setelah
diganti judul realistis, kelima belas halaman bersih di sepuluh lebar.

Tapi ada yang memang lolos dari putaran itu: fixture dibuat dengan me-strip
PHP, sehingga the_content() menghasilkan kosong dan ISI ARTIKEL DARI EDITOR
tidak pernah teruji sama sekali. Di situlah empat bug sebenarnya berada.
Diukur pada viewport 375px:

  <pre> berisi satu baris kode panjang      meluber 338px
  <table> enam kolom                        meluber  73px
  URL panjang tanpa spasi di dalam <p>      meluber  60px

Ketiganya karena tema sama sekali tidak punya aturan global untuk pre, table,
maupun overflow-wrap - yang ada cuma img { max-width: 100% }. Isi artikel
datang dari editor, bukan dari template, jadi tema tidak boleh mengandalkan
markupnya berperilaku baik. Ditambahkan satu blok pagar untuk .entry-content
dan .editorial-single__body: overflow-wrap break-word (bukan anywhere -
break-word hanya memotong kata yang memang tidak muat dan tidak mengubah
perhitungan min-content, jadi tipografi normal tidak terganggu), plus
display:block + overflow-x:auto untuk pre dan table.

  blockquote artikel     meluber 50px di 901px, 25px di 950px, 1px di 999px

Ini yang paling halus. .editorial-single__body blockquote memasang
margin: 64px -120px TANPA SYARAT sebagai efek bleed, lalu membatalkannya
lewat margin-inline: 0 di bawah 900px. Badan artikel lebarnya
min(100% - 32px, 760px), jadi bleed 120px per sisi baru benar-benar muat
kalau viewport minimal 760 + 240 = 1000px. Yang tersisa adalah celah
901-999px: pembatalannya sudah berhenti berlaku tapi ruangnya belum ada.

Diperbaiki dengan membalik logikanya: bleed dinyatakan sebagai afordansi
desktop lewat @media (min-width: 1040px), bukan default yang dibatalkan di
layar kecil. Celahnya tertutup secara definisi, bukan dengan menambal
breakpoint.

Ambangnya 1040px dan bukan 1000px pas karena alasan yang terukur: saat
halaman cukup panjang untuk memunculkan scrollbar vertikal, ruang yang
tersedia berkurang selebar scrollbar (~15px) sementara media query tetap
dievaluasi terhadap 1000px - tepat di 1000px masih tersisa 8px overflow.
40px kelonggaran menutupinya di semua lebar scrollbar yang wajar.

GUTTER. Padding kiri/kanan .wrap diperiksa di kelima belas halaman dan
sepuluh lebar: konsisten 16px di <=600px dan 24px di atasnya, kiri selalu
sama dengan kanan. Tidak ada yang menyimpang.

HASIL AKHIR. Kelima belas halaman plus lima fixture isi-artikel: nol
overflow horizontal di sepuluh lebar.

