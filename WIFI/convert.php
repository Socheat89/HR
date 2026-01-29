<?php
/*
 * ====================================================================
 *  PHP BACKEND LOGIC (with REAL iLovePDF API integration)
 * ====================================================================
 */

// ตรวจสอบว่าเป็น Request แบบ POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ตั้งค่า Header เพื่อบอก Browser ว่าการตอบกลับเป็น JSON
    header('Content-Type: application/json');

    $uploads_dir = 'uploads';
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    // 1. ตรวจสอบและสร้างโฟลเดอร์ 'uploads'
    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0777, true)) {
            $response['message'] = 'Server error: Cannot create "uploads" directory. Please check folder permissions.';
            echo json_encode($response);
            exit;
        }
    }
    if (!is_writable($uploads_dir)) {
        $response['message'] = 'Server error: The "uploads" directory is not writable. Please check folder permissions.';
        echo json_encode($response);
        exit;
    }

    // 2. ตรวจสอบว่ามีไฟล์ส่งมาหรือไม่
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $response['message'] = 'File upload error. Please try again or check file size.';
        echo json_encode($response);
        exit;
    }
    
    // 3. ย้ายไฟล์ที่ Upload มาไว้ในโฟลเดอร์ 'uploads'
    $file = $_FILES['file'];
    $original_file_name = basename($file['name']);
    $original_path = $uploads_dir . '/' . $original_file_name;

    if (move_uploaded_file($file['tmp_name'], $original_path)) {
        
        // --- REAL CONVERSION USING ILOVEPDF API ---
        
        // ตรวจสอบว่ามีไฟล์ autoload.php ของ Composer หรือไม่
        $autoload_path = 'vendor/autoload.php';
        if (!file_exists($autoload_path)) {
             $response['message'] = 'Server configuration error: Composer autoload file not found. Please run "composer install".';
             echo json_encode($response);
             exit;
        }
        require_once($autoload_path);

        // !!! **សំខាន់៖** ដាក់ Key របស់អ្នកនៅទីនេះ !!!
        $publicKey = 'YOUR_PUBLIC_KEY_HERE';
        $secretKey = 'YOUR_SECRET_KEY_HERE';

        if ($publicKey === 'project_public_f6aba979e60497aacd5f550ae88a9185_uUZcl9d5db7f079cfd46d5f86d42631e28555' || $secretKey === 'secret_key_aaa4a478a9be0cc74847da8c921e991d_Qcb519f5b58c79f700d5db12c7eff2554841d') {
            $response['message'] = 'API keys are not configured in index.php. Please add your iLovePDF keys.';
            echo json_encode($response);
            exit;
        }

        try {
            $ilovepdf = new \Ilovepdf\Ilovepdf($publicKey, $secretKey);
            $conversion_type = isset($_POST['conversionType']) ? htmlspecialchars($_POST['conversionType']) : 'unknown';
            
            // កំណត់ Task និង Extension ថ្មីដោយផ្អែកលើប្រភេទការបំប្លែង
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
            
            // សម្រាប់ PDF to JPG, យើងអាចកំណត់ឲ្យវាបង្កើតជា ZIP
            if ($task_info['task'] === 'pdfjpg') {
                $task->setPackaged(false); // false = 1 JPG per page, true = all JPGs in a ZIP
            }
            
            $task->execute();

            $file_info = pathinfo($original_path);
            $file_name_without_ext = $file_info['filename'];
            $new_extension = $task_info['ext'];
            $converted_file_name = $file_name_without_ext . '_converted' . $new_extension;
            
            // ទាញយកឯកសារដែលបំប្លែងរួច
            $task->download($uploads_dir);
            
            // iLovePDF អាចនឹងរក្សាទុកឯកសារដែលមានឈ្មោះខុសពីការរំពឹងទុក (ឧ. បើវាជា ZIP)
            // កូដនេះនឹងព្យាយាមប្តូរឈ្មោះវាឲ្យត្រឹមត្រូវ
            $downloaded_api_filename = $task->getOutputFileName();
            $final_path = $uploads_dir . '/' . $converted_file_name;
            if ($downloaded_api_filename !== $converted_file_name) {
                 rename($uploads_dir . '/' . $downloaded_api_filename, $final_path);
            }

            // បញ្ជូន Response ជោគជ័យ
            $response = [
                'success' => true,
                'message' => 'File converted successfully with iLovePDF!',
                'downloadUrl' => $final_path,
                'downloadName' => $converted_file_name
            ];

            // លុបឯកសារដើមដែលបាន Upload ដើម្បីសន្សំសំចៃទំហំ
            unlink($original_path);

        } catch (\Exception $e) {
            // หากมี Error จาก API
            $response['message'] = 'API Error: ' . $e->getMessage();
            // លុបឯកសារដើមបើមាន Error
            if (file_exists($original_path)) unlink($original_path);
        }
    } else {
        $response['message'] = 'Failed to save the uploaded file on the server.';
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All-in-One File Converter</title>
    
    <style>
        :root {
            --color-word: #2b579a;
            --color-ppt: #d24726;
            --color-excel: #217346;
            --color-pdf: #ae0e0e;
            --color-jpg: #e8a200;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f7f8fc;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px;
            margin: 0;
            color: #333;
        }

        .main-container {
            width: 100%;
            max-width: 1200px;
        }

        h1 {
            text-align: center;
            margin-bottom: 40px;
            color: #2c3e50;
        }

        .converter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            font-size: 24px;
            font-weight: bold;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .icon-word { background-color: var(--color-word); }
        .icon-ppt { background-color: var(--color-ppt); }
        .icon-excel { background-color: var(--color-excel); }
        .icon-pdf { background-color: var(--color-pdf); }
        .icon-jpg { background-color: var(--color-jpg); }

        .card-title {
            font-size: 1.15rem;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #343a40;
        }

        .card-description {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.5;
            margin: 0;
            flex-grow: 1;
        }
        
        #file-input { display: none; }

        #status-area {
            margin-top: 30px;
            padding: 15px 20px;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            display: none;
            transition: background-color 0.3s;
        }
        #status-area.loading { background-color: #e9ecef; color: #495057; }
        #status-area.success { background-color: #d4edda; color: #155724; }
        #status-area.error { background-color: #f8d7da; color: #721c24; }
        #status-area a {
            font-weight: bold;
            color: #0056b3;
            text-decoration: none;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 2px;
        }
    </style>
</head>
<body>

    <div class="main-container">
        <h1>File Conversion Tools</h1>
        
        <div id="status-area"></div>
        <input type="file" id="file-input">

        <div class="converter-grid">
            <div class="card" data-type="pdf-to-word" data-accept=".pdf">
                <div class="card-icon icon-word">W</div>
                <h2 class="card-title">PDF to Word</h2>
                <p class="card-description">Convert your PDFs to editable DOCX documents.</p>
            </div>
            <div class="card" data-type="pdf-to-powerpoint" data-accept=".pdf">
                <div class="card-icon icon-ppt">P</div>
                <h2 class="card-title">PDF to PowerPoint</h2>
                <p class="card-description">Convert your PDFs to editable PPTX slideshows.</p>
            </div>
            <div class="card" data-type="pdf-to-excel" data-accept=".pdf">
                <div class="card-icon icon-excel">X</div>
                <h2 class="card-title">PDF to Excel</h2>
                <p class="card-description">Extract data from PDFs into XLSX spreadsheets.</p>
            </div>
            <div class="card" data-type="word-to-pdf" data-accept=".doc,.docx">
                <div class="card-icon icon-pdf">W > PDF</div>
                <h2 class="card-title">Word to PDF</h2>
                <p class="card-description">Convert DOC and DOCX files to PDF.</p>
            </div>
            <div class="card" data-type="powerpoint-to-pdf" data-accept=".ppt,.pptx">
                <div class="card-icon icon-pdf">P > PDF</div>
                <h2 class="card-title">PowerPoint to PDF</h2>
                <p class="card-description">Convert PPT and PPTX files to PDF.</p>
            </div>
            <div class="card" data-type="excel-to-pdf" data-accept=".xls,.xlsx">
                <div class="card-icon icon-pdf">X > PDF</div>
                <h2 class="card-title">Excel to PDF</h2>
                <p class="card-description">Convert XLS and XLSX files to PDF.</p>
            </div>
            <div class="card" data-type="jpg-to-pdf" data-accept="image/jpeg,image/png,image/gif">
                <div class="card-icon icon-pdf">JPG > PDF</div>
                <h2 class="card-title">JPG to PDF</h2>
                <p class="card-description">Combine JPG, PNG, or GIF images into one PDF.</p>
            </div>
            <div class="card" data-type="pdf-to-jpg" data-accept=".pdf">
                <div class="card-icon icon-jpg">JPG</div>
                <h2 class="card-title">PDF to JPG</h2>
                <p class="card-description">Convert each page of a PDF into a JPG image.</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fileInput = document.getElementById('file-input');
            const statusArea = document.getElementById('status-area');
            const cards = document.querySelectorAll('.card');

            cards.forEach(card => {
                card.addEventListener('click', () => {
                    const conversionType = card.dataset.type;
                    const acceptedFiles = card.dataset.accept;
                    
                    fileInput.dataset.conversionType = conversionType;
                    fileInput.setAttribute('accept', acceptedFiles);
                    fileInput.click();
                });
            });

            fileInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (!file) return;

                const conversionType = fileInput.dataset.conversionType;
                const formData = new FormData();
                formData.append('file', file);
                formData.append('conversionType', conversionType);

                statusArea.style.display = 'block';
                statusArea.className = 'loading';
                statusArea.innerHTML = `Uploading <strong>${file.name}</strong>... This may take a moment.`;
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`Server responded with status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        statusArea.className = 'success';
                        statusArea.innerHTML = `${data.message} <a href="${data.downloadUrl}" download="${data.downloadName}">Download Now</a>`;
                    } else {
                        statusArea.className = 'error';
                        statusArea.innerHTML = `<strong>Error:</strong> ${data.message}`;
                    }
                })
                .catch(error => {
                    statusArea.className = 'error';
                    statusArea.innerHTML = '<strong>An unexpected error occurred.</strong> Please check the browser console (F12).';
                    console.error('Fetch Error:', error);
                });

                event.target.value = '';
            });
        });
    </script>

</body>
</html>