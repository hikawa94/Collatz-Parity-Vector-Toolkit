"""
Collatz_Calculator.py
Collatz Operation Calculator - Flask Web App

Computes the Collatz sequence for any large natural number.
Python's built-in int supports arbitrary precision (no GMP needed).
"""

from flask import Flask, render_template_string, request, send_from_directory
import os
import time
from datetime import datetime

app = Flask(__name__)

DATA_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')
os.makedirs(DATA_DIR, exist_ok=True)

HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collatz Operation Calculator</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f8f8f8; }
        h2 { text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .desc { width: 80%; margin: 0 auto 20px; line-height: 1.7; }
        form table { margin: 0 auto; }
        form td { padding: 6px 10px; vertical-align: middle; }
        input[type="text"] { width: 500px; padding: 6px; font-size: 15px; }
        input[type="submit"] { padding: 10px 36px; font-size: 16px; background: #0066cc; color: white; border: none; cursor: pointer; border-radius: 4px; }
        input[type="submit"]:hover { background: #0055aa; }
        .result-table { border-collapse: collapse; width: 95%; margin: 20px auto; background: white; }
        .result-table td { border: 0; padding: 3px 8px; white-space: nowrap; }
        .highlight { color: red; font-weight: bold; }
        .summary { color: red; }
        .time { color: #1e3a8a; font-weight: bold; }
        .error { color: red; text-align: center; font-weight: bold; }
        a { color: #0066cc; }
    </style>
</head>
<body>
<div class="container">
    <h2>Collatz Conjecture Calculator (Single Number)</h2>

    <div class="desc">
        <p>
            The Collatz conjecture states that for any positive integer, repeatedly applying the following operations will eventually reach 1:<br>
            &nbsp;&nbsp;· If the number is <b>even</b>: divide by 2<br>
            &nbsp;&nbsp;· If the number is <b>odd</b>: multiply by 3, add 1, then divide by 2 (always yields an even number)<br><br>
            This tool shows each step of the Collatz operation, highlights
            <font color="red">the first step where the value drops below the initial input</font>,
            and displays the total number of steps to reach 1, the Glide value, and the Parity Vector.
        </p>
    </div>

    <form method="POST" style="text-align:center; margin-bottom:20px;">
        <table>
            <tr>
                <td colspan="2">Enter any natural number (integer, any number of digits):</td>
            </tr>
            <tr>
                <td colspan="2">
                    <input type="text" name="txtN1"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"
                           placeholder="e.g. 27">
                </td>
            </tr>
            <tr>
                <td>Screen display:</td>
                <td>
                    <input type="radio" name="gamen" value="Yes" checked> Show all steps &nbsp;&nbsp;
                    <input type="radio" name="gamen" value="No"> Hide intermediate steps
                </td>
            </tr>
            <tr>
                <td>File output:</td>
                <td>
                    <input type="radio" name="ffout" value="Yes" checked> Save to ./data/ &nbsp;&nbsp;
                    <input type="radio" name="ffout" value="No"> Do not save
                </td>
            </tr>
            <tr>
                <td colspan="2" style="text-align:center; padding-top:10px;">
                    <input type="submit" value="Run Calculation">
                </td>
            </tr>
        </table>
    </form>

    {% if error %}
        <div class="error">
            <h3>Input Error</h3>
            <p>{{ error }}</p>
            <p>Please enter an integer greater than or equal to 2.</p>
        </div>
    {% endif %}

    {% if result %}
        <div style="text-align:center;">
            <p><font color="red">Input value = {{ result.input_val }}</font></p>
            <hr style="width:80%">
        </div>

        <table class="result-table">
            {% for row in result.rows %}
            <tr>
                {% if row.type == 'divider' %}
                    <td colspan="4"><hr style="border-top:1px dashed #888;"></td>
                {% elif row.type == 'summary' %}
                    <td colspan="4" class="summary">{{ row.text }}</td>
                {% elif row.type == 'time' %}
                    <td colspan="4" class="time">{{ row.text }}</td>
                {% elif row.highlight %}
                    <td>{{ row.op }}</td><td>=</td>
                    <td>{{ row.val }}</td>
                    <td class="highlight">(r={{ row.mod }}) &lt; {{ result.input_val }} &lt;--- step {{ result.M }}</td>
                {% else %}
                    <td>{{ row.op }}</td><td>=</td>
                    <td>{{ row.val }}</td>
                    <td>(r={{ row.mod }})</td>
                {% endif %}
            </tr>
            {% endfor %}
        </table>

        {% if result.dl_file %}
        <p style="text-align:center;">
            <a href="/download/{{ result.dl_file }}" download>📥 Download CSV Result</a>
        </p>
        {% endif %}
    {% endif %}

</div>
</body>
</html>
"""


@app.route('/', methods=['GET', 'POST'])
def index():
    error = None
    result = None

    if request.method == 'POST':
        txtN1 = request.form.get('txtN1', '').strip()
        gamen = request.form.get('gamen', 'Yes')
        ffout = request.form.get('ffout', 'No')

        # バリデーション
        if not txtN1.isdigit() or int(txtN1) < 2:
            error = f'"{txtN1}" is not a valid input.'
        else:
            n_init = int(txtN1)
            n1 = n_init
            n2 = n_init
            N  = n_init

            # ファイル出力の準備
            fp = None
            dl_file = None
            if ffout == 'Yes':
                mojiretu = txtN1[:200]
                fname = os.path.join(DATA_DIR, f'Collatz-{mojiretu}.csv')
                fp = open(fname, 'w', encoding='utf-8')
                fp.write(f'Collatz sequence for {n_init}\n')
                dl_file = os.path.basename(fname)

            first = True   # まだ初回収束マーク未表示
            flag  = False  # 初めてN未満になったか
            n     = 0      # ステップ数
            M     = 0      # Glide
            R     = 0
            parity_vector = ''
            rows  = []

            time_start = time.time()

            while True:
                if n2 <= 1:
                    break
                n += 1

                if n2 % 2 == 0:
                    # 偶数：÷2
                    n1     = n2
                    n2     = n2 // 2
                    module = n2 % 32
                    parity_vector += '0'
                    op = f'{n1} / 2'

                    if not flag and first and n2 < N:
                        flag = True
                        M = n
                        R = n2

                    if flag and first:
                        rows.append({'type': 'step', 'op': op, 'val': str(n2), 'mod': str(module), 'highlight': True})
                        if fp: fp.write(f'{n1} / 2 = {n2} (r={module}) < {N} <--- step {M}\n')
                        first = False
                    else:
                        if gamen == 'Yes':
                            rows.append({'type': 'step', 'op': op, 'val': str(n2), 'mod': str(module), 'highlight': False})
                        if fp: fp.write(f'{n1} / 2 = {n2} (r={module})\n')

                else:
                    # 奇数：(×3+1)÷2
                    n1     = n2
                    n2     = (n2 * 3 + 1) // 2
                    module = n2 % 32
                    parity_vector += '1'
                    op = f'({n1} * 3 + 1) / 2'

                    if not flag and first and n2 < N:
                        flag = True
                        M = n
                        R = n2

                    if flag and first:
                        rows.append({'type': 'step', 'op': op, 'val': str(n2), 'mod': str(module), 'highlight': True})
                        if fp: fp.write(f'({n1} * 3 + 1) / 2 = {n2} (r={module}) < {N} <--- step {M}\n')
                        first = False
                    else:
                        if gamen == 'Yes':
                            rows.append({'type': 'step', 'op': op, 'val': str(n2), 'mod': str(module), 'highlight': False})
                        if fp: fp.write(f'({n1} * 3 + 1) / 2 = {n2} (r={module})\n')

            elapsed = round(time.time() - time_start, 6)
            parity_vector_moji = parity_vector[:M]

            rows.append({'type': 'divider'})
            rows.append({'type': 'summary', 'text': f'Steps to reach 1: {n}'})
            rows.append({'type': 'summary', 'text': f'Glide({N}) = {M}'})
            rows.append({'type': 'summary', 'text': f'Parity Vector up to Glide: ({parity_vector_moji})'})
            rows.append({'type': 'time',    'text': f'Processing Time: {elapsed} seconds'})

            if fp:
                fp.write(f'Steps to reach 1: {n}\n')
                fp.write(f'Glide({N}) = {M}\n')
                fp.write(f'Parity Vector up to Glide: ({parity_vector_moji})\n')
                fp.close()

            result = {
                'input_val': str(n_init),
                'rows':      rows,
                'M':         M,
                'dl_file':   dl_file,
            }

    return render_template_string(HTML_TEMPLATE, error=error, result=result)


@app.route('/download/<filename>')
def download(filename):
    return send_from_directory(DATA_DIR, filename, as_attachment=True)


if __name__ == '__main__':
    print("Starting Collatz Calculator")
    print("Access: http://127.0.0.1:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
