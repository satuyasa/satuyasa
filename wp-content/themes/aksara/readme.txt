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


== v0.9.12 - "ABOUT THE AUTHOR ke bawah" & lantai min-content ==

Audit 0.9.11 melewatkan seluruh bagian bawah halaman single post, dan
alasannya sama seperti sebelumnya: isinya digenerate PHP - get_avatar(),
the_author(), the_post_navigation(), comments_template(), dan loop related -
sedangkan fixture dibuat dengan me-strip PHP. Bagian itu dirender kosong,
jadi tidak pernah benar-benar diuji. Fixture baru memakai markup WordPress
yang sebenarnya untuk seluruh wilayah itu.

PELAKUNYA: .editorial-author

Lebarnya terkunci 405px berapa pun viewport-nya. Di 320px meluber 100px, di
375px 45px, di 414px 6px, dan baru hilang di 600px - persis pola yang
dilaporkan.

Sebabnya bukan aturan width yang salah, melainkan lantai min-content:

  Setiap anak grid atau flex punya min-width: auto secara bawaan, yang
  berarti ia TIDAK BOLEH menyusut di bawah lebar min-content-nya.

.editorial-author memakai grid-template-columns: 80px minmax(0, 600px).
Trek keduanya memang boleh mengecil sampai 0 - tapi ITEM di dalamnya tidak.
Bio penulis yang memuat satu URL panjang memaksa lebarnya 301px, sehingga
kotaknya terkunci di 80 + 24 + 301 = 405px dan mendorong <body> ikut melebar.
Tidak ada satu pun properti width yang keliru; yang keliru adalah asumsi
bahwa anak grid boleh menyusut.

PERBAIKAN - DUA LAPIS, KEDUANYA PERLU

1. overflow-wrap: break-word di body. Teks yang tidak muat DIPOTONG, bukan
   dibiarkan mendorong halaman. Ditaruh di body, bukan didaftar per wadah,
   karena sumbernya bisa muncul di mana saja: nama font panjang, URL di
   dalam judul, alamat email di heading footer. Dipakai break-word dan bukan
   anywhere supaya perhitungan min-content tidak ikut berubah - tipografi
   normal dan spesimen besar tidak berubah sama sekali.

2. min-width: 0 pada anak setiap wadah grid/flex. Daftar selektornya TIDAK
   ditulis tangan: ia diturunkan dari stylesheet ini sendiri dengan memindai
   setiap aturan yang mendeklarasikan display: grid atau display: flex - 53
   wadah. Daftar tangan terbukti selalu tertinggal: percobaan pertama
   melewatkan .editorial-masthead dan .font-library-header, dan keduanya
   langsung muncul di uji tekan.

   Keduanya diperlukan. Tanpa overflow-wrap, teksnya tetap tumpah keluar
   item meski itemnya sudah boleh menyusut. Tanpa min-width: 0, treknya
   tetap dipaksa melebar meski teksnya sudah boleh dipotong.

SATU LAGI: .editorial-footer-cta p

max-width: 34ch pada font-size 18px = 321px. Di bawah 640px wadahnya jadi
flex-direction: column dengan align-items: flex-start, sehingga item ini
menyusut ke lebar kontennya - dibatasi max-width, TAPI mengabaikan lebar
wadahnya. Di viewport 320px ruang yang tersedia cuma 273px, jadi meluber
tepat 32px - di SETIAP halaman, karena footer ini dipakai semua halaman.
min-width: 0 tidak menolong: itu soal penyusutan sumbu utama, bukan batas
atas di sumbu silang. Diperbaiki jadi max-width: min(34ch, 100%).

UJI TEKAN

Selain suite konten normal, ditambahkan suite kedua yang menyuntikkan token
88 karakter tanpa spasi ke SETIAP heading dan paragraf di semua halaman -
worst case yang disengaja, bukan kecelakaan fixture seperti di 0.9.11.
Ia langsung membuktikan pagar versi pertama terlalu sempit: 17 dari 21
halaman masih meluber, sampai 1306px.

HASIL AKHIR. 21 fixture (15 jenis halaman + bagian bawah single post + 5
fixture isi artikel) x 10 lebar viewport, pada kedua suite: nol overflow
horizontal.


== v0.9.13 - gutter yang hilang: shorthand padding menimpa .wrap ==

Gejalanya bukan overflow, melainkan KEBALIKANNYA: kotak About the author dan
kartu Previous/Next berhenti tepat di 0 - menempel rata di tepi kiri dan
kanan halaman - sementara badan artikel di atasnya tetap masuk karena ia
punya width sendiri.

Itulah sebabnya audit 0.9.11 dan 0.9.12 melewatkannya. Keduanya mengukur
scrollWidth vs clientWidth, dan elemen yang berhenti PERSIS di 0 tidak
menghasilkan overflow sama sekali. Pemeriksaan gutter-nya pun cacat: ia hanya
mengukur .wrap PERTAMA di setiap halaman - yaitu header - lalu menyimpulkan
seluruh halaman konsisten.

PENYEBABNYA

  .wrap                       { padding: 0 var(--gutter); }        /* baris 152 */
  .editorial-single__footer   { padding: 64px 0 88px; }            /* baris 1739 */

Elemen itu di markup berkelas "editorial-single__footer wrap". Kedua selektor
sama-sama berbobot (0,1,0), jadi yang menang adalah yang muncul BELAKANGAN -
aturan editorial. Shorthand `padding` menulis ulang KEEMPAT sisi, sehingga
padding kiri/kanan dari .wrap ikut jadi 0.

Ini regresi. Blok SPACING AUDIT 0.8.4 sudah pernah mengenali pola yang sama
persis dan menambahkan padding-block untuk .content-area dan
.aksara-product-summary. Kerja editorial di 0.9.8 memasukkannya kembali lewat
selektor baru.

DAN PERBAIKAN 0.8.4 ITU SENDIRI TERNYATA TIDAK TUNTAS

Begitu pemeriksaan gutter diperbaiki supaya memeriksa SETIAP .wrap, bukan
hanya yang pertama, dua korban lama langsung muncul:

  .content-area              0/0 padahal harus 16/24  (404, index, page,
                             search, dan tiga page template)
  .aksara-product-summary    0/0 padahal harus 16/24  (halaman WooCommerce)

Sebabnya: 0.8.4 MENAMBAHKAN aturan padding-block di baris 1534-1535, tapi
tidak pernah MENGHAPUS shorthand aslinya di baris 824 dan 996. Shorthand itu
sudah menolkan padding horizontal lebih dulu, dan padding-block hanya
menyetel sumbu atas-bawah - horizontalnya tetap 0. Jadi tujuh halaman lain
sebenarnya juga menempel di tepi selama ini, tanpa pernah terdeteksi.

Ketiganya kini memakai padding-block.

PENCEGAHAN

Aturan .wrap diberi catatan eksplisit: elemen apa pun yang ikut memakai .wrap
HANYA boleh menyetel padding sumbu blok, tidak boleh shorthand. Pemeriksaan
gutter di harness juga diperbaiki permanen - ia kini memeriksa setiap .wrap
di setiap halaman dan membandingkan padding kiri DAN kanan terhadap nilai yang
diharapkan menurut lebar viewport (16px di <=600px, 24px di atasnya).

VERIFIKASI

Tiga pemeriksaan, seluruhnya lulus: gutter setiap .wrap di 21 fixture x 6
lebar; overflow horizontal dengan konten normal; dan overflow horizontal pada
uji tekan token 88 karakter. Ditambah pemeriksaan visual: screenshot 1440px
memastikan avatar, About the author, kartu Previous/Next, dan Related stories
semuanya mulai di 24px, bukan 0.


== v0.9.14 - halaman Free Font (sistem visual Foundry) ==

Dua halaman baru mengikuti docs/DESIGN3.md: arsip free font dan halaman
tunggalnya.

DATANYA BUKAN MILIK TEMA

Authentype sudah punya seluruh sistemnya: CPT ath_free_download, preset
lisensi, gerbang email (lead), token unduhan sekali pakai, rate limit per IP,
dan honeypot. Tema hanya menyediakan tampilan. Tombol unduhnya TETAP dirender
shortcode plugin, tidak ditulis ulang — di dalam markup kartu itu ada nonce,
license fingerprint, dan hidden field yang seluruhnya bagian dari kontrak
keamanan plugin, dan menyalinnya berarti menyalin sesuatu yang bisa berubah
saat plugin di-update.

Masalahnya [authentype_free_downloads] tidak punya atribut id — ia hanya
menyaring lewat type/font_id, sedangkan halaman tunggal butuh tepat satu item.
Jadi QUERY-nya yang dipersempit, bukan markupnya yang disalin: get_posts()
memakai WP_Query di baliknya dan pre_get_posts berlaku untuk semua WP_Query.
Cakupannya dipersempit tiga lapis — hanya saat bendera dipasang, hanya untuk
post type ini, dan bendera dilepas segera setelah shortcode selesai.

DUA SISTEM VISUAL YANG BERTENTANGAN, DIPISAH DENGAN SENGAJA

  DESIGN.md  (Studio Few) — terang, 0% kromatik, Sterling/Work Sans
  DESIGN3.md (Foundry)    — gelap #121212, aksen oranye #ff4d00, JetBrains Mono

Keduanya tidak bisa digabung. Foundry ditaruh di assets/css/foundry.css,
di-enqueue HANYA di arsip dan halaman tunggal ath_free_download, dan seluruh
aturannya diberi awalan .foundry supaya tidak pernah bocor. Halaman Free Font
karena itu TIDAK mengikuti DESIGN.md — itu memang yang diminta, tapi perlu
disebut terang-terangan supaya tidak dikira regresi oleh audit monokrom.

Header dan footernya juga terpisah (header-foundry.php / footer-foundry.php).
DESIGN3 menetapkan sidebar kiri tetap dan melarang tata letak terpusat; itu
struktur halaman yang berbeda, bukan variasi warna. Memakai header.php lalu
menempelkan kanvas gelap akan menghasilkan bilah terang di atas ruang hitam,
persis yang dilarang DESIGN3.

SATU PENYIMPANGAN SADAR DARI DESIGN3, KARENA DIUKUR

Ash #747474 di atas kanvas #121212 hanya 4,01:1. DESIGN3 menugaskannya ke
"muted helper text, inactive labels, secondary metadata" — di sistem ini
semuanya 12-14px, persis kategori yang WCAG AA minta 4,5:1. Jadi ia gagal
untuk peran yang diberikan kepadanya. Ash tetap dipakai untuk hal non-teks,
teks redup memakai --fd-ash-text (#7d7d7d = 4,55:1), nilai tergelap yang
masih lolos. Draf pertama komentar di CSS sempat mengklaim 4,84:1; itu keliru
dan ketahuan karena angkanya dihitung, bukan diasumsikan.

Sisa palet lulus: bone #efefef 16,29:1, ember #ff4d00 5,63:1, chalk #e2e8f0
15,20:1, tag terbalik 16,29:1.

SPESIMEN & PLACEHOLDER

Free download adalah post tersendiri dan tidak punya token preview. Yang punya
token adalah ath_font, dan admin bisa menautkan keduanya lewat
_ath_free_download_related_font. Kalau tautannya ada, spesimennya dirender
sungguhan lewat Authentype; kalau tidak, halaman memakai placeholder yang
jujur mengaku placeholder — pola yang sama dengan 0.9.10, dengan keterangan
yang dibedakan: "Preview unavailable" (render gagal), "Preview needs
JavaScript" (JS mati), "No specimen linked" (belum ditautkan).

TIGA BUG DITEMUKAN OLEH PENGUJIAN SENDIRI

* foundry.css tidak me-reset body. Di produksi style.css menutupinya, tapi
  bergantung pada stylesheet lain itu rapuh, dan kanvas hitamnya bocor jadi
  putih di area bawah konten pendek serta area overscroll. Ditambahkan
  body.foundry-page, digerbangi body class supaya tidak menyentuh halaman lain.
* Garis vertikal sidebar berhenti di tengah halaman: sidebar-nya sticky
  dengan align-self: start sehingga setinggi isinya sendiri. Garisnya
  dipindahkan ke border-left milik kanvas, yang selalu setinggi konten.
* .foundry-sidebar punya overflow-y: auto untuk mode desktop dan itu tidak
  ikut direset saat dilipat di mobile. Menyetel satu sumbu ke non-visible
  memaksa sumbu lain jadi auto, jadi ia berubah menjadi wadah scroll yang
  MENYEMBUNYIKAN overflow dari mata dan dari scrollWidth sekaligus. Direset
  ke overflow: visible supaya pengukurannya kembali bisa dipercaya.

VERIFIKASI

Kedua halaman diuji dengan harness yang sama seperti audit responsif: 10
lebar viewport (320-1440), pada suite konten normal DAN suite uji tekan token
88 karakter tanpa spasi. Nol overflow horizontal di keduanya. Ditambah
screenshot 1440px dan 390px.

CATATAN PEMASANGAN

URL arsipnya /free-downloads/ — slug itu ditetapkan plugin Authentype saat
mendaftarkan CPT dan tidak punya filter, jadi tema tidak bisa mengubahnya.
Setelah memasang versi ini, buka Settings > Permalinks lalu Save sekali agar
rewrite rule-nya ter-flush.


== v0.9.15 - font tester di halaman Free Font, + satu bug 0.9.14 ==

BUG DI 0.9.14 YANG BARU KETAHUAN

Canvas spesimen di halaman Free Font TIDAK PERNAH DIRENDER SAMA SEKALI.
Bukan gagal render, tapi diam: specimen.js hanya menjalankan initRoot() pada
elemen berkelas .ath-specimen-v7 (baris 1273), dan initRoot itulah yang
memasang IntersectionObserver yang memicu render. Template Foundry di 0.9.14
tidak punya wadah itu, jadi observernya tidak pernah terpasang.

Ketahuan justru saat menyiapkan tester ini, karena pertanyaannya memaksa
membaca kontrak specimen.js sampai ke fungsi init-nya, bukan hanya bagian
requestnya. Ditambahkan wadah .ath-specimen-v7 dengan data-font-post-id yang
berisi ID ath_font YANG DITAUTKAN — bukan ID free download-nya, karena nilai
itulah yang dikirim sebagai post_id dan endpoint menolak apa pun yang bukan
ath_font berstatus publish.

Bug kedua di jalur yang sama: data-text-color / data-bg-color ditulis di
canvas, padahal specimen.js membacanya dari ROOT (baris 173-174). Jadi
setelan itu tidak berpengaruh apa pun.

FONT TESTER

Menumpang mesin milik plugin, bukan mesin baru. specimen.js sudah punya
seluruh logikanya — debounce (360ms teks, 120ms ukuran), sinkronisasi antar
kontrol, antrian maksimal 3 request paralel, dan cache hasil. Tema hanya
menyediakan markup dengan kontrak yang tepat:

  .ath-specimen-v7                          wadah yang di-init
  data-font-post-id                         ID ath_font yang ditautkan
  .ath-preview-toolbar                      wadah kontrol
  .ath-master-text                          input teks
  .ath-size                                 input ukuran
  .ath-server-canvas[data-sync-master="1"]  canvas yang ikut berubah

Testernya ada di halaman TUNGGAL, bukan di arsip. Alasannya struktural, bukan
selera: satu toolbar hanya menggerakkan canvas di dalam ROOT-nya sendiri, dan
data-font-post-id melekat pada root. Semua baris arsip punya ath_font yang
berbeda, jadi satu tester bersama untuk seluruh arsip mustahil tanpa
mengorbankan kebenaran post_id-nya. Tester per baris bisa ditambahkan kalau
memang diinginkan.

SATU MASALAH YANG MENUNTUT JAVASCRIPT SENDIRI

initRoot() membuka dirinya dengan dua baris ini, tanpa syarat:

    root.dataset.textColor = "#111111";
    root.dataset.bgColor   = "#ffffff";

Nilai itu dikirim ke endpoint render, jadi PNG-nya jadi tinta nyaris hitam di
atas putih — di kanvas Foundry yang hitam hasilnya blok putih menyala, bukan
spesimen. Berkas plugin tidak boleh diedit karena akan tertimpa saat update,
jadi warnanya dipasang ulang dari assets/js/foundry-tester.js SESUDAH init.

Urutannya dijamin, bukan untung-untungan: skrip tema mendeklarasikan handle
plugin sebagai dependency sehingga selalu dicetak sesudahnya, jadi listener
DOMContentLoaded-nya juga terdaftar dan berjalan sesudah initRoot. Render
pertama dipicu IntersectionObserver, yang callback-nya selalu dikirim asinkron
setelah layout — jadi penyetelan warna pasti selesai sebelum request pertama.

Satu pengecualian yang diakui: kalau IntersectionObserver tidak tersedia,
specimen.js me-render seluruh canvas secara SINKRON di dalam initRoot dan di
situ skrip tema memang terlambat. Konsekuensinya hanya warna spesimen yang
keliru, bukan halaman yang rusak.

Tombol .ath-reset milik plugin sengaja TIDAK dipakai: handler-nya menyetel
warna balik ke #111111, persis masalah di atas. Tombol reset tema memakai
kelas sendiri dan bekerja dengan cara paling tidak invasif — menulis nilai ke
input milik plugin lalu men-dispatch event "input", sehingga yang mengerjakan
render tetap listener milik plugin.

Pemilih warna (.ath-text-color) juga tidak dipakai: DESIGN3 monokrom.

VERIFIKASI

Kontrak JS-nya diuji dengan tiruan specimen.js yang meniru initRoot dan
handler toolbarnya persis, dimuat dalam urutan yang sama seperti produksi.
Delapan pemeriksaan lulus: warna gelap terpasang sesudah init, canvas ikut
berubah saat mengetik, fit-single-line dilepas saat pengunjung mengetik
sendiri, reset mengembalikan teks dan ukuran, warna tetap gelap sesudah
reset, dan render terakhir benar-benar memakai warna terang.

Responsif diuji ulang dengan tester terpasang: 10 lebar viewport, suite
konten normal dan uji tekan, nol overflow.


== v0.9.20 - tombol unduh yang tidak terlihat di halaman tunggal Free Font ==

GEJALA: tombol unduh tampil sebagai kotak bergaris hitam yang KOSONG.
Teksnya ada di DOM, ukurannya 151x42px, tapi tidak terlihat sama sekali.

PENYEBAB, hasil pengukuran computed style di Chromium — bukan tebakan:

    color       rgb(255, 255, 255)   putih
    background  rgba(0, 0, 0, 0)     transparan
    wadah       rgb(255, 255, 255)   putih

Putih di atas putih. Rasio kontras 1:1.

Yang membuatnya terbelah: TIGA aturan berebut, masing-masing menang untuk
properti yang berbeda.

1. specimen.css:1020 milik plugin
     .ath-free-download-button { color: var(--ath-free-primary) !important }
   !important, jadi ia mengalahkan aturan tema mana pun yang tidak memakai
   !important, berapa pun spesifisitasnya. Ada aturan !important kedua yang
   muncul belakangan di berkas plugin dan menyetel warnanya ke
   var(--ath-free-surface, #fff) = PUTIH. Itu sumber warna putihnya.

2. foundry.css:191 milik tema, varian terang
     .freefonts-single .foundry-download .ath-free-download-button
     { background: #000; color: #fff }
   Spesifisitas (0,3,0). Kalah untuk background oleh nomor 3 yang
   spesifisitasnya SAMA PERSIS tapi muncul belakangan di berkas yang sama,
   dan kalah untuk color oleh !important plugin. Praktisnya aturan ini tidak
   berpengaruh sama sekali.

3. foundry.css:691 milik tema, varian gelap
     .foundry .foundry-download .ath-free-download-button
     { background: transparent; color: var(--fd-ember) }
   Menang untuk background. Warnanya kalah oleh !important plugin. Itu sumber
   latar transparannya.

Jadi latar datang dari aturan gelap tema, warna datang dari plugin, dan
keduanya kebetulan sama-sama putih di halaman yang sudah terang.

PERBAIKANNYA ditaruh di UJUNG foundry.css, dengan tiga keputusan sadar:

* Di ujung berkas, supaya tidak ada aturan tema lain yang bisa mendahuluinya
  lewat urutan. Masalahnya tadi memang lahir dari urutan.
* color memakai !important karena TIDAK ADA cara lain: plugin sudah memakai
  !important dan spesifisitas tidak pernah mengalahkannya.
* background dan border ikut !important supaya pasangan warnanya tidak bisa
  terbelah lagi oleh aturan lain — persis kegagalan di atas.
* Halaman terang dan gelap dipisah eksplisit lewat
  .freefonts-archive dan .foundry:not(.freefonts-archive), bukan mengandalkan
  var(--fd-ember) yang nilainya berubah tergantung kelas mana yang kebetulan
  menang.

SATU KESALAHAN SAYA SENDIRI, KETAHUAN SAAT DIRENDER

Draf pertama aturan ini memberi border pada .ath-free-download-cancel. Tombol
itu BUKAN tombol teks: di plugin ia tombol ikon bulat 30x30 berlabel "x"
dengan border: 0. Border tadi mengubahnya jadi lingkaran bergaris. Sekarang
hanya warnanya yang diperbaiki, bentuknya tidak disentuh.

VERIFIKASI

Kontras diukur ulang di Chromium pada dua konteks:

  terang  Download Free   putih di atas hitam      21,00:1
  terang  Send            putih di atas hitam      21,00:1
  terang  Cancel          #111 di atas putih       18,88:1
  gelap   Download Free   #ff4d00 di atas #121212   5,63:1

Sebelumnya 1:1. Regresi responsif dijalankan ulang pada template 0.9.19: 9
lebar viewport, suite konten normal dan uji tekan, nol overflow.


== v0.9.21 - header & footer dipecah jadi template-parts ==

header.php (65 baris) dan footer.php (60 baris) tadinya monolit: doctype,
branding, navigasi, aksi keranjang, CTA, tiga menu footer, dan baris hak
cipta semuanya bercampur di dua berkas. Sekarang keduanya HANYA MENYUSUN:

  template-parts/header/branding.php      logo / judul situs
  template-parts/header/nav-primary.php   toggle + menu utama
  template-parts/header/actions.php       Sign in + Cart
  template-parts/footer/cta.php           "Explore the font library"
  template-parts/footer/menus.php         identitas + tiga kolom menu
  template-parts/footer/bottom.php        hak cipta

header.php tinggal 52 baris, footer.php 28 baris.

KENAPA — INI PELAJARAN DARI KODE INI SENDIRI

Dulu ada header-foundry.php: header kedua untuk halaman Free Font yang
MENGGANDAKAN branding, navigasi, Sign in dan Cart dari header.php. Begitu
header utama berubah, salinannya tidak ikut. Ia melenceng, lalu ditinggalkan
sama sekali — di 0.9.19 seluruh template sudah kembali memanggil get_header()
biasa dan kedua berkas foundry itu jadi yatim, 108 baris kode mati. Keduanya
kini dihapus.

Dengan pemecahan ini, varian header apa pun cukup menyusun ulang PART YANG
SAMA. Perubahan pada penghitung keranjang mendarat di semua tempat sekaligus
dan tidak bisa lagi melenceng diam-diam.

Aturan yang dipegang ke depan, ditulis di docblock header.php: buat
header-<nama>.php hanya kalau KERANGKA halamannya memang berbeda — landmark
lain, tata letak lain. Kalau bedanya cuma warna, itu urusan body class dan
CSS. Halaman Free Font membuktikannya: kerangka kedua dibuat untuk sesuatu
yang ternyata varian palet, dan akhirnya dilipat kembali.

PEMBUKTIAN: KELUARANNYA HARUS IDENTIK

Refactor yang mengubah HTML bukan refactor. Dibuat harness PHP yang benar-
benar MENJALANKAN header.php dan footer.php dengan stub WordPress
deterministik, lalu membandingkan keluaran versi lama (diambil dari git) dan
versi baru — dinormalkan seperti cara browser meruntuhkan whitespace, supaya
perbedaan indentasi tidak dihitung tapi perbedaan nyata sekecil apa pun
tetap terlihat.

Sepuluh konteks diuji, seluruhnya identik: logo teks vs custom logo, menu
primary terisi vs fallback, ketiga menu footer terisi vs kosong sebagian vs
kosong semua, halaman editorial vs non-editorial (CTA muncul/hilang),
keranjang berisi vs kosong, dan WooCommerce aktif vs nonaktif.

SATU KESALAHAN SAYA SENDIRI, DITANGKAP OLEH PENGUJIAN ITU

Draf pertama template-parts/header/actions.php melakukan `return` lebih awal
saat WooCommerce nonaktif — kelihatan lebih rapi, tapi header.php lama SELALU
mencetak pembungkus <div class="header-actions"> dan hanya isinya yang
bersyarat. Menghilangkan div itu mengubah jumlah anak .site-header-inner yang
memakai justify-content: space-between, jadi posisi branding dan navigasi ikut
bergeser saat Woo nonaktif. Dikembalikan persis seperti semula.

Ada juga celah di pengujiannya sendiri: kasus "WooCommerce nonaktif" awalnya
tidak benar-benar teruji karena stub mendeklarasikan kelas WooCommerce tanpa
syarat, dan class_exists() tidak bisa ditimpa. Kasus itu kini dijalankan
sebagai proses tersendiri dengan deklarasi kelasnya dilewati.


== v0.9.22 - header & footer yang bisa diedit dari wp-admin ==

Pemecahan di 0.9.21 membuat setiap komponen header/footer punya berkasnya
sendiri. Versi ini memakai itu: teks di dalamnya kini datang dari Customizer
(Appearance > Customize > Aksara), bukan lagi ditulis di dalam template.

YANG BISA DIUBAH

  Header  bilah pengumuman: nyala/mati, teksnya, dan tautannya (opsional)
  Footer  cakupan ajakan (editorial saja / semua halaman / mati),
          teks & label ajakan, tautannya,
          judul tiga kolom menu,
          baris penutup di kanan bawah
  Home    judul dan sub-judul hero

Ditambah satu lokasi menu baru, "Footer - Social", yang merender baris tautan
sosial di kolom identitas footer.

YANG SENGAJA TIDAK BISA DIUBAH

Tidak ada kontrol warna, ukuran huruf, atau lebar kolom, dan jumlah kolom
footer tetap tiga. DESIGN.md yang menetapkan sistem visualnya; membuka warna
ke admin berarti mengundang situs keluar dari sistemnya sendiri - persis yang
dicegah theme.json waktu tema ini sempat jadi block theme.

Navigasi juga tidak dibuatkan kontrol repeater sendiri. Menu WordPress sudah
punya UI pengurutan, label, dan target; menirunya di Customizer hanya
menghasilkan versi yang lebih buruk dari yang sudah ada.

BAWAANNYA HARUS TIDAK MENGUBAH APA PUN, DAN ITU DIBUKTIKAN

Setiap setting default-nya persis string yang selama ini tertulis di
template, dikumpulkan di satu tempat (aksara_mod_defaults() di
inc/customizer.php) supaya template dan Customizer tidak bisa berbeda
pendapat soal apa yang default. Bilah pengumuman mati, dan baris sosial tidak
mencetak apa pun tanpa menu.

Harness yang sama dari 0.9.21 dipakai lagi, kali ini memuat inc/customizer.php
SUNGGUHAN alih-alih men-stub aksara_mod(), supaya yang diuji benar-benar nilai
bawaan yang dipakai situs. Sepuluh konteks dibandingkan dengan 0.9.21 dari
git: seluruhnya identik.

Perilaku barunya diuji terpisah: bilah muncul hanya kalau sakelar DAN teks
sama-sama terisi (sakelar sendirian menghasilkan strip hitam kosong yang
terbaca seperti kerusakan), ajakan footer muncul/hilang sesuai ketiga
cakupan, ajakan hilang seluruhnya kalau teks atau labelnya dikosongkan,
baris penutup yang dikosongkan menghapus <span>-nya alih-alih menyisakan span
kosong yang menahan ruang di kanan, dan baris sosial tidak meninggalkan
markup apa pun tanpa menu.

SATU PERBAIKAN TAMPILAN YANG MEMANG DISENGAJA

Menu footer keluar dari wp_nav_menu() sebagai <ul> polos dan tidak pernah ada
yang mengatur ulang gayanya, jadi kolom Shop/Help/Company selama ini tampil
BERBULATAN dan menjorok memakai gaya bawaan browser - bukan yang digambarkan
DESIGN.md, dan bukan yang terlihat di kolom identitas di sebelahnya.
.main-navigation ul sudah lama mengatur ulang hal yang sama untuk navigasi
atas; sekarang .footer-grid ul juga. Ini satu-satunya hal di rilis ini yang
mengubah tampilan bawaan, dan diubah karena tampilan sebelumnya keliru.

CATATAN PRATINJAU LANGSUNG

Partial selective refresh dipasang PER KOMPONEN, bukan per teks, dan
render_callback-nya memanggil template part yang sama dengan halaman
sungguhan. Satu partial per setting terlihat lebih sederhana tapi salah:
label ajakan footer berbagi elemen <a> dengan panahnya, jadi mengganti isi
elemen itu dengan teks polos akan menghapus panah tersebut di pratinjau - dan
callback yang menyusun ulang markup sendiri berarti markup yang sama ditulis
di dua tempat.

REGRESI RESPONSIF

Diukur di Chromium pada 320/360/375/414/768/900/1024/1280/1440: overflow
halaman 0 dan gutter kiri-kanan tepat (16px di bawah 600, 24px di atasnya) di
seluruh lebar, baik dengan bilah pengumuman + baris sosial maupun tanpa
keduanya. Satu-satunya sisa temuan adalah glyph panah pada tombol ajakan
footer yang advance-nya 23px di kotak 16px - ada juga di 0.9.21, terkurung di
dalam <a>-nya, dan tidak melebarkan halaman.

== v0.9.23 - logo header yang kekecilan ==

Logo yang diunggah tampil sangat kecil, dan penyebabnya adalah dua baris di
tema ini yang saling bertentangan:

  functions.php  add_theme_support('custom-logo', height 60, width 200)
                 -> media uploader menyuruh admin menyiapkan logo 200x60
  style.css      .custom-logo { max-height: 20px }
                 -> lalu mengecilkannya jadi 67x20, sepertiga ukuran itu

Diukur di Chromium dengan berkas logo 200x60: terpasang 67x20, sementara
kotak isi .site-header-inner menyediakan 32px (min-height 56px dikurangi
padding 12px atas-bawah). Jadi 12px ruang header dibiarkan kosong.

20px kemungkinan diambil dari DESIGN.md yang menyebut logomark 16px. Tapi
yang dimaksud di sana WORDMARK TEKS - huruf telanjang tanpa apa pun di
sekelilingnya. Berkas logo hampir selalu membawa ruang kosong di dalam
gambarnya, sering pula lambang di samping tulisan, sehingga hurufnya sendiri
jatuh jauh di bawah 16px: wordmark teks setinggi 16px, sedangkan gambar logo
pada 20px cuma menyisakan huruf setinggi sekitar 9px.

Sekarang 32px, yaitu seluruh kotak isi yang tersedia. Ini TIDAK menambah
tinggi header sama sekali - diukur 57px sebelum dan sesudah, karena
min-height 56px yang menentukan, bukan logonya.

Ditambah kontrol "Logo height" di Customizer > Aksara > Header, dijepit
16-48px. Ini satu-satunya kontrol ukuran yang dibuka ke admin, dan alasannya
berbeda dari ukuran huruf atau lebar kolom: banyaknya ruang kosong di dalam
berkas logo hanya diketahui pemilik berkasnya, tidak bisa ditebak tema.
Batas bawah 16px supaya logo tidak bisa dibuat lebih kecil daripada wordmark
teks yang digantikannya - persis masalah yang membuat kontrol ini ada. Di
atas 32px header memang ikut meninggi, dan itu disebutkan di deskripsi
kontrolnya.

Nilainya dicetak sebagai custom property di wp_head, dan HANYA kalau berbeda
dari bawaan - situs yang tidak mengubah apa pun tidak mendapat <style>
tambahan. Aturan .custom-logo tetap hanya ada di style.css; yang dicetak PHP
cuma angkanya. Angkanya lewat absint lalu dijepit, jadi masukan seperti
"48px; } body{display:none" keluar sebagai 48 - diuji.

Sepuluh konteks perbandingan HTML dengan 0.9.21 dijalankan ulang: seluruhnya
masih identik. Perubahan di rilis ini murni CSS.

== v0.9.24 - audit jarak atas-bawah seluruh halaman ==

Diukur, bukan dikira-kira: 24 halaman dirender di Chromium dan setiap jarak
vertikal antar-elemen di tingkat alur utama dicatat (padding, margin, dan
jarak nyata antar-saudara sesudah margin collapsing), di 1280px dan 375px.

APA YANG DITEMUKAN

1. --section-gap adalah token MATI. .section memakainya di satu tempat, tapi
   blok SPACING AUDIT di bawahnya menimpa dengan --section-rhythm - dua token
   bernilai 80px untuk satu ritme, dan yang satu tidak pernah menang. Akibat
   lanjutannya: @media (max-width: 600px) yang mengecilkan --section-gap ke
   56px selama ini TIDAK berpengaruh apa pun. Token dan override-nya dihapus;
   --gutter di media query yang sama tetap, karena itu memang dipakai.

2. Enam wadah utama dideklarasikan DUA KALI dengan nilai persis sama
   (.content-area, .aksara-product-summary, .font-library, .authentype-single,
   .site-footer, .section). Tidak salah, tapi membuat berkas berbohong soal di
   mana jaraknya diputuskan. Disatukan ke blok SPACING AUDIT.

3. .font-library .specimen-list punya margin-top 28px lalu 24px. Yang 28px
   tidak pernah menang; dihapus.

4. .trust di Home memakai 72px di desktop tapi 56px di ponsel - dan 56px itu
   PERSIS --section-rhythm di lebar tersebut. Jadi di ponsel bagian ini sudah
   seirama dengan .section di atasnya, sementara di desktop meleset 8px tanpa
   alasan. Kini memakai tokennya di kedua lebar.

   Efek sampingnya: jarak bagian terakhir ke footer di Home tadinya 72+72=144,
   sedangkan semua halaman lain 80+72=152. Sekarang Home ikut 152.

5. .hero memakai angka lepas 76px (desktop) dan 56px (ponsel). Keduanya
   ternyata BUKAN sembarang angka: --page-top bernilai 64px dan 44px, dan hero
   selalu tepat 12px lebih lapang di kedua lebar. Itu keputusan yang konsisten,
   cuma tidak pernah ditulis sebagai hubungan - jadi kalau --page-top berubah,
   hero diam-diam berhenti mengikutinya. Ditulis ulang jadi
   calc(var(--page-top) + var(--spacing-12)); nilainya identik, dan override
   .hero di @media 782px jadi tidak perlu.

   Bagian bawah hero (44px) SENGAJA dibiarkan. Sesudahnya datang .categories
   yang tidak punya padding sendiri; yang memberi ruang adalah padding 36px
   milik .cat-card. Jarak yang benar-benar terlihat karena itu 44+36 = 80px,
   yaitu --section-rhythm. Menyamakan 44px ke token mana pun justru merusak
   jumlah itu.

6. DESIGN.md menetapkan base unit 4px. Ada 21 nilai vertikal yang melanggarnya.
   Sebelas yang merupakan jarak ANTAR-ELEMEN dibawa ke kelipatan 4 (masing-
   masing bergeser paling banyak 2px): .eyebrow 18->16, .font-breadcrumb 18->16,
   .editorial-card h2 18/14->16/16, .editorial-read-link 22->24,
   .editorial-single__byline 30->32, .editorial-author h2 6->8, dua margin
   .editorial-masthead 10->12, dan .site-topbar__inner 10->12 (buatan 0.9.22
   sendiri, jadi ikut melanggar sejak awal).

   Sepuluh sisanya SENGAJA tidak diubah: semuanya padding DI DALAM komponen
   (tombol, pil harga, badge diskon), bukan jarak antar-elemen. Membulatkannya
   akan mengubah tinggi tombol, dan itu perubahan yang berbeda jenis dari yang
   diminta.

YANG SENGAJA TIDAK DISERAGAMKAN

Halaman editorial memakai 72px atas / 112px bawah, bukan 64/80 seperti halaman
lain. Itu sistem terpisah yang diadaptasi dari DESIGN-2 dan memang dilingkupi
ke template blog saja. Menyamakannya bukan merapikan, itu mendesain ulang satu
keluarga halaman.

Begitu juga .single-product (40px atas, karena judul produknya raksasa) dan
.authentype-single (0px atas, karena diawali breadcrumb full-bleed yang punya
padding sendiri). Keduanya punya alasan, bukan kelalaian.

DAMPAK YANG DIUKUR

Tahap buang-kode-mati: 24 halaman diukur ulang, SELURUHNYA identik - nol
perubahan tampilan, seperti yang seharusnya.

Setelah semua perubahan, tinggi halaman bergeser di 7 dari 24 halaman, paling
besar +14px (Home: trust +16, eyebrow -2). Setiap selisih cocok dengan
aritmetika perubahannya, tidak ada yang tak terjelaskan. Di 375px, jarak di
tingkat alur utama tidak berubah sama sekali.

Regresi luber/gutter dijalankan di 9 lebar untuk 24 halaman, sebelum dan
sesudah: hasilnya sama persis - overflow halaman 0 dan gutter tepat di
semuanya. Empat halaman menandai scroll DI DALAM elemen (tabel di dalam
overflow-x:auto seperti yang dirancang, dan dua fixture potongan tanpa
kerangka halaman); keempatnya sudah begitu sebelum rilis ini.

== v0.9.25 - lebar halaman Free Font, warna spesimen, gambar unggulan ==

LEBAR: ISINYA TIDAK PERNAH SEJAJAR DENGAN HEADER-NYA SENDIRI

Diukur di Chromium, tepi isi halaman Free Font dibandingkan dengan tepi isi
header dan footer di halaman yang sama:

    viewport      header/footer     isi Free Font
    360-600            16px              16px   sejajar
    768-1440           24px              49px
    1600               24px              98px
    1920               24px             258px
    2560               24px             578px

Dua sebabnya. Pertama GUTTER GANDA: .freefonts-archive__inner memberi 24px
lewat width: min(100% - 48px, 1440px), lalu section di dalamnya memberi
--fd-pad 24px lagi, ditambah 1px garis rambut - 49px, dua kali lipat gutter
tema. Kedua BATAS 1440px: di atas ~1488px kolomnya berhenti melebar sementara
header dan footer terus sampai tepi, dan selisihnya tumbuh tanpa batas.

Batas 1440px itu datang dari DESIGN3, tapi di sana ia berpasangan dengan
sidebar tetap 200px di kiri - tata letak yang TIDAK diadopsi tema ini;
halaman Free Font memakai header Aksara biasa. Dan DESIGN.md, yang mengatur
kerangka situs, menyatakan sistemnya tanpa max-width dan tanpa kolom yang
dipusatkan. Jadi yang tersisa adalah batas warisan dari layout yang sudah
tidak ada. Garis rambut kiri-kanan ikut dilepas: ia hanya masuk akal sebagai
bingkai kolom terpusat.

Gutter kini datang dari satu tempat saja, dan --fd-pad diikat ke --gutter
milik tema. Nilainya persis sama dengan yang dulu ditulis lepas (24px, 16px
di bawah 600px), jadi override --fd-pad di @media 600px ikut dihapus. Sesudah
perbaikan, 11 lebar dari 360px sampai 2560px diukur ulang: SELURUHNYA sejajar
dengan header dan footer.

SPESIMEN ARSIP: LATAR HITAM DI ATAS HALAMAN PUTIH

Arsip Free Font sudah lama putih (.freefonts-archive menimpa seluruh palet
Foundry jadi terang), tapi template-parts/free-font-row.php masih mengirim
text_color="#efefef" bg_color="#121212" ke shortcode - sisa dari masa arsip
ini masih kanvas gelap. Warna itu dikirim ke SERVER dan ikut terbakar ke
dalam PNG spesimennya, jadi yang muncul persegi hitam di atas halaman putih.
Latar canvas di CSS tidak bisa menolong karena PNG-nya menutupi latar itu.
Sekarang #111111 di atas #ffffff.

assets/js/foundry-tester.js DIHAPUS. Berkas itu ada untuk menimpa warna yang
dipaksakan initRoot() milik plugin, tapi sejak inc/free-fonts.php beralih ke
atribut data-* milik Authentype 1.0.7 ia berhenti di-enqueue - yatim, sama
seperti header-foundry.php dulu. Blok CSS .foundry-tester (~80 baris) ikut
dihapus: tidak ada satu pun template yang mencetak markupnya, karena toolbar
yang benar-benar tampil milik plugin (.ath-free-live-preview__toolbar).

GAMBAR UNGGULAN DI KANAN DESKRIPSI

Halaman tunggal Free Font sebelumnya TIDAK PERNAH mencetak gambar unggulan
sama sekali - gambar yang sudah dipasang admin di editor tidak muncul di mana
pun. Kini deskripsi dan gambar berdampingan: teks kiri, gambar kanan
(minmax(0,1fr) / minmax(0,34%)), ditumpuk di bawah 900px dengan gambar turun
mengikuti urutan DOM.

Kalau salah satunya tidak ada, blok ini tidak memaksakan dua kolom. Kelas
pembedanya dipasang di PHP, bukan lewat :has() di CSS, supaya perilakunya
sama di peramban yang belum mendukung :has().

SATU BUG YANG KETAHUAN SAAT MERAPIKANNYA

Seluruh paragraf deskripsi free font selama ini MENEMPEL tanpa jarak.
Penyebabnya spesifisitas: ".foundry p { margin: 0 }" bernilai (0,1,1)
sedangkan ".foundry-body > * + * { margin-top: 1em }" hanya (0,1,0), jadi
reset itulah yang menang. Terukur: margin-top setiap <p> dihitung 0px.
Ditambah satu kelas jadi ".foundry .foundry-body > * + *" (0,2,0) dan aturan
itu menang tanpa perlu !important - terukur kini 16px.

Regresi luber/gutter untuk kedua halaman di 9 lebar: bersih seluruhnya.
