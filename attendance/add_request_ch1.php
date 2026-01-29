<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['username'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

$selected_date = $_GET['date'];

// Get report ID
$stmt = $pdo->prepare("SELECT id FROM reports_date_ch1 WHERE report_date = ?");
$stmt->execute([$selected_date]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
$report_id = $report['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $comment = $_POST['comment'];
    $tricycle_dept = isset($_POST['tricycle_dept']) ? 1 : 0;
    $loading_dept = isset($_POST['loading_dept']) ? 1 : 0;
    $truck_dept = isset($_POST['truck_dept']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO staff_requests_ch1 (report_id, name, role, comment, tricycle_dept, loading_dept, truck_dept) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$report_id, $name, $role, $comment, $tricycle_dept, $loading_dept, $truck_dept]);
    
    header("Location: attendance_CH1.php?date=$selected_date");
    exit();
}
?>

<!-- Add HTML form for adding new request -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>បន្ថែមសំណើថ្មី</title>
    <!-- Add your existing CSS -->
</head>
<body>
    <div class="container">
        <h1>បន្ថែមសំណើថ្មី</h1>
        <form method="POST">
            <label>ឈ្មោះ: <input type="text" name="name" required></label><br>
            <label>តួនាទី: <input type="text" name="role" required></label><br>
            <label>អធិប្បាយ: <input type="text" name="comment"></label><br>
            <label><input type="checkbox" name="tricycle_dept"> ផ្នែកបើកកង់បី</label><br>
            <label><input type="checkbox" name="loading_dept"> ផ្នែកលើកទំនិញ</label><br>
            <label><input type="checkbox" name="truck_dept"> រថយន្ត</label><br>
            <button type="submit">រក្សាទុក</button>
            <a href="attendance_CH1.php?date=<?php echo $selected_date; ?>">បោះបង់</a>
        </form>
    </div>
</body>
</html>