from __future__ import annotations

from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_JUSTIFY, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import cm
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    HRFlowable,
    KeepTogether,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfbase import pdfmetrics

OUT = Path(__file__).resolve().parent
PDF_PATH = OUT / "Pengajuan_Bank_Sampah_Digital_Desa_Sindangheula.pdf"

PAGE_W, PAGE_H = A4
GREEN = colors.HexColor("#145B4A")
TEAL = colors.HexColor("#168C78")
MINT = colors.HexColor("#DDF1EA")
PALE = colors.HexColor("#F3F8F5")
INK = colors.HexColor("#18302A")
MUTED = colors.HexColor("#61736D")
GOLD = colors.HexColor("#D6A84B")
WHITE = colors.white
LINE = colors.HexColor("#C9DDD6")


def register_fonts() -> tuple[str, str]:
    candidates = [
        (r"C:\Windows\Fonts\aptos.ttf", r"C:\Windows\Fonts\aptosbd.ttf"),
        (r"C:\Windows\Fonts\calibri.ttf", r"C:\Windows\Fonts\calibrib.ttf"),
        (r"C:\Windows\Fonts\arial.ttf", r"C:\Windows\Fonts\arialbd.ttf"),
    ]
    for regular, bold in candidates:
        if Path(regular).exists() and Path(bold).exists():
            pdfmetrics.registerFont(TTFont("DocRegular", regular))
            pdfmetrics.registerFont(TTFont("DocBold", bold))
            return "DocRegular", "DocBold"
    return "Helvetica", "Helvetica-Bold"


FONT, FONT_BOLD = register_fonts()

styles = getSampleStyleSheet()
BODY = ParagraphStyle(
    "Body", fontName=FONT, fontSize=8.7, leading=12.4, textColor=INK,
    alignment=TA_JUSTIFY, spaceAfter=5,
)
LEAD = ParagraphStyle(
    "Lead", parent=BODY, fontSize=10, leading=14.2, textColor=GREEN,
    alignment=TA_LEFT, spaceAfter=8,
)
H1 = ParagraphStyle(
    "H1", fontName=FONT_BOLD, fontSize=17, leading=20.5, textColor=GREEN,
    spaceBefore=3, spaceAfter=8, keepWithNext=True,
)
H2 = ParagraphStyle(
    "H2", fontName=FONT_BOLD, fontSize=12, leading=15, textColor=TEAL,
    spaceBefore=7, spaceAfter=4, keepWithNext=True,
)
H3 = ParagraphStyle(
    "H3", fontName=FONT_BOLD, fontSize=10, leading=13, textColor=INK,
    spaceBefore=6, spaceAfter=3, keepWithNext=True,
)
BULLET = ParagraphStyle(
    "Bullet", parent=BODY, leftIndent=13, firstLineIndent=-9, bulletIndent=2,
    spaceAfter=2, alignment=TA_LEFT,
)
SMALL = ParagraphStyle(
    "Small", fontName=FONT, fontSize=7.8, leading=10.2, textColor=MUTED,
)
TABLE_HEAD = ParagraphStyle(
    "TableHead", fontName=FONT_BOLD, fontSize=8.2, leading=10, textColor=WHITE,
    alignment=TA_LEFT,
)
TABLE_BODY = ParagraphStyle(
    "TableBody", fontName=FONT, fontSize=8.2, leading=10.5, textColor=INK,
)
CALLOUT = ParagraphStyle(
    "Callout", fontName=FONT_BOLD, fontSize=10, leading=14, textColor=GREEN,
    alignment=TA_CENTER,
)


def p(text: str, style=BODY) -> Paragraph:
    return Paragraph(text, style)


def bullets(items: list[str]) -> list[Paragraph]:
    return [Paragraph(f"• {escape(item)}", BULLET) for item in items]


def numbered(items: list[str]) -> list[Paragraph]:
    return [Paragraph(f"{i}. {escape(item)}", BULLET) for i, item in enumerate(items, 1)]


def section(title: str) -> list:
    return [Spacer(1, 4), HRFlowable(width="100%", thickness=1, color=LINE), Spacer(1, 7), p(title, H1)]


def subsection(title: str) -> Paragraph:
    return p(title, H2)


def table(headers: list[str], rows: list[list[str]], widths=None) -> Table:
    data = [[p(h, TABLE_HEAD) for h in headers]] + [[p(str(c), TABLE_BODY) for c in row] for row in rows]
    t = Table(data, colWidths=widths, repeatRows=1, hAlign="LEFT")
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, 0), GREEN),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("BACKGROUND", (0, 1), (-1, -1), colors.white),
        ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, PALE]),
        ("GRID", (0, 0), (-1, -1), 0.35, LINE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return t


class ProposalDoc(BaseDocTemplate):
    def __init__(self, filename: str):
        super().__init__(filename, pagesize=A4, rightMargin=2.0*cm, leftMargin=2.1*cm,
                         topMargin=2.55*cm, bottomMargin=2.35*cm,
                         title="Pengajuan Bank Sampah Digital Desa Sindangheula",
                         author="Desa Sindangheula")
        cover_frame = Frame(0, 0, PAGE_W, PAGE_H, id="cover", leftPadding=0, rightPadding=0,
                            topPadding=0, bottomPadding=0)
        body_frame = Frame(self.leftMargin, self.bottomMargin, self.width, self.height, id="body")
        self.addPageTemplates([
            PageTemplate(id="Cover", frames=[cover_frame], onPage=self.cover_page),
            PageTemplate(id="Body", frames=[body_frame], onPage=self.body_page),
        ])

    def cover_page(self, canvas, doc):
        canvas.saveState()
        canvas.setFillColor(GREEN)
        canvas.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
        canvas.setFillColor(TEAL)
        canvas.circle(PAGE_W * .88, PAGE_H * .88, 5.2*cm, fill=1, stroke=0)
        canvas.setFillColor(colors.HexColor("#0E473B"))
        canvas.circle(PAGE_W * .08, PAGE_H * .06, 4.6*cm, fill=1, stroke=0)
        canvas.setStrokeColor(colors.Color(1, 1, 1, alpha=.20))
        canvas.setLineWidth(1)
        for r in (1.2, 2.0, 2.8):
            canvas.circle(PAGE_W*.88, PAGE_H*.88, r*cm, fill=0, stroke=1)
        canvas.setFillColor(GOLD)
        canvas.roundRect(1.8*cm, PAGE_H-3.0*cm, 4.7*cm, .65*cm, .16*cm, fill=1, stroke=0)
        canvas.setFillColor(GREEN)
        canvas.setFont(FONT_BOLD, 9)
        canvas.drawCentredString(4.15*cm, PAGE_H-2.78*cm, "DOKUMEN PENGAJUAN  •  2026")
        canvas.setFillColor(WHITE)
        canvas.setFont(FONT_BOLD, 29)
        y = PAGE_H - 6.2*cm
        for line in ["SISTEM INFORMASI", "BANK SAMPAH DIGITAL", "DESA SINDANGHEULA"]:
            canvas.drawString(1.8*cm, y, line)
            y -= 1.05*cm
        canvas.setFillColor(MINT)
        canvas.setFont(FONT, 13)
        canvas.drawString(1.85*cm, y-.15*cm, "Berbasis Web Mobile-First")
        canvas.setStrokeColor(GOLD)
        canvas.setLineWidth(3)
        canvas.line(1.85*cm, y-.75*cm, 8.3*cm, y-.75*cm)
        canvas.setFillColor(WHITE)
        canvas.setFont(FONT, 10.5)
        text = canvas.beginText(1.85*cm, 5.4*cm)
        text.setLeading(16)
        for line in ["Desa Sindangheula", "Kecamatan Pabuaran", "Kabupaten Serang, Provinsi Banten"]:
            text.textLine(line)
        canvas.drawText(text)
        canvas.setFont(FONT_BOLD, 9)
        canvas.setFillColor(MINT)
        canvas.drawString(1.85*cm, 2.1*cm, "TRANSPARAN  •  MUDAH DIAKSES  •  BERKELANJUTAN")
        canvas.restoreState()

    def body_page(self, canvas, doc):
        canvas.saveState()
        canvas.setFillColor(GREEN)
        canvas.rect(0, PAGE_H-1.25*cm, PAGE_W, 1.25*cm, fill=1, stroke=0)
        canvas.setFillColor(WHITE)
        canvas.setFont(FONT_BOLD, 8.5)
        canvas.drawString(1.9*cm, PAGE_H-.78*cm, "BANK SAMPAH DIGITAL SINDANGHEULA")
        canvas.setFont(FONT, 7.5)
        canvas.drawRightString(PAGE_W-1.7*cm, PAGE_H-.78*cm, "DOKUMEN PENGAJUAN PENGEMBANGAN")
        canvas.setStrokeColor(LINE)
        canvas.line(1.9*cm, 1.25*cm, PAGE_W-1.7*cm, 1.25*cm)
        canvas.setFillColor(MUTED)
        canvas.setFont(FONT, 8)
        canvas.drawString(1.9*cm, .75*cm, "Desa Sindangheula • Kecamatan Pabuaran • Kabupaten Serang")
        canvas.drawRightString(PAGE_W-1.7*cm, .75*cm, str(doc.page-1))
        canvas.restoreState()


def build_story() -> list:
    s: list = []
    s += [Spacer(1, PAGE_H), NextPageTemplate("Body"), PageBreak()]
    s += section("Ringkasan Eksekutif")
    s += [p("Bank Sampah Digital Sindangheula adalah sistem informasi berbasis web mobile-first untuk mengelola nasabah, petugas, jenis dan harga sampah, transaksi penimbangan, saldo rupiah, penjemputan, pencairan tunai, penukaran saldo dengan sembako, notifikasi, laporan, dan audit log.", LEAD)]
    s += [p("Sistem dirancang agar dapat digunakan melalui telepon pintar, tablet, dan komputer tanpa mewajibkan pemasangan aplikasi Android. Pengembangan diprioritaskan pada kemudahan penggunaan, transparansi transaksi, ketepatan saldo, keamanan data, dan kesesuaian dengan kondisi operasional di Desa Sindangheula.")]
    s += [Table([[p("FOKUS", TABLE_HEAD), p("NILAI UTAMA", TABLE_HEAD), p("PLATFORM", TABLE_HEAD)],
                [p("Pengelolaan bank sampah", TABLE_BODY), p("Transparansi saldo rupiah", TABLE_BODY), p("Web responsif mobile-first", TABLE_BODY)]],
               colWidths=[5.2*cm]*3, style=TableStyle([("BACKGROUND",(0,0),(-1,0),GREEN),("GRID",(0,0),(-1,-1),.4,LINE),("VALIGN",(0,0),(-1,-1),"TOP"),("LEFTPADDING",(0,0),(-1,-1),8),("RIGHTPADDING",(0,0),(-1,-1),8),("TOPPADDING",(0,0),(-1,-1),8),("BOTTOMPADDING",(0,0),(-1,-1),8)]))]

    s += section("1. Nama Aplikasi")
    s += [p("Nama resmi sistem yang diusulkan adalah <b>Sistem Informasi Bank Sampah Digital Desa Sindangheula Berbasis Web Mobile-First</b>. Nama singkat yang digunakan dalam tampilan dan komunikasi masyarakat adalah <b>Bank Sampah Digital Sindangheula</b>.")]
    s += [p("Aplikasi dikembangkan sebagai web application responsif yang dapat digunakan melalui smartphone, tablet, laptop, dan komputer, serta sebagai Progressive Web App installable dengan cache terbatas untuk halaman informasi umum.")]

    s += section("2. Latar Belakang")
    paras = [
        "Pengelolaan bank sampah di Desa Sindangheula masih menghadapi beberapa permasalahan, antara lain pencatatan data nasabah dan transaksi yang belum terorganisasi, belum tersedianya layanan yang mudah diakses masyarakat, risiko kesalahan pencatatan, rendahnya transparansi saldo warga, serta belum adanya sistem digital yang menghubungkan warga dengan petugas bank sampah.",
        "Pencatatan manual dapat menyebabkan data sulit dicari, riwayat setoran tidak tersimpan secara terstruktur, perhitungan nilai sampah berisiko salah, perubahan harga sulit diinformasikan, permintaan penjemputan belum dikelola secara terpusat, dan penyusunan laporan membutuhkan waktu lebih lama.",
        "Berdasarkan kondisi tersebut, diusulkan Bank Sampah Digital Sindangheula untuk membantu pengelolaan data nasabah, harga sampah, setoran, hasil penimbangan, saldo rupiah, penjemputan, pencairan tunai, penukaran saldo dengan sembako, pengumuman, dan laporan.",
        "Sistem ini menjadi bagian dari penguatan kelompok pengelola sampah desa agar memiliki tata kelola yang lebih profesional, transparan, tertib, dan berkelanjutan. Sistem juga menyediakan sumber data untuk mengevaluasi perkembangan program pengelolaan sampah di Desa Sindangheula.",
        "Selain mendukung pelayanan dan tata kelola bank sampah, keberadaan sistem ini juga diharapkan dapat membantu menyediakan data mengenai jumlah dan jenis sampah plastik yang berhasil dikumpulkan dari masyarakat. Informasi tersebut dapat dimanfaatkan oleh pengelola sebagai bahan pendukung dalam merencanakan ketersediaan bahan baku untuk kegiatan pemanfaatan limbah plastik menjadi paving block. Dengan demikian, Bank Sampah Digital Sindangheula tidak hanya mendukung proses pengumpulan dan pencatatan sampah, tetapi juga menjadi bagian awal dari upaya pemanfaatan kembali sampah plastik secara berkelanjutan. Namun, aplikasi ini tetap difokuskan pada pengelolaan bank sampah dan tidak mencakup proses produksi paving block.",
    ]
    s += [p(x) for x in paras]

    s += section("3. Identifikasi Permasalahan")
    s += numbered([
        "Pencatatan nasabah belum tersimpan dalam satu sistem.", "Pencatatan setoran dan hasil penimbangan berisiko hilang atau rusak.",
        "Perhitungan nilai sampah masih dilakukan secara manual.", "Warga belum dapat melihat saldo dan riwayat setoran secara mandiri.",
        "Riwayat perubahan harga belum terdokumentasi.", "Permintaan penjemputan belum memiliki alur pemeriksaan dan penjadwalan yang jelas.",
        "Pencairan saldo belum memiliki proses persetujuan dan bukti pembayaran yang terstruktur.", "Penukaran saldo dengan sembako belum memiliki pencatatan pengajuan dan penyerahan.",
        "Koreksi transaksi belum dilengkapi riwayat perubahan.", "Laporan transaksi, saldo, dan aktivitas belum dapat dihasilkan secara cepat.",
        "Belum terdapat audit log yang memadai.", "Belum terdapat pembagian hak akses yang jelas.",
    ])

    s += section("4. Tujuan Pengembangan")
    s += numbered([
        "Mendigitalisasi proses pencatatan bank sampah.", "Mempermudah pengelolaan data nasabah dan petugas.",
        "Mempermudah warga melihat saldo dan riwayat setoran.", "Memberikan layanan pengajuan penjemputan sampah.",
        "Mengurangi kesalahan perhitungan nilai sampah.", "Meningkatkan transparansi saldo warga.",
        "Mempermudah petugas mencatat hasil penimbangan.", "Mempermudah admin mengelola harga, transaksi, saldo, dan laporan.",
        "Mendokumentasikan pencairan saldo secara tertib.", "Menyediakan alternatif penggunaan saldo dalam bentuk sembako.",
        "Menyediakan data untuk evaluasi program.", "Mendorong masyarakat memilah dan menyetorkan sampah.",
        "Memperkuat tata kelola kelompok bank sampah desa.", "Mempermudah penyusunan laporan kepada pemerintah desa dan pihak terkait.",
        "Meningkatkan akuntabilitas melalui riwayat transaksi dan audit log.",
        "Menyediakan informasi mengenai jumlah dan jenis sampah plastik yang terkumpul sebagai data pendukung bagi pengelola dalam merencanakan pemanfaatan limbah plastik sebagai bahan baku paving block.",
    ])

    s += section("5. Manfaat Aplikasi")
    groups = {
        "5.1 Bagi warga": ["Mengetahui harga sampah yang berlaku.", "Mengetahui saldo secara transparan.", "Melihat riwayat setoran dan pencairan.", "Mengajukan penjemputan dari rumah.", "Mengajukan pencairan saldo.", "Menukar saldo dengan paket sembako.", "Memperoleh jadwal dan pengumuman."],
        "5.2 Bagi petugas": ["Mempercepat pencarian nasabah.", "Mempermudah pencatatan penimbangan.", "Menghitung nilai sampah secara otomatis.", "Mengelola penjemputan.", "Mendokumentasikan pembayaran dan penyerahan sembako.", "Melihat kegiatan harian."],
        "5.3 Bagi admin dan pengelola": ["Mengelola data melalui satu sistem.", "Memantau transaksi dan berat sampah.", "Mengetahui total kewajiban saldo warga.", "Mengawasi pencairan dan penukaran.", "Melihat koreksi dan aktivitas pengguna.", "Menghasilkan laporan secara cepat."],
        "5.4 Bagi pemerintah desa": ["Memperoleh data perkembangan program.", "Memantau partisipasi warga.", "Mendukung keputusan berbasis data.", "Menilai efektivitas program.", "Meningkatkan transparansi pelayanan."],
    }
    for title, items in groups.items():
        s += [subsection(title)] + bullets(items)

    s += section("6. Ruang Lingkup Aplikasi")
    s += [p("Aplikasi difokuskan pada pengelolaan Bank Sampah Digital Desa Sindangheula.")]
    s += bullets(["Akun warga, petugas, admin, dan verifikasi pendaftaran.", "Wilayah, jenis sampah, harga, setoran, transaksi, dan saldo rupiah.", "Penjemputan, pencairan tunai, dan penukaran saldo dengan sembako.", "Notifikasi, pengumuman, dashboard, laporan, ekspor data, dan audit log."])
    s += [p("Aplikasi tidak mencakup pengolahan sampah menjadi paving block, penjualan produk hasil pengolahan, pengelolaan bantuan sosial di luar saldo warga, akuntansi desa secara menyeluruh, pengelolaan armada terperinci, ataupun integrasi timbangan digital.")]

    s += section("7. Konsep Nilai Sampah")
    s += [p("Aplikasi menggunakan saldo rupiah langsung tanpa sistem poin. Setiap jenis sampah memiliki harga berdasarkan kilogram atau satuan lain yang ditentukan pengelola."), p("<b>Nilai setoran = berat sampah × harga per satuan</b>", CALLOUT)]
    s += [table(["Komponen", "Contoh"], [["Jenis", "Botol plastik PET"], ["Berat", "3 kilogram"], ["Harga", "Rp3.000/kg"], ["Nilai setoran", "Rp9.000"]], [6*cm, 9.6*cm])]
    s += [p("Setelah transaksi dikonfirmasi, saldo warga bertambah. Saldo dapat disimpan, dicairkan tunai, atau ditukar dengan paket sembako sesuai baseline fitur yang disetujui.")]

    s += section("8. Konsep Saldo dan Mutasi")
    s += [p("Saldo tidak boleh berupa angka yang dapat diubah langsung. Setiap perubahan harus berasal dari mutasi dengan sumber dan riwayat yang jelas. Agar mudah dipahami warga dan pengelola, dokumen ini menggunakan istilah saldo masuk dan saldo keluar, bukan istilah kredit dan debit.")]
    s += [subsection("8.1 Istilah saldo")]
    s += [table(
        ["Istilah", "Pengertian"],
        [
            ["Saldo masuk", "Seluruh nilai yang menambah saldo warga, terutama hasil setoran sampah yang telah dikonfirmasi serta koreksi penambahan yang sah."],
            ["Saldo keluar", "Seluruh nilai yang sudah digunakan dan diselesaikan, seperti pencairan tunai, penukaran sembako, atau koreksi pengurangan."],
            ["Saldo tertahan", "Saldo yang sedang digunakan untuk pengajuan pencairan atau penukaran, tetapi prosesnya belum selesai. Saldo ini tidak dapat dipakai untuk pengajuan lain."],
            ["Saldo tersedia", "Saldo yang masih dapat digunakan warga setelah total saldo masuk dikurangi total saldo keluar dan saldo tertahan."],
            ["Mutasi saldo", "Catatan setiap perubahan saldo yang memuat nilai, jenis perubahan, sumber transaksi, waktu, dan saldo setelah perubahan."],
        ],
        [4.1*cm, 11.5*cm],
    )]
    s += [subsection("8.2 Sumber perubahan saldo")]
    s += bullets(["Saldo masuk dari transaksi setoran.", "Saldo keluar dari pencairan tunai.", "Saldo keluar dari penukaran sembako.", "Koreksi penambahan atau pengurangan.", "Penahanan dan pelepasan saldo.", "Pembalikan transaksi."])
    s += [p("<b>Saldo tersedia = total saldo masuk − total saldo keluar − saldo tertahan</b>", CALLOUT)]
    s += [subsection("8.3 Contoh perhitungan")]
    s += [p("Apabila total saldo masuk warga sebesar Rp50.000, total saldo keluar sebesar Rp10.000, dan saldo yang sedang ditahan sebesar Rp15.000, maka saldo yang masih dapat digunakan adalah Rp25.000.")]
    s += [p("<b>Rp50.000 − Rp10.000 − Rp15.000 = Rp25.000</b>", CALLOUT)]
    s += [subsection("8.4 Ketentuan saldo")]
    s += bullets(["Saldo tidak boleh negatif.", "Saldo tertahan tidak dapat digunakan untuk pengajuan lain.", "Saldo dikembalikan jika pengajuan ditolak atau dibatalkan.", "Setiap mutasi memiliki nomor dan referensi unik.", "Mutasi final tidak dihapus langsung; koreksi wajib memiliki alasan dan audit log."])

    s += section("9. Pengguna Aplikasi")
    roles = {
        "9.1 Warga atau nasabah": ["Registrasi dan login.", "Profil, nomor, dan QR nasabah.", "Harga, panduan, jadwal, riwayat, saldo, notifikasi, dan pengumuman.", "Pengajuan penjemputan, pencairan tunai, dan penukaran sembako."],
        "9.2 Petugas bank sampah": ["Mencari atau memindai nasabah.", "Mencatat setoran dan hasil penimbangan.", "Memproses penjemputan.", "Mencatat pembayaran pencairan dan penyerahan sembako.", "Melihat transaksi harian dan aktivitas sendiri."],
        "9.3 Admin atau pengelola": ["Mengelola pengguna, wilayah, sampah, harga, transaksi, saldo, dan jadwal.", "Menyetujui pencairan dan penukaran.", "Membuat pengumuman, laporan, ekspor, dan melihat audit log."],
        "9.4 Bendahara atau petugas pembayaran": ["Melihat pencairan yang disetujui.", "Memverifikasi penerima.", "Menyerahkan uang, mengunggah bukti, dan menyelesaikan pembayaran."],
        "9.5 Superadmin": ["Mengelola admin, role, permission, dan status akun.", "Tidak boleh mengubah saldo tanpa mekanisme koreksi tercatat."],
    }
    for title, items in roles.items(): s += [subsection(title)] + bullets(items)

    s += section("10. Fitur Warga")
    features = {
        "10.1 Registrasi": "Warga mengisi nama, telepon, alamat, dusun, RT/RW, identitas bila diperlukan, kata sandi, serta persetujuan ketentuan. Akun berstatus menunggu verifikasi, aktif, ditolak, atau dinonaktifkan.",
        "10.2 Login": "Login memakai nomor telepon dan kata sandi, dilengkapi ubah kata sandi setelah login, logout, pembatasan percobaan, dan pengelolaan sesi. Pemulihan akses dibantu admin setelah verifikasi identitas warga.",
        "10.3 Profil nasabah": "Memuat identitas dasar, nomor nasabah, alamat, wilayah, tanggal bergabung, status akun, dan QR code.",
        "10.4 Nomor dan QR code": "QR mempercepat pencarian dan mengurangi salah pemilihan akun; petugas tetap mengonfirmasi nama warga.",
        "10.5 Dashboard warga": "Menampilkan saldo tersedia dan tertahan, berat, transaksi, nilai bulan berjalan, status pengajuan, jadwal, pengumuman, dan notifikasi.",
        "10.6 Daftar harga": "Menampilkan nama, kategori, harga, satuan, kondisi diterima, tanggal berlaku, pembaruan, dan status.",
        "10.7 Panduan pemilahan": "Berisi jenis diterima atau ditolak, cara membersihkan dan memisahkan, ketentuan kering dan kemasan, contoh foto, serta kontak pengelola.",
        "10.8 Pengajuan penjemputan": "Warga wajib mengisi foto, jenis, perkiraan jumlah dan berat, alamat, tanggal, dan catatan. Nilai saldo tetap berdasarkan penimbangan aktual.",
        "10.9 Status penjemputan": "Menunggu pemeriksaan, diterima, dijadwalkan, petugas menuju lokasi, telah dijemput, selesai, ditolak, atau dibatalkan. Penolakan wajib beralasan.",
        "10.10 Riwayat setoran": "Memuat nomor, waktu, lokasi, metode, petugas, detail jenis, berat, harga, subtotal, total, status, foto, dan catatan.",
        "10.11 Saldo": "Menampilkan saldo tersedia, tertahan, total masuk, total keluar, riwayat dan sumber mutasi, tanggal, serta saldo setelah mutasi.",
        "10.12 Pencairan tunai": "Sistem memeriksa nominal dan saldo, menahan saldo, menunggu persetujuan, mencatat pembayaran dan bukti, lalu memotong permanen setelah selesai.",
        "10.13 Penukaran sembako": "Warga memilih paket, saldo ditahan, admin memeriksa ketersediaan manual, petugas menyerahkan dan mengunggah bukti, kemudian saldo dipotong.",
        "10.14 Notifikasi dan pengingat": "Notifikasi dalam aplikasi mencakup akun, transaksi, saldo, penjemputan, pencairan, penukaran, harga, pengumuman, jadwal setor atau bank sampah keliling, perubahan jadwal, serta pengajuan yang mendekati kedaluwarsa. WhatsApp tersedia secara manual melalui tautan wa.me; aplikasi hanya membuka WhatsApp dan pengguna mengirim pesan sendiri.",
        "10.15 Estimasi nilai sebelum setor": "Warga dapat memilih jenis sampah dan memasukkan perkiraan berat untuk melihat estimasi nilai berdasarkan harga aktif. Estimasi hanya bersifat informasi dan tidak membentuk transaksi atau saldo; nilai akhir tetap mengikuti penimbangan aktual petugas.",
        "10.16 Edukasi kontekstual": "Petunjuk pemilahan ditampilkan sesuai jenis sampah dan tindakan pengguna, misalnya cara membersihkan botol PET, melipat kardus, memperbaiki foto yang buram, atau memahami alasan sampah tidak diterima.",
        "10.17 Riwayat koreksi untuk warga": "Warga dapat melihat nilai sebelum dan setelah koreksi, alasan, tanggal, serta dampaknya terhadap saldo tanpa membuka data internal yang sensitif.",
        "10.18 Jadwal Bank Sampah Keliling": "Warga dapat melihat titik pelayanan, RT/RW, tanggal, waktu, petugas, jenis sampah yang diterima, kapasitas, dan perubahan jadwal layanan keliling.",
    }
    for title, text in features.items(): s += [subsection(title), p(text)]
    s += [table(["Jenis Sampah", "Harga"], [["Botol plastik PET", "Rp3.000/kg"], ["Gelas plastik", "Rp2.500/kg"], ["Kardus", "Rp2.000/kg"], ["Kertas campur", "Rp1.500/kg"], ["Kaleng aluminium", "Rp8.000/kg"]], [10*cm, 5.6*cm])]

    s += section("11. Fitur Petugas")
    petugas = [
        ("11.1 Dashboard dan tugas hari ini", "Menampilkan setoran, penjemputan, pencairan siap dibayar, sembako siap diserahkan, perubahan jadwal, notifikasi penting, serta tugas yang belum dimulai, sedang dikerjakan, dan selesai."),
        ("11.2 Pencarian nasabah", "Berdasarkan nama, telepon, nomor nasabah, RT/RW, atau QR code."),
        ("11.3 Setoran langsung", "Pilih nasabah, input jenis dan berat, hitung otomatis, unggah bukti, konfirmasi, buat mutasi, dan kirim notifikasi."),
        ("11.4 Hasil penjemputan", "Buka pengajuan, timbang tiap jenis, input berat aktual, unggah foto, konfirmasi transaksi, tambah saldo, dan selesaikan penjemputan."),
        ("11.5 Koreksi", "Draf dapat diedit petugas pembuat. Transaksi final hanya dikoreksi admin dengan alasan, nilai sebelum-sesudah, waktu, pengguna, referensi, dampak saldo, dan bukti."),
        ("11.6 Bukti transaksi", "Dapat dilihat, diunduh, dicetak, dan dibagikan melalui WhatsApp secara manual menggunakan tautan wa.me; aplikasi tidak mengirim pesan otomatis."),
        ("11.7 Penjemputan", "Melihat foto dan alamat, menerima atau menolak, memperbarui status, mencatat hasil, dan membentuk transaksi berdasarkan berat aktual."),
        ("11.8 Pembayaran pencairan", "Memverifikasi nomor dan identitas, mencatat waktu dan metode, mengunggah bukti, dan menandai sudah dibayar."),
        ("11.9 Penyerahan sembako", "Memeriksa identitas dan nomor pengajuan, mencatat tanggal, mengunggah bukti, dan menyelesaikan penyerahan."),
        ("11.10 Pelayanan warga tanpa smartphone", "Dengan persetujuan warga, petugas dapat membantu pendaftaran, menggunakan nomor atau kartu QR, mencatat transaksi atas nama warga, menyampaikan informasi saldo, dan memberikan bukti cetak tanpa mengambil alih kata sandi pribadi warga."),
        ("11.11 Bank Sampah Keliling", "Petugas melihat jadwal, titik pelayanan, wilayah, kapasitas, dan daftar tugas; transaksi pada titik keliling tetap menggunakan alur setoran langsung."),
        ("11.12 Bukti dengan QR verifikasi", "Bukti transaksi memuat token QR yang membuka halaman verifikasi terbatas tanpa menampilkan saldo atau data pribadi lengkap."),
    ]
    for title, text in petugas: s += [subsection(title), p(text)]

    s += section("12. Fitur Admin")
    admin = {
        "12.1 Dashboard": "Nasabah, petugas, transaksi, berat, nilai, saldo tersedia dan tertahan, pengajuan menunggu, grafik, wilayah aktif, serta aktivitas terbaru.",
        "12.2 Manajemen pengguna": "Verifikasi, penolakan, penambahan, perubahan, aktivasi, nonaktivasi, perubahan kata sandi berbantuan setelah verifikasi identitas, role, dan aktivitas.",
        "12.3 Manajemen wilayah": "Dusun, RT/RW, area pelayanan, status area, petugas, dan jadwal wilayah.",
        "12.4 Jenis sampah": "Kode, nama, kategori, satuan, foto, deskripsi, kondisi diterima, status, dan urutan tampilan.",
        "12.5 Harga": "Harga dan tanggal berlaku, riwayat, aktivasi, serta snapshot harga agar transaksi lama tidak berubah.",
        "12.6 Transaksi": "Daftar, detail, filter, koreksi, pembalikan, bukti, ekspor, dan log perubahan.",
        "12.7 Saldo": "Saldo, mutasi, saldo tertahan, kewajiban total, pembekuan, dan koreksi resmi tanpa edit angka langsung.",
        "12.8 Penjemputan": "Permintaan, petugas, jadwal, batas minimal, kapasitas harian berdasarkan alamat atau perkiraan berat, wilayah, foto, status, alternatif tanggal, penolakan, pembatalan, dan riwayat.",
        "12.9 Pencairan": "Pemeriksaan saldo, persetujuan, penolakan, jadwal, lokasi, petugas pembayaran, bukti, dan riwayat status.",
        "12.10 Paket sembako": "Nama, isi, nilai, foto, status, periode, permintaan, persetujuan, lokasi, dan bukti; tanpa pengelolaan stok terperinci.",
        "12.11 Jadwal": "Jadwal buka, setor, penjemputan, hari libur, kapasitas, wilayah, lokasi, dan catatan.",
        "12.12 Pengumuman": "Perubahan harga, kegiatan, libur, edukasi, pelayanan, dan informasi desa terkait bank sampah.",
        "12.13 Laporan": "Harian hingga tahunan, per warga, wilayah, petugas, jenis, saldo, mutasi, pencairan, penukaran, penjemputan, pertumbuhan, koreksi, dan aktivitas; format web, Excel, CSV, PDF.",
        "12.14 Audit log": "Login, pengguna, role, harga, transaksi, koreksi, saldo, persetujuan, pembayaran, penyerahan, ekspor, dan konfigurasi; tidak dapat dihapus melalui fungsi operasional biasa.",
        "12.15 Target pengumpulan": "Admin menetapkan jenis sampah, target berat, periode, tujuan program, status, jumlah terkumpul, persentase pencapaian, dan informasi yang boleh ditampilkan secara publik.",
        "12.16 Bank Sampah Keliling": "Admin menetapkan titik layanan, RT/RW, tanggal, waktu, petugas, jenis sampah diterima, kapasitas, status, dan pengumuman perubahan jadwal.",
        "12.17 Partisipasi RT/RW": "Dashboard menampilkan jumlah nasabah aktif, transaksi, total berat, jenis dominan, pertumbuhan, dan perbandingan wilayah dalam bentuk tabel, grafik, atau heatmap.",
        "12.18 Ringkasan publik desa": "Admin mengatur statistik agregat yang dapat dipublikasikan, seperti total sampah, plastik, nasabah aktif, progres target, jadwal, dan kegiatan tanpa menampilkan data pribadi atau saldo warga.",
    }
    for title, text in admin.items(): s += [subsection(title), p(text)]

    s += section("13. Alur Utama Sistem")
    flows = [
        ["Setoran langsung", "Warga → QR/nomor → timbang → input → hitung → bukti → konfirmasi → mutasi → saldo bertambah"],
        ["Penjemputan", "Foto dan pengajuan → pemeriksaan → jadwal → jemput → timbang → transaksi → saldo bertambah"],
        ["Pencairan", "Ajukan nominal → saldo ditahan → persetujuan → pembayaran → bukti → saldo dipotong"],
        ["Sembako", "Pilih paket → saldo ditahan → cek manual → siapkan → serahkan → bukti → saldo dipotong"],
        ["Koreksi", "Buka transaksi → alasan → nilai benar → dampak saldo → konfirmasi → mutasi penyesuaian → audit"],
    ]
    s += [table(["Proses", "Alur"], flows, [4*cm, 11.6*cm])]

    s += section("14. Aturan Bisnis")
    rule_groups = {
        "14.1 Transaksi": ["Nomor transaksi unik; wajib memiliki warga dan petugas.", "Berat lebih dari nol dan maksimal tiga desimal.", "Harga berlaku disimpan sebagai snapshot.", "Saldo bertambah setelah konfirmasi dan tidak boleh dua kali.", "Transaksi final tidak dihapus langsung; koreksi hanya oleh pihak berwenang."],
        "14.2 Saldo": ["Menggunakan rupiah dan tidak boleh minus.", "Semua perubahan memiliki mutasi.", "Saldo pengajuan ditahan dan dikembalikan jika ditolak atau dibatalkan.", "Nilai uang dan pembulatan diterapkan secara konsisten."],
        "14.3 Penjemputan": ["Foto, alamat, dan jenis wajib.", "Perkiraan tidak menjadi dasar saldo.", "Berat final berasal dari petugas.", "Sistem memeriksa kapasitas harian berdasarkan kebijakan jumlah alamat, perkiraan berat, wilayah, petugas, atau kendaraan; jika penuh, warga ditawarkan tanggal lain.", "Penolakan wajib beralasan; selesai hanya setelah transaksi berhasil."],
        "14.4 Harga": ["Dikelola admin, memiliki tanggal berlaku, riwayat tetap tersimpan, tidak negatif, dan perubahan diaudit."],
        "14.5 Pencairan": ["Nominal tidak melebihi saldo dan memenuhi minimum.", "Saldo ditahan saat pengajuan.", "Admin menyetujui; petugas membayar dan mengunggah bukti.", "Tidak dapat diselesaikan dua kali; pengajuan dapat kedaluwarsa."],
        "14.6 Penukaran sembako": ["Saldo mencukupi dan paket tersedia.", "Ketersediaan diperiksa manual.", "Saldo dipotong setelah bukti penyerahan."],
        "14.7 Status": ["Perubahan mengikuti urutan dan menyimpan status lama-baru, pengguna, waktu, catatan, serta alasan."],
        "14.8 Pembatalan": ["Penjemputan sebelum petugas berangkat; pencairan sebelum dibayar; penukaran sebelum diserahkan; transaksi final melalui reversal atau koreksi."],
        "14.9 Estimasi nilai": ["Estimasi memakai harga aktif dan perkiraan berat.", "Hasil estimasi tidak membentuk transaksi, tidak menahan saldo, dan tidak menjamin nilai akhir.", "Nilai final hanya berasal dari penimbangan dan konfirmasi petugas."],
        "14.10 Verifikasi bukti QR": ["QR hanya berisi token atau referensi acak.", "Halaman verifikasi menampilkan nomor, tanggal, berat, nilai, dan status sah secara terbatas.", "Saldo, alamat, nomor identitas, dan nomor telepon lengkap tidak ditampilkan."],
        "14.11 Pelayanan inklusif": ["Warga tanpa smartphone dapat dibantu petugas dengan persetujuan.", "Transaksi tetap tercatat atas nama warga.", "Petugas tidak mengambil alih kata sandi pribadi dan warga dapat menerima kartu QR atau bukti cetak."],
    }
    for title, items in rule_groups.items(): s += [subsection(title)] + bullets(items)

    s += section("15. Halaman Aplikasi")
    pages = [
        ["Umum", "Beranda, tentang, harga, panduan, jadwal, statistik publik desa, verifikasi bukti QR, pengumuman, login, registrasi, privasi, ketentuan, kontak."],
        ["Warga", "Dashboard, profil, QR, harga, kalkulator estimasi, edukasi, transaksi dan koreksi, saldo, penjemputan, jadwal keliling, pencairan, sembako, dan pengingat."],
        ["Petugas", "Dashboard tugas hari ini, scan QR, transaksi, layanan warga tanpa smartphone, nasabah, penjemputan, layanan keliling, pencairan, penyerahan, riwayat, profil."],
        ["Admin", "Dashboard, target pengumpulan, partisipasi RT/RW, statistik publik, pengguna, wilayah, sampah, harga, transaksi, saldo, penjemputan dan kapasitas, layanan keliling, laporan, dan audit."],
    ]
    s += [table(["Kelompok", "Halaman"], pages, [3.2*cm, 12.4*cm])]

    s += section("16. Data Utama Sistem")
    data_groups = {
        "Pengguna dan akses": "users, roles, permissions, role_permissions, user_roles, nasabah, petugas, user_sessions",
        "Wilayah": "wilayah, dusun, rw, rt, area_penjemputan",
        "Sampah dan harga": "kategori_sampah, jenis_sampah, harga_sampah, riwayat_harga_sampah",
        "Transaksi": "transaksi, detail_transaksi, foto_transaksi, koreksi_transaksi, riwayat_status_transaksi",
        "Saldo": "rekening_saldo, mutasi_saldo, penahanan_saldo, koreksi_saldo",
        "Penjemputan": "pengajuan_penjemputan, detail_penjemputan, foto_penjemputan, jadwal_penjemputan, penugasan_petugas, riwayat_status_penjemputan",
        "Pencairan": "pengajuan_pencairan, riwayat_status_pencairan, bukti_pencairan",
        "Sembako": "paket_sembako, penukaran_sembako, riwayat_status_penukaran, bukti_penyerahan_sembako",
        "Informasi": "notifikasi, pengumuman, jadwal_layanan, audit_logs, media",
    }
    s += [table(["Kelompok", "Data/Tabel"], [[k, v] for k, v in data_groups.items()], [4.2*cm, 11.4*cm])]

    s += section("17. Kebutuhan Nonfungsional")
    nf = {
        "17.1 Tampilan dan kemudahan": ["Mobile-first, responsif, tombol mudah ditekan, bahasa sederhana, navigasi konsisten.", "Format rupiah dan tanggal Indonesia, konfirmasi tindakan keuangan, pesan kesalahan mudah dipahami.", "Status tidak hanya dibedakan dengan warna dan dapat digunakan masyarakat yang belum terbiasa dengan aplikasi."],
        "17.2 Keamanan": ["Password hashing, role/permission, validasi server, audit, timeout, rate limiting, CSRF, HTTPS.", "Validasi dan pembatasan unggahan, otorisasi data, pencegahan transaksi ganda, database transaction, dan pembatasan ekspor."],
        "17.3 Perlindungan data": ["Pengumpulan minimal, masking identitas, media tidak publik, kebijakan privasi, dan pencatatan ekspor."],
        "17.4 Performa": ["Halaman cepat, gambar terkompresi, pagination, pencarian optimal, indikator proses, pencegahan klik berulang, dan ekspor sesuai format yang tersedia."],
        "17.5 Kompatibilitas": ["Chrome Android/desktop, Edge, Safari mobile, dan Firefox."],
        "17.6 PWA": ["Instalasi home screen, ikon, dan cache terbatas untuk informasi umum. Transaksi keuangan tetap memerlukan koneksi internet."],
    }
    for title, items in nf.items(): s += [subsection(title)] + bullets(items)
    s += [subsection("17.7 Teknologi Pengembangan")]
    s += [p("Sistem Informasi Bank Sampah Digital Desa Sindangheula akan dikembangkan menggunakan Laravel 13 dan PHP 8.3 atau lebih baru, kemudian ditempatkan pada layanan Hostinger Web Hosting Premium atau Business yang dikelola melalui hPanel. Laravel 13 dipasang secara manual menggunakan Composer 2 melalui akses SSH atau SFTP.")]
    s += [table(
        ["Komponen", "Teknologi"],
        [
            ["Framework backend", "Laravel 13, dipasang manual melalui Composer 2"],
            ["Bahasa pemrograman", "PHP 8.3 atau lebih baru"],
            ["Antarmuka aplikasi", "Blade dan Livewire"],
            ["Interaksi antarmuka", "Alpine.js"],
            ["Desain dan styling", "Tailwind CSS"],
            ["Panel administrasi", "Filament"],
            ["Basis data", "MariaDB terkelola yang kompatibel dengan MySQL"],
            ["Layanan hosting", "Hostinger Web Hosting Premium atau Business"],
            ["Pengelolaan hosting", "hPanel, SSH/SFTP, Composer 2, dan cron job"],
            ["Infrastruktur server", "Linux dan web server terkelola oleh Hostinger"],
            ["Tugas terjadwal", "Laravel Scheduler melalui cron job"],
            ["Penyimpanan berkas", "Storage hosting privat atau object storage kompatibel S3"],
            ["Pengujian", "Pest PHP"],
            ["Pengelolaan kode", "Git"],
            ["Bentuk aplikasi", "Web responsif mobile-first dan PWA installable dengan cache terbatas untuk informasi umum"],
            ["Arsitektur", "Modular monolith"],
        ],
        [5.2*cm, 10.4*cm],
    )]
    s += [p("Antarmuka aplikasi menggunakan Blade dan Livewire, dengan Alpine.js untuk interaksi ringan serta Tailwind CSS untuk tampilan mobile-first yang responsif. Filament dapat digunakan untuk fungsi administrasi standar, sedangkan halaman warga dan alur operasional petugas dikembangkan secara khusus agar sederhana dan nyaman digunakan melalui smartphone.")]
    s += [p("Sistem ditempatkan pada layanan web hosting terkelola. Tugas terjadwal Laravel yang diperlukan aplikasi dijalankan melalui cron job sesuai dukungan paket hosting.")]
    s += [p("Aplikasi menggunakan pendekatan modular monolith: seluruh modul berada dalam satu aplikasi Laravel, tetapi dipisahkan berdasarkan proses bisnis dan tanggung jawabnya. Proses yang memengaruhi transaksi, saldo masuk, saldo keluar, dan saldo tertahan wajib menggunakan database transaction, penguncian data yang sesuai, foreign key, unique constraint, dan referensi transaksi unik untuk menjaga konsistensi serta mencegah transaksi ganda.")]

    s += section("18. Fitur Pengembangan yang Disetujui")
    s += numbered(["Registrasi, login, ubah kata sandi setelah login, dan pemulihan akses berbantuan admin.", "Verifikasi akun, termasuk pelayanan berbantuan bagi warga tanpa smartphone.", "Data nasabah, petugas, role, permission, wilayah, nomor, kartu, dan QR nasabah.", "Jenis sampah, harga, riwayat harga, serta edukasi kontekstual.", "Transaksi multi-jenis, saldo rupiah, mutasi, riwayat, dan riwayat koreksi yang dapat dilihat warga.", "Penjemputan dengan foto, kapasitas harian, alternatif tanggal, dan pemrosesannya.", "Pencairan tunai.", "Paket sembako sederhana dan penukaran tanpa pengelolaan stok terperinci.", "Dashboard warga, dashboard tugas petugas hari ini, dan dashboard admin.", "Notifikasi dan pengingat dalam aplikasi serta WhatsApp manual melalui tautan wa.me.", "Target pengumpulan sampah desa dan progres pencapaiannya.", "Bank Sampah Keliling per RT/RW.", "Kalkulator estimasi nilai sebelum setor.", "Bukti transaksi dengan QR verifikasi publik terbatas.", "Peta atau heatmap partisipasi RT/RW.", "Ringkasan statistik publik desa tanpa data pribadi.", "PWA installable dengan cache terbatas untuk informasi umum.", "Laporan web, Excel, CSV, dan PDF.", "Audit log dan bukti transaksi, pencairan, serta penyerahan."])

    s += section("19. Pengembangan di Luar Baseline")
    s += bullets(["WhatsApp gateway otomatis.", "Pengelolaan stok sembako terperinci.", "Transfer bank, dompet digital, OTP atau PIN pengambilan.", "Integrasi timbangan digital dan pelacakan armada.", "Aplikasi native, AI klasifikasi foto, dan sistem produksi paving block."])

    s += section("20. Kriteria Penerimaan Sistem")
    criteria = {
        "20.1 Akun dan akses": ["Warga dapat mendaftar; admin dapat memverifikasi; akun nonaktif tidak dapat login.", "Warga tidak dapat melihat data warga lain; petugas tidak dapat mengubah harga; percobaan login dibatasi."],
        "20.2 Transaksi": ["Setoran multi-jenis dihitung benar; harga lama tidak berubah; konfirmasi menambah saldo satu kali; koreksi diaudit."],
        "20.3 Saldo": ["Tidak negatif; setiap perubahan bermutuasi; saldo tertahan tidak dapat dipakai; saldo kembali jika ditolak."],
        "20.4 Penjemputan": ["Foto wajib; penolakan beralasan; hasil aktual dapat dimasukkan; penyelesaian menghasilkan transaksi.", "Jika kapasitas tanggal penuh, sistem menolak tanggal tersebut dan menawarkan jadwal lain tanpa menggunakan perkiraan sebagai dasar saldo."],
        "20.5 Pencairan": ["Saldo diperiksa dan ditahan; admin memutuskan; petugas mencatat pembayaran dan bukti; tidak selesai dua kali."],
        "20.6 Sembako": ["Paket dapat dipilih; saldo ditahan; ketersediaan diperiksa manual; bukti penyerahan tersimpan; saldo dipotong setelah penyerahan selesai."],
        "20.7 Laporan dan audit": ["Laporan dapat difilter dan diekspor; koreksi, harga, persetujuan, pembayaran, dan ekspor dapat ditelusuri."],
        "20.8 Tampilan": ["Dapat digunakan pada HP dan laptop, termasuk lebar 360 px; tanpa scroll horizontal; tombol mudah ditekan; status tidak hanya warna."],
        "20.9 Fitur inklusif dan transparansi": ["Petugas dapat melayani warga tanpa smartphone melalui prosedur berbantuan.", "Warga dapat melihat riwayat koreksi dan dampaknya terhadap saldo.", "Dashboard petugas menampilkan tugas hari ini dan status penyelesaiannya."],
        "20.10 Program dan transparansi": ["Estimasi nilai menampilkan penafian dan tidak membentuk transaksi.", "QR bukti hanya membuka informasi verifikasi terbatas.", "Statistik publik tidak membuka data pribadi.", "Target dan partisipasi wilayah dihitung dari data agregat yang sah."],
    }
    for title, items in criteria.items(): s += [subsection(title)] + bullets(items)

    s += section("21. Kebutuhan Operasional")
    s += numbered(["Penanggung jawab penimbangan dan jenis timbangan.", "Pemegang kas, pemberi persetujuan, dan petugas pembayaran.", "Penyedia sembako serta sumber pengadaannya.", "Lokasi pengambilan uang dan barang.", "Jadwal pelayanan, area dan batas minimal penjemputan.", "Batas minimal serta masa berlaku pencairan.", "Prosedur internet/timbangan bermasalah dan ketidaksesuaian sampah.", "Prosedur koreksi, penutupan harian, dan rekonsiliasi."])

    s += section("22. Konsep Sumber Dana Pencairan")
    s += [p("Saldo warga merupakan kewajiban pengelola. Sampah yang disetor dicatat dan menambah saldo, kemudian dikumpulkan dan dijual kepada pengepul. Hasil penjualan menjadi sumber kas untuk memenuhi pencairan atau penyediaan paket sembako.")]
    s += bullets(["Total saldo warga.", "Saldo tertahan.", "Kas tersedia.", "Pencairan menunggu.", "Nilai sampah yang belum dibayar pengepul.", "Selisih hasil penjualan dan kewajiban saldo.", "Biaya operasional yang berlaku."])
    s += [p("Aplikasi bukan sistem akuntansi lengkap, tetapi perlu menampilkan total kewajiban saldo agar pengelola dapat menjaga likuiditas.")]

    s += section("23. Rekonsiliasi Harian")
    s += bullets(["Saldo awal dan akhir.", "Total transaksi, berat, dan nilai setoran.", "Total pencairan dan penukaran.", "Total koreksi dan saldo tertahan.", "Transaksi dibatalkan atau belum selesai.", "Petugas bertugas.", "Kesesuaian bukti dengan pembayaran/penyerahan.", "Kesesuaian kas dengan pencairan."])

    s += section("24. Risiko dan Mitigasi")
    risks = [
        ["Kesalahan berat", "Konfirmasi, batas wajar, subtotal otomatis, koreksi terbatas."],
        ["Transaksi ganda", "Nomor unik, tombol terkunci, database transaction, referensi unik, idempotensi."],
        ["Saldo tidak sesuai", "Ledger mutasi, tanpa edit langsung, rekonsiliasi, audit, laporan."],
        ["Kas tidak tersedia", "Pemantauan kas, jadwal, batas nominal, status transparan."],
        ["Sembako tidak tersedia", "Cek manual sebelum persetujuan, nonaktifkan paket, saldo ditahan lalu dikembalikan."],
        ["Akses data tidak sah", "Role dan permission, media privat, pembatasan ekspor, dan pencatatan aktivitas penting."],
        ["Penyalahgunaan akun", "Hashing, rate limiting, timeout, role, audit, autentikasi tambahan."],
        ["Internet tidak stabil", "Tampilan ringan, kompresi, indikator, pesan jelas, cegah konfirmasi berulang."],
    ]
    s += [table(["Risiko", "Mitigasi"], risks, [4.2*cm, 11.4*cm])]

    s += section("25. Rencana Implementasi")
    implementation = {
        "25.1 Analisis": ["Validasi kebutuhan, wawancara, pemetaan proses, role, aturan harga/saldo, pencairan, sembako, laporan, dan seluruh baseline fitur."],
        "25.2 Perancangan": ["Arsitektur, database, hak akses, status, UI mobile-first, prototipe, validasi pengguna, dan skenario uji."],
        "25.3 Pengembangan": ["Autentikasi, pengguna, sampah, harga, transaksi, ledger, penjemputan, pencairan, sembako, notifikasi, dashboard, laporan, dan audit."],
        "25.4 Pengujian": ["Fungsi, akses, perhitungan, transaksi ganda, saldo, unggahan, responsif, keamanan dasar, laporan, dan penerimaan pengguna."],
        "25.5 Penerapan": ["Hosting, domain/HTTPS, data awal, akun, pelatihan, uji terbatas, evaluasi, dan peluncuran."],
        "25.6 Evaluasi penggunaan": ["Mengumpulkan masukan pengguna, menilai kesesuaian alur layanan, dan mencatat kebutuhan perubahan untuk keputusan lanjutan."],
    }
    for title, items in implementation.items(): s += [subsection(title)] + bullets(items)

    s += section("26. Pelatihan Pengguna")
    training = [
        ["Warga", "Registrasi, login, harga, saldo, riwayat, penjemputan, pencairan, sembako, keamanan akun."],
        ["Petugas", "Pencarian/QR, penimbangan, perhitungan, penjemputan, pembayaran, penyerahan, bukti, kesalahan input, kerahasiaan."],
        ["Admin", "Verifikasi, pengguna, harga, transaksi, koreksi, saldo, persetujuan, laporan, audit, rekonsiliasi."],
    ]
    s += [table(["Peserta", "Materi"], training, [3.2*cm, 12.4*cm])]

    s += section("27. Ringkasan Konsep Aplikasi")
    s += [p("Bank Sampah Digital Sindangheula adalah sistem web mobile-first untuk mengelola nasabah, petugas, harga, penimbangan, saldo rupiah, penjemputan, pencairan tunai, penukaran sembako, notifikasi, laporan, dan audit log.")]
    s += [p("Warga memperoleh saldo dari jenis, berat aktual, dan harga yang berlaku. Penjemputan menggunakan foto sebagai pemeriksaan awal, sedangkan nilai tetap berdasarkan hasil penimbangan petugas.")]
    s += [p("Pencairan dan penukaran menggunakan mekanisme pengajuan, penahanan saldo, persetujuan, bukti penyerahan, dan pemotongan permanen setelah selesai. Stok sembako tidak dikelola secara terperinci.")]
    s += [p("Semua perubahan saldo dicatat sebagai mutasi. Saldo tidak dapat diubah langsung tanpa transaksi atau koreksi yang memiliki alasan dan audit log.")]

    s += section("28. Penutup")
    s += [p("Pengembangan Sistem Informasi Bank Sampah Digital Desa Sindangheula diharapkan meningkatkan kualitas pelayanan, memperbaiki pencatatan transaksi, meningkatkan transparansi saldo, mempermudah penjemputan, serta mendukung pencairan dan penukaran saldo secara tertib.")]
    s += [p("Sistem dirancang dengan pendekatan web mobile-first agar mudah diakses melalui perangkat masyarakat. Seluruh fitur dalam ruang lingkup dikembangkan dengan urutan yang mengikuti ketergantungan proses bisnis.")]
    s += [p("Melalui sistem ini, Bank Sampah Digital Sindangheula diharapkan menjadi layanan pengelolaan sampah yang profesional, transparan, mudah digunakan, dan berkelanjutan serta mendukung partisipasi masyarakat dalam menjaga kebersihan dan kelestarian lingkungan.")]
    s += [Spacer(1, 18), HRFlowable(width="40%", thickness=2, color=GOLD, hAlign="LEFT"), Spacer(1, 8), p("Desa Sindangheula • Kecamatan Pabuaran • Kabupaten Serang • Provinsi Banten", SMALL)]
    return s


if __name__ == "__main__":
    doc = ProposalDoc(str(PDF_PATH))
    doc.build(build_story())
    print(PDF_PATH)
