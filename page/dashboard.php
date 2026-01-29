<?php
session_start();

// Simple authentication check (replace with proper login system)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
        if ($_POST['username'] === 'admin' && $_POST['password'] === 'password123') {
            $_SESSION['logged_in'] = true;
        } else {
            $error = "Invalid credentials!";
        }
    } else {
        // Show login form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Chatbot Dashboard Login</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-100 flex items-center justify-center h-screen">
            <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
                <h2 class="text-2xl font-bold mb-6 text-center">Login to Chatbot Dashboard</h2>
                <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>$error</p>"; ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="block text-gray-700">Username</label>
                        <input type="text" name="username" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700">Password</label>
                        <input type="password" name="password" class="w-full p-2 border rounded" required>
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_chatbot_db", "samann1_chatbot_db", "samann1_chatbot_db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_page':
                $page_id = $_POST['page_id'];
                $access_token = $_POST['access_token'];
                $page_name = $_POST['page_name'];
                $stmt = $pdo->prepare("INSERT INTO pages (page_id, access_token, page_name) VALUES (?, ?, ?)");
                $stmt->execute([$page_id, $access_token, $page_name]);
                $message = "Page added successfully!";
                break;
            case 'delete_page':
                $page_id = $_POST['page_id'];
                $stmt = $pdo->prepare("DELETE FROM pages WHERE page_id = ?");
                $stmt->execute([$page_id]);
                $message = "Page deleted successfully!";
                break;
            case 'update_response':
                $page_id = $_POST['page_id'];
                $response_type = $_POST['response_type'];
                $response_text = $_POST['response_text'];
                $stmt = $pdo->prepare("INSERT INTO responses (page_id, response_type, response_text) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE response_text = ?");
                $stmt->execute([$page_id, $response_type, $response_text, $response_text]);
                $message = "Response updated successfully!";
                break;
        }
    }
}

// Fetch pages and interactions
$pages = $pdo->query("SELECT * FROM pages")->fetchAll(PDO::FETCH_ASSOC);
$interactions = $pdo->query("SELECT u.*, p.page_name FROM users u LEFT JOIN pages p ON u.page_id = p.page_id ORDER BY u.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Chatbot Management Dashboard</h1>
        <?php if (isset($message)) echo "<p class='text-green-500 mb-4'>$message</p>"; ?>

        <!-- Manage Pages -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4">Manage Facebook Pages</h2>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_page">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input type="text" name="page_id" placeholder="Page ID" class="p-2 border rounded" required>
                    <input type="text" name="access_token" placeholder="Access Token" class="p-2 border rounded" required>
                    <input type="text" name="page_name" placeholder="Page Name" class="p-2 border rounded" required>
                </div>
                <button type="submit" class="mt-4 bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Add Page</button>
            </form>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 border">Page Name</th>
                        <th class="p-2 border">Page ID</th>
                        <th class="p-2 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pages as $page): ?>
                        <tr>
                            <td class="p-2 border"><?php echo htmlspecialchars($page['page_name']); ?></td>
                            <td class="p-2 border"><?php echo htmlspecialchars($page['page_id']); ?></td>
                            <td class="p-2 border">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="delete_page">
                                    <input type="hidden" name="page_id" value="<?php echo $page['page_id']; ?>">
                                    <button type="submit" class="text-red-500 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Configure Responses -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
            <h2 class="text-2xl font-semibold mb-4">Configure Responses</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_response">
                <div class="mb-4">
                    <label class="block text-gray-700">Select Page</label>
                    <select name="page_id" class="p-2 border rounded w-full" required>
                        <?php foreach ($pages as $page): ?>
                            <option value="<?php echo $page['page_id']; ?>"><?php echo htmlspecialchars($page['page_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Response Type</label>
                    <select name="response_type" class="p-2 border rounded w-full" required>
                        <option value="welcome">Welcome Message</option>
                        <option value="products">Product List</option>
                        <option value="fallback">Fallback Response</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Response Text</label>
                    <textarea name="response_text" class="p-2 border rounded w-full" rows="4" required></textarea>
                </div>
                <button type="submit" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Update Response</button>
            </form>
        </div>

        <!-- User Interactions -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-semibold mb-4">User Interactions</h2>
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="p-2 border">User ID</th>
                        <th class="p-2 border">Page Name</th>
                        <th class="p-2 border">Message</th>
                        <th class="p-2 border">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($interactions as $interaction): ?>
                        <tr>
                            <td class="p-2 border"><?php echo htmlspecialchars($interaction['user_id']); ?></td>
                            <td class="p-2 border"><?php echo htmlspecialchars($interaction['page_name']); ?></td>
                            <td class="p-2 border"><?php echo htmlspecialchars($interaction['message']); ?></td>
                            <td class="p-2 border"><?php echo $interaction['created_at']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>