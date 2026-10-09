"""Extract the Ingco price list (Ingco.xlsx) into scripts/ingco_catalog.json
and copy each row's pictures into storage/app/public/uploads.

Sheet columns: B code, C picture, D name, E illustrative picture,
F description, G price, H price $, I units per carton (عدد الطرد).

Usage: python3 -I scripts/extract_ingco_xlsx.py public/Ingco.xlsx
Then:  php artisan ingco:import-catalog
"""
import json
import posixpath
import re
import sys
import zipfile
from pathlib import Path

import openpyxl

ROOT = Path(__file__).resolve().parent.parent
UPLOADS = ROOT / 'storage/app/public/uploads'
OUT = ROOT / 'scripts/ingco_catalog.json'

NS = {
    'xdr': 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing',
    'a': 'http://schemas.openxmlformats.org/drawingml/2006/main',
    'r': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
}
PICTURE_COL, ILLUSTRATION_COL = 2, 4


def row_images(book: zipfile.ZipFile) -> dict:
    """1-based sheet row -> {column index: zip path of the picture}."""
    import xml.etree.ElementTree as ET

    rels_xml = ET.fromstring(book.read('xl/drawings/_rels/drawing1.xml.rels'))
    rels = {
        rel.get('Id'): posixpath.normpath(posixpath.join('xl/drawings', rel.get('Target')))
        for rel in rels_xml
    }
    images = {}
    for event, el in ET.iterparse(book.open('xl/drawings/drawing1.xml')):
        if el.tag not in (f"{{{NS['xdr']}}}twoCellAnchor", f"{{{NS['xdr']}}}oneCellAnchor"):
            continue
        start = el.find('xdr:from', NS)
        blip = el.find('.//a:blip', NS)
        if start is not None and blip is not None:
            col = int(start.find('xdr:col', NS).text)
            row = int(start.find('xdr:row', NS).text) + 1
            target = rels.get(blip.get(f"{{{NS['r']}}}embed"))
            if target:
                images.setdefault(row, {}).setdefault(col, target)
        el.clear()
    return images


def number(value):
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def main(path: str) -> None:
    book = zipfile.ZipFile(path)
    images = row_images(book)
    sheet = openpyxl.load_workbook(path, read_only=True, data_only=True).worksheets[0]
    UPLOADS.mkdir(parents=True, exist_ok=True)

    def save(code: str, suffix: str, member: str | None) -> str | None:
        if not member:
            return None
        name = f"ingco-{re.sub(r'[^a-z0-9]+', '-', code.lower()).strip('-')}{suffix}{Path(member).suffix.lower()}"
        (UPLOADS / name).write_bytes(book.read(member))
        return f'uploads/{name}'

    items = []
    for row_no, row in enumerate(sheet.iter_rows(min_row=3, max_col=9, values_only=True), start=3):
        _, code, _, name, _, description, price, price_usd, pack = row
        code = str(code or '').strip()
        if not code or number(price_usd) is None:
            continue
        pics = images.get(row_no, {})
        illustration = save(code, '-info', pics.get(ILLUSTRATION_COL))
        items.append({
            'row': row_no,
            'code': code,
            'name_ar': re.sub(r'\s+', ' ', str(name or '')).strip() or code,
            'description_ar': '\n'.join(
                line.strip() for line in str(description or '').splitlines() if line.strip()
            ) or None,
            'price': round(number(price) or number(price_usd), 4),
            'price_usd': round(number(price_usd), 4),
            'pack_quantity': int(number(pack)) if number(pack) else None,
            'image_main': save(code, '', pics.get(PICTURE_COL)) or illustration,
            'image_gallery': [illustration] if illustration and pics.get(PICTURE_COL) else [],
        })

    OUT.write_text(json.dumps(items, ensure_ascii=False, indent=1), encoding='utf-8')
    print(f'{len(items)} products -> {OUT}')
    print(f"{sum(1 for i in items if i['image_main'])} with a picture")


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else str(ROOT / 'public/Ingco.xlsx'))
