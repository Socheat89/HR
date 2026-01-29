<?php
// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_Fingerprint';
$dbPass = 'Fingerprint@2025';
$dbName = 'samann1_fingerprint_db';

// Get username from GET parameter or POST form submission
$username = isset($_GET['username']) ? $_GET['username'] : (isset($_POST['username']) ? $_POST['username'] : '');

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Fetch logs only for the provided username if one is given
    if (!empty($username)) {
        $stmt = $pdo->prepare("SELECT * FROM scan_logs WHERE username = :username ORDER BY timestamp DESC");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $logs = []; // No logs to show if no username is provided
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ");
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ប្រវត្តិស្កេន</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">

    <style>
    /* General Styles */
    body {
        font-family: 'Battambang', sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
    }

    h1, h3 {
        color: #343a40;
    }

    .container {
        max-width: 90%;
        margin: 20px auto;
    }

    .btn-back {
        margin-top: 20px;
    }

    table {
        width: 100%;
        margin-top: 20px;
    }

    .table th, .table td {
        padding: 12px;
        text-align: center;
    }

    .table th {
        background-color: #007bff;
        color: white;
    }

    /* Media Queries for Different Screen Sizes */

    /* For screens larger than 768px (tablets and desktops) */
    @media (min-width: 768px) {
        .container {
            max-width: 80%;
        }

        .table {
            font-size: 1rem;
        }

        .btn-back {
            font-size: 1.1rem;
        }

        h1 {
            font-size: 2rem;
        }

        h3 {
            font-size: 1.5rem;
        }
    }

    /* For screens 768px and smaller (tablets and mobile devices) */
    @media (max-width: 768px) {
        .container {
            max-width: 100%;
            padding: 10px;
        }

        .table {
            font-size: 10px;
        }

        .table th, .table td {
            padding: 8px;
        }

        .btn-back {
            font-size: 1rem;
            width: 100%;
            margin-top: 15px;
        }

        h1 {
            font-size: 1.5rem;
            text-align: center;
        }

        h3 {
            font-size: 1.2rem;
            text-align: center;
        }

        /* Ensure the input field in the form is responsive */
        .input-group input {
            font-size: 1rem;
            padding: 10px;
        }

        .input-group button {
            font-size: 1rem;
            padding: 10px;
        }
    }

    /* For smaller screens like mobile phones */
    @media (max-width: 480px) {
        .table th, .table td {
            padding: 6px;
        }

        .table {
            font-size: 8px;
        }

        .btn-back {
            font-size: 0.9rem;
        }

        h1 {
            font-size: 1.3rem;
        }

        h3 {
            font-size: 10px;
        }

        .input-group input,
        .input-group button {
            padding: 8px;
            font-size: 0.9rem;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #343a40;">ប្រវត្តិស្កេន</h1>

        <!-- Form to input username -->
        <form method="POST" class="mb-4 d-none">
            <div class="input-group">
                <input type="text" class="form-control" id="userNameInput" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើ" value="<?php echo htmlspecialchars($username); ?>" required>
                <button type="submit" class="btn btn-primary">ស្វែងរក</button>
            </div>
        </form>

        <a href="index.html" class="btn btn-primary btn-back">ត្រឡប់ទៅកាន់ទំព័រស្កេន</a>

        <?php if (!empty($username)): ?>
            <h3 class="mt-3 d-none">ប្រវត្តិស្កេនរបស់ <?php echo htmlspecialchars($username); ?></h3>
        <?php endif; ?>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ឈ្មោះ</th>
                    <th>ប្រភេទស្កេន</th>
                    <th>ថ្ងៃខែឆ្នាំ/ម៉ោង</th>
                    <th>ទីតាំង</th>
                    <th>ស្ថានភាព</th>
                    <th>អាសយដ្ឋាន</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($username)): ?>
                    <tr>
                        <td colspan="6" class="text-center">សូមបញ្ចូលឈ្មោះអ្នកប្រើដើម្បីមើលប្រវត្តិ!</td>
                    </tr>
                <?php elseif (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center">មិនមានទិន្នន័យប្រវត្តិសម្រាប់ឈ្មោះនេះទេ!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo htmlspecialchars($log['timestamp']); ?></td>
                            <td>
                                <a href="https://www.google.com/maps?q=<?php echo $log['latitude']; ?>,<?php echo $log['longitude']; ?>" target="_blank">
                                    <?php echo $log['latitude'] . ', ' . $log['longitude']; ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($log['status']); ?></td>
                            <td><?php echo htmlspecialchars($log['address'] ?: 'មិនមាន'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>