<?php
session_start();

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set timeout for large uploads (300s = 5 minutes)
set_time_limit(300);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database configuration
$servername = "localhost";
$username = "samann1_file_manager_db";
$password = "file_manager_db";
$dbname = "samann1_file_manager_db";

// Connect to database with retry
for ($attempt = 1; $attempt <= 3; $attempt++) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        if ($attempt == 3) {
            die("Connection failed after 3 attempts: " . $conn->connect_error);
        }
        sleep(2 ** ($attempt - 1)); // Exponential backoff
        continue;
    }
    break;
}

// Create table if not exists
$sql = "CREATE TABLE IF NOT EXISTS recordings (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if (!$conn->query($sql)) {
    die("Error creating table: " . $conn->error);
}

// Handle file upload (audio and image)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['audioFile'])) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $targetDir = "Uploads/";
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0755, true)) {
            $_SESSION['error'] = "Failed to create uploads directory.";
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    }

    $fileName = null;
    $imageName = null;

    // Handle audio file
    if (isset($_FILES['audioFile']) && $_FILES['audioFile']['error'] == UPLOAD_ERR_OK) {
        $allowedMimes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/x-m4a', 'audio/webm', 'video/mp4', 'audio/aac'];
        if (!handleFileUpload($_FILES['audioFile'], $targetDir, $fileName, 1073741824, $allowedMimes)) {
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    } else {
        $_SESSION['error'] = "No audio file uploaded or an error occurred during upload.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    // Handle image file (optional)
    if (isset($_FILES['imageFile']) && $_FILES['imageFile']['error'] === UPLOAD_ERR_OK) {
        if (!handleFileUpload($_FILES['imageFile'], $targetDir, $imageName, 5000000, ['image/jpeg', 'image/png', 'image/gif'])) {
            if ($fileName && file_exists($targetDir . $fileName)) {
                unlink($targetDir . $fileName); // Cleanup audio file if image fails
            }
            header("Location: " . $_SERVER['PHP_SELF']); exit;
        }
    }

    // Save to database
    if ($fileName) {
        $title = htmlspecialchars(pathinfo($_FILES["audioFile"]["name"], PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
        $sql = "INSERT INTO recordings (title, filename, image) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $title, $fileName, $imageName);
            if ($stmt->execute()) {
                $_SESSION['message'] = "File uploaded successfully!";
            } else {
                $_SESSION['error'] = "Failed to save recording to database: " . $stmt->error;
                cleanupFiles($targetDir, $fileName, $imageName);
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = "Failed to prepare database statement: " . $conn->error;
            cleanupFiles($targetDir, $fileName, $imageName);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// Handle recorded audio save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['audioData']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if (empty($_POST['audioData']) || !preg_match('/^data:audio\/(mp4|webm|wav|mpeg|ogg);base64,/', $_POST['audioData'])) {
        $_SESSION['error'] = "Invalid audio data.";
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }

    $audioData = $_POST['audioData'];
    $title = htmlspecialchars($_POST['title'] ?? 'សំឡេងដែលគ្មានឈ្មោះ', ENT_QUOTES, 'UTF-8');
    $data = explode(',', $audioData);
    $mime = explode(';', explode(':', $data[0])[1])[0];
    
    $mime_map = ['audio/mp4' => 'm4a', 'audio/webm' => 'webm', 'audio/wav' => 'wav', 'audio/mpeg' => 'mp3', 'audio/ogg' => 'ogg'];
    $extension = $mime_map[$mime] ?? 'dat';

    $fileName = uniqid() . '.' . $extension;
    $filePath = 'Uploads/' . $fileName;

    if (file_put_contents($filePath, base64_decode($data[1]))) {
        $sql = "INSERT INTO recordings (title, filename) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $title, $fileName);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Recording saved successfully!";
        } else {
            $_SESSION['error'] = "Failed to save recording to database: " . $stmt->error;
            unlink($filePath);
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Failed to save recording file.";
    }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// Handle delete
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT filename, image FROM recordings WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        cleanupFiles('Uploads/', $row['filename'], $row['image']);
        $delete_stmt = $conn->prepare("DELETE FROM recordings WHERE id=?");
        $delete_stmt->bind_param("i", $id);
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "Recording deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete recording: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    }
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

// Fetch all recordings
$recordings = [];
$result = $conn->query("SELECT * FROM recordings ORDER BY created_at DESC");
if ($result) {
    $recordings = $result->fetch_all(MYSQLI_ASSOC);
}

$imageRecordings = array_filter($recordings, fn($r) => !empty($r['image']));
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';

function handleFileUpload($file, $targetDir, &$fileName, $maxSize, $allowedMimes) {
    if ($file["size"] > $maxSize) {
        $_SESSION['error'] = "File is too large."; return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);
    if (!in_array($mime, $allowedMimes)) {
        $_SESSION['error'] = "Invalid file type. Detected: ($mime)."; return false;
    }
    $fileName = uniqid() . '_' . basename($file["name"]);
    if (!move_uploaded_file($file["tmp_name"], $targetDir . $fileName)) {
        $_SESSION['error'] = "Failed to move uploaded file."; return false;
    }
    return true;
}

function cleanupFiles($targetDir, $fileName, $imageName) {
    if ($fileName && file_exists($targetDir . $fileName)) unlink($targetDir . $fileName);
    if ($imageName && file_exists($targetDir . $imageName)) unlink($targetDir . $imageName);
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>វេបសាយថតសំឡេង All-in-One</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* យក CSS របស់អ្នកមកដាក់នៅទីនេះ */
        body { font-family: 'Noto Sans Khmer', sans-serif; background-color: #f4f7fa; padding: 20px; }
        .container { max-width: 1200px; margin: auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); padding: 30px; }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        .tabs { display: flex; justify-content: center; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .tab-btn { padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 8px 8px 0 0; font-size: 1rem; cursor: pointer; transition: all 0.3s ease; margin: 0 5px; }
        .tab-btn.active { background: #3498db; color: #fff; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .message { padding: 12px; margin-bottom: 20px; border-radius: 8px; text-align: center; }
        .message.success { background: #dff0d8; color: #3c763d; }
        .message.error { background: #f2dede; color: #a94442; }
        form { display: flex; flex-direction: column; gap: 15px; max-width: 500px; margin: auto; }
        input[type="file"], input[type="text"] { padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 12px 24px; background: #3498db; color: #fff; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; text-align: center; }
        .delete-btn { background: #e74c3c; }
        .share-btn { background: #2ecc71; }
        .recordings-list { display: grid; gap: 20px; }
        .recording-item { display: flex; flex-wrap: wrap; align-items: center; background: #f9f9f9; border-radius: 12px; padding: 15px; gap: 15px; }
        .recording-item audio { flex-grow: 1; }
        .recording-details { flex-basis: 100%; md:flex-basis: auto; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; }
        .modal-close { float: right; font-size: 1.5rem; cursor: pointer; }
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .image-item img { width: 100%; height: 150px; object-fit: cover; border-radius: 8px; }
    </style>
</head>
<body>
<div class="container">
    <h1>វេបសាយថតសំឡេង</h1>
    <?php if (isset($_SESSION['message']) || isset($_SESSION['error'])): ?>
        <div class="message <?= isset($_SESSION['error']) ? 'error' : 'success' ?>">
            <?= htmlspecialchars($_SESSION['message'] ?? $_SESSION['error']) ?>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['error']); ?>
    <?php endif; ?>

    <div class="tabs">
        <button class="tab-btn active" onclick="openTab('recordTab')">ថតសំឡេង</button>
        <button class="tab-btn" onclick="openTab('uploadTab')">ផ្ទុកឡើង</button>
        <button class="tab-btn" onclick="openTab('audioLibraryTab')">បណ្ណាល័យសំឡេង</button>
        <button class="tab-btn" onclick="openTab('imageLibraryTab')">បណ្ណាល័យរូបភាព</button>
    </div>

    <!-- Record Tab -->
    <div id="recordTab" class="tab-content active">
        <div class="recorder" style="text-align:center; margin-bottom: 20px;">
            <button id="recordBtn" class="btn">ចាប់ផ្តើមថត</button>
            <button id="stopBtn" class="btn" disabled>ឈប់ថត</button>
        </div>
        <div id="audioPreview" style="text-align:center;"></div>
        <form id="saveForm" method="post" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="audioData" id="audioData">
            <label for="title">ឈ្មោះសំឡេង:</label>
            <input type="text" name="title" id="title" required>
            <button type="submit" class="btn">រក្សាទុក</button>
        </form>
    </div>

    <!-- Upload Tab -->
    <div id="uploadTab" class="tab-content">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>ជ្រើសរើសឯកសារសំឡេង:</label>
            <input type="file" name="audioFile" accept="audio/*" required>
            <label>ជ្រើសរើសរូបភាព (ជាជម្រើស):</label>
            <input type="file" name="imageFile" accept="image/*">
            <button type="submit" class="btn">ផ្ទុកឡើង</button>
        </form>
    </div>

    <!-- Audio Library Tab -->
    <div id="audioLibraryTab" class="tab-content">
        <h2>សំឡេងដែលបានរក្សាទុក</h2>
        <?php if (empty($recordings)): ?>
            <p>មិនមានសំឡេងដែលបានរក្សាទុកទេ។</p>
        <?php else: ?>
            <div class="recordings-list">
                <?php foreach ($recordings as $recording): ?>
                    <div class="recording-item">
                        <?php if (!empty($recording['image'])): ?>
                            <img src="download.php?file=<?= urlencode($recording['image']) ?>" alt="Thumbnail" style="width:60px; height:60px; border-radius:8px;">
                        <?php endif; ?>
                        <audio controls src="download.php?file=<?= urlencode($recording['filename']) ?>"></audio>
                        <div class="recording-details">
                            <h3><?= htmlspecialchars($recording['title']) ?></h3>
                            <p><?= date('d/m/Y H:i', strtotime($recording['created_at'])) ?></p>
                            <div class="recording-controls" style="display:flex; gap:10px;">
                                <a href="download.php?file=<?= urlencode($recording['filename']) ?>" class="btn" download>ទាញយក</a>
                                <a href="?delete=<?= $recording['id'] ?>&csrf_token=<?= $_SESSION['csrf_token'] ?>" class="btn delete-btn">លុប</a>
                                <button class="btn share-btn" data-url="<?= htmlspecialchars($protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/download.php?file=' . urlencode($recording['filename'])) ?>">ចែករំលែក</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Image Library Tab -->
    <div id="imageLibraryTab" class="tab-content">
        <h2>រូបភាពដែលបានរក្សាទុក</h2>
        <?php if (empty($imageRecordings)): ?>
            <p>មិនមានរូបភាពដែលបានរក្សាទុកទេ។</p>
        <?php else: ?>
            <div class="image-gallery">
                <?php foreach ($imageRecordings as $recording): ?>
                    <div class="image-item">
                        <img src="download.php?file=<?= urlencode($recording['image']) ?>" alt="Image">
                         <h3><?= htmlspecialchars($recording['title']) ?></h3>
                         <p><?= date('d/m/Y H:i', strtotime($recording['created_at'])) ?></p>
                         <a href="download.php?file=<?= urlencode($recording['image']) ?>" class="btn" download>ទាញយក</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content">
        <span class="modal-close" onclick="document.getElementById('shareModal').style.display='none'">&times;</span>
        <h3>ចែករំលែក</h3>
        <input type="text" id="shareLink" style="width:100%; padding:8px;" readonly>
        <button id="copyBtn" class="btn">ចម្លង</button>
    </div>
</div>

<script>
    function openTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    document.addEventListener('DOMContentLoaded', () => {
        let mediaRecorder, audioChunks = [];
        const recordBtn = document.getElementById('recordBtn');
        const stopBtn = document.getElementById('stopBtn');
        const audioPreview = document.getElementById('audioPreview');
        const saveForm = document.getElementById('saveForm');

        recordBtn.onclick = async () => {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                alert('Your browser does not support audio recording.'); return;
            }
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                mediaRecorder.start();
                recordBtn.disabled = true;
                stopBtn.disabled = false;
                audioChunks = [];
                mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                mediaRecorder.onstop = () => {
                    const blob = new Blob(audioChunks, { type: mediaRecorder.mimeType });
                    audioPreview.innerHTML = `<audio controls src="${URL.createObjectURL(blob)}"></audio>`;
                    const reader = new FileReader();
                    reader.readAsDataURL(blob);
                    reader.onloadend = () => document.getElementById('audioData').value = reader.result;
                    let ext = mediaRecorder.mimeType.split('/')[1].split(';')[0];
                    document.getElementById('title').value = `Recording ${new Date().toISOString()}.${ext}`;
                    saveForm.style.display = 'flex';
                    recordBtn.disabled = false;
                    stopBtn.disabled = true;
                    stream.getTracks().forEach(track => track.stop());
                };
            } catch(e) { alert('You must allow microphone access to record.'); }
        };
        stopBtn.onclick = () => mediaRecorder.stop();

        document.querySelectorAll('.delete-btn').forEach(b => b.onclick = (e) => !confirm('Are you sure you want to delete this?') && e.preventDefault());
        
        const shareModal = document.getElementById('shareModal');
        document.querySelectorAll('.share-btn').forEach(b => {
            b.onclick = () => {
                document.getElementById('shareLink').value = b.dataset.url;
                shareModal.style.display = 'flex';
            };
        });
        document.getElementById('copyBtn').onclick = () => {
            const linkInput = document.getElementById('shareLink');
            linkInput.select();
            navigator.clipboard.writeText(linkInput.value);
            alert('Copied to clipboard!');
        };
    });
</script>

</body>
</html>
<?php $conn->close(); ?>