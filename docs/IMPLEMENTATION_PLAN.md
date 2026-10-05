# Rencana Implementasi Aktif

Dokumen ini merangkum pekerjaan dokumentasi dan verifikasi untuk ruang lingkup aplikasi saat ini. Status di sini bukan klaim rilis atau kesiapan produksi.

## 1. Sumber kontrak

Gunakan sumber berikut:

- [PRODUCT.md](PRODUCT.md), [REQUIREMENTS.md](REQUIREMENTS.md), dan [ROADMAP.md](ROADMAP.md) untuk ruang lingkup aktif
- [USER_FLOWS.md](USER_FLOWS.md), [BUSINESS_RULES.md](BUSINESS_RULES.md), [PERMISSIONS.md](PERMISSIONS.md), dan [VALIDATION.md](VALIDATION.md) untuk alur, invariant, akses, dan validasi
- [DATA_MODEL.md](DATA_MODEL.md), [ARCHITECTURE.md](ARCHITECTURE.md), [SECURITY.md](SECURITY.md), dan [DECISIONS.md](DECISIONS.md) untuk kontrak teknis
- [DESIGN.md](DESIGN.md) dan [TEST_PLAN.md](TEST_PLAN.md) untuk antarmuka dan pembuktian
- [DEPLOYMENT.md](DEPLOYMENT.md), [OPERATIONS.md](OPERATIONS.md), dan [TERMS_AND_PRIVACY.md](TERMS_AND_PRIVACY.md) untuk rilis, operasi, dan kontrak publik

Jika sumber bertentangan, hentikan pekerjaan terdampak dan selaraskan dokumen sebelum implementasi.

## 2. Environment

- PHP minimum adalah 8.3 sesuai `composer.json`; PHP web dan CLI harus selaras
- Pengembangan lokal memakai Laragon dan MySQL 8.0.30
- Suite harian dapat memakai SQLite `:memory:`; hasilnya tidak membuktikan perilaku engine MySQL production
- Queue aktif menggunakan `sync`; tidak ada klaim worker asinkron atau retry otomatis

## 3. Ruang lingkup kerja

Pekerjaan aktif mempertahankan fungsi inti akun, pengguna, wilayah, master sampah dan harga, identitas nasabah, setoran, ledger, penjemputan, pencairan, penukaran sembako, pengumuman, WhatsApp manual, jadwal layanan keliling, estimasi, QR bukti, laporan yang tersedia, audit, media privat, dan PWA terbatas.

Aturan berikut tetap normatif dan tak boleh dilonggarkan untuk menyesuaikan defect:

1. Target yang masih menjadi sasaran produk dihitung dari transaksi final bersih dan tidak menerima nilai progres bebas.
2. Jadwal layanan keliling mengikuti hierarki desa, dusun, RW, dan RT, menolak benturan, serta menerapkan kapasitas pada jadwal terkait.
3. Penjemputan memerlukan foto, area aktif, slot kapasitas, alternatif tanggal saat penuh, berat aktual, dan transaksi final sebelum status selesai.

## 4. Administrasi teknis

Health adalah satu-satunya administrasi teknis aktif pada UI dan bersifat baca-saja.

Secret tetap berada pada environment. Perubahan infrastruktur dilakukan melalui proses deployment di luar UI aplikasi.

## 5. Kapabilitas yang tak dijanjikan

Dokumentasi dan acceptance criteria tidak boleh menjanjikan:

- bantuan sembako gratis
- push notification atau notification center sebagai kanal aktif
- reset kata sandi publik berbasis token atau kanal pengiriman
- queue database, pemrosesan asinkron, worker, atau retry otomatis
- klaim production-ready, UAT lulus, atau deployment tervalidasi tanpa bukti baru pada kandidat rilis

## 6. Gate verifikasi

| Gate | Syarat |
|---|---|
| Perilaku | Test positif dan negatif untuk aturan yang berubah lulus |
| Otorisasi | Permission, policy, dan record scope diuji |
| Finansial | Transaction, lock, idempotensi, ledger, dan jalur gagal diperiksa |
| UI | Build, responsive, accessibility, dan state yang berubah diperiksa |
| Engine | Semantik MySQL yang sensitif diuji pada environment disposable sebelum UAT atau production |
| Rilis | PHP `^8.3`, document root, storage privat, cron atau timezone, Health, rollback, smoke test, monitoring, UAT, dan approval diverifikasi |

## 7. Struktur 36 alur

[USER_FLOWS.md](USER_FLOWS.md) mempertahankan FL-01 sampai FL-36. Sebagian flow berfungsi sebagai struktur traceability dan tidak otomatis menambah kapabilitas aktif. FL-15 menjelaskan informasi pada halaman terkait dan WhatsApp manual. FL-36 memvisualisasikan Health baca-saja yang memerlukan `system.maintenance`.

## 8. Status

Status aktif: `needs_verification`.

Catatan historis berada di [CHANGELOG.md](CHANGELOG.md). Template penerimaan berada di [UAT_EVIDENCE.md](UAT_EVIDENCE.md). Tidak ada snapshot historis yang menjadi bukti status aktif atau klaim bahwa defect aplikasi telah diperbaiki.
