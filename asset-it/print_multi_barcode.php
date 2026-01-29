<?php
// Add error reporting to see the exact problem.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// We need two files from the simple library we will create
require_once __DIR__ . '/barcode_generator.php'; 

// Check if assets were posted
$assets = $_POST['assets'] ?? [];

if (empty($assets)) {
    echo "<!DOCTYPE html><html><head><title>Error</title><body style='font-family: sans-serif; text-align: center; padding-top: 50px;'>";
    echo "<h2>No Assets Selected</h2>";
    echo "<p>No assets were selected for printing. Please go back to the dashboard and check at least one asset.</p>";
    echo "<button onclick='window.close()'>Close</button>";
    echo "</body></html>";
    exit();
}

// Create an instance of the barcode generator
$generator = new Picqer\Barcode\BarcodeGeneratorPNG();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Multiple Labels</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #ccc;
        }
        .label {
            width: 8cm;
            height: 4cm;
            border: 1px dashed #999;
            padding: 5px;
            box-sizing: border-box;
            background-color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            page-break-inside: avoid;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 5px;
        }
        .barcode-img {
            max-height: 50px; /* Control the height of the barcode */
        }
        .tag-text {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .serial-text {
            font-size: 8pt;
            margin-top: 2px;
            text-align: center;
            word-break: break-all;
        }
        .print-controls {
            text-align: center;
            padding: 20px;
            background-color: #eee;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #ccc;
        }

        @media print {
            body { background-color: white; }
            .print-controls { display: none; }
            .label { border: none; margin: 0; page-break-after: auto; }
            @page { margin: 0.5cm; }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <h2>Print Preview (<?php echo count($assets); ?> Labels)</h2>
        <p>Ensure your printer settings are set for your label sheets (e.g., Paper Size: A4).</p>
        <button onclick="window.print()">Print All Labels</button>
        <button onclick="window.close()">Close</button>
    </div>

    <?php foreach ($assets as $asset): 
        $tag = $asset['tag'] ?? 'NO-TAG';
        $serial = $asset['serial'] ?? 'N/A';
        
        // This is the new, reliable method. It creates the image data directly.
        $barcode_data = '';
        if ($tag !== 'NO-TAG' && !empty($tag)) {
            try {
                $barcode_data = $generator->getBarcode($tag, $generator::TYPE_CODE_128, 2, 50);
            } catch (Exception $e) {
                // handle exception
            }
        }
    ?>
    <div class="label">
        <?php if (!empty($barcode_data)): ?>
            <!-- Embed the image directly into the HTML. This is very reliable. -->
            <img class="barcode-img" src="data:image/png;base64,<?php echo base64_encode($barcode_data); ?>" alt="Barcode for <?php echo htmlspecialchars($tag); ?>">
        <?php else: ?>
            <div style="color: red;">Invalid Tag</div>
        <?php endif; ?>
        <div class="tag-text"><?php echo htmlspecialchars($tag); ?></div>
        <div class="serial-text"><?php echo htmlspecialchars($serial); ?></div>
    </div>
    <?php endforeach; ?>

</body>
</html>