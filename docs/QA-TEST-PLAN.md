# Rencana Uji Manual — Fase 4

Sesuai Breakdown Task Fase 4, beberapa item testing butuh instalasi WordPress + WooCommerce + payment gateway yang benar-benar berjalan (checkout sungguhan, klik di browser, dsb.) — environment development yang dipakai untuk membangun kode ini **tidak punya** MySQL/WordPress aktif (lihat catatan di root `README.md`), jadi item-item ini didokumentasikan sebagai checklist untuk dijalankan manual di staging, bukan diklaim sudah lolos.

Yang **sudah** diverifikasi otomatis di environment development (tidak perlu diulang manual, tapi boleh untuk sanity check):
- Semua kode PHP/JS lolos `php -l` / `node --check` (lihat riwayat commit tiap fase).
- PDF sertifikat lisensi (`Aksara_Simple_Pdf`) divalidasi dengan `pypdf` — struktur, halaman, dan ekstraksi teks benar.
- Microservice subsetting (`services/font-preview-service/`) diuji fungsional (`test_subsetter.py`) dan di-load-test (lihat `services/font-preview-service/README.md` bagian "Load test konkurensi").
- Kontras warna palet situs terhadap WCAG AA (lihat catatan di `wp-content/themes/aksara/style.css`, variabel `--ochre`).

---

## 1. Pembelian font — kombinasi style & lisensi

Siapkan 1 produk font dengan minimal 4 style (mis. Regular, Bold, Italic, SemiBold) dan harga terisi untuk minimal 2 jenis lisensi berbeda pada tiap style.

| # | Skenario | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 1.1 | 1 style + 1 lisensi | Pilih 1 style, pilih 1 lisensi, tambah ke keranjang | 1 baris cart dengan harga = harga style×lisensi tsb |
| 1.2 | Banyak style + 1 lisensi | Pilih 3 style, 1 lisensi, tambah ke keranjang | 1 baris cart, harga = jumlah 3 harga style tsb untuk lisensi itu |
| 1.3 | 1 style, beli 2x dengan lisensi berbeda | Tambah style A + lisensi Desktop, lalu style A + lisensi Web (transaksi terpisah, produk sama) | 2 baris cart TERPISAH (bukan digabung/di-overwrite) |
| 1.4 | Semua style sekaligus (paket lengkap) tanpa diskon diatur | Klik "Pilih Semua", tambah ke keranjang | Harga = jumlah semua style, TANPA potongan (kalau `_aksara_bundle_discount_percent` belum diisi admin) |
| 1.5 | Semua style sekaligus DENGAN diskon paket | Admin isi diskon paket 15% di metabox Font Styles, ulangi tes 1.4 | Harga di kalkulator & di cart terpotong 15% dari jumlah harga individual |
| 1.6 | Style tanpa harga untuk lisensi tertentu | Pilih style yang belum diisi harga untuk lisensi X | Checkbox style tidak muncul di daftar untuk lisensi X (harga kosong tersaring di kalkulator), ATAU kalau dipaksa lewat API langsung → REST menolak dengan pesan "belum memiliki harga" |
| 1.7 | Manipulasi harga dari client (percobaan curang) | Buka DevTools, ubah nilai harga yang dikirim ke `/aksara/v1/cart/add-font` (kalau ada) | Harga di cart TETAP dihitung ulang oleh server dari `aksara_style_prices` — nilai dari client diabaikan sepenuhnya (cek `Aksara_Cart_Handler::validate_combo()`) |
| 1.8 | Checkout sampai selesai | Selesaikan checkout skenario 1.1 sampai order berstatus *completed* | Order tersimpan dengan meta `_aksara_style_ids` & `_aksara_license_id` pada line item; token unduh otomatis dibuat |

## 2. Sistem unduhan aman

| # | Skenario | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 2.1 | Unduh setelah beli | Order completed → buka **My Account > Unduhan Saya** | Muncul baris per style yang dibeli, tombol Unduh berfungsi, file yang terunduh adalah font ASLI (bukan file subset pratinjau) |
| 2.2 | Token dipakai user lain | Salin URL unduh dari 2.1, buka di browser lain / mode incognito tanpa login | **Saat ini token TETAP berfungsi** (token = kredensial pembawa, sama seperti `download_permissions` bawaan WooCommerce) — ini BUKAN bug, dan diverifikasi dari desain di `class-download-manager.php`. Kalau kebijakan produk berubah ingin token terikat user, ini butuh perubahan desain, bukan sekadar bugfix. |
| 2.3 | Kuota unduhan habis | Unduh berulang sampai `download_count` = `download_limit` (default 50 — bisa dites lebih cepat dengan turunkan `download_limit` langsung di DB untuk keperluan tes) | Percobaan unduh berikutnya ditolak (HTTP 403, pesan "Batas jumlah unduhan... sudah tercapai"), tombol di My Account berubah jadi "Batas tercapai" |
| 2.4 | Order di-refund | Refund order dari 2.1 lewat wp-admin | Seluruh token milik order itu langsung `is_revoked=1`; percobaan unduh setelahnya ditolak (403 "sudah tidak berlaku") |
| 2.5 | Token kedaluwarsa waktu | **Tidak ada expiry waktu secara default** (lihat catatan arsitektur di `class-cleanup-jobs.php` — lisensi yang sudah dibeli tidak semestinya berhenti bisa diunduh). Kalau kebijakan retensi baru ditambahkan nanti (mengisi kolom `expires_at`), uji: set `expires_at` ke masa lalu langsung di DB, lalu coba unduh | Ditolak 403 "sudah kedaluwarsa"; baris tsb hilang dari daftar setelah cron `aksara_cleanup_download_tokens` berikutnya berjalan |
| 2.6 | Produk Canva | Beli produk Canva Template, order completed | Token dibuat dengan `resource_type=canva_asset`; klik link unduh → redirect ke `_aksara_canva_link` yang diisi admin |
| 2.7 | Sertifikat lisensi | Order 1.1 selesai | PDF sertifikat otomatis dibuat, muncul di **My Account > Sertifikat Lisensi**, bisa diunduh, dan (kalau order berisi item font) terlampir di email order selesai |
| 2.8 | Email guest checkout | Checkout sebagai guest (tanpa akun) | Email order selesai tetap berisi tautan unduh & lampiran sertifikat — akses tidak bergantung pada login (lihat `class-order-emails.php`) |

## 3. Pratinjau font (typing tool)

| # | Skenario | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 3.1 | Ketik & lihat pratinjau | Buka halaman produk font, ketik di kotak pratinjau, tunggu ~1 detik | Teks berubah tampilan sesuai font asli (lewat FontFace API), status "Memuat pratinjau…" hilang setelah selesai |
| 3.2 | Batas 100 karakter | Ketik/tempel teks >100 karakter | Input terpotong otomatis di karakter ke-100 (lihat `maxPreviewChars` di JS) |
| 3.3 | Rate limit | Kirim >40 request `/font-preview*` dalam 1 menit dari IP yang sama (mis. lewat script) | Request ke-41 dst. mendapat HTTP 429 |
| 3.4 | Produk draft tidak bisa dipratinjau | Panggil `/aksara/v1/font-preview` langsung dengan `style_id` milik produk berstatus draft | Ditolak 403 "Produk belum dipublikasikan" |
| 3.5 | Microservice mati | Matikan `font-preview-service`, ulangi 3.1 | UI menampilkan pesan "Pratinjau tidak tersedia" (bukan error JS yang merusak halaman); kalkulator harga & tombol tambah-ke-keranjang tetap berfungsi normal |

## 4. Payment gateway (PayPal — keputusan produk)

| # | Skenario | Langkah | Hasil yang diharapkan |
|---|---|---|---|
| 4.1 | Pembayaran sukses | Checkout dengan akun PayPal sandbox, selesaikan pembayaran | Order jadi *processing*/*completed*, token unduh & sertifikat dibuat, email terkirim |
| 4.2 | Pembayaran dibatalkan pembeli | Mulai checkout PayPal, klik "Cancel and return to merchant" | Order tetap *pending*/*failed*, TIDAK ada token yang dibuat, keranjang tetap ada supaya pembeli bisa coba lagi |
| 4.3 | Pembayaran gagal (kartu/saldo ditolak) | Gunakan skenario penolakan di PayPal sandbox | Order berstatus *failed*, pesan error jelas ke pembeli, tidak ada token yang terlanjur dibuat |
| 4.4 | Pembayaran pending (mis. eCheck) | Gunakan metode yang menghasilkan status pending di PayPal sandbox | Order berstatus *on-hold*, token BELUM dibuat sampai status berubah jadi completed/processing (cek hook di `Aksara_Download_Manager::init()`) |
| 4.5 | Mata uang & rekonsiliasi | Bandingkan nominal yang tampil di kalkulator vs. yang ditagih PayPal | Harus identik — kalkulator memakai `get_woocommerce_currency()`/`wc_price()`, bukan hardcode |

## 5. Regresi umum

- [ ] Halaman Home, Fonts, Templates, Elements, License, single product, Cart, Checkout, My Account tampil benar di Chrome/Firefox/Safari desktop.
- [ ] Ulangi semua di atas pada lebar layar mobile (≤480px) dan tablet (~768px) — perhatikan khusus: tabel cart/checkout bisa di-scroll horizontal tanpa merusak layout halaman, sidebar kalkulator lisensi di halaman produk font tidak "menempel" aneh saat ditumpuk ke 1 kolom di mobile (lihat perbaikan Fase 4 di `aksara-marketplace.css`).
- [ ] Navigasi keyboard penuh (Tab/Shift+Tab/Enter/Space) di typing tool: weight tabs, italic toggle, checkbox style, pilihan lisensi, tombol tambah-ke-keranjang — semua harus punya indikator fokus yang terlihat (lihat perbaikan `:focus-visible` Fase 4) dan bisa dioperasikan tanpa mouse.
- [ ] Screen reader dasar (VoiceOver/NVDA): kotak pratinjau punya label "Teks pratinjau font", tombol wishlist mengumumkan status (tambah/hapus dari wishlist).
- [ ] `<title>` dan meta description berubah sesuai halaman (cek "View Page Source", bukan DevTools Elements, supaya lihat HTML asli bukan hasil render JS).
- [ ] Google Rich Results Test pada 1 URL produk font & 1 produk Canva Template yang sudah publish — pastikan structured data Product (harga, ketersediaan) terbaca tanpa error.

## Kuota codepoint pratinjau (plugin v0.5.1)

Butuh staging dengan WordPress + plugin aktif + minimal satu produk font
berisi style .ttf/.otf, dan `font-preview-service` berjalan.

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka halaman produk font, ketik kalimat biasa di typing tool (mis. "Kopi pagi, ide baru") | Pratinjau tampil normal. Kuota terpakai ~13 dari 120. |
| 2 | Ganti-ganti teks wajar 5-10 kali (kalimat berbeda, huruf besar, angka) | Selalu normal — pemakaian wajar tidak boleh pernah kena batas. Ini yang paling penting diuji. |
| 3 | Ketik ulang teks yang PERSIS SAMA berkali-kali | Kuota tidak bertambah sama sekali (disimpan sebagai gabungan himpunan, bukan penghitung). |
| 4 | Tempel teks berisi banyak karakter unik (100 karakter berbeda-beda) berulang kali sampai tembus 120 | Muncul pesan "Batas pratinjau untuk style ini sudah tercapai…". |
| 5 | Setelah kena batas: kotak ketik | **Masih bisa diketik** — jangan terkunci. Ini disengaja. |
| 6 | Setelah kena batas: ketik ulang teks dari langkah 1 | Pratinjau tetap dirender — karakter yang sudah pernah dipakai masih boleh. |
| 7 | Setelah kena batas: buka style LAIN dari produk yang sama | Normal — kuota terpisah per style. |
| 8 | Setelah kena batas: buka dari perangkat/IP lain | Normal — kuota terpisah per klien. |
| 9 | Periksa pesan yang tampil saat batas tercapai | Harus menjelaskan batas, **bukan** "layanan tidak tersedia". Kalau muncul pesan layanan mati, berarti jalur 429 tidak tertangani. |
| 10 | Matikan `font-preview-service`, lalu ketik | Barulah pesan fallback layanan + gambar specimen statis muncul. Dua kondisi ini tidak boleh tertukar. |

Untuk mempercepat pengujian, batasnya bisa diturunkan sementara di
`functions.php` staging:

```php
add_filter( 'aksara_preview_codepoint_budget', function () { return 30; } );
```

Kuota tersimpan di transient `aksara_cpb_*` dengan masa berlaku 24 jam.
Untuk mereset saat menguji, hapus transient tersebut (mis. lewat WP-CLI:
`wp transient delete --all`) atau tunggu kedaluwarsa.
