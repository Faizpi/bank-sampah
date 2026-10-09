# Seeder utama dan data privat

`database/seeders/DatabaseSeeder.php` adalah satu-satunya seeder aplikasi. Tidak ada lagi `DeveloperUsersSeeder`, `DemoMasterSeeder`, atau `InitialAdminSeeder` terpisah. Fixture pengujian berada di `tests/Fixtures/WorkflowFixture.php`; runner tes memilihnya secara eksplisit di database disposable, bukan melalui seeder utama. [Bukti validasi dan penggantian dummy lokal](VALIDATION_MASTER_DATA_2026-10-09.md).

## Menyiapkan sumber

Sumber yang dipilih pemilik adalah `smssinde_banksampah.sql` (dump 5 Oktober 2026). Konverter hanya membaca literal INSERT; tidak menjalankan SQL, DDL, trigger, atau instruksi lain dari file. Output disaring ke pengguna, profil/peran, wilayah/penugasan, master sampah, dan harga.

```powershell
node documents/extract-master-data.mjs "C:\Users\Faiz\Downloads\smssinde_banksampah.sql" "database/data/master-data.json"
```

Konverter menolak mengganti file output yang sudah ada. Untuk sumber baru, gunakan nama output baru dan atur `MASTER_SEED_PATH` di lingkungan privat. Default: `database/data/master-data.json`. Simpan SQL/JSON di luar Git; keduanya mengandung data akun, alamat, dan hash kata sandi. Jangan menaruh file ini sebagai unduhan publik.

Hanya dataset berformat versi 1 dengan tabel/kolom yang diizinkan diterima. Referensi, keempat peran, kecocokan profil, hash kata sandi, duplikasi hubungan, dan pasangan/periode harga divalidasi sebelum INSERT. Tidak ada fallback akun buatan jika sumber hilang.

## Pemetaan sumber saat ini

| Data | Hasil |
|---|---|
| Pengguna | 26: 20 Warga, 3 Petugas, 1 Bendahara, 2 Admin |
| Superadmin lama | Role menjadi `admin`; username lama dipertahankan agar login tidak berubah |
| Wilayah | 1 kelompok Desa Sindangheula, 5 RW, 19 RT; bukan asumsi nama dusun baru |
| Area dan tugas | 1 area seluruh desa, 19 RT tercakup, 4 penugasan Petugas/Bendahara |
| Profil Warga | 20; satu profil lama pada akun Petugas tidak diimpor |
| Sampah dan harga | 2 kategori, 1 satuan kg, 2 kondisi, 4 jenis dan 4 harga |
| Harga per kg | Plastik Campuran Rp500; Aqua Gelas, Aqua Botol, Kardus masing-masing Rp1.500 |

Nama, username, kontak, alamat, nomor nasabah, status, tanggal, dan hash kata sandi berasal dari sumber, bukan nama/fakta desa yang dikarang. Riwayat persetujuan tidak dibuat-buat: nilai versi/waktu persetujuan pada pengguna tetap mengikuti sumber. `verified_by`, token login/QR, dan ciphertext lama tidak dibawa; QR baru dienkripsi dengan `APP_KEY` lokal. Kartu/QR lama dari sistem sumber tidak berlaku pada baseline baru.

Transaksi/setoran, saldo/mutasi/hold, notifikasi, sesi, audit historis, lampiran, layanan bantuan, role/permission dinamis, dan paket sembako lama tidak diimpor. Saldo awal nol. Admin dapat membuat paket sembako baru melalui web; seeder tidak menambah paket yang tidak diminta.

## Menjalankan dan mengulang

Pada database lokal kosong yang sudah dimigrasikan:

```powershell
php artisan db:seed
```

Seeder menolak database yang sudah berisi data; ia tidak melakukan upsert pengguna, mengganti password, merotasi QR yang sudah digunakan, atau menghapus transaksi. INSERT dan pembuatan QR berada dalam satu transaksi.

Untuk mengganti baseline **dummy** lokal `bank_sampah`, backup terlebih dahulu dan uji pemulihannya di database terpisah. Command reset berkonfirmasi memvalidasi sumber, memegang snapshot sumber di memori, dan membatasi target ke local/testing + MySQL loopback + tepat nama `bank_sampah` + media workspace:

```powershell
php artisan app:reset-demo-baseline --confirm-reset-demo --dry-run
php artisan app:reset-demo-baseline --confirm-reset-demo
```

Tanpa dry-run schema/data dummy dan folder media dummy diganti. Jangan gunakan untuk produksi/data nyata. Untuk produksi `DatabaseSeeder` hanya melakukan bootstrap Admin awal dari konfigurasi privat dan tidak menaikkan hak/menonaktifkan ulang akun yang sudah ada.

## Alamat: bukti dan batas

Alamat pada sumber diperiksa terhadap RT/RW terpilih: tidak ditemukan pertentangan pada 17 alamat Warga yang mencantumkan nomor RT/RW. Tiga alamat lainnya tidak memiliki kedua nomor itu dalam teks; wilayah terstruktur tetap ada. Seluruh 19 RT tercakup area layanan. Ini pemeriksaan konsistensi data, bukan verifikasi kunjungan rumah.

Web meminta satu pilihan RT saat pendaftaran (label memuat wilayah/RW/RT), menyimpan alamat rincian, dan mengisi alamat penjemputan dari profil. Warga dapat memperbarui rincian alamat sendiri; perubahan RT ditangani Admin karena mengubah cakupan pelayanan. Permintaan penjemputan tetap memakai RT profil, bukan geolokasi dari teks alamat.

Saran kenyamanan berikutnya: petunjuk singkat nomor rumah/patokan opsional dan penjelasan bahwa alamat penjemputan alternatif harus tetap di RT domisili. Tidak perlu menjadikan pin peta, kode pos, atau pengisian RT/RW berulang sebagai syarat. Belum ada perubahan schema/alur pindah RT mandiri atau peta pada tahap ini.
