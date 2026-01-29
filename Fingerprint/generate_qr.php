<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'vendor/autoload.php';
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\JpgWriter; // Use JpgWriter instead of PngWriter

// Log incoming request for debugging
file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Received index: " . var_export($_GET, true) . "\n", FILE_APPEND);

header('Content-Type: image/jpeg'); // Set content type to JPEG
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!isset($_GET['index']) || !is_numeric($_GET['index'])) {
    http_response_code(400);
    echo "ឥន្ទន៍មិនត្រឹមត្រូវ! (Debug: index=" . ($_GET['index'] ?? 'not set') . ")";
    exit;
}

$index = intval($_GET['index']);
$dataFile = 'data.json';

if (!file_exists($dataFile) || !is_readable($dataFile)) {
    http_response_code(500);
    echo "ឯកសារទិន្នន័យមិនអាចចូលបាន!";
    exit;
}

$data = json_decode(file_get_contents($dataFile), true);

if (!isset($data['allowedLocations'][$index])) {
    http_response_code(404);
    echo "ទីតាំងមិនត្រូវបានរកឃើញ! (Index: $index)";
    exit;
}

$location = $data['allowedLocations'][$index];
$qrData = json_encode([
    'locationId' => $location['id'] ?? time(),
    'name' => $location['name'] ?? 'Unknown Location',
    'branch' => $location['branch'] ?? 'N/A',
    'latitude' => $location['latitude'] ?? 0.0,
    'longitude' => $location['longitude'] ?? 0.0,
    'users' => $location['users'] ?? []
]);

try {
    $qrCode = QrCode::create($qrData)
        ->setSize(300)
        ->setMargin(10);
    $writer = new JpgWriter(); // Use JpgWriter
    $result = $writer->write($qrCode);
    $result->saveToFile('php://output');
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "កំហុសក្នុងការបង្កើត QR Code: " . $e->getMessage();
    exit;
}
?>