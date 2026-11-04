# Linimasa Prototipe Penelitian

Dokumen ini merangkum tahapan prototipe **Bank Sampah Digital Sindangheula** dan memetakan
setiap tahap ke bukti yang tersedia di repositori. Riwayat commit disusun mengikuti urutan
tahapan agar perubahan kontrak, antarmuka, dan integrasi mudah ditelusuri.

## Ringkasan tahapan

| Tanggal | Tahap | Fokus | Bukti utama |
| --- | --- | --- | --- |
| 4–5 Oktober 2026 | Prototipe awal | Fondasi aplikasi, akun, data master, setoran, pickup, pencairan, sembako, laporan, dan audit. Setiap layanan masih melalui review atau keputusan Admin. | Riwayat commit awal; [kontrak bisnis](BUSINESS_RULES.md) |
| 5 Oktober 2026 | Cutover empat peran | Persetujuan Admin rutin dihapus. Setoran langsung final; pickup, pencairan, dan sembako memakai klaim pelaksana searea. Satu akun satu peran. | [Validasi cutover](VALIDATION_CUTOVER_2026-10-05.md), [workflow](WEB_WORKFLOW.md) |
| 8–9 Oktober 2026 | UI dan data | Penyempurnaan antarmuka empat peran, statistik landing, foto katalog, data master privat, dan copy publik. | [Pelaksanaan UI](IMPLEMENTATION_UI_2026-10-08.md), [landing dan katalog](LANDING_CATALOG_2026-10-09.md) |
| 17–24 Oktober 2026 | Penyempurnaan | Hero maskot, riwayat Petugas, akun Green Ledger, navigasi backoffice, dan tutorial video inline. | [Kontrak desain](../DESIGN.md), [catatan perubahan](CHANGELOG.md) |
| 25–28 Oktober 2026 | Bot Telegram | Penautan 1:1, consent opt-in, webhook, outbox idempoten, akses persisten, dan privasi v2.1. | [Kanal Telegram](TELEGRAM_CHANNEL.md), [kebijakan privasi](TERMS_AND_PRIVACY.md) |
| 29 Oktober–4 November 2026 | Penutupan prototipe | README, linimasa penelitian, changelog rilis, dan dokumentasi akhir. | [README utama](../README.md), [catatan perubahan](CHANGELOG.md) |

## Perubahan kontrak utama

### Sebelum cutover

- Setoran menunggu review Admin sebelum dianggap final.
- Pickup, pencairan tunai, dan sembako melalui keputusan atau verifikasi Admin.
- Peran dan izin masih dinamis; pengurus dapat merangkap tugas.
- Pengajuan layanan dapat kedaluwarsa otomatis.

### Sesudah cutover

- Setoran langsung final setelah berat dan nilai diperiksa Petugas.
- Pickup, pencairan, dan sembako memakai **klaim pelaksana searea**, bukan approval rutin.
- Satu akun memiliki tepat satu peran; Admin bukan pelaksana fisik rutin.
- Hold belum merupakan pengeluaran; serah fisik dicatat satu kali secara atomik.
- Tidak ada transfer bank, pembayaran sebagian, atau expiry otomatis pengajuan.
- Admin menangani akun, master data, audit, koreksi resmi, dan pengecualian beralasan.

## Peran kanal Telegram

Telegram adalah kanal opsional untuk **Warga, Petugas, dan Bendahara**. Admin tetap bekerja
melalui web. Bot memakai layanan domain, guard, dan pembukuan yang sama dengan web; ia tidak
menggantikan atau menggandakan transaksi. Penautan awal dan pemutusan dilakukan melalui web,
dengan consent notifikasi yang terpisah dan opt-in.

## Cara membaca riwayat

```bash
git log --reverse --date=short --pretty=format:"%ad  %s"
```

Gunakan riwayat tersebut bersama dokumen ini untuk melihat kapan sebuah kontrak berubah,
bukan hanya melihat keadaan kode terakhir.

## Batas

- Tanggal pada tabel adalah tanggal tahapan prototipe, bukan tanggal unggah dokumen skripsi.
- UAT, pengukuran SUS, dan perbandingan Web vs Telegram membutuhkan peserta desa nyata dan
  tidak diklaim selesai oleh dokumen ini.
- Bot Telegram aktif di production dengan token dan webhook pada environment server.
  `TELEGRAM_ENABLED=false` tetap nilai bawaan `.env.example` untuk instalasi baru; scheduler
  dan persetujuan privasi dikelola sebagai konfigurasi server.
- Data warga, dokumen skripsi, dan instrumen penelitian tetap berada di luar repositori publik.
