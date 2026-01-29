<?php
// Set a high error reporting level for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "    PHP & FFmpeg Server Debugger    \n";
echo "========================================\n\n";

// --- Step 1: Check if shell_exec is enabled ---
echo "--- ជំហានទី១: ពិនិត្យមើល Function shell_exec ---\n";
if (function_exists('shell_exec')) {
    echo "ស្ថានភាព: ដំណើរការ (shell_exec is enabled).\n";
    $disabled_functions = ini_get('disable_functions');
    if (stripos($disabled_functions, 'shell_exec') !== false) {
        echo "คำเตือน: 'shell_exec' ស្ថិតនៅក្នុង 'disable_functions'។ នេះអាចជាបញ្ហា។\n";
        echo "disable_functions list: " . htmlspecialchars($disabled_functions) . "\n";
    }
} else {
    echo "ស្ថានភាព: បរាជ័យ (shell_exec is DISABLED).\n";
    echo "ដំណោះស្រាយ: សូមទាក់ទងអ្នកគ្រប់គ្រង Hosting របស់អ្នក ហើយសុំឲ្យគេដក 'shell_exec' ចេញពី 'disable_functions' នៅក្នុងไฟล์ php.ini។\n";
    exit(); // Stop here if the function doesn't exist
}
echo "\n";

// --- Step 2: Check FFmpeg path and permissions ---
echo "--- ជំហានទី២: ពិនិត្យមើលទីតាំង និង Permission របស់ FFmpeg ---\n";
$ffmpeg_path = __DIR__ . '/ffmpeg';
echo "ទីតាំងដែលរំពឹងទុក: " . htmlspecialchars($ffmpeg_path) . "\n";

if (file_exists($ffmpeg_path)) {
    echo "ស្ថានភាពไฟล์: ដំណើរការ (រកឃើញไฟล์ FFmpeg)។\n";
    if (is_executable($ffmpeg_path)) {
        echo "ស្ថានភាព Permission: ដំណើរការ (ไฟล์នេះអាចដំណើរការបាន)។\n";
    } else {
        echo "ស្ថានភាព Permission: បរាជ័យ (ไฟล์នេះគ្មានសិទ្ធិដំណើរការ)។\n";
        echo "ដំណោះស្រាយ: សូមកំណត់ Permission របស់ไฟล์ '" . htmlspecialchars($ffmpeg_path) . "' ទៅជា 0755 ដោយប្រើ File Manager។\n";
    }
} else {
    echo "ស្ថានភាពไฟล์: បរាជ័យ (រកមិនឃើញไฟล์ FFmpeg នៅទីតាំងនេះទេ)។\n";
    echo "ដំណោះស្រាយ: សូមប្រាកដថាអ្នកបាន Upload ไฟล์ 'ffmpeg' ចូលไปใน folder 'ffmpeg' แล้ว។\n";
}
echo "\n";


// --- Step 3: Try to run a simple FFmpeg command and capture ALL output ---
echo "--- ជំហានទី៣: សាកល្បងដំណើរការ FFmpeg និងចាប់យកលទ្ធផលទាំងអស់ ---\n";
if (file_exists($ffmpeg_path) && is_executable($ffmpeg_path)) {
    // We run the simplest command: -version.
    // '2>&1' is CRITICAL. It redirects all error messages to the standard output, so we can capture everything.
    $command = escapeshellcmd($ffmpeg_path) . ' -version 2>&1';
    echo "កំពុងដំណើរការ Command: " . htmlspecialchars($command) . "\n\n";

    echo "---------- លទ្ធផលពី Server ----------\n";
    $output = shell_exec($command);

    if ($output === null) {
        echo "លទ្ធផលគឺ NULL។ នេះអាចមានន័យថា shell_exec ត្រូវបានបិទដោយវិធីផ្សេងទៀត (safe_mode?) ឬក៏ដំណើរការបានបរាជ័យទាំងស្រុង។\n";
    } elseif (empty(trim($output))) {
        echo "លទ្ធផលគឺទទេ។ ដំណើរការ FFmpeg ប្រហែលជាត្រូវបានបញ្ឈប់ដោយ Server (Resource Limit?) ឬក៏ជួបបញ្ហា Permission។\n";
    } else {
        // Print the raw output
        echo htmlspecialchars($output);
    }
    echo "\n---------- ចប់លទ្ធផល ----------\n";
} else {
    echo "រំលងជំហាននេះ ព្រោះរកមិនឃើញ FFmpeg ឬក៏វាគ្មានសិទ្ធិដំណើរការ។\n";
}