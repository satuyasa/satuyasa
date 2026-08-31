# Aksara Font Preview Service — Fase 0 Proof of Concept

Microservice kecil (Python + fontTools) yang menerima **1 file font + teks**, dan mengembalikan `.woff2` yang **hanya berisi glyph yang dibutuhkan** untuk merender teks tersebut. Ini yang memungkinkan typing tool di halaman produk font (lihat `mockup-font-product.html`) menampilkan pratinjau live tanpa pernah mengekspos file font lengkap sebelum dibeli.

Dibangun berdiri sendiri (tanpa dependency ke WordPress/plugin) agar bisa dites & divalidasi dulu sebagai POC sesuai Breakdown Task Fase 0, sebelum diintegrasikan ke plugin di Fase 2.

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

## Keamanan yang sudah ditangani di level POC ini

- **Path traversal:** `font_path` divalidasi harus tetap berada di dalam `AKSARA_FONT_STORAGE_DIR` (`_resolve_font_path` di `app.py`); path absolut atau `../..` ditolak dengan HTTP 400.
- **Batas panjang teks:** maksimal 100 karakter per request (`MAX_TEXT_LENGTH` di `subsetter.py`), sesuai PRD Bagian 4.3 — membatasi seberapa banyak glyph bisa diekstrak per request.
- **Metadata di-strip:** tabel `name` disederhanakan ke ID yang minimal perlu (font family/style name), bukan mewariskan seluruh metadata font asli.
- **Rate limit dasar per-IP** (30 request/menit) sebagai lapisan pertahanan tambahan — **bukan** pengganti rate limiting yang sesungguhnya. Implementasi produksi (Fase 2) harus melakukan rate limiting yang persisten (bukan in-memory, hilang saat restart) di level WordPress REST endpoint/reverse proxy, terikat ke sesi/IP, sesuai catatan keamanan di PRD Bagian 8.
- **Binding ke localhost saja** — service ini dirancang tidak pernah diakses langsung dari luar server.

## Yang sengaja belum ada di Fase 0 ini (menyusul di fase lanjut)

- Signed/expiring URL untuk hasil subset (Fase 2 — jadi tanggung jawab plugin).
- Caching hasil subset per kombinasi teks umum (disebut di Breakdown Task Fase 2).
- Rate limiting yang persisten & terikat sesi (bukan in-memory per proses).
- Deployment sebagai service produksi (systemd unit, WSGI server seperti gunicorn — dev server Flask bawaan **tidak boleh** dipakai di production, sudah otomatis diperingatkan oleh Flask sendiri saat dijalankan).

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
