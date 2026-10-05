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
    import json
    data = json.loads((OUT / "cutover_content.json").read_text(encoding="utf-8"))
    story = [Spacer(1, PAGE_H), NextPageTemplate("Body"), PageBreak()]
    story += section("Workflow web empat role")
    story += [p(data["version"], LEAD), p(data["scope"])]
    story += [table(["Peran", "Tanggung jawab"], data["roles"], [3.0*cm, 12.5*cm])]
    story += [PageBreak()]
    story += section("Alur layanan rutin")
    for title, text in data["workflows"][:4]:
        story += [subsection(title), p(text)]
    story += [PageBreak()]
    story += section("Saldo dan penanganan kendala")
    for title, text in data["workflows"][4:]:
        story += [subsection(title), p(text)]
    story += section("Batas akses dan layanan")
    story += bullets(data["boundaries"])
    story += [PageBreak()]
    story += section("Pengujian dan penerimaan")
    story += [p(data["uat"]), p("Hasil validasi teknis dicatat terpisah pada dokumentasi repository sesuai eksekusi aktual. Reset demo hanya untuk database lokal yang dijaga oleh command khusus. Tidak ada deployment pada tahap cutover lokal ini.")]
    story += section("Panduan serah fisik")
    story += numbered(["Pastikan claimant dan warga yang benar sebelum menyerahkan uang/paket.", "Lakukan serah fisik satu kali, lalu catat menggunakan tombol penyelesaian.", "Jika respons hilang, baca status dan kuitansi terlebih dahulu; jangan mengulangi serah fisik.", "Jika belum dapat dipastikan, laporkan pengecualian dan tunggu keputusan Admin."])
    return story


if __name__ == "__main__":
    doc = ProposalDoc(str(PDF_PATH))
    doc.build(build_story())
    print(PDF_PATH)
