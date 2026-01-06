import matplotlib
matplotlib.use('Agg') # Non-interactive backend for servers
import matplotlib.pyplot as plt
from matplotlib.backends.backend_pdf import PdfPages
import pandas as pd
import sys
import json
import os

def process_charts(config):
    """
    Reads configuration, generates charts, and returns file paths.
    """
    requests = config.get('requests', [])
    output_dir = config.get('output_dir', 'storage')
    
    # Ensure output directory exists
    if not os.path.exists(output_dir):
        os.makedirs(output_dir)

    # Prepare return structure
    response = {
        "pdf": None,
        "images": []
    }

    # 1. Setup PDF
    pdf_filename = f"report_{config.get('job_id', 'temp')}.pdf"
    pdf_path = os.path.join(output_dir, pdf_filename)
    
    pdf_pages = None
    try:
        pdf_pages = PdfPages(pdf_path)
    except Exception as e:
        return {"error": f"Failed to create PDF: {str(e)}"}

    # 2. Process each chart request
    for req in requests:
        try:
            # Parse Request
            data = req.get('data')
            chart_type = req.get('type', 'bar').lower()
            key_col = req.get('key_col')
            axis = req.get('axis', 'x').lower()
            title = req.get('title', 'Untitled')
            fname = req.get('filename', 'chart')
            
            # Create DataFrame
            df = pd.DataFrame(data)
            
            if key_col not in df.columns:
                # Add a text page to PDF explaining the error
                fig_err = plt.figure(figsize=(8, 2))
                plt.text(0.1, 0.5, f"Error: Column '{key_col}' missing for {title}", fontsize=12, color='red')
                plt.axis('off')
                pdf_pages.savefig(fig_err)
                plt.close(fig_err)
                continue

            # Set Index
            df.set_index(key_col, inplace=True)

            # Determine Plot Orientation
            kind = chart_type
            if axis == 'y':
                if kind == 'bar': kind = 'barh'
                elif kind == 'line': kind = 'barh' # Pandas line plots are strictly X-axis based
            
            # Plot
            fig, ax = plt.subplots(figsize=(10, 6))
            df.plot(kind=kind, ax=ax, title=title, rot=0)
            
            # Labels
            if axis == 'x':
                ax.set_xlabel(key_col)
                ax.set_ylabel("Values")
            else:
                ax.set_ylabel(key_col)
                ax.set_xlabel("Values")

            ax.legend(title="Legend")
            plt.grid(True, linestyle='--', alpha=0.5)
            plt.tight_layout()

            # Save Individual Image
            img_filename = f"{fname}_{config.get('job_id')}.png"
            img_path = os.path.join(output_dir, img_filename)
            plt.savefig(img_path, format='png', dpi=100)
            response['images'].append({
                "id": fname,
                "path": img_filename # Return filename only, PHP handles the full path
            })

            # Save to PDF
            pdf_pages.savefig(fig)
            plt.close(fig)

        except Exception as e:
            # Log error internally or print to stderr
            sys.stderr.write(f"Error processing {title}: {str(e)}\n")

    # Finalize PDF
    pdf_pages.close()
    response['pdf'] = pdf_filename
    
    return response

if __name__ == "__main__":
    try:
        # Read JSON file path from command line
        if len(sys.argv) < 2:
            print(json.dumps({"error": "No input file provided"}))
            sys.exit(1)

        input_file = sys.argv[1]
        
        with open(input_file, 'r') as f:
            config = json.load(f)

        result = process_charts(config)
        
        # Print result to stdout for PHP to capture
        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))