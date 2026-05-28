<?php
$f = 'admin/table_report.php';
$c = file($f);
echo "Read " . count($c) . " lines.\n";
echo "Line 52: " . trim($c[51]) . "\n";
echo "Line 53: " . trim($c[52]) . "\n";
?>
