# Aksara Marketplace

Marketplace WordPress + WooCommerce untuk Font (per-style, lisensi bertingkat ala MyFonts), Canva Template, dan Canva Element. Lihat dokumen sumber (PRD, Starter Brief, Breakdown Task, mockup) untuk konteks lengkap — ringkasan & status implementasi ada di bawah.

## Status: Fase 1 selesai (fondasi produk)

| Fase | Status |
|---|---|
| **Fase 0** — POC preview engine | ✅ Selesai — lihat `services/font-preview-service/` |
| **Fase 1** — Fondasi produk & halaman inti | ✅ Selesai — lihat `wp-content/plugins/aksara-marketplace/` & `wp-content/themes/aksara/` |
| **Fase 2** — Font preview engine interaktif & kalkulator lisensi | ⏳ Belum dimulai |
| **Fase 3** — Download aman, invoice lisensi, wishlist | ⏳ Belum dimulai |
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
2. **Font preview service** (dibutuhkan mulai Fase 2, tapi bisa dites berdiri sendiri sekarang):
   ```bash
   cd services/font-preview-service
   pip install -r requirements.txt
   python3 test_subsetter.py   # validasi POC
   python3 app.py              # jalankan service di localhost:5055
   ```

## Catatan keamanan penting

File font/template asli **tidak pernah** diekspos lewat URL publik — baik di halaman Home (yang sengaja tidak merender font asli produk, lihat `readme.txt` tema) maupun di microservice preview (yang hanya mengirim subset glyph terbatas dengan token kedaluwarsa, bukan file lengkap). Detail lengkap ada di PRD Bagian 8 (Keamanan) dan `services/font-preview-service/README.md`.
