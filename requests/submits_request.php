<?php
// DB connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Validate required fields
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Insert into item_requests
        $stmt = $conn->prepare("INSERT INTO item_requests 
            (number, request_date, pr_no, request_person, position, department, project, none_date, advance_type, deadline, in_words) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $_POST['number'],
            $_POST['request_date'],
            $_POST['pr_no'],
            $_POST['request_person'],
            $_POST['position'],
            $_POST['department'],
            $_POST['project'],
            $_POST['none_date'],
            $_POST['advance_type'],
            $_POST['deadline'],
            $_POST['in_words']
        ]);

        $request_id = $conn->lastInsertId();

        // Insert request items
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            $stmtItem = $conn->prepare("INSERT INTO request_items (request_id, item_name, quantity, price) VALUES (?, ?, ?, ?)");

            foreach ($_POST['items'] as $item) {
                if (!empty($item['item_name']) && is_numeric($item['quantity']) && is_numeric($item['price'])) {
                    $stmtItem->execute([
                        $request_id,
                        $item['item_name'],
                        $item['quantity'],
                        $item['price']
                    ]);
                }
            }
        }

        echo "<h3 style='color: green; text-align: center;'>âœ… Request submitted successfully!</h3>";
        echo "<div style='text-align: center;'><a href='../requests/request_form.php'>ðŸ”™ Back to form</a></div>";
    } catch (PDOException $e) {
        echo "<h3 style='color: red;'>âŒ Error: " . $e->getMessage() . "</h3>";
    }
} else {
    echo "<h3 style='color: red;'>Invalid request method.</h3>";
}
?>

