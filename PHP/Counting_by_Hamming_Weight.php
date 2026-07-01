<?php
/**
 * Collatz Parity Vector Counter - By Hamming Weight
 * Combined Version (Form + Calculation in one file)
 * Based on Algorithm 2 from Kazunobu Hikawa (2026)
 *
 * Requires: PHP 8.1+, GMP extension (php-gmp)
 */

declare(strict_types=1);
set_time_limit(0);
header('Content-Type: text/html; charset=UTF-8');

const LAMBDA = 1.5849625007211563; // log2(3)

/**
 * Convert GMP number to scientific notation
 */
function gmp_to_sci(GMP $n): string
{
    $s = gmp_strval($n);
    if ($s === '0') return '0.000000e+0';
    $len  = strlen($s);
    $exp  = $len - 1;
    $mant = substr($s, 0, 7);
    $num  = (float)$mant / 1_000_000;
    return sprintf('%.6fe+%d', $num, $exp);
}

/**
 * Minimum length for Hamming weight d
 */
function kmin(int $d): int
{
    return (int)ceil(LAMBDA * $d);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Aggregation of Converged/Unconverged PVs and Counting by Hamming Weight</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f8f8f8; }
        table { border-collapse: collapse; width: 88%; margin: 20px auto; background: white; }
        th, td { border: 2px solid #444; padding: 8px 10px; text-align: center; }
        th { background: #e0e0e0; }
        .header { text-align: center; margin-bottom: 20px; }
        .error { color: red; font-weight: bold; text-align: center; }
        .time { text-align: center; font-size: 15px; color: #1e3a8a; font-weight: bold; }
        input[type="text"] { width: 140px; padding: 6px; font-size: 15px; }
        input[type="submit"] { padding: 10px 36px; font-size: 16px; background: #0066cc; color: white; border: none; cursor: pointer; }
        input[type="submit"]:hover { background: #0055aa; }
        .desc { width: 50%; margin: 0 auto 20px; text-align: left; }
    </style>
</head>
<body>
<div class="header">
    <h2>Aggregation of Converged/Unconverged PVs and Counting by Hamming Weight</h2>
    <p class="desc">
        This tool aggregates the number of converged and unconverged parity vectors based on their Hamming weight (number of ones).
        Please specify the start and end values for the Hamming weight you wish to count.<br>
        The output file will be saved as <code>NumPVofOnes-(start)-(end).txt</code>.
    </p>

    <form method="POST" style="margin: 20px 0;">
        <table border="0" style="width:auto; margin:0 auto; background:transparent; border:none;">
            <tr>
                <td style="border:none;">Start "Hamming Weight" (Integer &ge; 1):</td>
                <td style="border:none;"><input type="text" name="A" value=""></td>
            </tr>
            <tr>
                <td style="border:none;">End "Hamming Weight" (Integer):</td>
                <td style="border:none;"><input type="text" name="B" value=""></td>
            </tr>
            <tr>
                <td colspan="2" style="border:none; text-align:center; padding-top:10px;">
                    <input type="submit" value="Execute">
                </td>
            </tr>
        </table>
    </form>
</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST'):

    $d_start = filter_input(INPUT_POST, 'A', FILTER_VALIDATE_INT) ?? 0;
    $d_end   = filter_input(INPUT_POST, 'B', FILTER_VALIDATE_INT) ?? 0;

    // 入力バリデーション
    if ($d_start < 1 || $d_end < $d_start || $d_start > 10000 || $d_end > 10000):
?>
    <p class="error">Input Error: Please set A &ge; 1, B &ge; A, and both &le; 10000.</p>
<?php
    else:
        $time_start = date('Y-m-d H:i:s');
        $start_sec  = microtime(true);

        // dataディレクトリ作成
        $data_dir = __DIR__ . '/data';
        if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);

        $out_file = "{$data_dir}/NumPVofOnes-{$d_start}-{$d_end}.txt";
        $fp = fopen($out_file, 'w');
        fwrite($fp, "Collatz Parity Vector Counter - d = {$d_start} to {$d_end}\n\n");

        $ZERO = gmp_init(0);
        $ONE  = gmp_init(1);

        $k_upper = (int)floor($d_end * LAMBDA) + 1;

        // テーブル初期化
        $W       = [];
        $W_total = array_fill(0, $k_upper + 1, clone $ZERO);

        for ($i = 0; $i <= $k_upper; $i++) {
            $W[$i] = array_fill(-1, $k_upper + 2, clone $ZERO);
        }

        // 初期値: d=1, u=0
        $W[1][0]    = clone $ONE;
        $W_total[0] = clone $ONE;
        $W_total[1] = clone $ONE;

        echo '<div class="header">';
        echo "<p class='time'>Start Time: {$time_start}</p>";
        echo '<p>(A) X(d) = Just Converged PVs　　(B) W(d) = Unconverged PVs</p>';
        echo '</div>';

        echo '<table>';
        echo '<tr><th>Hamming Weight d</th><th>kmin(d)</th><th>(A) X(d) = Just Converged</th><th>(B) W(d) = Unconverged</th><th>&rho;<sub>d</sub> = W(d)/2<sup>kmin</sup></th></tr>';

        for ($d = 2; $d <= $d_end; $d++) {
            $u_max = (int)floor($d * LAMBDA) + 1;

            if ($d >= $d_start) {
                fwrite($fp, "d = {$d}\n");
            }

            for ($u = 0; $u <= $u_max; $u++) {
                $k = $d + $u;
                $e = ($k < $u_max) ? 1 : 0;

                if ($e === 1) {
                    $prev_d    = $W[$d - 1][$u]     ?? $ZERO;
                    $prev_u    = $W[$d][$u - 1]     ?? $ZERO;
                    $W[$d][$u] = gmp_add($prev_d, $prev_u);
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
            }

            // 指定範囲のみ表示
            if ($d >= $d_start) {
                $km   = kmin($d);
                $xd   = $W_total[$d - 1];
                $wd   = $W_total[$d];
                $pow2 = gmp_pow(gmp_init(2), $km);

                $rho = 0.0;
                if (gmp_cmp($wd, $ZERO) > 0) {
                    $rho = (float)gmp_strval($wd) / (float)gmp_strval($pow2);
                }

                echo "<tr>";
                echo "<td>{$d}</td>";
                echo "<td>{$km}</td>";
                echo "<td>" . gmp_to_sci($xd) . "<br><small>" . gmp_strval($xd) . "</small></td>";
                echo "<td>" . gmp_to_sci($wd)  . "<br><small>" . gmp_strval($wd)  . "</small></td>";
                echo "<td>" . sprintf('%.4e', $rho) . "</td>";
                echo "</tr>\n";
            }
        }

        echo '</table>';

        fclose($fp);

        $time_end = date('Y-m-d H:i:s');
        $duration = round(microtime(true) - $start_sec, 2);

        echo "<p class='time'>
                End Time: {$time_end}<br>
                <strong>Processing Time: {$duration} seconds</strong>
              </p>";
        echo "<p style='text-align:center; margin:20px;'>
                <a href='data/NumPVofOnes-{$d_start}-{$d_end}.txt' download>📥 Download TXT Result</a>
              </p>";

    endif;
endif;
?>

<p style="text-align:center; margin-top:40px; color:#555;">
    <small>Based on Algorithm 2 from "Discrete Geometry and Combinatorial Structure of Parity Vectors in the Collatz Map", Kazunobu Hikawa, 2026</small>
</p>
</body>
</html>
