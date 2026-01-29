<?php
// Enable full error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

/**
 * MINI-AUTOLOADER FOR PICQER BARCODE LIBRARY
 * This function will automatically load the required class files when they are needed.
 * This is the standard way to solve the problem without Composer.
 */
spl_autoload_register(function ($class) {
    // Defines the base namespace and the base directory for the library
    $prefix = 'Picqer\\Barcode\\';
    $base_dir = __DIR__ . '/lib/Barcode/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators, and append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});


// Get the asset tag and model from the URL
$assetTag = $_GET['tag'] ?? 'NO-TAG';
$model = $_GET['model'] ?? 'N/A';

// Use the full namespace for the class
use Picqer\Barcode\BarcodeGeneratorPNG;

// Now, when PHP sees "new BarcodeGeneratorPNG()", our autoloader function will run.
// It will find the file 'lib/Barcode/BarcodeGeneratorPNG.php' and include it.
// Then, when that file needs "TypeCode128", the autoloader will run again
// and find 'lib/Barcode/Types/TypeCode128.php' automatically.
$generator = new BarcodeGeneratorPNG();
$barcodeImage = $generator->getBarcode($assetTag, $generator::TYPE_CODE_128, 2, 60);
$barcodeBase64 = 'data:image/png;base64,' . base64_encode($barcodeImage);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Barcode: <?php echo htmlspecialchars($assetTag); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; }
        .label {
            width: 3.5in;
            height: 1.5in;
            border: 1px dashed #ccc;
            padding: 0.1in;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            page-break-inside: avoid;
        }
        .barcode { max-width: 90%; height: 60px; object-fit: contain; }
        .asset-tag { font-size: 14pt; font-weight: bold; margin-top: 5px; letter-spacing: 1px; }
        .model { font-size: 9pt; color: #555; }
        @media print {
            body { margin: 0; }
            .label { border: none; }
            .no-print { display: none; }
        }
        .print-button-container {
            position: fixed;
            top: 20px;
            left: 20px;
        }
        .print-button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="no-print print-button-container">
        <button onclick="window.print()" class="print-button"><i class="fas fa-print"></i> Print Label</button>
    </div>

    <div class="label">
        <img src="<?php echo $barcodeBase64; ?>" alt="Barcode" class="barcode">
        <div class="asset-tag"><?php echo htmlspecialchars($assetTag); ?></div>
        <div class="model"><?php echo htmlspecialchars($model); ?></div>
    </div>
</body>
</html>