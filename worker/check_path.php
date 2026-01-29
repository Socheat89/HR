<?php
echo "<h1>Diagnostic Check</h1>";

// ទីតាំង Folder ដែលយើងគិតថាถูกต้อง
$file_path = __DIR__ . '/phpspreadsheet_lib/src/PhpSpreadsheet/Spreadsheet.php';

echo "<p><strong>Checking for file at this exact path:</strong></p>";
echo "<pre>" . htmlspecialchars($file_path) . "</pre>";

echo "<hr>";

echo "<p><strong>Does the file exist?</strong></p>";
if (file_exists($file_path)) {
    echo "<p style='color:green; font-weight:bold;'>YES, file exists!</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>NO, file does NOT exist at this path.</p>";
    echo "<p><strong>Probable Cause:</strong> The folder structure inside 'phpspreadsheet_lib' is incorrect. It might be nested one level too deep, like <code>phpspreadsheet_lib/PhpSpreadsheet-1.29.0/src/...</code> which is wrong.</p>";
}

echo "<hr>";

echo "<p><strong>Is the file readable?</strong></p>";
if (is_readable($file_path)) {
    echo "<p style='color:green; font-weight:bold;'>YES, file is readable.</p>";
} else {
    echo "<p style='color:red; font-weight:bold;'>NO, file is NOT readable.</p>";
    echo "<p><strong>Probable Cause:</strong> File or folder permissions are incorrect. Please set folders to 755 and files to 644.</p>";
}

?>