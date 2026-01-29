<?php
// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_scan_logs_worker_db';
$dbPass = 'scan_logs_worker_db@2025';
$dbName = 'samann1_scan_logs_worker_db';

// Get parameters from GET or POST
$username = isset($_GET['username']) ? $_GET['username'] : (isset($_POST['username']) ? $_POST['username'] : '');
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : '';
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Fetch logs based on username and date range if provided
    if (!empty($username)) {
        $query = "SELECT * FROM scan_logs WHERE username = :username";
        $params = [':username' => $username];

        if (!empty($from_date) && !empty($to_date)) {
            $query .= " AND timestamp BETWEEN :from_date AND :to_date";
            $params[':from_date'] = $from_date . ' 00:00:00'; // Start of day
            $params[':to_date'] = $to_date . ' 23:59:59';     // End of day
        }

        $query .= " ORDER BY timestamp DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
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
@import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Noto+Sans+Khmer:wght@100..900&display=swap');
</style>

    <style>
    /* General Styles */
    body {
        font-family: 'Kantumruy Pro', sans-serif;
        background-color: #f8f9fa;
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    h1, h3 {
        color: #343a40;
    }

    .container {
        max-width: 90%;
        margin: 20px auto;
        position: relative;
        left: 0;
        right: 0;
    }

    .btn-back {
        margin-top: 20px;
    }

    table {
        width: 100%;
        margin-top: 20px;
        border-collapse: collapse;
    }

    .table th, .table td {
        padding: 12px;
        text-align: center;
        border-left: none !important;
        border-right: none !important;
    }

    .table th {
        background-color: #007bff;
        color: white;
    }

    .form-group {
        margin-bottom: 15px;
    }

    /* Limit width and truncate for Status and Address columns */
    .table td:nth-child(5), /* ស្ថានភាព */
    .table th:nth-child(5) {
        max-width: 90px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .table td:nth-child(6), /* អាសយដ្ឋាន */
    .table th:nth-child(6) {
        max-width: 140px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Remove left/right padding for first/last cell */
    .table th:first-child,
    .table td:first-child {
        padding-left: 0 !important;
    }
    .table th:last-child,
    .table td:last-child {
        padding-right: 0 !important;
    }

    /* Media Queries for Different Screen Sizes */
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

        .input-group input {
            font-size: 1rem;
            padding: 10px;
        }

        .main-form{
            font-size:12px;
        }

        /* Truncate status and address more on small screens */
        .table td:nth-child(5),
        .table th:nth-child(5) {
            max-width: 60px;
        }
        .table td:nth-child(6),
        .table th:nth-child(6) {
            max-width: 80px;
        }
    }

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

        .input-group input {
            padding: 8px;
            font-size: 10px;
        }

        /* Even smaller max-width for status and address */
        .table td:nth-child(5),
        .table th:nth-child(5) {
            max-width: 40px;
        }
        .table td:nth-child(6),
        .table th:nth-child(6) {
            max-width: 60px;
        }
    }
</style>

</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-center align-items-center mb-4 flex-wrap gap-2 text-center" style="flex-direction: column;">
            <div>
                <h1 class="mb-0" style="color: #007bff; font-weight: bold; letter-spacing: 1px;">ប្រវត្តិស្កេន</h1>
                <div class="text-muted" style="font-size: 1rem;">ស្វែងរកប្រវត្តិស្កេនតាមឈ្មោះ និងកាលបរិច្ឆេទ</div>
            </div>
        </div>

        <!-- Form for username and date range -->
        <form method="POST" class="mb-4 main-form p-3 rounded shadow-sm bg-white" id="searchForm" style="border: 1px solid #e3e6ea;">
            <div class="row g-2 align-items-end">
                <div class="col-12 mb-2 d-md-none">
                    <label for="username" class="form-label fw-bold" style="color:#007bff;">ឈ្មោះអ្នកប្រើ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-person" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm4-3a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/><path d="M14 14s-1-1.5-6-1.5S2 14 2 14v1h12v-1z"/></svg>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើ" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="off">
                    </div>
                </div>
                <div class="col-12 mb-2 d-md-none">
                    <label for="from_date" class="form-label fw-bold" style="color:#007bff;">ចាប់ពីថ្ងៃ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-calendar" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1H1V3zm0 2v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5H1z"/></svg>
                        </span>
                        <input type="date" class="form-control custom-date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                </div>
                <div class="col-12 mb-2 d-md-none">
                    <label for="to_date" class="form-label fw-bold" style="color:#007bff;">ដល់ថ្ងៃ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-calendar2" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1H1V3zm0 2v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5H1z"/></svg>
                        </span>
                        <input type="date" class="form-control custom-date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                </div>
                <div class="col-12 d-md-none">
                    <button type="submit" class="btn btn-primary fw-bold w-100" style="height: 40px;">
                        <i class="fa fa-search" aria-hidden="true"></i>
                        ស្វែងរក
                    </button>
                    <!-- Add Font Awesome CDN in your <head> if not already included -->
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                </div>
                <!-- Desktop layout -->
                <div class="col-md-4 form-group d-none d-md-block">
                    <label for="username" class="form-label fw-bold" style="color:#007bff;">ឈ្មោះអ្នកប្រើ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-person" viewBox="0 0 16 16"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm4-3a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/><path d="M14 14s-1-1.5-6-1.5S2 14 2 14v1h12v-1z"/></svg>
                        </span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើ" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="off">
                    </div>
                </div>
                <div class="col-md-3 form-group d-none d-md-block">
                    <label for="from_date" class="form-label fw-bold" style="color:#007bff;">ចាប់ពីថ្ងៃ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-calendar" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1H1V3zm0 2v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5H1z"/></svg>
                        </span>
                        <input type="date" class="form-control custom-date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
                    </div>
                </div>
                <div class="col-md-3 form-group d-none d-md-block">
                    <label for="to_date" class="form-label fw-bold" style="color:#007bff;">ដល់ថ្ងៃ</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="#007bff" class="bi bi-calendar2" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1H1V3zm0 2v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5H1z"/></svg>
                        </span>
                        <input type="date" class="form-control custom-date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
                    </div>
                </div>
                <div class="col-md-2 d-grid d-none d-md-block">
                    <button type="submit" class="btn btn-primary fw-bold" style="height: 40px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="white" class="bi bi-search" viewBox="0 0 16 16"><path d="M11 6a5 5 0 1 1-10 0 5 5 0 0 1 10 0zm-1.293 6.707a1 1 0 0 1-1.414 0l-3.387-3.387A6.978 6.978 0 0 1 1 6a7 7 0 1 1 7 7c-1.61 0-3.09-.534-4.293-1.293l3.387 3.387a1 1 0 0 1 0 1.414z"/></svg>
                        ស្វែងរក
                    </button>
                </div>
            </div>
        </form>
        <style>
        /* Custom style for input[type="date"] */
        input[type="date"].custom-date {
            position: relative;
            padding-right: 30px;
            background: #fff url('data:image/svg+xml;utf8,<svg fill="gray" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v1H1V3zm0 2v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5H1z"/></svg>') no-repeat right 10px center/18px 18px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            transition: border-color 0.2s;
        }

        input[type="date"].custom-date:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,.15);
        }
        </style>
        <?php if (!empty($username)): ?>
            <h3 class="mt-3">ប្រវត្តិស្កេនរបស់ <?php echo htmlspecialchars($username); ?>
                <?php if (!empty($from_date) && !empty($to_date)): ?>
                    (ចាប់ពី <?php echo htmlspecialchars($from_date); ?> ដល់ <?php echo htmlspecialchars($to_date); ?>)
                <?php endif; ?>
            </h3>
        <?php endif; ?>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ឈ្មោះ</th>
                    <th>ប្រភេទស្កេន</th>
                    <th>ថ្ងៃខែឆ្នាំ/ម៉ោង</th>
                    <th>ស្ថានភាព</th>
                    <th>អាសយដ្ឋាន</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($username)): ?>
                    <tr>
                        <td colspan="5" class="text-center">សូមបញ្ចូលឈ្មោះអ្នកប្រើដើម្បីមើលប្រវត្តិ!</td>
                    </tr>
                <?php elseif (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center">មិនមានទិន្នន័យប្រវត្តិសម្រាប់ឈ្មោះនេះទេ!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td><?php echo date('m/d/Y h:i:s A', strtotime($log['timestamp'])); ?></td>
                            <td>
                                <?php 
                                $status = htmlspecialchars($log['status']);
                                if ($status === 'Good') {
                                    echo '🔵 Good';
                                } elseif ($status === 'Late') {
                                    echo '🔴 Late';
                                } else {
                                    echo $status;
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['address'] ?: 'មិនមាន'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('searchForm');
            const usernameInput = document.getElementById('username');
            const fromDateInput = document.getElementById('from_date');
            const toDateInput = document.getElementById('to_date');
            
            let isSubmitting = false;
            let debounceTimeout;

            // Debounce function to limit submission frequency
            function debounce(func, wait) {
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(debounceTimeout);
                        func(...args);
                    };
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(later, wait);
                };
            }

            // Function to submit form
            function submitForm() {
                if (isSubmitting) return;
                if (usernameInput.value.trim() === '') return;

                isSubmitting = true;
                form.submit();
                // Reset isSubmitting after a delay to allow page reload
                setTimeout(() => {
                    isSubmitting = false;
                }, 1000);
            }

            // Debounced submit function (waits 500ms after last input)
            const debouncedSubmit = debounce(submitForm, 500);

            // Add event listeners
            usernameInput.addEventListener('input', debouncedSubmit);
            
            fromDateInput.addEventListener('change', function() {
                if (this.value && toDateInput.value) {
                    debouncedSubmit();
                }
            });

            toDateInput.addEventListener('change', function() {
                if (this.value && fromDateInput.value) {
                    debouncedSubmit();
                }
            });

            // Prevent default form submission on enter key
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>