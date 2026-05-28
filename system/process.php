<?php
session_start();
require 'vendor/autoload.php'; // Composer autoload for mPDF and other dependencies
use Imagick;
use Mpdf\Mpdf;

header('Content-Type: application/json');

try {
    // Create directories
    $uploadDir = 'uploads/';
    $outputDir = 'output/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    if (!file_exists($outputDir)) mkdir($outputDir, 0777, true);

    // Get form data
    $files = $_FILES['files'];
    $conversionMode = $_POST['conversionMode'];
    $outputFormat = $_POST['outputFormat'];
    $autoCrop = isset($_POST['autoCrop']) && $_POST['autoCrop'] === 'true';
    $removeBg = isset($_POST['removeBg']) && $_POST['removeBg'] === 'true';
    $outputFolders = array_map('json_decode', $_POST['outputFolders']);
    $fileFolders = array_map('json_decode', $_POST['folders'], true);

    // Validate inputs
    if (empty($files) || empty($outputFolders)) {
        throw new Exception('សូមជ្រើសឯកសារ និង Folder សម្រាប់រក្សាទុក');
    }

    $savedFiles = [];
    $zipFiles = [];
    $imagesToCombine = [];

    // Process each file
    foreach ($files['tmp_name'] as $index => $tmpName) {
        $fileName = $files['name'][$index];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $uploadPath = $uploadDir . uniqid() . '.' . $fileExt;

        // Validate file
        $allowedTypes = ['png', 'jpg', 'jpeg', 'pdf'];
        if (!in_array($fileExt, $allowedTypes)) {
            throw new Exception("ប្រភេទឯកសារមិនត្រឹមត្រូវ: $fileName");
        }
        if ($files['size'][$index] > 10 * 1024 * 1024) {
            throw new Exception("ឯកសារធំជាង 10 MB: $fileName");
        }
        if ($files['error'][$index] !== UPLOAD_ERR_OK) {
            throw new Exception("កំហុសក្នុងការផ្ទុកឯកសារ: $fileName");
        }

        move_uploaded_file($tmpName, $uploadPath);

        $assignedFolders = $fileFolders[$index];
        if (empty($assignedFolders)) continue;

        if ($conversionMode === 'pdf_to_jpg' && $fileExt === 'pdf') {
            // Convert PDF to JPG
            $jpgPaths = convertPdfToJpg($uploadPath, $outputDir, $fileName);
            foreach ($jpgPaths as $jpgPath) {
                if ($autoCrop || $removeBg) {
                    $jpgPath = processImage($jpgPath, $autoCrop, $removeBg);
                }
                foreach ($outputFolders as $folder) {
                    if (in_array($folder->name, $assignedFolders)) {
                        $outputPath = $outputDir . $folder->name . '/' . $folder->filePrefix . '_' . basename($jpgPath);
                        if (!file_exists($outputDir . $folder->name)) {
                            mkdir($outputDir . $folder->name, 0777, true);
                        }
                        copy($jpgPath, $outputPath);
                        $savedFiles[] = $outputPath;
                        $zipFiles[] = $outputPath;
                    }
                }
            }
        } else {
            // Process images
            $processedPath = $uploadPath;
            if ($autoCrop || $removeBg) {
                $processedPath = processImage($uploadPath, $autoCrop, $removeBg);
            }

            foreach ($outputFolders as $folder) {
                if (!in_array($folder->name, $assignedFolders)) continue;
                $outputPath = $outputDir . $folder->name . '/' . $folder->filePrefix . '_' . $fileName;
                if (!file_exists($outputDir . $folder->name)) {
                    mkdir($outputDir . $folder->name, 0777, true);
                }

                if ($conversionMode === 'single_pdf') {
                    $imagesToCombine[] = $processedPath;
                } elseif ($conversionMode === 'individual_pdf') {
                    createPdf([$processedPath], str_replace('.' . $fileExt, '.pdf', $outputPath));
                    $savedFiles[] = str_replace('.' . $fileExt, '.pdf', $outputPath);
                    $zipFiles[] = str_replace('.' . $fileExt, '.pdf', $outputPath);
                } else {
                    $ext = ($outputFormat === 'transparent' || $outputFormat === 'PNG') ? 'png' : 'pdf';
                    $outputPath = str_replace('.' . $fileExt, '.' . $ext, $outputPath);
                    if ($ext === 'pdf') {
                        createPdf([$processedPath], $outputPath);
                    } else {
                        copy($processedPath, $outputPath);
                    }
                    $savedFiles[] = $outputPath;
                    $zipFiles[] = $outputPath;
                }
            }
        }
    }

    // Combine images into a single PDF if needed
    if ($conversionMode === 'single_pdf' && !empty($imagesToCombine)) {
        foreach ($outputFolders as $folder) {
            if (!file_exists($outputDir . $folder->name)) {
                mkdir($outputDir . $folder->name, 0777, true);
            }
            $outputPath = $outputDir . $folder->name . '/' . $folder->filePrefix . '.pdf';
            createPdf($imagesToCombine, $outputPath);
            $savedFiles[] = $outputPath;
            $zipFiles[] = $outputPath;
        }
    }

    // Create ZIP file
    $zipPath = $outputDir . 'processed_files_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        foreach ($zipFiles as $file) {
            if (file_exists($file)) {
                $zip->addFile($file, basename($file));
            }
        }
        $zip->close();
    }

    // Clean up temporary files
    foreach (glob($uploadDir . '*') as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'បានរក្សាទុកឯកសារនៅ: ' . implode(', ', array_unique(array_map('dirname', $savedFiles))),
        'download' => $zipPath
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'កំហុស: ' . $e->getMessage()
    ]);
}

function processImage($path, $autoCrop, $removeBg) {
    try {
        $imagick = new Imagick($path);
        
        if ($autoCrop) {
            $imagick->trimImage(0);
            $imagick->setImagePage(0, 0, 0, 0);
        }
        
        if ($removeBg) {
            // Simplified background removal for blue signatures
            $imagick->thresholdImage(0.8 * $imagick->getQuantum());
            $imagick->transparentPaintImage('white', 0, 10000, false);
        }
        
        $outputPath = str_replace(pathinfo($path, PATHINFO_EXTENSION), 'png', $path);
        $imagick->writeImage($outputPath);
        $imagick->destroy();
        return $outputPath;
    } catch (Exception $e) {
        throw new Exception("មិនអាចដំណើរការរូបភាព: " . $e->getMessage());
    }
}

function convertPdfToJpg($pdfPath, $outputDir, $baseName) {
    try {
        $outputPaths = [];
        $imagick = new Imagick();
        $imagick->readImage($pdfPath);
        foreach ($imagick as $i => $page) {
            $outputPath = $outputDir . pathinfo($baseName, PATHINFO_FILENAME) . "_page_$i.jpg";
            $page->setImageFormat('jpg');
            $page->writeImage($outputPath);
            $outputPaths[] = $outputPath;
        }
        $imagick->destroy();
        return $outputPaths;
    } catch (Exception $e) {
        throw new Exception("មិនអាចបម្លែង PDF ទៅ JPG: " . $e->getMessage());
    }
}

function createPdf($imagePaths, $outputPath) {
    try {
        $mpdf = new Mpdf(['default_font' => 'KhmerOS']);
        foreach ($imagePaths as $image) {
            $mpdf->AddPage();
            $mpdf->Image($image, 0, 0, 210, 297); // A4 size
        }
        $mpdf->Output($outputPath, 'F');
    } catch (Exception $e) {
        throw new Exception("មិនអាចបង្កើត PDF: " . $e->getMessage());
    }
}
?>