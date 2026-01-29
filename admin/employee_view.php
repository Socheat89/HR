<?php
include 'includes/auth.php';
include 'includes/db.php';
$conn = include 'includes/db.php';

// Redirect to login page if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

// Function to check if the user is an admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

try {
    if (isAdmin()) {
        // Admins see all users with role 'admin' or 'employee'
        $stmt = $conn->prepare("SELECT * FROM users WHERE role = 'admin' OR role = 'employee'");
    } else {
        // Non-admins see only their own record
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the employees.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --bg-dark: #121212;
            --card-bg: #1e1e1e;
            --primary: #00e676;
            --secondary: #ff1744;
            --text-light: #e0e0e0;
            --accent: #3f51b5;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            --glow: 0 0 10px rgba(0, 230, 118, 0.5);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-dark);
            color: var(--text-light);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
        }

        .container-fluid {
            padding: 0;
        }

        /* Header */
        .dashboard-header {
            text-align: center;
            margin: 40px 0;
        }
        .dashboard-title {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: var(--glow);
            animation: flicker 2s infinite alternate;
        }

        /* Search Bar */
        .search-container {
            max-width: 500px;
            margin: 0 auto 40px;
            position: relative;
        }
        .search-input {
            width: 100%;
            padding: 12px 20px;
            background: var(--card-bg);
            border: 2px solid var(--primary);
            border-radius: 30px;
            color: var(--text-light);
            font-size: 1rem;
            transition: 0.3s ease;
            box-shadow: var(--shadow);
        }
        .search-input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 15px rgba(255, 23, 68, 0.4);
            outline: none;
        }
        .search-input::placeholder {
            color: rgba(224, 224, 224, 0.5);
        }

        /* Card Layout */
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 0 20px;
        }
        .employee-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: 0.3s ease;
            position: relative;
            overflow: hidden;
            animation: slideUp 0.5s ease-in;
        }
        .employee-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(0, 230, 118, 0.3);
            border: 1px solid var(--primary);
        }
        .employee-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0.8;
        }
        .employee-img {
            width: 80px;
            height: 85px;
            border-radius: 10px;
            border: 3px solid var(--primary);
            margin-bottom: 15px;
            transition: 0.3s ease;
        }
        .employee-img:hover {
            transform: scale(1.1);
            border-color: var(--secondary);
            box-shadow: var(--glow);
        }
        .employee-info {
            margin-bottom: 15px;
        }
        .employee-info h5 {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--primary);
            margin: 0;
        }
        .employee-info p {
            font-size: 0.9rem;
            color: rgba(224, 224, 224, 0.8);
            margin: 5px 0 0;
        }
        .employee-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .action-btn {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            transition: 0.3s ease;
            border: none;
            box-shadow: var(--shadow);
            text-decoration: none;
        }
        .btn-view { background: var(--primary); color: #121212; }
        .btn-edit { background: var(--accent); color: #fff; }
        .btn-delete { background: var(--secondary); color: #fff; }
        .action-btn:hover {
            transform: scale(1.05);
            filter: brightness(1.2);
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
        }

        /* FAB (Floating Action Button) */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            color: #121212;
            font-size: 1.5rem;
            transition: 0.3s ease;
            z-index: 1000;
            border: none;
        }
        .fab:hover {
            background: var(--secondary);
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(255, 23, 68, 0.5);
        }

        /* Logout Button */
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: var(--secondary);
            color: #fff;
            border: none;
            border-radius: 20px;
            font-size: 1rem;
            font-weight: 500;
            text-transform: uppercase;
            transition: 0.3s ease;
            box-shadow: var(--shadow);
        }
        .logout-btn:hover {
            background: var(--primary);
            color: #121212;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(0, 230, 118, 0.5);
        }

        /* No Data */
        .no-data {
            text-align: center;
            padding: 50px;
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: var(--shadow);
            font-size: 1.5rem;
            color: var(--primary);
            margin: 0 20px;
            animation: fadeIn 0.5s ease-in;
        }
        .no-data i {
            font-size: 2.5rem;
            color: var(--secondary);
            animation: spin 2s infinite linear;
        }

        /* Animations */
        @keyframes flicker {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-title { font-size: 2rem; }
            .employee-grid { grid-template-columns: 1fr; }
            .employee-img { width: 60px; height: 60px; }
            .action-btn { padding: 6px 12px; font-size: 0.8rem; }
            .fab { width: 50px; height: 50px; font-size: 1.2rem; }
            .logout-btn { padding: 8px 15px; font-size: 0.9rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Logout Button -->
        <form action="https://app.vvc.asia/logout.php" method="POST">
            <button type="submit" class="logout-btn">Logout</button>
        </form>

        <div class="dashboard-header">
            <h1 class="dashboard-title">Employee Dashboard</h1>
        </div>

        <div class="search-container">
            <input type="text" id="searchInput" class="search-input" placeholder="Search Employees..." <?php echo !isAdmin() ? 'disabled' : ''; ?>>
        </div>

        <?php if (empty($employees)): ?>
            <div class="no-data">
                <i class="fas fa-user-slash"></i>
                <p>No Employees Found</p>
            </div>
        <?php else: ?>
            <div class="employee-grid" id="employeeGrid">
                <?php foreach ($employees as $employee): ?>
                    <div class="employee-card" data-name="<?php echo htmlspecialchars(strtolower($employee['full_name'])); ?>">
                        <img src="<?php echo htmlspecialchars($employee['image_url']); ?>" alt="Profile" class="employee-img">
                        <div class="employee-info">
                            <h5><?php echo htmlspecialchars($employee['full_name']); ?></h5>
                            <p><?php echo htmlspecialchars($employee['email']); ?></p>
                            <p>Role: <?php echo htmlspecialchars($employee['role']); ?></p>
                            <p>ID: <?php echo htmlspecialchars($employee['id']); ?></p>
                        </div>
                        <div class="employee-actions">
                            <a href="view_employee.php?id=<?php echo htmlspecialchars($employee['id']); ?>" class="action-btn btn-view">View</a>
                           
                            <?php if (isAdmin()): ?>
                                <a href="delete_user.php?id=<?php echo htmlspecialchars($employee['id']); ?>" class="action-btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
            <button class="fab" title="Add Employee"><i class="fas fa-plus"></i></button>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const employeeGrid = document.getElementById('employeeGrid');

            <?php if (isAdmin()): ?>
            searchInput.addEventListener('input', (event) => {
                const searchTerm = event.target.value.toLowerCase();
                Array.from(employeeGrid.children).forEach((card) => {
                    const name = card.getAttribute('data-name');
                    card.style.display = name.includes(searchTerm) ? '' : 'none';
                });
            });
            <?php endif; ?>

            <?php if (isAdmin()): ?>
            document.querySelector('.fab').addEventListener('click', () => {
                alert('Add Employee functionality coming soon!');
                // Replace with actual redirect, e.g., window.location.href = 'add_employee.php';
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>