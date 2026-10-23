# Draft Artikel Blog — Konten Awal (Fase 4)

4 draft artikel siap-terbit untuk mengisi blog Aksara di awal peluncuran, sesuai Breakdown Task Fase 4 ("Isi konten blog awal — font pairing, tips desain, tutorial Canva"). Ditulis sebagai file Markdown di sini (bukan langsung dimasukkan ke database) karena environment development ini tidak punya instalasi WordPress aktif untuk diisi datanya — silakan salin ke **Posts > Add New** secara manual, atau import lewat WP-CLI (lihat di bawah).

| File | Kategori | Topik |
|---|---|---|
| `01-cara-memadupadankan-font.md` | Tips Desain | Prinsip dasar font pairing |
| `02-kesalahan-memilih-font-brand.md` | Tips Desain | 5 kesalahan umum memilih font brand |
| `03-tutorial-pakai-template-canva.md` | Tutorial | Alur lengkap pakai template Canva setelah beli |
| `04-memahami-lisensi-font.md` | Panduan | Penjelasan tiap jenis lisensi font (saling melengkapi halaman License) |

Setiap file punya front-matter (`title`, `slug`, `excerpt`, `kategori`) diikuti isi artikel dalam Markdown — format umum yang dikenali kebanyakan tool migrasi konten.

## Cara publish manual

1. Buka **Posts > Add New** di wp-admin.
2. Salin judul dari front-matter `title` ke kolom judul.
3. Salin isi artikel (di bawah front-matter) ke editor — kalau editornya Gutenberg/block editor, tempel sebagai teks lalu convert ke blocks; heading Markdown (`##`) otomatis terdeteksi jadi Heading block oleh Gutenberg saat paste.
4. Isi **Excerpt** dari front-matter `excerpt`.
5. Buat/pilih kategori sesuai front-matter `kategori`.
6. Publish.

## Cara import lewat WP-CLI (kalau tersedia di server Anda)

Contoh skrip sederhana (butuh `wp-cli` & PHP `yaml`/parsing manual front-matter — sesuaikan dengan tool migrasi yang biasa Anda pakai, mis. `wp import` dari WXR, atau plugin migrasi Markdown):

```bash
for f in content/blog/*.md; do
  # Pisahkan front-matter & body, lalu buat post — sesuaikan parsing sesuai kebutuhan.
  wp post create "$f" --post_type=post --post_status=publish
done
```

Skrip di atas contoh minimal — WP-CLI `post create` menerima seluruh isi file sebagai `post_content`, jadi untuk hasil rapi sebaiknya pisahkan dulu front-matter dari body (mis. lewat `awk`/`sed`, atau proses di PHP) sebelum importing, supaya front-matter tidak ikut masuk sebagai isi post.

## Konten tambahan untuk fase selanjutnya

Ini hanya starter — pertimbangkan menambah konten reguler (kalender editorial bulanan) begitu situs live, karena SEO organik untuk marketplace font/template sangat bergantung pada konten yang terus bertambah (lihat PRD Bagian 7: "SEO-friendly ... penting karena font/template marketplace sangat bergantung pada organic search").
