#!/usr/bin/env python3
"""
Build a rack-row .vsdx by cloning the Rectangle shape from lib/vsdx/template.vsdx
(Visio 2013+ media template shipped with the python vsdx package).

Usage:
  python3 scripts/build_rack_visio.py --rack-id 1 --db storage/server_rack.sqlite -o out.vsdx
  python3 scripts/build_rack_visio.py --payload export.json -o out.vsdx

Payload JSON (optional): {"title": "...", "racks": [{"name","label","u_total","power_capacity","stats":{},"devices":[]}]}
"""
from __future__ import annotations

import argparse
import json
import os
import shutil
import sqlite3
import sys
import tempfile
import xml.etree.ElementTree as ET

from vsdx import VisioFile, namespace

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATE = os.path.join(ROOT, "lib", "vsdx", "template.vsdx")

# Layout in inches (Visio page coords, origin bottom-left, Y up)
U_H = 0.25
RACK_W = 1.8
RACK_GAP = 0.4
MARGIN_X = 0.75
MARGIN_Y = 0.5
HEADER_H = 0.35
RACK_PAD = 0.08
LINE_BLACK = "#000000"
LINE_WEIGHT_PT = 1.0  # points (thin; ~0.014 in)


def _set_cell_pt(shape, name: str, value: float) -> None:
    shape.set_cell_value(name, str(value))
    cell = shape.cells.get(name)
    if cell is not None:
        cell.xml.set("U", "PT")


def _set_loc_pin_center(shape) -> None:
    """Template copies keep stale LocPin V values; formulas must win."""
    for name, formula in (("LocPinX", "Width*0.5"), ("LocPinY", "Height*0.5")):
        shape.set_cell_formula(name, formula)
        cell = shape.cells.get(name)
        if cell is not None and "V" in cell.xml.attrib:
            del cell.xml.attrib["V"]


def shape_bounds_y(shape) -> tuple[float, float, float]:
    """Return (bottom, top, center) page Y in inches from PinY/Height."""
    h = float(shape.height or 0)
    cy = float(shape.y or 0)
    return cy - h / 2, cy + h / 2, cy


def load_from_sqlite(db_path: str, rack_id: int) -> dict:
    conn = sqlite3.connect(db_path)
    conn.row_factory = sqlite3.Row
    cur = conn.cursor()
    anchor = cur.execute("SELECT * FROM racks WHERE id = ?", (rack_id,)).fetchone()
    if not anchor:
        raise SystemExit(f"rack id {rack_id} not found")
    row_label = (anchor["row_label"] or "").strip()
    if row_label:
        racks = cur.execute(
            "SELECT * FROM racks WHERE room_id = ? AND row_label = ? ORDER BY name",
            (anchor["room_id"], row_label),
        ).fetchall()
    else:
        racks = [anchor]
    out_racks = []
    for r in racks:
        rid = int(r["id"])
        devices = cur.execute(
            "SELECT * FROM devices WHERE rack_id = ? ORDER BY u_start ASC", (rid,)
        ).fetchall()
        st = cur.execute(
            """SELECT COUNT(*) total, COALESCE(SUM(u_size),0) used_u, COALESCE(SUM(power_w),0) power
               FROM devices WHERE rack_id = ?""",
            (rid,),
        ).fetchone()
        out_racks.append(
            {
                "name": r["name"],
                "label": r["label"] or "",
                "u_total": int(r["u_total"] or 42),
                "power_capacity": int(r["power_capacity"] or 0),
                "stats": {"used_u": st["used_u"], "power": st["power"], "total": st["total"]},
                "devices": [dict(d) for d in devices],
            }
        )
    conn.close()
    return {"racks": out_racks}


def find_rectangle_prototype(page) -> "Shape":
    for sh in page.child_shapes:
        t = (sh.text or "").strip().upper()
        if t == "RECTANGLE":
            return sh
    return page.child_shapes[0]


def clear_page_shapes(page) -> None:
    root = page.xml.getroot()
    for tag in ("Shapes", "Connects"):
        for el in root.findall(f"{namespace}{tag}"):
            root.remove(el)
    ET.SubElement(root, f"{namespace}Shapes")


def shapes_container(page):
    root = page.xml.getroot()
    el = root.find(f"{namespace}Shapes")
    if el is None:
        el = ET.SubElement(root, f"{namespace}Shapes")
    return el


def _style_outline(sh, font_pt: int = 8, with_text: bool = True) -> None:
    """Minimal Visio style: no fill, black border, horizontal centered text."""
    sh.set_cell_value("FillPattern", "0")
    sh.set_cell_value("LinePattern", "1")
    sh.set_cell_value("LineColor", LINE_BLACK)
    _set_cell_pt(sh, "LineWeight", LINE_WEIGHT_PT)
    sh.set_cell_value("Rounding", "0")
    sh.set_cell_value("TextDirection", "0")
    if with_text:
        sh.set_cell_value("VerticalAlign", "1")
        sh.set_cell_value("HorzAlign", "1")
        sh.set_cell_value("CharColor", LINE_BLACK)
        sh.set_cell_value("Size", str(font_pt))
        sh.set_cell_value("LangID", "2052")


def add_box(proto, page, pin_x, pin_y, width, height, text="", font_pt=8):
    sh = proto.copy(page)
    sh.x = pin_x
    sh.y = pin_y
    sh.width = width
    sh.height = height
    _set_loc_pin_center(sh)
    sh.text = text
    _style_outline(sh, font_pt, with_text=bool(text))
    return sh


def add_caption(proto, page, pin_x, pin_y, width, height, text, font_pt=11):
    sh = proto.copy(page)
    sh.x = pin_x
    sh.y = pin_y
    sh.width = width
    sh.height = height
    _set_loc_pin_center(sh)
    sh.text = text
    sh.set_cell_value("FillPattern", "0")
    sh.set_cell_value("LinePattern", "0")
    sh.set_cell_value("TextDirection", "0")
    sh.set_cell_value("CharColor", LINE_BLACK)
    sh.set_cell_value("VerticalAlign", "1")
    sh.set_cell_value("HorzAlign", "1")
    sh.set_cell_value("Size", str(font_pt))
    return sh


def build_vsdx(payload: dict, output_path: str) -> None:
    if not os.path.isfile(TEMPLATE):
        raise SystemExit(f"Missing template: {TEMPLATE}")

    racks = payload["racks"]
    max_u = max(int(r.get("u_total") or 42) for r in racks)
    grid_h = max_u * U_H
    page_w = MARGIN_X * 2 + len(racks) * RACK_W + (len(racks) - 1) * RACK_GAP
    page_h = MARGIN_Y * 2 + HEADER_H + grid_h

    work = tempfile.mkdtemp(prefix="vsdx-build-")
    try:
        src = os.path.join(work, "work.vsdx")
        shutil.copy(TEMPLATE, src)
        vf = VisioFile(src)
        page = vf.pages[0]
        page.width = page_w
        page.height = page_h
        # PageContents PageSheet
        ps = page.xml.find(f"{namespace}PageSheet")
        if ps is not None:
            for name, val in (("PageWidth", page_w), ("PageHeight", page_h)):
                cell = ps.find(f'{namespace}Cell[@N="{name}"]')
                if cell is None:
                    cell = ET.SubElement(ps, f"{namespace}Cell", N=name)
                cell.set("V", str(val))
                cell.set("U", "IN")

        proto = find_rectangle_prototype(page)
        clear_page_shapes(page)

        top_y = page_h - MARGIN_Y
        # Header band above racks; rack grid occupies [rack_base, rack_top]
        rack_top = top_y - HEADER_H
        rack_base = rack_top - grid_h

        for i, rack in enumerate(racks):
            left = MARGIN_X + i * (RACK_W + RACK_GAP)
            cx = left + RACK_W / 2
            rack_code = rack.get("name") or f"R{i + 1}"
            add_caption(
                proto, page, cx, top_y - HEADER_H / 2, RACK_W, HEADER_H, rack_code, 12
            )

            frame_pin_y = rack_base + grid_h / 2
            add_box(proto, page, cx, frame_pin_y, RACK_W, grid_h, text="", font_pt=6)

            for d in rack.get("devices") or []:
                u_start = int(d.get("u_start") or 1)
                u_size = max(1, int(d.get("u_size") or 1))
                slot_h = u_size * U_H
                h = max(slot_h - 0.01, 0.05)
                # Center of U slot, bottom-up: U1 sits on rack_base
                pin_y = rack_base + (u_start - 1) * U_H + slot_h / 2
                w = RACK_W - RACK_PAD * 2
                name = (d.get("name") or "").strip()
                font = 5 if u_size <= 1 else (6 if u_size <= 2 else 7)
                add_box(proto, page, cx, pin_y, w, h, name, font)

        root = page.xml.getroot()
        for el in list(root.findall(f"{namespace}Connects")):
            root.remove(el)

        vf.save_vsdx(output_path)
        vf.close_vsdx()
    finally:
        shutil.rmtree(work, ignore_errors=True)


def verify(path: str, rack_code: str = "A01") -> None:
    vf = VisioFile(path)
    page = vf.pages[0]
    print(f"Page size: {page.width:.2f} x {page.height:.2f} in")
    frames = [s for s in page.child_shapes if s.height and s.height > 5 and not (s.text or "").strip()]
    print(f"Shapes: {len(page.child_shapes)} (rack frames={len(frames)})")

    cap = next((s for s in page.child_shapes if (s.text or "").strip() == rack_code), None)
    if not cap:
        vf.close_vsdx()
        return
    cx = float(cap.x)
    frame = min(
        (s for s in frames if abs(float(s.x) - cx) < 0.05),
        key=lambda s: float(s.height),
        default=None,
    )
    if not frame:
        vf.close_vsdx()
        return

    fb, ft, _ = shape_bounds_y(frame)
    print(f"\nVerify nesting ({rack_code}, center X={cx:.2f} in)")
    print(f"{'shape':<22} {'bottom':>8} {'top':>8} {'height':>8}")
    print(f"{'rack frame':<22} {fb:8.3f} {ft:8.3f} {float(frame.height):8.3f}")

    devs = [
        s
        for s in page.child_shapes
        if s.text
        and (s.text or "").strip() != rack_code
        and abs(float(s.x) - cx) < 0.08
        and float(s.height or 0) < 2
    ]
    devs.sort(key=lambda s: shape_bounds_y(s)[0])

    if devs:
        u1 = devs[0]
        b, t, _ = shape_bounds_y(u1)
        print(
            f"{'lowest device (U1?)':<22} {b:8.3f} {t:8.3f} {float(u1.height):8.3f}  {(u1.text or '').strip()}"
        )
        top_dev = max(devs, key=lambda s: shape_bounds_y(s)[1])
        b, t, _ = shape_bounds_y(top_dev)
        print(
            f"{'highest device':<22} {b:8.3f} {t:8.3f} {float(top_dev.height):8.3f}  {(top_dev.text or '').strip()}"
        )
        ok_low = abs(b - fb) < 0.02 or shape_bounds_y(u1)[0] >= fb - 0.02
        ok_high = t <= ft + 0.02
        print(f"  U1 bottom ~= frame bottom: {ok_low} (delta {shape_bounds_y(u1)[0] - fb:+.3f} in)")
        print(f"  top device <= frame top:   {ok_high} (delta {ft - shape_bounds_y(top_dev)[1]:+.3f} in)")

    vf.close_vsdx()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("-o", "--output", required=True)
    ap.add_argument("--rack-id", type=int)
    ap.add_argument("--db", default=os.path.join(ROOT, "storage", "server_rack.sqlite"))
    ap.add_argument("--payload")
    ap.add_argument("--verify", action="store_true", help="print dimensions after build")
    ap.add_argument("--verify-only", action="store_true", help="only verify existing -o file")
    ap.add_argument("--rack-code", default="A01", help="rack label for nesting verify")
    args = ap.parse_args()

    if args.verify_only:
        verify(args.output, args.rack_code)
        return

    if args.payload:
        with open(args.payload, encoding="utf-8") as f:
            payload = json.load(f)
    elif args.rack_id:
        payload = load_from_sqlite(args.db, args.rack_id)
    else:
        ap.error("need --rack-id or --payload")

    build_vsdx(payload, args.output)
    if args.verify:
        verify(args.output, args.rack_code)
    print(args.output)


if __name__ == "__main__":
    main()
