<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage(), 3, 'errors.log');
    die('ការតភ្ជាប់ទិន្នន័យបរាជ័យ: ' . $e->getMessage());
}

// Initialize messages
$success_message = '';
$error_message = '';

// Get reward ID from URL
$reward_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reward_id) {
    $error_message = "រង្វាន់មិនត្រឹមត្រូវ!";
    header('Location: dashboard.php?section=rewards');
    exit();
}

// Fetch reward details
try {
    $stmt = $pdo->prepare("SELECT id, name, points_required, image FROM rewards WHERE id = ?");
    $stmt->execute([$reward_id]);
    $reward = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$reward) {
        $error_message = "រកមិនឃើញរង្វាន់!";
        header('Location: dashboard.php?section=rewards');
        exit();
    }
} catch (PDOException $e) {
    error_log('Reward fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកទិន្នន័យរង្វាន់: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_reward') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $points_required = filter_input(INPUT_POST, 'points_required', FILTER_VALIDATE_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);

    if ($name && $points_required && $image) {
        try {
            $stmt = $pdo->prepare("UPDATE rewards SET name = ?, points_required = ?, image = ? WHERE id = ?");
            $stmt->execute([$name, $points_required, $image, $reward_id]);
            $success_message = "បានធ្វើបច្ចុប្បន្នភាពរង្វាន់ដោយជោគជ័យ!";
            // Redirect to rewards section after successful update
            header('Location: dashboard.php?section=rewards');
            exit();
        } catch (PDOException $e) {
            error_log('Reward update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការធ្វើបច្ចុប្បន្នភាពរង្វាន់: " . $e->getMessage();
        }
    } else {
        $error_message = "សូមបំពេញព័ត៌មានឱ្យគ្រប់!";
    }
}

// Set active section for sidebar
$active_section = 'rewards';
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - កែសម្រួលរង្វាន់</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background-color: #f3f4f6;
        }
        .sidebar {
            background-color: #1f2937;
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            overflow-y: auto;
            transition: transform 0.3s ease-in-out;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            color: #d1d5db;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .sidebar a i {
            margin-right: 0.75rem;
            font-size: 1.25rem;
        }
        .sidebar a:hover {
            background-color: #374151;
        }
        .sidebar a.active {
            background-color: #10b981;
            color: #ffffff;
        }
        .content {
            margin-left: 250px;
            padding: 2rem;
        }
        .card {
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .card h2 i {
            margin-right: 0.5rem;
            color: #10b981;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f3f4f6;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 200px;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h1 class="text-xl font-bold text-white"><i class="fas fa-cog mr-2"></i> Admin Panel</h1>
        </div>
        <nav>
            <a href="dashboard.php?section=dashboard" class="<?php echo $active_section === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> ផ្ទាំងគ្រប់គ្រង</a>
            <a href="dashboard.php?section=points" class="<?php echo $active_section === 'points' ? 'active' : ''; ?>"><i class="fas fa-star"></i> កំណត់ពិន្ទុ</a>
            <a href="dashboard.php?section=rewards" class="<?php echo $active_section === 'rewards' ? 'active' : ''; ?>"><i class="fas fa-gift"></i> គ្រប់គ្រងរង្វាន់</a>
            <a href="dashboard.php?section=users" class="<?php echo $active_section === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i> អ្នកប្រើ</a>
            <a href="dashboard.php?section=products" class="<?php echo $active_section === 'products' ? 'active' : ''; ?>"><i class="fas fa-box"></i> ផលិតផល</a>
            <a href="dashboard.php?section=cart" class="<?php echo $active_section === 'cart' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> កន្ត្រកទំនិញ</a>
            <a href="dashboard.php?section=orders" class="<?php echo $active_section === 'orders' ? 'active' : ''; ?>"><i class="fas fa-file-invoice"></i> ការបញ្ជាទិញ</a>
            <a href="logout.php" class="bg-red-500 text-white mt-4 mx-4 rounded-lg text-center flex items-center justify-center"><i class="fas fa-sign-out-alt mr-2"></i> ចាកចេញ</a>
        </nav>
    </div>

    <!-- Content -->
    <div class="content">
        <button class="menu-toggle hidden fixed top-4 left-4 z-50 p-2 bg-gray-800 text-white rounded-lg md:hidden" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <h1 class="text-3xl font-bold text-gray-800 mb-6"><i class="fas fa-gift mr-2 text-teal-500"></i> កែសម្រួលរង្វាន់</h1>

        <?php if ($success_message): ?>
            <div class="card bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="card bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Edit Reward Form -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-edit"></i> កែសម្រួលរង្វាន់ (ID: <?php echo $reward['id']; ?>)</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_reward">
                <div>
                    <label for="name" class="block text-gray-700">ឈ្មោះរង្វាន់:</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($reward['name']); ?>" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                </div>
                <div>
                    <label for="points_required" class="block text-gray-700">ពិន្ទុដែលត្រូវការ:</label>
                    <input type="number" name="points_required" id="points_required" value="<?php echo $reward['points_required']; ?>" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="1" required>
                </div>
                <div>
                    <label for="image" class="block text-gray-700">URL រូបភាព:</label>
                    <input type="url" name="image" id="image" value="<?php echo htmlspecialchars($reward['image']); ?>" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                    <img src="<?php echo htmlspecialchars($reward['image']); ?>" alt="Reward Image" class="mt-2 w-32 h-32 object-cover rounded-md">
                </div>
                <div class="flex space-x-4">
                    <button type="submit" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i class="fas fa-save mr-2"></i> រក្សាទុក</button>
                    <a href="dashboard.php?section=rewards" class="bg-gray-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-gray-600 transition duration-300"><i class="fas fa-times mr-2"></i> បោះបង់</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>