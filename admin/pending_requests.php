<?php
require_once 'includes/auth.php'; // Starts the session and provides isLoggedIn()
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ];
    $conn = new PDO($dsn, $username, $password, $options);
    $conn->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("後夺後後犪夺後峄後岫後忈後岫後後後坚後後夺後丰後後愥� 後坚後後夺岫後後忈後釓後後佱後後後");
}

// Handle removing requests from pending (update status or delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = htmlspecialchars($_GET['action']);
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($id === false || $id <= 0) {
        header("Location: pending_requests.php?error=invalid_request");
        exit();
    }

    try {
        if ($action === 'remove_pending') {
            // Update status to 'rejected'
            $stmt = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                header("Location: pending_requests.php?success=updated");
            } else {
                header("Location: pending_requests.php?error=no_changes");
            }
        } elseif ($action === 'delete') {
            // Delete the record
            $stmt = $conn->prepare("DELETE FROM requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                header("Location: pending_requests.php?success=deleted");
            } else {
                header("Location: pending_requests.php?error=no_changes");
            }
        } else {
            header("Location: pending_requests.php?error=invalid_action");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        header("Location: pending_requests.php?error=database_error");
        exit();
    }
    exit();
}

// Initialize variables for messages
$success = '';
$error = '';

// Handle success/error messages from redirects
if (isset($_GET['success'])) {
    $success = match ($_GET['success']) {
        'updated' => '後後踞忈後坚後夺後会後佱後羔後愥夺後夺 "pending" 後後後後愥!',
        'deleted' => '後後踞忈後坚後夺後会後後後後愥!',
        'approved' => '後後踞忈後坚後夺幄後会釔釓後後後後愥!',
        'rejected' => '後後踞忈後坚後夺後岱後佱 後後後後愥!',
        default => '後後踞忈後坚後夺後後踞後後佱後後後後愥!'
    };
}
if (isset($_GET['error'])) {
    $error = match ($_GET['error']) {
        'database_error' => '後釥峄後峒後後岫後岱後後釔後岫後峋釓帷峋後',
        'invalid_request' => '後佱佱後後夺後後峋後丰釓後峁後忈後坚�',
        'no_changes' => '後後夺後夺後後夺後後忈坚 岈後後踞岱後後後釔岱釓後後会後釔岫後岫後後後夺後佱',
        'invalid_action' => '後後後岫後岱後忈後贯釓後峒後',
        default => '後釥峄後岱後後岫後後夺後踞忈♂踞�'
    };
}

// Fetch pending requests
try {
    $stmt = $conn->prepare("
        SELECT * 
        FROM requests 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $pendingRequests = [];
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>後後踞後後夺</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background: #f4f7f6;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 1200px;
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #1a3c5e;
            text-align: center;
            margin-bottom: 25px;
            font-size: 1.8rem;
            font-weight: 700;
            position: relative;
        }
        h1::after {
            content: '';
            width: 50px;
            height: 3px;
            background: #007bff;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }
        .success, .error {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .success {
            background-color: #e6ffe6;
            color: #2d862d;
            border: 1px solid #b3ffb3;
        }
        .error {
            background-color: #ffe6e6;
            color: #cc0000;
            border: 1px solid #ff9999;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.95rem;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #dee2e6;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: 700;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e9ecef;
        }
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 6px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            margin-right: 5px; /* Space between buttons */
        }
        .btn-approve {
            background-color: #28a745;
        }
        .btn-reject {
            background-color: #dc3545;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-primary {
            background-color: #007bff;
            padding: 10px 20px;
            border-radius: 6px;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .text-center {
            text-align: center;
        }
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            h1 {
                font-size: 1.5rem;
            }
            table {
                font-size: 0.85rem;
            }
            th, td {
                padding: 8px;
            }
            .btn {
                padding: 5px 10px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="flex mb-8 items-center gap-3 text-slate-500 font-black text-[10px] uppercase tracking-widest animate-fade-in" aria-label="Breadcrumb" style="margin-bottom: 2rem;">
            <a href="dashboard.php" class="flex items-center gap-2 hover:text-amber-600 transition-colors bg-white px-4 py-2 rounded-xl shadow-sm border border-slate-100 no-underline text-slate-600" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; background: white; padding: 0.5rem 1rem; border-radius: 0.75rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); border: 1px solid #f1f5f9; color: #475569;">
                <i class="fas fa-home text-amber-500" style="color: #f59e0b;"></i>
                <span>ផ្ទាំងគ្រប់គ្រង</span>
            </a>
            <i class="fas fa-chevron-right text-slate-300 text-[8px]" style="font-size: 12px; color: #cbd5e1; margin: 0 0.5rem;"></i>
            <span class="bg-amber-500/10 text-amber-600 px-4 py-2 rounded-xl border border-amber-500/10" style="background-color: rgba(245, 158, 11, 0.1); color: #d97706; padding: 0.5rem 1rem; border-radius: 0.75rem; border: 1px solid rgba(245, 158, 11, 0.1);">
                សំណើរង់ចាំ (Pending Requests)
            </span>
        </nav>

        <h1>後後踞後後夺</h1>

        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if ($error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (empty($pendingRequests)): ?>
            <p class="text-center">後丰後夺後後踞後後夺後佱</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>後佱佱後後夺�</th>
                        <th>後後醽後後峋</th>
                        <th>後後後⑨後後後�</th>
                        <th>後後�</th>
                        <th>後坚釥醽釓峄</th>
                        <th>後夺後岱後後佱後後�</th>
                        <th>後釔岫後岫�</th>
                        <th>後後後岫�</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRequests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['id']); ?></td>
                            <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                            <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['department'] ?? '後丰後夺'); ?></td>
                            <td><?php echo htmlspecialchars($request['reason']); ?></td>
                            <td><?php echo htmlspecialchars($request['request_date'] ?? '後丰後夺'); ?></td>
                            <td><?php echo htmlspecialchars($request['status'] === 'pending' ? '後後岫�' : $request['status']); ?></td>
                            <td>
                                <a href="approve_request.php?id=<?php echo $request['id']; ?>" class="btn btn-approve">幄後会釔釓</a>
                                <a href="reject_request.php?id=<?php echo $request['id']; ?>" class="btn btn-reject">後岱後佱</a>
                                <a href="?action=remove_pending&id=<?php echo $request['id']; ?>" class="btn btn-secondary" onclick="return confirm('釓峋幄後後後岫後岈釔岫後後峄後後峋後佱後羔後愥夺後夺 "pending" 岈?')">後会後羔後愥夺後夺後後岫�</a>
                                <a href="?action=delete&id=<?php echo $request['id']; ?>" class="btn btn-danger" onclick="return confirm('釓峋幄後後後岫後岈釔岫後後峄後後峋後佱後夺後後峄後�?')">後会</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-primary">釓後帷後後後後夺後後後後後</a>
        </div>
    </div>
</body>
</html>
