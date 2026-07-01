"""
Collatz Parity Vector Counter (By Hamming Weight)
Flask Web App - HTML template embedded in Python
Based on Algorithm 2 from "Discrete Geometry and Combinatorial Structure of
Parity Vectors in the Collatz Map", Kazunobu Hikawa, 2026
"""

from flask import Flask, render_template_string, request, send_from_directory
import math
import os
import sys
from datetime import datetime
from pathlib import Path

sys.set_int_max_str_digits(1000000)

app = Flask(__name__)
app.config['SECRET_KEY'] = 'collatz_parity_secret'

LAMBDA = math.log(3) / math.log(2)  # log2(3) ≈ 1.58496

HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Collatz Parity Vector Counter</title>
  <style>
    body { font-family: monospace; margin: 20px; background: #f9f9f9; }
    table { border-collapse: collapse; width: 90%; margin: 20px auto; background: white; }
    th, td { border: 2px solid #333; padding: 10px; text-align: center; }
    th { background: #ddd; }
    td.left { text-align: left; }
    .error { color: red; font-weight: bold; text-align: center; }
    input[type="number"] { width: 140px; padding: 8px; font-size: 16px; }
    button { padding: 12px 40px; font-size: 18px; background: #0066cc; color: white; border: none; cursor: pointer; }
    button:hover { background: #0055aa; }
    .time { font-size: 16px; color: #1e3a8a; font-weight: bold; text-align: center; }
    .sci { color: #d32f2f; font-weight: bold; }
  </style>
</head>
<body>
  <h2 style="text-align:center">Collatz Parity Vector Counter<br>(By Hamming Weight)</h2>

  <form method="POST" style="text-align:center; margin:30px;">
    <label>A (Start d): <input type="number" name="A" value="{{ a_val }}" min="1" max="10000" required></label>
    <label style="margin-left:30px;">B (End d): <input type="number" name="B" value="{{ b_val }}" min="1" max="10000" required></label>
    <button type="submit" style="margin-left:40px;">Run Calculation</button>
  </form>

  {% if error %}
    <p class="error">{{ error }}</p>
  {% endif %}

  {% if result %}
    <p class="time">
      Calculation Time: {{ result.time_start }} → {{ result.time_end }}<br>
      <strong>Processing Time: {{ result.duration }} seconds</strong>
    </p>
    <p style="text-align:center; margin:10px;">
      <a href="/data/{{ result.output_file }}" download>📥 Download Result File ({{ result.output_file }})</a>
    </p>

    <table>
      <tr>
        <th>Hamming Weight d</th>
        <th>kmin(d)</th>
        <th>(A) X(d) = Just Converged</th>
        <th>(B) W(d) = Unconverged</th>
        <th>rho_d = W(d)/2^kmin</th>
      </tr>
      {% for row in result.table_rows %}
      <tr>
        <td>{{ row.d }}</td>
        <td>{{ row.kmin }}</td>
        <td class="left"><span class="sci">{{ row.x_sci }}</span><br><small>{{ row.x_full }}</small></td>
        <td class="left"><span class="sci">{{ row.w_sci }}</span><br><small>{{ row.w_full }}</small></td>
        <td><span class="sci">{{ row.rho }}</span></td>
      </tr>
      {% endfor %}
    </table>
  {% endif %}

  <p style="text-align:center; margin-top:40px; color:#555;">
    <small>Based on Algorithm 2 from "Discrete Geometry and Combinatorial Structure of Parity Vectors in the Collatz Map", Kazunobu Hikawa, 2026</small>
  </p>
</body>
</html>
"""


def gmp_to_sci(n: int) -> str:
    """大整数を科学的記法に変換する"""
    if n == 0:
        return "0.000000e+0"
    s = str(n)
    length = len(s)
    exp = length - 1
    mant = s[:7]
    num = int(mant) / 1_000_000
    return f"{num:.6f}e+{exp}"


def kmin(d: int) -> int:
    """ハミング重みdに対する最小許容長"""
    return math.ceil(LAMBDA * d)


@app.route('/', methods=['GET', 'POST'])
def index():
    result = None
    error = None
    a_val = 1
    b_val = 20

    if request.method == 'POST':
        try:
            d_start = max(1, min(10000, int(request.form.get('A', 1))))
            d_end   = max(1, min(10000, int(request.form.get('B', 20))))
            a_val = d_start
            b_val = d_end

            if d_end < d_start:
                raise ValueError("Please specify A >= 1 and B >= A.")

            time_start = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            start_sec = datetime.now().timestamp()

            # dataディレクトリ作成
            data_dir = Path(os.path.dirname(os.path.abspath(__file__))) / "data"
            data_dir.mkdir(exist_ok=True)
            out_file = data_dir / f"NumPVofOnes-{d_start}-{d_end}.txt"

            # テーブル初期化
            k_upper = int(math.floor(d_end * LAMBDA)) + 1
            W = {}
            W_total = [0] * (k_upper + 1)

            for i in range(k_upper + 1):
                W[i] = {}
                for j in range(-1, k_upper + 1):
                    W[i][j] = 0

            # 初期値: d=1, u=0
            W[1][0] = 1
            W_total[0] = 1
            W_total[1] = 1

            table_rows = []

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
                            prev_d = W[d - 1].get(u, 0)
                            prev_u = W[d].get(u - 1, 0)
                            W[d][u] = prev_d + prev_u
                        else:
                            W[d][u] = 0

                        W_total[d] += W[d][u]

                        if d >= d_start and W[d][u] > 0:
                            fp.write(f"  length={k}, count={W[d][u]}\n")

                    if d >= d_start:
                        fp.write(f"  W({d}) total = {W_total[d]}\n\n")

                    # テーブル行の作成
                    if d >= d_start:
                        km   = kmin(d)
                        xd   = W_total[d - 1]
                        wd   = W_total[d]
                        pow2 = 1 << km
                        rho  = wd / pow2 if wd > 0 else 0.0

                        table_rows.append({
                            'd':      d,
                            'kmin':   km,
                            'x_sci':  gmp_to_sci(xd),
                            'x_full': xd,
                            'w_sci':  gmp_to_sci(wd),
                            'w_full': wd,
                            'rho':    f"{rho:.4e}"
                        })

            time_end = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            duration = round(datetime.now().timestamp() - start_sec, 2)

            result = {
                'table_rows':  table_rows,
                'd_start':     d_start,
                'd_end':       d_end,
                'time_start':  time_start,
                'time_end':    time_end,
                'duration':    duration,
                'output_file': out_file.name
            }

        except ValueError as e:
            error = str(e)
        except Exception as e:
            error = f"An error occurred during calculation: {str(e)}"

    return render_template_string(HTML_TEMPLATE, result=result, error=error,
                                  a_val=a_val, b_val=b_val)


@app.route('/data/<filename>')
def download_file(filename):
    data_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')
    return send_from_directory(data_dir, filename, as_attachment=True)


if __name__ == '__main__':
    data_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'data')
    os.makedirs(data_dir, exist_ok=True)
    print("Starting Collatz Parity Vector Counter (By Hamming Weight)")
    print("Access: http://127.0.0.1:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
