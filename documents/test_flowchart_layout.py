from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

GENERATOR = Path(__file__).with_name("generate_flowcharts.py")
EXPECTED_DIAGRAM_TITLES = (
    "Tahapan Pengembangan Aplikasi",
    "Peta Jalan Pengembangan",
    "Pengujian dan Penerimaan Sistem",
    "Diagram Konteks Sistem",
    "Arsitektur Sistem Tingkat Tinggi",
    "Alur Hak Akses Pengguna",
    "Sitemap Aplikasi",
    "Registrasi dan Verifikasi Akun",
    "Setoran Sampah Langsung",
    "Pengajuan dan Penyelesaian Penjemputan",
    "Pencairan Saldo Tunai",
    "Penukaran Saldo dengan Sembako",
    "Koreksi Transaksi Final",
    "Perubahan Harga Sampah",
    "Alur Notifikasi dan Pengingat",
    "Alur Saldo Masuk",
    "Alur Saldo Keluar",
    "Alur Saldo Tertahan",
    "Siklus Saldo Warga",
    "Pencegahan Transaksi Ganda",
    "Pembatalan dan Pengembalian Saldo",
    "Status Penjemputan",
    "Status Pencairan Tunai",
    "Status Penukaran Sembako",
    "Status Transaksi Setoran",
    "Pembuatan Laporan dan Statistik",
    "Audit Log",
    "Penanganan Kesalahan Transaksi",
    "Rekonsiliasi Harian",
    "Target Pengumpulan Sampah Desa",
    "Bank Sampah Keliling per RT/RW",
    "Estimasi Nilai Sebelum Setor",
    "Pelayanan Warga Tanpa Smartphone",
    "Verifikasi Bukti Transaksi dengan QR",
    "Pengaturan Kapasitas Penjemputan Harian",
    "Health Sistem Read-Only",
)


def load_generator():
    spec = importlib.util.spec_from_file_location("flowchart_generator", GENERATOR)
    if spec is None or spec.loader is None:
        raise RuntimeError("Unable to load flowchart generator")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def test_decision_outcomes_share_visual_rank() -> None:
    module = load_generator()
    failures: list[str] = []
    for diagram in module.DIAGRAMS:
        positions = module.layout_nodes(diagram)
        nodes = {node.id: node for level in diagram.levels for node in level}
        order = {node.id: index for index, node in enumerate(nodes.values())}
        for node in nodes.values():
            if node.kind != "decision":
                continue
            forward_targets = [
                edge.target
                for edge in diagram.edges
                if edge.source == node.id and order[edge.target] > order[node.id]
            ]
            target_y = {round(positions[target][1], 2) for target in forward_targets}
            if len(forward_targets) > 1 and len(target_y) != 1:
                failures.append(f"{diagram.title}: {node.label}")
    assert not failures, "Decision outcomes are not aligned: " + "; ".join(failures)


def test_all_graph_elements_stay_inside_safe_area() -> None:
    module = load_generator()
    failures: list[str] = []
    for diagram in module.DIAGRAMS:
        for node_id, (_, y, _, height) in module.layout_nodes(diagram).items():
            if y < 3.25 * module.cm or y + height > module.H - 3.8 * module.cm:
                failures.append(f"{diagram.title}:{node_id}")
    assert not failures, "Nodes outside safe area: " + "; ".join(failures)


def test_diagram_set_has_expected_titles_and_page_count() -> None:
    module = load_generator()
    assert tuple(diagram.title for diagram in module.DIAGRAMS) == EXPECTED_DIAGRAM_TITLES
    assert len(module.DIAGRAMS) == 36
    assert module.diagram_page(36) == 41


def test_toc_page_matches_rendered_diagram_page() -> None:
    module = load_generator()
    assert module.diagram_page(1) == 6
    assert module.diagram_page(len(module.DIAGRAMS)) == len(module.DIAGRAMS) + 5


def test_diagram_scope_matches_active_capabilities() -> None:
    module = load_generator()
    content = [
        text
        for diagram in module.DIAGRAMS
        for text in (
            diagram.category,
            diagram.title,
            diagram.purpose,
            diagram.actors,
            diagram.note,
            *(node.label for level in diagram.levels for node in level),
        )
    ]
    flattened = " ".join(content).lower()
    excluded = (
        "backup",
        "cadangan",
        "restore",
        "pemulihan data",
        "pemeliharaan",
        "maintenance",
        "pengaturan teknis",
        "retensi",
        "bantuan gratis",
        "push notification",
        "push opsional",
        "reset kata sandi publik",
        "token reset kata sandi",
        "worker",
        "antrean berkala",
        "retry",
        "php 8.5",
    )
    assert not [term for term in excluded if term in flattened]
    assert "php 8.3" in flattened


if __name__ == "__main__":
    test_decision_outcomes_share_visual_rank()
    test_all_graph_elements_stay_inside_safe_area()
    test_diagram_set_has_expected_titles_and_page_count()
    test_toc_page_matches_rendered_diagram_page()
    test_diagram_scope_matches_active_capabilities()
    print("flowchart layout tests passed")
