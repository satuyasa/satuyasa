# Satuyasa — Theme & Plugin WordPress

Repo ini berisi sepasang tema dan plugin WordPress custom, murni PHP tanpa build step (tidak perlu `npm install` / compiler).

## Struktur

```
wp-content/
├── themes/
│   └── satuyasa/            # Tema serbaguna (bisnis, portofolio, blog)
└── plugins/
    └── satuyasa-toolkit/    # Plugin pendamping: CPT Portofolio, form kontak, sosial media
```

## Tema: Satuyasa

Tema klasik (bukan full-site editing) yang ringan dan mudah dikustom lewat Customizer:

- Halaman depan (`front-page.php`) dengan hero yang bisa diatur judul/subjudul/tombolnya lewat **Tampilan > Kustomisasi**.
- Grid portofolio otomatis muncul di halaman depan jika plugin Satuyasa Toolkit aktif.
- Mendukung custom logo, menu utama & footer, widget sidebar + 3 kolom widget footer.
- Navigasi mobile responsif, dukungan komentar bertingkat, pagination.
- Tautan sosial media & teks footer diambil dari pengaturan plugin (opsional).

## Plugin: Satuyasa Toolkit

Menambahkan fitur yang biasanya dibutuhkan situs bisnis/portofolio:

- Custom Post Type **Portofolio** + taksonomi **Kategori Portofolio**.
- Meta box: nama klien, tautan proyek, tahun pengerjaan.
- Shortcode `[satuyasa_portfolio limit="6" columns="3" category="slug"]`.
- Shortcode `[satuyasa_contact]` — formulir kontak dengan honeypot anti-spam, mengirim email via `wp_mail()`.
- Halaman **Pengaturan > Satuyasa Toolkit**: email tujuan kontak, URL Facebook/Instagram, nomor WhatsApp, teks footer.

Plugin ini berdiri sendiri — tetap berfungsi walau memakai tema WordPress lain.

## Instalasi (lokal/staging)

1. Salin folder `wp-content/themes/satuyasa` ke instalasi WordPress Anda di `wp-content/themes/`.
2. Salin folder `wp-content/plugins/satuyasa-toolkit` ke `wp-content/plugins/`.
3. Di wp-admin: aktifkan plugin **Satuyasa Toolkit**, lalu aktifkan tema **Satuyasa**.
4. Atur menu, logo, dan hero lewat **Tampilan > Kustomisasi**.
5. Isi kontak & sosial media lewat **Pengaturan > Satuyasa Toolkit**.
6. Tambahkan konten pada menu **Portofolio**.

## Catatan pengembangan

- Semua file PHP sudah divalidasi dengan `php -l` (tanpa error sintaks).
- Kode mengikuti praktik keamanan WordPress standar: nonce untuk form, `esc_html()`/`esc_url()`/`esc_attr()` untuk output, `sanitize_*()` untuk input.
- Teks domain: `satuyasa` (tema) dan `satuyasa-toolkit` (plugin) — siap diterjemahkan (translation-ready).
