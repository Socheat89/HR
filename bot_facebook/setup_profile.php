<?php
/**
 * File: setup_profile.php
 * Description: Sets the "Get Started" button and Greeting Text for the Facebook Page.
 * How to use: Run this file directly in your browser ONE TIME after setting up config.php.
 * Example URL: https://yourdomain.com/bot/setup_profile.php
 */

require_once 'config.php';

// --- Configuration Data ---
$data_payload = [
    // សារស្វាគមន៍ដែលបង្ហាញ "មុនពេល" ចុច Get Started
    'greeting' => [
        [
            'locale' => 'default',
            'text' => 'សូមស្វាគមន៍មកកាន់ Page របស់យើង!'
        ],
        [
            'locale' => 'en_US',
            'text' => 'Welcome to our Page!'
        ]
    ],
    // កំណត់ប៊ូតុង Get Started
    'get_started' => [
        'payload' => 'USER_TAPPED_GET_STARTED' // Payload ដែលនឹងត្រូវផ្ញើទៅ webhook ពេលគេចុច
    ]
];

// --- API Request ---
$url = 'https://graph.facebook.com/v19.0/me/messenger_profile?access_token=' . PAGE_ACCESS_TOKEN;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_payload, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response_body = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// --- Display Result ---
header('Content-Type: text/plain; charset=utf-8');

if ($curl_error) {
    echo "cURL Error: " . $curl_error;
    exit;
}

echo "===== Messenger Profile Setup =====\n\n";
echo "HTTP Status Code: " . $http_code . "\n";
echo "Facebook API Response: " . $response_body . "\n\n";

if ($http_code == 200) {
    echo "SUCCESS: The 'Get Started' button and greeting text have been set correctly.\n";
} else {
    echo "ERROR: Failed to set up the profile. Please check your PAGE_ACCESS_TOKEN in config.php.\n";
}
?>