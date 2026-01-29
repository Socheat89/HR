<?php
// PHP Upload Handler with Image Compression & Large File Support

// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes <= 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Helper function to get readable upload error messages in Khmer
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE: return "ទំហំឯកសារលើសពីការកំណត់របស់ server (upload_max_filesize)។";
        case UPLOAD_ERR_FORM_SIZE: return "ទំហំឯកសារលើសពីការកំណត់របស់ Form។";
        case UPLOAD_ERR_PARTIAL: return "ឯកសារត្រូវបានបញ្ចូលតែផ្នែកខ្លះប៉ុណ្ណោះ។";
        case UPLOAD_ERR_NO_FILE: return "គ្មានឯកសារណាមួយត្រូវបានបញ្ចូលទេ។";
        case UPLOAD_ERR_NO_TMP_DIR: return "រកមិនឃើញថតបណ្ដោះអាសន្ន (Temporary folder)។";
        case UPLOAD_ERR_CANT_WRITE: return "មិនអាចសរសេរឯកសារទៅក្នុង Disk បានទេ។";
        case UPLOAD_ERR_EXTENSION: return "ការបញ្ចូលឯកសារត្រូវបានបញ្ឈប់ដោយ extension របស់ PHP។";
        default: return "មានកំហុសមួយដែលមិនស្គាល់។";
    }
}

/**
 * Scans the upload directory and returns an array of file information.
 * @param string $uploadDir The directory to scan.
 * @return array A sorted list of files with their details.
 */
function listUploadedFiles($uploadDir) {
    if (!is_dir($uploadDir)) {
        return [];
    }

    $files = [];
    $filePaths = glob($uploadDir . '*.*'); 

    foreach ($filePaths as $path) {
        if (is_file($path)) {
            $filename = basename($path);
            $parts = explode('_', $filename, 2);
            $originalName = (count($parts) > 1) ? $parts[1] : $filename;

            $files[] = [
                'original_name' => $originalName,
                'path' => $path,
                'size' => formatFileSize(filesize($path)),
                'type' => mime_content_type($path) ?: 'application/octet-stream',
                'ext'  => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                'timestamp' => filemtime($path)
            ];
        }
    }
    
    usort($files, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    return $files;
}

/**
 * Compresses an image file (JPEG, PNG, GIF).
 * @param string $sourcePath The path to the source image.
 * @param string $destinationPath The path to save the compressed image.
 * @param int $quality The quality of the compression (0-100 for JPEG, 0-9 for PNG).
 * @return bool True on success, false on failure.
 */
function compressImage($sourcePath, $destinationPath, $quality = 75) {
    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) return false;
    
    $mime = $imageInfo['mime'];
    $image = null;

    switch ($mime) {
        case 'image/jpeg':
        case 'image/pjpeg':
            $image = imagecreatefromjpeg($sourcePath);
            if ($image) imagejpeg($image, $destinationPath, $quality);
            break;
        case 'image/png':
            $image = imagecreatefrompng($sourcePath);
            if ($image) {
                $pngQuality = round(($quality / 100) * 9);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $destinationPath, $pngQuality);
            }
            break;
        case 'image/gif':
            $image = imagecreatefromgif($sourcePath);
            if ($image) imagegif($image, $destinationPath);
            break;
        default:
            return copy($sourcePath, $destinationPath);
    }

    if ($image) {
        imagedestroy($image);
        return file_exists($destinationPath);
    }
    return false;
}

$uploadDir = 'uploads/';
$initialFiles = listUploadedFiles($uploadDir);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['files'])) {
    header('Content-Type: application/json');
    
    $allowedImageTypes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedAudioTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/x-wav', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/flac', 'audio/x-flac', 'audio/3gpp', 'audio/3gpp2'];
    $allowedTypes = array_merge($allowedImageTypes, $allowedAudioTypes);
    
    $maxFileSize = 512 * 1024 * 1024; // 512MB (ត្រូវនឹង cPanel)
    $response = ['success' => [], 'errors' => []];

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    foreach ($_FILES['files']['name'] as $key => $name) {
        $fileTmpPath = $_FILES['files']['tmp_name'][$key];
        $fileSize = $_FILES['files']['size'][$key];
        $fileType = $_FILES['files']['type'][$key];
        $fileName = basename($name);
        $fileError = $_FILES['files']['error'][$key];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileError !== UPLOAD_ERR_OK) {
            $response['errors'][] = "មានបញ្ហាក្នុងការ Upload '$fileName': " . getUploadErrorMessage($fileError);
            continue;
        }
        
        if (!is_uploaded_file($fileTmpPath)) {
            $response['errors'][] = "ឯកសារ '$fileName' មិនត្រូវបានបញ្ចូលតាមរយៈ HTTP POST ទេ។";
            continue;
        }
        
        if ($fileSize > $maxFileSize) {
            $response['errors'][] = "ឯកសារ '$fileName' មានទំហំធំពេក (ទំហំអតិបរមាគឺ " . formatFileSize($maxFileSize) . ")។";
            continue;
        }

        if (!in_array($fileType, $allowedTypes)) {
            $response['errors'][] = "ប្រភេទឯកសារ '$fileName' មិនត្រឹមត្រូវទេ។";
            continue;
        }

        $newFileName = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\.\-]/', '', $fileName);
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $originalSize = $fileSize;
            $newPath = $destPath;

            // Check if it's a compressible image
            if (in_array($fileType, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'])) {
                if (compressImage($destPath, $destPath, 75)) { // Compress in-place
                    clearstatcache(); // Clear cache to get the new file size
                    $fileSize = filesize($destPath);
                }
            }
            
            $response['success'][] = [
                'original_name' => $fileName,
                'path' => $newPath,
                'size' => formatFileSize($fileSize),
                'original_size' => formatFileSize($originalSize),
                'type' => $fileType,
                'ext'  => $fileExt
            ];
        } else {
            $response['errors'][] = "មិនអាចផ្លាស់ទីឯកសារ '$fileName' បានទេ។";
        }
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
    <title>បណ្ណាល័យឯកសារ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Koulen&family=Siemreap:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4a69bd;
            --secondary-color: #6a89cc;
            --danger-color: #e53935;
            --success-color: #43a047;
            --light-bg: #f8f9fa;
            --dark-text: #343a40;
            --light-text: #6c757d;
            --border-color: #dee2e6;
            --font-heading: 'Koulen', sans-serif;
            --font-body: 'Siemreap', 'Khmer OS Siemreap', sans-serif;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body); background: linear-gradient(135deg, #f5f7fa, #c3cfe2); min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; padding: 2rem; color: var(--dark-text); font-size: 16px; }
        .container { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 16px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); max-width: 800px; width: 100%; border: 1px solid rgba(255, 255, 255, 0.2); }
        h1, h3 { font-family: var(--font-heading); letter-spacing: 1px; }
        h1 { text-align: center; color: var(--primary-color); margin-bottom: 2rem; font-size: 2.8rem; }
        .upload-area { border: 2px dashed var(--secondary-color); border-radius: 12px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s ease; background: var(--light-bg); }
        .upload-area.drag-over { border-color: var(--primary-color); background: #e9ecef; transform: scale(1.02); }
        .upload-icon { font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem; }
        .upload-label p { margin: 0.5rem 0; font-size: 1.1rem; }
        .browse-btn { color: var(--primary-color); font-weight: 700; text-decoration: underline; }
        .file-list { margin-top: 1.5rem; }
        .file-item { display: flex; align-items: center; padding: 0.8rem 1rem; background: white; border: 1px solid var(--border-color); border-radius: 8px; margin-bottom: 0.8rem; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); }
        .file-icon { font-size: 1.5rem; margin-right: 1rem; color: var(--secondary-color); }
        .file-info { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .file-name { font-weight: 700; color: var(--dark-text); overflow: hidden; text-overflow: ellipsis; }
        .file-size { color: var(--light-text); font-size: 0.9rem; }
        .remove-btn { background: none; border: none; color: var(--danger-color); cursor: pointer; font-size: 1.5rem; opacity: 0.7; transition: all 0.2s ease; }
        .remove-btn:hover { opacity: 1; transform: scale(1.1); }
        .progress-container { height: 10px; background: #e9ecef; border-radius: 5px; margin: 1.5rem 0; overflow: hidden; display: none; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, var(--secondary-color), var(--primary-color)); width: 0%; transition: width 0.4s ease; border-radius: 5px; }
        .upload-btn { width: 100%; padding: 1rem; background: linear-gradient(90deg, var(--secondary-color), var(--primary-color)); color: white; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700; font-family: var(--font-body); cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(74, 105, 189, 0.4); }
        .upload-btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(74, 105, 189, 0.5); }
        .upload-btn:disabled { background: #ced4da; cursor: not-allowed; box-shadow: none; }
        .notification { padding: 1rem; margin: 1.5rem 0; border-radius: 8px; text-align: left; display: none; line-height: 1.6; }
        .success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .uploaded-files { margin-top: 2.5rem; }
        .uploaded-files h3 { color: var(--primary-color); margin-bottom: 1.5rem; font-size: 1.8rem; }
        .uploaded-item { display: flex; align-items: center; padding: 1rem; background: white; border-radius: 12px; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); transition: all 0.3s ease; }
        .uploaded-item:hover { transform: translateY(-3px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12); }
        .uploaded-item-preview { width: 60px; height: 60px; margin-right: 1rem; border-radius: 8px; background: var(--light-bg); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--secondary-color); flex-shrink: 0; }
        .uploaded-item-preview img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
        .uploaded-item-preview audio { width: 200px; height: 40px; }
        .file-actions { display: flex; gap: 0.5rem; }
        .action-btn { background: var(--light-bg); color: var(--dark-text); border: 1px solid var(--border-color); border-radius: 6px; padding: 0.5rem; cursor: pointer; font-size: 1rem; transition: all 0.2s ease; }
        .action-btn:hover { background: #e9ecef; border-color: #adb5bd; }
        .action-btn.copied { background: var(--success-color); color: white; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--dark-text); color: white; padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3); opacity: 0; transform: translateY(20px); transition: opacity 0.3s, transform 0.3s; z-index: 1000; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .server-info { font-size: 0.9rem; color: var(--light-text); margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>បណ្ណាល័យឯកសារ</h1>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="upload-area" id="uploadArea">
                <div class="upload-label">
                    <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <p>អូស និងទម្លាក់ឯកសារនៅទីនេះ</p>
                    <p>ឬ <span class="browse-btn">ជ្រើសរើសឯកសារ</span></p>
                </div>
                <input type="file" id="fileInput" name="files[]" multiple accept="image/*,audio/*" hidden>
            </div>
            
            <div class="file-list" id="fileList"></div>
            <div class="progress-container" id="progressContainer"><div class="progress-bar" id="progressBar"></div></div>
            <div class="notification" id="notification"></div>
            <button type="submit" class="upload-btn" id="uploadBtn" disabled>បញ្ចូលឯកសារ</button>
        </form>

        <div class="uploaded-files" id="uploadedFiles">
            <h3><i class="fas fa-folder-open"></i> ឯកសារដែលបានបញ្ចូល</h3>
            <div id="uploadedList"></div>
        </div>
        
        <div class="server-info">
            ទំហំ Upload អតិបរមា: <?php echo ini_get('upload_max_filesize'); ?> | 
            ទំហំ Post អតិបរមា: <?php echo ini_get('post_max_size'); ?>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    const initialFiles = <?php echo json_encode($initialFiles, JSON_UNESCAPED_SLASHES); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const progressBar = document.getElementById('progressBar');
        const progressContainer = document.getElementById('progressContainer');
        const notification = document.getElementById('notification');
        const uploadedList = document.getElementById('uploadedList');
        const toast = document.getElementById('toast');
        let selectedFiles = [];

        function displayUploadedFiles(files) {
            files.forEach(file => {
                const item = document.createElement('div');
                item.className = 'uploaded-item';
                const fullUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'))}/${file.path}`;
                
                let previewHTML = '';
                if (file.type.startsWith('image/')) {
                    previewHTML = `<div class="uploaded-item-preview"><img src="${file.path}" alt="${file.original_name}"></div>`;
                } else if (file.type.startsWith('audio/')) {
                    previewHTML = `<div class="uploaded-item-preview"><audio controls><source src="${file.path}" type="${file.type}"></audio></div>`;
                } else {
                    previewHTML = `<div class="uploaded-item-preview"><i class="fas fa-file"></i></div>`;
                }
                
                let sizeHTML = `<div class="file-size">${file.size}</div>`;
                if (file.original_size && file.original_size !== file.size) {
                    sizeHTML = `<div class="file-size">${file.size} <span style="color: #28a745; font-size: 0.8em;">(បានបង្រួមពី ${file.original_size})</span></div>`;
                }

                item.innerHTML = `
                    ${previewHTML}
                    <div class="file-info">
                        <div class="file-name">${file.original_name}</div>
                        ${sizeHTML} 
                    </div>
                    <div class="file-actions">
                        <button class="action-btn copy-btn" data-url="${fullUrl}" title="Copy Link"><i class="fas fa-copy"></i></button>
                        <a href="${fullUrl}" download="${file.original_name}" class="action-btn" title="Download"><i class="fas fa-download"></i></a>
                    </div>
                `;
                uploadedList.prepend(item);
            });
        }
        
        function displayInitialFiles() {
            if (initialFiles && initialFiles.length > 0) {
                displayUploadedFiles(initialFiles);
            }
        }

        uploadArea.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => handleFiles(fileInput.files));
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.add('drag-over'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => uploadArea.classList.remove('drag-over'), false);
        });
        uploadArea.addEventListener('drop', handleDrop, false);

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function handleDrop(e) {
            handleFiles(e.dataTransfer.files);
        }

        function handleFiles(files) {
            for (const file of files) {
                if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            }
            renderFileList();
            updateUploadButton();
        }

        function renderFileList() {
            fileList.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                fileItem.innerHTML = `<div class="file-icon">${getFileIcon(file.type)}</div><div class="file-info"><div class="file-name">${file.name}</div><div class="file-size">${formatFileSize(file.size)}</div></div><button type="button" class="remove-btn" data-index="${index}">&times;</button>`;
                fileList.appendChild(fileItem);
            });
        }
        
        fileList.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-btn')) {
                const index = parseInt(e.target.dataset.index, 10);
                selectedFiles.splice(index, 1);
                renderFileList();
                updateUploadButton();
            }
        });

        function getFileIcon(fileType) {
            if (fileType.startsWith('image/')) return '<i class="fas fa-file-image"></i>';
            if (fileType.startsWith('audio/')) return '<i class="fas fa-file-audio"></i>';
            return '<i class="fas fa-file"></i>';
        }

        function formatFileSize(bytes) {
            if (bytes <= 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function updateUploadButton() {
            uploadBtn.disabled = selectedFiles.length === 0;
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (selectedFiles.length === 0) return;
            
            const formData = new FormData();
            selectedFiles.forEach(file => { formData.append('files[]', file); });
            
            progressContainer.style.display = 'block';
            progressBar.style.width = '0%';
            notification.style.display = 'none';
            uploadBtn.disabled = true;

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $_SERVER["PHP_SELF"]; ?>', true);
            
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                }
            };
            
            xhr.onload = function() {
                progressContainer.style.display = 'none';
                uploadBtn.disabled = false;
                
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        handleResponse(response);
                    } catch (err) {
                        showNotification('ការឆ្លើយតបពី Server មិនត្រឹមត្រូវ', 'error');
                    }
                } else {
                    showNotification(`មានកំហុស Server: ${xhr.status} ${xhr.statusText}`, 'error');
                }
            };
            
            xhr.onerror = function() {
                showNotification('មានបញ្ហាក្នុងការភ្ជាប់ទៅកាន់ Server។', 'error');
                progressContainer.style.display = 'none';
                uploadBtn.disabled = false;
            };
            
            xhr.send(formData);
        });

        function handleResponse(response) {
            if (response.errors && response.errors.length > 0) {
                showNotification(response.errors.join('<br>'), 'error');
            }
            if (response.success && response.success.length > 0) {
                showNotification(`បានបញ្ចូល ${response.success.length} ឯកសារដោយជោគជ័យ!`, 'success');
                displayUploadedFiles(response.success);
                resetForm();
            }
        }

        function showNotification(message, type) {
            notification.innerHTML = message;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
        }

        uploadedList.addEventListener('click', function(e) {
            const copyBtn = e.target.closest('.copy-btn');
            if (copyBtn) {
                copyToClipboard(copyBtn.dataset.url, copyBtn);
            }
        });

        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('បានចម្លង Link ហើយ!');
                const originalIcon = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i>';
                button.classList.add('copied');
                setTimeout(() => {
                    button.innerHTML = originalIcon;
                    button.classList.remove('copied');
                }, 2000);
            }).catch(() => {
                showToast('ការចម្លង Link បរាជ័យ');
            });
        }
        
        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }

        function resetForm() {
            selectedFiles = [];
            fileList.innerHTML = '';
            fileInput.value = '';
            updateUploadButton();
        }
        
        displayInitialFiles();
    });
    </script>
</body>
</html>