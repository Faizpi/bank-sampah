# Perapian tulisan publik dan urutan landing

Permintaan pengguna: hapus dua catatan panjang di bawah ringkasan/sampah dan tulisan tidak perlu yang serupa; pindahkan bagian “Mengenal Kepala Desa Sindangheula” tepat setelah hero. Scope hanya presentasi publik (landing, katalog, harga, footer), regresi, dan kontrak desain. Tidak mengubah data, query, workflow, izin, foto, atau perhitungan.

## AI SLOP CLEANUP REPORT

- **Scope:** empat template publik, tes `PublicLandingPageTest`, dan dokumentasi.
- **Behavior Lock:** sebelum edit 40 tes publik lulus; tes baru untuk ketidakhadiran catatan mendeteksi tampilan lama sebelum dibersihkan. Regresi angka/privasi/foto/harga dipertahankan.
- **Cleanup Plan:** hapus dua catatan yang disebut pengguna; hapus duplikasi penjelasan di katalog/harga/footer; sederhanakan judul; pindahkan blok Kepala Desa tanpa mengubah isi; periksa ulang UI/tes.
- **Fallback Findings:** status foto kosong/gagal dan harga belum tersedia adalah grounded compatibility/fail-safe, bukan masking slop; tidak dihapus atau diganti nilai/gambar palsu. Tidak ada fallback backend yang dimodifikasi atau perlu eskalasi arsitektur.
- **UI/Design Findings:** catatan implementasi di halaman Warga/publik dan tumpukan eyebrow/deskripsi berulang dipangkas. Identitas Green Ledger, foto/isi Kepala Desa, maskot, tautan, dan petunjuk pemilahan dipertahankan.
- **Passes Completed:** gate klasifikasi, penghapusan catatan tidak perlu, pengurangan duplikasi, judul langsung, serta penguatan regresi urutan/copy. Tidak membuat abstraksi/dependency baru.
- **Quality Gates:** regresi publik, Pint, build Vite, whitespace, dan pemeriksaan browser. Bukan klaim UAT/perangkat desa.
- **Changed Files:** `welcome.blade.php` (dua catatan + header + urutan), `waste-catalog.blade.php` (header ringkas), `waste-prices.blade.php` (judul/deskripsi singkat, catatan ganda hilang), `footer.blade.php` (slogan generik hilang), `PublicLandingPageTest.php` (dua regresi), `DESIGN.md`/changelog (kontrak terbaru).
- **Remaining Risks:** foto asli tetap perlu diunggah Admin seperti baseline sebelumnya; tidak terkait cleanup copy. Tidak ada perubahan penyamaran angka atau pengungkapan bukti privat.

Urutan terbaru: hero → Kepala Desa → ringkasan → sampah yang diterima → layanan → cara kerja → maskot → akses akun → footer. Anchor/id section, isi Kepala Desa, tombol akses, empat kartu, harga/kondisi, dan fallback foto tetap sama. Definisi teknis tetap tersedia di `LANDING_CATALOG_2026-10-09.md`.

Hasil akhir: 42 tes publik lulus (723 assertions), Pint/build Vite/whitespace lulus. Browser mengonfirmasi urutan delapan section, kedua catatan tidak tampil, angka utama tetap 20/3/0/0 kg, dan tidak ada overflow halaman. Tidak ada mutasi database utama. Screenshot lokal: `artifacts/public-copy-2026-10-09/summary-clean.jpg` (diabaikan Git).
