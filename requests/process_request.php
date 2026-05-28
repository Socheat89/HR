<?php
// Database connection
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$dbname = "item_requests"; // Database name unchanged

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate input data
    $number = filter_input(INPUT_POST, 'លេខប័ណ្ណ/Number', FILTER_SANITIZE_STRING);
    $request_date = filter_input(INPUT_POST, 'Request Date', FILTER_SANITIZE_STRING);
    $request_person = filter_input(INPUT_POST, 'ឈ្មោះអ្នកសើ្នសុំ/Request Person', FILTER_SANITIZE_STRING);
    $position = filter_input(INPUT_POST, 'មុខតំណែង/Position', FILTER_SANITIZE_STRING);
    $department = filter_input(INPUT_POST', 'ផែ្នក/Department', FILTER_SANITIZE_STRING);
    $project = filter_input(INPUT_POST, 'កូដគំរោង/Project', FILTER_SANITIZE_STRING);
    $none_date = filter_input(INPUT_POST, 'ថៃ្ងចង់បាន/none Date', FILTER_SANITIZE_STRING);
    $deadline = filter_input(INPUT_POST, 'ថៃ្ងផុតកំណត់ការទូទាត់បុរេប្រទាន/Advance Clearance Deadline', FILTER_SANITIZE_STRING);
    $in_words = filter_input(INPUT_POST, 'ជាអក្សរ/In Words', FILTER_SANITIZE_STRING);
    $image_url = filter_input(INPUT_POST, 'Upload Image', FILTER_VALIDATE_URL);

    // Items array
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $item_name = filter_input(INPUT_POST, "Item Name$i", FILTER_SANITIZE_STRING);
        $quantity = filter_input(INPUT_POST, "Quantity$i", FILTER_VALIDATE_INT);
        $price = filter_input(INPUT_POST, "Price$i", FILTER_VALIDATE_FLOAT);

        if ($item_name && $quantity && $price) {
            $items[] = [
                'name' => $item_name,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
    }

    // Basic validation
    if (empty($number) || empty($request_date) || empty($request_person) || empty($items)) {
        http_response_code(400);
        echo json_encode(['error' => 'Required fields are missing']);
        exit;
    }

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Insert request header
        $stmt = $conn->prepare("INSERT INTO item_requests (
            number, request_date, request_person, position, department, 
            project, none_date, deadline, in_words, image_url
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $number, $request_date, $request_person, $position, $department,
            $project, $none_date, $deadline, $in_words, $image_url
        ]);

        $request_id = $conn->lastInsertId();

        // Insert items
        $stmt = $conn->prepare("INSERT INTO request_items (
            request_id, item_name, quantity, price
        ) VALUES (?, ?, ?, ?)");

        foreach ($items as $item) {
            $stmt->execute([
                $request_id,
                $item['name'],
                $item['quantity'],
                $item['price']
            ]);
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => 'Request submitted successfully']);
    } catch(Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Error processing request: ' . $e->getMessage()]);
    }
}

$conn = null;
?>