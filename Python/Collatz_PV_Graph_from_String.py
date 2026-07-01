"""
Collatz_PV_Graph.py
Parity Vector Graph & Detailed Information - Flask Web App

- Displays a graphical matrix of the parity vector path
- Computes Generator, Resultant, Glide for each prefix of the PV
- Saves detailed results to ./data/ as an HTML file
"""

from flask import Flask, render_template_string, request, send_from_directory
import os
import re
import math
from datetime import datetime

app = Flask(__name__)

DATA_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')
os.makedirs(DATA_DIR, exist_ok=True)


def glide_check(number: int) -> int:
    """Compute the Glide (first step where value drops below initial N)"""
    M = 0
    first = True
    flag = False
    n = 0
    N = number
    n2 = number

    while True:
        if n2 <= 1:
            break
        n += 1

        if n2 % 2 == 0:
            n2 = n2 // 2
            if not flag and first and n2 < N:
                flag = True
                M = n
            if flag and first:
                first = False
        else:
            n2 = (n2 * 3 + 1) // 2
            if not flag and first and n2 < N:
                flag = True
                M = n

    return M


HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collatz Parity Vector Graph</title>
    <style>
        body  { font-family: monospace; margin: 20px; background: #f8f8f8; }
        h2    { text-align: center; }
        .container { max-width: 100%; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        form  { margin: 10px 0 16px; }
        input[type="text"]   { width: 600px; padding: 6px; font-size: 14px; }
        input[type="submit"] { padding: 8px 28px; font-size: 15px; background: #0066cc; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px; }
        input[type="submit"]:hover { background: #0055aa; }
        .pv-table { border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        .pv-table th, .pv-table td { border: 1px solid #888; padding: 3px 6px; text-align: center; white-space: nowrap; }
        .pv-table th { background: #e0e0e0; }
        .pv-header-row th { background: orange; color: black; }
        .pv-header-row th:first-child { background: white; font-size: 11px; }
        .yellow { background: yellow; }
        .red-bold { color: red; font-weight: bold; }
        .detail-table { border-collapse: collapse; margin: 20px 0; width: auto; }
        .detail-table th, .detail-table td { border: 1px solid #666; padding: 5px 10px; }
        .detail-table th { background: #ddd; }
        .just { color: red; font-weight: bold; }
        .msg  { margin: 10px 0; font-size: 14px; }
        a     { color: #0066cc; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>Collatz Parity Vector Graph &amp; Detailed Information</h2>

    <form method="POST">
        <b>Enter Parity Vector (0s and 1s only):</b><br>
        <input type="text" name="pattern" value=""
               placeholder="e.g. 10110101" pattern="[01 ]+">
        <input type="submit" value="Execute">
    </form>

    {% if bit_length == 0 %}
        <p class="msg">Please enter a parity vector above and press Execute.</p>
    {% else %}
        <div style="display:flex; align-items:flex-start; gap:25px;">
        <table class="pv-table">
            <tr class="pv-header-row">
                <th><span style="font-size:11px">PV &rarr;</span>&nbsp;</th>
                {% for bit in pat_table[1:] %}
                <th>{{ bit }}</th>
                {% endfor %}
            </tr>
            <tr>
                <th style="white-space:nowrap">k &nbsp; d</th>
                {% for j in col_headers %}
                <th>&ensp;{{ j }}</th>
                {% endfor %}
            </tr>
            {% for row in graph_rows %}
            <tr>
                <th>{{ row.i }}</th>
                {% for cell in row.cells %}
                <td{% if cell.cls %} class="{{ cell.cls }}"{% endif %}>{{ cell.val|safe }}</td>
                {% endfor %}
            </tr>
            {% endfor %}
        </table>

        <div style="max-width:380px; background:#fff8e1; border:1px solid #d4a017; border-radius:8px; padding:14px 18px; font-size:13px; line-height:1.7;">
            <b>(Explanation)</b><br>
            A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that it is an unconverged PV.<br><br>
            The Parity Vector that continues past the "0" cells will be already converged.<br><br>
            Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>, and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.
        </div>
        </div>

        <p class="msg">
            <b>Input PV:</b> {{ bit_pattern }}<br>
            <b>Length:</b> {{ bit_length }} &nbsp; <b>Number of Ones:</b> {{ bit_odd }}<br>
            Detailed result file:
            <a href="/data/{{ out_fname }}" target="_blank">📄 Open in new tab</a> &nbsp;|&nbsp;
            <a href="/download/{{ out_fname }}" download>📥 Download</a>
        </p>

        <h3>Detailed Information on Input PV</h3>

        {% if bit_length <= 100 %}
        <p><b>Input PV = </b>{{ bit_pattern }}</p>
        {% else %}
        <p><b>Input PV =</b><br>
        {% for chunk in pv_chunks %}
        {{ chunk }}<br>
        {% endfor %}
        </p>
        {% endif %}

        <table class="detail-table">
            <tr>
                <th>Ratio<br>(Glide/Length)</th>
                <th>Length from<br>beginning of PV</th>
                <th>Number of Ones</th>
                <th>Generator</th>
                <th>Pre-Resultant</th>
                <th>Resultant</th>
                <th>Glide<br>(Convergence Time)</th>
            </tr>
            {% for row in detail_rows %}
            <tr{% if row.just %} class="just"{% endif %}>
                <td align="center">{% if row.just %}<b>{{ row.ratio }}</b>{% else %}{{ row.ratio }}{% endif %}</td>
                <td align="center">{{ row.length }}</td>
                <td align="center">{{ row.ones }}</td>
                <td>{{ row.gen }}</td>
                <td>{{ row.pre_res }}</td>
                <td>{{ row.res }}</td>
                {% if row.just %}
                <td><b>{{ row.glide }} (Just Converged)</b></td>
                {% else %}
                <td>{{ row.glide }}</td>
                {% endif %}
            </tr>
            {% endfor %}
        </table>
    {% endif %}

</div>
</body>
</html>
"""


@app.route('/', methods=['GET', 'POST'])
def index():
    bit_pattern = ''
    bit_length = 0
    bit_odd = 0
    pat_table = [' ']
    col_headers = []
    graph_rows = []
    detail_rows = []
    pv_chunks = []
    out_fname = ''

    if request.method == 'POST':
        raw = request.form.get('pattern', '')
        raw = raw.replace(' ', '')
        bit_pattern = re.sub(r'[^01]', '', raw.strip())
        bit_length = len(bit_pattern)
        bit_odd = bit_pattern.count('1')

        if bit_length > 0:
            # ====================== Build Graph Table ======================
            table_max = bit_length + 10
            for i in range(bit_length):
                pat_table.append(bit_pattern[i])

            bit_table = {}
            bit_table[0] = {j: j for j in range(table_max + 1)}

            zen_d = -1
            for i in range(1, table_max + 1):
                d = int(i * math.log(2) / math.log(3))
                bit_table[i] = {}
                gyo_end = False
                for j in range(table_max + 1):
                    if j == d:
                        if zen_d != d:
                            bit_table[i][j] = '0'
                            zen_d = d
                        else:
                            bit_table[i][j] = ' '
                    elif j == i + 1:
                        bit_table[i][j] = '*'
                        gyo_end = True
                    else:
                        bit_table[i][j] = ' '
                    if gyo_end:
                        break

            # Overlay PV bits
            pos_j = 0
            for i in range(bit_length):
                pos_i = i + 1
                bit = pat_table[i + 1]
                if bit_table.get(i, {}).get(i) == '*':
                    continue
                if bit == '0':
                    bit_table[pos_i][pos_j] = '<span class="red-bold">0</span>'
                else:
                    bit_table[pos_i][pos_j + 1] = '<span class="red-bold">1</span>'
                    pos_j += 1

            # Column headers row (limit display similarly to PHP version)
            col_headers = list(range(0, min(table_max, bit_length + 1) + 1))

            # Data rows
            zen_d = -1
            for i in range(1, table_max + 1):
                d = int(i * math.log(2) / math.log(3))
                cells = []
                for j in range(table_max + 1):
                    cell_val = bit_table.get(i, {}).get(j, ' ')
                    cls = ''
                    if j == d:
                        if zen_d != d:
                            cls = 'yellow'
                            zen_d = d
                    cells.append({'val': cell_val, 'cls': cls})
                    if cell_val == '*':
                        break
                graph_rows.append({'i': i, 'cells': cells})

            # ====================== Compute Detailed Info ======================
            first_bit = bit_pattern[0]

            if first_bit == '1':
                pre_length = 1
                pre_ones = 1
                pre_generator = 1
                pre_zen_resultant = 1
                pre_resultant = 2
                pre_oddeven = 0  # even
                pre_glide = 2
                pre_power_of_2 = 2 ** pre_length
            else:
                pre_length = 1
                pre_ones = 0
                pre_generator = 2
                pre_zen_resultant = 1
                pre_resultant = 1
                pre_oddeven = 1  # odd
                pre_glide = 1
                pre_power_of_2 = 2 ** pre_length

            # Prepare output file
            timestamp = datetime.now().strftime('%Y-%m-%d-%H-%M')
            out_fname = f"GeneratorForPVstring{bit_length}_{timestamp}.html"
            out_fpath = os.path.join(DATA_DIR, out_fname)

            file_lines = []
            file_lines.append("<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n")
            file_lines.append("<title>Result detailed information on the input PV</title>\n</head>\n<body>\n")
            file_lines.append("<h3 style='text-align:center'>Result detailed information on the input PV</h3>\n")
            file_lines.append("<table border='1'>\n")

            if bit_length <= 100:
                file_lines.append(f"<tr><td colspan='7'>Input PV = {bit_pattern}</td></tr>\n")
            else:
                pv_display = "<tr><td colspan='7'>Input PV =<br>"
                for jj in range(math.ceil(bit_length / 100)):
                    chunk = bit_pattern[jj * 100:(jj + 1) * 100]
                    pv_display += ('<br>' if jj > 0 else '') + chunk
                file_lines.append(pv_display + "</td></tr>\n")

            file_lines.append("""<tr>
                <th>Ratio<br>(Glide/Length)</th>
                <th>Length from<br>beginning of PV</th>
                <th>Number of Ones</th>
                <th>Generator</th>
                <th>Pre-Resultant</th>
                <th>Resultant</th>
                <th>Glide<br>(Convergence Time)</th>
            </tr>\n""")

            # First row
            if first_bit == '0':
                file_lines.append("<tr><td align='center'><font color='red'><b>1.00</b></font></td><td align='center'>1</td><td align='center'>0</td><td>2</td><td>2</td><td>1</td><td><font color='red'>1 (Just Converged)</font></td></tr>\n")
                detail_rows.append({'ratio': '1.00', 'length': 1, 'ones': 0, 'gen': '2', 'pre_res': '2', 'res': '1', 'glide': 1, 'just': True})
            else:
                file_lines.append("<tr><td align='center'><b>-</b></td><td align='center'>1</td><td align='center'>1</td><td>1</td><td>1</td><td>2</td><td><b>-</b></td></tr>\n")
                detail_rows.append({'ratio': '-', 'length': 1, 'ones': 1, 'gen': '1', 'pre_res': '1', 'res': '2', 'glide': '-', 'just': False})

            for ii in range(2, bit_length + 1):
                gen_length = ii
                gen_pattern = bit_pattern[:ii]
                gen_ones = gen_pattern.count('1')
                cur_bit = int(gen_pattern[ii - 1])

                if cur_bit == pre_oddeven:
                    gen_generator = pre_generator
                    gen_zen_resultant = pre_resultant
                    if gen_zen_resultant % 2 == 1:
                        gen_resultant = (3 * gen_zen_resultant + 1) // 2
                    else:
                        gen_resultant = gen_zen_resultant // 2
                    gen_glide = pre_glide
                else:
                    gen_generator = pre_generator + pre_power_of_2
                    gen_glide = glide_check(gen_generator)
                    ws_ones = 3 ** pre_ones
                    gen_zen_resultant = pre_resultant + ws_ones
                    if gen_zen_resultant % 2 == 1:
                        gen_resultant = (3 * gen_zen_resultant + 1) // 2
                    else:
                        gen_resultant = gen_zen_resultant // 2

                data_e = (gen_glide / gen_length) if (gen_glide > 0 and gen_length > 0) else 0.0
                gen_ratio = f"{data_e:.2f}"
                gen_oddeven = gen_resultant % 2
                gen_power_of_2 = pre_power_of_2 * 2
                just = (data_e == 1.0)

                if just:
                    file_lines.append(f"<tr><td align='center'><font color='red'><b>{gen_ratio}</b></font></td><td align='center'>{gen_length}</td><td align='center'>{gen_ones}</td><td>{gen_generator}</td><td>{gen_zen_resultant}</td><td>{gen_resultant}</td><td><font color='red'>{gen_glide} (Just Converged)</font></td></tr>\n")
                else:
                    file_lines.append(f"<tr><td align='center'>{gen_ratio}</td><td align='center'>{gen_length}</td><td align='center'>{gen_ones}</td><td>{gen_generator}</td><td>{gen_zen_resultant}</td><td>{gen_resultant}</td><td>{gen_glide}</td></tr>\n")

                detail_rows.append({
                    'ratio': gen_ratio,
                    'length': gen_length,
                    'ones': gen_ones,
                    'gen': str(gen_generator),
                    'pre_res': str(gen_zen_resultant),
                    'res': str(gen_resultant),
                    'glide': gen_glide,
                    'just': just,
                })

                # Update pre_ variables
                pre_length = gen_length
                pre_ones = gen_ones
                pre_generator = gen_generator
                pre_zen_resultant = gen_zen_resultant
                pre_resultant = gen_resultant
                pre_oddeven = gen_oddeven
                pre_glide = gen_glide
                pre_power_of_2 = gen_power_of_2

            file_lines.append("</table>\n</body>\n</html>\n")

            with open(out_fpath, 'w', encoding='utf-8') as f:
                f.write(''.join(file_lines))

            # PV chunks for inline display (>100 chars)
            if bit_length > 100:
                for jj in range(math.ceil(bit_length / 100)):
                    pv_chunks.append(bit_pattern[jj * 100:(jj + 1) * 100])

    return render_template_string(
        HTML_TEMPLATE,
        bit_pattern=bit_pattern,
        bit_length=bit_length,
        bit_odd=bit_odd,
        pat_table=pat_table,
        col_headers=col_headers,
        graph_rows=graph_rows,
        detail_rows=detail_rows,
        pv_chunks=pv_chunks,
        out_fname=out_fname,
    )


@app.route('/data/<filename>')
def view_data(filename):
    return send_from_directory(DATA_DIR, filename)


@app.route('/download/<filename>')
def download(filename):
    return send_from_directory(DATA_DIR, filename, as_attachment=True)


if __name__ == '__main__':
    print("Starting Collatz Parity Vector Graph")
    print("Access: http://127.0.0.1:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
