<?php
// Check if composer autoload exists, if not, prompt to install
if (!file_exists('vendor/autoload.php')) {
    die("Error: Please install the required library using 'composer require endroid/qrcode' in the terminal.");
}

// Include the QR Code library
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\ErrorCorrectionLevel;

// Function to generate QR Code
function generateQRCode($text, $filename) {
    // Validate input
    if (empty($text)) {
        return ["success" => false, "message" => "Error: No text provided for QR Code generation."];
    }

    try {
        // Check if directory is writable
        $directory = __DIR__;
        if (!is_writable($directory)) {
            return ["success" => false, "message" => "Error: Directory '$directory' is not writable. Please check permissions."];
        }

        // Create QR Code object
        $qrCode = new QrCode($text);
        
        // Set properties
        $qrCode->setSize(300);
        $qrCode->setMargin(10);
        $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel\ErrorCorrectionLevelMedium()); // Better error correction
        
        // Use PNG writer
        $writer = new PngWriter();
        
        // Generate and save QR Code
        $result = $writer->write($qrCode);
        $result->saveToFile($directory . '/' . $filename);
        
        return ["success" => true, "message" => "QR Code generated successfully!", "filename" => $filename];
    } catch (Exception $e) {
        return ["success" => false, "message" => "Error: " . $e->getMessage()];
    }
}

// Handle form submission and default example
$output = "";
$defaultUrl = "https://example.com";
$defaultFilename = "qrcode.png";

// Generate default QR Code on page load
$defaultResult = generateQRCode($defaultUrl, $defaultFilename);
$output .= $defaultResult["message"] . "<br>";
if ($defaultResult["success"]) {
    $output .= '<img src="' . $defaultFilename . '?t=' . time() . '" alt="Default QR Code"><br><br>';
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["text"])) {
    $pasteText = trim($_POST["text"]);
    $pasteFilename = "paste_qrcode.png";
    
    $result = generateQRCode($pasteText, $pasteFilename);
    $output .= $result["message"] . "<br>";
    if ($result["success"]) {
        $output .= '<img src="' . $pasteFilename . '?t=' . time() . '" alt="Paste QR Code">';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>QR Code Generator</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; }
        img { margin: 10px; }
        textarea { margin: 10px; }
    </style>
</head>
<body>
    <h2>QR Code Generator</h2>
    <p>Default QR Code (<?php echo htmlspecialchars($defaultUrl); ?>):</p>
    <?php echo $output; ?>
    
    <h3>Create Your Own QR Code</h3>
    <form method="post" action="">
        <textarea name="text" rows="4" cols="50" placeholder="Paste your text or link here"></textarea><br>
        <input type="submit" value="Generate QR Code">
    </form>
</body>
</html>