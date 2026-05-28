<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
// Note: You included ../system/db.php twice. I'm removing the redundant one.
$conn = include 'includes/db.php';

$error_message = '';
$success_message = '';

// Get the meeting ID from the query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: meetings.php");
    exit();
}
$meeting_id = $_GET['id'];

// NEW: Fetch existing categories to populate the datalist for suggestions
$existing_categories = [];
try {
    $stmt_cat = $conn->query("SELECT DISTINCT category FROM meetings WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $existing_categories = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log("Could not fetch categories: " . $e->getMessage());
}


// Fetch the meeting details from the database
try {
    // MODIFIED: Select the 'category' column as well
    $stmt = $conn->prepare("
        SELECT id, title, category, meeting_date, description, mp3_url 
        FROM meetings 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        // If no meeting is found, redirect
        header("Location: meetings.php");
        exit();
    }

    // Fetch all photos associated with the meeting (this part is unchanged)
    $stmt_photos = $conn->prepare("
        SELECT photo_url 
        FROM meeting_photos 
        WHERE meeting_id = :meeting_id
    ");
    $stmt_photos->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
    $stmt_photos->execute();
    $photos = $stmt_photos->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while fetching the meeting details.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get all form data
    $title = trim($_POST['title']);
    $category = trim($_POST['category']); // NEW: Get the category from the form
    $date = trim($_POST['date']);
    $description = trim($_POST['description']);
    $audio_url = trim($_POST['audio_url']);
    $photo_urls = isset($_POST['photo_urls']) ? array_filter($_POST['photo_urls']) : [];

    // MODIFIED: Added category check to the validation
    if (!empty($title) && !empty($date) && !empty($description) && !empty($category)) {
        try {
            $conn->beginTransaction();

            // MODIFIED: Update the 'category' field in the UPDATE statement
            $stmt = $conn->prepare("
                UPDATE meetings 
                SET title = :title, category = :category, meeting_date = :meeting_date, description = :description, mp3_url = :mp3_url 
                WHERE id = :id
            ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category', $category); // Bind the new category
            $stmt->bindParam(':meeting_date', $date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':mp3_url', $audio_url);
            $stmt->bindParam(':id', $meeting_id, PDO::PARAM_INT);
            $stmt->execute();

            // The logic for deleting and re-inserting photos remains the same
            $stmt_delete_photos = $conn->prepare("DELETE FROM meeting_photos WHERE meeting_id = :meeting_id");
            $stmt_delete_photos->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
            $stmt_delete_photos->execute();

            if (!empty($photo_urls)) {
                $stmt_insert_photo = $conn->prepare("INSERT INTO meeting_photos (meeting_id, photo_url) VALUES (:meeting_id, :photo_url)");
                foreach ($photo_urls as $photo_url) {
                    if (!filter_var($photo_url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid photo URL: $photo_url");
                    }
                    $stmt_insert_photo->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
                    $stmt_insert_photo->bindParam(':photo_url', $photo_url);
                    $stmt_insert_photo->execute();
                }
            }
            
            $conn->commit();
            
            $success_message = "Meeting updated successfully!";
            // Refresh the page to show the success message and then redirect
            header("Refresh: 2; url=meetings.php"); 
            // We don't exit here so the success message can be displayed before redirecting.

        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error: " . $e->getMessage());
            $error_message = "An error occurred while updating the meeting.";
        }
    } else {
        $error_message = "Please fill in all required fields, including the category.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management - Edit Meeting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 flex h-screen font-sans">
    <!-- Sidebar remains the same -->
    <aside class="w-64 bg-indigo-700 text-white p-6 hidden md:block">
        <h2 class="text-xl font-bold mb-6">HR Panel</h2>
        <ul class="space-y-4">
            <li><a href="dashboard.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <li><a href="add_user.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-user-plus"></i><span>Add User</span></a></li>
            <li><a href="post_meeting.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-calendar-plus"></i><span>Post Meeting</span></a></li>
            <li><a href="meetings.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-list"></i><span>Meetings List</span></a></li>
            <?php endif; ?>
            <li><a href="../auth/logout.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-y-auto">
        <header class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                </h1>
            </div>
        </header>

        <section class="bg-white p-6 rounded-lg shadow-md max-w-3xl mx-auto">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Edit Meeting</h2>
            
            <?php if ($error_message): ?>
                <div role="alert" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-4"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div role="alert" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-md mb-4"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <!-- Check if a meeting was actually fetched before displaying the form -->
            <?php if ($meeting): ?>
            <form method="POST" action="edit_meeting.php?id=<?php echo $meeting_id; ?>" class="space-y-4">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Meeting Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($meeting['title']); ?>" class="mt-1 block w-full px-3 py-2 border rounded-md" required>
                </div>

                <!-- *** NEW CATEGORY FIELD FOR EDITING *** -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Meeting Folder / Category</label>
                    <input type="text" id="category" name="category" list="category-list" value="<?php echo htmlspecialchars($meeting['category']); ?>" required placeholder="Type or select a category" class="mt-1 block w-full px-3 py-2 border rounded-md">
                    <datalist id="category-list">
                        <?php foreach ($existing_categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <p class="text-xs text-gray-500 mt-1">You can move this meeting to an existing folder or create a new one by typing a new name.</p>
                </div>

                <div>
                    <label for="date" class="block text-sm font-medium text-gray-700">Meeting Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($meeting['meeting_date']); ?>" class="mt-1 block w-full px-3 py-2 border rounded-md" required>
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea id="description" name="description" rows="4" class="mt-1 block w-full px-3 py-2 border rounded-md" required><?php echo htmlspecialchars($meeting['description']); ?></textarea>
                </div>

                <div>
                    <label for="audio_url" class="block text-sm font-medium text-gray-700">Audio URL</label>
                    <input type="url" id="audio_url" name="audio_url" value="<?php echo htmlspecialchars($meeting['mp3_url']); ?>" class="mt-1 block w-full px-3 py-2 border rounded-md" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Photos</label>
                    <div id="photo-inputs" class="space-y-2">
                        <?php if (empty($photos)): ?>
                            <!-- Show one empty input if no photos exist -->
                            <div class="flex items-center space-x-2">
                                <input type="url" name="photo_urls[]" placeholder="Enter photo URL" class="mt-1 block w-full px-3 py-2 border rounded-md">
                                <button type="button" onclick="addPhotoInput()" class="text-indigo-700 hover:text-indigo-900"><i class="fas fa-plus"></i></button>
                            </div>
                        <?php else: ?>
                            <?php foreach ($photos as $photo_url): ?>
                                <div class="flex items-center space-x-2">
                                    <input type="url" name="photo_urls[]" value="<?php echo htmlspecialchars($photo_url); ?>" class="mt-1 block w-full px-3 py-2 border rounded-md">
                                    <button type="button" onclick="removePhotoInput(this)" class="text-red-700 hover:text-red-900"><i class="fas fa-minus"></i></button>
                                </div>
                            <?php endforeach; ?>
                             <!-- Add button at the end -->
                            <button type="button" onclick="addPhotoInput()" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800"><i class="fas fa-plus-circle mr-1"></i>Add Another Photo</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Update Meeting</button>
                </div>
            </form>
            <?php endif; ?>
        </section>
    </main>

    <script>
        function addPhotoInput() {
            const container = document.getElementById('photo-inputs');
            // Remove the "Add Another" button before adding a new input if it exists
            const addButton = container.querySelector('button[onclick="addPhotoInput()"]');
            if(addButton) addButton.remove();
            
            const newDiv = document.createElement('div');
            newDiv.classList.add('flex', 'items-center', 'space-x-2');
            newDiv.innerHTML = `
                <input type="url" name="photo_urls[]" placeholder="Enter photo URL" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <button type="button" onclick="removePhotoInput(this)" class="text-red-700 hover:text-red-900"><i class="fas fa-minus"></i></button>
            `;
            container.appendChild(newDiv);
            // Re-add the "Add Another" button at the very end
            container.appendChild(createAddButton());
        }

        function createAddButton() {
            const button = document.createElement('button');
            button.type = 'button';
            button.onclick = addPhotoInput;
            button.className = 'mt-2 text-sm text-indigo-600 hover:text-indigo-800';
            button.innerHTML = '<i class="fas fa-plus-circle mr-1"></i>Add Another Photo';
            return button;
        }

        function removePhotoInput(button) {
            button.parentElement.remove();
        }
    </script>
</body>
</html>