<?php
/**
 * Collatz_PV_Graph_from_Number.php
 * Parity Vector Graph generated from an input Natural Number N
 *
 * - Computes the Collatz parity vector (PV) for N up to its Glide point
 * - Displays a graphical matrix of that parity vector path
 * - Same graphical style as Collatz_PV_Graph.php
 *
 * Requires: PHP 8.1+, GMP extension
 */

declare(strict_types=1);
set_time_limit(0);
header('Content-Type: text/html; charset=UTF-8');

// ====================== Glide Check Function ======================

/**
 * Run the Collatz sequence from $number and return:
 *  [Glide value, total stopping time, parity vector up to total stopping time]
 */
function glide_check(GMP $number): array
{
    $M     = 0;
    $first = true;
    $flag  = false;
    $one   = gmp_init(1);
    $n     = 0;
    $N     = $number;
    $n2    = $number;
    $parity_vector = '';

    while (true) {
        if (gmp_cmp($n2, $one) <= 0) break;
        $n++;

        if (gmp_div_r($n2, 2) == 0) {
            // Even: divide by 2
            $n2 = gmp_div_q($n2, 2);
            $parity_vector .= '0';
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) {
                $flag = true;
                $M    = $n;
            }
            if ($flag && $first) {
                $first = false;
            }
        } else {
            // Odd: (×3+1)÷2
            $n2 = gmp_div_q(gmp_add(gmp_mul($n2, 3), $one), gmp_init(2));
            $parity_vector .= '1';
            if (!$flag && $first && gmp_cmp($n2, $N) < 0) {
                $flag = true;
                $M    = $n;
            }
        }
    }

    return [$M, $n, $parity_vector];
}

// ====================== Input ======================

$raw    = trim($_POST['number'] ?? '');
$number = preg_replace('/[^0-9]/', '', $raw); // digits only

$bit_pattern = '';
$bit_length  = 0;
$bit_odd     = 0;
$glide_no    = 0;
$reach1      = 0;
$valid_input = false;
$error       = null;

if ($number !== '') {
    if ($number === '0' || $number === '1') {
        $error = 'Please enter an integer greater than 1.';
    } else {
        $valid_input = true;
        $gmp_number  = gmp_init($number);
        [$glide_no, $reach1, $bit_pattern] = glide_check($gmp_number);
        $bit_length = strlen($bit_pattern);
        $bit_odd    = substr_count($bit_pattern, '1');
    }
}
?>
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

<?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>

<?php elseif (!$valid_input): ?>
    <p class="msg">Please enter a positive integer above and press Execute.</p>

<?php else: ?>

    <p class="msg">
        <b>Status:</b> Glide = <?= $glide_no ?>, Number of times reaching 1 = <?= $reach1 ?><br>
        <b>Input N:</b> <?= htmlspecialchars($number, ENT_QUOTES, "UTF-8") ?> &nbsp;
        <b>PV Length:</b> <?= $bit_length ?> &nbsp;
        <b>Number of Ones:</b> <?= $bit_odd ?>
    </p>

<?php
    // ====================== Build Graph Table ======================

    $Table_max = $bit_length + 10;
    $Pat_table = [0 => ' '];
    for ($i = 0; $i < $bit_length; $i++) {
        $Pat_table[$i + 1] = substr($bit_pattern, $i, 1);
    }

    $Bit_table = [];
    $zen_d     = -1;

    // Row 0: column headers (d values)
    for ($j = 0; $j <= $Table_max; $j++) {
        $Bit_table[0][$j] = $j;
    }

    // Rows 1..Table_max: fill * and 0 markers
    for ($i = 1; $i <= $Table_max; $i++) {
        $d       = (int)($i * log(2) / log(3));
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
    for ($i = 0; $i < $bit_length; $i++) {
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
    for ($i = 1; $i <= $bit_length; $i++) {
        echo '<th>' . htmlspecialchars($Pat_table[$i], ENT_QUOTES, "UTF-8") . '</th>';
    }
    echo '</tr>';

    // Column index row (k / d)
    echo '<tr>';
    echo '<th style="white-space:nowrap">k &nbsp; d</th>';
    for ($j = 0; $j <= $Table_max; $j++) {
        echo '<th>&ensp;' . $j . '</th>';
        if ($j >= $bit_length + 1) break;
    }
    echo '</tr>';

    // Data rows
    $zen_d = -1;
    for ($i = 1; $i <= $Table_max; $i++) {
        $d = (int)($i * log(2) / log(3));
        echo '<tr>';
        echo '<th>' . $i . '</th>';
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
            if ($cell === '*') break;
        }
        echo '</tr>';
    }
    echo '</table>';

    echo '<div class="explain-box">';
    echo '<b>(Explanation)</b><br>';
    echo 'A cell shown in <span style="background:yellow; padding:0 4px;">yellow</span> means that if the Parity Vector stops at that cell, the Parity Vector is just converged. If it does not reach this cell, it indicates that it is an unconverged PV.<br><br>';
    echo 'The Parity Vector that continues past the "0" cells will be already converged.<br><br>';
    echo 'Therefore, in the figure, <b>Region A</b> (the area to the right of cell "0") is the <b>unconverged region</b>, and <b>Region B</b> (the area to the left of cell "0") is the <b>already converged region</b>.';
    echo '</div>';

    echo '</div>'; // close flex container

    if ($bit_length > 100) {
        echo "<p class='msg'><b>Parity Vector (up to Glide):</b><br>";
        for ($jj = 0; $jj < ceil($bit_length / 100); $jj++) {
            echo htmlspecialchars(substr($bit_pattern, $jj * 100, 100), ENT_QUOTES, "UTF-8") . "<br>";
        }
        echo "</p>";
    } else {
        echo "<p class='msg'><b>Parity Vector (up to Glide):</b> " . htmlspecialchars($bit_pattern, ENT_QUOTES, "UTF-8") . "</p>";
    }

endif;
?>

</div>
</body>
</html>
