<?php
// Database connection
$servername = "localhost";
$username = "samann1_Fingerprint";
$password = "Fingerprint@2025";
$dbname = "samann1_fingerprint_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
date_default_timezone_set('Asia/Phnom_Penh');
$conn->query("SET time_zone = '+07:00'");

// Handle form submission (Delete only)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $token = $_POST['token'];

    $stmt = $conn->prepare("DELETE FROM allowed_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    if ($stmt->execute()) {
        $message = "Token deleted successfully!";
    } else {
        $message = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Fetch all tokens for display
$result = $conn->query("SELECT * FROM allowed_tokens ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Token Admin Panel</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            margin: 20px;
            background-color: #f5f6fa;
            color: #333;
        }
        h1, h2 {
            color: #2c3e50;
        }
        .section {
            margin-bottom: 30px;
        }
        .btn {
            padding: 8px 16px;
            margin: 5px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-danger {
            background-color: #e74c3c;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .message {
            color: #27ae60;
            margin: 10px 0;
        }
        .error {
            color: #c0392b;
        }

        /* Token List Style */
        .token-list {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .token-list table {
            width: 100%;
            border-collapse: collapse;
        }
        .token-list th {
            background-color: #34495e;
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
        }
        .token-list td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .token-list tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .token-list tr:hover {
            background-color: #f1f3f5;
            transition: background-color 0.2s;
        }
        .token-list .active-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
        }
        .token-list .active-yes {
            background-color: #2ecc71;
        }
        .token-list .active-no {
            background-color: #95a5a6;
        }
    </style>
</head>
<body>
    <h1>Token Management</h1>

    <!-- Message Display -->
    <?php if ($message): ?>
        <p class="<?php echo strpos($message, 'Error') === false ? 'message' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <!-- Token List -->
    <div class="section">
        <h2>Token List</h2>
        <div class="token-list">
            <table>
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Username</th>
                        <th>Active</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['token']); ?></td>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td>
                            <span class="active-status <?php echo $row['is_active'] ? 'active-yes' : 'active-no'; ?>">
                                <?php echo $row['is_active'] ? 'Yes' : 'No'; ?>
                            </span>
                        </td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this token?');">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($row['token']); ?>">
                                <input type="submit" name="delete" value="Delete" class="btn btn-danger">
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>