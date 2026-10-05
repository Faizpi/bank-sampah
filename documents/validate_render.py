"""Render generated PDFs for local QA and reject text outside page bounds."""

from pathlib import Path

import subprocess

import pdfplumber
from PIL import Image, ImageDraw


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "artifacts" / "document-review-2026-10-05"
OUT.mkdir(parents=True, exist_ok=True)

for filename in (
    "Kumpulan_Flowchart_Bank_Sampah_Digital_Sindangheula.pdf",
    "Pengajuan_Bank_Sampah_Digital_Desa_Sindangheula.pdf",
):
    document = pdfplumber.open(ROOT / "documents" / filename)
    folder = OUT / Path(filename).stem
    folder.mkdir(exist_ok=True)
    thumbnails = []
    subprocess.run(["pdftoppm", "-scale-to", "1280", "-png", str(ROOT / "documents" / filename), str(folder / "page")], check=True)
    for index, page in enumerate(document.pages):
        for char in page.chars:
            if char["x0"] < -1 or char["top"] < -1 or char["x1"] > page.width + 1 or char["bottom"] > page.height + 1:
                raise ValueError(f"Text outside page: {filename}, page {index + 1}")
        destination = folder / f"page-{index + 1:02}.png"
        thumbnail = Image.open(destination).convert("RGB")
        thumbnail.thumbnail((840, 840))
        thumbnails.append(thumbnail)
    for start in range(0, len(thumbnails), 4):
        group = thumbnails[start : start + 4]
        height = max(image.height for image in group)
        sheet = Image.new("RGB", (1680, 2 * (height + 28)), "#dddddd")
        draw = ImageDraw.Draw(sheet)
        for offset, image in enumerate(group):
            x, y = (offset % 2) * 840, (offset // 2) * (height + 28)
            draw.text((x + 8, y + 5), f"Page {start + offset + 1}", fill="black")
            sheet.paste(image, (x, y + 28))
        sheet.save(folder / f"contact-{start // 4 + 1:02}.png")
    print(f"{filename}: {len(document.pages)} pages rendered; page bounds valid")
