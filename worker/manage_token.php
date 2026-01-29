<?php
header('Content-Type: application/json');
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
$servername = "localhost";
$username = "samann1_Fingerprint";
$password = "Fingerprint@2025";
$dbname = "samann1_fingerprint_db";

$conn = new mysqli($servername, $username, $password, $dbname);
date_default_timezone_set('Asia/Phnom_Penh');
$conn->query("SET time_zone = '+07:00'");

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Get request data
$request = json_decode(file_get_contents('php://input'), true);
if (!$request || !isset($request['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing action']);
    exit();
}

$action = $request['action'];

if ($action === 'toggle') {
    $id = $request['id'] ?? null;
    $newStatus = $request['status'] ?? null;

    if ($id === null || $newStatus === null) {
        echo json_encode(['success' => false, 'message' => 'Missing ID or status']);
        exit();
    }

    $timestamp = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE allowed_tokens SET is_active = ?, deactivated_at = ? WHERE id = ?");
    $deactivated_at = ($newStatus == 0) ? $timestamp : null;
    $stmt->bind_param("isi", $newStatus, $deactivated_at, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Token status updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $stmt->error]);
    }
    $stmt->close();
} elseif ($action === 'delete') {
    $id = $request['id'] ?? null;

    if ($id === null) {
        echo json_encode(['success' => false, 'message' => 'Missing ID']);
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM allowed_tokens WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Token deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting token: ' . $stmt->error]);
    }
    $stmt->close();
} elseif ($action === 'edit') {
    $id = $request['id'] ?? null;
    $username = $request['username'] ?? null;
    $token = $request['token'] ?? null;

    if ($id === null || !$username || !$token) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    if (strlen($username) > 50 || strlen($token) > 255) {
        echo json_encode(['success' => false, 'message' => 'Username or token too long']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE allowed_tokens SET username = ?, token = ? WHERE id = ?");
    $stmt->bind_param("ssi", $username, $token, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Token updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating token: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    // Token creation
    $username = $request['username'] ?? null;
    $token = $request['token'] ?? null;

    if (!$username || !$token) {
        echo json_encode(['success' => false, 'message' => 'Missing username or token']);
        exit();
    }

    if (strlen($username) > 50 || strlen($token) > 255) {
        echo json_encode(['success' => false, 'message' => 'Username or token too long']);
        exit();
    }

    $tokenLimit = 1;

    // Check active token count
    $stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM allowed_tokens WHERE username = ? AND is_active = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $activeCount = $row['active_count'];
    $stmt->close();

    // Check if token exists
    $stmt = $conn->prepare("SELECT is_active FROM allowed_tokens WHERE token = ? AND username = ?");
    $stmt->bind_param("ss", $token, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['is_active'] == 1) {
            echo json_encode(['success' => true, 'message' => 'Token already registered and active']);
        } else {
            // Optionally reactivate the token if admin creates it again
            $timestamp = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("UPDATE allowed_tokens SET is_active = 1, deactivated_at = NULL, created_at = ? WHERE token = ? AND username = ?");
            $stmt->bind_param("sss", $timestamp, $token, $username);
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Token reactivated successfully',
                    'registered_at' => $timestamp
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error reactivating token: ' . $stmt->error]);
            }
        }
    } else {
        if ($activeCount >= $tokenLimit) {
            echo json_encode(['success' => false, 'message' => 'Token limit reached for this username']);
        } else {
            $timestamp = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("INSERT INTO allowed_tokens (token, username, is_active, created_at) VALUES (?, ?, 1, ?)");
            $stmt->bind_param("sss", $token, $username, $timestamp);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Token created successfully',
                    'registered_at' => $timestamp
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error creating token: ' . $stmt->error]);
            }
        }
    }
    $stmt->close();
}

$conn->close();
?>