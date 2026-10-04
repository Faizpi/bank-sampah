# Dokumentasi Bank Sampah Digital Sindangheula

Dokumentasi ini menjadi kontrak pengembangan Sistem Informasi Bank Sampah Digital Desa Sindangheula setelah proposal disetujui.

## Status

- Baseline proposal: disetujui.
- Seluruh fitur yang disetujui: terkunci sebagai satu baseline pengembangan.
- Perubahan ruang lingkup: wajib melalui change request.
- Platform: web responsif mobile-first dan PWA installable dengan cache terbatas untuk halaman informasi umum.

## Teknologi

- Laravel 13 dan PHP 8.5.
- Blade, Livewire 4, Alpine.js bawaan Livewire.
- Tailwind CSS 4.1+.
- Filament 5 untuk back-office.
- MariaDB terkelola yang kompatibel dengan MySQL.
- Hostinger Web Hosting Premium atau Business.
- Pest 4.

## Dokumen Acuan

- Proposal: [`../documents/Pengajuan_Bank_Sampah_Digital_Desa_Sindangheula.pdf`](../documents/Pengajuan_Bank_Sampah_Digital_Desa_Sindangheula.pdf)
- Flowchart: [`../documents/Kumpulan_Flowchart_Bank_Sampah_Digital_Sindangheula.pdf`](../documents/Kumpulan_Flowchart_Bank_Sampah_Digital_Sindangheula.pdf)

## Kontrak Produk dan Bisnis

- [PRODUCT.md](PRODUCT.md) — identitas, pengguna, nilai, baseline, dan indikator keberhasilan.
- [REQUIREMENTS.md](REQUIREMENTS.md) — requirement berkode dan traceability seluruh fitur serta 36 flowchart.
- [BUSINESS_RULES.md](BUSINESS_RULES.md) — invariant lintas domain, finansial, status, dan privasi.
- [PERMISSIONS.md](PERMISSIONS.md) — permission granular, matriks role, scope record, dan separation of duties.
- [ROADMAP.md](ROADMAP.md) — urutan implementasi teknis seluruh baseline.
- [IMPLEMENTATION_PLAN.md](IMPLEMENTATION_PLAN.md) — tracker work item, dependency, status, quality gate, dan bukti eksekusi.
- [TERMS_AND_PRIVACY.md](TERMS_AND_PRIVACY.md) — Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 yang tersedia untuk publik.

## Kontrak Teknis

- [ARCHITECTURE.md](ARCHITECTURE.md) — modular monolith, boundary modul, UI, storage, queue, cron, dan PWA.
- [DATA_MODEL.md](DATA_MODEL.md) — ERD, tabel, tipe, indeks, constraint, status, dan deletion policy.
- [VALIDATION.md](VALIDATION.md) — normalisasi, aturan input/file/status, konkurensi, dan pesan gagal.
- [SECURITY.md](SECURITY.md) — kontrol aplikasi, data, file, QR, publik, backup, dan insiden.
- [DEPLOYMENT.md](DEPLOYMENT.md) — deployment dan rollback Hostinger shared hosting.

## Kontrak Antarmuka

- [DESIGN.md](DESIGN.md) — kontrak Sindangheula Green Ledger untuk token, shell, komponen, state, responsive, dan aksesibilitas.
- [USER_FLOWS.md](USER_FLOWS.md) — representasi tekstual normatif 36 flowchart.

## Kualitas dan Operasional

- [TEST_PLAN.md](TEST_PLAN.md) — strategi test, Given/When/Then, coverage, UAT, dan traceability.
- [OPERATIONS.md](OPERATIONS.md) — SOP seluruh role, layanan, gangguan, rekonsiliasi, dan pemulihan.
- [DECISIONS.md](DECISIONS.md) — ADR ringan keputusan final.
- [CHANGELOG.md](CHANGELOG.md) — riwayat perubahan baseline dan dokumentasi.

## Aturan Penggunaan Dokumen

1. `PRODUCT.md` dan `ROADMAP.md` menentukan ruang lingkup.
2. `REQUIREMENTS.md` menentukan perilaku yang harus dibangun.
3. `BUSINESS_RULES.md` menentukan aturan yang tidak boleh dilanggar.
4. `DATA_MODEL.md` menentukan integritas data.
5. `DESIGN.md` menentukan seluruh keputusan visual dan interaksi.
6. Bila dokumen bertentangan, keputusan terbaru yang tercatat di `DECISIONS.md` berlaku setelah mendapat persetujuan pengelola.
7. Tidak ada emoji pada antarmuka, notifikasi, empty state, ilustrasi, atau dokumentasi produk. Ikon menggunakan SVG dari set yang disetujui.
