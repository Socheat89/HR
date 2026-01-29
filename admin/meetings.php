<?php

include 'admin/includes/db.php';
$conn = include 'includes/db.php';

// Fetch all meetings from the database
try {
    $stmt = $conn->query("
        SELECT m.id, m.title, m.meeting_date, m.description, m.mp3_url, GROUP_CONCAT(mp.photo_url) AS photo_urls
        FROM meetings m
        LEFT JOIN meeting_photos mp ON m.id = mp.meeting_id
        GROUP BY m.id
        ORDER BY m.meeting_date DESC
    ");
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $meetings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management - Meetings List</title>
    <!-- Include Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        /* Custom Styles */
        .single-photo {
            width: 4rem; /* Fixed width for the photo */
            height: 4rem; /* Fixed height for the photo */
            object-fit: cover; /* Ensures the image fits within the dimensions */
            border-radius: 0.5rem; /* Rounded corners */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Subtle shadow */
            transition: transform 0.3s ease-in-out; /* Smooth hover effect */
        }

        .single-photo:hover {
            transform: scale(1.1); /* Slightly zoom on hover */
        }
    </style>
</head>
<body class="bg-gray-50 flex h-screen font-sans">
    <!-- Sidebar -->
    <aside class="w-64 bg-indigo-700 text-white p-6 hidden md:block transition-all duration-300 ease-in-out">
        <h2 class="text-xl font-bold mb-6">HR Panel</h2>
        <ul class="space-y-4">
            <li>
                <a href="dashboard.php" class="flex items-center space-x-2 hover:text-indigo-300 focus:text-indigo-300">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li>
                    <a href="add_user.php" class="flex items-center space-x-2 hover:text-indigo-300 focus:text-indigo-300">
                        <i class="fas fa-user-plus"></i>
                        <span>Add User</span>
                    </a>
                </li>
                <li>
                    <a href="post_meeting.php" class="flex items-center space-x-2 hover:text-indigo-300 focus:text-indigo-300">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Post Meeting</span>
                    </a>
                </li>
                <li>
                    <a href="meetings.php" class="flex items-center space-x-2 hover:text-indigo-300 focus:text-indigo-300 font-bold">
                        <i class="fas fa-list"></i>
                        <span>Meetings List</span>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="logout.php" class="flex items-center space-x-2 hover:text-indigo-300 focus:text-indigo-300">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
        <!-- Header -->
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </h1>
                <button class="md:hidden text-indigo-700 hover:text-indigo-900 focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </header>

        <!-- Meetings List -->
        <section>
            <h2 class="text-xl font-bold text-gray-800 mb-4">Meetings List</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg shadow-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Photos</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audio</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($meetings)): ?>
                            <tr>
                                <td colspan="7" class="py-4 px-6 text-center text-gray-500">No meetings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($meetings as $meeting): ?>
                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                    <td class="py-4 px-6 text-sm text-gray-700"><?php echo htmlspecialchars($meeting['id']); ?></td>
                                    <td class="py-4 px-6 text-sm text-gray-700"><?php echo htmlspecialchars($meeting['title']); ?></td>
                                    <td class="py-4 px-6 text-sm text-gray-700"><?php echo htmlspecialchars($meeting['meeting_date']); ?></td>
                                    <td class="py-4 px-6 text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($meeting['description'])); ?></td>
                                    <td class="py-4 px-6">
                                        <?php if (!empty($meeting['photo_urls'])): ?>
                                            <?php 
                                                // Split the photo URLs and take only the first one
                                                $photos = explode(',', $meeting['photo_urls']);
                                                $first_photo = htmlspecialchars($photos[0]); // Get the first photo
                                            ?>
                                            <img src="<?php echo $first_photo; ?>" alt="Meeting Photo" class="single-photo">
                                        <?php else: ?>
                                            <span class="text-gray-500">No Photos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($meeting['mp3_url']): ?>
                                            <audio controls class="w-full mt-2 rounded-md overflow-hidden bg-gray-100">
                                                <source src="<?php echo htmlspecialchars($meeting['mp3_url']); ?>" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>
                                        <?php else: ?>
                                            <span class="text-gray-500">No Audio</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-sm text-gray-700 space-x-2">
                                        <a href="view_meeting.php?id=<?php echo $meeting['id']; ?>" class="text-blue-600 hover:text-blue-800">View</a>
                                        <a href="edit_meeting.php?id=<?php echo $meeting['id']; ?>" class="text-green-600 hover:text-green-800">Edit</a>
                                        <a href="delete_meeting.php?id=<?php echo $meeting['id']; ?>" class="text-red-600 hover:text-red-800" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>