<?php
/**
 * Collatz PV Counter by Length - Scientific Notation for Ratio (1 <= A < 10)
 * Example: 2.1234567890123e-100
 * Fix: ratio uses bcmath only (no float cast) for full precision
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
set_time_limit(0);
ini_set('memory_limit', '6G');

const LAMBDA = 1.5849625007211563;

session_start();

$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);

/**
 * GMP数値を短い科学的記法で表示する（表示用）
 */
function sci(GMP $num): string {
    $str = gmp_strval($num);
    if (strlen($str) <= 8) return number_format((int)$str);
    $exp = strlen($str) - 1;
    $mant = substr($str, 0, 6);
    $val = (float)$mant / 100000;
    return sprintf("%.5fe+%d", $val, $exp);
}

/**
 * 高精度な未収束比率を科学的記法で返す（bcmathのみ使用、float変換なし）
 *
 * @param string $D_str  分子（未収束数）の文字列
 * @param string $A_str  分母（総PV数）の文字列
 * @param int    $precision  有効桁数（小数点以下）
 * @return string  例: "1.2345678901234e-45"
 */
function ratio_sci(string $D_str, string $A_str, int $precision = 15): string {
    if ($D_str === '0' || $A_str === '0') return '0.0e+0';

    // 指数の大きさを推定：桁数差 ≒ 指数の絶対値
    $exp_estimate = strlen($A_str) - strlen($D_str);
    // 必要な小数点以下桁数 = 指数分のゼロ + 有効桁数 + 余裕
    $scale = max($exp_estimate + $precision + 10, $precision + 30);
    $ratio_str = bcdiv($D_str, $A_str, $scale);

    // 整数部が0でない場合（比率 >= 1）
    if (!str_starts_with($ratio_str, '0.')) {
        // 整数部の桁数から指数を計算
        [$int_part, $frac_part] = explode('.', $ratio_str . '.0');
        $int_len = strlen(ltrim($int_part, '-'));
        $exp = $int_len - 1;
        $all_digits = $int_part . $frac_part;
        $all_digits = ltrim($all_digits, '0');
        $mant = $all_digits[0] . '.' . substr($all_digits, 1, $precision);
        return "{$mant}e+{$exp}";
    }

    // 小数点以下から先頭のゼロを数えて指数を求める
    [, $frac] = explode('.', $ratio_str);
    $exp = 0;
    $digits = '';
    $leading = true;

    foreach (str_split($frac) as $ch) {
        if ($leading && $ch === '0') {
            $exp--;
        } else {
            $leading = false;
            $digits .= $ch;
        }
    }

    if ($digits === '') return '0.0e+0';

    // 仮数を 1.xxxx 形式に整形
    $exp--; // 1桁目を整数部にするため
    $mant = $digits[0] . '.' . substr($digits, 1, $precision);

    return "{$mant}e{$exp}";
}
?>

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
    </style>
</head>
<body>
<div class="container">
    <h1 style="text-align:center">Collatz Parity Vector Counter by Length</h1>

    <form method="POST" style="text-align:center; margin:25px 0;">
        <label>Start (A): <input type="number" name="A" value="1" min="1" max="10000" required></label>
        <label style="margin-left:40px;">End (B): <input type="number" name="B" value="200" min="1" max="10000" required></label>
        <label style="margin-left:40px;">Ratio Precision: <input type="number" name="PREC" value="15" min="5" max="50" required></label>
        <button type="submit" style="margin-left:50px;">Start Calculation</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $k_start  = max(1, min(10000, (int)($_POST['A'] ?? 1)));
        $k_end    = max(1, min(10000, (int)($_POST['B'] ?? 200)));
        $ratio_prec = max(5, min(50, (int)($_POST['PREC'] ?? 15)));

        if ($k_end < $k_start) {
            echo '<p style="color:red; text-align:center;">Error: B must be ≥ A</p>';
        } else {
            $start_time_str = date('Y-m-d H:i:s');
            $start_time = microtime(true);
            echo "<p class='time'>Start Time: {$start_time_str}</p>";

            $filename = "PV_Length_{$k_start}_{$k_end}";
            $txt_file = "{$data_dir}/{$filename}.txt";
            $fp = fopen($txt_file, 'w');

            $ZERO = gmp_init(0);
            $ONE  = gmp_init(1);

            $W_total = [];
            $X_total = [];
            $W = [];
            $X = [];
            $CON = [];

            for ($k = 0; $k <= $k_end; $k++) {
                $W_total[$k] = clone $ZERO;
                $X_total[$k] = clone $ZERO;
            }

            $W[1][1] = clone $ONE;
            $X[1][0] = clone $ONE;
            $X[2][1] = clone $ONE;
            $W_total[1] = clone $ONE;
            $X_total[1] = clone $ONE;
            $X_total[2] = clone $ONE;
            $CON[1] = 1;
            $CON[2] = 2;

            // 未収束 (W) の計算
            for ($k = 2; $k <= $k_end; $k++) {
                for ($d = 0; $d <= $k; $d++) {
                    $boundary = (int)floor($d * LAMBDA) + 1;
                    if ($k < $boundary) {
                        $WS = gmp_add($W[$k-1][$d] ?? $ZERO, $W[$k-1][$d-1] ?? $ZERO);
                        $W[$k][$d] = $WS;
                    }
                    $W_total[$k] = gmp_add($W_total[$k], $W[$k][$d] ?? $ZERO);
                }
            }

            // 収束 (X) の計算
            for ($k = 4; $k <= $k_end; $k++) {
                for ($d = 2; $d < $k; $d++) {
                    $boundary = (int)floor($d * LAMBDA) + 1;
                    if ($k == $boundary) {
                        $WS = gmp_add($W[$k-1][$d] ?? $ZERO, $W[$k][$d-1] ?? $ZERO);
                        $X[$k][$d] = $WS;
                        $CON[$k] = $boundary;
                    }
                    $X_total[$k] = gmp_add($X_total[$k], $X[$k][$d] ?? $ZERO);
                }
            }

            echo "<table>";
            echo "<tr>
                    <th>k</th>
                    <th>Conv Steps</th>
                    <th>(A) Total PVs</th>
                    <th>(B) Converged</th>
                    <th>(C) Already Conv.</th>
                    <th>(D) Unconverged</th>
                    <th>(E) Ratio D/A</th>
                  </tr>";

            fwrite($fp, "k\tConvSteps\tA_TotalPVs\tB_Converged\tC_AlreadyConv\tD_Unconverged\tRatio\n");

            for ($k = $k_start; $k <= $k_end; $k++) {
                $A_val = gmp_pow(gmp_init(2), $k);
                $B_val = $X_total[$k] ?? $ZERO;
                $D_val = $W_total[$k] ?? $ZERO;
                $C_val = gmp_sub(gmp_sub($A_val, $B_val), $D_val);

                // 高精度比率：bcmathのみ使用、float変換なし
                $ratio_sci = ratio_sci(gmp_strval($D_val), gmp_strval($A_val), $ratio_prec);

                echo "<tr>";
                echo "<td><b>{$k}</b></td>";
                echo "<td>" . ($CON[$k] ?? '-') . "</td>";
                echo "<td class='left'>" . sci($A_val) . "<br><small>" . gmp_strval($A_val) . "</small></td>";
                echo "<td class='left'>" . sci($B_val) . "<br><small>" . gmp_strval($B_val) . "</small></td>";
                echo "<td class='left'>" . sci($C_val) . "<br><small>" . gmp_strval($C_val) . "</small></td>";
                echo "<td class='left'>" . sci($D_val) . "<br><small>" . gmp_strval($D_val) . "</small></td>";
                echo "<td class='left'><span class='sci'>{$ratio_sci}</span></td>";
                echo "</tr>";

                fwrite($fp, "{$k}\t" . ($CON[$k] ?? '-') . "\t" . gmp_strval($A_val) . "\t" . gmp_strval($B_val) . "\t" . gmp_strval($C_val) . "\t" . gmp_strval($D_val) . "\t{$ratio_sci}\n");
            }
            echo "</table>";

            fclose($fp);

            $end_time_str = date('Y-m-d H:i:s');
            $duration = round(microtime(true) - $start_time, 2);

            echo "<p class='time' style='text-align:center; margin:25px;'>
                  End Time: {$end_time_str}<br>
                  <strong>Processing Time: {$duration} seconds</strong>
                  </p>";

            echo "<p style='text-align:center; margin:25px;'>
                  <a href='data/{$filename}.txt' download>📥 Download TXT Result</a>
                  </p>";
        }
    }
    ?>
</div>
</body>
</html>
