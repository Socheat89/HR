<?php
// បង្ហាញ Error ដើម្បីងាយស្រួលរកបញ្ហា (អ្នកអាចលុប/Comment បន្ទាត់២នេះចេញពេលដាក់ឲ្យប្រើប្រាស់จริง)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['file'])) {
    http_response_code(400);
    die("Error 400: Bad Request. File parameter is missing.");
}

// ការពារ Directory Traversal Attack - នេះគឺសំខាន់បំផុតសម្រាប់សុវត្ថិភាព
$requested_file = basename($_GET['file']); 
$file_path = __DIR__ . '/Uploads/' . $requested_file;

// ---- DEBUGGING CODE (អ្នកអាចលុប Comment ដើម្បីតេស្ត) ----
// die("Checking path: " . $file_path); 
// ----------------------------------------------------

// ពិនិត្យថា File មានពិតប្រាកដ ហើយអាចអានបាន
if (file_exists($file_path) && is_readable($file_path)) {
    
    // ប្រើ finfo ដើម្បីកំណត់ប្រភេទ File (MIME Type)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        http_response_code(500);
        die("Error 500: Could not initialize fileinfo.");
    }
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    // កំណត់ Headers សម្រាប់ Browser
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    header('Content-Disposition: inline; filename="' . $requested_file . '"');
    header('Accept-Ranges: bytes');
    
    // សម្អាត Output Buffer ដែលអាចមាន Error ឬដកឃ្លាដែលមើលមិនឃើញ
    ob_clean();
    flush();
    
    // បញ្ជូនទិន្នន័យ File ទៅកាន់ Browser
    readfile($file_path);
    exit;
} else {
    // បើរក File មិនឃើញ ឬអានមិនបាន បង្ហាញ Error 404
    http_response_code(404);
    die("Error 404: File not found or is not readable.");
}
?>