import sys
import json
import base64
import io
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
from matplotlib.backends.backend_pdf import PdfPages
import pandas as pd
import numpy as np

# --- Configuration ---
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
FONT_FAMILY = 'sans-serif'

def setup_style(ax):
    ax.spines['top'].set_visible(False)
    ax.spines['right'].set_visible(False)
    ax.spines['left'].set_color('#cbd5e1')
    ax.spines['bottom'].set_color('#cbd5e1')
    ax.tick_params(axis='x', colors=SUBTEXT_COLOR, labelsize=10)
    ax.tick_params(axis='y', colors=SUBTEXT_COLOR, labelsize=10)
    ax.set_axisbelow(True)
    ax.grid(axis='y', linestyle='-', alpha=0.1, color=TEXT_COLOR)

def add_watermark(fig):
    fig.text(0.5, 0.5, 'University of Ibadan', fontsize=50, color='gray', ha='center', va='center', alpha=0.04, rotation=45, weight='bold')

def calculate_metrics(df, chart_type, config):
    metrics = []
    series_keys = []
    if chart_type == 'bar': series_keys = [b.get('key', 'value') for b in config.get('bars', [])]
    elif chart_type == 'line': series_keys = [l.get('key', 'value') for l in config.get('lines', [])]
    elif chart_type == 'area': series_keys = [a.get('key', 'value') for a in config.get('areas', [])]
    elif chart_type == 'pie': series_keys = [p.get('key', 'value') for p in config.get('pies', [])]
    elif chart_type == 'scatter': series_keys = [s.get('key', 'value') for s in config.get('scatters', [])]
    
    if not series_keys: series_keys = ['value']
    
    for col in series_keys:
        if col in df.columns: df[col] = pd.to_numeric(df[col], errors='coerce').fillna(0)
        else: df[col] = 0
            
    values = df[series_keys].sum(axis=1).tolist()
    total_sum = sum(values)
    if total_sum == 0: return []
    
    max_val = max(values)
    min_val = min(values)
    avg_val = total_sum / len(values)
    
    if chart_type == 'pie':
        sum_sq_probs = sum([(v/total_sum)**2 for v in values])
        diversity = (1 - sum_sq_probs) * 100
        dominance = (max_val / total_sum) * 100
        metrics.append(f"Diversity: {diversity:.1f}%")
        metrics.append(f"Dominance: {dominance:.1f}%")
        metrics.append(f"Segments: {len(values)}")
    elif chart_type == 'bar':
        x_key = config.get('xKey', 'name')
        if x_key in df.columns:
            top_idx = values.index(max_val)
            top_name = str(df.iloc[top_idx][x_key])
            if len(top_name) > 15: top_name = top_name[:12] + "..."
            metrics.append(f"Top: {top_name}")
        metrics.append(f"Avg: {avg_val:.0f}")
        metrics.append(f"Spread: {max_val - min_val:,.0f}")
    elif chart_type == 'line':
        first, last = values[0], values[-1]
        change = ((last - first) / first * 100) if first != 0 else 0
        sign = "+" if change >= 0 else ""
        metrics.append(f"Trend: {sign}{change:.1f}%")
        metrics.append(f"Peak: {max_val:,.0f}")
    elif chart_type == 'area':
        metrics.append(f"Peak: {max_val:,.0f}")
        metrics.append(f"Avg: {avg_val:.0f}")
    else:
        metrics.append(f"Count: {len(values)}")
        metrics.append(f"Max: {max_val:,.0f}")
    return metrics

def render_chart_on_ax(ax, chart_type, df, x_key, config):
    legend_kwargs = dict(frameon=False, loc='lower center', bbox_to_anchor=(0.5, 1.02), ncol=4, fontsize=9)

    if chart_type == 'bar':
        bars_conf = config.get('bars', [])
        num_series = len(bars_conf)
        bar_width = 0.8 / num_series if num_series > 0 else 0.8
        indices = np.arange(len(df))
        for i, bar_conf in enumerate(bars_conf):
            key = bar_conf.get('key', 'value')
            label = bar_conf.get('name', key)
            colors = df[bar_conf['colorKey']].tolist() if 'colorKey' in bar_conf and bar_conf['colorKey'] in df.columns else bar_conf.get('color', COLORS[i % len(COLORS)])
            offset = (i - num_series/2) * bar_width + bar_width/2
            rects = ax.bar(indices + offset, df[key], width=bar_width, label=label, color=colors, alpha=0.9, edgecolor='white')
            ax.bar_label(rects, padding=3, fontsize=9, color=TEXT_COLOR)
        ax.set_xticks(indices)
        ax.set_xticklabels(df[x_key], rotation=25, ha='right')
        if num_series > 1: ax.legend(**legend_kwargs)

    elif chart_type == 'line':
        lines_conf = config.get('lines', [])
        for i, line_conf in enumerate(lines_conf):
            key = line_conf.get('key', 'value')
            label = line_conf.get('name', key)
            color = line_conf.get('color', COLORS[i % len(COLORS)])
            ax.plot(df[x_key], df[key], marker='o', markersize=6, linewidth=2.5, color=color, label=label)
            for j, val in enumerate(df[key]): ax.annotate(str(val), (j, val), textcoords="offset points", xytext=(0,8), ha='center', fontsize=8, color=TEXT_COLOR)
        ax.set_xticklabels(df[x_key], rotation=25, ha='right')
        ax.legend(**legend_kwargs)

    elif chart_type == 'area':
        areas_conf = config.get('areas', [])
        for i, area_conf in enumerate(areas_conf):
            key = area_conf.get('key', 'value')
            label = area_conf.get('name', key)
            color = area_conf.get('color', COLORS[i % len(COLORS)])
            ax.fill_between(df[x_key], df[key], color=color, alpha=0.4, label=label)
            ax.plot(df[x_key], df[key], color=color, linewidth=2)
        ax.set_xticklabels(df[x_key], rotation=25, ha='right')
        ax.legend(**legend_kwargs)

    elif chart_type == 'pie':
        ax.axis('equal')
        pies_conf = config.get('pies', [])
        if pies_conf:
            pie_conf = pies_conf[0]
            key = pie_conf.get('key', 'value')
            colors = pie_conf.get('colors', COLORS)
            total = df[key].sum()
            wedges, texts, autotexts = ax.pie(df[key], startangle=90, colors=colors, wedgeprops=dict(width=0.5, edgecolor='white', linewidth=2), autopct='%1.1f%%', pctdistance=0.75)
            plt.setp(autotexts, size=9, weight="bold", color="white")
            plt.setp(texts, size=0)
            legend_labels = [f"{n}: {v} ({v/total:.1%})" for n, v in zip(df[x_key], df[key])]
            # Pie legend moved to Top Center
            ax.legend(wedges, legend_labels, title="Distribution", loc="lower center", bbox_to_anchor=(0.5, 0.95), ncol=3, frameon=False, fontsize=8)

    elif chart_type == 'scatter':
        scatters_conf = config.get('scatters', [])
        for i, scat_conf in enumerate(scatters_conf):
            key = scat_conf.get('key', 'value')
            label = scat_conf.get('name', key)
            color = scat_conf.get('color', COLORS[i % len(COLORS)])
            sizes = [v * 1.5 + 50 for v in df[key]]
            ax.scatter(df[x_key], df[key], s=sizes, color=color, alpha=0.6, edgecolors='white', linewidth=1.5, label=label)
        ax.set_xticklabels(df[x_key], rotation=25, ha='right')
        ax.legend(**legend_kwargs)

def generate_pdf():
    try:
        input_stream = sys.stdin.read()
        if not input_stream: raise ValueError("No input data")
        payload = json.loads(input_stream)
        widgets = []
        meta = {}
        if isinstance(payload, dict):
            if 'widgets' in payload: widgets = payload['widgets']; meta = payload.get('meta', {})
            else: widgets = [payload]
        elif isinstance(payload, list): widgets = payload
            
        buf = io.BytesIO()
        with PdfPages(buf) as pdf:
            total_charts = len(widgets)
            for index, widget in enumerate(widgets):
                chart_type = widget.get('type', 'bar')
                title = widget.get('title', 'Chart')
                subtitle = widget.get('subtitle', '')
                raw_data = widget.get('data', [])
                config = widget.get('config', {})
                if not raw_data: continue
                df = pd.DataFrame(raw_data)
                x_key = config.get('xKey', 'name')
                for col in df.columns:
                    if col != x_key and col != 'fill': df[col] = pd.to_numeric(df[col], errors='ignore')

                fig = plt.figure(figsize=(11.7, 8.3))
                ax = fig.add_subplot(111)
                if chart_type != 'pie': setup_style(ax)
                else: ax.axis('off')
                add_watermark(fig)
                
                # Header
                fig.text(0.05, 0.92, title, fontsize=18, fontweight='bold', color=TEXT_COLOR, ha='left')
                fig.text(0.05, 0.88, subtitle, fontsize=10, color=SUBTEXT_COLOR, ha='left')
                metrics = calculate_metrics(df, chart_type, config)
                metrics_text = "  |  ".join(metrics)
                fig.text(0.05, 0.84, metrics_text, fontsize=10, fontweight='bold', color='#eab308', ha='left', bbox=dict(facecolor='#fefce8', edgecolor='#eab308', boxstyle='round,pad=0.3', alpha=0.5))

                # Render
                render_chart_on_ax(ax, chart_type, df, x_key, config)
                
                # Footer
                gen_by = meta.get('generated_by', '')
                gen_at = meta.get('generated_at', '')
                footer_text = f"{gen_by} • {gen_at}" if gen_by else "Generated Report"
                fig.text(0.05, 0.02, footer_text, ha='left', fontsize=8, color=SUBTEXT_COLOR)
                fig.text(0.5, 0.02, f'Page {index + 1} of {total_charts}', ha='center', fontsize=8, color=SUBTEXT_COLOR)

                plt.tight_layout(rect=[0.05, 0.05, 0.95, 0.80]) # Extra space at top for legend
                pdf.savefig(fig)
                plt.close(fig)

        buf.seek(0)
        b64_data = base64.b64encode(buf.read()).decode('utf-8')
        print(json.dumps({"success": True, "data": f"data:application/pdf;base64,{b64_data}", "filename": "full_report.pdf"}))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))

if __name__ == "__main__":
    generate_pdf()