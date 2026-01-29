<?php
session_start(); // Start session for login checking

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

// Pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // Number of lessons per page
$offset = ($page - 1) * $limit;

// Fetch total number of lessons
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM lessons");
    $totalLessons = $stmt->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($totalLessons / $limit);

    // Fetch lessons for the current page
    $stmt = $conn->prepare("
        SELECT id, title, lesson_date, description 
        FROM lessons 
        ORDER BY lesson_date DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $lessons = [];
    $totalLessons = 0;
    $totalPages = 1;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lessons - HR Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #e0e0e0;
        }
        .nav-bar {
            background: linear-gradient(to right, #2a2a2a, #3a3a3a);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .lesson-card {
            background: #252525;
            border: 1px solid #333;
            transition: all 0.3s ease;
        }
        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
            border-color: #555;
        }
        .btn-premiere {
            background: linear-gradient(to right, #ff6d00, #ff9500);
            color: white;
            transition: all 0.3s ease;
        }
        .btn-premiere:hover {
            background: linear-gradient(to right, #ff9500, #ffaa00);
            transform: scale(1.05);
        }
        .pagination-btn {
            background: #333;
            color: #e0e0e0;
            transition: all 0.3s ease;
        }
        .pagination-btn:hover {
            background: #444;
        }
        .pagination-btn.active {
            background: linear-gradient(to right, #ff6d00, #ff9500);
            color: white;
        }
        .truncate-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased">
    <!-- Top Navigation Bar -->
    <nav class="nav-bar fixed top-0 left-0 w-full text-white z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold tracking-tight">HR Studio</h1>
                </div>
                <div class="flex items-center space-x-6">
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="add_user.php" class="hover:text-orange-300 transition duration-200 flex items-center">
                            <i class="fas fa-user-plus mr-2"></i> Add User
                        </a>
                        <a href="post_meeting.php" class="hover:text-orange-300 transition duration-200 flex items-center">
                            <i class="fas fa-calendar-plus mr-2"></i> Post Meeting
                        </a>
                        <a href="post_lesson.php" class="hover:text-orange-300 transition duration-200 flex items-center">
                            <i class="fas fa-book mr-2"></i> Post Lesson
                        </a>
                        <a href="lessons.php" class="hover:text-orange-300 transition duration-200 flex items-center">
                            <i class="fas fa-list mr-2"></i> View Lessons
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="hover:text-orange-300 transition duration-200 flex items-center">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 pb-12">
        <!-- Header -->
        <header class="mb-10">
            <h1 class="text-4xl font-extrabold text-white tracking-wide">Lessons Library</h1>
            <p class="text-gray-400 mt-2">Explore and manage your premium lesson collection.</p>
        </header>

        <!-- Lessons Section -->
        <section>
            <?php if (empty($lessons)): ?>
                <div class="text-center py-16 bg-gray-800 rounded-lg">
                    <p class="text-gray-400 text-lg">No lessons available at this time.</p>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="post_lesson.php" class="btn-premiere inline-flex items-center px-4 py-2 mt-4 rounded-lg font-medium">
                            <i class="fas fa-plus mr-2"></i> Create a Lesson
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($lessons as $lesson): ?>
                        <div class="lesson-card rounded-lg p-6">
                            <h2 class="text-xl font-semibold text-white"><?php echo htmlspecialchars($lesson['title']); ?></h2>
                            <p class="text-sm text-gray-400 mt-1"><?php echo htmlspecialchars($lesson['lesson_date']); ?></p>
                            <p class="text-gray-300 mt-3 truncate-3"><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                            <div class="mt-4 flex space-x-4">
                                <a href="view_lesson.php?id=<?php echo $lesson['id']; ?>" 
                                   class="btn-premiere inline-flex items-center px-4 py-2 rounded-lg font-medium">
                                    <i class="fas fa-eye mr-2"></i> View
                                </a>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" 
                                       class="text-yellow-400 hover:text-yellow-300 font-medium transition duration-200">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $lesson['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this lesson?');"
                                       class="text-red-400 hover:text-red-300 font-medium transition duration-200">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <div class="mt-12 flex justify-center">
                    <?php if ($totalPages > 1): ?>
                        <nav class="inline-flex rounded-md shadow-md" aria-label="Pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="pagination-btn relative inline-flex items-center px-4 py-2 mx-1 rounded-md text-sm font-medium <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>