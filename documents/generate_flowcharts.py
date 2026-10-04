from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Literal

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas
from reportlab.lib.units import cm
from reportlab.platypus import Paragraph
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_CENTER, TA_LEFT

OUT = Path(__file__).resolve().parent
PDF_PATH = OUT / "Kumpulan_Flowchart_Bank_Sampah_Digital_Sindangheula.pdf"
W, H = landscape(A4)

GREEN = colors.HexColor("#145B4A")
TEAL = colors.HexColor("#168C78")
MINT = colors.HexColor("#DDF1EA")
PALE = colors.HexColor("#F3F8F5")
INK = colors.HexColor("#18302A")
MUTED = colors.HexColor("#61736D")
GOLD = colors.HexColor("#D6A84B")
RED = colors.HexColor("#B85C52")
BLUE = colors.HexColor("#3E748C")
LINE = colors.HexColor("#AFCBC1")
WHITE = colors.white


def register_fonts() -> tuple[str, str]:
    for regular, bold in [
        (r"C:\Windows\Fonts\aptos.ttf", r"C:\Windows\Fonts\aptosbd.ttf"),
        (r"C:\Windows\Fonts\calibri.ttf", r"C:\Windows\Fonts\calibrib.ttf"),
        (r"C:\Windows\Fonts\arial.ttf", r"C:\Windows\Fonts\arialbd.ttf"),
    ]:
        if Path(regular).exists() and Path(bold).exists():
            pdfmetrics.registerFont(TTFont("FlowRegular", regular))
            pdfmetrics.registerFont(TTFont("FlowBold", bold))
            return "FlowRegular", "FlowBold"
    return "Helvetica", "Helvetica-Bold"


FONT, BOLD = register_fonts()
NODE_STYLE = ParagraphStyle("node", fontName=FONT, fontSize=6.8, leading=8.1, textColor=INK, alignment=TA_CENTER)
NODE_BOLD = ParagraphStyle("nodebold", parent=NODE_STYLE, fontName=BOLD)
NOTE_STYLE = ParagraphStyle("note", fontName=FONT, fontSize=7, leading=8.8, textColor=MUTED, alignment=TA_LEFT)

NodeType = Literal["start", "process", "decision", "data", "document", "end"]


@dataclass(frozen=True)
class Node:
    id: str
    label: str
    kind: NodeType = "process"


@dataclass(frozen=True)
class Edge:
    source: str
    target: str
    label: str = ""


@dataclass(frozen=True)
class Diagram:
    category: str
    title: str
    purpose: str
    actors: str
    levels: list[list[Node]]
    edges: list[Edge]
    note: str


def n(id_: str, label: str, kind: NodeType = "process") -> Node:
    return Node(id_, label, kind)


def chain(ids: list[str]) -> list[Edge]:
    return [Edge(a, b) for a, b in zip(ids, ids[1:])]


DIAGRAMS: list[Diagram] = [
    Diagram("PERENCANAAN & PENGEMBANGAN", "Tahapan Pengembangan Aplikasi", "Menunjukkan siklus pengembangan dari kebutuhan hingga pemeliharaan.", "Tim pengembang, pengelola, petugas, perwakilan warga", [[n("a","Mulai","start")],[n("b","Analisis kebutuhan")],[n("c","Perancangan sistem dan UI")],[n("d","Pengembangan modul")],[n("e","Pengujian")],[n("f","Sesuai kebutuhan?","decision")],[n("g","Penerapan")],[n("h","Pemeliharaan dan evaluasi")],[n("i","Siklus berlanjut","end")]], chain(list("abcdef"))+[Edge("f","g","Ya"),Edge("f","b","Tidak"),Edge("g","h"),Edge("h","i")], "Setiap tahap menghasilkan keluaran yang diperiksa sebelum berlanjut."),
    Diagram("PERENCANAAN & PENGEMBANGAN", "Peta Jalan Pengembangan", "Menunjukkan urutan implementasi seluruh baseline fitur yang telah disetujui.", "Pemerintah desa, pengelola, tim pengembang", [[n("a","Baseline fitur disetujui","start")],[n("b","Fondasi, akun, master data")],[n("c","Transaksi, saldo, penjemputan, pencairan, sembako")],[n("d","Program, layanan keliling, estimasi, QR, statistik")],[n("e","Integrasi seluruh modul")],[n("f","Pengujian dan UAT")],[n("g","Sesuai baseline?","decision")],[n("h","Perbaikan")],[n("i","Peluncuran dan evaluasi","end")]], chain(["a","b","c","d","e","f","g"])+[Edge("g","h","Belum"),Edge("h","e"),Edge("g","i","Ya")], "Urutan implementasi bukan pembagian tahap fitur; seluruh baseline wajib selesai sebelum penerimaan sistem."),
    Diagram("PERENCANAAN & PENGEMBANGAN", "Pengujian dan Penerimaan Sistem", "Menetapkan alur pemeriksaan kualitas sebelum peluncuran.", "Tim pengembang, admin, petugas, warga uji", [[n("a","Build siap diuji","start")],[n("b","Uji fungsi dan hak akses")],[n("c","Uji transaksi, saldo, unggahan, laporan")],[n("d","Temuan masalah?","decision")],[n("e","Perbaikan dan pengujian ulang")],[n("f","UAT oleh pengguna")],[n("g","Diterima?","decision")],[n("h","Persetujuan peluncuran")],[n("i","Produksi","end")]], chain(["a","b","c","d"])+[Edge("d","e","Ada"),Edge("e","b"),Edge("d","f","Tidak"),Edge("f","g"),Edge("g","e","Belum"),Edge("g","h","Ya"),Edge("h","i")], "Jika UAT belum diterima, alur kembali ke perbaikan lalu seluruh pengujian terkait diulang."),
    Diagram("SISTEM & PENGGUNA", "Diagram Konteks Sistem", "Memperlihatkan pihak yang berinteraksi dengan Bank Sampah Digital.", "Warga, petugas, admin, bendahara, superadmin", [[n("a","Warga\nregistrasi, setoran, saldo, pengajuan","data"),n("b","Petugas\npenimbangan, penjemputan, penyerahan","data")],[n("s","BANK SAMPAH DIGITAL\nSINDANGHEULA","process")],[n("c","Admin\npengguna, harga, persetujuan, laporan","data"),n("d","Bendahara\npembayaran pencairan","data"),n("e","Superadmin\nkonfigurasi dan pemeliharaan","data")]], [Edge("a","s"),Edge("b","s"),Edge("s","c"),Edge("s","d"),Edge("s","e")], "Semua akses dibatasi berdasarkan role dan setiap aktivitas penting dicatat."),
    Diagram("SISTEM & PENGGUNA", "Arsitektur Sistem Tingkat Tinggi", "Menunjukkan teknologi utama, managed hosting, komponen sistem, dan aliran data.", "Pengguna aplikasi dan pengelola teknis", [[n("a","Smartphone / tablet / komputer","start")],[n("b","Browser atau PWA")],[n("c","Blade + Livewire + Alpine.js + Tailwind CSS")],[n("d","Laravel 13 + PHP 8.5")],[n("e","Otentikasi, transaksi, saldo, laporan")],[n("f","MariaDB kompatibel MySQL","data"),n("g","Storage privat / object storage S3","data")],[n("h","Hostinger Web Premium / Business\nhPanel + SSH/SFTP + Composer 2 + cron")],[n("i","Infrastruktur Linux dan web server dikelola Hostinger")],[n("j","Backup terpisah","end")]], chain(["a","b","c","d","e"])+[Edge("e","f"),Edge("e","g"),Edge("f","h"),Edge("g","h"),Edge("h","i"),Edge("i","j")], "Arsitektur modular monolith pada managed web hosting. Laravel Scheduler dan antrean berkala memakai cron; worker permanen atau konfigurasi server khusus memerlukan peningkatan ke VPS."),
    Diagram("SISTEM & PENGGUNA", "Alur Hak Akses Pengguna", "Menentukan dashboard dan fitur berdasarkan peran setelah login.", "Seluruh pengguna terdaftar", [[n("a","Buka aplikasi","start")],[n("b","Login")],[n("c","Kredensial valid?","decision")],[n("d","Tolak dan catat percobaan")],[n("e","Identifikasi role")],[n("f","Warga"),n("g","Petugas / bendahara"),n("h","Admin / superadmin")],[n("i","Dashboard dan fitur sesuai izin")],[n("j","Logout / sesi berakhir","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("d","b"),Edge("c","e","Ya"),Edge("e","f"),Edge("e","g"),Edge("e","h"),Edge("f","i"),Edge("g","i"),Edge("h","i"),Edge("i","j")], "Role menentukan kelompok akses; permission membatasi tindakan yang lebih spesifik."),
    Diagram("SISTEM & PENGGUNA", "Sitemap Aplikasi", "Merangkum struktur navigasi utama untuk setiap kelompok pengguna.", "Warga, petugas, admin", [[n("a","Beranda / Login","start")],[n("b","Halaman umum"),n("c","Area warga"),n("d","Area petugas"),n("e","Area admin")],[n("f","Harga, statistik publik, jadwal, verifikasi QR"),n("g","Dashboard, estimasi, transaksi/koreksi, saldo, pengajuan"),n("h","Tugas hari ini, QR, layanan berbantuan, keliling, penyerahan"),n("i","Target, partisipasi wilayah, kapasitas, laporan, audit")],[n("j","Keluar","end")]], [Edge("a","b"),Edge("a","c"),Edge("a","d"),Edge("a","e"),Edge("b","f"),Edge("c","g"),Edge("d","h"),Edge("e","i"),Edge("f","j"),Edge("g","j"),Edge("h","j"),Edge("i","j")], "Navigasi tiap role hanya menampilkan menu yang diizinkan; statistik publik tidak memuat data pribadi."),
    Diagram("OPERASIONAL UTAMA", "Registrasi dan Verifikasi Akun", "Menjelaskan pendaftaran warga hingga akun aktif.", "Warga dan admin", [[n("a","Warga membuka registrasi","start")],[n("b","Isi data dan persetujuan")],[n("c","Data lengkap dan valid?","decision")],[n("d","Tampilkan kesalahan")],[n("e","Simpan: menunggu verifikasi")],[n("f","Admin memeriksa domisili dan duplikasi")],[n("g","Disetujui?","decision")],[n("h","Aktifkan akun"),n("i","Tolak dengan alasan")],[n("j","Kirim notifikasi","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("d","b"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h","Ya"),Edge("g","i","Tidak"),Edge("h","j"),Edge("i","j")], "Verifikasi admin disarankan agar nasabah sesuai wilayah pelayanan."),
    Diagram("OPERASIONAL UTAMA", "Setoran Sampah Langsung", "Menunjukkan setoran di lokasi bank sampah hingga saldo bertambah.", "Warga dan petugas", [[n("a","Warga datang","start")],[n("b","QR / nomor nasabah")],[n("c","Petugas konfirmasi identitas")],[n("d","Timbang dan pilih jenis")],[n("e","Input berat; sistem ambil harga")],[n("f","Hitung subtotal dan total")],[n("g","Data benar?","decision")],[n("h","Perbaiki data")],[n("i","Unggah bukti dan konfirmasi")],[n("j","Buat transaksi dan saldo masuk")],[n("k","Notifikasi dan bukti transaksi","end")]], chain(["a","b","c","d","e","f","g"])+[Edge("g","h","Tidak"),Edge("h","d","Ulangi input"),Edge("g","i","Ya"),Edge("i","j"),Edge("j","k")], "Cabang Tidak kembali ke penimbangan/input; cabang Ya melanjutkan konfirmasi. Harga disimpan sebagai snapshot."),
    Diagram("OPERASIONAL UTAMA", "Pengajuan dan Penyelesaian Penjemputan", "Menjelaskan seluruh proses penjemputan dari foto hingga saldo masuk.", "Warga, admin/petugas, petugas lapangan", [[n("a","Warga isi pengajuan dan foto","start")],[n("b","Sistem validasi data")],[n("c","Kapasitas tanggal tersedia?","decision")],[n("x","Tawarkan tanggal lain")],[n("d","Petugas periksa kelayakan")],[n("e","Layak dijemput?","decision")],[n("f","Ditolak dan alasan dikirim","end")],[n("g","Terima dan jadwalkan")],[n("h","Petugas menuju lokasi")],[n("i","Jemput dan timbang aktual")],[n("j","Catat transaksi")],[n("k","Konfirmasi; saldo masuk")],[n("l","Status selesai","end")]], chain(["a","b","c"])+[Edge("c","x","Tidak"),Edge("x","c","Pilih ulang"),Edge("c","d","Ya"),Edge("d","e"),Edge("e","f","Tidak"),Edge("e","g","Ya"),Edge("g","h"),Edge("h","i"),Edge("i","j"),Edge("j","k"),Edge("k","l")], "Cabang penolakan berhenti setelah alasan dikirim; hanya cabang layak yang masuk jadwal dan transaksi."),
    Diagram("OPERASIONAL UTAMA", "Pencairan Saldo Tunai", "Menjelaskan pengajuan pencairan hingga uang diterima.", "Warga, admin, bendahara/petugas", [[n("a","Warga ajukan nominal","start")],[n("b","Saldo dan minimum cukup?","decision")],[n("c","Input ditolak","end")],[n("d","Tahan saldo")],[n("e","Admin memeriksa")],[n("f","Disetujui?","decision")],[n("g","Saldo dilepas; pengajuan ditolak","end")],[n("h","Tentukan jadwal dan petugas")],[n("i","Verifikasi penerima")],[n("j","Serahkan uang dan unggah bukti")],[n("k","Catat saldo keluar; selesai","end")]], chain(["a","b"])+[Edge("b","c","Tidak"),Edge("b","d","Ya"),Edge("d","e"),Edge("e","f"),Edge("f","g","Tidak"),Edge("f","h","Ya"),Edge("h","i"),Edge("i","j"),Edge("j","k")], "Jalur Tidak berhenti tanpa pembayaran; hanya jalur Ya yang menuju penyerahan uang."),
    Diagram("OPERASIONAL UTAMA", "Penukaran Saldo dengan Sembako", "Menjelaskan penukaran paket tanpa pengelolaan stok terperinci.", "Warga, admin, petugas/pemerintah desa", [[n("a","Warga pilih paket","start")],[n("b","Saldo cukup dan paket aktif?","decision")],[n("c","Pengajuan ditolak","end")],[n("d","Tahan saldo")],[n("e","Admin cek ketersediaan manual")],[n("f","Tersedia?","decision")],[n("g","Saldo dilepas; pengajuan ditolak","end")],[n("h","Setujui dan siapkan paket")],[n("i","Verifikasi warga dan serahkan")],[n("j","Unggah bukti")],[n("k","Catat saldo keluar; selesai","end")]], chain(["a","b"])+[Edge("b","c","Tidak"),Edge("b","d","Ya"),Edge("d","e"),Edge("e","f"),Edge("f","g","Tidak"),Edge("f","h","Ya"),Edge("h","i"),Edge("i","j"),Edge("j","k")], "Jalur penolakan berhenti dan saldo dilepas; hanya paket tersedia yang dapat diserahkan."),
    Diagram("OPERASIONAL UTAMA", "Koreksi Transaksi Final", "Memastikan koreksi dapat ditelusuri, menyesuaikan saldo, dan terlihat oleh warga.", "Admin berwenang dan warga", [[n("a","Pilih transaksi final","start")],[n("b","Masukkan alasan dan bukti")],[n("c","Tampilkan nilai lama")],[n("d","Masukkan nilai yang benar")],[n("e","Hitung selisih dan dampak saldo")],[n("f","Konfirmasi koreksi?","decision")],[n("g","Perubahan dibatalkan","end")],[n("h","Buat catatan koreksi")],[n("i","Buat mutasi penyesuaian")],[n("j","Simpan audit log")],[n("k","Tampilkan nilai lama/baru, alasan, dampak kepada warga")],[n("l","Kirim notifikasi","end")]], chain(["a","b","c","d","e","f"])+[Edge("f","g","Tidak"),Edge("f","h","Ya"),Edge("h","i"),Edge("i","j"),Edge("j","k"),Edge("k","l")], "Cabang batal berhenti tanpa mutasi; cabang konfirmasi membuat penyesuaian dan audit."),
    Diagram("OPERASIONAL UTAMA", "Perubahan Harga Sampah", "Menjaga riwayat harga dan transaksi lama tetap konsisten.", "Admin", [[n("a","Pilih jenis sampah","start")],[n("b","Masukkan harga dan tanggal berlaku")],[n("c","Data valid dan tidak tumpang tindih?","decision")],[n("d","Perbaiki data")],[n("e","Tutup periode harga lama")],[n("f","Aktifkan harga baru")],[n("g","Simpan riwayat dan audit")],[n("h","Tampilkan ke warga","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("d","b"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h")], "Detail transaksi menyimpan harga saat transaksi sehingga perubahan tidak mengubah riwayat lama."),
    Diagram("OPERASIONAL UTAMA", "Alur Notifikasi dan Pengingat", "Menunjukkan kejadian dan jadwal yang menghasilkan pemberitahuan kepada pengguna.", "Sistem, warga, petugas, admin", [[n("a","Peristiwa atau jadwal tercapai","start")],[n("b","Akun / transaksi / koreksi / saldo / status / harga / jadwal / kedaluwarsa")],[n("c","Tentukan penerima, waktu, dan pesan")],[n("d","Simpan notifikasi dalam aplikasi","data")],[n("e","Kanal tambahan aktif?","decision")],[n("f","Buka WhatsApp manual / push opsional")],[n("g","Tampilkan belum dibaca dan pengingat")],[n("h","Pengguna membaca","end")]], chain(["a","b","c","d","e"])+[Edge("e","f","Ya"),Edge("e","g","Tidak"),Edge("f","g"),Edge("g","h")], "Notifikasi aplikasi dan WhatsApp manual termasuk baseline; gateway otomatis berada di luar baseline."),
    Diagram("SALDO & TRANSAKSI", "Alur Saldo Masuk", "Menjelaskan kapan nilai setoran resmi menambah saldo warga.", "Petugas, sistem, warga", [[n("a","Data penimbangan siap","start")],[n("b","Validasi berat dan harga")],[n("c","Transaksi dikonfirmasi?","decision")],[n("d","Tersimpan sebagai draf","end")],[n("e","Buat transaksi final")],[n("f","Buat mutasi saldo masuk")],[n("g","Perbarui saldo tersedia")],[n("h","Bukti dan notifikasi","end")]], chain(["a","b","c"])+[Edge("c","d","Belum"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h")], "Draf menjadi endpoint sementara tanpa perubahan saldo; transaksi final hanya menghasilkan satu mutasi saldo masuk."),
    Diagram("SALDO & TRANSAKSI", "Alur Saldo Keluar", "Menjelaskan kapan saldo dianggap benar-benar telah digunakan.", "Warga, admin, petugas/bendahara", [[n("a","Pengajuan telah disetujui","start")],[n("b","Saldo masih tertahan")],[n("c","Uang/barang sudah diserahkan?","decision")],[n("d","Status tetap tertahan","end")],[n("e","Unggah dan validasi bukti")],[n("f","Buat mutasi saldo keluar")],[n("g","Hapus penahanan")],[n("h","Pengajuan selesai","end")]], chain(["a","b","c"])+[Edge("c","d","Belum"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h")], "Belum diserahkan menjadi endpoint sementara; saldo keluar hanya dicatat setelah manfaat diterima."),
    Diagram("SALDO & TRANSAKSI", "Alur Saldo Tertahan", "Menjelaskan fungsi penahanan saldo selama pengajuan berlangsung.", "Warga dan sistem", [[n("a","Warga membuat pengajuan","start")],[n("b","Hitung saldo tersedia")],[n("c","Saldo mencukupi?","decision")],[n("d","Pengajuan ditolak","end")],[n("e","Buat penahanan saldo")],[n("f","Kurangi saldo yang dapat digunakan")],[n("g","Hasil pengajuan?","decision")],[n("h","Diselesaikan: ubah menjadi saldo keluar"),n("i","Ditolak/dibatalkan: lepaskan penahanan")],[n("j","Perbarui saldo tersedia","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h","Selesai"),Edge("g","i","Batal/tolak"),Edge("h","j"),Edge("i","j")], "Saldo tidak cukup berhenti tanpa penahanan; hanya pengajuan sah yang mengurangi saldo tersedia."),
    Diagram("SALDO & TRANSAKSI", "Siklus Saldo Warga", "Merangkum hubungan saldo masuk, saldo tersedia, saldo tertahan, dan saldo keluar.", "Warga, petugas, admin, sistem", [[n("a","Setoran sah","start")],[n("b","Saldo masuk")],[n("c","Saldo tersedia")],[n("d","Warga membuat pengajuan")],[n("e","Saldo tertahan")],[n("f","Pengajuan selesai?","decision")],[n("g","Saldo keluar"),n("h","Saldo dikembalikan")],[n("i","Saldo tersedia terbaru","end")]], chain(["a","b","c","d","e","f"])+[Edge("f","g","Ya"),Edge("f","h","Tidak"),Edge("g","i"),Edge("h","i"),Edge("i","d","Pengajuan baru")], "Rumus: saldo tersedia = total saldo masuk − total saldo keluar − saldo tertahan."),
    Diagram("SALDO & TRANSAKSI", "Pencegahan Transaksi Ganda", "Mencegah klik atau permintaan berulang menghasilkan mutasi ganda.", "Petugas dan sistem", [[n("a","Permintaan konfirmasi","start")],[n("b","Baca nomor/idempotency key")],[n("c","Sudah pernah diproses?","decision")],[n("d","Kembalikan hasil sebelumnya","end")],[n("e","Mulai database transaction")],[n("f","Kunci referensi dan validasi")],[n("g","Simpan transaksi dan mutasi atomik")],[n("h","Commit berhasil?","decision")],[n("i","Rollback; tampilkan gagal","end")],[n("j","Kembalikan hasil sukses","end")]], chain(["a","b","c"])+[Edge("c","d","Ya"),Edge("c","e","Tidak"),Edge("e","f"),Edge("f","g"),Edge("g","h"),Edge("h","i","Tidak"),Edge("h","j","Ya")], "Permintaan yang pernah diproses berhenti dengan hasil lama; hanya permintaan baru masuk database transaction."),
    Diagram("SALDO & TRANSAKSI", "Pembatalan dan Pengembalian Saldo", "Menunjukkan pelepasan saldo tertahan ketika pengajuan tidak dilanjutkan.", "Warga, admin, sistem", [[n("a","Pengajuan aktif","start")],[n("b","Permintaan batal / keputusan tolak")],[n("c","Masih boleh dibatalkan?","decision")],[n("d","Pembatalan ditolak","end")],[n("e","Simpan alasan dan status")],[n("f","Ada saldo tertahan?","decision")],[n("g","Lepaskan penahanan"),n("h","Tidak ada penahanan")],[n("i","Hitung ulang bila diperlukan")],[n("j","Audit dan notifikasi","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g","Ya"),Edge("f","h","Tidak"),Edge("g","i"),Edge("h","i"),Edge("i","j")], "Pengajuan yang tidak boleh dibatalkan berhenti; pelepasan hanya terjadi jika saldo benar-benar tertahan."),
    Diagram("STATUS", "Status Penjemputan", "Memperlihatkan transisi status penjemputan yang diizinkan.", "Warga, admin, petugas", [[n("a","Menunggu pemeriksaan","start")],[n("q","Hasil pemeriksaan?","decision")],[n("x","Ditolak","end"),n("b","Diterima")],[n("c","Dijadwalkan")],[n("r","Dibatalkan sebelum berangkat?","decision")],[n("y","Dibatalkan","end"),n("d","Petugas menuju lokasi")],[n("e","Sampah telah dijemput")],[n("f","Selesai","end")]], [Edge("a","q"),Edge("q","x","Tolak"),Edge("q","b","Terima"),Edge("b","c"),Edge("c","r"),Edge("r","y","Ya"),Edge("r","d","Tidak"),Edge("d","e"),Edge("e","f")], "Penolakan dan pembatalan menjadi endpoint; selesai hanya setelah transaksi hasil penimbangan berhasil."),
    Diagram("STATUS", "Status Pencairan Tunai", "Memperlihatkan status pengajuan pencairan dari awal hingga selesai.", "Warga, admin, bendahara", [[n("a","Menunggu verifikasi","start")],[n("q","Keputusan admin?","decision")],[n("x","Ditolak","end"),n("b","Disetujui")],[n("c","Siap diambil")],[n("r","Uang diambil sebelum batas waktu?","decision")],[n("z","Kedaluwarsa","end"),n("d","Sudah dibayar","end")]], [Edge("a","q"),Edge("q","x","Tolak"),Edge("q","b","Setuju"),Edge("b","c"),Edge("c","r"),Edge("r","z","Tidak"),Edge("r","d","Ya")], "Pembatalan oleh warga sebelum persetujuan ditangani melalui alur pembatalan; saldo dilepas pada ditolak atau kedaluwarsa."),
    Diagram("STATUS", "Status Penukaran Sembako", "Memperlihatkan status penukaran paket sampai penyerahan.", "Warga, admin, petugas", [[n("a","Menunggu verifikasi","start")],[n("q","Keputusan admin?","decision")],[n("x","Ditolak","end"),n("b","Disetujui")],[n("c","Sedang disiapkan")],[n("d","Siap diambil")],[n("r","Paket diambil sebelum batas waktu?","decision")],[n("z","Kedaluwarsa","end"),n("e","Selesai","end")]], [Edge("a","q"),Edge("q","x","Tolak"),Edge("q","b","Setuju"),Edge("b","c"),Edge("c","d"),Edge("d","r"),Edge("r","z","Tidak"),Edge("r","e","Ya")], "Pembatalan sebelum persetujuan mengikuti alur pembatalan; selesai hanya setelah bukti penyerahan disimpan."),
    Diagram("STATUS", "Status Transaksi Setoran", "Memisahkan transaksi yang masih dapat diedit dari transaksi final.", "Petugas dan admin", [[n("a","Draf","start")],[n("b","Data lengkap dan valid?","decision")],[n("c","Perbaiki draf")],[n("d","Dikonfirmasi / final")],[n("e","Ditemukan kesalahan?","decision")],[n("f","Tetap final","end")],[n("g","Koreksi admin")],[n("h","Dikoreksi / dibalik","end")]], chain(["a","b"])+[Edge("b","c","Tidak"),Edge("c","a"),Edge("b","d","Ya"),Edge("d","e"),Edge("e","f","Tidak"),Edge("e","g","Ya"),Edge("g","h")], "Transaksi final tidak dihapus; perubahan menggunakan koreksi dengan audit log."),
    Diagram("ADMINISTRASI & PEMELIHARAAN", "Pembuatan Laporan dan Statistik", "Menjelaskan laporan operasional, partisipasi RT/RW, dan ringkasan publik.", "Admin dan sistem", [[n("a","Pilih laporan / statistik","start")],[n("b","Tentukan periode, wilayah, jenis, dan filter")],[n("c","Publik atau internal?","decision")],[n("d","Agregasikan dan hilangkan data pribadi"),n("e","Validasi hak akses internal")],[n("f","Data besar?","decision")],[n("g","Proses langsung"),n("h","Masukkan antrean berkala")],[n("i","Bangun web / grafik / Excel / CSV / PDF","document")],[n("j","Catat aktivitas ekspor/publikasi")],[n("k","Tampilkan atau unduh","end")]], chain(["a","b","c"])+[Edge("c","d","Publik"),Edge("c","e","Internal"),Edge("d","f"),Edge("e","f"),Edge("f","g","Tidak"),Edge("f","h","Ya"),Edge("g","i"),Edge("h","i"),Edge("i","j"),Edge("j","k")], "Statistik publik hanya berisi data agregat; partisipasi RT/RW dapat tampil sebagai tabel, grafik, atau heatmap."),
    Diagram("ADMINISTRASI & PEMELIHARAAN", "Audit Log", "Menunjukkan pencatatan aktivitas penting yang tidak dapat diubah pengguna operasional.", "Seluruh pengguna dan sistem", [[n("a","Aktivitas penting terjadi","start")],[n("b","Identifikasi pengguna, waktu, perangkat")],[n("c","Catat aksi, objek, nilai lama dan baru")],[n("d","Simpan audit log","data")],[n("e","Admin meminta penelusuran")],[n("f","Filter dan tampilkan riwayat")],[n("g","Permintaan penghapusan sesuai kebijakan retensi teknis?","decision")],[n("h","Tolak perubahan"),n("r","Jalankan retensi oleh pengelola teknis")],[n("i","Selesai","end")]], chain(["a","b","c","d","e","f","g"])+[Edge("g","h","Tidak"),Edge("g","r","Ya"),Edge("h","i"),Edge("r","i")], "Penghapusan hanya mengikuti kebijakan retensi teknis, bukan fungsi operasional biasa."),
    Diagram("ADMINISTRASI & PEMELIHARAAN", "Backup dan Pemulihan Data", "Menjelaskan pencadangan terjadwal dan pengujian pemulihan.", "Superadmin/pengelola teknis", [[n("a","Jadwal backup","start")],[n("b","Backup database dan media")],[n("c","Enkripsi dan simpan terpisah")],[n("d","Verifikasi checksum / integritas")],[n("e","Backup valid?","decision")],[n("f","Catat gagal dan kirim notifikasi")],[n("g","Catat berhasil dan terapkan retensi")],[n("h","Uji pemulihan berkala")],[n("i","Pemulihan berhasil?","decision")],[n("j","Perbaiki prosedur"),n("k","Selesai","end")]], chain(["a","b","c","d","e"])+[Edge("e","f","Tidak"),Edge("e","g","Ya"),Edge("g","h"),Edge("h","i"),Edge("i","j","Tidak"),Edge("j","b"),Edge("i","k","Ya")], "Target awal: RPO maksimal 24 jam dan RTO maksimal 8 jam, disesuaikan infrastruktur."),
    Diagram("ADMINISTRASI & PEMELIHARAAN", "Penanganan Kesalahan Transaksi", "Menentukan langkah aman ketika transaksi gagal atau hasilnya diragukan.", "Petugas, admin, sistem", [[n("a","Kesalahan terdeteksi","start")],[n("b","Hentikan pengulangan manual")],[n("c","Periksa status transaksi dan mutasi")],[n("d","Transaksi tersimpan lengkap?","decision")],[n("e","Gunakan hasil yang sudah ada"),n("f","Rollback proses tidak lengkap")],[n("g","Saldo terlanjur berubah?","decision")],[n("h","Buat koreksi admin"),n("i","Tidak perlu koreksi saldo")],[n("j","Catat insiden dan audit")],[n("k","Verifikasi saldo dan bukti","end")]], chain(["a","b","c","d"])+[Edge("d","e","Ya"),Edge("d","f","Tidak"),Edge("e","j"),Edge("f","g"),Edge("g","h","Ya"),Edge("g","i","Tidak"),Edge("h","j"),Edge("i","j"),Edge("j","k")], "Transaksi lengkap digunakan kembali; proses parsial di-rollback dan saldo dikoreksi hanya jika terlanjur berubah."),
    Diagram("ADMINISTRASI & PEMELIHARAAN", "Rekonsiliasi Harian", "Mencocokkan transaksi, saldo, kas, dan bukti pada akhir pelayanan.", "Admin, bendahara, petugas", [[n("a","Tutup pelayanan harian","start")],[n("b","Ambil saldo awal dan transaksi hari ini")],[n("c","Hitung setoran, pencairan, penukaran, koreksi")],[n("d","Cocokkan saldo akhir, kas, dan bukti")],[n("e","Ada selisih?","decision")],[n("f","Telusuri transaksi dan petugas")],[n("g","Buat koreksi resmi bila terbukti")],[n("h","Sahkan rekap harian")],[n("i","Simpan laporan rekonsiliasi","document")],[n("j","Selesai","end")]], chain(["a","b","c","d","e"])+[Edge("e","f","Ada"),Edge("f","g"),Edge("g","d"),Edge("e","h","Tidak"),Edge("h","i"),Edge("i","j")], "Rekonsiliasi membantu memastikan kewajiban saldo warga sesuai dengan pencatatan dan kas."),
    Diagram("PROGRAM & TRANSPARANSI", "Target Pengumpulan Sampah Desa", "Menetapkan target periodik dan menampilkan progres agregat kepada masyarakat.", "Admin, sistem, warga/publik", [[n("a","Admin membuat target","start")],[n("b","Pilih jenis, periode, berat, dan tujuan")],[n("c","Data target valid?","decision")],[n("d","Perbaiki target")],[n("e","Aktifkan target")],[n("f","Akumulasi transaksi sah")],[n("g","Hitung progres dan sisa target")],[n("h","Periode selesai?","decision")],[n("i","Progres dipublikasikan; target tetap aktif","end"),n("j","Tutup target dan simpan hasil")],[n("k","Ringkasan akhir dipublikasikan","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("d","b"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h"),Edge("h","i","Belum"),Edge("h","j","Ya"),Edge("j","k")], "Status aktif akan dihitung lagi saat transaksi baru masuk; periode selesai menutup target dan menghasilkan ringkasan akhir."),
    Diagram("PROGRAM & TRANSPARANSI", "Bank Sampah Keliling per RT/RW", "Menyelenggarakan titik pelayanan terjadwal yang lebih efisien daripada penjemputan rumah per rumah.", "Admin, petugas, warga", [[n("a","Admin membuat jadwal keliling","start")],[n("b","Tentukan wilayah, titik, waktu, petugas, kapasitas")],[n("c","Jadwal tersedia?","decision")],[n("d","Pilih waktu atau petugas lain")],[n("e","Publikasikan dan kirim pengingat")],[n("f","Petugas membuka titik pelayanan")],[n("g","Warga datang dan menunjukkan QR/nomor")],[n("h","Catat setoran seperti transaksi langsung")],[n("i","Saldo masuk dan bukti transaksi")],[n("j","Tutup layanan dan rekap","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("d","b"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g"),Edge("g","h"),Edge("h","i"),Edge("i","j")], "Bank Sampah Keliling adalah titik layanan terjadwal, bukan penjemputan individual ke rumah."),
    Diagram("PROGRAM & TRANSPARANSI", "Estimasi Nilai Sebelum Setor", "Memberikan perkiraan nilai tanpa membentuk transaksi atau menjamin saldo akhir.", "Warga dan sistem", [[n("a","Buka kalkulator estimasi","start")],[n("b","Pilih jenis sampah")],[n("c","Masukkan perkiraan berat")],[n("d","Input valid dan harga aktif tersedia?","decision")],[n("e","Tampilkan petunjuk perbaikan")],[n("f","Hitung berat × harga aktif")],[n("g","Tampilkan estimasi, edukasi, dan penafian")],[n("h","Warga menutup kalkulator")],[n("i","Selesai tanpa transaksi","end")]], chain(["a","b","c","d"])+[Edge("d","e","Tidak"),Edge("e","b"),Edge("d","f","Ya"),Edge("f","g"),Edge("g","h"),Edge("h","i")], "Penafian selalu ditampilkan; estimasi tidak membuat transaksi, mutasi, atau penahanan saldo."),
    Diagram("FITUR INKLUSIF", "Pelayanan Warga Tanpa Smartphone", "Memastikan warga tetap dapat menjadi nasabah dan menerima layanan melalui bantuan petugas.", "Warga, petugas, admin", [[n("a","Warga meminta bantuan","start")],[n("b","Petugas menjelaskan dan meminta persetujuan")],[n("c","Warga menyetujui?","decision")],[n("d","Hentikan proses")],[n("e","Cari atau bantu buat akun")],[n("f","Akun/identitas valid?","decision")],[n("g","Minta admin melengkapi/verifikasi")],[n("h","Gunakan nomor atau kartu QR")],[n("i","Catat transaksi atas nama warga")],[n("j","Berikan bukti cetak dan informasi saldo","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g","Tidak"),Edge("g","e"),Edge("f","h","Ya"),Edge("h","i"),Edge("i","j")], "Petugas tidak mengambil alih kata sandi pribadi; semua aktivitas tetap tercatat atas nama pelaksana."),
    Diagram("PROGRAM & TRANSPARANSI", "Verifikasi Bukti Transaksi dengan QR", "Memeriksa keaslian bukti melalui token tanpa membuka data pribadi.", "Warga, pemeriksa, sistem", [[n("a","Pindai QR pada bukti","start")],[n("b","Buka token verifikasi")],[n("c","Token valid dan aktif?","decision")],[n("d","Tampilkan bukti tidak ditemukan/tidak sah")],[n("e","Ambil data transaksi terbatas")],[n("f","Transaksi final?","decision")],[n("g","Tampilkan status belum final")],[n("h","Tampilkan nomor, tanggal, berat, nilai, status sah")],[n("i","Selesai","end")]], chain(["a","b","c"])+[Edge("c","d","Tidak"),Edge("c","e","Ya"),Edge("e","f"),Edge("f","g","Tidak"),Edge("f","h","Ya"),Edge("g","i"),Edge("h","i")], "QR tidak memuat saldo, alamat, identitas, kata sandi, atau nomor telepon lengkap."),
    Diagram("PROGRAM & TRANSPARANSI", "Pengaturan Kapasitas Penjemputan Harian", "Mencegah jadwal melebihi kemampuan petugas, kendaraan, wilayah, atau jumlah alamat.", "Admin, warga, sistem", [[n("a","Admin menetapkan kapasitas","start")],[n("b","Batas alamat, berat, wilayah, petugas, kendaraan")],[n("c","Warga memilih tanggal")],[n("d","Hitung kapasitas terpakai")],[n("e","Kapasitas tersedia?","decision")],[n("f","Tawarkan tanggal lain")],[n("g","Simpan pengajuan pada tanggal pilihan")],[n("h","Admin meninjau dan menjadwalkan")],[n("i","Perubahan kapasitas/jadwal?","decision")],[n("j","Kirim pengingat perubahan"),n("k","Pertahankan jadwal")],[n("l","Selesai","end")]], chain(["a","b","c","d","e"])+[Edge("e","f","Tidak"),Edge("f","c"),Edge("e","g","Ya"),Edge("g","h"),Edge("h","i"),Edge("i","j","Ya"),Edge("i","k","Tidak"),Edge("j","l"),Edge("k","l")], "Perkiraan berat hanya untuk kapasitas operasional dan tidak menjadi dasar nilai saldo."),
]


def wrap(c: canvas.Canvas, text: str, x: float, y: float, width: float, height: float, style=NODE_STYLE) -> None:
    para = Paragraph(text.replace("\n", "<br/>"), style)
    _, ph = para.wrap(width, height)
    para.drawOn(c, x, y + (height - ph) / 2)


def node_size(kind: NodeType, count_on_level: int) -> tuple[float, float]:
    available = W - 5.0 * cm
    width = min(4.1 * cm, (available - max(0, count_on_level - 1) * .85 * cm) / count_on_level)
    return width, .92 * cm if kind != "decision" else 1.25 * cm


def draw_node(c: canvas.Canvas, node: Node, x: float, y: float, w: float, h: float) -> None:
    c.saveState()
    if node.kind in ("start", "end"):
        c.setFillColor(GREEN if node.kind == "start" else TEAL)
        c.setStrokeColor(GREEN)
        c.roundRect(x, y, w, h, h / 2, fill=1, stroke=1)
        style = ParagraphStyle("white", parent=NODE_BOLD, textColor=WHITE)
        wrap(c, node.label, x + 7, y + 2, w - 14, h - 4, style)
    elif node.kind == "decision":
        c.setFillColor(colors.HexColor("#FFF6DF"))
        c.setStrokeColor(GOLD)
        path = c.beginPath()
        path.moveTo(x + w / 2, y + h)
        path.lineTo(x + w, y + h / 2)
        path.lineTo(x + w / 2, y)
        path.lineTo(x, y + h / 2)
        path.close()
        c.drawPath(path, fill=1, stroke=1)
        wrap(c, node.label, x + w * .18, y + h * .12, w * .64, h * .76, NODE_BOLD)
    elif node.kind == "data":
        c.setFillColor(colors.HexColor("#E8F2F7"))
        c.setStrokeColor(BLUE)
        skew = .28 * cm
        path = c.beginPath()
        path.moveTo(x + skew, y + h)
        path.lineTo(x + w, y + h)
        path.lineTo(x + w - skew, y)
        path.lineTo(x, y)
        path.close()
        c.drawPath(path, fill=1, stroke=1)
        wrap(c, node.label, x + 8, y + 2, w - 16, h - 4)
    elif node.kind == "document":
        c.setFillColor(colors.HexColor("#F1ECF8"))
        c.setStrokeColor(colors.HexColor("#77618F"))
        c.roundRect(x, y + .12 * cm, w, h - .12 * cm, .12 * cm, fill=1, stroke=1)
        c.bezier(x, y + .16 * cm, x + w * .25, y - .04 * cm, x + w * .7, y + .34 * cm, x + w, y + .1 * cm)
        wrap(c, node.label, x + 7, y + 3, w - 14, h - 6)
    else:
        c.setFillColor(PALE)
        c.setStrokeColor(TEAL)
        c.roundRect(x, y, w, h, .16 * cm, fill=1, stroke=1)
        wrap(c, node.label, x + 7, y + 2, w - 14, h - 4)
    c.restoreState()


def draw_routed_arrow(
    c: canvas.Canvas,
    points: list[tuple[float, float]],
    label: str = "",
    label_segment: int = 0,
) -> None:
    c.saveState()
    c.setStrokeColor(MUTED)
    c.setFillColor(MUTED)
    c.setLineWidth(.75)
    path = c.beginPath()
    path.moveTo(*points[0])
    for point in points[1:]:
        path.lineTo(*point)
    c.drawPath(path, fill=0, stroke=1)

    (px, py), (x2, y2) = points[-2], points[-1]
    import math
    angle = math.atan2(y2 - py, x2 - px)
    size = 4.5
    c.line(x2, y2, x2 - size * math.cos(angle - .52), y2 - size * math.sin(angle - .52))
    c.line(x2, y2, x2 - size * math.cos(angle + .52), y2 - size * math.sin(angle + .52))

    c.restoreState()


def route_edge(
    c: canvas.Canvas,
    edge: Edge,
    positions: dict[str, tuple[float, float, float, float]],
    nodes: dict[str, Node],
    loop_index: int,
) -> None:
    sx, sy, sw, sh = positions[edge.source]
    tx, ty, tw, th = positions[edge.target]
    source = nodes[edge.source]
    source_center_x = sx + sw / 2
    target_center_x = tx + tw / 2

    if ty < sy:
        start = (source_center_x, sy)
        end = (target_center_x, ty + th)
        if source.kind == "decision" and abs(source_center_x - target_center_x) > .2 * cm:
            branch_y = sy - .58 * cm
            points = [start, (source_center_x, branch_y), (target_center_x, branch_y), end]
            draw_routed_arrow(c, points, edge.label, 1)
            return
        if abs(source_center_x - target_center_x) > .2 * cm:
            middle_y = (sy + ty + th) / 2
            points = [start, (source_center_x, middle_y), (target_center_x, middle_y), end]
            draw_routed_arrow(c, points, edge.label, 1)
            return
        draw_routed_arrow(c, [start, end], edge.label)
        return

    use_left_lane = loop_index % 2 == 0
    lane_offset = (loop_index // 2) * .28 * cm
    lane_x = 1.25 * cm + lane_offset if use_left_lane else W - 1.25 * cm - lane_offset
    start_x = sx if use_left_lane else sx + sw
    end_x = tx if use_left_lane else tx + tw
    start = (start_x, sy + sh / 2)
    end = (end_x, ty + th / 2)
    points = [start, (lane_x, start[1]), (lane_x, end[1]), end]
    draw_routed_arrow(c, points, edge.label, 1)


def draw_header(c: canvas.Canvas, diagram: Diagram, number: int, page: int) -> None:
    c.setFillColor(GREEN)
    c.rect(0, H - 1.25 * cm, W, 1.25 * cm, fill=1, stroke=0)
    c.setFillColor(WHITE)
    c.setFont(BOLD, 8)
    c.drawString(1.4*cm, H-.78*cm, "BANK SAMPAH DIGITAL SINDANGHEULA")
    c.setFont(FONT, 7.5)
    c.drawRightString(W-1.4*cm, H-.78*cm, diagram.category)
    c.setFillColor(GREEN)
    c.setFont(BOLD, 17)
    c.drawString(1.4*cm, H-2.1*cm, f"{number:02d}. {diagram.title}")
    c.setFillColor(MUTED)
    c.setFont(FONT, 8)
    c.drawString(1.4*cm, H-2.65*cm, f"Tujuan: {diagram.purpose}")
    c.drawString(1.4*cm, H-3.05*cm, f"Aktor: {diagram.actors}")
    c.setStrokeColor(LINE)
    c.line(1.4*cm, 1.05*cm, W-1.4*cm, 1.05*cm)
    c.setFont(FONT, 7)
    c.setFillColor(MUTED)
    c.drawString(1.4*cm, .62*cm, "Kumpulan Flowchart • Dokumen Pendamping Proposal")
    c.drawRightString(W-1.4*cm, .62*cm, str(page))


def layout_nodes(diagram: Diagram) -> dict[str, tuple[float, float, float, float]]:
    nodes = [node for level in diagram.levels for node in level]
    node_by_id = {node.id: node for node in nodes}
    order = {node.id: index for index, node in enumerate(nodes)}
    rank = {node.id: 0 for node in nodes}

    forward_edges = [
        edge
        for edge in diagram.edges
        if order[edge.target] > order[edge.source]
    ]
    for _ in nodes:
        changed = False
        for edge in forward_edges:
            candidate = rank[edge.source] + 1
            if candidate > rank[edge.target]:
                rank[edge.target] = candidate
                changed = True
        if not changed:
            break

    for node in nodes:
        if node.kind != "decision":
            continue
        targets = [edge.target for edge in forward_edges if edge.source == node.id]
        if len(targets) > 1:
            shared_rank = min(rank[target] for target in targets)
            for target in targets:
                rank[target] = shared_rank

    max_rank = max(rank.values(), default=0)
    top = H - 4.55 * cm
    bottom = 3.85 * cm
    step = (top - bottom) / max(1, max_rank)
    positions: dict[str, tuple[float, float, float, float]] = {}

    for current_rank in range(max_rank + 1):
        level = [node for node in nodes if rank[node.id] == current_rank]
        count = len(level)
        if count == 0:
            continue
        gap = 1.15 * cm
        widths = [node_size(node.kind, count)[0] for node in level]
        total = sum(widths) + gap * (count - 1)
        x = (W - total) / 2
        y_center = top - current_rank * step
        for node, width in zip(level, widths):
            _, height = node_size(node.kind, count)
            positions[node.id] = (x, y_center - height / 2, width, height)
            x += width + gap

    return positions


def draw_edge_label(
    c: canvas.Canvas,
    edge: Edge,
    positions: dict[str, tuple[float, float, float, float]],
    nodes: dict[str, Node],
) -> None:
    if not edge.label:
        return
    sx, sy, sw, sh = positions[edge.source]
    tx, ty, tw, th = positions[edge.target]
    source_center_x = sx + sw / 2
    target_center_x = tx + tw / 2
    if nodes[edge.source].kind == "decision" and ty < sy:
        label_x = source_center_x + (target_center_x - source_center_x) * .55
        label_y = sy - .3 * cm
    elif ty >= sy:
        label_x = 2.05 * cm if tx < sx else W - 2.05 * cm
        label_y = (sy + sh / 2 + ty + th / 2) / 2
    else:
        label_x = (source_center_x + target_center_x) / 2
        label_y = (sy + ty + th) / 2
    c.saveState()
    text_width = pdfmetrics.stringWidth(edge.label, BOLD, 7)
    c.setFillColor(WHITE)
    c.roundRect(label_x - text_width / 2 - 4, label_y - 6, text_width + 8, 12, 2, fill=1, stroke=0)
    c.setFillColor(GREEN)
    c.setFont(BOLD, 7)
    c.drawCentredString(label_x, label_y - 2.4, edge.label)
    c.restoreState()


def render_diagram(c: canvas.Canvas, diagram: Diagram, number: int, page: int) -> None:
    draw_header(c, diagram, number, page)
    pos = layout_nodes(diagram)
    nodes = {node.id: node for level in diagram.levels for node in level}
    loop_index = 0
    for edge in diagram.edges:
        if edge.source not in pos or edge.target not in pos:
            continue
        is_loopback = pos[edge.target][1] >= pos[edge.source][1]
        route_edge(c, edge, pos, nodes, loop_index)
        if is_loopback:
            loop_index += 1
    for level in diagram.levels:
        for node in level:
            x,y,w,h = pos[node.id]
            draw_node(c,node,x,y,w,h)
    for edge in diagram.edges:
        if edge.source in pos and edge.target in pos:
            draw_edge_label(c, edge, pos, nodes)
    c.setFillColor(MINT)
    c.roundRect(2.0*cm, 1.65*cm, W-4.0*cm, .9*cm, .12*cm, fill=1, stroke=0)
    wrap(c, f"<b>Catatan:</b> {diagram.note}", 2.25*cm, 1.75*cm, W-4.5*cm, .65*cm, NOTE_STYLE)


def render_cover(c: canvas.Canvas) -> None:
    c.setFillColor(GREEN)
    c.rect(0,0,W,H,fill=1,stroke=0)
    c.setFillColor(TEAL)
    c.circle(W*.9,H*.88,5.5*cm,fill=1,stroke=0)
    c.setFillColor(colors.HexColor("#0E473B"))
    c.circle(W*.06,H*.05,4.5*cm,fill=1,stroke=0)
    c.setFillColor(GOLD)
    c.roundRect(1.7*cm,H-2.7*cm,5.4*cm,.65*cm,.15*cm,fill=1,stroke=0)
    c.setFillColor(GREEN)
    c.setFont(BOLD,9)
    c.drawCentredString(4.4*cm,H-2.48*cm,"DOKUMEN PENDAMPING  •  2026")
    c.setFillColor(WHITE)
    c.setFont(BOLD,30)
    c.drawString(1.7*cm,H-5.1*cm,"KUMPULAN FLOWCHART")
    c.setFont(BOLD,23)
    c.drawString(1.7*cm,H-6.2*cm,"BANK SAMPAH DIGITAL")
    c.drawString(1.7*cm,H-7.1*cm,"DESA SINDANGHEULA")
    c.setFillColor(MINT)
    c.setFont(FONT,12)
    c.drawString(1.72*cm,H-8.0*cm,"36 diagram • pengembangan • sistem • penggunaan • administrasi")
    c.setStrokeColor(GOLD); c.setLineWidth(3); c.line(1.7*cm,H-8.6*cm,10*cm,H-8.6*cm)
    c.setFillColor(WHITE); c.setFont(FONT,10)
    c.drawString(1.7*cm,2.2*cm,"Desa Sindangheula • Kecamatan Pabuaran • Kabupaten Serang • Provinsi Banten")


def render_toc(c: canvas.Canvas, items: list[tuple[int, Diagram]], page: int, title: str) -> None:
    c.setFillColor(GREEN); c.rect(0,H-1.25*cm,W,1.25*cm,fill=1,stroke=0)
    c.setFillColor(WHITE); c.setFont(BOLD,8); c.drawString(1.4*cm,H-.78*cm,"BANK SAMPAH DIGITAL SINDANGHEULA")
    c.setFillColor(GREEN); c.setFont(BOLD,20); c.drawString(1.4*cm,H-2.25*cm,title)
    y=H-3.2*cm
    current=""
    for number,d in items:
        if d.category!=current:
            current=d.category
            c.setFillColor(TEAL); c.setFont(BOLD,8); c.drawString(1.4*cm,y,current); y-=.48*cm
        c.setFillColor(INK); c.setFont(FONT,8.3)
        c.drawString(1.65*cm,y,f"{number:02d}. {d.title}")
        c.setFillColor(MUTED); c.drawRightString(W-1.5*cm,y,str(number+4))
        y-=.46*cm
    c.setStrokeColor(LINE); c.line(1.4*cm,1.05*cm,W-1.4*cm,1.05*cm)
    c.setFillColor(MUTED); c.setFont(FONT,7); c.drawRightString(W-1.4*cm,.62*cm,str(page))


def render_legend(c: canvas.Canvas, page: int) -> None:
    c.setFillColor(GREEN); c.rect(0,H-1.25*cm,W,1.25*cm,fill=1,stroke=0)
    c.setFillColor(WHITE); c.setFont(BOLD,8); c.drawString(1.4*cm,H-.78*cm,"BANK SAMPAH DIGITAL SINDANGHEULA")
    c.setFillColor(GREEN); c.setFont(BOLD,20); c.drawString(1.4*cm,H-2.25*cm,"Cara Membaca Diagram")
    examples=[n("s","Mulai / titik awal","start"),n("p","Proses atau aktivitas"),n("d","Keputusan / pertanyaan","decision"),n("x","Data / pihak eksternal","data"),n("r","Dokumen / laporan","document"),n("e","Selesai / hasil akhir","end")]
    y=H-4.1*cm
    for idx,node in enumerate(examples):
        col=idx%3; row=idx//3; x=1.6*cm+col*8.9*cm; yy=y-row*3.2*cm
        draw_node(c,node,x,yy,5.2*cm,1.35*cm if node.kind!="decision" else 1.65*cm)
    c.setFillColor(MINT); c.roundRect(1.6*cm,2.2*cm,W-3.2*cm,2.2*cm,.2*cm,fill=1,stroke=0)
    notes="Panah menunjukkan urutan proses. Label Ya/Tidak atau status pada panah menunjukkan hasil keputusan. Diagram merupakan gambaran bisnis tingkat tinggi; rincian validasi teknis mengikuti spesifikasi aplikasi dan aturan bisnis proposal."
    wrap(c,notes,2*cm,2.55*cm,W-4*cm,1.5*cm,ParagraphStyle("legend",parent=NOTE_STYLE,fontSize=9,leading=13))
    c.setStrokeColor(LINE); c.line(1.4*cm,1.05*cm,W-1.4*cm,1.05*cm)
    c.setFillColor(MUTED); c.setFont(FONT,7); c.drawRightString(W-1.4*cm,.62*cm,str(page))


def validate_diagrams() -> None:
    if len(DIAGRAMS) != 36:
        raise ValueError(f"Expected 36 diagrams, found {len(DIAGRAMS)}")
    for diagram in DIAGRAMS:
        nodes = {node.id: node for level in diagram.levels for node in level}
        outgoing: dict[str, int] = {}
        for edge in diagram.edges:
            outgoing[edge.source] = outgoing.get(edge.source, 0) + 1
        invalid_questions = [
            node.label
            for node in nodes.values()
            if node.label.rstrip().endswith("?") and node.kind != "decision"
        ]
        invalid_decisions = [
            node.label
            for node in nodes.values()
            if node.kind == "decision" and outgoing.get(node.id, 0) < 2
        ]
        if invalid_questions or invalid_decisions:
            raise ValueError(
                f"Diagram '{diagram.title}' has invalid decision symbols: "
                f"{invalid_questions + invalid_decisions}"
            )


def build() -> None:
    validate_diagrams()
    c=canvas.Canvas(str(PDF_PATH),pagesize=(W,H),pageCompression=1)
    c.setTitle("Kumpulan Flowchart Bank Sampah Digital Desa Sindangheula")
    c.setAuthor("Desa Sindangheula")
    render_cover(c); c.showPage()
    render_toc(c,list(enumerate(DIAGRAMS[:12],1)),2,"Daftar Diagram • Bagian 1"); c.showPage()
    render_toc(c,list(enumerate(DIAGRAMS[12:24],13)),3,"Daftar Diagram • Bagian 2"); c.showPage()
    render_toc(c,list(enumerate(DIAGRAMS[24:],25)),4,"Daftar Diagram • Bagian 3"); c.showPage()
    render_legend(c,5); c.showPage()
    for idx,d in enumerate(DIAGRAMS,1):
        render_diagram(c,d,idx,idx+5)
        c.showPage()
    c.save()
    print(PDF_PATH)


if __name__ == "__main__":
    build()
