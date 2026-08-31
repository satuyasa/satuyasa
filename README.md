# Aksara Marketplace

Marketplace WordPress + WooCommerce untuk Font (per-style, lisensi bertingkat ala MyFonts), Canva Template, dan Canva Element. Lihat dokumen sumber (PRD, Starter Brief, Breakdown Task, mockup) untuk konteks lengkap — ringkasan & status implementasi ada di bawah.

## Status: Fase 4 selesai + UI dirombak ke sistem visual monokrom (v0.5.0)

| Fase | Status |
|---|---|
| **Fase 0** — POC preview engine | ✅ Selesai — lihat `services/font-preview-service/` |
| **Fase 1** — Fondasi produk & halaman inti | ✅ Selesai — lihat `wp-content/plugins/aksara-marketplace/` & `wp-content/themes/aksara/` |
| **Fase 2** — Font preview engine interaktif & kalkulator lisensi | ✅ Selesai — REST API `aksara/v1`, typing tool, kalkulator multi-style + diskon paket |
| **Fase 3** — Download aman, invoice lisensi, wishlist | ✅ Selesai — token unduh, sertifikat PDF, 3 tab My Account, wishlist, email otomatis |
| **Fase 4** — SEO, blog, polish, testing | ✅ Selesai — lihat ringkasan & keterbatasan di bawah |
| **Fase 5** — Multi-vendor, integrasi Canva API, multi-bahasa | ⏳ Opsional, di luar keputusan saat ini |

### Ringkasan Fase 4

- **SEO**: meta description & Open Graph (tema `inc/seo.php`) — structured data Product, sitemap, dan meta title ternyata sudah otomatis dari WordPress/WooCommerce core, tidak perlu kode tambahan (didokumentasikan supaya jelas, bukan diam-diam terlewat).
- **Performa**: cache transient untuk query yang tadinya jalan ulang tiap Home dibuka.
- **Aksesibilitas**: kontras warna harga (`--ochre`) diperbaiki dari gagal WCAG AA (2,9-3,5:1) jadi lolos (5,9-6,5:1); fokus keyboard terlihat jelas di seluruh situs; opsi lisensi di typing tool diubah dari `<div>` jadi elemen yang benar-benar bisa dioperasikan lewat keyboard.
- **Responsif**: tabel WooCommerce & baris spesimen font ditata ulang untuk layar sempit.
- **Monitoring**: setiap error dari endpoint `aksara/v1` & order yang gagal bayar otomatis ter-log, dengan action hook siap pakai untuk Sentry.
- **Load test nyata** terhadap `font-preview-service`: menemukan & memperbaiki bug performa asli (dev server Flask single-threaded), membuktikan kebutuhan WSGI multi-process untuk produksi — lihat angka lengkapnya di `services/font-preview-service/README.md`.
- **Konten blog** awal (4 draft artikel) di `content/blog/`.
- **Rencana uji manual** (`docs/QA-TEST-PLAN.md`) untuk skenario yang butuh WordPress+WooCommerce+PayPal sungguhan.

## Sistem visual: monokrom (DESIGN.md)

Sejak v0.5.0 tema & plugin mengikuti `docs/DESIGN.md` ("Studio Few — Style
Reference"): galeri huruf serba tinta-di-atas-kertas, tanpa warna kromatik,
tanpa shadow, pemisah cuma hairline 1px, radius tepat dua nilai (6px tombol /
2px sisanya), dan satu pola tombol (Trial outline + View terisi).

Ini **menggantikan** palet hangat dari `mockup-home.html` (paper #EDEBE3,
indigo, ochre) yang dipakai Fase 1-4. Kalau menemukan token lama di kode atau
dokumen, itu sisa yang harus dibersihkan, bukan pilihan yang masih berlaku.

Diverifikasi dengan merender halaman di Chromium headless lalu mengaudit
pikselnya: **0 piksel berwarna** setelah antialiasing subpiksel dimatikan, dan
`scrollWidth == clientWidth` di lebar 375px, 768px & 1440px (tidak ada scroll
horizontal). Penyimpangan yang disengaja beserta alasannya — terutama token
Ash yang gagal kontras WCAG — didokumentasikan di
`wp-content/themes/aksara/readme.txt`.

**wp-admin sengaja TIDAK ikut monokrom.** DESIGN.md adalah acuan etalase;
dasbor tetap memakai bahasa visual WordPress yang sudah dikenal admin.

## Keputusan arsitektur yang sudah ditetapkan

| Keputusan | Pilihan |
|---|---|
| Model bisnis | Single-vendor |
| Integrasi Canva | Link/file statis (bukan Canva API resmi) |
| Skema harga web license | Flat price (bukan tier per-pageview) |
| Payment gateway | PayPal |
| Base tema | Full-custom dari nol |
| Preview typing tool | Debounce ~1 detik saat diimplementasikan di Fase 2 |
| Volume katalog awal | Menengah (ratusan produk) — bulk-upload style font diprioritaskan sejak Fase 1 |
| Storage file privat | Disk lokal + permission ketat (`.htaccess`) |
| Lokasi microservice | Server sama, port internal (localhost) |

## Struktur repo

```
wp-content/
├── themes/aksara/                    # Tema frontend (lihat readme.txt di dalamnya)
└── plugins/aksara-marketplace/       # Plugin: product type, DB, metabox admin, cart (lihat readme.txt)
services/
└── font-preview-service/             # POC Fase 0: microservice subsetting font (Python + fontTools)
content/
└── blog/                             # Fase 4: draft artikel blog siap-terbit
docs/
├── DESIGN.md                         # Acuan sistem visual etalase (monokrom) — dirujuk dari CSS & readme tema
└── QA-TEST-PLAN.md                   # Fase 4: checklist uji manual (butuh staging WP+WooCommerce+PayPal)
```

## Menjalankan di lokal

1. **WordPress + WooCommerce**: salin `wp-content/themes/aksara` dan `wp-content/plugins/aksara-marketplace` ke instalasi WordPress, aktifkan WooCommerce lalu plugin lalu tema. Detail lengkap ada di `readme.txt` masing-masing folder.
2. **Font preview service** — dibutuhkan **hanya** untuk typing tool interaktif (ketik teks sendiri) di halaman produk font. Ini proses Python berdiri sendiri, **bukan** plugin: salin foldernya ke luar web-root di server yang sama (mis. `/opt/aksara-font-preview/`), jangan ke `wp-content/`. Butuh VPS dengan akses sudo & Python 3.10+; di shared hosting langkah ini dilewati saja. Pasang sekali sebagai layanan systemd:
   ```bash
   sudo mkdir -p /opt/aksara-font-preview
   sudo cp -r services/font-preview-service/. /opt/aksara-font-preview/
   cd /opt/aksara-font-preview
   sudo ./deploy/install.sh /path/ke/wp-content/uploads/aksara-private
   ```
   Skrip ini menyiapkan virtualenv, memasang unit systemd (auto-restart & jalan saat boot, gunicorn multi-worker sesuai jumlah core), lalu memverifikasi `/health`. Untuk development, `python3 app.py` masih bisa dipakai.

   **Tanpa service ini situs tetap berjalan normal**: nama & contoh font di listing dirender PHP sendiri lewat GD sebagai gambar, typing tool otomatis mundur ke gambar specimen statis, dan harga/keranjang/checkout/unduhan sama sekali tidak bergantung padanya. Statusnya bisa dipantau di **WooCommerce > Status Layanan Aksara**.

## Catatan keamanan penting

File font/template asli **tidak pernah** diekspos lewat URL publik. Tiga cara font ditampilkan, semuanya tanpa mengirim berkas fontnya:

1. **Listing/Home** — gambar PNG hasil render server (PHP GD). Yang dikirim cuma piksel; gambar raster tidak bisa dipasang balik jadi font.
2. **Typing tool** — subset `.woff2` berisi hanya glyph yang diketik, dibatasi 100 karakter/permintaan, dengan rate limit per-IP.
3. **Setelah dibeli** — berkas asli lewat token bearer acak yang dicabut otomatis kalau order di-refund/dibatalkan, tidak pernah ditulis ke path publik yang bisa ditebak.

Detail lengkap ada di PRD Bagian 8 (Keamanan), `services/font-preview-service/README.md`, dan komentar di `class-specimen-image.php`.

## Yang belum diuji end-to-end

Seluruh kode (PHP & JS) sudah lolos lint (`php -l`, `node --check`) dan direview manual baris-per-baris. Beberapa bagian sudah divalidasi lewat pengujian nyata di environment ini: PDF writer (lihat commit Fase 3), fungsi subsetting font, dan load test konkurensi microservice (lihat `services/font-preview-service/README.md` — load test ini bahkan menemukan & memperbaiki bug performa asli).

Yang **belum** bisa diuji di sini karena tidak ada instalasi WordPress + WooCommerce + MySQL aktif (dan akses keluar ke wordpress.org diblokir oleh kebijakan jaringan environment ini): alur checkout PayPal sungguhan, isi email order yang benar-benar terkirim, tampilan responsif/aksesibilitas di browser asli, kombinasi pembelian style+lisensi end-to-end, dan skenario token/kuota unduhan. Checklist lengkapnya ada di `docs/QA-TEST-PLAN.md` — jalankan di staging sebelum produksi.
