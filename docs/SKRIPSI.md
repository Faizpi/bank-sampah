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
| 3.4 | Desain antarmuka Bot Telegram | Rancangan berada di skripsi; implementasi belum ada di repo |
| 3.5.1 | Pengujian fungsional dan integrasi | `docs/TEST_PLAN.md`, `tests/`, `npm run test:browser` |
| 3.5.2–3.5.6 | UAT, prosedur perbandingan, SUS, analisis data | `docs/UAT_EVIDENCE.md`; instrumen SUS ada di lampiran skripsi |
| Lampiran | Kuitansi/audit, kartu warga | `app/Livewire/Citizen/CustomerCard.php`, bukti kuitansi bawaan transaksi |

## Status dan pengembangan selanjutnya

Urutan kerja mengikuti `ROADMAP.md` dan bab 3 skripsi:

1. **UAT web dengan peserta nyata** (20 warga, pengurus Desa Sindangheula) → sumber data Bab 4.
2. **Implementasi Bot Telegram** sesuai rancangan Bab 3.4: verifikasi akun, menu per peran, saldo/riwayat, pengajuan penjemputan/pencairan/sembako, tugas petugas, penanganan sesi kadaluarsa. Penimbangan dan serah fisik tetap lewat web.
3. **Pengukuran SUS dan perbandingan antarmuka Web vs Bot** pada peserta yang sama → inti kontribusi penelitian.
4. **Deployment dan DRP** sesuai Bab 3.3.6 ketika penerapan disetujui.

## Batas

- Bot Telegram belum diimplementasikan; jangan mengklaim bot tersedia di luar skripsi.
- Bukti UAT dikumpulkan di `UAT_EVIDENCE.md`, bukan dari pengujian otomatis.
- Data akun dan identitas peserta adalah data sensitif skripsi; tetap di luar repo atau diimport lewat alur `SEEDING.md`.
