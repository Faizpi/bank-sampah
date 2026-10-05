# Sindangheula Green Ledger — Kontrak Desain

## 1. Status kontrak

Dokumen ini mengikat implementasi UI publik, warga, petugas, bendahara, dan admin. Ini bukan mood board. Semua komponen harus memakai token, pola navigasi, state, breakpoint, aksesibilitas, dan larangan yang ditetapkan di sini.

Prinsip: mobile-first, sederhana bagi warga, task-first bagi petugas, action-first bagi admin, data-dense secara terkontrol, saldo-first, hangat, jelas, dan dapat diaudit.

### Tiga prioritas UI/UX publik yang disetujui

Redesign public frontend wajib mengutamakan tepat tiga prioritas berikut:

1. **CTA discovery:** CTA utama harus terlihat sejak hero, memakai label berbasis tindakan, memiliki tujuan yang jelas, dan mudah ditemukan tanpa bergantung pada gambar atau hover.
2. **Active nav:** navigasi harus menunjukkan halaman atau bagian yang sedang aktif melalui teks, penanda bentuk, dan state aksesibel, bukan warna saja.
3. **Public page template:** semua halaman publik yang diimplementasikan memakai shell, hero/banner editorial, struktur konten, CTA, dan footer yang konsisten, dengan variasi secukupnya sesuai tujuan halaman.

Ketiga prioritas ini berlaku untuk implementasi frontend publik dan tidak menambah kebutuhan backend, route, atau halaman baru.

## Kontrak visual publik, `referensi_style`

Kontrak ini menerjemahkan arah visual botani/editorial `referensi_style` ke dalam identitas Bank-Sampah. Referensi dipakai sebagai arah komposisi dan atmosfer, bukan sebagai sumber identitas Gardenie, copy Gardenie, logo Gardenie, foto Gardenie, data kontak Gardenie, atau konten berkebun Gardenie.

### Arah visual wajib

- **White canvas:** gunakan `--color-surface` sebagai kanvas utama publik. Gunakan `--color-warm-canvas` hanya untuk pemisahan section yang halus, bukan untuk mengubah halaman menjadi dashboard.
- **Sage dan deep green:** gunakan token Forest Green untuk aksen sage, `--color-deep-green` untuk heading dan area kontras, serta token teks yang sudah ada. Jangan membuat palet hijau baru dengan nilai mentah.
- **Tipografi tebal:** gunakan token `display`, `h1`, dan `h2` dengan bobot yang sudah ditentukan. Kontras ukuran harus tegas, tetapi body tetap minimal 16 px untuk warga.
- **Media dengan sudut organik:** gunakan `radius-lg` atau kombinasi radius yang sudah ditetapkan, dengan batas panel umum maksimal 16 px. Jangan memakai blob, radius ekstrem, atau crop yang mendistorsi aset.
- **Whitespace yang lapang:** pertahankan section 32 px pada mobile dan 48–64 px pada desktop. Jangan memadatkan hero, CTA, atau footer demi memasukkan lebih banyak konten.
- **Hero/banner editorial:** hero boleh memakai media lokal yang dipetakan di bawah, headline kuat, supporting copy singkat, dan CTA yang jelas. Hero tidak boleh menjadi carousel wajib atau mengorbankan keterbacaan.
- **Overlap yang terkendali:** overlap hanya boleh menjadi aksen satu lapis pada hero atau media, tidak menutupi teks, CTA, fokus keyboard, atau informasi penting. Jangan gunakan overlap sebagai kartu bersarang.
- **Footer multi-kolom:** desktop memakai beberapa kolom layanan dan navigasi yang terkelompok jelas. Mobile menumpuk kolom secara logis dengan heading yang tetap terbaca dan target sentuh sesuai kontrak.

### Pemetaan aset dan referensi

| Aset atau referensi | Penggunaan kontraktual |
|---|---|
| `homepage.jpg` | Media landing atau beranda publik, bila sesuai dengan konteks Bank-Sampah. |
| `section-header`, `footer`, `global-kit` | Arah visual untuk public shell, termasuk header, section heading, footer, dan elemen global. |
| `services.jpg` | Media untuk section `#layanan`, bukan alasan untuk membuat route baru. |
| `news.jpg` | Media untuk halaman atau section `/pengumuman`, sesuai route yang sudah tersedia. |
| `about`, `service-single`, `faq`, `testimonials`, `contact`, `page-404` | Referensi struktur atau visual saja. Referensi ini tidak membuat route atau halaman baru; `contact` di sini bukan route aplikasi. |

### CTA discovery dan active nav

- Hero memiliki satu CTA primer yang paling menonjol dan, bila dibutuhkan, satu CTA sekunder. Label harus menyebut tindakan dan tujuan, bukan “Klik di sini”.
- CTA yang sama harus memakai label dan tujuan yang konsisten di seluruh public shell. Aksi penting tidak boleh hanya muncul di footer atau tersembunyi dalam gambar.
- CTA primer memakai token Forest Green dan state `hover`, `pressed`, `focus-visible`, `disabled`, serta `loading` sesuai kontrak button. CTA sekunder tidak boleh bersaing secara visual dengan CTA primer.
- Nav menandai route aktif dengan `aria-current="page"`. Anchor section yang aktif boleh memakai `aria-current="location"` bila state tersebut benar-benar dikelola.
- State aktif memakai gabungan warna token, perubahan bobot atau underline, dan penanda bentuk atau posisi. State tidak boleh dibedakan dengan warna saja.
- Menu mobile harus mempertahankan urutan, label, focus management, dan state aktif yang sama dengan navigasi desktop.

### Template halaman publik

Urutan default public page template adalah:

1. Public shell: header, nama atau logo Bank-Sampah, navigasi ringkas, dan CTA akun bila memang tersedia.
2. Hero atau section header editorial: heading utama yang spesifik, supporting copy seperlunya, media lokal yang relevan, dan CTA utama.
3. Konten inti: section dengan satu tujuan per blok, whitespace lapang, heading berurutan, dan media atau data yang mendukung tujuan halaman.
4. CTA lanjutan: satu tindakan relevan setelah pengguna memahami konteks, tanpa mengulang CTA secara berlebihan.
5. Footer multi-kolom: navigasi, layanan publik yang memang sudah tersedia, serta tautan ketentuan dan privasi.

Template ini adalah pola layout, bukan instruksi untuk menambah route. Halaman yang sudah ada boleh menghilangkan blok yang tidak relevan, tetapi tidak boleh mengubah token, aksesibilitas, atau aturan navigasi.

### Token, aset lokal, ikon, dan reduced motion pada public frontend

- Semua warna, tipografi, spacing, radius, shadow, state, dan breakpoint memakai token Green Ledger di dokumen ini. Jangan menambahkan nilai hex, font, shadow, atau radius paralel tanpa keputusan desain baru.
- Gunakan hanya aset lokal repository yang sudah dipetakan atau disetujui. Jangan hotlink, mengambil foto stok baru, atau memakai URL eksternal sebagai ketergantungan visual. Pertahankan rasio aset, gunakan crop terkontrol, dan berikan `alt` yang bermakna. Aset dekoratif memakai `alt=""`.
- Ikon public frontend hanya SVG Lucide sesuai aturan di dokumen ini. Ikon dekoratif diberi `aria-hidden="true"`; tombol ikon saja wajib memiliki accessible name. Emoji, ikon dari screenshot referensi, dan logo Gardenie tidak boleh dipakai.
- Hormati `prefers-reduced-motion`. Pada public frontend, matikan parallax, auto-play carousel, hover lift, dan transform overlap yang tidak perlu. Gunakan transisi opacity minimum atau pergantian instan, tanpa menggeser layout besar atau menyembunyikan CTA.

### Exclusion frontend-only

Kontrak redesign ini terbatas pada presentasi dan interaksi frontend publik. Tidak termasuk perubahan atau persyaratan baru untuk backend, W10, PWA service worker, manifest, atau Wave10 tests. Jangan membuat route, endpoint, data dummy, klaim integrasi, atau halaman baru untuk memenuhi arah visual referensi.

## 2. Larangan mutlak

- Tidak menggunakan karakter emoji pada UI, notifikasi, empty state, ilustrasi, dokumentasi produk, atau ikon.
- Ikon utama hanya SVG asli dari Lucide; tidak ada emoji sebagai fallback.
- Tidak ada gradient neon, glassmorphism berat, shadow besar, radius berlebihan, kartu bersarang, atau ilustrasi stok lingkungan generik.
- Tidak ada status yang dibedakan hanya dengan warna.
- Tidak membuat UI warga atau petugas menggunakan Filament.
- Tidak memuat Alpine.js dua kali; gunakan instance bawaan Livewire 4.
- Tidak menggunakan React.

## 3. Token warna

```css
:root {
  --color-forest-600: #1E6A56;
  --color-forest-700: #185746;
  --color-deep-green: #123D32;
  --color-warm-canvas: #F6F5EF;
  --color-surface: #FFFFFF;
  --color-harvest-gold: #D6A84B;
  --color-terracotta: #C76B4F;
  --color-sky-blue: #3E7D92;
  --color-text-primary: #17241F;
  --color-text-secondary: #55635D;
  --color-border: #D9E1DC;
  --color-success-bg: #E7F3ED;
  --color-warning-bg: #FBF2DC;
  --color-danger-bg: #F8E9E4;
  --color-info-bg: #E8F1F4;
  --color-disabled-bg: #EEF1EF;
  --color-focus: #3E7D92;
  --color-overlay: rgb(18 61 50 / 55%);
}
```

Penggunaan:

| Token | Fungsi |
|---|---|
| Forest Green | CTA utama, link penting, navigasi aktif, grafik utama |
| Deep Green | Heading, app header, area kontras |
| Warm Canvas | Latar aplikasi |
| Surface | Panel, input, sheet/dialog |
| Harvest Gold | Status menunggu/perhatian, progres target |
| Terracotta | Error, tolak, batal, destructive |
| Sky Blue | Informasi, fokus, status berjalan |
| Text Primary/Secondary | Hierarki teks |
| Border | Divider, input, tabel |

Kontras teks/komponen harus memenuhi WCAG AA. Teks putih di Harvest Gold tidak diasumsikan aman; gunakan Deep Green bila kontras lebih baik.

## 4. Tipografi

Font utama: **Plus Jakarta Sans** dengan fallback `ui-sans-serif, system-ui, sans-serif`. Font disajikan lokal atau melalui sumber tepercaya dengan strategi performa dan privasi yang disetujui.

| Token | Mobile | Desktop | Line-height | Weight | Penggunaan |
|---|---:|---:|---:|---:|---|
| `display` | 32 px | 48 px | 1.1 | 700 | Hero publik terbatas |
| `h1` | 28 px | 36 px | 1.2 | 700 | Judul halaman |
| `h2` | 24 px | 30 px | 1.25 | 700 | Bagian utama |
| `h3` | 20 px | 24 px | 1.3 | 650 | Panel |
| `title` | 18 px | 20 px | 1.35 | 650 | Row/card title |
| `body` | 16 px | 16 px | 1.55 | 400 | Isi; minimum warga |
| `body-sm` | 14 px | 14 px | 1.5 | 400/500 | Metadata/admin |
| `label` | 14 px | 14 px | 1.35 | 600 | Label form |
| `caption` | 12 px | 12 px | 1.4 | 500 | Hanya metadata sekunder |
| `amount-lg` | 32 px | 40 px | 1.1 | 750 | Saldo utama, tabular nums |
| `amount` | 20 px | 24 px | 1.2 | 700 | Nilai transaksi |

Warga memakai body minimum 16 px. Angka rupiah/berat memakai `font-variant-numeric: tabular-nums`. Jangan memakai uppercase panjang; letter spacing hanya untuk label sangat singkat.

## 5. Spacing, ukuran, radius, shadow

### Spacing

Basis 4 px: `0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80`. Gap default komponen 12/16; section 32 mobile dan 48–64 desktop. Padding layar mobile 16 px; 390 px dapat 20 px bila ruang cukup.

### Target sentuh

- Warga/petugas: minimum 48×48 px.
- Admin desktop: minimum 40×40 px; aksi utama tetap 44–48 px.
- Jarak antar-target minimal 8 px.

### Radius

| Token | Nilai | Penggunaan |
|---|---:|---|
| `radius-sm` | 6 px | badge, chip |
| `radius-md` | 10 px | input, button |
| `radius-lg` | 14 px | panel, sheet |
| `radius-full` | 9999 px | avatar/status dot terkontrol |

Tidak memakai radius di atas 16 px untuk panel umum.

### Shadow

- `shadow-xs`: `0 1px 2px rgb(18 61 50 / 6%)` untuk pemisahan halus.
- `shadow-sm`: `0 4px 12px rgb(18 61 50 / 8%)` untuk sticky nav/sheet.
- Dialog: `0 16px 40px rgb(18 61 50 / 18%)` maksimum.
- Panel default mengandalkan border, bukan shadow.

## 6. Z-index, motion, breakpoint, container

### Z-index

| Layer | Nilai |
|---|---:|
| content | 0 |
| sticky header/table | 20 |
| bottom navigation | 30 |
| dropdown/popover | 40 |
| overlay | 50 |
| bottom sheet/dialog | 60 |
| toast | 70 |

### Motion

- Durasi: 120 ms micro, 180 ms default, 240 ms sheet/dialog.
- Easing: `cubic-bezier(0.2, 0, 0, 1)`; keluar 120–160 ms.
- Animasi hanya opacity/transform untuk performa; tidak menggeser layout besar.
- Hormati `prefers-reduced-motion`; hilangkan transform dan gunakan pergantian instan/fade minimum.
- Loading finansial tidak memakai animasi dekoratif; gunakan spinner Lucide/CSS dan teks.

### Breakpoint responsif

| Nama | Lebar | Kontrak |
|---|---:|---|
| compact | 360 px | Semua alur kritis satu kolom, padding 16, tanpa scroll horizontal |
| mobile | 390 px | Ruang nyaman 16–20, bottom nav tetap |
| tablet | 768 px | Grid 2 kolom bila membantu, sheet dapat menjadi dialog |
| desktop | 1280 px | Admin sidebar, container luas, tabel responsif |

Tailwind breakpoint dapat memakai `sm 640`, `md 768`, `lg 1024`, `xl 1280`, tetapi QA wajib pada 360/390/768/1280.

### Container

- Publik: max 1200 px.
- Warga: max 720 px; pusat pada tablet/desktop.
- Petugas: max 960 px; task list dapat dua kolom di tablet bila urutan tetap jelas.
- Admin: fluid dengan max 1600 px dan gutter 24–32 px.
- Form finansial: max 640 px untuk keterbacaan.

## 7. Shell dan navigasi

### Shell publik

Header logo/nama, navigasi ringkas, CTA masuk/daftar, konten, footer layanan. Mobile menggunakan menu dialog/sheet yang dapat dioperasikan keyboard. Halaman: beranda, harga, edukasi, jadwal/keliling, target/statistik, pengumuman, QR verifikasi, serta [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md). Dokumen ketentuan/privasi tersedia tanpa login dari footer dan halaman pendaftaran.

### Shell warga

- App header: nama halaman dan konteks akun. Jangan menjanjikan notification center atau push notification.
- Konten utama saldo-first.
- Bottom navigation maksimal lima: `Beranda`, `Setoran`, `Layanan`, `Riwayat`, `Akun`.
- Fitur dalam `Layanan`: penjemputan, pencairan, sembako, estimasi, jadwal keliling.
- Bottom nav fixed dengan safe-area padding; konten memiliki bottom padding cukup.

### Shell petugas/bendahara

- Header: konteks tanggal/lokasi, status koneksi, profil.
- Tugas hari ini sebagai halaman awal.
- Bottom navigation maksimal lima: `Tugas`, `Setoran`, `Pindai`, `Riwayat`, `Akun`.
- Bendahara dapat mengganti `Setoran/Pindai` dengan `Pembayaran/Laporan` sesuai permission tanpa melebihi lima.
- Aksi scan memiliki alternatif input nomor nasabah.

### Shell admin/superadmin

Filament 5 dengan custom theme. Sidebar dikelompokkan:

1. **Operasional:** dashboard, setoran, penjemputan/kapasitas, pencairan, sembako, tugas/jadwal.
2. **Data Master:** pengguna/nasabah/petugas, wilayah, jenis/kategori/satuan/kondisi, harga, paket.
3. **Program:** target, layanan keliling, pengumuman, statistik publik/partisipasi.
4. **Pengawasan:** laporan/ekspor Excel, rekonsiliasi, ledger/hold, dan audit log sesuai permission.
5. **Keamanan & Akses:** bantuan kata sandi, sesi pengguna, peran, dan izin sesuai permission.
6. **Administrasi sistem:** Health baca-saja untuk superadmin dengan `system.maintenance`; tidak ada item teknis aktif lain.

Sidebar menampilkan item sesuai permission. Tabel admin tidak dipaksakan ke layout kartu pada desktop; mobile memakai list/stack atau horizontal region yang diberi label dan fokus terkelola hanya bila tak terhindarkan.

## 8. Komponen inti

Setiap komponen memiliki state relevan: default, hover, active/pressed, focus-visible, disabled, loading, empty, error, success. Hover bukan satu-satunya indikasi.

### Button

- Varian: primary, secondary, quiet, danger, link.
- Tinggi warga/petugas 48 px; padding horizontal 16–20; ikon 20 px; gap 8.
- Focus ring 2 px Sky Blue + offset 2.
- Loading mempertahankan lebar, menampilkan indikator dan teks “Memproses”; disabled bukan opacity terlalu rendah.
- Aksi finansial destruktif/akhir memakai dialog konfirmasi dengan ringkasan nominal dan akibat.

### Input, select, textarea

- Label selalu terlihat di atas; placeholder bukan label.
- Untuk warga, petugas, dan bendahara, tinggi input/select 48 px; textarea minimum 120 px.
- Untuk field custom back-office desktop, tinggi kontrol minimum 44 px, padding horizontal 16 px, dan jarak label-ke-kontrol minimum 8 px. Kontrol tanggal/waktu tetap menyisakan ruang untuk ikon bawaannya.
- Border default, hover lebih gelap, focus ring, disabled surface abu, error Terracotta + ikon `circle-alert` + teks.
- Hint dan error terhubung `aria-describedby`.
- Rupiah dan berat memakai input mode sesuai, pemformatan visual, nilai canonical di server.
- Select native untuk pilihan sederhana; combobox accessible untuk data panjang.
- Pada pendaftaran, kontrol penerimaan ketentuan berupa checkbox kosong secara awal, dengan label eksplisit dan tautan ke [Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0](TERMS_AND_PRIVACY.md). Kontrol wajib dapat dioperasikan keyboard, memiliki nama aksesibel, dan tidak boleh dicentang otomatis. Jika belum diterima saat kirim, tampilkan error inline yang terhubung ke kontrol serta ringkasan kesalahan.

### Upload

- Tombol pilih/kamera 48 px, area drop hanya pelengkap desktop.
- Jelaskan format, ukuran, jumlah, dan kewajiban foto.
- Preview memiliki nama/ukuran, progress, ganti, hapus, error per file, dan alt/label.
- Jangan hanya menampilkan thumbnail tanpa status.

### Amount display

- Label (`Saldo tersedia`), nilai utama tabular, helper (`Saldo tertahan Rp…`), dan waktu pembaruan.
- Nilai negatif yang tidak semestinya menjadi error, bukan sekadar warna merah.
- Privasi opsional dengan tombol Lucide `eye/eye-off` dan label aksesibel.

### Status badge

- Selalu teks + ikon Lucide + warna/background.
- Menunggu: `clock-3` + gold; berjalan: `loader-circle`/`truck` + blue; sukses: `circle-check` + green; tolak/error: `circle-x` + terracotta; batal: `ban`; kedaluwarsa: `timer-off`.
- Tinggi minimum 28, padding 6×10, radius-sm. Ikon 16 px.

### Card/panel

- Satu surface, border, radius-lg, padding 16–24.
- Tidak menaruh card di dalam card. Gunakan divider, section, atau inset datar.

### Disclosure dan panduan berurutan

- Konten yang dapat dibuka memakai kontrol `details`/`summary` atau tombol yang setara secara semantik, label yang menjelaskan aksi, indikator chevron, dan state terbuka/tertutup yang terlihat.
- Chevron mengarah ke bawah saat tertutup dan berubah arah saat terbuka; kontrol memiliki target sentuh dan focus ring yang sama dengan tombol lain.
- Langkah pemeriksaan memakai daftar bernomor semantik. Nomor, judul langkah, dan penjelasan dipisahkan secara visual agar mudah dipindai; pada layar sempit daftar menjadi satu kolom.
- Klik seluruh panel hanya jika satu tujuan; tetap sediakan nama link aksesibel.

### Bottom navigation

- Maksimal lima item; tinggi konten minimum 64 + safe area.
- Ikon 22–24 px dan label 12–13 px selalu tampil.
- Active memakai Forest Green, ikon+label+indikator bentuk; tidak hanya warna.

### App header

- Tinggi 56–64 px; sticky bila membantu.
- Tombol kembali memiliki label aksesibel; judul tidak terpotong tanpa strategi.
- Tidak menumpuk banyak CTA; aksi tambahan masuk menu.

### Bottom sheet dan dialog

- Mobile: sheet dari bawah untuk pemilihan/konfirmasi ringan; desktop: dialog pusat.
- Trap focus, `aria-modal`, judul atau deskripsi, tombol tutup yang jelas, Escape, dan pengembalian fokus ke pemicu.
- Handle visual bukan satu-satunya cara menutup.
- Aksi utama/destruktif dipisahkan; tidak auto-close saat error.

### Toast

- Untuk konfirmasi nonkritis; status finansial juga tetap terlihat pada halaman/riwayat.
- Teks + ikon; `aria-live` sesuai urgensi; dapat ditutup; maksimum 1–2 aktif.
- Error yang memerlukan tindakan tidak hanya berupa toast.

### Skeleton

- Meniru struktur tanpa shimmer agresif; reduced-motion statis.
- Jangan menggantikan empty/error. Loading lebih dari batas wajar menampilkan teks status.

### Empty state

- Ikon Lucide sederhana 32–40 px, judul, penjelasan, satu CTA relevan.
- Tanpa karakter emoji atau ilustrasi stok.
- Bedakan “belum ada data” dari “filter tidak menemukan hasil”.

### Error dan success state

- Inline alert memakai ikon, judul, isi, dan aksi retry/kembali.
- Success finansial menampilkan nomor bukti, nilai, waktu, status, CTA lihat/cetak, bukan hanya toast.
- Error tidak menghapus input aman dan memindahkan fokus ke ringkasan.

### Timeline

- Status vertikal: ikon, teks, waktu, pelaku/catatan aman.
- Current step diberi `aria-current=step`; endpoint gagal tidak menampilkan langkah sukses berikut sebagai tercapai.
- Koreksi menampilkan sebelum/sesudah dan dampak saldo.

### Transaction row

- Urutan: jenis/nomor + status, waktu/metode, berat, nilai rata kanan, chevron/link.
- Tinggi minimum 72 px; nilai tabular; koreksi diberi label eksplisit.
- Pada compact, metadata membungkus tanpa mengecil di bawah minimum.

### Task row

- Prioritas: jenis tugas, warga/lokasi, waktu/jatuh tempo, status, CTA tunggal.
- Target 64–80 px; swipe tidak menjadi satu-satunya aksi.
- Kelompok “Belum dimulai”, “Sedang dikerjakan”, “Selesai” dengan jumlah.

### Table

- Header jelas/sticky pada data panjang; sort memiliki `aria-sort`; checkbox berlabel.
- Angka rata kanan dan tabular; status teks+ikon; action menu berlabel.
- Pagination server-side; filter aktif terlihat dan dapat dihapus.
- Pada 360/390, admin memakai row stack/list untuk tabel kompleks; warga tidak memakai tabel data padat.

### Pagination

- Tampilkan sebelumnya/berikutnya minimal 48 px untuk warga/petugas, info halaman/hasil, dan state disabled.
- Admin desktop boleh nomor halaman; mobile tidak memadatkan semua nomor.

### Chart

- Memiliki judul, periode, unit, legenda, tooltip keyboard bila interaktif, dan tabel/ringkasan data alternatif.
- Palet Forest/Gold/Sky/Terracotta dengan pola/label; tidak bergantung warna.
- Sumbu tidak menipu; mulai nol untuk bar bila relevan; format kg/rupiah Indonesia.
- Heatmap RT/RW memiliki legenda teks dan tabel alternatif.

### QR display

- Area putih dengan quiet zone cukup; ukuran minimum 200×200 px untuk tampilan utama.
- Label konteks, nomor referensi masked, tombol unduh/cetak, dan instruksi.
- Jangan menaruh QR di atas pola/warna; token tidak ditampilkan mentah.
- Fallback nomor nasabah/verifikasi tersedia.

## 9. Pola halaman utama

### Dashboard warga

1. Header salam ringkas tanpa dekorasi berlebih.
2. Panel saldo tersedia utama; saldo tertahan dan total masuk/keluar sekunder.
3. Quick actions: estimasi, penjemputan, pencairan, sembako; maksimal empat terlihat.
4. Status pengajuan aktif.
5. Riwayat terbaru.
6. Jadwal/pengumuman/edukasi kontekstual.

### Setoran petugas

1. Identifikasi QR/nomor dan konfirmasi nama.
2. Daftar item timbang datar, bukan nested card.
3. Ringkasan subtotal/total sticky pada mobile.
4. Bukti dan konfirmasi final.
5. Success receipt dengan QR verifikasi.

### Tugas petugas

Filter “Hari ini”, area, status; task row; CTA kontekstual; indikator offline hanya informasi karena transaksi memerlukan koneksi.

### Dashboard admin

Action queue di atas (verifikasi, pickup, approve), metrik ringkas, trend, operasional hari ini, aktivitas audit. Maksimal satu level panel; grafik tidak mendominasi tindakan.

## 10. Responsif wajib

| Lebar | Kewajiban |
|---|---|
| 360 | Satu kolom; body 16 warga; target 48; bottom nav tidak bertabrakan; dialog jadi sheet; tabel kompleks jadi list; QR tidak overflow. |
| 390 | Gutter 16–20; amount tetap satu baris atau skala token terkendali; quick action 2×2. |
| 768 | Warga tetap fokus dengan max-width; form/detail dapat 2 kolom; sheet boleh dialog; petugas task/detail split bila jelas. |
| 1280 | Admin sidebar tetap; content fluid; tabel/filter horizontal; warga/petugas centered tanpa membentang berlebihan. |

Semua lebar diuji untuk zoom 200%, teks panjang Bahasa Indonesia, nilai rupiah panjang, error, loading, keyboard, dan reduced motion.

## 11. Aksesibilitas

- WCAG AA; kontras teks, ikon penting, border input, focus, dan status diuji.
- Struktur heading berurutan; landmark header/nav/main/footer.
- Setiap input memiliki label, hint/error, required state, dan autocomplete tepat.
- Focus visible selalu; urutan DOM mengikuti visual; tidak ada keyboard trap.
- Target sentuh minimal 48 px di warga/petugas.
- Status dan chart memakai teks+ikon+warna/label.
- Livewire loading/status menggunakan `aria-live` tanpa pengumuman berulang.
- Dialog/sheet/menu/combobox mengikuti pola ARIA yang benar.
- Gambar bermakna memiliki alt; dekoratif alt kosong. QR memiliki penjelasan tekstual.
- Error summary fokus dan menaut ke field.
- Mendukung zoom 200%, orientation, reduced motion, dan screen reader.

## 12. Filament custom theme

Filament 5 hanya untuk back-office. Theme harus:

- memetakan primary ke Forest Green, danger Terracotta, warning Harvest Gold, info Sky Blue;
- menggunakan Plus Jakarta Sans;
- memakai Warm Canvas dan Surface;
- menyamakan radius, shadow, focus ring, badge, button, form, table, chart, dan spacing;
- mengelompokkan sidebar sesuai enam kelompok yang ditetapkan pada shell admin/superadmin;
- mempertahankan density admin tanpa mengecilkan aksesibilitas;
- menggunakan kontrol form custom minimum 44 px dengan padding dan jarak label yang konsisten, serta disclosure dengan chevron dan state terbuka/tertutup yang jelas;
- mengotorisasi resource/action dengan policy dan permission granular;
- tidak menjadi sumber komponen UI warga/petugas.

## 13. Checklist implementasi komponen

Untuk setiap komponen/halaman, review wajib memastikan:

- [ ] Token warna/typography/spacing/radius digunakan.
- [ ] Default, hover, active, focus, disabled, loading tersedia bila relevan.
- [ ] Empty, error, dan success tersedia untuk data atau alur request yang relevan.
- [ ] Target sentuh dan body text memenuhi kontrak aktor.
- [ ] Status bukan warna saja.
- [ ] Ikon Lucide SVG dengan label aksesibel.
- [ ] Tidak ada karakter emoji, ilustrasi stok generik, nested card, neon/glass/heavy shadow.
- [ ] Responsif 360, 390, 768, 1280.
- [ ] Keyboard, screen reader, zoom, reduced motion diuji.
- [ ] Jalur gagal tidak terlihat sebagai sukses.

## 14. Referensi

- Struktur fitur: [PRODUCT.md](PRODUCT.md)
- Flow: [USER_FLOWS.md](USER_FLOWS.md)
- Validasi/state: [VALIDATION.md](VALIDATION.md)
- UI shell teknis: [ARCHITECTURE.md](ARCHITECTURE.md)
- Pengujian: [TEST_PLAN.md](TEST_PLAN.md)
