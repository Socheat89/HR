<?php
// Database connection
$servername = "localhost";
$username = "samann1_location_db";
$password = "location_db@2025";
$dbname = "samann1_location_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit;
}

// Function to generate unique tracking code
function generateTrackingCode($length = 8) {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Handle tracking code generation
if (isset($_GET['generate'])) {
    $employee_id = $_POST['employee_id'] ?? 'EMP_DEFAULT';
    $tracking_code = generateTrackingCode();

    $sql = "INSERT INTO employee_codes (employee_id, tracking_code) VALUES (:employee_id, :tracking_code)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':employee_id' => $employee_id, ':tracking_code' => $tracking_code]);

    $tracking_url = "http://yourdomain.com/tracker.php?code=$tracking_code";
    echo "Tracking URL: <a href='$tracking_url'>$tracking_url</a>";
    exit;
}

// Handle location data from tracker
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['tracking_code'])) {
    $tracking_code = $_POST['tracking_code'];
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    $timestamp = date('Y-m-d H:i:s');

    // Verify tracking code
    $stmt = $conn->prepare("SELECT employee_id FROM employee_codes WHERE tracking_code = :tracking_code");
    $stmt->execute([':tracking_code' => $tracking_code]);
    $employee = $stmt->fetch();

    if ($employee) {
        $sql = "INSERT INTO locations (employee_id, latitude, longitude, timestamp) 
                VALUES (:employee_id, :latitude, :longitude, :timestamp)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':employee_id' => $employee['employee_id'],
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':timestamp' => $timestamp
        ]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid tracking code']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate Tracking Code</title>
</head>
<body>
    <h2>Generate Tracking Code for Employee</h2>
    <form method="POST" action="?generate=1">
        <label>Employee ID: </label>
        <input type="text" name="employee_id" value="EMP001" required>
        <button type="submit">Generate Code</button>
    </form>
</body>
</html>