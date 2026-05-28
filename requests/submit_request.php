<?php
// Start session for user tracking
session_start();

// Include the database from admin/includes and telegram
require_once '../admin/includes/db.php';
// Map $conn to $pdo if needed
if (isset($conn) && !isset($pdo)) {
    $pdo = $conn;
}

require_once '../system/send_to_telegram.php';

// Fetch current user ID from session if available
$currentUserId = $_SESSION['user_id'] ?? null;

// Initialize variables
$success = '';
$errors = [];

// Only process form data if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle request_type as an array from icons (via hidden inputs)
        $request_types = [];
        if (isset($_POST['request_type']) && is_array($_POST['request_type'])) {
            $request_types = array_map('trim', $_POST['request_type']);
        }
        $request_type_str = !empty($request_types) ? implode(', ', $request_types) : '';

        // --- NEW: AUTO-PULL PREVIOUS SIGNATURE & USER_ID ---
        $signatureData = null;
        $signatureDate = null;
        
        if ($currentUserId) {
            $stmtSig = $pdo->prepare("SELECT signature, signature_date FROM requests WHERE user_id = ? AND signature IS NOT NULL AND signature != '' ORDER BY id DESC LIMIT 1");
            $stmtSig->execute([$currentUserId]);
            $prevSig = $stmtSig->fetch();
            if ($prevSig) {
                $signatureData = $prevSig['signature'];
                $signatureDate = $prevSig['signature_date'] ?? date('Y-m-d');
            }
        }

        // Prepare the SQL statement including user_id, signature, and signature_date
        $stmt = $pdo->prepare("
            INSERT INTO requests (
                user_id, request_type, requester_name, number_of_days, remaining_days, department, position, branch,
                request_date, return_date, late_hours, forgot_scan_in, forgot_scan_out, time_in, time_out,
                total_hours, repay_time_in, repay_time_out, repay_total_hours, reason, assigned_to, location,
                contact_number, signature, signature_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Execute with all parameters
        $stmt->execute([
            $currentUserId,
            $request_type_str,
            trim($_POST['requester_name'] ?? ''),
            isset($_POST['number_of_days']) && $_POST['number_of_days'] !== '' ? floatval($_POST['number_of_days']) : null,
            isset($_POST['remaining_days']) && $_POST['remaining_days'] !== '' ? floatval($_POST['remaining_days']) : null,
            trim($_POST['department'] ?? ''),
            trim($_POST['position'] ?? ''),
            trim($_POST['branch'] ?? ''),
            !empty($_POST['request_date']) ? $_POST['request_date'] : null,
            !empty($_POST['return_date']) ? $_POST['return_date'] : null,
            trim($_POST['late_hours'] ?? ''),
            trim($_POST['forgot_scan_in'] ?? ''),
            trim($_POST['forgot_scan_out'] ?? ''),
            !empty($_POST['time_in']) ? $_POST['time_in'] : null,
            !empty($_POST['time_out']) ? $_POST['time_out'] : null,
            trim($_POST['total_hours'] ?? ''),
            !empty($_POST['repay_time_in']) ? $_POST['repay_time_in'] : null,
            !empty($_POST['repay_time_out']) ? $_POST['repay_time_out'] : null,
            trim($_POST['repay_total_hours'] ?? ''),
            trim($_POST['reason'] ?? ''),
            trim($_POST['assigned_to'] ?? ''),
            trim($_POST['location'] ?? ''),
            (!empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null),
            $signatureData,
            $signatureDate
        ]);

        // Success message
        $success = "ដាក់ស្នើសុំបានជោគជ័យ!";

        // Send success notification to Telegram
        $chatId = '-4714007198';
        $message = "🔔 *សំណើថ្មី* 🔔\n" .
                   "━━━━━━━━━━━━━━━\n" .
                   "📍 ប្រភេទ៖ " . $request_type_str . "\n" .
                   "👤 ឈ្មោះ៖ " . ($_POST['requester_name'] ?? 'N/A') . "\n" .
                   "🏢 ផ្នែក៖ " . ($_POST['department'] ?? 'N/A') . "\n" .
                   "📅 កាលបរិច្ឆេទ៖ " . ($_POST['request_date'] ?? 'N/A') . "\n" .
                   "📝 មូលហេតុ៖ " . ($_POST['reason'] ?? 'N/A') . "\n" .
                   "⏰ ម៉ោង៖ " . (isset($_POST['time_in']) && !empty($_POST['time_in']) ? $_POST['time_in'] : 'N/A');
        
        if (function_exists('sendTelegramMessage')) {
            sendTelegramMessage($chatId, $message);
        }

    } catch (PDOException $e) {
        $errors[] = "កំហុសមូលដ្ឋានទិន្នន័យ៖ " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>ដាក់ស្នើសុំ - HR App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Kantumruy Pro', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .request-form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            max-width: 700px;
            width: 100%;
        }
        .form-title {
            color: #4f46e5;
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
        }
        .icon-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .request-icon {
            cursor: pointer;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .request-icon.active {
            border-color: #4f46e5;
            background-color: #eef2ff;
            color: #4f46e5;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 2px solid #e2e8f0;
        }
        .btn-primary {
            background-color: #4f46e5;
            border: none;
            padding: 14px;
            font-weight: 600;
            border-radius: 12px;
            width: 100%;
            margin-top: 10px;
        }
        .success { background-color: #d1fae5; color: #065f46; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        .error { background-color: #fee2e2; color: #991b1b; padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="request-form-container">
        <h2 class="form-title">ទម្រង់ដាក់ស្នើសុំ</h2>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endforeach; ?>

        <form method="POST" id="requestForm">
            <div class="mb-4">
                <label class="form-label fw-bold">ប្រភេទនៃការស្នើសុំ</label>
                <div class="icon-group">
                    <div class="request-icon" data-value="សម្រាកប្រចាំឆ្នាំ"><i class="fas fa-calendar-alt"></i> សម្រាកប្រចាំឆ្នាំ</div>
                    <div class="request-icon" data-value="សម្រាកដោយជំងឺ"><i class="fas fa-medkit"></i> សម្រាកដោយជំងឺ</div>
                    <div class="request-icon" data-value="ភ្លេចស្កេនមេដៃ"><i class="fas fa-fingerprint"></i> ភ្លេចស្កេនមេដៃ</div>
                    <div class="request-icon" data-value="សម្រាកមាតុភាព"><i class="fas fa-baby"></i> សម្រាកមាតុភាព</div>
                    <div class="request-icon" data-value="ថែមម៉ោង (OT)"><i class="fas fa-clock"></i> ថែមម៉ោង (OT)</div>
                    <div class="request-icon" data-value="ចេញមុនម៉ោង"><i class="fas fa-door-open"></i> ចេញមុនម៉ោង</div>
                    <div class="request-icon" data-value="ប្តូរថ្ងៃសម្រាក"><i class="fas fa-exchange-alt"></i> ប្តូរថ្ងៃសម្រាក</div>
                    <div class="request-icon" data-value="សម្រាកពិសេស"><i class="fas fa-star"></i> សម្រាកពិសេស</div>
                    <div class="request-icon" data-value="មកយឺត"><i class="fas fa-hourglass-half"></i> មកយឺត</div>
                </div>
                <input type="hidden" name="request_type[]" id="selectedRequestTypes">
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">ឈ្មោះអ្នកស្នើសុំ</label>
                    <input type="text" name="requester_name" class="form-control" placeholder="បញ្ចូលឈ្មោះ" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">ផ្នែក</label>
                    <select name="department" class="form-select">
                        <option value="">ជ្រើសរើសផ្នែក</option>
                        <option value="IT">IT</option>
                        <option value="Stock">Stock</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Admin">Admin</option>
                        <option value="Sale">Sale</option>
                        <option value="Worker">Worker</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">ចំនួនថ្ងៃ</label>
                    <select name="number_of_days" class="form-select">
                        <option value="">ជ្រើសរើសចំនួនថ្ងៃ</option>
                        <?php for ($i = 0.5; $i <= 10; $i += 0.5): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?> ថ្ងៃ</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">កាលបរិច្ឆេទ</label>
                    <input type="date" name="request_date" class="form-control">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">មូលហេតុ</label>
                <textarea name="reason" class="form-control" rows="3" placeholder="បញ្ចូលមូលហេតុនៃការស្នើសុំ"></textarea>
            </div>

            <button type="submit" class="btn btn-primary">បញ្ជូនសំណើ</button>
            
            <div class="text-center mt-4">
                <a href="../homes.php" class="text-decoration-none text-muted">
                    <i class="fas fa-arrow-left me-1"></i> ត្រឡប់ទៅទំព័រដើម
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const icons = document.querySelectorAll('.request-icon');
            const hiddenInput = document.getElementById('selectedRequestTypes');

            icons.forEach(icon => {
                icon.addEventListener('click', function () {
                    this.classList.toggle('active');
                    const selected = Array.from(icons)
                        .filter(i => i.classList.contains('active'))
                        .map(i => i.getAttribute('data-value'));
                    hiddenInput.value = selected.join(', ');
                });
            });

            document.getElementById('requestForm').addEventListener('submit', function (e) {
                if (!hiddenInput.value) {
                    e.preventDefault();
                    alert('សូមជ្រើសរើសប្រភេទនៃការស្នើសុំយ៉ាងហោចណាស់មួយ!');
                }
            });
        });
    </script>
</body>
</html>
