<?php
/**
 * Collatz Operation Calculator
 * Collatz_Calculator.php
 *
 * Computes the Collatz sequence for any large natural number.
 * Uses GMP for arbitrary precision arithmetic.
 * Requires: PHP 8.1+, GMP extension
 */

declare(strict_types=1);
set_time_limit(0);
header('Content-Type: text/html; charset=UTF-8');

$data_dir = __DIR__ . '/data';

// ====================== Helper ======================

function make_error_page(string $msg): string {
    return <<<HTML
    <div style="text-align:center; color:red; font-weight:bold; margin:40px;">
        <h2>Input Error</h2>
        <p>{$msg}</p>
        <p>Please enter an integer greater than or equal to 2.</p>
    </div>
    HTML;
}

// ====================== HTML Template ======================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collatz Operation Calculator</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f8f8f8; }
        h2 { text-align: center; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.1); }
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
        .divider { border-top: 1px dashed #888; margin: 6px 0; }
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
                    <input type="text" name="txtN1" size="80"
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

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST'):

    $txtN1 = trim($_POST['txtN1'] ?? '');
    $gamen = $_POST['gamen'] ?? 'Yes';
    $ffout = $_POST['ffout'] ?? 'No';

    // バリデーション
    if ($txtN1 === '' || !ctype_digit($txtN1) || gmp_cmp(gmp_init($txtN1), gmp_init(2)) < 0):
        echo make_error_page(htmlspecialchars($txtN1) . ' is not a valid input.');
    else:

        $n1 = gmp_init($txtN1);
        $n2 = $n1;
        $N  = $n1;

        // ファイル出力の準備
        $fp = null;
        $fname = '';
        if ($ffout === 'Yes') {
            if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
            $mojiretu = gmp_strval($n1);
            if (strlen($mojiretu) >= 200) $mojiretu = substr($mojiretu, 0, 200);
            $fname = $data_dir . '/Collatz-' . $mojiretu . '.csv';
            $fp = fopen($fname, 'w');
            fwrite($fp, "Collatz sequence for " . gmp_strval($n1) . "\n");
        }

        echo "<div style='text-align:center'>";
        echo "<p><font color='red'>Input value = " . gmp_strval($n1) . "</font></p>";
        echo "<hr style='width:80%'>";
        echo "</div>";
        echo "<table class='result-table'>";

        $first = 'Y';
        $flag  = 0;
        $n     = 0;
        $M     = 0;
        $R     = gmp_init(0);
        $parity_vector = '';
        $ZERO  = gmp_init(0);

        $timeStart = microtime(true);

        $rows = '';

        while (true) {
            if (gmp_cmp($n2, 1) <= 0) break;
            $n++;

            if (gmp_div_r($n2, 2) == 0) {
                // 偶数：÷2
                $n1     = $n2;
                $n2     = gmp_div_q($n2, 2);
                $module = gmp_div_r($n2, 32);
                $parity_vector .= '0';

                if ($flag == 0 && $first === 'Y' && gmp_cmp($n2, $N) < 0) {
                    $flag = 1;
                    $M = $n;
                    $R = $n2;
                }

                if ($flag == 1 && $first === 'Y') {
                    $rows .= "<tr><td>" . gmp_strval($n1) . " / 2</td><td>=</td>"
                           . "<td>" . gmp_strval($n2) . "</td>"
                           . "<td class='highlight'>(r=" . gmp_strval($module) . ") &lt; " . gmp_strval($N) . " &lt;--- step {$M}</td></tr>\n";
                    if ($fp) fwrite($fp, gmp_strval($n1) . " / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ") < " . gmp_strval($N) . " <--- step {$M}\n");
                    $first = 'N';
                } else {
                    if ($gamen === 'Yes') {
                        $rows .= "<tr><td>" . gmp_strval($n1) . " / 2</td><td>=</td>"
                               . "<td>" . gmp_strval($n2) . "</td>"
                               . "<td>(r=" . gmp_strval($module) . ")</td></tr>\n";
                    }
                    if ($fp) fwrite($fp, gmp_strval($n1) . " / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ")\n");
                }

            } else {
                // 奇数：(×3+1)÷2
                $n1     = $n2;
                $n2     = gmp_div_q(gmp_add(gmp_mul($n2, 3), 1), 2);
                $module = gmp_div_r($n2, 32);
                $parity_vector .= '1';

                if ($flag == 0 && $first === 'Y' && gmp_cmp($n2, $N) < 0) {
                    $flag = 1;
                    $M = $n;
                    $R = $n2;
                }

                if ($flag == 1 && $first === 'Y') {
                    $rows .= "<tr><td>(" . gmp_strval($n1) . " * 3 + 1) / 2</td><td>=</td>"
                           . "<td>" . gmp_strval($n2) . "</td>"
                           . "<td class='highlight'>(r=" . gmp_strval($module) . ") &lt; " . gmp_strval($N) . " &lt;--- step {$M}</td></tr>\n";
                    if ($fp) fwrite($fp, "(" . gmp_strval($n1) . " * 3 + 1) / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ") < " . gmp_strval($N) . " <--- step {$M}\n");
                    $first = 'N';
                } else {
                    if ($gamen === 'Yes') {
                        $rows .= "<tr><td>(" . gmp_strval($n1) . " * 3 + 1) / 2</td><td>=</td>"
                               . "<td>" . gmp_strval($n2) . "</td>"
                               . "<td>(r=" . gmp_strval($module) . ")</td></tr>\n";
                    }
                    if ($fp) fwrite($fp, "(" . gmp_strval($n1) . " * 3 + 1) / 2 = " . gmp_strval($n2) . " (r=" . gmp_strval($module) . ")\n");
                }
            }
        }

        $timeEnd    = microtime(true);
        $syori_jikan = round($timeEnd - $timeStart, 6);

        $parity_vector_moji = substr($parity_vector, 0, $M);

        echo $rows;

        echo "<tr><td colspan='4'><hr class='divider'></td></tr>";
        echo "<tr><td colspan='4' class='summary'>Steps to reach 1: <b>{$n}</b></td></tr>";
        echo "<tr><td colspan='4' class='summary'>Glide(" . gmp_strval($N) . ") = <b>{$M}</b></td></tr>";
        echo "<tr><td colspan='4' class='summary'>Parity Vector up to Glide: <b>({$parity_vector_moji})</b></td></tr>";
        echo "<tr><td colspan='4' class='time'>Processing Time: {$syori_jikan} seconds</td></tr>";
        echo "</table>";

        if ($fp) {
            fwrite($fp, "Steps to reach 1: {$n}\n");
            fwrite($fp, "Glide(" . gmp_strval($N) . ") = {$M}\n");
            fwrite($fp, "Parity Vector up to Glide: ({$parity_vector_moji})\n");
            fclose($fp);
            $dl_name = basename($fname);
            echo "<p style='text-align:center'><a href='data/{$dl_name}' download>📥 Download CSV Result</a></p>";
        }

    endif;
endif;
?>

</div>
</body>
</html>
