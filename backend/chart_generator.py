import matplotlib
matplotlib.use('Agg') # Non-interactive backend
import matplotlib.pyplot as plt
from matplotlib.backends.backend_pdf import PdfPages
import pandas as pd
import numpy as np
import sys
import json
import os
import math

# Try to apply a style
try:
    plt.style.use('ggplot')
except:
    pass

def process_charts(config):
    requests = config.get('requests', [])
    output_dir = config.get('output_dir', 'storage')
    
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    response = {
        "pdf": None,
        "images": []
    }

    # Log the requests to a file
    log_filename = f"requests_{config.get('job_id', 'temp')}.log"
    log_path = os.path.join(output_dir, log_filename)
    with open(log_path, 'w') as log_file:
        log_file.write(json.dumps(requests, indent=2))

    pdf_filename = f"report_{config.get('job_id', 'temp')}.pdf"
    pdf_path = os.path.join(output_dir, pdf_filename)
    
    try:
        with PdfPages(pdf_path) as pdf:
            for req in requests:
                try:
                    # --- 1. Parse & Validate ---
                    data = req.get('data')
                    chart_type = req.get('type', 'bar').lower()
                    key_col = req.get('key_col')
                    title = req.get('title', 'Untitled')
                    fname = req.get('filename', 'chart')
                    
                    df = pd.DataFrame(data)
                    
                    if df.empty or key_col not in df.columns:
                        sys.stderr.write(f"Skipping {title}: Data empty or key column missing.\n")
                        continue 

                    df.set_index(key_col, inplace=True)
                    
                    # --- 2. Handle Chart Types ---

                    # === TYPE: STATISTICAL TABLE (PAGINATED) ===
                    if chart_type == 'table':
                        # Analysis: Sort by first numeric column
                        numeric_cols = df.select_dtypes(include=['number']).columns
                        if len(numeric_cols) > 0:
                            df = df.sort_values(by=numeric_cols[0], ascending=False)

                        # Prepare Data
                        df_reset = df.reset_index().round(2)
                        
                        # Add Summary Row (Analysis)
                        if len(numeric_cols) > 0:
                            summary_row = {col: '' for col in df_reset.columns}
                            summary_row[df_reset.columns[0]] = "TOTAL" # Label Col
                            for col in numeric_cols:
                                summary_row[col] = df[col].sum()
                            
                            # Append using loc (safe for different pandas versions)
                            df_reset.loc[len(df_reset)] = summary_row

                        # Convert to List of Lists for Table
                        # Force all to string to prevent rendering crashes
                        all_values = [[str(x) for x in row] for row in df_reset.values]
                        col_labels = df_reset.columns.to_list()
                        
                        # Pagination Logic
                        ROWS_PER_PAGE = 30
                        total_rows = len(all_values)
                        total_pages = math.ceil(total_rows / ROWS_PER_PAGE)

                        for page in range(total_pages):
                            fig, ax = plt.subplots(figsize=(8.27, 11.69)) # A4 Portrait
                            ax.axis('off')

                            # Slice data for this page
                            start_row = page * ROWS_PER_PAGE
                            end_row = min((page + 1) * ROWS_PER_PAGE, total_rows)
                            page_data = all_values[start_row:end_row]

                            # Create Table
                            table = ax.table(
                                cellText=page_data, 
                                colLabels=col_labels, 
                                loc='upper center', 
                                cellLoc='center'
                            )
                            
                            # Styling
                            table.auto_set_font_size(False)
                            table.set_fontsize(10)
                            table.scale(1, 1.4)

                            # Header Styling
                            for (row, col), cell in table.get_celld().items():
                                if row == 0:
                                    cell.set_text_props(weight='bold', color='white')
                                    cell.set_facecolor('#4a4a4a') # Dark Header
                                elif row % 2 == 0:
                                    cell.set_facecolor('#f2f2f2') # Zebra Striping

                            # Page Title
                            page_title = f"{title}"
                            if total_pages > 1:
                                page_title += f" (Page {page + 1}/{total_pages})"
                            
                            plt.title(page_title, pad=20, fontweight='bold', y=0.98)

                            # Save Page to PDF
                            pdf.savefig(fig, bbox_inches='tight')
                            
                            # Save First Page as the Preview Image
                            if page == 0:
                                img_filename = f"{fname}_{config.get('job_id')}.png"
                                img_path = os.path.join(output_dir, img_filename)
                                plt.savefig(img_path, format='png', dpi=100, bbox_inches='tight')
                                response['images'].append({"id": fname, "path": img_filename})

                            plt.close(fig)

                    # === TYPE: VISUAL CHARTS ===
                    else:
                        fig, ax = plt.subplots(figsize=(10, 6))
                        
                        # Chart Logic
                        kind = chart_type
                        if chart_type == 'pie':
                            numeric_cols = df.select_dtypes(include=['number']).columns
                            if len(numeric_cols) > 0:
                                df.plot.pie(y=numeric_cols[0], ax=ax, autopct='%1.1f%%', legend=False)
                                ax.set_ylabel('')
                        elif chart_type == 'scatter':
                            # Reset index to access key_col as X
                            df_scat = df.reset_index()
                            num_cols = df_scat.select_dtypes(include=['number']).columns
                            if len(num_cols) >= 2:
                                df_scat.plot.scatter(x=num_cols[0], y=num_cols[1], ax=ax, s=50)
                            else:
                                ax.text(0.5, 0.5, "Scatter requires 2+ numeric cols", ha='center')
                        else:
                            # Standard plots (bar, line, etc.)
                            if chart_type == 'kde':
                                try: import scipy
                                except: kind = 'hist' # Fallback
                            
                            df.plot(kind=kind, ax=ax, rot=45 if len(df) > 8 else 0)
                            ax.set_xlabel(key_col)
                            ax.set_ylabel("Values")
                            if len(ax.get_legend_handles_labels()[1]) > 0:
                                ax.legend(title="Legend")
                            plt.grid(True, linestyle='--', alpha=0.5)

                        plt.title(title)
                        plt.tight_layout()

                        # Save Image
                        img_filename = f"{fname}_{config.get('job_id')}.png"
                        img_path = os.path.join(output_dir, img_filename)
                        plt.savefig(img_path, format='png', dpi=100, bbox_inches='tight')
                        response['images'].append({"id": fname, "path": img_filename})

                        # Save to PDF
                        pdf.savefig(fig, bbox_inches='tight')
                        plt.close(fig)

                except Exception as e:
                    sys.stderr.write(f"Error processing {title}: {str(e)}\n")
                    continue

    except Exception as e:
        return {"error": str(e)}

    response['pdf'] = pdf_filename
    return response

if __name__ == "__main__":
    try:
        if len(sys.argv) < 2:
            print(json.dumps({"error": "No input file provided"}))
            sys.exit(1)

        input_file = sys.argv[1]
        with open(input_file, 'r') as f:
            config = json.load(f)

        result = process_charts(config)
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))