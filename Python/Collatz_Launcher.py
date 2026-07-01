"""
Collatz_Launcher.py
Unified Launcher for the Collatz Conjecture Tool Suite (Flask Web App)

Combines the following 5 tools into a single application with a menu:
  1. Counting by Length
  2. Counting by Hamming Weight
  3. Collatz Calculator
  4. Parity Vector Graph (from PV string)
  5. Parity Vector Graph (from Number)
"""

from flask import Flask, render_template_string, request, send_from_directory
import os
import re
import math
import time
from datetime import datetime
from decimal import Decimal, getcontext

app = Flask(__name__)

DATA_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')
os.makedirs(DATA_DIR, exist_ok=True)

LAMBDA = math.log(3) / math.log(2)  # log2(3) ≈ 1.58496

# ============================================================
# Shared layout (menu + footer)
# ============================================================

BASE_STYLE = """
<style>
    body  { font-family: monospace; margin: 0; padding: 0; background: #f4f6f9; }
    .topbar { background: #1e3a8a; padding: 16px 24px; }
    .topbar h1 { color: white; margin: 0; font-size: 22px; text-align: center; }
    .menu { background: #163066; display: flex; justify-content: center; flex-wrap: wrap; }
    .menu a { color: #cfe0ff; text-decoration: none; padding: 12px 18px; font-size: 14px; }
    .menu a:hover, .menu a.active { background: #0d1f47; color: white; }
    .container { max-width: 1300px; margin: 20px auto; background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
    h2 { text-align: center; color: #1e3a8a; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #444; padding: 10px; text-align: center; }
    th { background: #1e3a8a; color: white; }
    td.left { text-align: left; }
    .sci { font-size: 0.95em; color: #d32f2f; font-weight: bold; }
    .time { font-size: 16px; color: #1e3a8a; font-weight: bold; text-align: center; }
    .error { color: red; text-align: center; font-weight: bold; }
    .desc { width: 85%; margin: 0 auto 20px; line-height: 1.7; }
    form { text-align: center; margin: 20px 0; }
    input[type="text"], input[type="number"] { padding: 6px; font-size: 14px; }
    input[type="submit"], button { padding: 10px 30px; font-size: 15px; background: #0066cc; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px; }
    input[type="submit"]:hover, button:hover { background: #0055aa; }
    .pv-table { border-collapse: collapse; margin: 16px 0; font-size: 13px; }
    .pv-table th, .pv-table td { border: 1px solid #888; padding: 3px 6px; text-align: center; white-space: nowrap; }
    .pv-header-row th { background: orange; color: black; }
    .pv-header-row th:first-child { background: white; font-size: 11px; }
    .yellow { background: yellow; }
    .red-bold { color: red; font-weight: bold; }
    .explain-box { max-width: 380px; background:#fff8e1; border:1px solid #d4a017; border-radius:8px; padding:14px 18px; font-size:13px; line-height:1.7; }
    .result-table { border-collapse: collapse; width: 95%; margin: 20px auto; background: white; }
    .result-table td { border: 0; padding: 3px 8px; white-space: nowrap; text-align:left; }
    .highlight { color: red; font-weight: bold; }
    .summary { color: red; text-align:left; }
    a.dl { color: #0066cc; }
    .footer { text-align: center; color: #888; font-size: 12px; margin: 30px 0; }
</style>
"""

MENU_ITEMS = [
    ('home', '/', 'Home'),
    ('length', '/length', '1. Counting by Length'),
    ('hamming', '/hamming', '2. Counting by Hamming Weight'),
    ('calculator', '/calculator', '3. Collatz Calculator'),
    ('pvgraph', '/pvgraph', '4. PV Graph (from PV string)'),
    ('numgraph', '/numgraph', '5. PV Graph (from Number)'),
]


def render_page(active: str, title: str, body: str) -> str:
    menu_html = ''.join(
        f'<a href="{url}" class="{"active" if key == active else ""}">{label}</a>'
        for key, url, label in MENU_ITEMS
    )
    return f"""
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{title} - Collatz Conjecture Tool Suite</title>
    {BASE_STYLE}
</head>
<body>
    <div class="topbar"><h1>Collatz Conjecture Tool Suite</h1></div>
    <div class="menu">{menu_html}</div>
    <div class="container">
        {body}
    </div>
    <div class="footer">Collatz Conjecture Tool Suite &mdash; Unified Launcher (Python / Flask)</div>
</body>
</html>
"""


# ============================================================
# Home page
# ============================================================

@app.route('/')
def home():
    body = """
    <h2>Welcome</h2>
    <div class="desc">
        <p>This launcher combines five tools for exploring the Collatz Conjecture
        and the structure of Parity Vectors (PVs). Select a tool from the menu above:</p>
        <ol>
            <li><b>Counting by Length</b> &mdash; Counts converged/unconverged Parity Vectors for each bit length k.</li>
            <li><b>Counting by Hamming Weight</b> &mdash; Counts converged/unconverged Parity Vectors for each Hamming weight d.</li>
            <li><b>Collatz Calculator</b> &mdash; Computes and displays the full Collatz sequence for any natural number.</li>
            <li><b>PV Graph (from PV string)</b> &mdash; Displays a graphical matrix for a manually entered Parity Vector.</li>
            <li><b>PV Graph (from Number)</b> &mdash; Computes the Parity Vector for a natural number N and displays it graphically.</li>
        </ol>
        <p>All results that are saved to disk are stored in the shared <code>data/</code> folder next to this program.</p>
    </div>
    """
    return render_page('home', 'Home', body)


# ============================================================
# Tool 1: Counting by Length
# ============================================================

def ratio_sci(D: int, A: int, precision: int = 15) -> str:
    if D == 0 or A == 0:
        return "0.0e+0"
    exp_estimate = len(str(A)) - len(str(D))
    scale = max(exp_estimate + precision + 10, precision + 30)
    getcontext().prec = scale + 10
    ratio = Decimal(D) / Decimal(A)
    ratio_str = format(ratio, f'.{scale}f')

    if not ratio_str.startswith('0.'):
        parts = ratio_str.split('.')
        int_part = parts[0]
        frac_part = parts[1] if len(parts) > 1 else ''
        exp = len(int_part) - 1
        all_digits = (int_part + frac_part).lstrip('0')
        mant = all_digits[0] + '.' + all_digits[1:precision + 1]
        return f"{mant}e+{exp}"

    frac = ratio_str.split('.')[1]
    exp = 0
    digits = ''
    leading = True
    for ch in frac:
        if leading and ch == '0':
            exp -= 1
        else:
            leading = False
            digits += ch
    if not digits:
        return "0.0e+0"
    exp -= 1
    mant = digits[0] + '.' + digits[1:precision + 1]
    return f"{mant}e{exp}"


def sci_short(num: int) -> str:
    s = str(num)
    if len(s) <= 8:
        return f"{num:,}"
    exp = len(s) - 1
    mant = int(s[:6]) / 100000
    return f"{mant:.5f}e+{exp}"


@app.route('/length', methods=['GET', 'POST'])
def length_tool():
    body = """
    <h2>1. Counting by Length</h2>
    <div class="desc">Counts the number of converged and unconverged Parity Vectors for each bit length k.</div>
    <form method="POST">
        <label>Start (A): <input type="number" name="A" value="" min="1" max="10000" required></label>
        <label>End (B): <input type="number" name="B" value="" min="1" max="10000" required></label>
        <label>Ratio Precision: <input type="number" name="PREC" value="15" min="5" max="50" required></label>
        <input type="submit" value="Start Calculation">
    </form>
    """

    if request.method == 'POST':
        k_start = max(1, min(10000, int(request.form.get('A', 1))))
        k_end = max(1, min(10000, int(request.form.get('B', 200))))
        ratio_prec = max(5, min(50, int(request.form.get('PREC', 15))))

        if k_end < k_start:
            body += '<p class="error">Error: B must be &ge; A</p>'
            return render_page('length', 'Counting by Length', body)

        start_time = time.time()
        start_time_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        W_total = [0] * (k_end + 1)
        X_total = [0] * (k_end + 1)
        W = [{} for _ in range(k_end + 1)]
        X = [{} for _ in range(k_end + 1)]
        CON = {}

        W[1][1] = 1
        X[1][0] = 1
        X[2][1] = 1
        W_total[1] = 1
        X_total[1] = 1
        X_total[2] = 1
        CON[1] = 1
        CON[2] = 2

        for k in range(2, k_end + 1):
            for d in range(0, k + 1):
                boundary = int(math.floor(d * LAMBDA)) + 1
                if k < boundary:
                    W[k][d] = W[k-1].get(d, 0) + W[k-1].get(d-1, 0)
                W_total[k] += W[k].get(d, 0)

        for k in range(4, k_end + 1):
            for d in range(2, k):
                boundary = int(math.floor(d * LAMBDA)) + 1
                if k == boundary:
                    X[k][d] = W[k-1].get(d, 0) + W[k].get(d-1, 0)
                    CON[k] = boundary
                X_total[k] += X[k].get(d, 0)

        rows_html = ""
        txt_lines = ["k\tConvSteps\tA_TotalPVs\tB_Converged\tC_AlreadyConv\tD_Unconverged\tRatio"]

        for k in range(k_start, k_end + 1):
            A_val = 2 ** k
            B_val = X_total[k]
            D_val = W_total[k]
            C_val = A_val - B_val - D_val
            ratio = ratio_sci(D_val, A_val, ratio_prec)

            rows_html += f"""<tr>
                <td><b>{k}</b></td>
                <td>{CON.get(k, '-')}</td>
                <td class="left">{sci_short(A_val)}<br><small>{A_val}</small></td>
                <td class="left">{sci_short(B_val)}<br><small>{B_val}</small></td>
                <td class="left">{sci_short(C_val)}<br><small>{C_val}</small></td>
                <td class="left">{sci_short(D_val)}<br><small>{D_val}</small></td>
                <td class="left"><span class="sci">{ratio}</span></td>
            </tr>"""
            txt_lines.append(f"{k}\t{CON.get(k, '-')}\t{A_val}\t{B_val}\t{C_val}\t{D_val}\t{ratio}")

        filename = f"PV_Length_{k_start}_{k_end}.txt"
        with open(os.path.join(DATA_DIR, filename), 'w', encoding='utf-8') as f:
            f.write('\n'.join(txt_lines))

        duration = round(time.time() - start_time, 2)
        end_time_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        body += f"""
        <p class="time">Start Time: {start_time_str}</p>
        <table>
            <tr>
                <th>k</th><th>Conv Steps</th><th>(A) Total PVs</th>
                <th>(B) Converged</th><th>(C) Already Conv.</th>
                <th>(D) Unconverged</th><th>(E) Ratio D/A</th>
            </tr>
            {rows_html}
        </table>
        <p class="time">End Time: {end_time_str}<br><strong>Processing Time: {duration} seconds</strong></p>
        <p style="text-align:center"><a class="dl" href="/download/{filename}" download>📥 Download TXT Result</a></p>
        """

    return render_page('length', 'Counting by Length', body)


# ============================================================
# Tool 2: Counting by Hamming Weight
# ============================================================

def gmp_to_sci(n: int) -> str:
    if n == 0:
        return "0.000000e+0"
    s = str(n)
    exp = len(s) - 1
    mant = s[:7]
    num = int(mant) / 1_000_000
    return f"{num:.6f}e+{exp}"


def kmin(d: int) -> int:
    return math.ceil(LAMBDA * d)


@app.route('/hamming', methods=['GET', 'POST'])
def hamming_tool():
    body = """
    <h2>2. Counting by Hamming Weight</h2>
    <div class="desc">Counts the number of converged and unconverged Parity Vectors for each Hamming weight d.</div>
    <form method="POST">
        <label>Start "Hamming Weight" (A): <input type="number" name="A" value="" min="1" max="10000" required></label>
        <label>End "Hamming Weight" (B): <input type="number" name="B" value="" min="1" max="10000" required></label>
        <input type="submit" value="Execute">
    </form>
    """

    if request.method == 'POST':
        d_start = max(1, min(10000, int(request.form.get('A', 1))))
        d_end = max(1, min(10000, int(request.form.get('B', 20))))

        if d_end < d_start:
            body += '<p class="error">Error: Please set A &ge; 1 and B &ge; A.</p>'
            return render_page('hamming', 'Counting by Hamming Weight', body)

        start_time = time.time()
        time_start = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

        k_upper = int(math.floor(d_end * LAMBDA)) + 1
        W = {i: {j: 0 for j in range(-1, k_upper + 2)} for i in range(k_upper + 1)}
        W_total = [0] * (k_upper + 1)

        W[1][0] = 1
        W_total[0] = 1
        W_total[1] = 1

        out_file = os.path.join(DATA_DIR, f"NumPVofOnes-{d_start}-{d_end}.txt")
        rows_html = ""

        with open(out_file, 'w', encoding='utf-8') as fp:
            fp.write(f"Collatz Parity Vector Counter - d = {d_start} to {d_end}\n\n")

            for d in range(2, d_end + 1):
                if d >= d_start:
                    fp.write(f"d: Hamming weight (number of ones) = {d}\n")
                u_max = int(math.floor(d * LAMBDA)) + 1

                for u in range(u_max + 1):
                    k = d + u
                    e = 1 if k < u_max else 0
                    if e == 1:
                        W[d][u] = W[d-1].get(u, 0) + W[d].get(u-1, 0)
                    else:
                        W[d][u] = 0
                    W_total[d] += W[d][u]
                    if d >= d_start and W[d][u] > 0:
                        fp.write(f"  length={k}, count={W[d][u]}\n")

                if d >= d_start:
                    fp.write(f"  W({d}) total = {W_total[d]}\n\n")
                    km = kmin(d)
                    xd = W_total[d - 1]
                    wd = W_total[d]
                    pow2 = 1 << km
                    rho = wd / pow2 if wd > 0 else 0.0
                    rows_html += f"""<tr>
                        <td>{d}</td><td>{km}</td>
                        <td class="left">{gmp_to_sci(xd)}<br><small>{xd}</small></td>
                        <td class="left">{gmp_to_sci(wd)}<br><small>{wd}</small></td>
                        <td><span class="sci">{rho:.4e}</span></td>
                    </tr>"""

        time_end = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        duration = round(time.time() - start_time, 2)
        fname = os.path.basename(out_file)

        body += f"""
        <p class="time">Calculation Time: {time_start} &rarr; {time_end}<br><strong>Processing Time: {duration} seconds</strong></p>
        <p style="text-align:center"><a class="dl" href="/download/{fname}" download>📥 Download TXT Result</a></p>
        <table>
            <tr><th>Hamming Weight d</th><th>kmin(d)</th><th>(A) X(d) = Just Converged</th><th>(B) W(d) = Unconverged</th><th>&rho;<sub>d</sub> = W(d)/2<sup>kmin</sup></th></tr>
            {rows_html}
        </table>
        """

    return render_page('hamming', 'Counting by Hamming Weight', body)


# ============================================================
# Tool 3: Collatz Calculator
# ============================================================

@app.route('/calculator', methods=['GET', 'POST'])
def calculator_tool():
    body = """
    <h2>3. Collatz Calculator</h2>
    <div class="desc">
        Computes the Collatz sequence for any large natural number, step by step,
        highlighting the first point where the value drops below the initial input,
        and reports the Glide and Parity Vector.
    </div>
    <form method="POST">
        <table style="width:auto; margin:0 auto; background:transparent; border:none;">
        <tr><td colspan="2">Enter any natural number (integer, any number of digits):</td></tr>
        <tr><td colspan="2"><input type="text" name="txtN1" size="60"
               oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="e.g. 27"></td></tr>
        <tr><td>Screen display:</td><td>
            <input type="radio" name="gamen" value="Yes" checked> Show all steps
            <input type="radio" name="gamen" value="No"> Hide intermediate steps
        </td></tr>
        <tr><td>File output:</td><td>
            <input type="radio" name="ffout" value="Yes" checked> Save to data/
            <input type="radio" name="ffout" value="No"> Do not save
        </td></tr>
        <tr><td colspan="2" style="text-align:center; padding-top:10px;"><input type="submit" value="Run Calculation"></td></tr>
        </table>
    </form>
    """

    if request.method == 'POST':
        txtN1 = request.form.get('txtN1', '').strip()
        gamen = request.form.get('gamen', 'Yes')
        ffout = request.form.get('ffout', 'No')

        if not txtN1.isdigit() or int(txtN1) < 2:
            body += f'<p class="error">"{txtN1}" is not a valid input.</p>'
        else:
            n_init = int(txtN1)
            n2 = n_init
            N = n_init

            fp = None
            dl_file = None
            if ffout == 'Yes':
                mojiretu = txtN1[:200]
                fname = os.path.join(DATA_DIR, f'Collatz-{mojiretu}.csv')
                fp = open(fname, 'w', encoding='utf-8')
                fp.write(f'Collatz sequence for {n_init}\n')
                dl_file = os.path.basename(fname)

            first = True
            flag = False
            n = 0
            M = 0
            parity_vector = ''
            rows = []
            time_start = time.time()

            while n2 > 1:
                n += 1
                if n2 % 2 == 0:
                    n1 = n2
                    n2 = n2 // 2
                    module = n2 % 32
                    parity_vector += '0'
                    op = f'{n1} / 2'
                    if not flag and first and n2 < N:
                        flag, M = True, n
                    if flag and first:
                        rows.append((op, n2, module, True))
                        if fp: fp.write(f'{n1} / 2 = {n2} (r={module}) < {N} <--- step {M}\n')
                        first = False
                    else:
                        if gamen == 'Yes':
                            rows.append((op, n2, module, False))
                        if fp: fp.write(f'{n1} / 2 = {n2} (r={module})\n')
                else:
                    n1 = n2
                    n2 = (n2 * 3 + 1) // 2
                    module = n2 % 32
                    parity_vector += '1'
                    op = f'({n1} * 3 + 1) / 2'
                    if not flag and first and n2 < N:
                        flag, M = True, n
                    if flag and first:
                        rows.append((op, n2, module, True))
                        if fp: fp.write(f'({n1} * 3 + 1) / 2 = {n2} (r={module}) < {N} <--- step {M}\n')
                        first = False
                    else:
                        if gamen == 'Yes':
                            rows.append((op, n2, module, False))
                        if fp: fp.write(f'({n1} * 3 + 1) / 2 = {n2} (r={module})\n')

            elapsed = round(time.time() - time_start, 6)
            pv_until_glide = parity_vector[:M]

            rows_html = ""
            for op, val, mod, hl in rows:
                if hl:
                    rows_html += f'<tr><td>{op}</td><td>=</td><td>{val}</td><td class="highlight">(r={mod}) &lt; {N} &lt;--- step {M}</td></tr>'
                else:
                    rows_html += f'<tr><td>{op}</td><td>=</td><td>{val}</td><td>(r={mod})</td></tr>'

            if fp:
                fp.write(f'Steps to reach 1: {n}\nGlide({N}) = {M}\nParity Vector up to Glide: ({pv_until_glide})\n')
                fp.close()

            body += f"""
            <div style="text-align:center;"><p><font color="red">Input value = {n_init}</font></p><hr style="width:80%"></div>
            <table class="result-table">
                {rows_html}
                <tr><td colspan="4"><hr></td></tr>
                <tr><td colspan="4" class="summary">Steps to reach 1: <b>{n}</b></td></tr>
                <tr><td colspan="4" class="summary">Glide({N}) = <b>{M}</b></td></tr>
                <tr><td colspan="4" class="summary">Parity Vector up to Glide: <b>({pv_until_glide})</b></td></tr>
                <tr><td colspan="4" class="time">Processing Time: {elapsed} seconds</td></tr>
            </table>
            """
            if dl_file:
                body += f'<p style="text-align:center"><a class="dl" href="/download/{dl_file}" download>📥 Download CSV Result</a></p>'

    return render_page('calculator', 'Collatz Calculator', body)


# ============================================================
# Tool 4: PV Graph (from PV string)
# ============================================================

def glide_check_from_number(number: int):
    M = 0
    first = True
    flag = False
    n = 0
    N = number
    n2 = number
    parity_vector = ''

    while n2 > 1:
        n += 1
        if n2 % 2 == 0:
            n2 = n2 // 2
            parity_vector += '0'
            if not flag and first and n2 < N:
                flag, M = True, n
            if flag and first:
                first = False
        else:
            n2 = (n2 * 3 + 1) // 2
            parity_vector += '1'
            if not flag and first and n2 < N:
                flag, M = True, n

    return M, n, parity_vector


def build_pv_graph_html(bit_pattern: str) -> str:
    """Shared rendering logic for the PV graph matrix + explanation box."""
    bit_length = len(bit_pattern)
    table_max = bit_length + 10
    pat_table = [' '] + list(bit_pattern)

    bit_table = {0: {j: j for j in range(table_max + 1)}}
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

    header_cells = ''.join(f'<th>{b}</th>' for b in pat_table[1:])
    col_headers = list(range(0, min(table_max, bit_length + 1) + 1))
    col_header_cells = ''.join(f'<th>&ensp;{j}</th>' for j in col_headers)

    zen_d = -1
    data_rows = ""
    for i in range(1, table_max + 1):
        d = int(i * math.log(2) / math.log(3))
        cells = ""
        for j in range(table_max + 1):
            cell_val = bit_table.get(i, {}).get(j, ' ')
            cls = ''
            if j == d:
                if zen_d != d:
                    cls = 'yellow'
                    zen_d = d
            cells += f'<td{f" class=\"{cls}\"" if cls else ""}>{cell_val}</td>'
            if cell_val == '*':
                break
        data_rows += f'<tr><th>{i}</th>{cells}</tr>'

    explain = """
    <div class="explain-box">
        <b>(Explanation)</b><br>
        A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector
        stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that
        it is an unconverged PV.<br><br>
        The Parity Vector that continues past the "0" cells will be already converged.<br><br>
        Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>,
        and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.
    </div>
    """

    html = f"""
    <div style="display:flex; align-items:flex-start; gap:25px;">
        <table class="pv-table">
            <tr class="pv-header-row"><th><span style="font-size:11px">PV &rarr;</span>&nbsp;</th>{header_cells}</tr>
            <tr><th style="white-space:nowrap">k &nbsp; d</th>{col_header_cells}</tr>
            {data_rows}
        </table>
        {explain}
    </div>
    """
    return html


@app.route('/pvgraph', methods=['GET', 'POST'])
def pvgraph_tool():
    body = """
    <h2>4. Parity Vector Graph (from PV string)</h2>
    <div class="desc">Enter a Parity Vector (a string of 0s and 1s) to view its graphical matrix.</div>
    <form method="POST">
        <input type="text" name="pattern" value="" size="60" placeholder="e.g. 10110101" pattern="[01 ]+">
        <input type="submit" value="Execute">
    </form>
    """

    if request.method == 'POST':
        raw = request.form.get('pattern', '').replace(' ', '')
        bit_pattern = re.sub(r'[^01]', '', raw.strip())
        bit_length = len(bit_pattern)
        bit_odd = bit_pattern.count('1')

        if bit_length == 0:
            body += '<p class="msg">Please enter a parity vector above and press Execute.</p>'
        else:
            graph_html = build_pv_graph_html(bit_pattern)

            timestamp = datetime.now().strftime('%Y-%m-%d-%H-%M')
            out_fname = f"GeneratorForPVstring{bit_length}_{timestamp}.html"

            pv_display = bit_pattern if bit_length <= 100 else '<br>'.join(
                bit_pattern[i:i+100] for i in range(0, bit_length, 100)
            )

            body += f"""
            <p class="time">Input PV Length: {bit_length} &nbsp; Number of Ones: {bit_odd}</p>
            {graph_html}
            <p class="msg"><b>Input PV:</b><br>{pv_display}</p>
            """

    return render_page('pvgraph', 'PV Graph from String', body)


# ============================================================
# Tool 5: PV Graph (from Number)
# ============================================================

@app.route('/numgraph', methods=['GET', 'POST'])
def numgraph_tool():
    body = """
    <h2>5. Parity Vector Graph (from Number)</h2>
    <div class="desc">Enter a positive integer N (greater than 1) to compute and visualize its Parity Vector up to the Glide point.</div>
    <form method="POST">
        <input type="text" name="number" value="" size="40" placeholder="e.g. 27"
               oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        <input type="submit" value="Execute">
    </form>
    """

    if request.method == 'POST':
        raw = request.form.get('number', '').strip()
        number = re.sub(r'[^0-9]', '', raw)

        if number == '':
            body += '<p class="msg">Please enter a positive integer above and press Execute.</p>'
        elif number in ('0', '1'):
            body += '<p class="error">Please enter an integer greater than 1.</p>'
        else:
            n_val = int(number)
            glide_no, reach1, bit_pattern = glide_check_from_number(n_val)
            bit_length = len(bit_pattern)
            bit_odd = bit_pattern.count('1')

            graph_html = build_pv_graph_html(bit_pattern)

            pv_display = bit_pattern if bit_length <= 100 else '<br>'.join(
                bit_pattern[i:i+100] for i in range(0, bit_length, 100)
            )

            body += f"""
            <p class="time">Status: Glide = {glide_no}, Number of times reaching 1 = {reach1}<br>
            Input N: {number} &nbsp; PV Length: {bit_length} &nbsp; Number of Ones: {bit_odd}</p>
            {graph_html}
            <p class="msg"><b>Parity Vector (up to Glide):</b><br>{pv_display}</p>
            """

    return render_page('numgraph', 'PV Graph from Number', body)


# ============================================================
# Shared download route
# ============================================================

@app.route('/download/<filename>')
def download(filename):
    return send_from_directory(DATA_DIR, filename, as_attachment=True)


if __name__ == '__main__':
    print("Starting Collatz Conjecture Tool Suite (Unified Launcher)")
    print("Access: http://127.0.0.1:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
