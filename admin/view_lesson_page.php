<?php
include 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $conn->beginTransaction();
        
        // Delete related photos/videos first
        $stmt = $conn->prepare("DELETE FROM lesson_photos WHERE lesson_id = :id");
        $stmt->bindParam(':id', $_GET['delete'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete the lesson
        $stmt = $conn->prepare("DELETE FROM lessons WHERE id = :id");
        $stmt->bindParam(':id', $_GET['delete'], PDO::PARAM_INT);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Lesson deleted successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error deleting lesson: " . $e->getMessage());
        $error_message = "Failed to delete lesson.";
    }
}

// Fetch all lessons
$stmt = $conn->prepare("SELECT * FROM lessons ORDER BY lesson_date DESC");
$stmt->execute();
$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lessons</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-50 flex h-screen font-sans">
    <!-- Sidebar (same as your original code) -->
    <aside class="w-64 bg-indigo-700 text-white p-6 hidden md:block transition-all duration-300 ease-in-out">
        <h2 class="text-xl font-bold mb-6">HR Panel</h2>
        <ul class="space-y-4">
            <li><a href="dashboard.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li><a href="add_user.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-user-plus"></i><span>Add User</span></a></li>
                <li><a href="post_meeting.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-calendar-plus"></i><span>Post Meeting</span></a></li>
                <li><a href="post_lesson.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-book"></i><span>Post Lesson</span></a></li>
                <li><a href="lessons.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-list"></i><span>View Lessons</span></a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">Lessons</h1>
                <button class="md:hidden text-indigo-700 hover:text-indigo-900 focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </header>

        <!-- Messages -->
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Lessons Table -->
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($lessons as $lesson): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($lesson['title']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($lesson['lesson_date']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="view_lesson.php?id=<?php echo $lesson['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-4">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" class="text-yellow-600 hover:text-yellow-900 mr-4">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $lesson['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this lesson?');"
                                       class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lessons)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No lessons found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>