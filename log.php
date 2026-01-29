<?php
$logFile = __DIR__ . '/access.log';

// ទទួល IP អ្នកប្រើ
$userIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';

// ទទួល URI ដែលបាន request
$requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown URI';

// ទទួលពេលវេលា
$date = date('Y-m-d H:i:s');

// បង្កើត message log
$logMessage = "[$date] IP: $userIP requested $requestUri" . PHP_EOL;

// សរសេរទៅឯកសារ log (បន្ថែមថ្មីៗទៅចុង)
file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);

$logFile = __DIR__ . '/logs/access-' . date('Y-m-d') . '.log';
?>
