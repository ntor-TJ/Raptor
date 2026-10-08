import zipfile
import xml.etree.ElementTree as ET
import os
import json

folder = r"e:\Raptor\payroll_sample_inputs"

def get_shared_strings(z):
    shared = []
    if "xl/sharedStrings.xml" in z.namelist():
        tree = ET.fromstring(z.read("xl/sharedStrings.xml"))
        for si in tree.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}si"):
            text = "".join([t.text for t in si.findall(".//{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t") if t.text])
            shared.append(text)
    return shared

def get_sheet_dict(z, sheet_path, shared_strings):
    if sheet_path not in z.namelist():
        return []
    stree = ET.fromstring(z.read(sheet_path))
    rows_xml = stree.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheetData/{http://schemas.openxmlformats.org/spreadsheetml/2006/main}row")
    
    rows = []
    for r in rows_xml:
        cols = {}
        for c in r.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}c"):
            col_ref = c.attrib.get("r")
            col_letter = "".join([ch for ch in col_ref if ch.isalpha()])
            t = c.attrib.get("t")
            v = c.find("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}v")
            val = ""
            if v is not None and v.text is not None:
                val = shared_strings[int(v.text)] if t == "s" and int(v.text) < len(shared_strings) else v.text
            cols[col_letter] = val
        if cols:
            rows.append(cols)
    return rows

def extract_all():
    extracted = {
        "employees_pf": [],
        "consultants": [],
        "hiring_team": [],
        "bench_team": [],
        "trainers": [],
        "salaries_aug": [],
        "salaries_apr": [],
        "payments_apr": [],
        "lop_aug": {},
    }

    # 1. Parse Aug_Payroll_Data.xlsx
    fpath = os.path.join(folder, "Aug_Payroll_Data.xlsx")
    with zipfile.ZipFile(fpath, "r") as z:
        shared = get_shared_strings(z)
        wb = ET.fromstring(z.read("xl/workbook.xml"))
        sheets = wb.findall("{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheets/{http://schemas.openxmlformats.org/spreadsheetml/2006/main}sheet")
        
        sheet_map = {}
        for idx, s in enumerate(sheets, 1):
            sheet_map[s.attrib.get("name")] = f"xl/worksheets/sheet{idx}.xml"

        # LOP Sheet
        if "LOP" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["LOP"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                lop = r.get("C", "0").strip()
                if emp_id and lop:
                    try:
                        extracted["lop_aug"][emp_id] = float(lop)
                    except:
                        pass

        # Employees Sheet (Full-time PF)
        if "Employees" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Employees"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                name = r.get("B", "").strip()
                if emp_id and name:
                    extracted["employees_pf"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "ctc": r.get("D", "0"),
                        "cal_days": r.get("E", "31"),
                        "paid_days": r.get("F", "31"),
                        "lop_days": r.get("G", "0"),
                        "basic": r.get("H", "0"),
                        "hra": r.get("I", "0"),
                        "special": r.get("J", "1600"),
                        "other": r.get("K", "0"),
                        "gross_income": r.get("L", "0"),
                        "pf_ee": r.get("M", "0"),
                        "esi_ee": r.get("N", "0"),
                        "pt": r.get("O", "200"),
                        "arrears": r.get("P", "0"),
                        "reimb": r.get("Q", "0"),
                        "bonus": r.get("R", "0"),
                        "gross_earnings": r.get("S", "0"),
                        "tds": r.get("T", "0"),
                        "pf_er": r.get("W", "0"),
                        "esi_er": r.get("X", "0"),
                        "gratuity": r.get("Y", "0"),
                        "total_deductions": r.get("Z", "0"),
                        "net_salary": r.get("AA", "0"),
                    })

        # Consultant Sheet
        if "Consultant" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Consultant"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                name = r.get("B", "").strip()
                if emp_id and name:
                    extracted["consultants"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "ctc": r.get("D", "0"),
                        "cal_days": r.get("E", "31"),
                        "paid_days": r.get("F", "31"),
                        "lop_days": r.get("G", "0"),
                        "basic": r.get("H", "0"),
                        "hra": r.get("I", "0"),
                        "special": r.get("J", "1600"),
                        "other": r.get("K", "0"),
                        "gross_income": r.get("L", "0"),
                        "pt": r.get("O", "200"),
                        "arrears": r.get("P", "0"),
                        "bonus": r.get("R", "0"),
                        "gross_earnings": r.get("S", "0"),
                        "tds": r.get("T", "0"),
                        "total_deductions": r.get("Z", "0"),
                        "net_salary": r.get("AA", "0"),
                    })

        # Hiring Team Sheet
        if "Hiring Team" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Hiring Team"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                name = r.get("B", "").strip()
                if emp_id and name:
                    extracted["hiring_team"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "ctc": r.get("D", "0"),
                        "cal_days": r.get("E", "31"),
                        "paid_days": r.get("F", "31"),
                        "lop_days": r.get("G", "0"),
                        "basic": r.get("H", "0"),
                        "hra": r.get("I", "0"),
                        "special": r.get("J", "1600"),
                        "other": r.get("K", "0"),
                        "gross_earnings": r.get("S", "0"),
                        "pt": r.get("O", "200"),
                        "total_deductions": r.get("Z", "0"),
                        "net_salary": r.get("AA", "0"),
                    })

        # Bench Employees Sheet
        if "Bench Employees" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Bench Employees"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                name = r.get("B", "").strip()
                if emp_id and name:
                    extracted["bench_team"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "ctc": r.get("D", "0"),
                        "cal_days": r.get("E", "31"),
                        "paid_days": r.get("F", "31"),
                        "lop_days": r.get("G", "0"),
                        "basic": r.get("H", "0"),
                        "hra": r.get("I", "0"),
                        "special": r.get("J", "1600"),
                        "other": r.get("K", "0"),
                        "gross_earnings": r.get("S", "0"),
                        "pf_ee": r.get("M", "0"),
                        "pt": r.get("O", "200"),
                        "total_deductions": r.get("Z", "0"),
                        "net_salary": r.get("AA", "0"),
                    })

        # Trainers Sheet
        if "Trainers" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Trainers"], shared)
            for r in rows[1:]:
                name = r.get("B", "").strip()
                if name:
                    extracted["trainers"].append({
                        "name": name,
                        "units": r.get("C", "0"),
                        "unit_type": r.get("D", "Hours"),
                        "rate": r.get("E", "0"),
                        "total": r.get("F", "0"),
                        "bank_acc": r.get("G", ""),
                        "ifsc": r.get("H", ""),
                        "holder": r.get("I", name),
                        "bank_name": r.get("J", ""),
                    })

        # Salaries Sheet (Bank details for August)
        if "Salaries" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Salaries"], shared)
            for r in rows[1:]:
                emp_id = r.get("B", "").strip()
                name = r.get("C", "").strip()
                if emp_id and name:
                    extracted["salaries_aug"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "net_salary": r.get("E", "0"),
                        "bank_acc": r.get("F", ""),
                        "ifsc": r.get("G", ""),
                        "bank_name": r.get("H", ""),
                        "remarks": r.get("I", ""),
                    })

        # Salaries_30042026 Sheet (April)
        if "Salaries_30042026" in sheet_map:
            rows = get_sheet_dict(z, sheet_map["Salaries_30042026"], shared)
            for r in rows[1:]:
                emp_id = r.get("A", "").strip()
                name = r.get("B", "").strip()
                if emp_id and name:
                    extracted["salaries_apr"].append({
                        "emp_id": emp_id,
                        "name": name,
                        "net_salary": r.get("D", "0"),
                        "bank_acc": r.get("E", ""),
                        "ifsc": r.get("F", ""),
                        "bank_name": r.get("G", ""),
                        "remarks": r.get("H", ""),
                    })

    # 2. Parse Payslip_PF.xlsx to get full details (UAN, Designation, Department, etc.)
    fpath = os.path.join(folder, "Payslip_PF.xlsx")
    if os.path.exists(fpath):
        with zipfile.ZipFile(fpath, "r") as z:
            shared = get_shared_strings(z)
            for sname in ["xl/worksheets/sheet1.xml", "xl/worksheets/sheet2.xml"]:
                rows = get_sheet_dict(z, sname, shared)
                for r in rows[1:]:
                    emp_id = r.get("A", "").strip()
                    name = r.get("B", "").strip()
                    if emp_id and name:
                        extracted["employees_pf"].append({
                            "emp_id": emp_id,
                            "name": name,
                            "doj": r.get("C", ""),
                            "designation": r.get("D", ""),
                            "department": r.get("E", ""),
                            "location": r.get("F", "Hyderabad"),
                            "bank_acc": r.get("G", ""),
                            "ifsc": r.get("H", ""),
                            "uan": r.get("I", ""),
                            "pf_num": r.get("J", ""),
                            "paid_days": r.get("M", "31"),
                            "lop_days": r.get("N", "0"),
                            "basic": r.get("Q", "0"),
                            "hra": r.get("S", "0"),
                            "special": r.get("U", "1600"),
                            "other": r.get("W", "0"),
                            "gross_earnings": r.get("Y", "0"),
                            "pf_ee": r.get("Z", "0"),
                            "pt": r.get("AA", "200"),
                            "tds": r.get("AB", "0"),
                            "total_deductions": r.get("AC", "0"),
                            "pf_er": r.get("AD", "0"),
                            "gratuity": r.get("AE", "0"),
                            "ctc": r.get("AG", "0"),
                            "net_salary": r.get("AH", "0"),
                            "words": r.get("AI", ""),
                        })

    out_json = os.path.join(folder, "extracted_payroll_data.json")
    with open(out_json, "w", encoding="utf-8") as f:
        json.dump(extracted, f, indent=2)

    print(f"Extraction complete! Saved to {out_json}")
    print(f"  - Full-time PF records: {len(extracted['employees_pf'])}")
    print(f"  - Consultant records: {len(extracted['consultants'])}")
    print(f"  - Hiring team records: {len(extracted['hiring_team'])}")
    print(f"  - Bench team records: {len(extracted['bench_team'])}")
    print(f"  - Trainers: {len(extracted['trainers'])}")
    print(f"  - April salary entries: {len(extracted['salaries_apr'])}")
    print(f"  - August salary entries: {len(extracted['salaries_aug'])}")

extract_all()
