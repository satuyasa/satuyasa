# Aksara Font Preview Service

Microservice kecil (Python + fontTools) yang menerima **1 file font + teks**, dan mengembalikan `.woff2` yang **hanya berisi glyph yang dibutuhkan** untuk merender teks tersebut. Ini yang memungkinkan typing tool di halaman produk font menampilkan pratinjau live tanpa pernah mengekspos file font lengkap sebelum dibeli.

Dibangun berdiri sendiri (tanpa dependency ke WordPress/plugin) sebagai POC Fase 0, dan sejak Fase 2 **sudah terintegrasi** ke plugin `aksara-marketplace` lewat `includes/class-preview-service-client.php` (dipanggil dari `includes/class-rest-controller.php`, endpoint `POST /wp-json/aksara/v1/font-preview` & `/font-preview-batch`).

## Seberapa penting service ini? (baca dulu sebelum panik)

Sejak optimasi pasca-Fase 4, service ini **tidak lagi menjadi titik kegagalan tunggal**. Pembagian tugasnya:

| Kebutuhan | Ditangani oleh | Butuh service ini? |
|---|---|---|
| Nama & contoh font di listing/Home | PHP GD (`class-specimen-image.php`) | **Tidak** |
| Pratinjau saat typing tool tidak bisa menghubungi service | Gambar specimen statis (fallback otomatis) | **Tidak** |
| Ketik teks sendiri & lihat langsung di halaman produk | Service ini (subset `.woff2`) | **Ya** |
| Harga, keranjang, checkout, unduhan, sertifikat lisensi | WordPress/WooCommerce | **Tidak** |

Jadi kalau service mati: pengunjung tetap melihat wujud font aslinya (sebagai gambar), toko tetap bisa menerima pesanan — yang hilang hanya kemampuan mengetik teks sendiri untuk dipratinjau. Admin juga langsung diberi tahu lewat notice di wp-admin dan halaman **WooCommerce > Status Layanan Aksara**.

## Di mana service ini dipasang?

**Bukan di dalam WordPress.** Ini proses Python yang berdiri sendiri, bukan plugin — jadi jangan taruh di `wp-content/`, `public_html/`, atau folder apa pun yang bisa diakses browser. Yang benar: folder biasa di **server yang sama**, di luar web-root.

Rekomendasi: **`/opt/aksara-font-preview/`**

```bash
sudo mkdir -p /opt/aksara-font-preview
sudo cp -r services/font-preview-service/. /opt/aksara-font-preview/
cd /opt/aksara-font-preview
sudo ./deploy/install.sh /var/www/situs/wp-content/uploads/aksara-private
```

Hubungannya dengan WordPress hanya dua, keduanya tidak lewat web:

1. **Filesystem yang sama** — service membaca berkas font langsung dari folder privat WordPress (`wp-content/uploads/aksara-private/`), read-only. Karena itu harus satu server: ia butuh akses baca ke folder itu.
2. **Loopback `127.0.0.1:5055`** — PHP memanggilnya lewat localhost. Port ini tidak boleh dibuka ke internet.

### Syarat server

| Butuh | Kenapa |
|---|---|
| Akses root/sudo (VPS, cloud server, atau dedicated) | Memasang unit systemd & user layanan |
| **Python 3.10+** | Ditentukan oleh dependency: `fonttools 4.63` & `gunicorn 26` sama-sama `Requires-Python >=3.10` |
| Paket `python3-venv` | `install.sh` membuat virtualenv sendiri, tidak mengotori Python sistem |
| Satu server dengan WordPress | Berbagi folder `uploads/aksara-private` |

### Kalau situsnya di shared hosting

Shared hosting (cPanel, hosting murah kebanyakan) **tidak bisa** menjalankan proses Python yang hidup terus-menerus dan tidak memberi akses systemd. Di kasus ini: **lewati saja service ini** — jangan cari cara memaksakannya.

Konsekuensinya cuma satu: pengunjung tidak bisa mengetik teksnya sendiri untuk dipratinjau. Selebihnya jalan normal — nama & contoh font di listing tetap tampil dalam font aslinya (dirender PHP GD), typing tool otomatis menampilkan gambar specimen statis, dan harga/keranjang/checkout/unduhan tidak pernah menyentuh service ini sama sekali (lihat tabel di atas).

Kalau typing tool interaktif itu penting, pindahkan situs ke VPS — bukan menaruh service ini di server terpisah, karena ia butuh akses baca ke folder font WordPress.

## Pemasangan yang disarankan: systemd (sekali jalan)

```bash
cd /opt/aksara-font-preview          # folder tujuan, lihat bagian di atas
sudo ./deploy/install.sh /path/ke/wp-content/uploads/aksara-private
```

Skrip ini membuat virtualenv, memasang dependency, menulis unit systemd (`deploy/aksara-font-preview.service`), mengaktifkannya supaya jalan otomatis saat boot & restart sendiri kalau crash, lalu memverifikasi endpoint `/health` merespons. Jumlah worker default mengikuti jumlah core CPU (minimal 2) sesuai temuan load test di bawah.

Opsional: `PORT=5055 WORKERS=4 RUN_USER=www-data sudo -E ./deploy/install.sh <dir>`

Perintah harian setelah terpasang:

```bash
sudo systemctl status aksara-font-preview
sudo systemctl restart aksara-font-preview
sudo journalctl -u aksara-font-preview -f
```

## Menjalankan manual (development / tanpa systemd)

`AKSARA_FONT_STORAGE_DIR` harus mengarah ke folder privat WordPress supaya `font_path` relatif yang dikirim plugin (mis. `fonts/xxxx.otf`) ketemu — **bukan** `./fixtures`, itu cuma untuk testing berdiri sendiri:

```bash
export AKSARA_FONT_STORAGE_DIR=/path/ke/wp-content/uploads/aksara-private
python3 app.py                                    # dev saja, single process
gunicorn -w 4 -b 127.0.0.1:5055 app:app           # mendekati produksi
```

Di dalam container (systemd tidak tersedia), jalankan perintah `gunicorn` di atas sebagai service tersendiri di `docker-compose.yml`.

Plugin memanggil `http://127.0.0.1:5055` secara default; override lewat konstanta `AKSARA_PREVIEW_SERVICE_URL` di `wp-config.php` kalau portnya beda atau (di luar rekomendasi Starter Brief) service dipindah ke server lain.

## Keputusan arsitektur (mengikuti Starter Brief)

- **Lokasi deploy:** server yang sama dengan WordPress, dipanggil lewat port internal (`127.0.0.1:5055`). Service ini **tidak boleh** diekspos ke publik.
- **Storage:** font asli disimpan di disk lokal (`AKSARA_FONT_STORAGE_DIR`, default `./fixtures` untuk POC ini), di luar web-root. WordPress akan memanggil service ini dengan `font_path` **relatif** terhadap direktori itu — bukan meng-upload ulang file setiap kali preview diminta, karena kedua proses berbagi filesystem yang sama.
- **Preview typing tool:** dipanggil dengan debounce ~1 detik dari sisi frontend (keputusan produk), bukan tiap ketukan tombol — mengurangi beban ke service ini secara signifikan.
- **Signed URL / expiring token:** **belum** diimplementasikan di sini — itu tanggung jawab `class-preview-service-client.php` di plugin (Fase 2). Service ini murni fungsi "font + text → woff2 bytes".

## Cara pakai

```bash
cd services/font-preview-service
pip install -r requirements.txt

# jalankan service (mengikat ke localhost saja)
python3 app.py            # default port 5055, atau set PORT=xxxx

# panggil dari server yang sama (font sudah ada di disk, gaya produksi)
curl -F "font_path=BricolageGrotesque-Regular.ttf" \
     -F "text=Aksara Marketplace" \
     http://127.0.0.1:5055/v1/subset -o preview.woff2

# atau upload file langsung (untuk demo/testing tanpa font_path)
curl -F "font=@fixtures/CrimsonPro-Regular.ttf" \
     -F "text=Hello" \
     http://127.0.0.1:5055/v1/subset -o preview.woff2
```

`GET /health` untuk cek service hidup.

## Menjalankan validasi POC

```bash
cd services/font-preview-service
python3 test_subsetter.py
```

Script ini adalah demo command-line yang diminta Breakdown Task Fase 0 — memvalidasi 3 hal terhadap font asli (`fixtures/*.ttf`, font Google Fonts berlisensi OFL):

1. **Subset benar-benar merender teks yang diminta** — semua glyph yang dibutuhkan ada, tidak ada yang hilang.
2. **Waktu respons di bawah target 800ms** dari PRD.
3. **Subset jauh lebih kecil & hanya mengekspos sebagian kecil dari charset lengkap** — bukan "seluruh font dibungkus ulang".

### Hasil pengujian (di environment ini)

| Font sumber | Ukuran asli | Glyph asli | Contoh subset | Ukuran subset | % charset terekspos | Waktu |
|---|---|---|---|---|---|---|
| Bricolage Grotesque Regular | 90.920 bytes | 597 | "Kopi pagi, ide baru, karya berani" | 2.424 bytes | 2,7% | ~90ms |
| Bricolage Grotesque Regular | 90.920 bytes | 597 | "AKSARA" | 1.068 bytes | 0,7% | ~70ms |
| Crimson Pro Regular | 106.696 bytes | 823 | "The quick brown fox jumps over…" | 5.116 bytes | 3,8% | ~125ms |

Semua kombinasi jauh di bawah target 800ms (p95 ~150-165ms di environment ini — server produksi sebaiknya tetap diukur ulang karena beban CPU untuk subsetting cukup terasa saat konkuren).

## Load test konkurensi (Fase 4)

Breakdown Task Fase 4 secara eksplisit meminta load test endpoint `/font-preview` karena ini bagian paling berat dari sistem. Diuji langsung di service ini (bukan lewat WordPress) dengan `requests` + `ThreadPoolExecutor`, hasilnya membongkar dua temuan nyata yang lalu diperbaiki/didokumentasikan:

**Temuan 1 — dev server Flask default itu single-threaded.** Burst 50 request dengan 20 klien konkuren ke `python3 app.py` (sebelum perbaikan): rata-rata **2.295ms** per request (naik ~20x dari baseline ~100ms), karena semua request diproses satu-satu secara berurutan. **Perbaikan:** `app.run(..., threaded=True)` ditambahkan (lihat `app.py`) — cukup untuk local/staging, TAPI thread Python tidak membantu banyak untuk kerja CPU-bound seperti subsetting (GIL), rata-rata cuma turun ke ~1.746ms.

**Temuan 2 — perlu multi-process (bukan cuma multi-thread) untuk beban CPU-bound ini.** Dijalankan ulang di bawah `gunicorn -w 4` (4 proses worker, bukan thread):

| Skenario | Konkurensi | Total request | Sukses | Latency (min / p50 / p95 / max) | Throughput |
|---|---|---|---|---|---|
| `python3 app.py` (single-thread, sebelum fix) | 20 | 50 | 30 (20 kena rate limit) | 1090 / — / — / 3468 ms | — |
| `python3 app.py` (dengan `threaded=True`) | 20 | 50 | 30 (20 kena rate limit) | 1032 / — / — / 2591 ms | — |
| `gunicorn -w 4` | 8 | 96 | 96 (0 gagal) | 126 / 194 / 206 / 215 ms | ~40 req/s |

**Kesimpulan untuk deployment produksi:** jangan pernah jalankan `python3 app.py` langsung di production (Flask sendiri sudah memperingatkan ini). Pakai WSGI server dengan **beberapa worker PROCESS**, bukan sekadar thread — mulai dari `gunicorn -w 4 -b 127.0.0.1:5055 app:app` (sesuaikan jumlah worker dengan jumlah core CPU server). Dengan 4 worker, layanan ini menangani ~40 request pratinjau/detik dengan p95 di bawah 210ms — jauh di atas kebutuhan realistis (debounce 1 detik di frontend berarti satu pengguna aktif mengetik paling banyak menghasilkan ~1 request/detik).

**Temuan 3 (efek samping) — rate limiter in-memory jadi per-worker, bukan global.** Dengan 4 worker gunicorn, batas "30 request/menit/IP" secara efektif menjadi ~120/menit karena tiap worker punya counter sendiri-sendiri di memori (dikonfirmasi: batch 50 request lolos semua tanpa kena 429 sampai counter gabungan terlampaui di test berikutnya). Ini **tidak diperbaiki** di sini secara sengaja — lihat komentar di `app.py` dekat `RATE_LIMIT_MAX_REQUESTS`: pertahanan rate-limit yang sesungguhnya sudah ada di lapisan WordPress (`Aksara_Rest_Controller::check_preview_rate_limit()`, transient yang dibagi semua PHP-FPM worker lewat database), jadi menambah rate limiter terdistribusi (mis. Redis) di sini untuk "memperbaiki" masalah yang sama akan jadi kerja ganda yang tidak perlu.

## Keamanan yang sudah ditangani

- **Path traversal:** `font_path` divalidasi harus tetap berada di dalam `AKSARA_FONT_STORAGE_DIR` (`_resolve_font_path` di `app.py`); path absolut atau `../..` ditolak dengan HTTP 400.
- **Batas panjang teks:** maksimal 100 karakter per request (`MAX_TEXT_LENGTH` di `subsetter.py`, dicek ulang di sisi WordPress oleh `Aksara_Rest_Controller::validate_preview_request()`), sesuai PRD Bagian 4.3.
- **Metadata di-strip:** tabel `name` disederhanakan ke ID yang minimal perlu (font family/style name), bukan mewariskan seluruh metadata font asli.
- **Rate limit dasar per-IP** (30 request/menit) di level service ini sebagai backstop POC-grade — **pertahanan sesungguhnya** ada di lapisan WordPress: `Aksara_Rest_Controller::check_preview_rate_limit()` pakai transient (persisten lintas worker/restart, 40 request/menit/IP) sebelum request sampai ke service ini sama sekali.
- **Binding ke localhost saja** — service ini dirancang tidak pernah diakses langsung dari luar server.
- **Style hanya bisa dipratinjau kalau produknya sudah publish** — dicek di `Aksara_Rest_Controller::validate_preview_request()`, mencegah endpoint dipakai mengintip font dari produk draft/privat.
- **Cache hasil subset (10 menit, transient WordPress)** — mengurangi beban berulang ke service ini untuk kombinasi style+teks yang sama, dibersihkan proaktif lewat cron `aksara_cleanup_preview_cache` (`Aksara_Cleanup_Jobs`).

## Struktur folder deploy

```
deploy/
├── install.sh                     # pemasang systemd satu perintah
└── aksara-font-preview.service     # template unit (placeholder diisi install.sh)
```

Unit systemd-nya sudah memakai pengetatan dasar: `NoNewPrivileges`, `ProtectSystem=strict`, `ProtectHome=read-only`, dan folder font dipasang `ReadOnlyPaths` — service ini memang cuma perlu MEMBACA berkas font dan mendengarkan di loopback.
- Signed/expiring URL bergaya file statis — sengaja tidak dipakai; endpoint WordPress mengembalikan data langsung (base64 dalam JSON) tanpa pernah menulis file ke direktori publik, yang secara desain lebih aman daripada URL yang bisa disalin-ulang (lihat komentar di `class-rest-controller.php`).

## Struktur

```
font-preview-service/
├── app.py              # Flask app: POST /v1/subset, GET /health
├── subsetter.py         # Logic subsetting inti (tanpa dependency Flask)
├── test_subsetter.py    # Demo/validasi command-line untuk Fase 0
├── requirements.txt
└── fixtures/             # Font contoh berlisensi OFL untuk testing
    ├── BricolageGrotesque-Regular.ttf (+ LICENSE-BricolageGrotesque.txt)
    └── CrimsonPro-Regular.ttf (+ LICENSE-CrimsonPro.txt)
```
