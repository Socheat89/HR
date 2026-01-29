<?php
header('Content-Type: application/json; charset=utf-8');
// Get year and month from GET parameters
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 1970 || $year > 9999) $year = date('Y');

// Calculate calendar details
$today = ($year == date('Y') && $month == date('n')) ? date('j') : 0;
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfWeek = date('N', strtotime("$year-$month-01"));

// Khmer month names
$khmerMonths = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];

// Generate table HTML
ob_start();
?>
<table role="grid" aria-label="ប្រតិទិនសម្រាប់ <?php echo $khmerMonths[$month-1] . ' ' . $year; ?>">
    <tr>
        <th>ច</th><th>អ</th><th>ព</th><th>ព្រ</th><th>សុ</th><th>ស</th><th>អា</th>
    </tr>
    <tr>
    <?php
    $dow = 1;
    for ($i = 1; $i < $firstDayOfWeek; $i++, $dow++) {
        echo "<td class='empty'></td>";
    }
    for ($d = 1; $d <= $daysInMonth; $d++, $dow++) {
        $class = ($d == $today) ? 'today' : '';
        echo "<td class='$class'>$d</td>";
        if ($dow % 7 == 0 && $d != $daysInMonth) {
            echo "</tr><tr>";
        }
    }
    while ($dow % 7 != 1) {
        echo "<td class='empty'></td>";
        $dow++;
    }
    ?>
    </tr>
</table>
<?php
$tableHtml = ob_get_clean();

// Output JSON
echo json_encode([
    'table' => $tableHtml,
    'monthName' => $khmerMonths[$month-1]
]);
?>