#!/usr/bin/env python3
"""
Build a rack-row .vsdx by cloning the owner's 42U rack drawing
(lib/vsdx/rack42u-template.vsdx): frame, U scale, and title.

The template is a Visio page (no masters). Measured from that file, inches:

  rack group 7230: LocPinY = 0, so child Y is from the frame bottom
  bottom rail height / interior floor = 0.147638
  interior height = 3.675 = 42 * 0.0875
  U pitch = 0.0875
  opening between inner posts, group-local X: 0.125 .. 1.025

Device center (group-local, LocPin at center):
  pin_y = interior_floor + (u_start - 1) * pitch + (u_size * pitch) / 2

Usage:
  python3 scripts/build_rack_visio.py --rack-id 1 --db storage/server_rack.sqlite -o out.vsdx
  python3 scripts/build_rack_visio.py --payload export.json -o out.vsdx --verify
"""
from __future__ import annotations

import argparse
import copy
import json
import os
import re
import sqlite3
import zipfile
import xml.etree.ElementTree as ET

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TEMPLATE = os.path.join(ROOT, "lib", "vsdx", "rack42u-template.vsdx")

NS = "http://schemas.microsoft.com/office/visio/2012/main"
REL = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
V = "{" + NS + "}"

# Template geometry (inches). Do not rescale the rack art.
U_COUNT = 42
U_PITCH = 0.0875  # 3.675 / 42
INTERIOR_FLOOR = 0.147638  # top of bottom rail, group-local Y
# Inner posts of shape 7233: local X 0.0757874 and 0.975788, shape origin at 0.049213
OPEN_LEFT = 0.1250
OPEN_RIGHT = 1.0250
DEVICE_INSET_X = 0.015
DEVICE_INSET_Y = 0.004
# Same LineWeight value as the template frame shapes (hairline, internal inches).
FRAME_LINE_WEIGHT = "6.94445E-5"
# U-label / No Style weight is 0.75 pt stored in inches. Devices use the frame hairline
# so a 1U box is not flooded by stroke. See _device_shape.
TITLE_ID = "7226"
SCALE_ID = "5366"
RACK_ID = "7230"
# Empty groups with no geometry in the source file (not part of the rack art).
DROP_IDS = {"2663", "2894", "3638"}
COLUMN_GAP = 0.32
PAGE_MARGIN = 0.40

ET.register_namespace("", NS)
ET.register_namespace("r", REL)


def _local(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def _cell(shape, name: str):
    for c in shape:
        if _local(c.tag) == "Cell" and c.get("N") == name:
            return c
    return None


def _num(shape, name: str, default: float = 0.0) -> float:
    cell = _cell(shape, name)
    if cell is None or cell.get("V") in (None, ""):
        return default
    return float(cell.get("V"))


def _set_cell(shape, name: str, value: str, formula: str | None = None, unit: str | None = None) -> None:
    cell = _cell(shape, name)
    if cell is None:
        cell = ET.Element(V + "Cell")
        cell.set("N", name)
        # Keep cells ahead of sections/text.
        insert_at = 0
        for i, child in enumerate(list(shape)):
            if _local(child.tag) != "Cell":
                insert_at = i
                break
            insert_at = i + 1
        shape.insert(insert_at, cell)
    cell.set("V", value)
    if formula is None:
        if "F" in cell.attrib:
            del cell.attrib["F"]
    else:
        cell.set("F", formula)
    if unit is None:
        if "U" in cell.attrib and name in ("PinX", "PinY", "Width", "Height"):
            del cell.attrib["U"]
    else:
        cell.set("U", unit)


def _shape_text(shape) -> str:
    for c in shape:
        if _local(c.tag) == "Text":
            return "".join(c.itertext()).replace("\n", "").strip()
    return ""


def _set_text(shape, text: str) -> None:
    text_el = None
    for c in shape:
        if _local(c.tag) == "Text":
            text_el = c
            break
    if text_el is None:
        text_el = ET.SubElement(shape, V + "Text")
    for child in list(text_el):
        text_el.remove(child)
    text_el.text = None
    cp = ET.SubElement(text_el, V + "cp")
    cp.set("IX", "0")
    cp.tail = text


def _find_shape(root, sid: str):
    for sh in root.iter(V + "Shape"):
        if sh.get("ID") == sid:
            return sh
    raise SystemExit(f"template shape {sid} not found")


def _max_id(root) -> int:
    ids = [int(sh.get("ID")) for sh in root.iter(V + "Shape") if sh.get("ID", "").isdigit()]
    return max(ids) if ids else 1


def _assign_ids(shape, id_map: dict[str, str], counter: list[int]) -> None:
    if _local(shape.tag) == "Shape":
        old = shape.get("ID")
        new = str(counter[0])
        counter[0] += 1
        if old:
            id_map[old] = new
        shape.set("ID", new)
    for child in shape:
        _assign_ids(child, id_map, counter)


def _rewrite_refs(node, id_map: dict[str, str]) -> None:
    for attr in ("F", "V"):
        val = node.get(attr)
        if val and "Sheet." in val:
            node.set(
                attr,
                re.sub(r"Sheet\.(\d+)", lambda m: "Sheet." + id_map.get(m.group(1), m.group(1)), val),
            )
    for child in node:
        _rewrite_refs(child, id_map)


def _clone_shape(shape, id_map: dict[str, str], counter: list[int]):
    cloned = copy.deepcopy(shape)
    _assign_ids(cloned, id_map, counter)
    return cloned


def _shift_top(shape, dx: float, dy: float) -> None:
    _set_cell(shape, "PinX", _fmt(_num(shape, "PinX") + dx))
    _set_cell(shape, "PinY", _fmt(_num(shape, "PinY") + dy))


def _fmt(n: float) -> str:
    return f"{n:.6f}".rstrip("0").rstrip(".")


def _device_shape(sid: str, pin_x: float, pin_y: float, width: float, height: float, text: str):
    """Unfilled rectangle. Line weight matches the template frame (not a styled master)."""
    sh = ET.Element(V + "Shape")
    sh.set("ID", sid)
    sh.set("NameU", "Device")
    sh.set("Name", "Device")
    sh.set("Type", "Shape")
    sh.set("LineStyle", "0")
    sh.set("FillStyle", "0")
    sh.set("TextStyle", "0")

    def cell(name, value, formula=None, unit=None):
        el = ET.SubElement(sh, V + "Cell")
        el.set("N", name)
        el.set("V", value)
        if formula:
            el.set("F", formula)
        if unit:
            el.set("U", unit)

    cell("PinX", _fmt(pin_x))
    cell("PinY", _fmt(pin_y))
    cell("Width", _fmt(width))
    cell("Height", _fmt(height))
    cell("LocPinX", _fmt(width / 2), "Width*0.5", "IN")
    cell("LocPinY", _fmt(height / 2), "Height*0.5", "IN")
    cell("Angle", "0")
    cell("FlipX", "0")
    cell("FlipY", "0")
    cell("ResizeMode", "0")
    cell("FillPattern", "0")
    cell("LinePattern", "1")
    cell("LineWeight", FRAME_LINE_WEIGHT)
    cell("LineColor", "#000000")
    cell("Rounding", "0")
    cell("VerticalAlign", "1")
    for edge in ("LeftMargin", "RightMargin", "TopMargin", "BottomMargin"):
        cell(edge, "0", unit="PT")

    # 5 pt = 0.0694444 in, same Size cell convention as the template's U labels.
    # Shrink slightly when the name is wider than the bay.
    size_in = 5.0 / 72.0
    est = max(len(text), 1) * size_in * 0.55
    if est > width * 0.92:
        size_in *= (width * 0.92) / est
    char = ET.SubElement(sh, V + "Section")
    char.set("N", "Character")
    row = ET.SubElement(char, V + "Row")
    row.set("IX", "0")
    for name, val, unit in (
        ("Font", "Microsoft YaHei", None),
        ("AsianFont", "Microsoft YaHei", None),
        ("ComplexScriptFont", "Microsoft YaHei", None),
        ("Color", "#000000", None),
        ("Style", "0", None),
        ("Size", _fmt(size_in), "PT"),
        ("LangID", "zh-CN", None),
    ):
        c = ET.SubElement(row, V + "Cell")
        c.set("N", name)
        c.set("V", val)
        if unit:
            c.set("U", unit)

    para = ET.SubElement(sh, V + "Section")
    para.set("N", "Paragraph")
    prow = ET.SubElement(para, V + "Row")
    prow.set("IX", "0")
    hc = ET.SubElement(prow, V + "Cell")
    hc.set("N", "HorzAlign")
    hc.set("V", "1")

    geom = ET.SubElement(sh, V + "Section")
    geom.set("N", "Geometry")
    geom.set("IX", "0")
    for name, val in (("NoFill", "1"), ("NoLine", "0"), ("NoShow", "0"), ("NoSnap", "0"), ("NoQuickDrag", "0")):
        c = ET.SubElement(geom, V + "Cell")
        c.set("N", name)
        c.set("V", val)
    # Absolute geometry with Width/Height formulas, same pattern as the template title box.
    rows = [
        ("MoveTo", "1", "0", None, "0", "Height*0"),
        ("LineTo", "2", _fmt(width), "Width*1", "0", "Height*0"),
        ("LineTo", "3", _fmt(width), "Width*1", _fmt(height), "Height*1"),
        ("LineTo", "4", "0", None, _fmt(height), "Height*1"),
        ("LineTo", "5", "0", None, "0", "Height*0"),
    ]
    for typ, ix, xv, xf, yv, yf in rows:
        r = ET.SubElement(geom, V + "Row")
        r.set("T", typ)
        r.set("IX", ix)
        x = ET.SubElement(r, V + "Cell")
        x.set("N", "X")
        x.set("V", xv)
        x.set("U", "IN")
        if xf:
            x.set("F", xf)
        y = ET.SubElement(r, V + "Cell")
        y.set("N", "Y")
        y.set("V", yv)
        y.set("U", "IN")
        if yf:
            y.set("F", yf)

    text_el = ET.SubElement(sh, V + "Text")
    cp = ET.SubElement(text_el, V + "cp")
    cp.set("IX", "0")
    cp.tail = text
    return sh


def _add_devices(rack_group, devices: list[dict], counter: list[int]) -> None:
    shapes_el = None
    for c in rack_group:
        if _local(c.tag) == "Shapes":
            shapes_el = c
            break
    if shapes_el is None:
        shapes_el = ET.SubElement(rack_group, V + "Shapes")
    bay_w = (OPEN_RIGHT - DEVICE_INSET_X) - (OPEN_LEFT + DEVICE_INSET_X)
    pin_x = (OPEN_LEFT + OPEN_RIGHT) / 2
    interior_top = INTERIOR_FLOOR + U_COUNT * U_PITCH
    for d in devices or []:
        u_start = int(d.get("u_start") or 1)
        u_size = max(1, int(d.get("u_size") or 1))
        if u_start < 1 or u_start > U_COUNT:
            continue
        if u_start + u_size - 1 > U_COUNT:
            u_size = U_COUNT - u_start + 1
        slot_h = u_size * U_PITCH
        h = slot_h - 2 * DEVICE_INSET_Y
        bottom = INTERIOR_FLOOR + (u_start - 1) * U_PITCH + DEVICE_INSET_Y
        if bottom + h > interior_top:
            h = interior_top - bottom
        pin_y = bottom + h / 2
        name = (d.get("name") or "").replace("\n", " ").strip()
        sid = str(counter[0])
        counter[0] += 1
        shapes_el.append(_device_shape(sid, pin_x, pin_y, bay_w, h, name))


def _bounds(shape) -> tuple[float, float, float, float]:
    """Page-space left, bottom, right, top. LocPin is the pin's offset from the shape's left/bottom."""
    pin_x = _num(shape, "PinX")
    pin_y = _num(shape, "PinY")
    w = _num(shape, "Width")
    h = _num(shape, "Height")
    left = pin_x - _num(shape, "LocPinX")
    bottom = pin_y - _num(shape, "LocPinY")
    return left, bottom, left + w, bottom + h


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
    out = []
    for r in racks:
        devices = cur.execute(
            "SELECT * FROM devices WHERE rack_id = ? ORDER BY u_start ASC", (int(r["id"]),)
        ).fetchall()
        out.append(
            {
                "name": r["name"],
                "label": r["label"] or "",
                "u_total": int(r["u_total"] or 42),
                "devices": [dict(d) for d in devices],
            }
        )
    conn.close()
    return {"racks": out}


def _serialize(root) -> bytes:
    xml = ET.tostring(root, encoding="utf-8", xml_declaration=True)
    # ElementTree emits a space-less declaration; Visio accepts either.
    return xml


def _set_page_size(pages_xml: bytes, width: float, height: float) -> bytes:
    root = ET.fromstring(pages_xml)
    for cell in root.iter(V + "Cell"):
        if cell.get("N") == "PageWidth":
            cell.set("V", _fmt(width))
        elif cell.get("N") == "PageHeight":
            cell.set("V", _fmt(height))
    page = root.find(V + "Page")
    if page is not None:
        page.set("ViewCenterX", _fmt(width / 2))
        page.set("ViewCenterY", _fmt(height / 2))
    return _serialize(root)


def build_vsdx(payload: dict, output_path: str) -> None:
    if not os.path.isfile(TEMPLATE):
        raise SystemExit(f"Missing template: {TEMPLATE}")
    racks = payload.get("racks") or []
    if not racks:
        raise SystemExit("no racks in payload")

    with zipfile.ZipFile(TEMPLATE) as zin:
        infos = zin.infolist()
        blobs = {info.filename: zin.read(info.filename) for info in infos}
        page_xml = blobs["visio/pages/page1.xml"]
        pages_xml = blobs["visio/pages/pages.xml"]

    root = ET.fromstring(page_xml)
    shapes_el = root.find(V + "Shapes")
    if shapes_el is None:
        raise SystemExit("template page has no Shapes")

    for sh in list(shapes_el):
        if _local(sh.tag) == "Shape" and sh.get("ID") in DROP_IDS:
            shapes_el.remove(sh)

    title = _find_shape(root, TITLE_ID)
    scale = _find_shape(root, SCALE_ID)
    rack = _find_shape(root, RACK_ID)

    scale_left, _, _, _ = _bounds(scale)
    rack_left, rack_bottom, rack_right, _ = _bounds(rack)
    _, _, title_right, title_top = _bounds(title)
    title_left, _, _, _ = _bounds(title)
    content_left = min(scale_left, rack_left, title_left)
    content_right = max(rack_right, title_right)
    span = content_right - content_left
    content_h = title_top - rack_bottom
    dx_step = span + COLUMN_GAP

    counter = [_max_id(root) + 1]
    columns = [(title, scale, rack)]
    for _ in racks[1:]:
        id_map: dict[str, str] = {}
        new_title = _clone_shape(title, id_map, counter)
        new_scale = _clone_shape(scale, id_map, counter)
        new_rack = _clone_shape(rack, id_map, counter)
        _rewrite_refs(new_title, id_map)
        _rewrite_refs(new_scale, id_map)
        _rewrite_refs(new_rack, id_map)
        shapes_el.append(new_scale)
        shapes_el.append(new_title)
        shapes_el.append(new_rack)
        columns.append((new_title, new_scale, new_rack))

    dx0 = PAGE_MARGIN - content_left
    dy0 = PAGE_MARGIN - rack_bottom
    for i, ((title_s, scale_s, rack_s), rack_data) in enumerate(zip(columns, racks)):
        dx = dx0 + i * dx_step
        for sh in (title_s, scale_s, rack_s):
            _shift_top(sh, dx, dy0)
        code = (rack_data.get("name") or f"R{i + 1}").strip()
        _set_text(title_s, code)
        _add_devices(rack_s, rack_data.get("devices") or [], counter)

    page_w = PAGE_MARGIN + (len(racks) - 1) * dx_step + span + PAGE_MARGIN
    page_h = PAGE_MARGIN + content_h + PAGE_MARGIN
    new_page = _serialize(root)
    if b"xmlns:r=" not in new_page:
        new_page = new_page.replace(
            b'xmlns="http://schemas.microsoft.com/office/visio/2012/main"',
            b'xmlns="http://schemas.microsoft.com/office/visio/2012/main" '
            b'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"',
            1,
        )
    new_pages = _set_page_size(pages_xml, page_w, page_h)
    blobs["visio/pages/page1.xml"] = new_page
    blobs["visio/pages/pages.xml"] = new_pages

    os.makedirs(os.path.dirname(os.path.abspath(output_path)) or ".", exist_ok=True)
    with zipfile.ZipFile(output_path, "w") as zout:
        for info in infos:
            cloned = zipfile.ZipInfo(filename=info.filename, date_time=info.date_time)
            cloned.compress_type = info.compress_type
            cloned.external_attr = info.external_attr
            zout.writestr(cloned, blobs[info.filename])


def _child_page_bounds(parent, child) -> tuple[float, float, float]:
    """Return child bottom, top, center page Y. Parent LocPin defines the group's page origin."""
    origin_y = _num(parent, "PinY") - _num(parent, "LocPinY")
    h = _num(child, "Height")
    cy = origin_y + _num(child, "PinY")
    # Child PinY is the pin; LocPinY is offset from the child's bottom.
    bottom = origin_y + _num(child, "PinY") - _num(child, "LocPinY")
    return bottom, bottom + h, cy


def verify(path: str, rack_code: str = "A01") -> None:
    with zipfile.ZipFile(path) as z:
        root = ET.fromstring(z.read("visio/pages/page1.xml"))
        pages = ET.fromstring(z.read("visio/pages/pages.xml"))
    pw = ph = None
    for cell in pages.iter(V + "Cell"):
        if cell.get("N") == "PageWidth":
            pw = cell.get("V")
        elif cell.get("N") == "PageHeight":
            ph = cell.get("V")
    print(f"Page size: {pw} x {ph} in")

    shapes = [s for s in root.find(V + "Shapes") if _local(s.tag) == "Shape"]
    print(f"Top-level shapes: {len(shapes)}")

    title = None
    for s in shapes:
        if _shape_text(s) == rack_code:
            title = s
            break
    if title is None:
        print(f"title {rack_code} not found")
        return
    tx = _num(title, "PinX")
    racks = [s for s in shapes if _num(s, "Height") > 3 and abs(_num(s, "PinX") - tx) < 0.2]
    if not racks:
        print("rack group not found")
        return
    rack = racks[0]
    origin = _num(rack, "PinY") - _num(rack, "LocPinY")
    frame_bottom = origin
    frame_top = origin + _num(rack, "Height")
    interior_bottom = origin + INTERIOR_FLOOR
    interior_top = interior_bottom + U_COUNT * U_PITCH

    devices = []
    for c in rack.iter(V + "Shape"):
        if c.get("NameU") == "Device":
            devices.append(c)
    devices.sort(key=lambda s: _child_page_bounds(rack, s)[0])

    print(f"\nVerify nesting ({rack_code})")
    print(f"{'shape':<28} {'bottom':>8} {'top':>8} {'height':>8}")
    print(f"{'rack frame':<28} {frame_bottom:8.3f} {frame_top:8.3f} {_num(rack, 'Height'):8.3f}")
    print(f"{'U interior (1U..42U)':<28} {interior_bottom:8.3f} {interior_top:8.3f} {U_COUNT * U_PITCH:8.3f}")
    if devices:
        u1 = devices[0]
        b, t, _ = _child_page_bounds(rack, u1)
        print(f"{'lowest device':<28} {b:8.3f} {t:8.3f} {_num(u1, 'Height'):8.3f}  {_shape_text(u1)}")
        top_dev = max(devices, key=lambda s: _child_page_bounds(rack, s)[1])
        hb, ht, _ = _child_page_bounds(rack, top_dev)
        print(f"{'highest device':<28} {hb:8.3f} {ht:8.3f} {_num(top_dev, 'Height'):8.3f}  {_shape_text(top_dev)}")
        print(f"  device count: {len(devices)}")
        print(f"  U1 bottom inside interior: {interior_bottom - 0.02 <= b <= interior_bottom + U_PITCH}")
        print(f"  U1 bottom - interior floor: {b - interior_bottom:+.4f} in")
        print(f"  highest top <= interior top: {ht <= interior_top + 0.01} (delta {interior_top - ht:+.4f} in)")
        lw = _cell(u1, "LineWeight")
        fp = _cell(u1, "FillPattern")
        rd = _cell(u1, "Rounding")
        print(f"  device LineWeight={lw.get('V') if lw is not None else None} "
              f"FillPattern={fp.get('V') if fp is not None else None} "
              f"Rounding={rd.get('V') if rd is not None else None}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("-o", "--output", required=True)
    ap.add_argument("--rack-id", type=int)
    ap.add_argument("--db", default=os.path.join(ROOT, "storage", "server_rack.sqlite"))
    ap.add_argument("--payload")
    ap.add_argument("--verify", action="store_true")
    ap.add_argument("--verify-only", action="store_true")
    ap.add_argument("--rack-code", default="A01")
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
