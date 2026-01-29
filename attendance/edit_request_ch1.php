<?php
session_start();
require_once 'db_connect.php'; // Assumes you have a db_connect.php file

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

// Check if request_id and date are provided
if (!isset($_GET['id']) || !isset($_GET['date'])) {
    die("កំហុស: មិនមាន request_id ឬ date ត្រូវបានផ្តល់ឲ្យ!");
}

$request_id = $_GET['id'];
$selected_date = $_GET['date'];

// Fetch the existing request data
try {
    $stmt = $pdo->prepare("SELECT * FROM staff_requests_ch1 WHERE request_id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        die("កំហុស: មិនអាចរកឃើញទិន្នន័យសម្រាប់ request_id = $request_id");
    }
} catch (PDOException $e) {
    die("កំហុសក្នុងការតភ្ជាប់ទិន្នន័យ: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $comment = $_POST['comment'];
    $tricycle_dept = isset($_POST['tricycle_dept']) ? 1 : 0;
    $loading_dept = isset($_POST['loading_dept']) ? 1 : 0;
    $truck_dept = isset($_POST['truck_dept']) ? 1 : 0;

    try {
        $stmt = $pdo->prepare("UPDATE staff_requests_ch1 SET name = ?, role = ?, comment = ?, tricycle_dept = ?, loading_dept = ?, truck_dept = ? WHERE request_id = ?");
        $stmt->execute([$name, $role, $comment, $tricycle_dept, $loading_dept, $truck_dept, $request_id]);
        
        header("Location: attendance_CH1.php?date=$selected_date"); // Redirect back to main page
        exit();
    } catch (PDOException $e) {
        die("កំហុសក្នុងការកែទិន្នន័យ: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>កែសំណើបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        label {
            display: block;
            margin: 10px 0 5px;
        }
        input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background-color: #45a049;
        }
        a {
            display: inline-block;
            margin-top: 10px;
            color: #2196F3;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>កែសំណើបុគ្គលិក</h1>
        <form method="POST">
            <label>ឈ្មោះ: 
                <input type="text" name="name" value="<?php echo htmlspecialchars($request['name']); ?>" required>
            </label>
            <label>តួនាទី: 
                <input type="text" name="role" value="<?php echo htmlspecialchars($request['role']); ?>" required>
            </label>
            <label>អធិប្បាយ: 
                <input type="text" name="comment" value="<?php echo htmlspecialchars($request['comment']); ?>">
            </label>
            <label>
                <input type="checkbox" name="tricycle_dept" <?php echo $request['tricycle_dept'] ? 'checked' : ''; ?>>
                ផ្នែកបើកកង់បី
            </label>
            <label>
                <input type="checkbox" name="loading_dept" <?php echo $request['loading_dept'] ? 'checked' : ''; ?>>
                ផ្នែកលើកទំនិញ
            </label>
            <label>
                <input type="checkbox" name="truck_dept" <?php echo $request['truck_dept'] ? 'checked' : ''; ?>>
                រថយន្ត
            </label>
            <button type="submit">រក្សាទុក</button>
            <a href="attendance_CH1.php?date=<?php echo $selected_date; ?>">បោះបង់</a>
        </form>
    </div>
</body>
</html>