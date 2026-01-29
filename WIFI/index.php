<?php
// ====================================================================
//  DEBUGGING & ERROR HANDLING
//  This will force PHP to display errors on the screen,
//  which helps us see why it's crashing.
// ====================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ====================================================================
//  PHP BACKEND LOGIC
// ====================================================================

// This part of the code will only run when a file is uploaded (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Always start by setting the content type to JSON
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    // Use a try-catch block to handle any potential crashes gracefully
    try {
        // --- STEP 1: VERIFY COMPOSER AUTOLOAD ---
        $autoload_path = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload_path)) {
            // If this file doesn't exist, Composer was not installed correctly.
            throw new Exception('Server Configuration Error: Composer libraries not found. Please run "composer require ilovepdf/ilovepdf-php" in your project directory.');
        }
        require_once($autoload_path);

        // --- STEP 2: CHECK UPLOADS FOLDER ---
        $uploads_dir = __DIR__ . '/uploads';
        if (!is_dir($uploads_dir)) {
            if (!mkdir($uploads_dir, 0777, true)) {
                throw new Exception('Server Permission Error: Cannot create "uploads" directory.');
            }
        }
        if (!is_writable($uploads_dir)) {
            throw new Exception('Server Permission Error: The "uploads" directory is not writable.');
        }

        // --- STEP 3: VALIDATE FILE UPLOAD ---
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error. The file might be too large or corrupted.');
        }
        
        // --- STEP 4: MOVE UPLOADED FILE ---
        $file = $_FILES['file'];
        $original_file_name = basename($file['name']);
        $original_path = $uploads_dir . '/' . $original_file_name;

        if (!move_uploaded_file($file['tmp_name'], $original_path)) {
            throw new Exception('Server Error: Failed to move uploaded file.');
        }
        
        // --- STEP 5: PROCESS WITH ILOVEPDF API ---
        
        // !!! **សំខាន់มาก៖** ដាក់ Key ថ្មីរបស់អ្នក (ដែលអ្នកទើបតែបង្កើត) នៅទីនេះ !!!
        $publicKey = 'project_public_7305ba7aeaaf7c2a672551b2140e489f_75tZGeff81f88b7dcacebb216a422493b9f1e';
        $secretKey = 'secret_key_1c15f8a626808b600428411eaba71c43_1woFk0b1b10c9629af254160b8597a9682077';

        if ($publicKey === 'YOUR_NEW_PUBLIC_KEY_HERE' || $secretKey === 'YOUR_NEW_SECRET_KEY_HERE') {
            throw new Exception('API keys are not configured. Please add your new iLovePDF keys to index.php.');
        }

        $ilovepdf = new \Ilovepdf\Ilovepdf($publicKey, $secretKey);
        $conversion_type = isset($_POST['conversionType']) ? htmlspecialchars($_POST['conversionType']) : 'unknown';
        
        $task_map = [
            'word-to-pdf'       => ['task' => 'officepdf', 'ext' => '.pdf'],
            'powerpoint-to-pdf' => ['task' => 'officepdf', 'ext' => '.pdf'],
            'excel-to-pdf'      => ['task' => 'officepdf', 'ext' => '.pdf'],
            'jpg-to-pdf'        => ['task' => 'imagepdf',  'ext' => '.pdf'],
            'pdf-to-word'       => ['task' => 'pdfsword',  'ext' => '.docx'],
            'pdf-to-powerpoint' => ['task' => 'pdfspowerpoint', 'ext' => '.pptx'],
            'pdf-to-excel'      => ['task' => 'pdfsexcel', 'ext' => '.xlsx'],
            'pdf-to-jpg'        => ['task' => 'pdfjpg',    'ext' => '.jpg'],
        ];

        if (!isset($task_map[$conversion_type])) {
            throw new Exception("Conversion type '{$conversion_type}' is not supported.");
        }

        $task_info = $task_map[$conversion_type];
        $task = $ilovepdf->newTask($task_info['task']);
        $task->addFile($original_path);
        
        if ($task_info['task'] === 'pdfjpg') $task->setPackaged(false);
        
        $task->execute();

        $file_info = pathinfo($original_path);
        $file_name_without_ext = $file_info['filename'];
        $new_extension = $task_info['ext'];
        $converted_file_name = $file_name_without_ext . '_converted' . $new_extension;
        
        $task->download($uploads_dir);
        
        $downloaded_api_filename = $task->getOutputFileName();
        $final_path = $uploads_dir . '/' . $converted_file_name;
        if ($downloaded_api_filename !== $converted_file_name) {
             rename($uploads_dir . '/' . $downloaded_api_filename, $final_path);
        }

        // Relative path for the download link
        $download_url = 'uploads/' . $converted_file_name;

        // If everything is successful, create the success response
        $response = [
            'success' => true,
            'message' => 'File converted successfully with iLovePDF!',
            'downloadUrl' => $download_url,
            'downloadName' => $converted_file_name
        ];

        // Clean up the original file
        unlink($original_path);

    } catch (Exception $e) {
        // If any error (Exception) happens, it will be caught here
        $response['message'] = $e->getMessage();
        
        // Clean up the uploaded file if it exists
        if (isset($original_path) && file_exists($original_path)) {
            unlink($original_path);
        }
    }

    // Send the final response (either success or error) as JSON
    echo json_encode($response);
    
    // Stop the script here
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All-in-One File Converter</title>
    <!-- CSS is the same -->
    <style>
        :root { --color-word: #2b579a; --color-ppt: #d24726; --color-excel: #217346; --color-pdf: #ae0e0e; --color-jpg: #e8a200; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f7f8fc; display: flex; flex-direction: column; align-items: center; padding: 40px; margin: 0; color: #333; }
        .main-container { width: 100%; max-width: 1200px; }
        h1 { text-align: center; margin-bottom: 40px; color: #2c3e50; }
        .converter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; }
        .card { background-color: #ffffff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06); border: 1px solid #e9ecef; transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1); }
        .card-icon { font-size: 24px; font-weight: bold; color: white; width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
        .icon-word { background-color: var(--color-word); } .icon-ppt { background-color: var(--color-ppt); } .icon-excel { background-color: var(--color-excel); } .icon-pdf { background-color: var(--color-pdf); } .icon-jpg { background-color: var(--color-jpg); }
        .card-title { font-size: 1.15rem; font-weight: 600; margin: 0 0 10px 0; color: #343a40; }
        .card-description { font-size: 0.9rem; color: #6c757d; line-height: 1.5; margin: 0; flex-grow: 1; }
        #file-input { display: none; }
        #status-area { margin-top: 30px; padding: 15px 20px; border-radius: 8px; text-align: left; font-weight: 500; display: none; transition: background-color 0.3s; word-wrap: break-word; }
        #status-area.loading { background-color: #e9ecef; color: #495057; }
        #status-area.success { background-color: #d4edda; color: #155724; text-align: center; }
        #status-area.error { background-color: #f8d7da; color: #721c24; }
        #status-area a { font-weight: bold; color: #0056b3; text-decoration: none; border-bottom: 2px solid #0056b3; padding-bottom: 2px; }
        #status-area pre { background-color: rgba(0,0,0,0.05); padding: 10px; border-radius: 5px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <div class="main-container">
        <h1>File Conversion Tools</h1>
        <div id="status-area"></div>
        <input type="file" id="file-input">
        <div class="converter-grid">
            <!-- All the cards are here -->
            <div class="card" data-type="pdf-to-word" data-accept=".pdf"><div class="card-icon icon-word">W</div><h2 class="card-title">PDF to Word</h2><p class="card-description">Convert PDFs to editable DOCX documents.</p></div>
            <div class="card" data-type="pdf-to-powerpoint" data-accept=".pdf"><div class="card-icon icon-ppt">P</div><h2 class="card-title">PDF to PowerPoint</h2><p class="card-description">Convert PDFs to editable PPTX slideshows.</p></div>
            <div class="card" data-type="pdf-to-excel" data-accept=".pdf"><div class="card-icon icon-excel">X</div><h2 class="card-title">PDF to Excel</h2><p class="card-description">Extract data from PDFs into XLSX spreadsheets.</p></div>
            <div class="card" data-type="word-to-pdf" data-accept=".doc,.docx"><div class="card-icon icon-pdf">W > PDF</div><h2 class="card-title">Word to PDF</h2><p class="card-description">Convert DOC and DOCX files to PDF.</p></div>
            <div class="card" data-type="powerpoint-to-pdf" data-accept=".ppt,.pptx"><div class="card-icon icon-pdf">P > PDF</div><h2 class="card-title">PowerPoint to PDF</h2><p class="card-description">Convert PPT and PPTX files to PDF.</p></div>
            <div class="card" data-type="excel-to-pdf" data-accept=".xls,.xlsx"><div class="card-icon icon-pdf">X > PDF</div><h2 class="card-title">Excel to PDF</h2><p class="card-description">Convert XLS and XLSX files to PDF.</p></div>
            <div class="card" data-type="jpg-to-pdf" data-accept="image/jpeg,image/png,image/gif"><div class="card-icon icon-pdf">JPG > PDF</div><h2 class="card-title">JPG to PDF</h2><p class="card-description">Combine JPG, PNG, or GIF images into one PDF.</p></div>
            <div class="card" data-type="pdf-to-jpg" data-accept=".pdf"><div class="card-icon icon-jpg">JPG</div><h2 class="card-title">PDF to JPG</h2><p class="card-description">Convert each page of a PDF into a JPG image.</p></div>
        </div>
    </div>
    <!-- JavaScript with improved error display -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fileInput = document.getElementById('file-input');
            const statusArea = document.getElementById('status-area');
            const cards = document.querySelectorAll('.card');
            cards.forEach(card => {
                card.addEventListener('click', () => {
                    fileInput.dataset.conversionType = card.dataset.type;
                    fileInput.setAttribute('accept', card.dataset.accept);
                    fileInput.click();
                });
            });
            fileInput.addEventListener('change', (event) => {
                const file = event.target.files[0]; if (!file) return;
                const formData = new FormData();
                formData.append('file', file);
                formData.append('conversionType', fileInput.dataset.conversionType);
                statusArea.style.display = 'block';
                statusArea.className = 'loading';
                statusArea.innerHTML = `Processing <strong>${file.name}</strong>... Please wait.`;
                
                fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => {
                    const contentType = response.headers.get("content-type");
                    if (response.ok && contentType && contentType.indexOf("application/json") !== -1) {
                        // If response is OK and is JSON, process it as JSON
                        return response.json();
                    } else {
                        // If not, it's likely an HTML error page from PHP
                        return response.text().then(text => {
                            // Create a custom error object to throw
                            let error = new Error("PHP script crashed. See details below.");
                            error.responseHtml = text; // Attach the HTML response to the error
                            throw error;
                        });
                    }
                })
                .then(data => {
                    if (data.success) {
                        statusArea.className = 'success';
                        statusArea.innerHTML = `${data.message} <a href="${data.downloadUrl}" download="${data.downloadName}">Download Now</a>`;
                    } else {
                        // This handles JSON errors sent by our PHP script, e.g., {"success": false, "message": "..."}
                        statusArea.className = 'error';
                        statusArea.innerHTML = `<strong>Application Error:</strong><br><pre>${data.message}</pre>`;
                    }
                })
                .catch(error => {
                    // This catches network errors and the custom error we threw above
                    statusArea.className = 'error';
                    if (error.responseHtml) {
                        // If it's a PHP crash, display the HTML error page directly
                        statusArea.innerHTML = '<strong>A critical server error occurred (PHP Crash):</strong>';
                        statusArea.innerHTML += error.responseHtml;
                    } else {
                        // For other errors (e.g., network down)
                        statusArea.innerHTML = `<strong>Fetch Error:</strong><br><pre>${error.message}</pre>`;
                    }
                    console.error(error);
                });
                event.target.value = '';
            });
        });
    </script>
</body>
</html>