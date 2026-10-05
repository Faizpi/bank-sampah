from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

from reportlab.platypus import Flowable, Paragraph, Table


DOCUMENTS = Path(__file__).parent


def load_generator():
    generator = DOCUMENTS / "generate_proposal.py"
    spec = importlib.util.spec_from_file_location("proposal_generator", generator)
    if spec is None or spec.loader is None:
        raise RuntimeError("Unable to load proposal generator")
    module = importlib.util.module_from_spec(spec)
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


def flowable_text(flowable: Flowable) -> list[str]:
    if isinstance(flowable, Paragraph):
        return [flowable.getPlainText()]
    if isinstance(flowable, Table):
        return [
            text
            for row in flowable._cellvalues
            for cell in row
            for text in (flowable_text(cell) if isinstance(cell, Flowable) else [str(cell)])
        ]
    return []


def proposal_content() -> str:
    module = load_generator()
    return " ".join(
        text for flowable in module.build_story() for text in flowable_text(flowable)
    ).lower()


def test_proposal_scope_matches_active_capabilities() -> None:
    content = proposal_content()
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
    assert not [term for term in excluded if term in content]
    assert "php 8.3" in content


def test_proposal_preserves_current_business_scope() -> None:
    content = proposal_content()
    required = (
        "mobile-first",
        "target pengumpulan",
        "bank sampah keliling",
        "partisipasi rt/rw",
        "kapasitas harian",
        "tanggal lain",
        "penimbangan aktual",
    )
    assert not [term for term in required if term not in content]


def test_proposal_nonfunctional_sections_are_sequential() -> None:
    content = proposal_content()
    assert "17.7 teknologi pengembangan" in content
    assert "17.8 teknologi pengembangan" not in content


if __name__ == "__main__":
    test_proposal_scope_matches_active_capabilities()
    test_proposal_preserves_current_business_scope()
    test_proposal_nonfunctional_sections_are_sequential()
    print("generator scope tests passed")
