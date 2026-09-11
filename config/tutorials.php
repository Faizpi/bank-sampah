<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Video Tutorial Bank Sampah Digital
    |--------------------------------------------------------------------------
    |
    | Anda dapat memperbarui tautan 'video_url' dengan tautan file atau folder
    | Google Drive asli Anda kapan saja tanpa perlu mengubah kode template blade.
    |
    */

    'roles' => [
        'warga' => [
            'label' => 'Warga',
            'badge' => 'Warga',
            'description' => 'Panduan lengkap bagi nasabah warga untuk mengelola tabungan sampah dan layanan digital.',
            'color' => 'forest',
        ],
        'petugas' => [
            'label' => 'Petugas',
            'badge' => 'Petugas Lapangan',
            'description' => 'Panduan operasional bagi petugas untuk melayani penjemputan dan armada keliling.',
            'color' => 'blue',
        ],
        'bendahara' => [
            'label' => 'Bendahara',
            'badge' => 'Bendahara Kas',
            'description' => 'Panduan keuangan untuk verifikasi pembayaran dan rekapitulasi saldo tabungan warga.',
            'color' => 'amber',
        ],
        'superadmin' => [
            'label' => 'Superadmin & Admin',
            'badge' => 'Superadmin / Admin',
            'description' => 'Panduan pengelolaan sistem, data master sampah, harga, penugasan, dan manajemen staf.',
            'color' => 'purple',
        ],
    ],

    'items' => [
        // ==========================================
        // SESI WARGA
        // ==========================================
        [
            'id' => 'warga-login',
            'role' => 'warga',
            'number' => '1',
            'title' => 'Tutorial Masuk Aplikasi',
            'summary' => 'Cara masuk ke akun warga Bank Sampah Digital menggunakan username dan kata sandi.',
            'steps' => [
                'Buka halaman login melalui tombol "Masuk" di pojok kanan atas beranda.',
                'Masukkan username dan kata sandi yang telah terdaftar dan diverifikasi.',
                'Klik tombol "Masuk Sekarang" untuk membuka Dashboard Warga.',
            ],
            'video_url' => env('TUTORIAL_WARGA_LOGIN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-masuk-aplikasi'),
        ],
        [
            'id' => 'warga-register',
            'role' => 'warga',
            'number' => '2',
            'title' => 'Tutorial Daftar Pengguna Baru',
            'summary' => 'Panduan pendaftaran akun warga baru bagi masyarakat secara mandiri.',
            'steps' => [
                'Klik tombol "Daftar" pada halaman beranda atau menu navigasi.',
                'Isi data identitas: nama lengkap, nomor WhatsApp aktif, alamat RT/RW, dan buat kata sandi yang kuat.',
                'Setujui ketentuan operasional dan privasi, lalu kirim formulir pendaftaran.',
                'Tunggu verifikasi identitas dari Admin sebelum akun aktif untuk login.',
            ],
            'video_url' => env('TUTORIAL_WARGA_REGISTER_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-daftar-warga'),
        ],
        [
            'id' => 'warga-penjemputan',
            'role' => 'warga',
            'number' => '3',
            'title' => 'Tutorial Mengajukan Penjemputan Sampah',
            'summary' => 'Cara meminta petugas datang ke rumah untuk menjemput sampah terpilah yang siap ditimbang.',
            'steps' => [
                'Masuk ke Dashboard Warga dan pilih menu "Ajukan Penjemputan".',
                'Pilih perkiraan jenis sampah yang telah dipilah (plastik, kertas, logam, dll.) dan taksiran berat.',
                'Pastikan alamat RT/RW Anda sesuai dan tambahkan catatan patokan lokasi rumah jika diperlukan.',
                'Kirim permohonan dan pantau status penjemputan hingga petugas ditugaskan.',
            ],
            'video_url' => env('TUTORIAL_WARGA_PENJEMPUTAN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-ajukan-penjemputan'),
        ],
        [
            'id' => 'warga-pencairan',
            'role' => 'warga',
            'number' => '4',
            'title' => 'Tutorial Mengajukan Pencairan Saldo Rupiah',
            'summary' => 'Panduan menarik saldo hasil tabungan sampah menjadi uang tunai atau transfer rekening.',
            'steps' => [
                'Buka menu "Pencairan Saldo" pada Dashboard Warga.',
                'Periksa saldo tabungan yang dapat dicairkan.',
                'Tentukan nominal penarikan saldo dan pilih metode pencairan (Tunai di Balai / Transfer Bank).',
                'Kirim pengajuan dan tunggu persetujuan serta proses pembayaran oleh Bendahara.',
            ],
            'video_url' => env('TUTORIAL_WARGA_PENCAIRAN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-cairkan-saldo'),
        ],
        [
            'id' => 'warga-sembako',
            'role' => 'warga',
            'number' => '5',
            'title' => 'Tutorial Mengajukan Penukaran Sembako',
            'summary' => 'Langkah menukarkan saldo tabungan sampah dengan paket sembako berkualitas dari program desa.',
            'steps' => [
                'Pilih menu "Tukar Sembako" dari halaman dashboard nasabah.',
                'Lihat katalog paket sembako yang tersedia (beras, minyak goreng, gula, dll.) beserta nilai tukar rupiahnya.',
                'Pilih paket yang diinginkan sesuai kecukupan saldo tabungan Anda.',
                'Kirim pesanan penukaran dan tunggu jadwal pengambilan atau pengantaran oleh petugas.',
            ],
            'video_url' => env('TUTORIAL_WARGA_SEMBAKO_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-tukar-sembako'),
        ],
        [
            'id' => 'warga-qr-card',
            'role' => 'warga',
            'number' => '6',
            'title' => 'Tutorial Melihat Kartu Nasabah Digital & Kode QR',
            'summary' => 'Cara menampilkan Kartu Nasabah Digital dan Kode QR untuk scan cepat saat setor sampah.',
            'steps' => [
                'Buka menu "Kartu Nasabah" di Dashboard Warga.',
                'Periksa Nomor Nasabah resmi Anda (pola CST-########) dan nama terdaftar.',
                'Tunjukkan Kode QR di layar ponsel kepada Petugas saat armada keliling atau penjemputan tiba.',
            ],
            'video_url' => env('TUTORIAL_WARGA_QR_CARD_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-kartu-qr'),
        ],
        [
            'id' => 'warga-profile-password',
            'role' => 'warga',
            'number' => '7',
            'title' => 'Tutorial Mengubah Kata Sandi & Profil',
            'summary' => 'Panduan memperbarui data kontak dan mengganti kata sandi demi keamanan akun tabungan.',
            'steps' => [
                'Buka menu "Profil & Pengaturan" di navigasi akun warga.',
                'Pilih tab "Ubah Kata Sandi" untuk memperbarui kata sandi lama ke yang baru.',
                'Simpan perubahan dan pastikan mengingat kredensial baru Anda.',
            ],
            'video_url' => env('TUTORIAL_WARGA_PROFILE_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-ubah-sandi'),
        ],

        // ==========================================
        // SESI PETUGAS
        // ==========================================
        [
            'id' => 'petugas-login',
            'role' => 'petugas',
            'number' => '1',
            'title' => 'Tutorial Masuk ke Portal Petugas',
            'summary' => 'Cara masuk ke akun khusus petugas lapangan dan armada bank sampah.',
            'steps' => [
                'Akses halaman login dan masukkan kredensial petugas yang telah diberikan oleh Superadmin.',
                'Sistem otomatis mendeteksi peran Anda dan mengarahkan langsung ke Dashboard Petugas.',
                'Periksa ringkasan tugas hari ini, jadwal armada keliling, dan status penjemputan.',
            ],
            'video_url' => env('TUTORIAL_PETUGAS_LOGIN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-petugas-login'),
        ],
        [
            'id' => 'petugas-jemput-langsung',
            'role' => 'petugas',
            'number' => '2',
            'title' => 'Tutorial Penjemputan Sampah Langsung',
            'summary' => 'Panduan melayani warga yang langsung menyerahkan sampah di tempat atau pos pemilahan.',
            'steps' => [
                'Buka menu "Identifikasi Warga" atau pindai Kode QR dari kartu nasabah warga.',
                'Timbang jenis sampah yang diserahkan dan masukkan berat riil sesuai timbangan.',
                'Pilih kondisi sampah (bersih, kotor, campur) agar sistem menghitung nilai rupiah otomatis.',
                'Konfirmasi pencatatan setoran agar saldo tabungan warga langsung bertambah secara instan.',
            ],
            'video_url' => env('TUTORIAL_PETUGAS_JEMPUT_LANGSUNG_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-petugas-jemput-langsung'),
        ],
        [
            'id' => 'petugas-jemput-tugas',
            'role' => 'petugas',
            'number' => '3',
            'title' => 'Tutorial Menjalankan Penjemputan Sampah yang Ditugaskan',
            'summary' => 'Cara memproses permintaan jemput sampah dari warga yang telah didisposisikan oleh Admin.',
            'steps' => [
                'Buka daftar "Tugas Penjemputan" di Dashboard Petugas.',
                'Lihat rincian lokasi rumah warga, nomor kontak, dan estimasi berat sampah.',
                'Kunjungi lokasi warga, lakukan penimbangan akurat di tempat, dan input hasil setoran.',
                'Tandai status tugas penjemputan sebagai "Selesai".',
            ],
            'video_url' => env('TUTORIAL_PETUGAS_JEMPUT_TUGAS_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-petugas-tugas-jemput'),
        ],
        [
            'id' => 'petugas-keliling',
            'role' => 'petugas',
            'number' => '4',
            'title' => 'Tutorial Melayani Bank Sampah Keliling',
            'summary' => 'Panduan mengoperasikan armada keliling pada titik jadwal pelayanan RT/RW di desa.',
            'steps' => [
                'Buka modul "Bank Sampah Keliling" pada portal petugas.',
                'Aktifkan sesi operasional titik keliling hari ini.',
                'Pindai QR nasabah warga yang datang ke pos keliling dan catat timbangan sampah mereka.',
                'Tutup sesi operasional keliling setelah jam pelayanan berakhir untuk merekap total timbulan sampah.',
            ],
            'video_url' => env('TUTORIAL_PETUGAS_KELILING_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-petugas-keliling'),
        ],
        [
            'id' => 'petugas-sembako',
            'role' => 'petugas',
            'number' => '5',
            'title' => 'Tutorial Menyelesaikan Penyerahan Sembako',
            'summary' => 'Cara memverifikasi dan menyerahkan paket sembako kepada warga yang telah menukarkan saldonya.',
            'steps' => [
                'Buka menu "Tugas Sembako" di dashboard petugas.',
                'Periksa nomor permohonan penukaran dan paket yang harus diserahkan.',
                'Serahkan barang sembako kepada nasabah yang bersangkutan.',
                'Konfirmasi penyaluran sembako di sistem sebagai bukti serah terima.',
            ],
            'video_url' => env('TUTORIAL_PETUGAS_SEMBAKO_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-petugas-serah-sembako'),
        ],

        // ==========================================
        // SESI BENDAHARA
        // ==========================================
        [
            'id' => 'bendahara-login',
            'role' => 'bendahara',
            'number' => '1',
            'title' => 'Tutorial Masuk ke Portal Bendahara',
            'summary' => 'Cara masuk ke akun bendahara untuk mengelola kas dan pembayaran penarikan saldo.',
            'steps' => [
                'Login dengan akun Bendahara melalui halaman login utama.',
                'Sistem secara otomatis mengarahkan ke Dashboard Bendahara.',
                'Pantau antrean pencairan dana yang siap dibayarkan dan total kas yang dialokasikan.',
            ],
            'video_url' => env('TUTORIAL_BENDAHARA_LOGIN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-bendahara-login'),
        ],
        [
            'id' => 'bendahara-cairkan',
            'role' => 'bendahara',
            'number' => '2',
            'title' => 'Tutorial Memproses Pembayaran / Pencairan Saldo Warga',
            'summary' => 'Langkah verifikasi dan eksekusi pembayaran saldo uang tunai atau transfer kepada warga.',
            'steps' => [
                'Buka menu "Pembayaran Pencairan" pada dashboard bendahara.',
                'Pilih pengajuan warga yang berstatus siap dibayarkan.',
                'Lakukan serah terima uang tunai atau transfer ke rekening warga sesuai nominal yang diminta.',
                'Klik tombol "Konfirmasi Pembayaran Selesai" dan sistem otomatis mencatat pembukuan buku kas keluar.',
            ],
            'video_url' => env('TUTORIAL_BENDAHARA_CAIRKAN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-bendahara-bayar-pencairan'),
        ],
        [
            'id' => 'bendahara-laporan',
            'role' => 'bendahara',
            'number' => '3',
            'title' => 'Tutorial Melihat Riwayat & Rekap Keuangan',
            'summary' => 'Panduan memeriksa mutasi kas, laporan bulanan, dan audit rekonsiliasi keuangan.',
            'steps' => [
                'Buka menu "Laporan Keuangan" di portal bendahara.',
                'Pilih rentang tanggal atau periode pembukuan yang diinginkan.',
                'Periksa perputaran dana masuk dari penjualan sampah dan dana keluar dari pencairan warga.',
                'Ekspor rekapitulasi data keuangan jika diperlukan untuk pertanggungjawaban desa.',
            ],
            'video_url' => env('TUTORIAL_BENDAHARA_LAPORAN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-bendahara-rekap-kas'),
        ],

        // ==========================================
        // SESI SUPERADMIN & ADMIN
        // ==========================================
        [
            'id' => 'superadmin-login',
            'role' => 'superadmin',
            'number' => '1',
            'title' => 'Tutorial Masuk ke Panel Admin',
            'summary' => 'Panduan login ke panel admin bagi Admin dan Superadmin.',
            'steps' => [
                'Buka halaman Masuk, lalu klik tautan "Akses Panel Admin" di bagian bawah formulir (atau bisa juga akses langsung URL `/backoffice/login`).',
                'Masukkan username dan password akun Superadmin atau Admin.',
                'Akses panel administrasi lengkap dengan widget statistik dan navigasi master data.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_LOGIN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-panel-admin-login'),
        ],
        [
            'id' => 'superadmin-verifikasi-warga',
            'role' => 'superadmin',
            'number' => '2',
            'title' => 'Tutorial Memverifikasi Warga Baru',
            'summary' => 'Cara menyetujui atau menolak permohonan pendaftaran warga baru yang masuk.',
            'steps' => [
                'Buka menu "Warga Menunggu Verifikasi" pada panel admin.',
                'Periksa kesesuaian identitas warga dan wilayah RT/RW.',
                'Klik aksi "Verifikasi" untuk mengaktifkan akun (nomor nasabah CST dan kode QR langsung diterbitkan otomatis).',
                'Jika data tidak sesuai, masukkan alasan penolakan dan klik "Tolak".',
            ],
            'video_url' => env('TUTORIAL_ADMIN_VERIFIKASI_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-verifikasi-warga'),
        ],
        [
            'id' => 'superadmin-tambah-staf',
            'role' => 'superadmin',
            'number' => '3',
            'title' => 'Tutorial Menambah Petugas atau Bendahara Baru',
            'summary' => 'Panduan membuat akun staf pengelola dan memberikan hak akses peran secara tepat.',
            'steps' => [
                'Buka menu "Manajemen Staf / Pengguna" di panel admin.',
                'Klik tombol "Tambah Pengguna Baru".',
                'Isi nama, username, nomor kontak, dan tetapkan kata sandi awal.',
                'Pilih peran yang sesuai: centang "Petugas" atau "Bendahara".',
                'Simpan data dan serahkan kredensial masuk kepada staf terkait.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_TAMBAH_STAF_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-tambah-petugas-bendahara'),
        ],
        [
            'id' => 'superadmin-master-sampah',
            'role' => 'superadmin',
            'number' => '4',
            'title' => 'Tutorial Menambah Jenis Sampah (Master Sampah)',
            'summary' => 'Cara mengelola jenis komoditas sampah, kategori, dan unit timbangan.',
            'steps' => [
                'Buka menu "Master Sampah" > "Jenis Sampah".',
                'Klik "Buat Jenis Sampah Baru".',
                'Tentukan kategori (Plastik, Kertas, Logam, Kaca, Minyak Jelantah, dll.).',
                'Tuliskan nama jenis sampah (misal: Kardus Bersih, Botol PET Bening) dan pilih satuan (Kilogram / Liter / Pcs).',
                'Simpan jenis sampah untuk segera dapat digunakan dalam pencatatan setoran.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_MASTER_SAMPAH_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-master-sampah'),
        ],
        [
            'id' => 'superadmin-harga-sampah',
            'role' => 'superadmin',
            'number' => '5',
            'title' => 'Tutorial Mengubah & Menetapkan Harga Sampah',
            'summary' => 'Panduan memperbarui tarif harga beli sampah aktif sesuai fluktuasi pasar atau pengepul.',
            'steps' => [
                'Buka menu "Harga Sampah" pada panel admin.',
                'Pilih jenis sampah dan kondisi yang ingin disesuaikan harganya.',
                'Masukkan nominal harga baru per satuan (Rp/kg).',
                'Tetapkan tanggal mulai berlaku harga dan simpan.',
                'Halaman informasi harga publik otomatis diperbarui seketika.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_HARGA_SAMPAH_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-ubah-harga'),
        ],
        [
            'id' => 'superadmin-tugaskan-penjemputan',
            'role' => 'superadmin',
            'number' => '6',
            'title' => 'Tutorial Menugaskan Penjemputan ke Petugas',
            'summary' => 'Cara mengalokasikan permintaan jemput sampah warga kepada petugas lapangan yang bertugas.',
            'steps' => [
                'Buka menu "Penjemputan Sampah" di panel admin.',
                'Pilih permohonan penjemputan warga yang masih berstatus "Menunggu Petugas".',
                'Pilih nama petugas lapangan yang bertanggung jawab di wilayah RT/RW tersebut.',
                'Klik "Tugaskan", notifikasi akan langsung terkirim ke portal petugas terkait.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_TUGASKAN_JEMPUT_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-tugaskan-penjemputan'),
        ],
        [
            'id' => 'superadmin-tugaskan-pencairan',
            'role' => 'superadmin',
            'number' => '7',
            'title' => 'Tutorial Menugaskan Pencairan Saldo kepada Bendahara',
            'summary' => 'Langkah persetujuan awal penarikan saldo nasabah sebelum dieksekusi oleh Bendahara.',
            'steps' => [
                'Buka menu "Pencairan Saldo" di panel admin.',
                'Tinjau permohonan pencairan warga dan verifikasi ketersediaan saldo di buku besar.',
                'Klik opsi "Setujui & Teruskan ke Bendahara".',
                'Status berubah menjadi siap dibayar dan otomatis muncul pada antrean portal bendahara.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_TUGASKAN_CAIR_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-tugaskan-pencairan'),
        ],
        [
            'id' => 'superadmin-tambah-sembako',
            'role' => 'superadmin',
            'number' => '8',
            'title' => 'Tutorial Menambah & Mengelola Katalog Sembako',
            'summary' => 'Cara memasukkan barang sembako baru, mengatur harga poin saldo, dan stok produk.',
            'steps' => [
                'Buka menu "Katalog Sembako" di panel admin.',
                'Klik "Tambah Produk Sembako".',
                'Isi nama barang (misal: Beras Premium 5 Kg, Minyak Goreng 1L), nilai tukar rupiah, dan jumlah stok awal.',
                'Unggah foto barang agar menarik bagi warga di aplikasi.',
                'Simpan produk, barang langsung tampil pada pilihan tukar sembako warga.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_TAMBAH_SEMBAKO_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-tambah-sembako'),
        ],
        [
            'id' => 'superadmin-pengumuman-jadwal',
            'role' => 'superadmin',
            'number' => '9',
            'title' => 'Tutorial Membuat Pengumuman & Jadwal Keliling',
            'summary' => 'Panduan mempublikasikan agenda desa dan jadwal armada sampah keliling ke halaman publik.',
            'steps' => [
                'Buka menu "Pengumuman" atau "Jadwal Keliling".',
                'Buat posting pengumuman baru berisi judul, tanggal kegiatan, dan isi informasi untuk warga.',
                'Untuk jadwal keliling: tentukan tanggal, titik kumpul RW, jam mulai, dan petugas yang bertugas.',
                'Publikasikan jadwal agar langsung terbaca di beranda dan menu informasi publik.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_PENGUMUMAN_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-pengumuman-jadwal'),
        ],
        [
            'id' => 'superadmin-backup-audit',
            'role' => 'superadmin',
            'number' => '10',
            'title' => 'Tutorial Manajemen Backup & Log Audit',
            'summary' => 'Cara mengunduh cadangan database dan memeriksa catatan rekam jejak aktivitas sistem.',
            'steps' => [
                'Buka menu "Sistem & Keamanan" di panel admin.',
                'Pilih "Backup Data" untuk membuat dan mengunduh cadangan arsip database secara aman.',
                'Buka menu "Audit Log" untuk meninjau riwayat login, transaksi, dan perubahan data penting.',
            ],
            'video_url' => env('TUTORIAL_ADMIN_BACKUP_URL', 'https://drive.google.com/drive/folders/placeholder-tutorial-admin-backup-audit'),
        ],
    ],
];
