<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'admin/includes/db.php';
$conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check for mission ID
$mission_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
if (!$mission_id) {
    header("Location: view_all_mission_letters.php");
    exit;
}

// Fetch mission data
$stmt = $conn->prepare("SELECT * FROM mission_letters WHERE id = :id");
$stmt->bindParam(':id', $mission_id);
$stmt->execute();
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: view_all_mission_letters.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location']);
    $purpose = trim($_POST['purpose']);
    $person1 = trim($_POST['person1']);
    $role1 = trim($_POST['role1']);
    $person2 = trim($_POST['person2']);
    $role2 = trim($_POST['role2']);
    $start_date = trim($_POST['start_date']);
    $start_time = trim($_POST['start_time']);
    $end_date = trim($_POST['end_date']);
    $end_time = trim($_POST['end_time']);
    $transport = trim($_POST['transport']);
    $materials = trim($_POST['materials']);
    $date_khmer = trim($_POST['date_khmer']);

    $stmt = $conn->prepare("UPDATE mission_letters SET 
        location = :location, 
        purpose = :purpose, 
        person1 = :person1, 
        role1 = :role1, 
        person2 = :person2, 
        role2 = :role2, 
        start_date = :start_date, 
        start_time = :start_time, 
        end_date = :end_date, 
        end_time = :end_time, 
        transport = :transport, 
        materials = :materials, 
        date_khmer = :date_khmer 
        WHERE id = :id");

    $stmt->execute([
        ':location' => $location,
        ':purpose' => $purpose,
        ':person1' => $person1,
        ':role1' => $role1,
        ':person2' => $person2,
        ':role2' => $role2,
        ':start_date' => $start_date,
        ':start_time' => $start_time,
        ':end_date' => $end_date,
        ':end_time' => $end_time,
        ':transport' => $transport,
        ':materials' => $materials,
        ':date_khmer' => $date_khmer,
        ':id' => $mission_id
    ]);

    header("Location: view_all_mission_letters.php?success=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>កែសម្រួលលិខិតបញ្ជាបេសកម្ម</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .form-container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-label {
            font-family: 'Koulen';
        }
        .btn-sm {
            font-size: 14px;
        }
        @font-face { font-family: 'Koulen'; src: url('/font/Koulen.ttf'); }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('/font/KhmerOSBattambang.ttf'); }
    </style>
</head>
<body>
    <div class="form-container">
        <h3 style="text-align: center; font-family: 'Koulen';">កែសម្រួលលិខិតបញ្ជាបេសកម្ម</h3>
        <form method="POST">
            <div class="mb-3">
                <label for="location" class="form-label">ទីតាំង</label>
                <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($data['location'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="purpose" class="form-label">គោលបំណង</label>
                <input type="text" class="form-control" id="purpose" name="purpose" value="<?= htmlspecialchars($data['purpose'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="person1" class="form-label">លោក/លោកស្រី ១</label>
                <input type="text" class="form-control" id="person1" name="person1" value="<?= htmlspecialchars($data['person1'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="role1" class="form-label">តួនាទី ១</label>
                <input type="text" class="form-control" id="role1" name="role1" value="<?= htmlspecialchars($data['role1'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="person2" class="form-label">លោក/លោកស្រី ២</label>
                <input type="text" class="form-control" id="person2" name="person2" value="<?= htmlspecialchars($data['person2'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="role2" class="form-label">តួនាទី ២</label>
                <input type="text" class="form-control" id="role2" name="role2" value="<?= htmlspecialchars($data['role2'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="start_date" class="form-label">ថ្ងៃចាប់ផ្តើម</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($data['start_date'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="start_time" class="form-label">ម៉ោងចាប់ផ្តើម</label>
                <input type="time" class="form-control" id="start_time" name="start_time" value="<?= htmlspecialchars($data['start_time'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">ថ្ងៃបញ្ចប់</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($data['end_date'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="end_time" class="form-label">ម៉ោងបញ្ចប់</label>
                <input type="time" class="form-control" id="end_time" name="end_time" value="<?= htmlspecialchars($data['end_time'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="transport" class="form-label">មធ្យោបាយធ្វើដំណើរ</label>
                <input type="text" class="form-control" id="transport" name="transport" value="<?= htmlspecialchars($data['transport'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="materials" class="form-label">សម្ភារៈភ្ជាប់ជាមួយ</label>
                <input type="text" class="form-control" id="materials" name="materials" value="<?= htmlspecialchars($data['materials'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="date_khmer" class="form-label">កាលបរិច្ឆេទខ្មែរ</label>
                <input type="text" class="form-control" id="date_khmer" name="date_khmer" value="<?= htmlspecialchars($data['date_khmer'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">រក្សាទុក</button>
            <a href="view_all_mission_letters.php" class="btn btn-secondary btn-sm">ត្រឡប់ក្រោយ</a>
        </form>
    </div>
</body>
</html>