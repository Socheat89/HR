<?php
require 'vendor/autoload.php';
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\Drivers\Facebook\FacebookDriver;
use PDO;

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=chatbot_db", "username", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load BotMan Facebook Driver
DriverManager::loadDriver(FacebookDriver::class);

// BotMan configuration
$config = [
    'facebook' => [
        'token' => null, // Will be dynamically set per page
        'app_secret' => 'YOUR_APP_SECRET',
        'verification' => 'YOUR_VERIFY_TOKEN',
    ]
];

// Create BotMan instance
$botman = BotManFactory::create($config);

// Webhook verification for Facebook
if (isset($_GET['hub_verify_token']) && $_GET['hub_verify_token'] === $config['facebook']['verification']) {
    echo $_GET['hub_challenge'];
    exit;
}

// Function to get page token from database
function getPageToken($pdo, $pageId) {
    $stmt = $pdo->prepare("SELECT access_token FROM pages WHERE page_id = ?");
    $stmt->execute([$pageId]);
    return $stmt->fetchColumn();
}

// Function to get response from database
function getResponse($pdo, $pageId, $responseType) {
    $stmt = $pdo->prepare("SELECT response_text FROM responses WHERE page_id = ? AND response_type = ?");
    $stmt->execute([$pageId, $responseType]);
    return $stmt->fetchColumn() ?: "Default response not set.";
}

// Function to save user interaction
function saveUserInteraction($pdo, $userId, $pageId, $message) {
    $stmt = $pdo->prepare("INSERT INTO users (user_id, page_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $pageId, $message]);
}

// Middleware to set page-specific token
$botman->middleware->received(function($payload, $next, $bot) use ($pdo) {
    $pageId = $payload['recipient']['id'] ?? null;
    if ($pageId) {
        $token = getPageToken($pdo, $pageId);
        if ($token) {
            $bot->getDriver()->setConfig(['token' => $token]);
        }
    }
    return $next($payload);
});

// Welcome message
$botman->hears('start|hi|hello', function (BotMan $bot) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, 'start');
    $bot->reply(getResponse($pdo, $pageId, 'welcome'));
});

// Product catalog
$botman->hears('products', function (BotMan $bot) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, 'products');
    $bot->reply(getResponse($pdo, $pageId, 'products'));
});

// Product details
$botman->hears('T-Shirt|Sneakers|Headphones', function (BotMan $bot, $product) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, $product);

    $details = [
        'T-Shirt' => 'Comfortable cotton T-Shirt, available in all sizes. Price: $20',
        'Sneakers' => 'Stylish sneakers with great support. Price: $50',
        'Headphones' => 'Wireless headphones with noise cancellation. Price: $100'
    ];
    $bot->reply($details[$product]);
    $bot->reply("Type 'order $product' to purchase or 'products' to see more.");
});

// Order processing
$botman->hears('order {product}', function (BotMan $bot, $product) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, "order $product");

    $bot->reply("Great choice! You've selected $product.");
    $bot->reply("Please provide your shipping address to proceed.");
});

// Support queries
$botman->hears('support', function (BotMan $bot) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, 'support');

    $bot->reply("How can our support team help you? Type your issue or question.");
});

// Fallback for unrecognized messages
$botman->fallback(function (BotMan $bot) use ($pdo) {
    $userId = $bot->getUser()->getId();
    $pageId = $bot->getMessage()->getRecipient();
    saveUserInteraction($pdo, $userId, $pageId, $bot->getMessage()->getText());
    $bot->reply(getResponse($pdo, $pageId, 'fallback'));
});

// Start listening
$botman->listen();
?>