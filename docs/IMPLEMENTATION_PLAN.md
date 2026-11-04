# Rencana implementasi web

Tahap cutover mengikuti rencana yang disetujui: identitas empat role; penghapusan dependency lama; lampiran opsional; claim/state machine; pengecualian; reset dummy terjaga; dokumentasi dan pengujian.

Setiap tahap memerlukan bukti kode/schema/tes. Reset operasional dilakukan sesudah quality gate; data nyata tidak boleh dihapus oleh baseline dummy.

Perbaikan bug, penyederhanaan UI, rekonsiliasi satu Admin, dan pengamanan tambahan disetujui 8 Oktober 2026; pelacakan ada di [IMPLEMENTATION_UI_2026-10-08.md](IMPLEMENTATION_UI_2026-10-08.md).

Implementasi kanal Telegram ada di working copy. Bot sudah aktif di production melalui environment server; `TELEGRAM_ENABLED=false` tetap default untuk instalasi baru. Cakupan, bukti tes, syarat privasi, dan langkah operasi terbaru ada di [TELEGRAM_CHANNEL.md](TELEGRAM_CHANNEL.md); validasi cutover 5 Oktober tetap merupakan arsip untuk tahap sebelumnya, bukan status Telegram terkini.
