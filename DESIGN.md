# Design

## Source of truth

Status: Active. Diperbarui 23 Oktober 2026. Berlaku untuk publik, Warga, Petugas, Bendahara, dan Admin. Kontrak token/komponen ada di [docs/DESIGN.md](docs/DESIGN.md); aturan workflow di [docs/WEB_WORKFLOW.md](docs/WEB_WORKFLOW.md). Keputusan di bawah menggantikan penjelasan pra-cutover yang bertentangan. Sumber keputusan: audit Filament/Blade 8 Oktober, katalog berfoto 9 Oktober, serta perbaikan keterlacakan dan konfirmasi transaksi yang disetujui 21 Oktober 2026.

## Brand

Identitas Bank Sampah Sindangheula tetap hangat, sederhana, dapat dipercaya, berwarna hijau botani. Pertahankan aset lokal dan tipografi yang ada. Hindari dekorasi berlebihan, istilah teknis, emoji, atau identitas visual baru.

## Product goals

Pengguna dapat mengetahui status, pelaksana, dan tindakan berikutnya tanpa meminta persetujuan rutin Admin. Kurangi formulir berulang dan panel kosong. Lindungi angka keuangan, identitas, klaim pelaksana, dan jejak audit. Telegram, WhatsApp, deployment, reset database utama, dan klaim UAT/SUS bukan bagian pekerjaan ini.

## Personas and jobs

- Warga menggunakan akun sendiri untuk kartu, saldo, riwayat, dan layanan.
- Petugas menimbang, menjemput, mengoperasikan layanan keliling, dan menyerahkan sembako di area tugasnya.
- Bendahara menangani pembayaran tunai penuh dan melihat pembayaran sendiri.
- Admin mengurus konfigurasi, akun, wilayah, koreksi, pemeriksaan kas, dan pengecualian; bukan pelaksana fisik rutin.

## Information architecture

Navigasi Warga: Beranda, Kartu, Layanan, Riwayat, Akun. Layanan menyediakan semua layanan, bukan hanya penjemputan. Petugas: Tugas, Setoran, Layanan, Riwayat, Akun; Tugas memisahkan Menunggu dan Tugas saya yang aktif, sedangkan Riwayat memuat Setoran/Penjemputan/Sembako yang sudah dikerjakan sendiri dan hanya-baca. Bendahara: Tugas, Pembayaran, Laporan, Akun. Admin kembali ke pusat kerja dari layar bersama; master/teknis ada di kelompok lanjutan yang dapat dilipat.

Urutan landing: hero → Kepala Desa → ringkasan → sampah yang diterima → layanan → cara kerja → konten maskot → akses akun → footer. Bagian Kepala Desa dipindahkan utuh sesudah hero sesuai permintaan pengguna, bukan diubah isinya.

## Design principles

Satu layar memiliki satu tujuan utama. Tampilkan tindakan yang memang sah sekarang. Catatan, estimasi, foto, dan riwayat detail dapat dilipat. Foto rutin opsional. Pemeriksaan identitas tetap dilakukan tetapi dapat digabung dengan tombol mulai setoran. Rekonsiliasi menjadi pencatatan hasil pemeriksaan oleh satu Admin, bukan pengajuan kepada Admin kedua.

## Visual language

Gunakan token, font Plus Jakarta Sans, SVG, warna, radius, spacing, dan reduced motion dari docs/DESIGN.md. Jangan membuat lapisan desain atau pustaka frontend baru.

## Components

Gunakan komponen Blade ui yang ada. Antrean dipaginasi, hasil pencarian warga tidak memilih diam-diam kandidat pertama, dan lampiran tampil dalam disclosure. Tabel kompleks Filament menyediakan ringkasan/aksi ponsel; detail transaksi keuangan baca-saja tersedia sebelum keputusan pengecualian.

Landing menambah empat kartu ringkasan hijau: Warga aktif, Petugas aktif, setoran tercatat, dan kg terkumpul; empat kolom desktop, dua pada ponsel. Tidak memakai istilah Driver atau mengartikan penugasan sebagai status online. Bagian sampah memakai kartu foto 4:3 dan harga aktif. `public.waste-card`/`public.waste-photo` serta `PublicWasteCatalog` dipakai bersama oleh landing, katalog, dan harga. Foto kosong/gagal harus diberi keterangan, bukan foto palsu. Aksi Foto Admin tidak meminta perubahan harga atau kondisi.

Penyempurnaan 21 Oktober 2026: tautan Akses Panel Admin pada login tampil sebagai tombol sekunder selebar tombol Masuk ke Akun, dengan latar putih, teks/border hijau, target sentuh dan fokus keyboard yang jelas. Gunakan token yang sama dengan tombol Daftar Sebagai Warga. Peran tetap dikelola melalui Pengguna; tidak ada CRUD role/permission dinamis. URL lama diarahkan ke Pengguna hanya setelah autentikasi dan pemeriksaan akses Admin.

Setoran memakai baris timbang datar: jenis selebar baris, kondisi dan berat berdampingan pada ponsel, tetap berlabel dan bertarget sentuh 48 px. Total diperbarui dari server setelah perubahan input. Perkiraan yang dilewati bukan nol. Status maskot/badge dan bukti setelah pemuatan ulang mengikuti transaksi tersimpan. Pembayaran tunai dan penyerahan sembako memakai satu dialog ringkas nama/nominal sebelum pencatatan akhir; Periksa lagi menutup dialog tanpa mutasi. Ini konfirmasi pelaksana, bukan persetujuan Admin atau kewajiban foto baru.

Penyelarasan 23 Oktober 2026: beranda Warga menjadi acuan seluruh antarmuka akun. Urutannya salam ringkas → saldo → tindakan cepat → pengajuan aktif → setoran terbaru. `ui.page-heading` menyatukan judul utama dan maskot lokal; `ui.action-card` menyatukan ikon Lucide, label tindakan, dan tujuan layanan. Dashboard Petugas/Bendahara menempatkan pengantar dan tindakan sebelum antrean; tidak ada pengantar kedua di bawah daftar.

Kartu navigasi memakai hover lift 2 px/180 ms, tombol memiliki feedback tekan, dan baris transaksi hanya berubah latar/border agar data tidak bergerak. Fokus keyboard selalu terlihat; reduced motion menonaktifkan transform. Label field tetap terlihat, contoh isian menggunakan placeholder spesifik, dan tanggal tetap memakai kontrol native. `wrapper-class` pada primitive input/select/textarea mengatur kolom field utuh, bukan hanya kontrolnya.

Prinsip Appllama diadaptasi untuk web Laravel yang sudah ada: hierarki, satu keluarga ikon, ritme spacing, dan verifikasi interaksi di browser. Tidak menambah Expo/React Native, tema gelap, pustaka animasi, atau klaim riset layar Appllama MCP ketika koneksinya tidak tersedia. Teks persetujuan Admin rutin, pengulangan judul, dan penjelasan internal dihapus; status saldo disisihkan, error, konfirmasi serah fisik, serta peringatan penyerahan ganda tetap dipertahankan.

## Accessibility

Label, error, loading, fokus, dan accessible name berbahasa Indonesia. Tidak ada aksi sah yang hanya dapat dilakukan melalui kamera, hover, atau warna. Kegagalan validasi terlihat pada field/ringkasan. Target sentuh mengikuti kontrak komponen; pemeriksaan pembaca layar nyata tetap belum boleh diklaim selesai tanpa bukti.

## Responsive behavior

Validasi 360/390/768/1280. Form ponsel satu kolom; pasangan kondisi/berat pada baris timbang boleh dua kolom jika tetap terbaca tanpa overflow. Nominal/nama/status/aksi tidak mengharuskan geser horizontal pada layar operasional utama. Desktop Admin tetap mendukung tabel lengkap.

## Interaction states

Bedakan kosong, tidak ditemukan, menunggu, diklaim, terkunci, selesai, dan gagal. Draf Petugas bukan kuitansi Warga. Kunci pengecualian menyembunyikan aksi biasa. Layanan keliling yang masih terbuka tetap dapat ditutup setelah jam jadwal lewat. Receipt/public verification selalu berasal dari data server, termasuk invalidasi token.

## Content voice

Gunakan Penjemputan, Warga, Ambil tugas, Uang sudah diserahkan, dan Riwayat pembayaran saya. Jelaskan dana yang disisihkan belum dibayar tanpa membebani pengguna dengan jargon ledger/hold. Jangan menyebut persetujuan Admin pada transaksi rutin. Verifikasi akun sekali, pengaturan awal tugas, dan pengecualian adalah konteks berbeda.

Halaman publik memakai judul langsung, angka, dan petunjuk seperlunya. Definisi teknis statistik, cara penyimpanan foto/harga, serta catatan untuk Admin tetap dalam dokumentasi, bukan paragraf di bawah kartu. Hindari tumpukan eyebrow/judul/deskripsi yang mengulang pesan yang sama. Status foto kosong/gagal, harga belum tersedia, kondisi, satuan, tanggal harga, dan aksesibilitas tetap dipertahankan.

## Implementation constraints

Laravel 13, Livewire 4, Filament 5, Blade/Tailwind yang ada. Normalisasi format Indonesia hanya sebelum validasi; backend menyimpan nilai kanonik. Penugasan baru eksplisit saat peran berubah. Pembuatan akun pengurus dan tugas atomik. Klaim, saldo tertahan, transaksi atomik, otorisasi area, idempotensi, dan pemeriksaan serah fisik tidak dipangkas. Tindakan teknis dibatasi per halaman; pratinjau destructive berasal dari server.

Penambahan publik 9 Oktober memang membutuhkan data backend dan endpoint foto; tidak dibatasi exclusion redesign frontend-only lama. Foto katalog memakai tabel/disk khusus, terpisah dari bukti privat; wajib saat membuat jenis baru melalui Admin. Unggahan lama belum memiliki foto dan dapat dilengkapi tanpa mengubah transaksi. Statistik transaksi kelompok kecil disembunyikan (bukan nol palsu), dan allowlist/nonaktif publikasi Admin yang sudah ada tetap dihormati. [Kontrak dan bukti](docs/LANDING_CATALOG_2026-10-09.md).

## Open questions

- [ ] Kemudahan penggunaan oleh warga/pengurus nyata: pemilik proyek; membutuhkan UAT, bukan sekadar tes otomatis.
- [ ] Pengujian kamera/perangkat desa dan pembaca layar nyata: pemilik proyek; belum ada bukti peserta/perangkat.
