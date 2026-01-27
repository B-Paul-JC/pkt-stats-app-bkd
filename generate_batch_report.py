import sys
import json
import io
import requests
import warnings
import logging

# --- CRITICAL FIX: Suppress Warnings ---
# Prevents stderr buffer overflow which causes PHP/Python deadlocks
warnings.filterwarnings("ignore")

import matplotlib
# Set backend to 'Agg' (Non-interactive) BEFORE importing pyplot
matplotlib.use('Agg')
# Silence Matplotlib debug/info logs
logging.getLogger('matplotlib').setLevel(logging.WARNING)

import matplotlib.pyplot as plt
import pandas as pd
import numpy as np
from fpdf import FPDF
from fpdf.enums import XPos, YPos
from fpdf.fonts import FontFace

# --- Configuration (Merged) ---
COLORS = [
    '#eab308', '#f59e0b', '#f97316', '#ef4444', '#84cc16', '#10b981', '#6366f1', '#d946ef',
    '#3b82f6', '#ec4899', '#14b8a6', '#f43f5e', '#8b5cf6', '#0ea5e9', '#22c55e', '#e11d48',
    '#db2777', '#7c3aed', '#2563eb', '#0d9488', '#65a30d', '#ca8a04', '#ea580c', '#dc2626',
    '#0891b2', '#4f46e5', '#9333ea', '#c026d3', '#be123c', '#b91c1c', '#c2410c', '#a16207',
    '#4d7c0f', '#15803d', '#047857', '#0f766e', '#0369a1', '#1d4ed8', '#4338ca', '#7e22ce',
    '#a21caf', '#be185d', '#881337', '#7f1d1d', '#7c2d12', '#713f12', '#365314', '#14532d',
    '#064e3b', '#134e4a', '#0c4a6e', '#1e3a8a', '#312e81', '#581c87', '#701a75', '#831843'
]
TEXT_COLOR = '#334155'
SUBTEXT_COLOR = '#64748b'

# --- Helper: Generate Chart Image Buffer ---
def create_chart_image(widget_data):
    try:
        chart_type = widget_data.get('type', 'bar')
        title = widget_data.get('title', '')
        subtitle = widget_data.get('subtitle', '')
        raw_data = widget_data.get('data', [])
        config = widget_data.get('config', {})
        
        if not raw_data: return None

        df = pd.DataFrame(raw_data)
        x_key = config.get('xKey', 'name')
        
        # Data cleaning
        for col in df.columns:
            if col != x_key and col != 'fill':
                df[col] = pd.to_numeric(df[col], errors='ignore')

        # Figure setup
        fig = plt.figure(figsize=(10, 6))
        ax = fig.add_subplot(111)

        # Style
        if chart_type != 'pie':
            ax.spines['top'].set_visible(False)
            ax.spines['right'].set_visible(False)
            ax.spines['left'].set_color('#cbd5e1')
            ax.spines['bottom'].set_color('#cbd5e1')
            ax.tick_params(axis='x', colors=SUBTEXT_COLOR, labelsize=9)
            ax.tick_params(axis='y', colors=SUBTEXT_COLOR, labelsize=9)
            ax.set_axisbelow(True)
            ax.grid(axis='y', linestyle='-', alpha=0.1, color=TEXT_COLOR)
        else:
            ax.axis('off')
            # Critical fix for distortion: Force aspect ratio to be equal for Pie charts
            ax.set_aspect('equal')

        # Plotting Logic
        if chart_type == 'bar':
            bars_conf = config.get('bars', [])
            num = len(bars_conf)
            width = 0.8 / num if num > 0 else 0.8
            ind = np.arange(len(df))
            for i, conf in enumerate(bars_conf):
                key = conf.get('key', 'value')
                label = conf.get('name', key)
                colors = df[conf['colorKey']].tolist() if 'colorKey' in conf and conf['colorKey'] in df.columns else conf.get('color', COLORS[i % len(COLORS)])
                offset = (i - num/2) * width + width/2
                rects = ax.bar(ind + offset, df[key], width=width, label=label, color=colors, alpha=0.9)
                ax.bar_label(rects, padding=3, fontsize=9, color=TEXT_COLOR)
            ax.set_xticks(ind)
            ax.set_xticklabels(df[x_key], rotation=25, ha='right')
            if num > 1: ax.legend(frameon=False, fontsize=9)

        elif chart_type == 'line':
            lines_conf = config.get('lines', [])
            for i, conf in enumerate(lines_conf):
                key = conf.get('key', 'value')
                color = conf.get('color', COLORS[i % len(COLORS)])
                ax.plot(df[x_key], df[key], marker='o', linewidth=2.5, color=color, label=conf.get('name', key))
            ax.set_xticklabels(df[x_key], rotation=25, ha='right')
            ax.legend(frameon=False, fontsize=9)

        elif chart_type == 'area':
            areas_conf = config.get('areas', [])
            for i, conf in enumerate(areas_conf):
                key = conf.get('key', 'value')
                color = conf.get('color', COLORS[i % len(COLORS)])
                ax.fill_between(df[x_key], df[key], color=color, alpha=0.4, label=conf.get('name', key))
                ax.plot(df[x_key], df[key], color=color, linewidth=2)
            ax.set_xticklabels(df[x_key], rotation=25, ha='right')
            ax.legend(frameon=False, fontsize=9)

        elif chart_type == 'pie':
            pies_conf = config.get('pies', [])
            if pies_conf:
                conf = pies_conf[0]
                key = conf.get('key', 'value')
                colors = conf.get('colors', COLORS)
                total = df[key].sum()
                wedges, _, autotexts = ax.pie(df[key], startangle=90, colors=colors, autopct='%1.1f%%', pctdistance=0.75, wedgeprops=dict(width=0.5, edgecolor='white'))
                plt.setp(autotexts, size=9, weight="bold", color="white")
                labels = [f"{n}: {v} ({v/total:.1%})" for n, v in zip(df[x_key], df[key])]
                ax.legend(wedges, labels, title="Distribution", loc="center left", bbox_to_anchor=(1, 0, 0.5, 1), frameon=False, fontsize=8)

        elif chart_type == 'scatter':
            scatters_conf = config.get('scatters', [])
            for i, conf in enumerate(scatters_conf):
                key = conf.get('key', 'value')
                color = conf.get('color', COLORS[i % len(COLORS)])
                sizes = [v * 1.5 + 50 for v in df[key]]
                ax.scatter(df[x_key], df[key], s=sizes, color=color, alpha=0.6, edgecolors='white', linewidth=1.5, label=conf.get('name', key))
            ax.set_xticklabels(df[x_key], rotation=25, ha='right')
            ax.legend(frameon=False, fontsize=9)

        # Titles embedded in image
        plt.suptitle(title, fontsize=16, fontweight='bold', color=TEXT_COLOR, y=0.98)
        ax.set_title(subtitle, fontsize=10, color=SUBTEXT_COLOR, pad=10)
        plt.tight_layout()

        # Save buffer
        buf = io.BytesIO()
        # Increased DPI to 300
        plt.savefig(buf, format='png', dpi=300, bbox_inches='tight')
        plt.close(fig)
        buf.seek(0)
        return buf
    except:
        return None

# --- PDF Class ---
class CombinedPDF(FPDF):
    def __init__(self, meta_data):
        super().__init__()
        self.meta = meta_data
        self.set_auto_page_break(auto=True, margin=15)

    def header(self):
        # Logo handling
        logo_url = self.meta.get('school_logo')
        if logo_url and self.page_no() == 1:
            try:
                # Reduced timeout to prevent long hangs on network issues
                response = requests.get(logo_url, timeout=2)
                if response.status_code == 200:
                    self.image(io.BytesIO(response.content), 10, 8, 25)
            except: pass

        self.set_font('Helvetica', 'B', 14)
        title = self.meta.get('school_name', 'Report')
        self.set_xy(0, 10)
        self.cell(0, 10, title, 0, 1, 'C')
        
        self.set_font('Helvetica', '', 10)
        self.cell(0, 5, self.meta.get('address', ''), 0, 1, 'C')
        self.ln(10)

    def footer(self):
        self.set_y(-15)
        self.set_font('Helvetica', 'I', 8)
        self.cell(0, 5, f"{self.meta.get('generated_by', '')} | {self.meta.get('generated_at', '')}", 0, 0, 'L')
        self.cell(0, 5, f'Page {self.page_no()}/{{nb}}', 0, 0, 'R')

    def render_table_item(self, item):
        data = item.get('data', [])
        title = item.get('title', 'Data Table')
        
        self.add_page()
        self.set_font('Helvetica', 'B', 12)
        self.cell(0, 10, title, 0, 1, 'L')
        self.ln(5)

        if not data:
            self.set_font('Helvetica', 'I', 10)
            self.cell(0, 10, "No records found.", 1, 1, 'C')
            return

        # 1. Prepare Header
        keys = list(data[0].keys())
        headers = [k.replace('_', ' ').title() for k in keys]
        
        # 2. Prepare Data (List of Lists is faster than List of Dicts)
        table_data = []
        for row in data:
            table_data.append([str(row.get(k, '-')) for k in keys])

        # 3. Render using with pdf.table()
        self.set_font('Helvetica', '', 9)
        
        col_lens = {k: len(k) for k in keys} 
        for row in data[:20]:
            for k in keys:
                val_len = len(str(row.get(k, '')))
                if val_len > col_lens[k]:
                    col_lens[k] = val_len
        
        total_len = sum(col_lens.values())
        if total_len == 0: total_len = 1
        
        col_widths = [(col_lens[k] / total_len) * 100 for k in keys]

        with self.table(text_align="CENTER", col_widths=col_widths) as table:
            header_row = table.row()
            # Define style using FontFace
            header_style = FontFace(emphasis="BOLD", fill_color=(229, 231, 235))
            for h in headers:
                header_row.cell(h, style=header_style)
            
            for data_row in table_data:
                row = table.row()
                for datum in data_row:
                    row.cell(datum)

    def render_chart_item(self, item):
        img_buffer = create_chart_image(item)
        if img_buffer:
            self.add_page()
            self.image(img_buffer, x=15, y=40, w=180)

def main():
    try:
        # Reconfigure stdout to force UTF-8 (Fixes Windows encoding crashes)
        if hasattr(sys.stdout, 'reconfigure'):
            sys.stdout.reconfigure(encoding='utf-8')
            
        # Read from STDIN
        input_data = sys.stdin.buffer.read().decode('utf-8')
        if not input_data: return
        payload = json.loads(input_data)
        
        meta = payload.get("meta", {})
        items = payload.get("items", []) 
        
        pdf = CombinedPDF(meta)
        pdf.alias_nb_pages()
        
        for item in items:
            type_ = item.get('type')
            if type_ == 'table' or (type_ is None and 'data' in item and 'config' not in item):
                pdf.render_table_item(item)
            elif type_ in ['bar', 'line', 'area', 'pie', 'scatter']:
                pdf.render_chart_item(item)
        
        pdf_bytes = pdf.output()
        
        # Flush output to prevent buffering delays
        sys.stdout.buffer.write(pdf_bytes)
        sys.stdout.buffer.flush()

    except Exception as e:
        sys.stderr.write(str(e))

if __name__ == "__main__":
    main()