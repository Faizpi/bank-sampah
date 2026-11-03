# Kaitan Skripsi

Skripsi penulis memakai aplikasi ini sebagai objek penelitian. Dokumen ini memetaan isi skripsi ke repo agar pengembangan dan penilaian tetap sinkron.

## Identitas

- Judul: **Pengembangan Sistem Informasi Bank Sampah dengan Integrasi Bot Telegram: Studi Kasus Desa Sindangheula**
- Penulis: Muhammad Faiz Bintang Pratama (3337230040), Informatika FT Untirta, 2026
- Pembimbing: Nanang Krisdianto, S.T., M.Kom. (D1) dan Fitri Damyati, S.Kom., M.M. (D2)

## Lokasi dokumen skripsi

Folder utama: `C:\Skripsi` (di luar repo, privat).

- `drafskripsifaiz.docx` — draft gabungan skripsi
- `revisi_2026-10-15_perbandingan/drafskripsifaiz_Revisi_Perbandingan_Web_Telegram.docx` — revisi terakhir; bab 1–3 terfiksasi, menonjolkan perbandingan antarmuka Web vs Bot Telegram
- `Template Berkas Skripsi/` dan `Pedoman Penulisan Tugas Akhir Informatika 2025 (1).pdf` — format wajib
- `Referensi - fix/` — pustaka dan sitasi Mendeley
- Folder `revisi_2026-10-*` dan `audit_2026-10-*` — riwayat perbaikan per tanggal

## Pemetaan skripsi ke repo

| Bab skripsi | Isi | Kaitan di repo |
| --- | --- | --- |
| 1–2 | Latar belakang, tinjauan pustaka | Landing publik dan konteks desa: `app/Livewire/PublicSite/*` |
| 3.3.2–3.3.3 | Peran, kewenangan, arsitektur integrasi | `app/Authorization/*`, `docs/PERMISSIONS.md`, `docs/ARCHITECTURE.md` |
| 3.3.4 | Verifikasi akun dan sesi | `app/Support/Auth/*`, `app/Actions/Auth/*`, `docs/SECURITY.md` |
| 3.3.5 | Alur layanan dan konsistensi transaksi | `app/Domain/*`, `app/Livewire/{Citizen,Officer,Treasurer}/*`, `docs/BUSINESS_RULES.md` |
| 3.3.6 | Disaster Recovery Plan | `docs/DEPLOYMENT.md`, `docs/OPERATIONS.md` |
| 3.4 | Desain dan integrasi Bot Telegram | `app/Domain/Telegram/*`, `app/Http/Controllers/TelegramWebhookController.php`, `docs/TELEGRAM_CHANNEL.md`, `tests/Feature/Telegram/*` |
| 3.5.1 | Pengujian fungsional dan integrasi | `docs/TEST_PLAN.md`, `tests/`, `npm run test:browser` |
| 3.5.2–3.5.6 | UAT, prosedur perbandingan, SUS, analisis data | `docs/UAT_EVIDENCE.md`; instrumen SUS ada di lampiran skripsi |
| Lampiran | Kuitansi/audit, kartu warga | `app/Livewire/Citizen/CustomerCard.php`, bukti kuitansi bawaan transaksi |

## Status dan pengembangan selanjutnya

Urutan kerja mengikuti `ROADMAP.md` dan bab 3 skripsi:

1. **Automated verification lokal** atas web dan alur Telegram tiga role. Ini bukti teknis, bukan UAT atau ketersediaan bot pada hosting.
2. **Selaraskan Bab III dan prototipe penelitian** dengan penautan 1:1, consent notifikasi, finalisasi setoran/serah fisik via bot, serta cakupan tambahan yang kini ada pada kode.
3. **UAT dan pengukuran SUS/perbandingan Web vs Bot** bersama peserta desa yang sama setelah prototype disetujui; hasilnya menjadi sumber Bab IV, tidak dapat digantikan tes otomatis.
4. **Aktivasi dan deployment** hanya setelah pemberitahuan privasi disetujui, token/webhook/scheduler siap, dan penerapan disetujui.
5. **Linimasa dan bukti riwayat** mengikuti [RESEARCH_TIMELINE.md](RESEARCH_TIMELINE.md): prototipe awal dengan persetujuan Admin, cutover empat peran, penyempurnaan UI, lalu kanal Telegram.

## Batas

### Revisi akses Telegram 28 Oktober 2026

Bagian autentikasi/penautan, tabel parameter teknis, alur percakapan, dan skenario pengujian harus menjelaskan akses persisten: tidak ada masa akses 30 hari/8 jam atau batas 60 menit tidak aktif. Pengguna langsung melanjutkan dari bot selama tautan dan hak akses masih sah. Challenge awal lima menit, tombol 15 menit, dan state percakapan 30 menit tetap untuk data sementara; /menu cukup untuk memulai ulang, bukan kembali ke web. /keluar menampilkan petunjuk pencabutan melalui web. Pencabutan dan perubahan data keamanan/status/role/area tetap diberlakukan.

Teks siap salin disediakan privat di `C:/Skripsi/revisi_2026-10-28_akses_telegram/Teks_Revisi_Akses_Telegram.md`. Sumber Word tidak ditimpa dan tidak ada hasil UAT/SUS baru yang diasumsikan dari perubahan kode.

- Kode Telegram tersedia di working copy, tetapi `TELEGRAM_ENABLED=false` secara default; token, webhook, produksi, dan UAT belum dibuktikan.
- Bukti UAT dikumpulkan di `UAT_EVIDENCE.md`, bukan dari pengujian otomatis.
- Data akun dan identitas peserta adalah data sensitif skripsi; tetap di luar repo atau diimport lewat alur `SEEDING.md`.
