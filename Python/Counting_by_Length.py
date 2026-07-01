"""
Collatz PV Counter by Length - Flask Web App
Scientific Notation for Ratio (1 <= A < 10)
Example: 2.1234567890123e-100
"""

from flask import Flask, render_template_string, request
from decimal import Decimal, getcontext
import math
import os
import time
from datetime import datetime

app = Flask(__name__)

LAMBDA = 1.5849625007211563
DATA_DIR = os.path.join(os.path.dirname(__file__), 'data')
os.makedirs(DATA_DIR, exist_ok=True)


def sci(num: int) -> str:
    """整数を短い科学的記法で表示する（表示用）"""
    s = str(num)
    if len(s) <= 8:
        return f"{num:,}"
    exp = len(s) - 1
    mant = int(s[:6]) / 100000
    return f"{mant:.5f}e+{exp}"


def ratio_sci(D: int, A: int, precision: int = 15) -> str:
    """
    高精度な未収束比率を科学的記法で返す（Decimalのみ使用、float変換なし）
    例: "1.234567890123456e-45"
    """
    if D == 0 or A == 0:
        return "0.0e+0"

    # 指数の大きさを推定：桁数差 ≒ 指数の絶対値
    exp_estimate = len(str(A)) - len(str(D))
    # 必要な小数点以下桁数 = 指数分のゼロ + 有効桁数 + 余裕
    scale = max(exp_estimate + precision + 10, precision + 30)

    # Decimalで高精度除算
    getcontext().prec = scale + 10
    ratio = Decimal(D) / Decimal(A)
    ratio_str = format(ratio, f'.{scale}f')

    # 整数部が0でない場合（比率 >= 1）
    if not ratio_str.startswith('0.'):
        parts = ratio_str.split('.')
        int_part = parts[0]
        frac_part = parts[1] if len(parts) > 1 else ''
        exp = len(int_part) - 1
        all_digits = int_part + frac_part
        all_digits = all_digits.lstrip('0')
        mant = all_digits[0] + '.' + all_digits[1:precision + 1]
        return f"{mant}e+{exp}"

    # 小数点以下から先頭のゼロを数えて指数を求める
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

    exp -= 1  # 仮数を 1.xxxx 形式にするため
    mant = digits[0] + '.' + digits[1:precision + 1]
    return f"{mant}e{exp}"


HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collatz PV Counter by Length</title>
    <style>
        body { font-family: 'Segoe UI', Arial, monospace; background:#f4f6f9; margin:0; padding:20px; }
        .container { max-width:1600px; margin:auto; background:white; padding:25px; border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
        table { border-collapse: collapse; width:100%; margin:20px 0; }
        th, td { border:1px solid #444; padding:12px; text-align:center; }
        th { background:#1e3a8a; color:white; }
        td.left { text-align:left; }
        .sci { font-size:0.95em; color:#d32f2f; font-weight:bold; }
        .time { font-size:17px; color:#1e3a8a; font-weight:bold; }
        .error { color:red; text-align:center; }
        form label { margin: 0 10px; }
        button { margin-left:30px; padding:6px 18px; }
    </style>
</head>
<body>
<div class="container">
    <h1 style="text-align:center">Collatz Parity Vector Counter by Length</h1>

    <form method="POST" style="text-align:center; margin:25px 0;">
        <label>Start (A): <input type="number" name="A" value="{{ a_val }}" min="1" max="10000" required></label>
        <label style="margin-left:40px;">End (B): <input type="number" name="B" value="{{ b_val }}" min="1" max="10000" required></label>
        <label style="margin-left:40px;">Ratio Precision: <input type="number" name="PREC" value="{{ prec_val }}" min="5" max="50" required></label>
        <button type="submit">Start Calculation</button>
    </form>

    {% if error %}
        <p class="error">{{ error }}</p>
    {% endif %}

    {% if start_time_str %}
        <p class="time">Start Time: {{ start_time_str }}</p>
    {% endif %}

    {% if rows %}
    <table>
        <tr>
            <th>k</th>
            <th>Conv Steps</th>
            <th>(A) Total PVs</th>
            <th>(B) Converged</th>
            <th>(C) Already Conv.</th>
            <th>(D) Unconverged</th>
            <th>(E) Ratio D/A</th>
        </tr>
        {% for row in rows %}
        <tr>
            <td><b>{{ row.k }}</b></td>
            <td>{{ row.con }}</td>
            <td class="left">{{ row.A_sci }}<br><small>{{ row.A_val }}</small></td>
            <td class="left">{{ row.B_sci }}<br><small>{{ row.B_val }}</small></td>
            <td class="left">{{ row.C_sci }}<br><small>{{ row.C_val }}</small></td>
            <td class="left">{{ row.D_sci }}<br><small>{{ row.D_val }}</small></td>
            <td class="left"><span class="sci">{{ row.ratio }}</span></td>
        </tr>
        {% endfor %}
    </table>
    {% endif %}

    {% if end_time_str %}
        <p class="time" style="text-align:center; margin:25px;">
            End Time: {{ end_time_str }}<br>
            <strong>Processing Time: {{ duration }} seconds</strong>
        </p>
        <p style="text-align:center; margin:25px;">
            <a href="/download/{{ filename }}" download>📥 Download TXT Result</a>
        </p>
    {% endif %}
</div>
</body>
</html>
"""


@app.route('/', methods=['GET', 'POST'])
def index():
    context = {
        'a_val': 1,
        'b_val': 200,
        'prec_val': 15,
        'error': None,
        'start_time_str': None,
        'end_time_str': None,
        'duration': None,
        'rows': None,
        'filename': None,
    }

    if request.method == 'POST':
        k_start   = max(1, min(10000, int(request.form.get('A', 1))))
        k_end     = max(1, min(10000, int(request.form.get('B', 200))))
        ratio_prec = max(5, min(50, int(request.form.get('PREC', 15))))

        context['a_val']    = k_start
        context['b_val']    = k_end
        context['prec_val'] = ratio_prec

        if k_end < k_start:
            context['error'] = 'Error: B must be ≥ A'
            return render_template_string(HTML_TEMPLATE, **context)

        start_time = time.time()
        start_time_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        context['start_time_str'] = start_time_str

        # --- 計算 ---
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

        # 未収束 (W) の計算
        for k in range(2, k_end + 1):
            for d in range(0, k + 1):
                boundary = int(math.floor(d * LAMBDA)) + 1
                if k < boundary:
                    ws = W[k-1].get(d, 0) + W[k-1].get(d-1, 0)
                    W[k][d] = ws
                W_total[k] += W[k].get(d, 0)

        # 収束 (X) の計算
        for k in range(4, k_end + 1):
            for d in range(2, k):
                boundary = int(math.floor(d * LAMBDA)) + 1
                if k == boundary:
                    ws = W[k-1].get(d, 0) + W[k].get(d-1, 0)
                    X[k][d] = ws
                    CON[k] = boundary
                X_total[k] += X[k].get(d, 0)

        # --- 結果テーブル構築 ---
        rows = []
        txt_lines = ["k\tConvSteps\tA_TotalPVs\tB_Converged\tC_AlreadyConv\tD_Unconverged\tRatio"]

        for k in range(k_start, k_end + 1):
            A_val = 2 ** k
            B_val = X_total[k]
            D_val = W_total[k]
            C_val = A_val - B_val - D_val

            ratio = ratio_sci(D_val, A_val, ratio_prec)

            rows.append({
                'k':     k,
                'con':   CON.get(k, '-'),
                'A_sci': sci(A_val),
                'A_val': str(A_val),
                'B_sci': sci(B_val),
                'B_val': str(B_val),
                'C_sci': sci(C_val),
                'C_val': str(C_val),
                'D_sci': sci(D_val),
                'D_val': str(D_val),
                'ratio': ratio,
            })
            txt_lines.append(
                f"{k}\t{CON.get(k, '-')}\t{A_val}\t{B_val}\t{C_val}\t{D_val}\t{ratio}"
            )

        # TXTファイル保存
        filename = f"PV_Length_{k_start}_{k_end}.txt"
        txt_path = os.path.join(DATA_DIR, filename)
        with open(txt_path, 'w', encoding='utf-8') as f:
            f.write('\n'.join(txt_lines))

        end_time_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        duration = round(time.time() - start_time, 2)

        context.update({
            'rows':          rows,
            'end_time_str':  end_time_str,
            'duration':      duration,
            'filename':      filename,
        })

    return render_template_string(HTML_TEMPLATE, **context)


@app.route('/download/<filename>')
def download(filename):
    from flask import send_from_directory
    return send_from_directory(DATA_DIR, filename, as_attachment=True)


if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)
