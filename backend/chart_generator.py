import matplotlib
matplotlib.use('Agg') # Non-interactive backend
import matplotlib.pyplot as plt
from matplotlib.backends.backend_pdf import PdfPages
import pandas as pd
import sys
import json
import os

# Try to apply a style, fallback to default if not available
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

    pdf_filename = f"report_{config.get('job_id', 'temp')}.pdf"
    pdf_path = os.path.join(output_dir, pdf_filename)
    
    try:
        with PdfPages(pdf_path) as pdf:
            for req in requests:
                try:
                    # --- 1. Parse Request ---
                    data = req.get('data')
                    chart_type = req.get('type', 'bar').lower()
                    key_col = req.get('key_col')
                    title = req.get('title', 'Untitled')
                    fname = req.get('filename', 'chart')
                    
                    # Convert to DataFrame
                    df = pd.DataFrame(data)
                    
                    # Validate Data
                    if key_col not in df.columns:
                        sys.stderr.write(f"Skipping {title}: Key column '{key_col}' not found.\n")
                        continue 

                    # Set Key Column as Index (Standard behavior for X-axis labels)
                    df.set_index(key_col, inplace=True)
                    
                    # Initialize Figure
                    fig = None
                    ax = None

                    # --- 2. Handle Chart Types ---
                    
                    # TYPE: Statistical Table
                    if chart_type == 'table':
                        fig, ax = plt.subplots(figsize=(8.27, 11.69)) # A4 Portrait
                        ax.axis('off')
                        
                        # Sort by first numeric column if available
                        try:
                            numeric_cols = df.select_dtypes(include=['number']).columns
                            if len(numeric_cols) > 0:
                                df = df.sort_values(by=numeric_cols[0], ascending=False)
                        except: pass

                        # Render Table
                        df_reset = df.reset_index().round(2)
                        cell_text = [[str(x) for x in row] for row in df_reset.values]
                        col_labels = df_reset.columns.to_list()

                        table = ax.table(cellText=cell_text, colLabels=col_labels, loc='upper center', cellLoc='center')
                        table.auto_set_font_size(False)
                        table.set_fontsize(10)
                        table.scale(1, 1.5)
                        
                        # Style Header
                        for (row, col), cell in table.get_celld().items():
                            if row == 0:
                                cell.set_text_props(weight='bold')
                                cell.set_facecolor('#e6e6e6')
                        
                        plt.title(title, pad=20, fontweight='bold', y=0.95)

                    # TYPE: Pie Chart
                    elif chart_type == 'pie':
                        fig, ax = plt.subplots(figsize=(10, 6))
                        # Pie charts in pandas need `y` or `subplots=True`. We take the first numeric column.
                        numeric_cols = df.select_dtypes(include=['number']).columns
                        if len(numeric_cols) > 0:
                            y_col = numeric_cols[0]
                            df.plot.pie(y=y_col, ax=ax, autopct='%1.1f%%', startangle=90, legend=True)
                            ax.set_ylabel('') # Hide the column name on Y axis for cleaner look
                        else:
                            ax.text(0.5, 0.5, "No numeric data for Pie Chart", ha='center')

                    # TYPE: Scatter Plot
                    elif chart_type == 'scatter':
                        fig, ax = plt.subplots(figsize=(10, 6))
                        # Scatter needs explicit X and Y columns. 
                        # We reset index so the "Key Column" becomes a regular column accessible for X.
                        df_reset = df.reset_index()
                        numeric_cols = df_reset.select_dtypes(include=['number']).columns
                        
                        if len(numeric_cols) >= 2:
                            # Use first two numeric columns as X and Y
                            x_c, y_c = numeric_cols[0], numeric_cols[1]
                            df_reset.plot.scatter(x=x_c, y=y_c, ax=ax, s=100, alpha=0.7)
                        elif len(numeric_cols) == 1 and key_col:
                            # Use Key Col as X (if numeric) and the Value col as Y
                            try:
                                df_reset.plot.scatter(x=key_col, y=numeric_cols[0], ax=ax, s=100)
                            except:
                                ax.text(0.5, 0.5, "Scatter requires numeric X and Y", ha='center')
                        else:
                            ax.text(0.5, 0.5, "Not enough numeric data for Scatter", ha='center')

                    # TYPE: Standard Plots (Bar, Line, Area, Hist, Box, KDE)
                    else:
                        fig, ax = plt.subplots(figsize=(10, 6))
                        
                        # Map frontend types to Pandas/Matplotlib types
                        kind = chart_type
                        
                        # Handle KDE (Density) Dependency
                        if kind == 'kde':
                            try:
                                import scipy
                            except ImportError:
                                sys.stderr.write(f"Warning: SciPy not installed. Fallback to Hist for {title}.\n")
                                kind = 'hist'

                        # Plotting
                        try:
                            # rot=0 keeps x-axis labels horizontal
                            df.plot(kind=kind, ax=ax, rot=45 if len(df) > 5 else 0) 
                        except Exception as e:
                            ax.text(0.5, 0.5, f"Error plotting {kind}: {str(e)}", ha='center')

                        ax.set_xlabel(key_col)
                        ax.set_ylabel("Values")
                        ax.legend(title="Legend")
                        plt.grid(True, linestyle='--', alpha=0.5)

                    # --- 3. Finalize & Save ---
                    if fig:
                        plt.title(title)
                        plt.tight_layout()

                        # Save PNG
                        img_filename = f"{fname}_{config.get('job_id')}.png"
                        img_path = os.path.join(output_dir, img_filename)
                        plt.savefig(img_path, format='png', dpi=100, bbox_inches='tight')
                        
                        response['images'].append({
                            "id": fname,
                            "path": img_filename
                        })

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