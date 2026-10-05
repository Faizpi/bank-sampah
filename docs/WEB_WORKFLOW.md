# Workflow Web Bank Sampah Sindangheula

Keputusan 5 Oktober 2026: layanan web dibuat lebih ringkas untuk warga dan pengurus. Dokumen ini menjelaskan implementasi saat ini, bukan rancangan bot. WhatsApp tidak digunakan; Telegram belum tersedia.

## Warga

Registrasi dan persetujuan ketentuan tetap memerlukan verifikasi admin. Setelah aktif, warga melihat saldo tersedia, saldo tertahan, riwayat, serta kartu nasabah.

Pickup memakai alamat awal dari profil, tanggal pilihan, jenis/perkiraan, dan satu sampai dua foto. Area tunggal dipilih otomatis; beberapa area tetap harus dipilih. Tanggal adalah permintaan, bukan reservasi slot.

Pencairan tunai memerlukan nominal, tanggal, dan lokasi pengambilan. Sembako memerlukan paket aktif dan area yang sah. Keduanya membuat hold, belum saldo keluar. Pengulangan dengan kunci yang sama mengembalikan pengajuan sebelumnya.

Ringkasan status menggunakan Sedang diproses, Perlu tindakan Anda, Selesai, atau Perlu perhatian. Status bisnis dan riwayat terperinci tetap tersedia.

## Petugas

Cari/pindai nasabah dan pastikan identitasnya. Pada setoran, isi jenis, kondisi, berat aktual, serta bukti. Ringkasan nilai berada pada halaman yang sama; satu tombol akhir mencatat setoran. Simpan draf bersifat opsional. Harga draf yang dilanjutkan memakai waktu efektif pembuatan draf, bukan harga hari berikutnya.

Pickup yang ditugaskan bergerak menuju lokasi lalu dijemput. Kendala dapat dilaporkan dengan alasan oleh petugas berwenang, termasuk setelah berangkat. Timbang dan finalisasi tetap melalui web.

Setoran yang mencapai ambang pemeriksaan (default Rp250.000, dapat dikonfigurasi) tidak langsung menambah saldo. Pickup tetap dijemput dengan keterangan menunggu pemeriksaan. Persetujuan setoran menyelesaikan pickup dan menambah saldo; penolakan mengakhiri pengajuan dengan alasan tanpa saldo masuk.

Sembako yang disetujui cukup ditandai Paket sudah siap. Serah-terima tetap memerlukan kecocokan penerima, bukti, dan pelaksana yang sah. Status sedang_disiapkan lama tetap bisa dilanjutkan.

## Bendahara

Daftar pembayaran hanya mencakup pencairan siap diambil yang ditugaskan dan masih berada pada area tugas efektif. Cocokkan kartu/nomor nasabah dan unggah satu foto bukti. Periksa penerima, nominal, dan foto pada halaman yang sama.

Setelah uang fisik diserahkan, tekan Uang tunai sudah diserahkan - catat pembayaran. Sistem mencatat satu mutasi keluar dan mengonversi hold. Tombol tidak mengirim uang; transfer bank tidak tersedia.

## Admin

Tinjau pencairan serta pilih bendahara melalui satu formulir Setujui dan tetapkan bendahara. Persetujuan dan penugasan tersimpan atomik. Bendahara harus berbeda dari pemberi persetujuan.

Pickup diterima dan dijadwalkan melalui satu formulir dengan petugas dan tanggal. Penugasan tidak valid membatalkan seluruh tindakan. Pengajuan lama yang sudah diterima/disetujui tetap dapat dilanjutkan.

Ketersediaan paket sembako diperiksa manual. Admin menyetujui/menolak; petugas menandai siap dan melaksanakan serah-terima. Tidak ada stok rinci.

Kartu antrean membuka daftar sesuai filter yang ditampilkan. Kelola akun dan tugas menampilkan profil serta area dalam satu formulir. Perubahan peran tetap memerlukan izin khusus dan alasan. Area kosong tidak dianggap berhasil dihapus; akhiri masa tugas atau gunakan penonaktifan pengguna yang sah.

## Superadmin dan keamanan

Superadmin mewarisi kewenangan admin, ditambah pengelolaan akses serta rekonsiliasi yang diizinkan. Ini tidak membolehkan pembayaran atau penyerahan transaksi sendiri.

Admin operasional tidak dapat membuat atau mengambil alih akun superadmin. Perubahan permission mencatat nilai sebelum/sesudah dan mencabut sesi anggota. Halaman Health hanya membaca status, tidak mewarisi tindakan maintenance atau retensi.

Editor menetapkan satu peran operasional. Data multi-role lama tetap didukung tanpa perubahan paksa. Semua portal dan panel memeriksa izin dan sesi, termasuk request Livewire: idle 30 menit dan batas absolut delapan jam.

## Hal yang tidak dipangkas

Kepemilikan data, penugasan/area, identitas penerima, bukti wajib, pemisahan pelaksana, transaksi database, idempotensi, dan audit tetap dijaga server. Pengurangan jumlah layar tidak berarti pengurangan perlindungan saldo.

## Batas hasil

Pengujian lokal menggunakan data sintetis dan database/storage terpisah. Hasil tersebut bukan bukti UAT stakeholder, deployment, atau keberhasilan integrasi Telegram.
