# Roadmap

1. Validasi teknis kanal web dan implementasi lokal Telegram untuk tiga role; pertahankan Admin di web.
2. Review keamanan independen serta UAT warga/pengurus nyata pada prototype; perbaiki istilah, langkah, dan kesalahan yang teramati.
3. Selaraskan Bab III dan instrumen penelitian dengan implementasi yang benar-benar diuji; SUS/perbandingan Web vs Telegram membutuhkan peserta nyata.
4. **Selesai di production.** Token, webhook HTTPS, dan scheduler sudah terpasang di server. Deployment berikutnya tetap keputusan terpisah.

Telegram aktif di production; `TELEGRAM_ENABLED=false` tetap default `.env.example` untuk instalasi baru. Penimbangan dan serah fisik tetap kegiatan langsung, tetapi hasilnya dapat dicatat lewat web atau bot memakai layanan domain yang sama. UAT dan SUS tetap langkah terpisah; WhatsApp tidak digunakan.
