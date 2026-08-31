# Aksara Marketplace

Marketplace WordPress + WooCommerce untuk Font (per-style, lisensi bertingkat ala MyFonts), Canva Template, dan Canva Element. Lihat dokumen sumber (PRD, Starter Brief, Breakdown Task, mockup) untuk konteks lengkap — ringkasan & status implementasi ada di bawah.

## Status: Fase 3 selesai (download aman, sertifikat lisensi, wishlist)

| Fase | Status |
|---|---|
| **Fase 0** — POC preview engine | ✅ Selesai — lihat `services/font-preview-service/` |
| **Fase 1** — Fondasi produk & halaman inti | ✅ Selesai — lihat `wp-content/plugins/aksara-marketplace/` & `wp-content/themes/aksara/` |
| **Fase 2** — Font preview engine interaktif & kalkulator lisensi | ✅ Selesai — REST API `aksara/v1`, typing tool, kalkulator multi-style + diskon paket |
| **Fase 3** — Download aman, invoice lisensi, wishlist | ✅ Selesai — token unduh, sertifikat PDF, 3 tab My Account, wishlist, email otomatis |
| **Fase 4** — SEO, blog, polish, testing | ⏳ Belum dimulai |
| **Fase 5** — Multi-vendor, integrasi Canva API, multi-bahasa | ⏳ Opsional, di luar keputusan saat ini |

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
```

## Menjalankan di lokal

1. **WordPress + WooCommerce**: salin `wp-content/themes/aksara` dan `wp-content/plugins/aksara-marketplace` ke instalasi WordPress, aktifkan WooCommerce lalu plugin lalu tema. Detail lengkap ada di `readme.txt` masing-masing folder.
2. **Font preview service** (dibutuhkan sejak Fase 2 untuk typing tool interaktif di halaman produk font):
   ```bash
   cd services/font-preview-service
   pip install -r requirements.txt
   python3 test_subsetter.py   # validasi POC
   export AKSARA_FONT_STORAGE_DIR=/path/ke/wp-content/uploads/aksara-private
   python3 app.py              # jalankan service di localhost:5055
   ```
   Tanpa service ini berjalan, situs tetap berfungsi (kalkulator harga & checkout tidak bergantung padanya) — hanya area pratinjau tulisan yang menampilkan pesan tidak tersedia.

## Catatan keamanan penting

File font/template asli **tidak pernah** diekspos lewat URL publik — baik di halaman Home (yang sengaja tidak merender font asli produk, lihat `readme.txt` tema), di microservice preview (yang hanya mengirim subset glyph terbatas, bukan file lengkap), maupun di sistem unduhan pasca-pembelian (Fase 3): akses ke file font asli & tautan Canva selalu lewat token bearer acak yang dicabut otomatis kalau order di-refund/dibatalkan, tidak pernah ditulis ke path publik yang bisa ditebak. Detail lengkap ada di PRD Bagian 8 (Keamanan) dan `services/font-preview-service/README.md`.

## Yang belum diuji end-to-end

Seluruh kode (PHP & JS) sudah lolos lint (`php -l`, `node --check`) dan direview manual baris-per-baris, plus PDF writer & microservice sudah divalidasi lewat skrip pengujian nyata (lihat commit history). Tapi belum ada instalasi WordPress + WooCommerce aktif di environment pengembangan ini untuk uji end-to-end di browser sungguhan (alur checkout PayPal, isi email order, tampilan My Account di tema lain, dst.) — sebaiknya di-deploy ke staging dan dites langsung sebelum produksi.
