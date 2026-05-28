<?php
session_start();
include '../system/db.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលប្រព័ន្ធជាមុន!';
    header('Location: ../auth/login.php');
    exit;
}

// Check user role
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is_admin = ($user && $user['role'] === 'admin');
    error_log('../system/table.php - user_id: ' . ($_SESSION['user_id'] ?? 'not set') . ', is_admin: ' . ($is_admin ? 'true' : 'false'));
} catch (PDOException $e) {
    $_SESSION['error'] = 'មិនអាចទាញទិន្នន័យអ្នកប្រើប្រាស់បានទេ: ' . $e->getMessage();
    error_log('Error fetching user role in table.php: ' . $e->getMessage());
    $is_admin = false;
}

// Initialize session messages
$_SESSION['error'] = $_SESSION['error'] ?? '';
$_SESSION['success'] = $_SESSION['success'] ?? '';

// Generate CSRF token for actions
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

// Handle delete request (admin only)
if (isset($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (!$is_admin) {
        $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិលុបទិន្នន័យបុគ្គលិកទេ!';
        header('Location: ../system/table.php');
        exit;
    }
    if ($_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = 'សកម្មភាពមិនត្រឹមត្រូវ (CSRF token មិនត្រូវគ្នា)!';
        header('Location: ../system/table.php');
        exit;
    }

    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id === false) {
        $_SESSION['error'] = 'លេខសម្គាល់បុគ្គលិកមិនត្រឹមត្រូវ!';
        header('Location: ../system/table.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$delete_id]);
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'លុបបុគ្គលិកជោគជ័យ!';
            error_log('../system/table.php - Employee deleted, id: ' . $delete_id . ', user_id: ' . $_SESSION['user_id']);
        } else {
            $_SESSION['error'] = 'រកមិនឃើញបុគ្គលិកដែលត្រូវលុប!';
            error_log('../system/table.php - No employee found for deletion, id: ' . $delete_id);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'មិនអាចលុបបុគ្គលិកបានទេ: ' . $e->getMessage();
        error_log('Error deleting employee in table.php: ' . $e->getMessage() . ' | id: ' . $delete_id);
    }
    header('Location: ../system/table.php');
    exit;
}

try {
    // Check database connection
    if (!isset($pdo)) {
        throw new Exception('មិនអាចភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបានទេ!');
    }

    // Get table columns
    $stmt = $pdo->query("SHOW COLUMNS FROM employees");
    $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    // Fetch all employee data
    $query = "SELECT * FROM employees";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('../system/table.php - Query executed, rows fetched: ' . count($employees));
} catch (PDOException $e) {
    $_SESSION['error'] = 'មិនអាចទាញទិន្នន័យបុគ្គលិកបានទេ: ' . $e->getMessage();
    error_log('Error fetching employees in table.php: ' . $e->getMessage() . ' | user_id: ' . ($_SESSION['user_id'] ?? 'not set'));
    $employees = [];
}

// Handle logout request
if (isset($_GET['logout'])) {
    session_destroy();
    $_SESSION['success'] = 'អ្នកបានចាកចេញពីប្រព័ន្ធដោយជោគជ័យ!';
    header('Location: ../auth/login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ទិន្នន័យបុគ្គលិក</title>
    <style>
        body {
            font-family: 'Khmer OS', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333333;
            background-color: #F8F8F8;
        }
        .table-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #FFD700;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        .table-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #FFD700;
        }
        .table-header h1 {
            font-size: 1.8rem;
            color: #D4A017;
            margin: 0;
        }
        .table-header p {
            font-size: 1rem;
            color: #333333;
            margin: 5px 0;
        }
        .table-wrapper {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 20px;
            background: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #FFD700;
            font-size: 0.9rem;
        }
        th {
            background-color: #FFD700;
            color: #FFFFFF;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            color: #333333;
            background: #FFFFFF;
        }
        tr:nth-child(even) td {
            background: #F8F8F8;
        }
        tr:hover td {
            background: #FFF8E1;
            transition: background-color 0.3s ease;
        }
        tr:last-child td {
            border-bottom: none;
        }
        .profile-pic {
            width: 48px;
            height: 48px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #FFD700;
            display: block;
            margin: 0 auto;
        }
        .action-btn {
            color: #FFFFFF;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            text-align: center;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 2px;
        }
        .edit-btn {
            background-color: #FFD700;
        }
        .edit-btn:hover {
            background-color: #D4A017;
            transform: scale(1.05);
        }
        .delete-btn {
            background-color: #FF6347;
        }
        .delete-btn:hover {
            background-color: #D43F2A;
            transform: scale(1.05);
        }
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            font-size: 1rem;
        }
        .alert-success {
            background-color: #FFD700;
            color: #333333;
        }
        .alert-error {
            background-color: #FF6347;
            color: #FFFFFF;
        }
        @media (max-width: 600px) {
            .table-container {
                padding: 15px;
            }
            .table-wrapper {
                overflow-x: hidden;
            }
            table {
                display: block;
                border: none;
            }
            thead {
                display: none;
            }
            tbody, tr, td {
                display: block;
                width: 100%;
                border: none;
            }
            tr {
                margin-bottom: 15px;
                background: #FFFFFF;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                transition: box-shadow 0.3s ease;
            }
            tr:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            }
            td {
                padding: 10px;
                font-size: 0.85rem;
                text-align: left;
                background: transparent;
                position: relative;
                border-bottom: 1px solid #FFD700;
            }
            td:last-child {
                border-bottom: none;
            }
            td::before {
                content: attr(data-label);
                font-weight: bold;
                color: #D4A017;
                display: block;
                margin-bottom: 5px;
            }
            td[data-label="សកម្មភាព"], td[data-label="រូបភាព"] {
                text-align: center;
            }
            .profile-pic {
                width: 40px;
                height: 40px;
            }
            .action-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
            .table-header h1 {
                font-size: 1.5rem;
            }
            .table-header p {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="table-container">
        <?php if ($_SESSION['success']): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if ($_SESSION['error']): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="table-header">
            <h1>ទិន្នន័យបុគ្គលិក</h1>
            <p>មើល និងកែប្រែទិន្នន័យបុគ្គលិកទាំងអស់</p>
            <?php if (!empty($employees)): ?>
                <a href="profile.php?employee_id=<?php echo htmlspecialchars($employees[0]['id'] ?? ''); ?>" class="action-btn edit-btn">មើលប្រវត្តិរូបផ្ទាល់ខ្លួន</a>
            <?php endif; ?>
        </div>

        <?php if (empty($employees)): ?>
            <p>មិនមានទិន្នន័យបុគ្គលិកទេ!</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>រូបភាព</th>
                            <th>ឈ្មោះ</th>
                            <th>ភេទ</th>
                            <th>ថ្ងៃខែឆ្នាំកំណើត</th>
                            <th>អ៊ីមែល</th>
                            <th>លេខទូរស័ព្ទ</th>
                            <th>តួនាទី</th>
                            <th>ផ្នែក</th>
                            <th>ថ្ងៃចូលធ្វើការ</th>
                            <th>លេខបុគ្គលិក</th>
                            <th>ច្បាប់បានប្រើ</th>
                            <th>ច្បាប់នៅសល់</th>
                            <th>ភ្លេចស្កេន</th>
                            <th>យឺត</th>
                            <th>ប្រាក់កាត់ ($)</th>
                            <th>សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td data-label="រូបភាព">
                                    <?php if ($employee['profile_pic']): ?>
                                        <img src="Uploads/profiles/<?php echo htmlspecialchars($employee['profile_pic']); ?>" alt="Profile Picture" class="profile-pic">
                                    <?php else: ?>
                                        <img src="Uploads/profiles/default_profile.jpg" alt="Default Profile" class="profile-pic">
                                    <?php endif; ?>
                                </td>
                                <td data-label="ឈ្មោះ"><?php echo htmlspecialchars($employee['name']); ?></td>
                                <td data-label="ភេទ">
                                    <?php
                                    $gender = $employee['gender'];
                                    echo $gender === 'male' ? 'ប្រុស' : ($gender === 'female' ? 'ស្រី' : 'ផ្សេងទៀត');
                                    ?>
                                </td>
                                <td data-label="ថ្ងៃខែឆ្នាំកំណើត"><?php echo htmlspecialchars($employee['dob'] ?? '-'); ?></td>
                                <td data-label="អ៊ីមែល"><?php echo htmlspecialchars($employee['email'] ?? '-'); ?></td>
                                <td data-label="លេខទូរស័ព្ទ"><?php echo htmlspecialchars($employee['phone']); ?></td>
                                <td data-label="តួនាទី"><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td data-label="ផ្នែក"><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td data-label="ថ្ងៃចូលធ្វើការ"><?php echo htmlspecialchars($employee['join_date']); ?></td>
                                <td data-label="លេខបុគ្គលិក"><?php echo htmlspecialchars($employee['employee_code']); ?></td>
                                <td data-label="ច្បាប់បានប្រើ"><?php echo htmlspecialchars($employee['leave_taken']); ?></td>
                                <td data-label="ច្បាប់នៅសល់"><?php echo htmlspecialchars($employee['leave_left']); ?></td>
                                <td data-label="ភ្លេចស្កេន"><?php echo htmlspecialchars($employee['fingerprint_miss']); ?></td>
                                <td data-label="យឺត"><?php echo htmlspecialchars($employee['late_count']); ?></td>
                                <td data-label="ប្រាក់កាត់ ($)"><?php echo number_format($employee['salary_cut'], 2); ?></td>
                                <td data-label="សកម្មភាព">
                                    <a href="../system/form_input.php?employee_id=<?php echo $employee['id']; ?>" class="action-btn edit-btn">កែប្រែ</a>
                                    <a href="../system/table.php?delete_id=<?php echo $employee['id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" class="action-btn delete-btn" onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបបុគ្គលិកនេះមែនទេ?');">លុប</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Add fade-in animation for rows
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(10px)';
                setTimeout(() => {
                    row.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>