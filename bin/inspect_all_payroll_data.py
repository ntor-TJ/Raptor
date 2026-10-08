import zipfile
import xml.etree.ElementTree as ET
import os

folder = r"e:\Raptor\payroll_sample_inputs"

def read_sheet_rows(z, sheet_idx, shared_strings):
    sheet_path = f"xl/worksheets/sheet{sheet_idx}.xml"
    if sheet_path not in z.namelist():
        candidates = [n for n in z.namelist() if n.startswith("xl/worksheets/sheet")]
        if sheet_idx - 1 < len(candidates):
            sheet_path = candidates[sheet_idx - 1]
        else:
            return []
    stree = ET.fromstring(z.read(sheet_path))
    rows_xml = stree.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheetData/{http://schemas.openxmlformats.org/spreadsheetml/2006/main}row")
    
    parsed_rows = []
    for r in rows_xml:
        row_num = int(r.attrib.get("r", 0))
        cols = {}
        for c in r.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}c"):
            col_ref = c.attrib.get("r") # e.g. A1, B2, AA3
            col_letter = "".join([ch for ch in col_ref if ch.isalpha()])
            t = c.attrib.get("t")
            v = c.find("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}v")
            val = ""
            if v is not None and v.text is not None:
                val = shared_strings[int(v.text)] if t == "s" and int(v.text) < len(shared_strings) else v.text
            cols[col_letter] = val
        if cols:
            parsed_rows.append((row_num, cols))
    return parsed_rows

def inspect_all():
    for f in os.listdir(folder):
        if not f.endswith(".xlsx"):
            continue
        print(f"\n==========================================")
        print(f"FILE: {f}")
        print(f"==========================================")
        fpath = os.path.join(folder, f)
        with zipfile.ZipFile(fpath, "r") as z:
            shared_strings = []
            if "xl/sharedStrings.xml" in z.namelist():
                tree = ET.fromstring(z.read("xl/sharedStrings.xml"))
                for si in tree.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}si"):
                    text = "".join([t.text for t in si.findall(".//{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t") if t.text])
                    shared_strings.append(text)
            
            wb_tree = ET.fromstring(z.read("xl/workbook.xml"))
            sheets = wb_tree.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheets/{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheet")
            for s_idx, s in enumerate(sheets, 1):
                name = s.attrib.get("name")
                rows = read_sheet_rows(z, s_idx, shared_strings)
                print(f"Sheet {s_idx}: '{name}' ({len(rows)} rows)")
                if rows:
                    h_row = rows[0]
                    print(f"  Header R{h_row[0]}: {h_row[1]}")
                    if len(rows) > 1:
                        print(f"  Sample R{rows[1][0]}: {rows[1][1]}")

inspect_all()
