"""
Collatz_PV_Graph_from_Number.py
Parity Vector Graph generated from an input Natural Number N - Flask Web App

- Computes the Collatz parity vector (PV) for N up to its Glide point
- Displays a graphical matrix of that parity vector path
- Same graphical style as Collatz_PV_Graph.py
"""

from flask import Flask, render_template_string, request
import re
import math

app = Flask(__name__)


def glide_check(number: int):
    """
    Run the Collatz sequence from `number` and return:
    (Glide value, total stopping time, parity vector up to total stopping time)
    """
    M = 0
    first = True
    flag = False
    n = 0
    N = number
    n2 = number
    parity_vector = ''

    while True:
        if n2 <= 1:
            break
        n += 1

        if n2 % 2 == 0:
            # Even: divide by 2
            n2 = n2 // 2
            parity_vector += '0'
            if not flag and first and n2 < N:
                flag = True
                M = n
            if flag and first:
                first = False
        else:
            # Odd: (×3+1)÷2
            n2 = (n2 * 3 + 1) // 2
            parity_vector += '1'
            if not flag and first and n2 < N:
                flag = True
                M = n

    return M, n, parity_vector


HTML_TEMPLATE = """
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collatz Parity Vector Graph from Number</title>
    <style>
        body  { font-family: monospace; margin: 20px; background: #f8f8f8; }
        h2    { text-align: center; }
        .container { max-width: 100%; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        form  { margin: 10px 0 16px; }
        input[type="text"]   { width: 400px; padding: 6px; font-size: 14px; }
        input[type="submit"] { padding: 8px 28px; font-size: 15px; background: #0066cc; color: white; border: none; cursor: pointer; border-radius: 4px; margin-left: 10px; }
        input[type="submit"]:hover { background: #0055aa; }
        .pv-table { border-collapse: collapse; margin: 16px 0; font-size: 13px; }
        .pv-table th, .pv-table td { border: 1px solid #888; padding: 3px 6px; text-align: center; white-space: nowrap; }
        .pv-table th { background: #e0e0e0; }
        .pv-header-row th { background: orange; color: black; }
        .pv-header-row th:first-child { background: white; font-size: 11px; }
        .yellow { background: yellow; }
        .red-bold { color: red; font-weight: bold; }
        .msg  { margin: 10px 0; font-size: 14px; }
        a     { color: #0066cc; }
        .error { color: red; font-weight: bold; }
        .explain-box { max-width: 380px; background:#fff8e1; border:1px solid #d4a017; border-radius:8px; padding:14px 18px; font-size:13px; line-height:1.7; }
    </style>
</head>
<body>
<div class="container">
    <h2>Collatz Parity Vector Graph from Number</h2>

    <form method="POST">
        <b>Enter a positive integer N (greater than 1):</b><br>
        <input type="text" name="number" value=""
               placeholder="e.g. 27"
               oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        <input type="submit" value="Execute">
    </form>

    {% if error %}
        <p class="error">{{ error }}</p>
    {% elif not valid_input %}
        <p class="msg">Please enter a positive integer above and press Execute.</p>
    {% else %}
        <p class="msg">
            <b>Status:</b> Glide = {{ glide_no }}, Number of times reaching 1 = {{ reach1 }}<br>
            <b>Input N:</b> {{ number }} &nbsp;
            <b>PV Length:</b> {{ bit_length }} &nbsp;
            <b>Number of Ones:</b> {{ bit_odd }}
        </p>

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

        <div class="explain-box">
            <b>(Explanation)</b><br>
            A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that it is an unconverged PV.<br><br>
            The Parity Vector that continues past the "0" cells will be already converged.<br><br>
            Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>, and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.
        </div>
        </div>

        {% if bit_length > 100 %}
        <p class="msg"><b>Parity Vector (up to Glide):</b><br>
        {% for chunk in pv_chunks %}
        {{ chunk }}<br>
        {% endfor %}
        </p>
        {% else %}
        <p class="msg"><b>Parity Vector (up to Glide):</b> {{ bit_pattern }}</p>
        {% endif %}
    {% endif %}

</div>
</body>
</html>
"""


@app.route('/', methods=['GET', 'POST'])
def index():
    error = None
    valid_input = False
    number = ''
    glide_no = 0
    reach1 = 0
    bit_pattern = ''
    bit_length = 0
    bit_odd = 0
    pat_table = [' ']
    col_headers = []
    graph_rows = []
    pv_chunks = []

    if request.method == 'POST':
        raw = request.form.get('number', '').strip()
        number = re.sub(r'[^0-9]', '', raw)

        if number != '':
            if number in ('0', '1'):
                error = 'Please enter an integer greater than 1.'
            else:
                valid_input = True
                n_val = int(number)
                glide_no, reach1, bit_pattern = glide_check(n_val)
                bit_length = len(bit_pattern)
                bit_odd = bit_pattern.count('1')

                # ====================== Build Graph Table ======================
                table_max = bit_length + 10
                for i in range(bit_length):
                    pat_table.append(bit_pattern[i])

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

                col_headers = list(range(0, min(table_max, bit_length + 1) + 1))

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

                if bit_length > 100:
                    for jj in range(math.ceil(bit_length / 100)):
                        pv_chunks.append(bit_pattern[jj * 100:(jj + 1) * 100])

    return render_template_string(
        HTML_TEMPLATE,
        error=error,
        valid_input=valid_input,
        number=number,
        glide_no=glide_no,
        reach1=reach1,
        bit_pattern=bit_pattern,
        bit_length=bit_length,
        bit_odd=bit_odd,
        pat_table=pat_table,
        col_headers=col_headers,
        graph_rows=graph_rows,
        pv_chunks=pv_chunks,
    )


if __name__ == '__main__':
    print("Starting Collatz Parity Vector Graph from Number")
    print("Access: http://127.0.0.1:5000")
    app.run(debug=True, host='0.0.0.0', port=5000)
