<?php
/**
 * Collatz_Launcher.php
 * Unified Launcher for the Collatz Conjecture Tool Suite
 *
 * Combines the following 5 tools into a single application with a menu:
 *   1. Counting by Length
 *   2. Counting by Hamming Weight
 *   3. Collatz Calculator
 *   4. Parity Vector Graph (from PV string)
 *   5. Parity Vector Graph (from Number)
 *
 * Requires: PHP 8.1+, GMP extension
 */

declare(strict_types=1);
set_time_limit(0);
header('Content-Type: text/html; charset=UTF-8');

const LAMBDA = 1.5849625007211563; // log2(3)

$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);

$tab = $_GET['tab'] ?? 'home';
$valid_tabs = ['home', 'length', 'hamming', 'calculator', 'pvgraph', 'numgraph'];
if (!in_array($tab, $valid_tabs, true)) $tab = 'home';

// ====================== Shared Helper Functions ======================

function sci_short(GMP $num): string {
    $s = gmp_strval($num);
    if (strlen($s) <= 8) return number_format((int)$s);
    $exp = strlen($s) - 1;
    $mant = substr($s, 0, 6);
    $val = (float)$mant / 100000;
    return sprintf("%.5fe+%d", $val, $exp);
}

function ratio_sci(string $D_str, string $A_str, int $precision = 15): string {
    if ($D_str === '0' || $A_str === '0') return '0.0e+0';
    $exp_estimate = strlen($A_str) - strlen($D_str);
    $scale = max($exp_estimate + $precision + 10, $precision + 30);
    $ratio_str = bcdiv($D_str, $A_str, $scale);

    if (!str_starts_with($ratio_str, '0.')) {
        [$int_part, $frac_part] = explode('.', $ratio_str . '.0');
        $exp = strlen(ltrim($int_part, '-')) - 1;
        $all_digits = ltrim($int_part . $frac_part, '0');
        $mant = $all_digits[0] . '.' . substr($all_digits, 1, $precision);
        return "{$mant}e+{$exp}";
    }

    [, $frac] = explode('.', $ratio_str);
    $exp = 0; $digits = ''; $leading = true;
    foreach (str_split($frac) as $ch) {
        if ($leading && $ch === '0') { $exp--; }
        else { $leading = false; $digits .= $ch; }
    }
    if ($digits === '') return '0.0e+0';
    $exp--;
    $mant = $digits[0] . '.' . substr($digits, 1, $precision);
    return "{$mant}e{$exp}";
}

function gmp_to_sci(GMP $n): string {
    $s = gmp_strval($n);
    if ($s === '0') return '0.000000e+0';
    $exp = strlen($s) - 1;
    $mant = substr($s, 0, 7);
    $num = (float)$mant / 1_000_000;
    return sprintf('%.6fe+%d', $num, $exp);
}

function kmin(int $d): int {
    return (int)ceil(LAMBDA * $d);
}

function glide_check(GMP $number): array {
    $M = 0; $first = true; $flag = false;
    $one = gmp_init(1);
    $n = 0; $N = $number; $n2 = $number;
    $parity_vector = '';

    while (true) {
        if (gmp_cmp($n2, $one) <= 0) break;
        $n++;
        if (gmp_div_r($n2, 2) == 0) {
            $n2 = gmp_div_q($n2, 2);
            $parity_vector .= '0';
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) { $flag = true; $M = $n; }
            if ($flag && $first) { $first = false; }
        } else {
            $n2 = gmp_div_q(gmp_add(gmp_mul($n2, 3), $one), gmp_init(2));
            $parity_vector .= '1';
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) { $flag = true; $M = $n; }
        }
    }
    return [$M, $n, $parity_vector];
}

/**
 * Shared rendering logic for the PV graph matrix + explanation box.
 * Returns an HTML string.
 */
function build_pv_graph_html(string $bit_pattern): string {
    $bit_length = strlen($bit_pattern);
    $table_max  = $bit_length + 10;
    $Pat_table  = [0 => ' '];
    for ($i = 0; $i < $bit_length; $i++) {
        $Pat_table[$i + 1] = substr($bit_pattern, $i, 1);
    }

    $Bit_table = [];
    $zen_d = -1;
    for ($j = 0; $j <= $table_max; $j++) $Bit_table[0][$j] = $j;

    for ($i = 1; $i <= $table_max; $i++) {
        $d = (int)($i * log(2) / log(3));
        $Gyo_end = false;
        for ($j = 0; $j <= $table_max; $j++) {
            if ($j === $d) {
                if ($zen_d !== $d) { $Bit_table[$i][$j] = '0'; $zen_d = $d; }
                else { $Bit_table[$i][$j] = ' '; }
            } elseif ($j === ($i + 1)) {
                $Bit_table[$i][$j] = '*'; $Gyo_end = true;
            } else {
                $Bit_table[$i][$j] = ' ';
            }
            if ($Gyo_end) break;
        }
    }

    $Pos_j = 0;
    for ($i = 0; $i < $bit_length; $i++) {
        $Pos_i = $i + 1;
        $BIT = $Pat_table[$i + 1];
        if (($Bit_table[$i][$i] ?? '') === '*') continue;
        if ($BIT === '0') {
            $Bit_table[$Pos_i][$Pos_j] = '<span class="red-bold">0</span>';
        } else {
            $Bit_table[$Pos_i][$Pos_j + 1] = '<span class="red-bold">1</span>';
            $Pos_j++;
        }
    }

    $html = '<div style="display:flex; align-items:flex-start; gap:25px;">';
    $html .= '<table class="pv-table">';
    $html .= '<tr class="pv-header-row"><th><span style="font-size:11px">PV &rarr;</span>&nbsp;</th>';
    for ($i = 1; $i <= $bit_length; $i++) {
        $html .= '<th>' . htmlspecialchars($Pat_table[$i], ENT_QUOTES, "UTF-8") . '</th>';
    }
    $html .= '</tr>';

    $html .= '<tr><th style="white-space:nowrap">k &nbsp; d</th>';
    for ($j = 0; $j <= $table_max; $j++) {
        $html .= '<th>&ensp;' . $j . '</th>';
        if ($j >= $bit_length + 1) break;
    }
    $html .= '</tr>';

    $zen_d = -1;
    for ($i = 1; $i <= $table_max; $i++) {
        $d = (int)($i * log(2) / log(3));
        $html .= '<tr><th>' . $i . '</th>';
        for ($j = 0; $j <= $table_max; $j++) {
            $cell = $Bit_table[$i][$j] ?? ' ';
            $class = '';
            if ($j === $d) {
                if ($zen_d !== $d) { $class = 'yellow'; $zen_d = $d; }
            }
            $html .= '<td' . ($class ? " class=\"{$class}\"" : '') . '>' . $cell . '</td>';
            if ($cell === '*') break;
        }
        $html .= '</tr>';
    }
    $html .= '</table>';

    $html .= '<div class="explain-box">';
    $html .= '<b>(Explanation)</b><br>';
    $html .= 'A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that it is an unconverged PV.<br><br>';
    $html .= 'The Parity Vector that continues past the "0" cells will be already converged.<br><br>';
    $html .= 'Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>, and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.';
    $html .= '</div></div>';

    return $html;
}

// ====================== Shared Layout ======================

$BASE_STYLE = <<<CSS
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
CSS;

$MENU_ITEMS = [
    'home'       => ['?tab=home', 'Home'],
    'length'     => ['?tab=length', '1. Counting by Length'],
    'hamming'    => ['?tab=hamming', '2. Counting by Hamming Weight'],
    'calculator' => ['?tab=calculator', '3. Collatz Calculator'],
    'pvgraph'    => ['?tab=pvgraph', '4. PV Graph (from PV string)'],
    'numgraph'   => ['?tab=numgraph', '5. PV Graph (from Number)'],
];

function render_page(string $active, string $title, string $body): void {
    global $BASE_STYLE, $MENU_ITEMS;
    $menu_html = '';
    foreach ($MENU_ITEMS as $key => [$url, $label]) {
        $cls = ($key === $active) ? ' class="active"' : '';
        $menu_html .= "<a href=\"{$url}\"{$cls}>{$label}</a>";
    }
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{$title} - Collatz Conjecture Tool Suite</title>
{$BASE_STYLE}
</head>
<body>
<div class="topbar"><h1>Collatz Conjecture Tool Suite</h1></div>
<div class="menu">{$menu_html}</div>
<div class="container">
{$body}
</div>
<div class="footer">Collatz Conjecture Tool Suite &mdash; Unified Launcher (PHP)</div>
</body>
</html>
HTML;
}

// ====================== Tab: Home ======================

if ($tab === 'home') {
    $body = <<<HTML
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
    HTML;
    render_page('home', 'Home', $body);
    exit;
}

// ====================== Tab: Counting by Length ======================

if ($tab === 'length') {
    $body = <<<HTML
    <h2>1. Counting by Length</h2>
    <div class="desc">Counts the number of converged and unconverged Parity Vectors for each bit length k.</div>
    <form method="POST" action="?tab=length">
        <label>Start (A): <input type="number" name="A" value="" min="1" max="10000" required></label>
        <label>End (B): <input type="number" name="B" value="" min="1" max="10000" required></label>
        <label>Ratio Precision: <input type="number" name="PREC" value="15" min="5" max="50" required></label>
        <input type="submit" value="Start Calculation">
    </form>
    HTML;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $k_start = max(1, min(10000, (int)($_POST['A'] ?? 1)));
        $k_end   = max(1, min(10000, (int)($_POST['B'] ?? 200)));
        $ratio_prec = max(5, min(50, (int)($_POST['PREC'] ?? 15)));

        if ($k_end < $k_start) {
            $body .= '<p class="error">Error: B must be &ge; A</p>';
        } else {
            $start_time = microtime(true);
            $start_time_str = date('Y-m-d H:i:s');

            $ZERO = gmp_init(0); $ONE = gmp_init(1);
            $W_total = []; $X_total = []; $W = []; $X = []; $CON = [];
            for ($k = 0; $k <= $k_end; $k++) { $W_total[$k] = clone $ZERO; $X_total[$k] = clone $ZERO; }

            $W[1][1] = clone $ONE; $X[1][0] = clone $ONE; $X[2][1] = clone $ONE;
            $W_total[1] = clone $ONE; $X_total[1] = clone $ONE; $X_total[2] = clone $ONE;
            $CON[1] = 1; $CON[2] = 2;

            for ($k = 2; $k <= $k_end; $k++) {
                for ($d = 0; $d <= $k; $d++) {
                    $boundary = (int)floor($d * LAMBDA) + 1;
                    if ($k < $boundary) {
                        $W[$k][$d] = gmp_add($W[$k-1][$d] ?? $ZERO, $W[$k-1][$d-1] ?? $ZERO);
                    }
                    $W_total[$k] = gmp_add($W_total[$k], $W[$k][$d] ?? $ZERO);
                }
            }
            for ($k = 4; $k <= $k_end; $k++) {
                for ($d = 2; $d < $k; $d++) {
                    $boundary = (int)floor($d * LAMBDA) + 1;
                    if ($k == $boundary) {
                        $X[$k][$d] = gmp_add($W[$k-1][$d] ?? $ZERO, $W[$k][$d-1] ?? $ZERO);
                        $CON[$k] = $boundary;
                    }
                    $X_total[$k] = gmp_add($X_total[$k], $X[$k][$d] ?? $ZERO);
                }
            }

            $rows_html = '';
            $filename = "PV_Length_{$k_start}_{$k_end}.txt";
            $fp = fopen($data_dir . '/' . $filename, 'w');
            fwrite($fp, "k\tConvSteps\tA_TotalPVs\tB_Converged\tC_AlreadyConv\tD_Unconverged\tRatio\n");

            for ($k = $k_start; $k <= $k_end; $k++) {
                $A_val = gmp_pow(gmp_init(2), $k);
                $B_val = $X_total[$k] ?? $ZERO;
                $D_val = $W_total[$k] ?? $ZERO;
                $C_val = gmp_sub(gmp_sub($A_val, $B_val), $D_val);
                $ratio = ratio_sci(gmp_strval($D_val), gmp_strval($A_val), $ratio_prec);

                $rows_html .= "<tr><td><b>{$k}</b></td><td>" . ($CON[$k] ?? '-') . "</td>"
                    . "<td class='left'>" . sci_short($A_val) . "<br><small>" . gmp_strval($A_val) . "</small></td>"
                    . "<td class='left'>" . sci_short($B_val) . "<br><small>" . gmp_strval($B_val) . "</small></td>"
                    . "<td class='left'>" . sci_short($C_val) . "<br><small>" . gmp_strval($C_val) . "</small></td>"
                    . "<td class='left'>" . sci_short($D_val) . "<br><small>" . gmp_strval($D_val) . "</small></td>"
                    . "<td class='left'><span class='sci'>{$ratio}</span></td></tr>";

                fwrite($fp, "{$k}\t" . ($CON[$k] ?? '-') . "\t" . gmp_strval($A_val) . "\t" . gmp_strval($B_val) . "\t" . gmp_strval($C_val) . "\t" . gmp_strval($D_val) . "\t{$ratio}\n");
            }
            fclose($fp);

            $duration = round(microtime(true) - $start_time, 2);
            $end_time_str = date('Y-m-d H:i:s');

            $body .= "<p class='time'>Start Time: {$start_time_str}</p>";
            $body .= "<table><tr><th>k</th><th>Conv Steps</th><th>(A) Total PVs</th><th>(B) Converged</th><th>(C) Already Conv.</th><th>(D) Unconverged</th><th>(E) Ratio D/A</th></tr>{$rows_html}</table>";
            $body .= "<p class='time'>End Time: {$end_time_str}<br><strong>Processing Time: {$duration} seconds</strong></p>";
            $body .= "<p style='text-align:center'><a class='dl' href='data/{$filename}' download>📥 Download TXT Result</a></p>";
        }
    }
    render_page('length', 'Counting by Length', $body);
    exit;
}

// ====================== Tab: Counting by Hamming Weight ======================

if ($tab === 'hamming') {
    $body = <<<HTML
    <h2>2. Counting by Hamming Weight</h2>
    <div class="desc">Counts the number of converged and unconverged Parity Vectors for each Hamming weight d.</div>
    <form method="POST" action="?tab=hamming">
        <label>Start "Hamming Weight" (A): <input type="number" name="A" value="" min="1" max="10000" required></label>
        <label>End "Hamming Weight" (B): <input type="number" name="B" value="" min="1" max="10000" required></label>
        <input type="submit" value="Execute">
    </form>
    HTML;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $d_start = max(1, min(10000, (int)($_POST['A'] ?? 1)));
        $d_end   = max(1, min(10000, (int)($_POST['B'] ?? 20)));

        if ($d_end < $d_start) {
            $body .= '<p class="error">Error: Please set A &ge; 1 and B &ge; A.</p>';
        } else {
            $start_sec = microtime(true);
            $time_start = date('Y-m-d H:i:s');

            $ZERO = gmp_init(0); $ONE = gmp_init(1);
            $k_upper = (int)floor($d_end * LAMBDA) + 1;
            $W = []; $W_total = array_fill(0, $k_upper + 1, clone $ZERO);
            for ($i = 0; $i <= $k_upper; $i++) $W[$i] = array_fill(-1, $k_upper + 2, clone $ZERO);

            $W[1][0] = clone $ONE; $W_total[0] = clone $ONE; $W_total[1] = clone $ONE;

            $out_file = "{$data_dir}/NumPVofOnes-{$d_start}-{$d_end}.txt";
            $fp = fopen($out_file, 'w');
            fwrite($fp, "Collatz Parity Vector Counter - d = {$d_start} to {$d_end}\n\n");

            $rows_html = '';
            for ($d = 2; $d <= $d_end; $d++) {
                if ($d >= $d_start) fwrite($fp, "d = {$d}\n");
                $u_max = (int)floor($d * LAMBDA) + 1;

                for ($u = 0; $u <= $u_max; $u++) {
                    $k = $d + $u;
                    $e = ($k < $u_max) ? 1 : 0;
                    if ($e === 1) {
                        $W[$d][$u] = gmp_add($W[$d-1][$u] ?? $ZERO, $W[$d][$u-1] ?? $ZERO);
                    } else {
                        $W[$d][$u] = clone $ZERO;
                    }
                    $W_total[$d] = gmp_add($W_total[$d], $W[$d][$u]);
                    if ($d >= $d_start && gmp_cmp($W[$d][$u], $ZERO) > 0) {
                        fwrite($fp, "  length={$k}, count=" . gmp_strval($W[$d][$u]) . "\n");
                    }
                }
                if ($d >= $d_start) {
                    fwrite($fp, "  W({$d}) total = " . gmp_strval($W_total[$d]) . "\n\n");
                    $km = kmin($d);
                    $xd = $W_total[$d - 1]; $wd = $W_total[$d];
                    $pow2 = gmp_pow(gmp_init(2), $km);
                    $rho = 0.0;
                    if (gmp_cmp($wd, $ZERO) > 0) {
                        $rho = (float)gmp_strval($wd) / (float)gmp_strval($pow2);
                    }
                    $rows_html .= "<tr><td>{$d}</td><td>{$km}</td>"
                        . "<td class='left'>" . gmp_to_sci($xd) . "<br><small>" . gmp_strval($xd) . "</small></td>"
                        . "<td class='left'>" . gmp_to_sci($wd) . "<br><small>" . gmp_strval($wd) . "</small></td>"
                        . "<td><span class='sci'>" . sprintf('%.4e', $rho) . "</span></td></tr>";
                }
            }
            fclose($fp);

            $time_end = date('Y-m-d H:i:s');
            $duration = round(microtime(true) - $start_sec, 2);
            $fname = basename($out_file);

            $body .= "<p class='time'>Calculation Time: {$time_start} &rarr; {$time_end}<br><strong>Processing Time: {$duration} seconds</strong></p>";
            $body .= "<p style='text-align:center'><a class='dl' href='data/{$fname}' download>📥 Download TXT Result</a></p>";
            $body .= "<table><tr><th>Hamming Weight d</th><th>kmin(d)</th><th>(A) X(d) = Just Converged</th><th>(B) W(d) = Unconverged</th><th>&rho;<sub>d</sub> = W(d)/2<sup>kmin</sup></th></tr>{$rows_html}</table>";
        }
    }
    render_page('hamming', 'Counting by Hamming Weight', $body);
    exit;
}

// ====================== Tab: Collatz Calculator ======================

if ($tab === 'calculator') {
    $body = <<<HTML
    <h2>3. Collatz Calculator</h2>
    <div class="desc">Computes the Collatz sequence for any large natural number, step by step, highlighting the first point where the value drops below the initial input, and reports the Glide and Parity Vector.</div>
    <form method="POST" action="?tab=calculator">
        <table style="width:auto; margin:0 auto; background:transparent; border:none;">
        <tr><td colspan="2">Enter any natural number (integer, any number of digits):</td></tr>
        <tr><td colspan="2"><input type="text" name="txtN1" size="60" oninput="this.value = this.value.replace(/[^0-9]/g, '');" placeholder="e.g. 27"></td></tr>
        <tr><td>Screen display:</td><td><input type="radio" name="gamen" value="Yes" checked> Show all steps <input type="radio" name="gamen" value="No"> Hide intermediate steps</td></tr>
        <tr><td>File output:</td><td><input type="radio" name="ffout" value="Yes" checked> Save to data/ <input type="radio" name="ffout" value="No"> Do not save</td></tr>
        <tr><td colspan="2" style="text-align:center; padding-top:10px;"><input type="submit" value="Run Calculation"></td></tr>
        </table>
    </form>
    HTML;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $txtN1 = trim($_POST['txtN1'] ?? '');
        $gamen = $_POST['gamen'] ?? 'Yes';
        $ffout = $_POST['ffout'] ?? 'No';

        if ($txtN1 === '' || !ctype_digit($txtN1) || gmp_cmp(gmp_init($txtN1), gmp_init(2)) < 0) {
            $body .= '<p class="error">' . htmlspecialchars($txtN1, ENT_QUOTES, "UTF-8") . ' is not a valid input.</p>';
        } else {
            $n1 = gmp_init($txtN1); $n2 = $n1; $N = $n1;

            $fp = null; $dl_file = null;
            if ($ffout === 'Yes') {
                $mojiretu = gmp_strval($n1);
                if (strlen($mojiretu) >= 200) $mojiretu = substr($mojiretu, 0, 200);
                $fname = $data_dir . '/Collatz-' . $mojiretu . '.csv';
                $fp = fopen($fname, 'w');
                fwrite($fp, "Collatz sequence for " . gmp_strval($n1) . "\n");
                $dl_file = basename($fname);
            }

            $first = 'Y'; $flag = 0; $n = 0; $M = 0;
            $parity_vector = '';
            $rows = '';
            $timeStart = microtime(true);

            while (true) {
                if (gmp_cmp($n2, 1) <= 0) break;
                $n++;
                if (gmp_div_r($n2, 2) == 0) {
                    $n1 = $n2; $n2 = gmp_div_q($n2, 2);
                    $module = gmp_div_r($n2, 32);
                    $parity_vector .= '0';
                    if ($flag == 0 && $first === 'Y' && gmp_cmp($n2, $N) < 0) { $flag = 1; $M = $n; }
                    if ($flag == 1 && $first === 'Y') {
                        $rows .= "<tr><td>" . gmp_strval($n1) . " / 2</td><td>=</td><td>" . gmp_strval($n2) . "</td><td class='highlight'>(r=" . gmp_strval($module) . ") &lt; " . gmp_strval($N) . " &lt;--- step {$M}</td></tr>";
                        if ($fp) fwrite($fp, gmp_strval($n1) . " / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ") < " . gmp_strval($N) . " <--- step {$M}\n");
                        $first = 'N';
                    } else {
                        if ($gamen === 'Yes') {
                            $rows .= "<tr><td>" . gmp_strval($n1) . " / 2</td><td>=</td><td>" . gmp_strval($n2) . "</td><td>(r=" . gmp_strval($module) . ")</td></tr>";
                        }
                        if ($fp) fwrite($fp, gmp_strval($n1) . " / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ")\n");
                    }
                } else {
                    $n1 = $n2;
                    $n2 = gmp_div_q(gmp_add(gmp_mul($n2, 3), 1), 2);
                    $module = gmp_div_r($n2, 32);
                    $parity_vector .= '1';
                    if ($flag == 0 && $first === 'Y' && gmp_cmp($n2, $N) < 0) { $flag = 1; $M = $n; }
                    if ($flag == 1 && $first === 'Y') {
                        $rows .= "<tr><td>(" . gmp_strval($n1) . " * 3 + 1) / 2</td><td>=</td><td>" . gmp_strval($n2) . "</td><td class='highlight'>(r=" . gmp_strval($module) . ") &lt; " . gmp_strval($N) . " &lt;--- step {$M}</td></tr>";
                        if ($fp) fwrite($fp, "(" . gmp_strval($n1) . " * 3 + 1) / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ") < " . gmp_strval($N) . " <--- step {$M}\n");
                        $first = 'N';
                    } else {
                        if ($gamen === 'Yes') {
                            $rows .= "<tr><td>(" . gmp_strval($n1) . " * 3 + 1) / 2</td><td>=</td><td>" . gmp_strval($n2) . "</td><td>(r=" . gmp_strval($module) . ")</td></tr>";
                        }
                        if ($fp) fwrite($fp, "(" . gmp_strval($n1) . " * 3 + 1) / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ")\n");
                    }
                }
            }

            $timeEnd = microtime(true);
            $syori_jikan = round($timeEnd - $timeStart, 6);
            $pv_until_glide = substr($parity_vector, 0, $M);

            if ($fp) {
                fwrite($fp, "Steps to reach 1: {$n}\nGlide(" . gmp_strval($N) . ") = {$M}\nParity Vector up to Glide: ({$pv_until_glide})\n");
                fclose($fp);
            }

            $body .= "<div style='text-align:center'><p><font color='red'>Input value = " . gmp_strval($N) . "</font></p><hr style='width:80%'></div>";
            $body .= "<table class='result-table'>{$rows}";
            $body .= "<tr><td colspan='4'><hr></td></tr>";
            $body .= "<tr><td colspan='4' class='summary'>Steps to reach 1: <b>{$n}</b></td></tr>";
            $body .= "<tr><td colspan='4' class='summary'>Glide(" . gmp_strval($N) . ") = <b>{$M}</b></td></tr>";
            $body .= "<tr><td colspan='4' class='summary'>Parity Vector up to Glide: <b>({$pv_until_glide})</b></td></tr>";
            $body .= "<tr><td colspan='4' class='time'>Processing Time: {$syori_jikan} seconds</td></tr></table>";

            if ($dl_file) {
                $body .= "<p style='text-align:center'><a class='dl' href='data/{$dl_file}' download>📥 Download CSV Result</a></p>";
            }
        }
    }
    render_page('calculator', 'Collatz Calculator', $body);
    exit;
}

// ====================== Tab: PV Graph from String ======================

if ($tab === 'pvgraph') {
    $body = <<<HTML
    <h2>4. Parity Vector Graph (from PV string)</h2>
    <div class="desc">Enter a Parity Vector (a string of 0s and 1s) to view its graphical matrix.</div>
    <form method="POST" action="?tab=pvgraph">
        <input type="text" name="pattern" value="" size="60" placeholder="e.g. 10110101" pattern="[01 ]+">
        <input type="submit" value="Execute">
    </form>
    HTML;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = str_replace(' ', '', $_POST['pattern'] ?? '');
        $bit_pattern = preg_replace('/[^01]/', '', trim($raw));
        $bit_length = strlen($bit_pattern);
        $bit_odd = substr_count($bit_pattern, '1');

        if ($bit_length === 0) {
            $body .= '<p class="msg">Please enter a parity vector above and press Execute.</p>';
        } else {
            $graph_html = build_pv_graph_html($bit_pattern);

            if ($bit_length <= 100) {
                $pv_display = htmlspecialchars($bit_pattern, ENT_QUOTES, "UTF-8");
            } else {
                $chunks = [];
                for ($jj = 0; $jj < ceil($bit_length / 100); $jj++) {
                    $chunks[] = htmlspecialchars(substr($bit_pattern, $jj * 100, 100), ENT_QUOTES, "UTF-8");
                }
                $pv_display = implode('<br>', $chunks);
            }

            $body .= "<p class='time'>Input PV Length: {$bit_length} &nbsp; Number of Ones: {$bit_odd}</p>";
            $body .= $graph_html;
            $body .= "<p class='msg'><b>Input PV:</b><br>{$pv_display}</p>";
        }
    }
    render_page('pvgraph', 'PV Graph from String', $body);
    exit;
}

// ====================== Tab: PV Graph from Number ======================

if ($tab === 'numgraph') {
    $body = <<<HTML
    <h2>5. Parity Vector Graph (from Number)</h2>
    <div class="desc">Enter a positive integer N (greater than 1) to compute and visualize its Parity Vector up to the Glide point.</div>
    <form method="POST" action="?tab=numgraph">
        <input type="text" name="number" value="" size="40" placeholder="e.g. 27" oninput="this.value = this.value.replace(/[^0-9]/g, '');">
        <input type="submit" value="Execute">
    </form>
    HTML;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = trim($_POST['number'] ?? '');
        $number = preg_replace('/[^0-9]/', '', $raw);

        if ($number === '') {
            $body .= '<p class="msg">Please enter a positive integer above and press Execute.</p>';
        } elseif ($number === '0' || $number === '1') {
            $body .= '<p class="error">Please enter an integer greater than 1.</p>';
        } else {
            $gmp_number = gmp_init($number);
            [$glide_no, $reach1, $bit_pattern] = glide_check($gmp_number);
            $bit_length = strlen($bit_pattern);
            $bit_odd = substr_count($bit_pattern, '1');

            $graph_html = build_pv_graph_html($bit_pattern);

            if ($bit_length <= 100) {
                $pv_display = htmlspecialchars($bit_pattern, ENT_QUOTES, "UTF-8");
            } else {
                $chunks = [];
                for ($jj = 0; $jj < ceil($bit_length / 100); $jj++) {
                    $chunks[] = htmlspecialchars(substr($bit_pattern, $jj * 100, 100), ENT_QUOTES, "UTF-8");
                }
                $pv_display = implode('<br>', $chunks);
            }

            $body .= "<p class='time'>Status: Glide = {$glide_no}, Number of times reaching 1 = {$reach1}<br>";
            $body .= "Input N: " . htmlspecialchars($number, ENT_QUOTES, "UTF-8") . " &nbsp; PV Length: {$bit_length} &nbsp; Number of Ones: {$bit_odd}</p>";
            $body .= $graph_html;
            $body .= "<p class='msg'><b>Parity Vector (up to Glide):</b><br>{$pv_display}</p>";
        }
    }
    render_page('numgraph', 'PV Graph from Number', $body);
    exit;
}
