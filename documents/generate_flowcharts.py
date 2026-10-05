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
    Diagram("CUTOVER EMPAT ROLE", "Empat Role Tetap", "Satu akun hanya memiliki satu peran.", "Semua pengguna", [[n("n0", "Login aktif", "start")], [n("n1", "Baca users.role", "process")], [n("n2", "Warga | Petugas | Bendahara | Admin", "process")], [n("n3", "Dashboard role sendiri", "process")], [n("n4", "Aksi diperiksa ulang di server", "process")], [n("n5", "Selesai", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Admin tidak menjalankan layanan fisik rutin."),
    Diagram("CUTOVER EMPAT ROLE", "Registrasi dan Verifikasi Akun", "Warga mendaftar mandiri melalui smartphone.", "Warga dan Admin", [[n("n0", "Daftar mandiri", "start")], [n("n1", "Isi username dan domisili", "process")], [n("n2", "Akun menunggu verifikasi", "process")], [n("n3", "Admin memeriksa domisili", "process")], [n("n4", "Akun aktif dan kartu warga", "process")], [n("n5", "Masuk sebagai Warga", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Verifikasi akun tetap ada; layanan rutin tidak memerlukan approval Admin."),
    Diagram("CUTOVER EMPAT ROLE", "Setoran Sampah", "Setoran langsung final tanpa approval nilai tinggi.", "Petugas dan Warga", [[n("n0", "Pilih atau pindai warga", "start")], [n("n1", "Isi jenis, kondisi, berat aktual", "process")], [n("n2", "Periksa total nilai", "process")], [n("n3", "Catat setoran; foto opsional", "process")], [n("n4", "Final dan satu mutasi masuk", "process")], [n("n5", "Kuitansi warga", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Nilai dihitung server dari harga dan berat aktual."),
    Diagram("CUTOVER EMPAT ROLE", "Penjemputan Sampah", "Petugas searea mengambil tugas sendiri.", "Warga dan Petugas", [[n("n0", "Alamat, tanggal pilihan, jenis", "start")], [n("n1", "Menunggu Petugas", "process")], [n("n2", "Ambil tugas eksklusif", "process")], [n("n3", "Menuju lokasi dan dijemput", "process")], [n("n4", "Timbang dan catat setoran", "process")], [n("n5", "Setoran final dan pickup selesai", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Perkiraan/foto opsional. Pembatalan Warga hanya sebelum klaim; pelepasan tugas sebelum sampah dijemput."),
    Diagram("CUTOVER EMPAT ROLE", "Pencairan Tunai", "Kas tersedia sebelum klaim; uang dicatat setelah diberikan.", "Warga dan Bendahara", [[n("n0", "Ajukan nominal", "start")], [n("n1", "Hold aktif; menunggu Bendahara", "process")], [n("n2", "Kas tersedia: ambil pembayaran", "process")], [n("n3", "Serahkan uang satu kali", "process")], [n("n4", "Catat pembayaran; foto opsional", "process")], [n("n5", "Satu mutasi keluar dan kuitansi", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Kas belum tersedia: tetap menunggu. Tidak ada transfer, pembayaran sebagian, tunda kas, atau expiry otomatis."),
    Diagram("CUTOVER EMPAT ROLE", "Penukaran Sembako", "Paket tersedia sebelum klaim; siap diambil opsional.", "Warga dan Petugas", [[n("n0", "Pilih paket aktif", "start")], [n("n1", "Hold aktif; menunggu Petugas", "process")], [n("n2", "Ambil paket yang tersedia", "process")], [n("n3", "Langsung serahkan atau siap diambil", "process")], [n("n4", "Paket sudah diserahkan", "process")], [n("n5", "Satu mutasi keluar dan kuitansi", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "Tidak tersedia sebelum klaim: beri alasan dan lepas hold tanpa pengeluaran."),
    Diagram("CUTOVER EMPAT ROLE", "Saldo Tertahan", "Hold bukan pengeluaran.", "Warga dan sistem", [[n("a", "Pengajuan menahan saldo", "start")], [n("b", "Hasil pengajuan?", "decision")], [n("c", "Diserahkan: konversi hold"), n("d", "Batal: lepas hold"), n("e", "Masih menunggu: hold aktif")], [n("f", "Riwayat hasil dan mutasi", "end")]], [Edge("a", "b"), Edge("b", "c", "Selesai"), Edge("b", "d", "Batal"), Edge("b", "e", "Belum"), Edge("c", "f"), Edge("d", "f"), Edge("e", "f")], "Konversi dan mutasi keluar atomik. Batal tidak membuat mutasi keluar."),
    Diagram("CUTOVER EMPAT ROLE", "Pengecualian Penyerahan", "Jangan menyerahkan fisik ulang saat hasil tidak pasti.", "Claimant dan Admin", [[n("a", "Kunci klaim bermasalah", "start")], [n("b", "Admin memeriksa fakta, alasan, dan autentikasi ulang")], [n("c", "Fakta penyerahan?", "decision")], [n("d", "Sudah: catat satu mutasi"), n("e", "Belum: kembalikan ke antrean"), n("f", "Tidak pasti: tetap terkunci")], [n("g", "Catat audit dan hasil", "end")]], [Edge("a", "b"), Edge("b", "c"), Edge("c", "d", "Sudah"), Edge("c", "e", "Belum"), Edge("c", "f", "Tidak pasti"), Edge("d", "g"), Edge("e", "g"), Edge("f", "g")], "Admin dapat mengunci klaim saat akses pelaksana dicabut. Pelaksana fisik tetap tercatat; tidak ada penyerahan kedua."),
    Diagram("CUTOVER EMPAT ROLE", "Batas Sistem dan UAT", "Web tetap menjadi pelaksana transaksi fisik.", "Empat role dan penguji", [[n("n0", "Website mobile-first", "start")], [n("n1", "Penimbangan dan penyerahan via web", "process")], [n("n2", "Telegram belum diimplementasikan", "process")], [n("n3", "Tes akses, saldo, klaim, dan media", "process")], [n("n4", "UAT empat role dengan peserta nyata", "process")], [n("n5", "Rilis hanya setelah penerimaan", "end")]], chain(["n0","n1","n2","n3","n4","n5"]), "WhatsApp tidak digunakan. Tes otomatis tidak membuktikan skor SUS atau usability desa."),
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
    c.drawString(1.72*cm,H-8.0*cm,f"{len(DIAGRAMS)} diagram • pengembangan • sistem • penggunaan • administrasi")
    c.setStrokeColor(GOLD); c.setLineWidth(3); c.line(1.7*cm,H-8.6*cm,10*cm,H-8.6*cm)
    c.setFillColor(WHITE); c.setFont(FONT,10)
    c.drawString(1.7*cm,2.2*cm,"Desa Sindangheula • Kecamatan Pabuaran • Kabupaten Serang • Provinsi Banten")


def diagram_page(number: int) -> int:
    return number + 3


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
        c.setFillColor(MUTED); c.drawRightString(W-1.5*cm,y,str(diagram_page(number)))
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
    notes="Panah menunjukkan urutan proses. Label Ya/Tidak atau status pada panah menunjukkan hasil keputusan. Diagram menggambarkan proses bisnis tingkat tinggi; rincian validasi mengikuti aturan bisnis proposal."
    wrap(c,notes,2*cm,2.55*cm,W-4*cm,1.5*cm,ParagraphStyle("legend",parent=NOTE_STYLE,fontSize=9,leading=13))
    c.setStrokeColor(LINE); c.line(1.4*cm,1.05*cm,W-1.4*cm,1.05*cm)
    c.setFillColor(MUTED); c.setFont(FONT,7); c.drawRightString(W-1.4*cm,.62*cm,str(page))


def validate_diagrams() -> None:
    if len(DIAGRAMS) != 9:
        raise ValueError("Expected 9 cutover diagrams")
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
    toc_groups = (DIAGRAMS,)
    start_number = 1
    for toc_page, diagrams in enumerate(toc_groups, 2):
        items = list(enumerate(diagrams, start_number))
        render_toc(c, items, toc_page, f"Daftar Diagram • Bagian {toc_page - 1}")
        c.showPage()
        start_number += len(diagrams)
    render_legend(c,3); c.showPage()
    for idx,d in enumerate(DIAGRAMS,1):
        render_diagram(c,d,idx,idx+3)
        c.showPage()
    c.save()
    print(PDF_PATH)


if __name__ == "__main__":
    build()
