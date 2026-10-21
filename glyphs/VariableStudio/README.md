# Variable Studio untuk Glyphs 3

Dua plugin yang dipakai bersama:

| Bundle | Dibuka dari | Isi |
|---|---|---|
| `Variable Studio.glyphsPlugin` | **Window > Variable Studio** | jendela 7 tab: Titik, Axis & Master, Style, Italic, Master Baru, Pratinjau, Tebal / Tipis |
| `Variable Studio.glyphsReporter` | **View > Show Variable Studio** | gambar di Edit View: ID titik `kontur:indeks`, bayangan semua master, jalur titik antar master, bentuk hasil interpolasi (bisa dianimasikan) |

## Pasang

1. Unduh `dist/VariableStudio.zip`, ekstrak.
2. Klik dua kali kedua bundle (atau salin ke `~/Library/Application Support/Glyphs 3/Plugins/`).
3. Pastikan modul **Vanilla** terpasang: *Window > Plugin Manager > Modules*.
4. Restart Glyphs.

## Isi tiap tab

**Titik** – membaca semua titik glyph yang sedang dibuka: ID `kontur:indeks`, tipe, halus/tidak, dan koordinat x, y di setiap master (dan brace layer) berdampingan. Tanda `!` = tipe titik beda dengan master pertama.
- *Salin CSV*: semua koordinat ke clipboard.
- *Cek glyph ini* / *Cek seluruh font*: jumlah path, jumlah & tipe titik, titik awal, arah, komponen, anchor.
- *Samakan arah & titik awal*: membalik arah dan menggeser titik awal di master lain agar sama dengan master pertama (dipilih posisi yang paling mirip, jadi aman untuk huruf simetris seperti o).
- *Beri warna (flag)*: menandai glyph bermasalah dengan warna glyph; *Buka hasil di tab*.

**Axis & Master** – daftar axis beserta rentang desainnya; tambah axis dari preset (wght, wdth, ital, slnt, opsz, GRAD) atau buat sendiri (tag 4 huruf, bisa disembunyikan); ubah koordinat desain master; ubah Axis Location (nilai user); jadikan origin; tambah *Variable Font Setting*; tambah *Virtual Master*.

**Style** – membuat banyak instance sekaligus.
- Daftar tebal dan lebar ditulis `Nama=nilai` (nilai user: 100–900 untuk tebal, % untuk lebar). Ada tombol preset Thin–Black, Light–Bold, Condensed–Expanded, dll. Nama bebas, misalnya `Book=350` atau `Ultra=950`.
- Tegak dan/atau Italic → semua kombinasi lebar × tebal × miring, dengan nama otomatis (`Condensed SemiBold Italic`, `Italic`, ...).
- Pemetaan nilai user ke koordinat desain: *Linear*, *Geometris (Lucas de Groot)*, *Desain = user*, atau *Dari Axis Location master*.
- Opsional: Axis Location di master & instance (master yang belum punya dihitung balik), Axis Mappings (avar), style linking (Bold/Italic/Bold Italic ke Regular, Italic lain ke versi tegaknya), weightClass/widthClass, Elidable STAT untuk Regular/Normal/tegak, Variable Font Setting.
- *Pratinjau rencana* menampilkan semua instance beserta koordinatnya sebelum dibuat.

**Italic** – menduplikat master lalu memiringkan path & anchor (sudut bebas, pusat setengah x-height atau baseline). Komponen hanya digeser karena glyph dasarnya ikut dimiringkan. Pilihan axis: `ital` (0 → 1), `slnt` (0 → −sudut) atau tanpa axis (untuk file italic terpisah). Opsi kompensasi tebal stem (lebar dikali 1/cos sudut). Kerning ikut disalin. Bentuk lengkung tetap perlu dirapikan manual.

**Master Baru** – master baru dari dua master pada nilai axis berapa pun. Di luar rentang = ekstrapolasi, misalnya Thin atau Black dari Regular + Bold. Metrik, stem, anchor, posisi komponen, lebar dan kerning ikut diinterpolasi. Glyph yang tidak kompatibel disalin dari master A lalu dicantumkan di daftar. Ada juga *Instance → master* (memakai interpolasi Glyphs sendiri).

**Pratinjau** – mengatur reporter: pratinjau interpolasi, ID titik (opsional termasuk off-curve), bayangan master, jalur titik, brace layer. Pilih axis, geser slider, atau *Putar* untuk animasi bolak-balik Dari → ke dengan durasi siklus yang bisa diatur; tombol `[` `]` untuk melangkah. Interpolasi memakai model variasi OpenType (sama dengan fontTools, sudah dites berdampingan), jadi hasilnya sama dengan font variabel yang diekspor.

**Tebal / Tipis** – menebalkan atau menipiskan kontur **tanpa mengubah jumlah, urutan, atau arah titik**, jadi hasilnya langsung kompatibel untuk interpolasi.
- Isi tambahan tebal *stem tegak* dan *stem datar* (unit; negatif = lebih tipis). Contoh: stem Light 40 → Bold 160 = isi 120 (datar sekitar 90).
- Titik digeser tegak lurus terhadap garis (miter di sudut); handle kurva diskalakan mengikuti, sehingga lengkung tetap bulat. Kontur luar melebar, counter menyempit, termasuk bila arah path belum dirapikan atau kontur masih bertumpuk.
- *Pertahankan sidebearing*: LSB/RSB tetap, lebar glyph bertambah. *Pertahankan baseline, x-height, cap height*: setiap puncak/dasar yang menempel di garis metrik (termasuk overshoot, juga di tengah glyph seperti mangkuk g atau bahu n) tidak bergeser vertikal; tambahan tebalnya dipindahkan ke sisi seberang goresan, dan lengkung di sekitarnya tidak boleh menonjol melewati zona.
- Handle kurva: arah tetap (titik halus tetap halus); panjangnya dihitung ulang supaya titik tengah lengkung jatuh tepat di posisi offset sejatinya (geser tegak lurus, tebal tegak & datar dicampur sesuai kemiringan, termasuk efek bobot kontras dan titik zona yang dipegang). Jadi bagian diagonal busur ikut menebal sebanding, tidak tertinggal.
- **Glyph yang sedang dibuka**: tombol −10 / −2 / +2 / +10 atau nilai isian, bisa di-Undo. Centang *Hanya titik terpilih* untuk menebalkan satu stem saja.
- **Satu master sekaligus**: sumber (mis. Light) → *Master baru* (nama + nilai axis, otomatis = nilai sumber + tambahan) atau ke master yang sudah ada (mis. Bold yang masih salinan Light). Glyph komposit ikut menyesuaikan lebar dan posisi aksen; kerning disalin.
- **Bobot ekstrem (Thin ↔ Black)** – *Lindungi counter* (bawaan aktif):
  - Sisi yang menghadap ruang putih tertutup (counter, mata e, celah antar stem, aperture) hanya boleh memakan celah sampai tersisa minimal 20% (bisa diubah). Kekurangannya diambil alih sisi seberang goresan, jadi tebal yang diminta tetap tercapai dan huruf melebar seperti Black sungguhan.
  - Goresan tidak bisa lebih tipis dari *tebal min* (bawaan 4 unit), jadi Thin tidak membalik hairline.
  - Sisi lurus (stem) tetap lurus dan sejajar.
  - Ujung lekuk lancip (sambungan bahu n/m/h ke stem) bergeser paling jauh setengah kedalaman lekuk, jadi lekuknya mengecil tapi tetap ada.
  - Titik halus ikut bergeser sepanjang garis singgungnya sebanding posisinya, jadi puncak counter yang menyempit ikut ke tengah dan lengkung tidak terlipat.
- **Scan ketebalan**: mengukur tebal garis di setiap segmen semua glyph di master sumber (sinar tegak lurus ke dalam tinta, diambil lebar melintangnya, jadi ujung stem terukur selebar stem). Hasil: garis tertipis & tertebal (beserta glyph-nya), daftar per glyph (tertipis, tertebal, kontras, tegak/datar), dan batas otomatis dari huruf a–z, A–Z, 0–9. Klik dua kali untuk membuka glyph. Kontur bertumpuk (belum Remove Overlap) diukur sebagai gabungan tinta.
- **Mode**:
  - *Rata*: semua garis bertambah sama.
  - *Pertahankan garis tipis, tebalkan yang tebal*: garis ≤ batas tipis tidak bergeser, garis ≥ batas tebal bertambah penuh, di antaranya bertahap (cocok untuk huruf kontras/Didone: hairline tetap, stem makin berat).
  - *Pertahankan garis tebal, ubah yang tipis*: kebalikannya (menurunkan/menaikkan kontras).
  - Ujung garis yang tersembunyi di dalam kontur lain (mis. ujung palang H di dalam stem) tidak digeser memanjang agar tidak menembus keluar.
- Reporter: centang *Ukuran tebal garis* di tab Pratinjau untuk melihat garis ukur di Edit View (biru = tipis, merah = tebal, angka di tertipis & tertebal).
- Hasilnya titik awal yang baik; sudut tajam, sambungan stem, dan counter kecil tetap perlu dirapikan.

**Ukuran lebar tanpa kursor** (tab Pratinjau, digambar reporter dan selalu tampil, ikut berubah saat titik digeser):
- *Label lebar di setiap goresan*: kotak angka tebal goresan di setiap segmen, dengan garis ukur melintang. Label yang berdekatan tidak ditumpuk.
- *Garis ukur mendatar di*: garis putus-putus di ketinggian pilihan (bawaan `x/2, H/2`; bisa `x`, `H`, `a`, `d`, angka, `/n`, `*f`, dipisah koma). Setiap goresan yang terpotong diberi angka lebarnya.
- *Garis ukur tegak di tengah glyph*: tebal goresan datar (atas/bawah o, palang H, dll.).
- *Jarak putih*: counter dan sidebearing kiri/kanan di garis ukur (kotak biru).
- Komponen dipecah dulu dan kontur bertumpuk diukur sebagai satu tinta.

## Alur Thin → Black + Italic

1. Siapkan master (minimal dua, mis. Light dan Bold) dengan axis Weight.
2. *Titik* → *Cek seluruh font* → *Perbaiki semua hasil*; sisanya perbaiki manual.
3. Bila master Bold belum digambar: *Tebal / Tipis* → sumber Light, hasil ke Bold (atau master baru), isi tambahan stem → *Buat / isi master*, lalu rapikan.
4. Bila perlu master ekstrem: *Master Baru* → A = Light, B = Bold, nilai di luar rentang (atau *Tebal / Tipis* dengan nilai besar/negatif).
5. *Italic* → *Buat master italic*, lalu rapikan bentuk.
6. *Style* → preset Thin–Black, centang Tegak + Italic → *Pratinjau rencana* → *Buat instance*.
7. *Pratinjau* → *Putar* untuk memeriksa interpolasi.

## Untuk pengembang

```
python3 src/build.py                     # buat ulang kedua bundle dari src/
python3 tests/test_variable_studio.py    # tes dengan Glyphs tiruan
```

- Edit hanya `src/*.py`. `plugin.py` di dalam bundle adalah hasil build (gabungan `vs_core` + `vs_glyphs` + jendela/reporter).
- `src/loader/plugin` adalah loader biner dari GlyphsSDK (Apache 2.0).
- Tes menjalankan semua tombol jendela, reporter, dan operasi font pada Glyphs tiruan (`tests/mock/`). Belum dites di Glyphs sungguhan.

## Batasan

- Urutan kontur yang berbeda antar master hanya dilaporkan, tidak diubah otomatis.
- Komponen yang diskalakan/diputar di master italic dilaporkan untuk dicek manual.
- Tebal / Tipis: nilai negatif yang lebih besar dari tebal stem akan membalik kontur (terdeteksi oleh *Cek seluruh font*).
- *Instance → master* memakai `instance.interpolatedFont` dari Glyphs, jadi hasilnya tergantung pengaturan instance (mis. dekomposisi).
