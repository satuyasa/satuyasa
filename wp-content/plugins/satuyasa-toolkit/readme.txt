=== Satuyasa Toolkit ===
Contributors: satuyasa
Tags: portfolio, custom post type, contact form, shortcode
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Menambahkan Custom Post Type Portofolio, shortcode formulir kontak, dan pengaturan tautan sosial media. Dirancang sebagai pendamping tema Satuyasa, namun tetap berfungsi dengan tema lain.

== Description ==

Satuyasa Toolkit menambahkan:

* Custom Post Type **Portofolio** beserta taksonomi **Kategori Portofolio**.
* Meta box detail portofolio (nama klien, tautan proyek, tahun pengerjaan).
* Shortcode `[satuyasa_portfolio limit="6" columns="3" category="slug-kategori"]` untuk menampilkan grid portofolio di mana saja.
* Shortcode `[satuyasa_contact]` untuk formulir kontak sederhana (dengan honeypot anti-spam) yang mengirim email via `wp_mail()`.
* Halaman pengaturan (Pengaturan > Satuyasa Toolkit) untuk email tujuan kontak, tautan Facebook, Instagram, nomor WhatsApp, dan teks tambahan footer.

== Installation ==

1. Unggah folder `satuyasa-toolkit` ke `/wp-content/plugins/`.
2. Aktifkan plugin melalui menu "Plugin" di WordPress.
3. Buka **Pengaturan > Satuyasa Toolkit** untuk mengisi kontak dan sosial media.
4. Tambahkan konten pada menu **Portofolio** yang muncul di sidebar admin.
5. Gunakan shortcode `[satuyasa_portfolio]` atau `[satuyasa_contact]` pada halaman/postingan mana pun.

== Changelog ==

= 1.0.0 =
* Rilis awal: Custom Post Type Portofolio, shortcode kontak, dan pengaturan sosial media.
