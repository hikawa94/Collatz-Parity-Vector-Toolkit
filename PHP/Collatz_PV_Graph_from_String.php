<?php
/**
 * Collatz_PV_Graph.php
 * Parity Vector Graph & Detailed Information
 *
 * - Displays a graphical matrix of the parity vector path
 * - Computes Generator, Resultant, Glide for each prefix of the PV
 * - Saves detailed results to ./data/ as an HTML file
 *
 * Requires: PHP 8.1+, GMP extension
 */

declare(strict_types=1);
set_time_limit(0);
header('Content-Type: text/html; charset=UTF-8');

// ====================== Glide Check Function ======================

/**
 * Compute the Glide (first step where value drops below initial N)
 */
function glide_check(GMP $number): int
{
    $M     = 0;
    $first = true;
    $flag  = false;
    $one   = gmp_init(1);
    $n     = 0;
    $N     = $number;
    $n2    = $number;

    while (true) {
        if (gmp_cmp($n2, $one) <= 0) break;
        $n++;

        if (gmp_div_r($n2, 2) == 0) {
            // Even: divide by 2
            $n2 = gmp_div_q($n2, 2);
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) {
                $flag = true;
                $M    = $n;
            }
            if ($flag && $first) {
                $first = false;
            }
        } else {
            // Odd: (×3+1)÷2
            $n2 = gmp_div_q(gmp_add(gmp_mul($n2, 3), gmp_init(1)), gmp_init(2));
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) {
                $flag = true;
                $M    = $n;
            }
        }
    }
    return $M;
}

// ====================== Input ======================

$raw         = $_POST['pattern'] ?? '';
$raw         = str_replace(' ', '', $raw);
$BIT_PATTERN = trim($raw);
// Allow only 0 and 1
$BIT_PATTERN = preg_replace('/[^01]/', '', $BIT_PATTERN);
$BIT_length  = strlen($BIT_PATTERN);
$BIT_odd     = substr_count($BIT_PATTERN, '1');

// ====================== Data Directory ======================

$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);

// ====================== HTML Page ======================
?>
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
        <input type="text" name="pattern"
               value=""
               placeholder="e.g. 10110101"
               pattern="[01 ]+">
        <input type="submit" value="Execute">
    </form>

<?php if ($BIT_length === 0): ?>
    <p class="msg">Please enter a parity vector above and press Execute.</p>

<?php else: ?>

<?php
    // ====================== Build Graph Table ======================

    $Table_max = $BIT_length + 10;
    $Pat_table = [0 => ' '];
    for ($i = 0; $i < $BIT_length; $i++) {
        $Pat_table[$i + 1] = substr($BIT_PATTERN, $i, 1);
    }

    // Initialize Bit_table
    $Bit_table = [];
    $zen_d     = -1;

    // Row 0: column headers (d values)
    for ($j = 0; $j <= $Table_max; $j++) {
        $Bit_table[0][$j] = $j;
    }

    // Rows 1..$Table_max: fill * and 0 markers
    for ($i = 1; $i <= $Table_max; $i++) {
        $d     = (int)($i * log(2) / log(3));
        $Gyo_end = false;
        for ($j = 0; $j <= $Table_max; $j++) {
            if ($j === $d) {
                if ($zen_d !== $d) {
                    $Bit_table[$i][$j] = '0';
                    $zen_d = $d;
                } else {
                    $Bit_table[$i][$j] = ' ';
                }
            } elseif ($j === ($i + 1)) {
                $Bit_table[$i][$j] = '*';
                $Gyo_end = true;
            } else {
                $Bit_table[$i][$j] = ' ';
            }
            if ($Gyo_end) break;
        }
    }

    // Overlay PV bits onto the table
    $Pos_j = 0;
    for ($i = 0; $i < $BIT_length; $i++) {
        $Pos_i = $i + 1;
        $BIT   = $Pat_table[$i + 1];
        if (($Bit_table[$i][$i] ?? '') === '*') continue;
        if ($BIT === '0') {
            $Bit_table[$Pos_i][$Pos_j] = '<span class="red-bold">0</span>';
        } else {
            $Bit_table[$Pos_i][$Pos_j + 1] = '<span class="red-bold">1</span>';
            $Pos_j++;
        }
    }

    // ====================== Render Graph Table ======================
    echo '<div style="display:flex; align-items:flex-start; gap:25px;">';
    echo '<table class="pv-table">';

    // Header row: PV bits
    echo '<tr class="pv-header-row">';
    echo '<th><span style="font-size:11px">PV →</span>' . $Pat_table[0] . '</th>';
    for ($i = 1; $i <= $BIT_length; $i++) {
        echo '<th>' . htmlspecialchars($Pat_table[$i], ENT_QUOTES, "UTF-8") . '</th>';
    }
    echo '</tr>';

    // Column index row (k / d)
    echo '<tr>';
    echo '<th style="white-space:nowrap">k &nbsp; d</th>';
    for ($j = 0; $j <= $Table_max; $j++) {
        echo '<th>&ensp;' . $j . '</th>';
        if ($j >= $BIT_length + 1) break;
    }
    echo '</tr>';

    // Data rows
    $zen_d = -1;
    for ($i = 1; $i <= $Table_max; $i++) {
        $d = (int)($i * log(2) / log(3));
        echo '<tr>';
        echo '<th>' . $i . '</th>';
        $row_done = false;
        for ($j = 0; $j <= $Table_max; $j++) {
            $cell  = $Bit_table[$i][$j] ?? ' ';
            $class = '';
            if ($j === $d) {
                if ($zen_d !== $d) {
                    $class = 'yellow';
                    $zen_d = $d;
                }
            }
            echo '<td' . ($class ? " class=\"{$class}\"" : '') . '>' . $cell . '</td>';
            if ($cell === '*') { $row_done = true; break; }
        }
        echo '</tr>';
        if ($i > $BIT_length + 2 && $row_done) {
            // keep rendering until Table_max for context
        }
    }
    echo '</table>';

    echo '<div style="max-width:380px; background:#fff8e1; border:1px solid #d4a017; border-radius:8px; padding:14px 18px; font-size:13px; line-height:1.7;">';
    echo '<b>(Explanation)</b><br>';
    echo 'A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that it is an unconverged PV.<br><br>';
    echo 'The Parity Vector that continues past the "0" cells will be already converged.<br><br>';
    echo 'Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>, and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.';
    echo '</div>';

    echo '</div>'; // close flex container

    // ====================== Compute Detailed Info ======================

    $ZERO = gmp_init(0);
    $ONE  = gmp_init(1);
    $TWO  = gmp_init(2);

    $First_bit = substr($BIT_PATTERN, 0, 1);

    // Initialize pre_ variables
    if ($First_bit === '1') {
        $pre_Length        = 1;
        $pre_Ones          = 1;
        $pre_Generator     = gmp_init(1);
        $pre_zen_Resultant = gmp_init(1);
        $pre_Resultant     = gmp_init(2);
        $pre_OddEven       = gmp_init(0); // even
        $pre_Glide         = 2;
        $pre_Power_of_2    = gmp_pow(gmp_init(2), 1);
    } else {
        $pre_Length        = 1;
        $pre_Ones          = 0;
        $pre_Generator     = gmp_init(2);
        $pre_zen_Resultant = gmp_init(1);
        $pre_Resultant     = gmp_init(1);
        $pre_OddEven       = gmp_init(1); // odd
        $pre_Glide         = 1;
        $pre_Power_of_2    = gmp_pow(gmp_init(2), 1);
    }

    // Prepare output file
    $timestamp = date('Y-m-d-H-i');
    $out_fname = "GeneratorForPVstring{$BIT_length}_{$timestamp}.html";
    $out_fpath = $data_dir . '/' . $out_fname;
    $fp        = fopen($out_fpath, 'w');

    // Write detail file header
    fwrite($fp, "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"UTF-8\">\n");
    fwrite($fp, "<title>Result detailed information on the input PV</title>\n</head>\n<body>\n");
    fwrite($fp, "<h3 style='text-align:center'>Result detailed information on the input PV</h3>\n");
    fwrite($fp, "<table border='1'>\n");

    // PV display (wrap at 100 chars)
    if ($BIT_length <= 100) {
        fwrite($fp, "<tr><td colspan='7'>Input PV = {$BIT_PATTERN}</td></tr>\n");
    } else {
        $pv_display = "<tr><td colspan='7'>Input PV =<br>";
        for ($jj = 0; $jj < ceil($BIT_length / 100); $jj++) {
            $pv_display .= ($jj > 0 ? '<br>' : '') . substr($BIT_PATTERN, $jj * 100, 100);
        }
        fwrite($fp, $pv_display . "</td></tr>\n");
    }

    fwrite($fp, "<tr>
        <th>Ratio<br>(Glide/Length)</th>
        <th>Length from<br>beginning of PV</th>
        <th>Number of Ones</th>
        <th>Generator</th>
        <th>Pre-Resultant</th>
        <th>Resultant</th>
        <th>Glide<br>(Convergence Time)</th>
    </tr>\n");

    // First row
    if ($First_bit === '0') {
        fwrite($fp, "<tr><td align='center'><font color='red'><b>1.00</b></font></td><td align='center'>1</td><td align='center'>0</td><td>2</td><td>2</td><td>1</td><td><font color='red'>1 (Just Converged)</font></td></tr>\n");
    } else {
        fwrite($fp, "<tr><td align='center'><b>-</b></td><td align='center'>1</td><td align='center'>1</td><td>1</td><td>1</td><td>2</td><td><b>-</b></td></tr>\n");
    }

    // Compute rows for ii = 2..$BIT_length
    $detail_rows = [];

    // First row for inline display
    if ($First_bit === '0') {
        $detail_rows[] = ['ratio' => '1.00', 'length' => 1, 'ones' => 0, 'gen' => '2', 'pre_res' => '2', 'res' => '1', 'glide' => 1, 'just' => true];
    } else {
        $detail_rows[] = ['ratio' => '-', 'length' => 1, 'ones' => 1, 'gen' => '1', 'pre_res' => '1', 'res' => '2', 'glide' => '-', 'just' => false];
    }

    for ($ii = 2; $ii <= $BIT_length; $ii++) {
        $gen_Length  = $ii;
        $gen_Pattern = substr($BIT_PATTERN, 0, $ii);
        $gen_Ones    = substr_count($gen_Pattern, '1');
        $cur_bit     = substr($gen_Pattern, $ii - 1, 1);

        if ($cur_bit == gmp_strval($pre_OddEven)) {
            $gen_Generator     = $pre_Generator;
            $gen_zen_Resultant = $pre_Resultant;
            if (gmp_mod($gen_zen_Resultant, $TWO) == 1) {
                $gen_Resultant = gmp_div_q(gmp_add(gmp_mul(gmp_init(3), $gen_zen_Resultant), $ONE), $TWO);
            } else {
                $gen_Resultant = gmp_div_q($gen_zen_Resultant, $TWO);
            }
            $gen_Glide = $pre_Glide;
        } else {
            $gen_Generator     = gmp_add($pre_Generator, $pre_Power_of_2);
            $gen_Glide         = glide_check($gen_Generator);
            $ws_ones           = gmp_pow(gmp_init(3), $pre_Ones);
            $gen_zen_Resultant = gmp_add($pre_Resultant, $ws_ones);
            if (gmp_mod($gen_zen_Resultant, $TWO) == 1) {
                $gen_Resultant = gmp_div_q(gmp_add(gmp_mul(gmp_init(3), $gen_zen_Resultant), $ONE), $TWO);
            } else {
                $gen_Resultant = gmp_div_q($gen_zen_Resultant, $TWO);
            }
        }

        $data_E         = ($gen_Glide > 0 && $gen_Length > 0) ? (float)$gen_Glide / (float)$gen_Length : 0.0;
        $gen_Ratio      = number_format($data_E, 2);
        $gen_OddEven    = gmp_mod($gen_Resultant, $TWO);
        $gen_Power_of_2 = gmp_mul($pre_Power_of_2, $TWO);
        $just           = ($data_E == 1.0);

        // Write to file
        $g_str   = gmp_strval($gen_Generator);
        $zr_str  = gmp_strval($gen_zen_Resultant);
        $r_str   = gmp_strval($gen_Resultant);
        if ($just) {
            fwrite($fp, "<tr><td align='center'><font color='red'><b>{$gen_Ratio}</b></font></td><td align='center'>{$gen_Length}</td><td align='center'>{$gen_Ones}</td><td>{$g_str}</td><td>{$zr_str}</td><td>{$r_str}</td><td><font color='red'>{$gen_Glide} (Just Converged)</font></td></tr>\n");
        } else {
            fwrite($fp, "<tr><td align='center'>{$gen_Ratio}</td><td align='center'>{$gen_Length}</td><td align='center'>{$gen_Ones}</td><td>{$g_str}</td><td>{$zr_str}</td><td>{$r_str}</td><td>{$gen_Glide}</td></tr>\n");
        }

        $detail_rows[] = [
            'ratio'   => $gen_Ratio,
            'length'  => $gen_Length,
            'ones'    => $gen_Ones,
            'gen'     => $g_str,
            'pre_res' => $zr_str,
            'res'     => $r_str,
            'glide'   => $gen_Glide,
            'just'    => $just,
        ];

        // Update pre_ variables
        $pre_Length        = $gen_Length;
        $pre_Ones          = $gen_Ones;
        $pre_Generator     = $gen_Generator;
        $pre_zen_Resultant = $gen_zen_Resultant;
        $pre_Resultant     = $gen_Resultant;
        $pre_OddEven       = $gen_OddEven;
        $pre_Glide         = $gen_Glide;
        $pre_Power_of_2    = $gen_Power_of_2;
    }

    fwrite($fp, "</table>\n</body>\n</html>\n");
    fclose($fp);

    // ====================== Render Detail Table Inline ======================
    echo "<p class='msg'>";
    echo "<b>Input PV:</b> " . htmlspecialchars($BIT_PATTERN, ENT_QUOTES, "UTF-8") . "<br>";
    echo "<b>Length:</b> {$BIT_length} &nbsp; <b>Number of Ones:</b> {$BIT_odd}<br>";
    echo "Detailed result file: <a href='data/{$out_fname}' target='_blank'>📄 Open in new tab</a> &nbsp;|&nbsp; ";
    echo "<a href='data/{$out_fname}' download>📥 Download</a>";
    echo "</p>";

    echo "<h3>Detailed Information on Input PV</h3>";

    // PV display
    if ($BIT_length <= 100) {
        echo "<p><b>Input PV = </b>" . htmlspecialchars($BIT_PATTERN, ENT_QUOTES, "UTF-8") . "</p>";
    } else {
        echo "<p><b>Input PV =</b><br>";
        for ($jj = 0; $jj < ceil($BIT_length / 100); $jj++) {
            echo htmlspecialchars(substr($BIT_PATTERN, $jj * 100, 100), ENT_QUOTES, "UTF-8") . "<br>";
        }
        echo "</p>";
    }

    echo '<table class="detail-table">';
    echo '<tr>
            <th>Ratio<br>(Glide/Length)</th>
            <th>Length from<br>beginning of PV</th>
            <th>Number of Ones</th>
            <th>Generator</th>
            <th>Pre-Resultant</th>
            <th>Resultant</th>
            <th>Glide<br>(Convergence Time)</th>
          </tr>';

    foreach ($detail_rows as $row) {
        $just_class = $row['just'] ? ' class="just"' : '';
        echo "<tr{$just_class}>";
        echo "<td align='center'>" . ($row['just'] ? "<b>{$row['ratio']}</b>" : $row['ratio']) . "</td>";
        echo "<td align='center'>{$row['length']}</td>";
        echo "<td align='center'>{$row['ones']}</td>";
        echo "<td>{$row['gen']}</td>";
        echo "<td>{$row['pre_res']}</td>";
        echo "<td>{$row['res']}</td>";
        if ($row['just']) {
            echo "<td><b>{$row['glide']} (Just Converged)</b></td>";
        } else {
            echo "<td>{$row['glide']}</td>";
        }
        echo "</tr>\n";
    }
    echo '</table>';

endif; // BIT_length > 0
?>

</div>
</body>
</html>
