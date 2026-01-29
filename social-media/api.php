<?php
// api.php
// ไฟล์นี้จะจัดการคำขอทั้งหมดที่เกี่ยวข้องกับฐานข้อมูล เช่น การดึงและบันทึกประวัติ

header('Content-Type: application/json');

// --- การเชื่อมต่อฐานข้อมูล ---
$servername = "localhost";
$username = "samann1_facebook-bot";
$password = "facebook-bot!@#";
$dbname = "samann1_facebook-bot";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}
$conn->set_charset("utf8mb4");


// --- API Endpoint: ดึงข้อมูล (GET request) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_history') {
    
    $response = [
        'captions' => [],
        'buttons' => []
    ];

    // ดึงประวัติคำบรรยาย
    $caption_sql = "SELECT caption_text FROM captions ORDER BY id DESC LIMIT 20";
    $caption_result = $conn->query($caption_sql);
    if ($caption_result->num_rows > 0) {
        while($row = $caption_result->fetch_assoc()) {
            $response['captions'][] = $row['caption_text'];
        }
    }

    // ดึงปุ่ม Telegram
    $button_sql = "SELECT button_text, button_url FROM telegram_buttons ORDER BY sort_order ASC";
    $button_result = $conn->query($button_sql);
    if ($button_result->num_rows > 0) {
        while($row = $button_result->fetch_assoc()) {
            $response['buttons'][] = ['text' => $row['button_text'], 'url' => $row['button_url']];
        }
    }
    
    echo json_encode($response);
}

// --- API Endpoint: บันทึกข้อมูล (POST request) ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_action'])) {
    
    $api_action = $_POST['api_action'];
    $response_data = ['success' => false, 'message' => 'Invalid action.'];

    // กรณีบันทึกคำบรรยาย
    if ($api_action === 'save_caption' && isset($_POST['caption'])) {
        $caption = $_POST['caption'];
        $stmt_check = $conn->prepare("SELECT id FROM captions WHERE caption_text = ?");
        $stmt_check->bind_param("s", $caption);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO captions (caption_text) VALUES (?)");
            $stmt_insert->bind_param("s", $caption);
            if ($stmt_insert->execute()) {
                $response_data = ['success' => true, 'message' => 'Caption saved.'];
            }
            $stmt_insert->close();
        } else {
             $response_data = ['success' => true, 'message' => 'Caption already exists.'];
        }
        $stmt_check->close();
    }
    
    // กรณีบันทึกปุ่ม
    elseif ($api_action === 'save_buttons' && isset($_POST['buttons'])) {
        $buttons = json_decode($_POST['buttons'], true);
        $conn->query("TRUNCATE TABLE telegram_buttons");
        $stmt = $conn->prepare("INSERT INTO telegram_buttons (button_text, button_url, sort_order) VALUES (?, ?, ?)");
        $success = true;
        foreach ($buttons as $index => $button) {
            if (!$stmt->bind_param("ssi", $button['text'], $button['url'], $index) || !$stmt->execute()) {
                $success = false;
            }
        }
        if ($success) {
            $response_data = ['success' => true, 'message' => 'Buttons saved.'];
        } else {
            $response_data['message'] = 'Error saving buttons.';
        }
        $stmt->close();
    }
    
    echo json_encode($response_data);
}

else {
    // กรณีไม่มี action ที่ถูกต้อง
    echo json_encode(['success' => false, 'message' => 'No valid action specified.']);
}

$conn->close();
?>