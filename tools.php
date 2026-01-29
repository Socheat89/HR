<?php
// Enable error logging for debugging (disable display_errors in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');

// Handle file uploads and conversions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uploadDir = 'uploads/';
    $outputDir = 'output/';
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif'];

    // Create directories if they don't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // Clean up files older than 1 hour
    function cleanDirectory($dir, $maxAge = 3600) {
        foreach (glob($dir . '*') as $file) {
            if (filemtime($file) < time() - $maxAge) {
                @unlink($file);
            }
        }
    }
    cleanDirectory($uploadDir);
    cleanDirectory($outputDir);

    // Image to PDF conversion
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileName = preg_replace('/[^A-Za-z0-9\-\_\.]/', '', basename($_FILES['image']['name']));
        $imagePath = $uploadDir . uniqid() . '_' . $fileName;
        $pdfPath = $outputDir . pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
        $fileType = mime_content_type($_FILES['image']['tmp_name']);

        if (!in_array($fileType, $allowedImageTypes)) {
            $error = "Invalid image type. Only JPEG, PNG, and GIF are allowed.";
        } elseif ($_FILES['image']['size'] > $maxFileSize) {
            $error = "Image file is too large. Maximum size is 5MB.";
        } elseif (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
            $error = "Failed to upload image.";
        } else {
            if (!class_exists('Mpdf\Mpdf')) {
                $error = "mPDF library is not installed.";
                error_log("mPDF library missing.");
            } else {
                try {
                    require_once 'vendor/autoload.php';
                    $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
                    $mpdf->AddPage();
                    $mpdf->Image($imagePath, 0, 0, 210, 297); // A4 size
                    $mpdf->Output($pdfPath, 'F');
                    $success = "PDF created: <a href='$pdfPath' download>Download PDF</a>";
                } catch (\Mpdf\MpdfException $e) {
                    $error = "PDF creation failed.";
                    error_log("mPDF error: " . $e->getMessage());
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បម្លែងរូបភាព និង PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">បម្លែងរូបភាព និង PDF</h1>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 text-green-700 p-4 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="bg-red-100 text-red-700 p-4 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Image to PDF Form -->
        <form action="" method="post" enctype="multipart/form-data" class="mb-6" onsubmit="handleSubmit(this)">
            <h2 class="text-lg font-semibold mb-2">បម្លែងរូបភាពទៅជា PDF</h2>
            <input type="file" name="image" accept="image/*" class="w-full p-2 border rounded mb-4" required>
            <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">បម្លែង</button>
        </form>

        <!-- PDF to Image Form (Disabled if Imagick is not installed) -->
        <?php if (extension_loaded('imagick')): ?>
            <form action="" method="post" enctype="multipart/form-data" onsubmit="handleSubmit(this)">
                <h2 class="text-lg font-semibold mb-2">បម្លែង PDF ទៅជារូបភាព</h2>
                <input type="file" name="pdf" accept=".pdf" class="w-full p-2 border rounded mb-4" required>
                <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">បម្លែង</button>
            </form>
        <?php else: ?>
            <div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-4">
                មុខងារបម្លែង PDF ទៅជារូបភាពមិនអាចប្រើបានទេ ដោយសារកម្មវិធី Imagick មិនត្រូវបានដំឡើង។
            </div>
        <?php endif; ?>
    </div>

    <script>
        function handleSubmit(form) {
            const button = form.querySelector('button');
            button.innerText = 'កំពុងដំណើរការ...';
            button.disabled = true;
        }
    </script>
</body>
</html>