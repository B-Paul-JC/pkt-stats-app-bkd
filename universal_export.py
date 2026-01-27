import sys
import json
import io
import argparse
import pandas as pd
import requests
from fpdf import FPDF
from fpdf.fonts import FontFace
from fpdf.enums import XPos, YPos

# --- 1. PDF Generation Logic ---
class ReportPDF(FPDF):
    def __init__(self, meta_data, title):
        super().__init__()
        self.meta = meta_data
        self.report_title = title
        self.set_auto_page_break(auto=True, margin=15)

    def header(self):
        # Logo
        logo_url = self.meta.get('school_logo')
        if logo_url and self.page_no() == 1:
            try:
                response = requests.get(logo_url, timeout=2)
                if response.status_code == 200:
                    self.image(io.BytesIO(response.content), 10, 8, 25)
            except: pass

        self.set_font('Helvetica', 'B', 14)
        school_name = self.meta.get('school_name', 'Report')
        self.set_xy(0, 10)
        self.cell(0, 10, school_name, 0, 1, 'C')
        
        self.set_font('Helvetica', '', 10)
        self.cell(0, 5, self.meta.get('address', ''), 0, 1, 'C')
        self.ln(10)
        
        # Report Title
        if self.page_no() == 1:
            self.set_font('Helvetica', 'B', 12)
            self.cell(0, 10, self.report_title.upper(), 0, 1, 'C')
            self.ln(5)

    def footer(self):
        self.set_y(-15)
        self.set_font('Helvetica', 'I', 8)
        generated_by = self.meta.get('generated_by', '')
        self.cell(0, 5, f"{generated_by} | {self.meta.get('generated_at', '')}", 0, 0, 'L')
        self.cell(0, 5, f'Page {self.page_no()}/{{nb}}', 0, 0, 'R')

    def render_table(self, data):
        if not data:
            self.cell(0, 10, "No data available", 1, 1, 'C')
            return

        # Prepare Header
        keys = list(data[0].keys())
        headers = [k.replace('_', ' ').title() for k in keys]
        
        # Prepare Data Rows
        table_data = []
        for row in data:
            table_data.append([str(row.get(k, '-')) for k in keys])

        # Calculate Column Widths
        self.set_font('Helvetica', '', 9)
        col_lens = {k: len(k) for k in keys} 
        for row in data[:30]: # Sample first 30 rows
            for k in keys:
                val_len = len(str(row.get(k, '')))
                if val_len > col_lens[k]: col_lens[k] = val_len
        
        total_len = sum(col_lens.values())
        if total_len == 0: total_len = 1
        col_widths = [(col_lens[k] / total_len) * 100 for k in keys]

        # Render Table
        with self.table(text_align="CENTER", col_widths=col_widths) as table:
            header_row = table.row()
            style = FontFace(emphasis="BOLD", fill_color=(229, 231, 235))
            for h in headers:
                header_row.cell(h, style=style)
            
            for data_row in table_data:
                row = table.row()
                for datum in data_row:
                    row.cell(datum)

def generate_pdf_bytes(payload):
    meta = payload.get('print_meta', {})
    data = payload.get('data', [])
    title = payload.get('title', 'Report')

    pdf = ReportPDF(meta, title)
    pdf.alias_nb_pages()
    pdf.add_page()
    pdf.render_table(data)
    
    return pdf.output() # Returns bytearray

# --- 2. CSV Generation Logic ---
def generate_csv_string(payload):
    data = payload.get('data', [])
    if not data: return ""
    
    df = pd.DataFrame(data)
    # Return CSV string
    return df.to_csv(index=False)

# --- 3. XLSX Generation Logic ---
def generate_xlsx_bytes(payload):
    data = payload.get('data', [])
    meta = payload.get('print_meta', {})
    title = payload.get('title', 'Report')
    
    output = io.BytesIO()
    
    # Use pandas ExcelWriter with openpyxl engine
    with pd.ExcelWriter(output, engine='openpyxl') as writer:
        if data:
            df = pd.DataFrame(data)
            df.to_excel(writer, sheet_name='Data', index=False)
        else:
            pd.DataFrame(["No Data"]).to_excel(writer, sheet_name='Data')

        # Create a metadata sheet
        meta_flat = {**{'Report Title': title}, **meta}
        meta_df = pd.DataFrame(list(meta_flat.items()), columns=['Key', 'Value'])
        meta_df.to_excel(writer, sheet_name='Metadata', index=False)

    return output.getvalue()

# --- Main Controller ---
def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--format', choices=['pdf', 'csv', 'xlsx'], required=True)
    args = parser.parse_args()

    # Reconfigure stdout for binary on Windows
    if hasattr(sys.stdout, 'buffer'):
        sys.stdout.flush()
    
    try:
        # Read JSON from Stdin
        input_data = sys.stdin.buffer.read().decode('utf-8')
        if not input_data: return
        payload = json.loads(input_data)

        if args.format == 'pdf':
            pdf_bytes = generate_pdf_bytes(payload)
            sys.stdout.buffer.write(pdf_bytes)
            
        elif args.format == 'csv':
            # CSV is text, but writing via buffer ensures consistency
            csv_str = generate_csv_string(payload)
            sys.stdout.buffer.write(csv_str.encode('utf-8'))
            
        elif args.format == 'xlsx':
            xlsx_bytes = generate_xlsx_bytes(payload)
            sys.stdout.buffer.write(xlsx_bytes)

    except Exception as e:
        sys.stderr.write(f"Error: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()