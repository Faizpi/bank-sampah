# Dokumentasi Bank Sampah Digital Sindangheula

Dokumentasi aktif mengikuti cutover empat role 5 Oktober 2026 dan penyempurnaan UI 8 Oktober 2026. Kode, constraint database, dan hasil pengujian aktual menjadi bukti implementasi; catatan checkpoint lama bukan bukti keadaan terbaru.

- [Workflow](WEB_WORKFLOW.md), [panduan tiap pengguna](USER_FLOWS.md), dan [aturan bisnis](BUSINESS_RULES.md).
- [Kontrak desain aktif](../DESIGN.md) dan [pelaksanaan audit UI](IMPLEMENTATION_UI_2026-10-08.md).
- [Role dan akses](PERMISSIONS.md), [arsitektur](ARCHITECTURE.md), [model data](DATA_MODEL.md).
- [Keamanan](SECURITY.md), [operasional](OPERATIONS.md), [deployment](DEPLOYMENT.md).
- [Seeder utama dan data privat](SEEDING.md): sumber SQL pemilik, konversi empat peran, serta batas reset lokal.
- [Validasi data utama 9 Oktober](VALIDATION_MASTER_DATA_2026-10-09.md): backup, impor MySQL, pemisahan fixture, dan batas testing manual.
- [Rencana pengujian](TEST_PLAN.md), [bukti UI terbaru](IMPLEMENTATION_UI_2026-10-08.md), [bukti validasi cutover terdahulu](VALIDATION_CUTOVER_2026-10-05.md), [UAT](UAT_EVIDENCE.md).
- [Keputusan](DECISIONS.md), [perubahan](CHANGELOG.md), [roadmap](ROADMAP.md).

PDF proposal dan sembilan flowchart berada di `documents/`; keduanya dibuat dari sumber yang tersimpan di repository. Dokumen validasi checkpoint terdahulu tetap arsip, bukan hasil cutover.
