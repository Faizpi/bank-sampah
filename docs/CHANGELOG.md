# Catatan perubahan

## Tampilan bukti pencairan di ponsel (14 November 2026)

- Menempatkan logo dan nama bank atau dompet digital di tengah pada ponsel, tanpa mengubah posisi pada tablet dan desktop.
- Meringkas keterangan pada bukti menjadi “Simulasi. Uang asli tidak dikirim.” Bukti tetap berasal dari sistem bank sampah.

## Bahasa antarmuka lebih ringkas (14 November 2026)

- Menghapus catatan teknis pada Pengaturan serta penjelasan berulang di direktori, riwayat, bukti transaksi, dan filter laporan. Petunjuk penjemputan, kamera, serta halaman wilayah/katalog ditulis langsung sesuai tindakan pengguna.
- Memperpendek pemberitahuan administrasi tanpa menghilangkan batas nominal, validasi, status saldo, peringatan penghapusan permanen, atau keterangan bahwa halaman Cadangan belum membuat berkas backup.
- Mengganti frekuensi ringkasan Telegram menjadi harian/mingguan/bulanan dan menghapus penjelasan konfigurasi yang tidak diperlukan pada pemilihan tujuan pencairan. Pilihan langganan tetap opt-in.

## Label status pencairan seragam (14 November 2026)

- Menyatukan label status digital menjadi Pencairan berhasil, Pencairan gagal, dan Pencairan sedang diproses pada detail, riwayat, laporan, ekspor, notifikasi, backoffice, serta Telegram. Halaman detail memakai label model yang sama agar istilah tidak berbeda antarhalaman.
- Mempertahankan penanda digital (simulasi), Xendit Test Mode, dan keterangan bahwa tidak ada uang riil yang dikirim. Tidak mengubah status transaksi, saldo, atau alur provider.

## Pengaturan minimum dan kode tautan transaksi (13 November 2026)

- Menambahkan minimum pencairan yang dapat diubah Admin dari Pengaturan Teknis (Rp5.000 sampai Rp1.000.000), disimpan pada tabel pengaturan yang ada, dicatat pada audit, dan dipakai seragam pada validasi web serta Telegram. Nilai awal tetap Rp10.000. Batas aplikasi Rp5.000 belum membuktikan setiap kanal Xendit menerima nominal itu.
- Mengganti ID angka pada tautan detail transaksi baru dengan nomor transaksi/setoran, UUID pada tautan bukti privat/ekspor, serta kode nasabah/layanan pada alur setoran Petugas. Bookmark angka lama tetap didukung; otorisasi dan pemeriksaan pemilik tetap berlaku.
- Memperbarui referensi notifikasi dan penjagaan Telegram agar cocok dengan kode transaksi tanpa mengirim guard atau tautan kepada akun lain.
- Menampilkan logo tujuan di ringkasan warga, laporan Bendahara dan tabel Admin; membetulkan ukuran SVG bank/dompet, mencantumkan minimum pencairan pada form, dan menghapus footnote teknis yang berulang sambil mempertahankan pemberitahuan privasi sebelum konfirmasi.

## Rekonsiliasi status Xendit (13 November 2026)

- Pemeriksaan hosting menunjukkan GoPay berhasil di Xendit Test Mode tetapi masih diperiksa di website. Respons GET tidak menyertakan nominal sumber; pencocokan GET kini menerima field sumber yang tidak ada sambil tetap menolak nilai yang tidak cocok dan memeriksa tujuan serta nominal sebelum mengonversi saldo tertahan.
- Diagnostik status tidak pasti dengan ID provider menggunakan GET tanpa mengirim ulang atau mengubah saldo dan hanya menampilkan hasil kecocokan. Hasil di hosting setelah deployment masih perlu diverifikasi.

## Pemeriksaan penolakan request Xendit (13 November 2026)

- Mengonfirmasi pengajuan GoPay pada hosting mendapat `payout.http_400:API_VALIDATION_ERROR`; tidak ada klaim bahwa payout tersebut berhasil dibuat atau bahwa cron telah diverifikasi melalui log eksekusi.
- Memperluas pembacaan nama field validasi tersaring, termasuk awalan body/header, JSON pointer, array lokasi, dan rincian bertingkat. Pesan mentah dan nilai isian tetap tidak disimpan.
- Diagnosis memeriksa struktur snapshot request lama tanpa menampilkan nilai tujuan atau mengirim ulang. Lulus struktur inti bukan bukti kelengkapan persyaratan akun Xendit.
- Menyatukan riwayat tunai/digital pada laporan Bendahara dengan filter dan ekspor Excel; menambahkan kategori digital pada laporan backoffice serta detail modal pada tabel Admin tanpa tautan keluar panel.
- Mempertahankan lingkup pembayaran tunai sendiri dan digital per wilayah, tujuan tersamarkan, penanda simulasi pada ekspor, serta pemisahan dari kas fisik. Memperbaiki filter bulan berawalan nol.
- Berdasarkan penolakan alamat pada hosting, melengkapi alamat penelitian fiktif khusus Test Mode tanpa mengambil alamat Warga. Konfigurasi yang tidak lengkap ditolak sebelum pembuatan hold; pengajuan lama tidak ditulis ulang.
- Memisahkan pesan antarmuka dari diagnosis teknis: kode provider, HTTP, nama field API, dan petunjuk konfigurasi tidak ditampilkan pada halaman biasa maupun modal Admin.

## Penanganan penolakan Xendit (12 November 2026)

- Melengkapi objek negara/alamat penerima pada request Payouts v3 untuk web dan Telegram tanpa menyalin alamat pribadi Warga.
- Membedakan penolakan POST pertama yang terkonfirmasi dari timeout, kegagalan GET, konflik idempotensi, dan hasil tidak pasti; penolakan pertama yang sah melepas hold tanpa mutasi keluar.
- Menampilkan petunjuk error tersaring pada pantauan/diagnosis tanpa menyimpan pesan, body, header, atau nilai isian provider.
- Menambahkan pemulihan terarah pada deploy.php untuk kasus HTTP 400 lama, dengan konfirmasi operator, satu nomor SIM, pemeriksaan upaya pertama, dan audit tanpa pengiriman ulang.
- Memperjelas bahwa diagnosis konfigurasi bukan uji provider dan satu kolom webhook Payouts v3 dapat menerima kelima event.
- Menyatukan riwayat pencairan Warga pada pilihan Tunai/Digital dengan filter, baris transaksi, ikon, badge, dan komponen Green Ledger. URL digital lama diarahkan, bukan menghapus data; menu riwayat Telegram mengikuti pilihan yang sama.
- Menghapus teks larangan pengajuan ulang dan penjelasan tanpa Bendahara yang berulang dari tampilan pencairan normal, tanpa menghapus pengaman transaksi server.
- Merapikan bahasa halaman publik, riwayat/bukti Warga, formulir Petugas, laporan Bendahara, panel Admin, dan bot dengan Humanizer Indonesia. Menghapus uraian teknis berulang tanpa mengubah nominal, otorisasi, privasi, atau aturan penyerahan.
- Memperbaiki status memuat pada login/pendaftaran agar selebar formulir dan tetap memakai susunan flex saat Livewire menampilkan statusnya.

## Pencairan digital simulasi (10 November 2026)

- Menambahkan Xendit Test Mode mandiri pada web dan Telegram untuk BCA, Mandiri, BRI, BNI, DANA, GoPay; routing memerlukan konfirmasi akun provider.
- Memisahkan transaksi digital dari serah tunai, dengan hold, webhook terverifikasi, idempotensi, rekonsiliasi API, pemulihan timeout, serta reversal append-only.
- Menambahkan riwayat/bukti simulasi, notifikasi opt-in, pantauan Bendahara searea/Admin, CSV tersamarkan, logo lokal, dan aksi diagnosis deploy.php.
- Memperbarui privasi v2.2 secara prospektif dan konfirmasi data uji per pengajuan. Tidak menimpa persetujuan/tautan lama.
- Memperbaiki enum riwayat tunai, respons validasi JSON, dan kontras pesan error input yang ditemukan saat pengujian.
- Fitur default nonaktif; tidak ada uji akun provider nyata, deployment, atau perubahan database utama dalam rilis source ini.
- Pengaman pra-migrasi menyembunyikan akses sandbox dan mempertahankan rekonsiliasi biasa ketika tabel tambahan belum tersedia.

## Rilis prototipe penelitian 4 November 2026

- Menutup rangkaian prototipe dari alur persetujuan Admin berulang, cutover empat peran, penyempurnaan UI, hingga kanal Telegram.
- Menambahkan [linimasa prototipe](RESEARCH_TIMELINE.md) yang memetakan tahapan ke bukti di repositori.
- Menulis ulang README dengan konteks Desa Sindangheula, galeri maskot, alur terbaru, dan panduan lokal.
- Tidak ada perubahan pada aturan bisnis, schema, data, atau konfigurasi Telegram.

## Akses Telegram persisten 28 Oktober 2026

- Menghapus masa akses Warga 30 hari, pengurus 8 jam, dan batas 60 menit tanpa aktivitas. Semua role bot tetap dapat memakai akun selama tautan dan kewenangannya masih sah.
- Mempertahankan penautan awal 1:1, pemeriksaan keamanan/status/peran/area/claim, pencabutan, konfirmasi transaksi, serta kedaluwarsa tombol/isian yang dapat dimulai ulang lewat /menu.
- Mengganti /keluar menjadi petunjuk pemutusan, bukan pengakhiran izin waktu. Menyingkirkan tombol dan tanggal pembaruan akses dari profil/Admin.
- Menambah migration yang mempertahankan ID, generation, consent, dan transaksi pada tautan lama, tanpa reset atau seed ulang.
- Menyelaraskan ketentuan/privasi v2.1 secara prospektif serta menyediakan teks revisi skripsi tanpa menimpa sumber Word.

## Kanal Telegram (kode lokal, 25 Oktober 2026)

- Menambahkan webhook terverifikasi, update/outbox idempoten, penautan aktif 1:1 dari web, masa otorisasi tindakan, pencabutan Admin, dan antarmuka konfigurasi.
- Menambahkan alur Informasi, Warga, Petugas, dan Bendahara melalui service domain yang sama; termasuk penimbangan/finalisasi, pickup, sembako, layanan keliling, dan ekspor laporan pribadi.
- Menambahkan consent terpisah yang opt-in secara default mati untuk notifikasi transaksi, pengingat, ringkasan berkala, dan langganan pengumuman.
- Menambahkan penanganan foto opsional privat, pengingat/ringkasan, target publik dengan pembatasan kelompok kecil, retry dan retensi.
- Kolom kategori antrean diperlebar ke 40 karakter agar MySQL menerima label seperti `transaction_notifications`; suite Telegram lulus 44/44 di SQLite dan MySQL disposable.
- Menyelaraskan empat parameter Tabel 3 skripsi: izin Petugas/Bendahara berakhir juga setelah 60 menit tanpa aktivitas sah (kolom `last_activity_at`), klaim tautan tidak sah dibatasi lima kali per 15 menit per Telegram lalu ditunda, pengingat dipatok pukul 09.00 WIB sekali per hari, dan kiriman terjadwal menghormati jam tenang 20.00–07.00 WIB.
- Menerbitkan Ketentuan Operasional dan Kebijakan Privasi v2.0 yang mencakup kanal Telegram (penautan, data kiriman, consent default mati, retensi, pencabutan); versi default pendaftaran baru naik ke v2.0 tanpa permintaan persetujuan ulang massal bagi penerima v1.0.
- Harness tes menyediakan `.env.testing` kosong pada direktori runtime terisolasi sehingga suite tanpa file tersebut tetap bebas peringatan dan tidak pernah membaca `.env` developer; halaman pencabutan Admin diuji dengan fitur aktif tanpa mengubah konfigurasi normal.
- Telegram tetap nonaktif secara default pada rilis ini. Aktivasi production dilakukan setelah rilis melalui environment server. Catatan ini bukan bukti UAT atau persetujuan kebijakan privasi.

## Perapian copy publik dan urutan landing 9 Oktober 2026

- Catatan teknis statistik/foto di bawah kartu, penjelasan harga berulang, dan slogan footer generik dihapus dari UI publik.
- Judul ringkasan/katalog/harga dibuat langsung; petunjuk pemilahan, status foto/harga, angka dan tautan tetap ada.
- Kepala Desa dipindahkan utuh tepat sesudah hero, sebelum ringkasan dan sampah.
- Backend, hak akses, privasi, database, dan pengelolaan foto tidak diubah. [Bukti cleanup](PUBLIC_COPY_CLEANUP_2026-10-09.md).

## Landing dan foto katalog 9 Oktober 2026

- Ringkasan Warga aktif, Petugas aktif, setoran final, dan berat seluruh desa, dengan perlindungan kelompok kecil dan penghormatan publikasi Admin.
- Kartu jenis sampah/harga aktif pada landing; query, foto, dan harga yang sama pada katalog dan harga.
- Foto melalui aksi khusus Admin atau form jenis; unggahan wajib untuk jenis baru, edit teks mempertahankan foto lama.
- Foto publik berada di tabel/disk terpisah, dinormalisasi JPEG tanpa EXIF/GPS dan dengan orientasi kamera; bukti privat tidak dibuka.
- Relasi pilihan Filament disimpan melalui domain, bukan penulis otomatis kedua; label aksesibel/placeholder dirapikan.
- Migrasi hanya menambah tabel foto, tidak me-reset data utama. [Bukti dan panduan](LANDING_CATALOG_2026-10-09.md).

## Data utama 9 Oktober 2026

- Satu `DatabaseSeeder` menggunakan sumber SQL pemilik yang diekstrak menjadi JSON privat, bukan menjalankan dump mentah.
- Data sumber: 26 akun, 5 RW, 19 RT, empat penugasan, empat jenis sampah/harga. Superadmin menjadi Admin, tanpa rangkap peran.
- Hash kata sandi tetap; QR diterbitkan ulang dengan kunci lokal. Profil Warga pada akun Petugas lama tidak dibawa.
- Seeder demo/awal dipangkas; fixture workflow pindah ke `tests/Fixtures`. Bootstrap Admin production tetap terlindungi.
- Reset lokal memvalidasi dan membekukan sumber sebelum perubahan. Seed biasa menolak database berisi data.
- Data privat tidak diunggah ke GitHub. [Panduan](SEEDING.md).

## Penyempurnaan UI 8 Oktober 2026

- Perbaikan pilihan keliling, form edit jadwal/target, bukti publik anti-tamper, kuitansi draf, laporan, navigasi Admin, pickup terkunci, dan penutupan layanan lewat jam.
- Menu semua layanan, pickup satu form, hasil pencarian warga eksplisit, antrean Menunggu/Tugas saya dengan pagination dan lampiran dilipat.
- Akun pengurus+tugas atomik, tugas baru saat peran berubah, format angka/telepon lokal, label wilayah lengkap, dan tabel Admin bertumpuk di ponsel.
- Pemeriksaan saldo satu Admin dengan isian per record, selisih beralasan, tanpa perubahan saldo otomatis.
- Rincian/akibat pengecualian, kartu baca-saja dan pelengkapan diaudit, pengamanan preview/operasi teknis dormant.
- Kontrak desain, panduan, tutorial, README, proposal dan flowchart diperbarui. [Bukti pelaksanaan](IMPLEMENTATION_UI_2026-10-08.md).

## Cutover empat role 5 Oktober 2026

- Role scalar dengan constraint kanonis dan pengaman Admin terakhir.
- Penghapusan role/izin dinamis dan pengajuan oleh pengurus atas nama Warga.
- Setoran langsung final serta lampiran rutin opsional.
- Pickup, pencairan tunai, dan sembako menggunakan claim pelaksana searea.
- Tidak ada approval Admin rutin atau expiry otomatis layanan.
- Jalur exception, pemulihan akses yang dicabut, dan pengalihan pickup beralasan.
- Laporan Bendahara dibatasi ke pembayaran sendiri; koreksi resmi memakai reautentikasi.
- Reset demo lokal terjaga, seed empat akun/master tanpa transaksi.
- Panduan, README, proposal/flowchart, dan tes mengikuti kontrak baru.

Checkpoint sebelumnya tetap tersedia dalam riwayat Git. Hasil pengujian terbaru dicatat pada [validasi cutover](VALIDATION_CUTOVER_2026-10-05.md), bukan dianggap sama dengan checkpoint lama.
