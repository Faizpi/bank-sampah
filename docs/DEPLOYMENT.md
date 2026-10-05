# Deployment dan rollback

Tahap cutover ini hanya implementasi lokal; tidak melakukan deployment. Jangan menjalankan reset demo pada data nyata.

## Persiapan rilis

Validasikan PHP 8.3+, MySQL/InnoDB, root publik `public/`, storage privat, HTTPS, konfigurasi, build aset, backup, logging, dan scheduler. Jalankan seluruh tes pada database disposable dan UAT dengan pengguna nyata. Kredensial demo tidak boleh menjadi akun produksi.

## Cutover

Migration transaksi cutover sengaja menolak data transaksi yang tidak kosong. Reset bersih hanya sesuai izin atas dummy lokal. Untuk data nyata diperlukan rencana migrasi tersendiri, bukan `migrate:fresh`. Command demo membatasi database lokal; reset/seed demo produksi tidak disediakan di konsol web.

Bootstrap awal produksi hanya memakai username dan kata sandi Admin dari konfigurasi `APP_INITIAL_ADMIN_*`; seeder tidak mengganti sandi, menaikkan role, atau mengaktifkan kembali akun yang sudah ada. Wilayah/master selanjutnya dikelola Admin.

## Rollback

Cutover schema bersifat irreversible. Pulihkan kode checkpoint dan backup database/media yang cocok bersama-sama; jangan rollback sebagian migration atau menghapus transaksi final. Backup lokal dan kredensial tidak di-commit.
