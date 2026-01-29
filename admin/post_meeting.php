<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$conn = include 'includes/db.php';

$error_message = '';
$success_message = '';

// NEW: Fetch existing categories to populate the dropdown
$existing_categories = [];
try {
    $stmt = $conn->query("SELECT DISTINCT category FROM meetings WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $existing_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // It's okay if this fails, we can still show the input field.
    error_log("Could not fetch categories: " . $e->getMessage());
}


// Notification function
function notify_all_staff($conn, $message) {
    // This function remains the same
    $stmt = $conn->query("SELECT id FROM users WHERE role = 'staff'");
    $staff_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
    foreach ($staff_ids as $user_id) {
        $stmt->execute([':user_id' => $user_id, ':message' => $message]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $date = trim($_POST['date']);
    // NEW: Get the category from the form
    $category = trim($_POST['category']); 
    $description = trim($_POST['description']);
    $audio_url = trim($_POST['audio_url']);
    $photo_urls = isset($_POST['photo_urls']) ? array_filter($_POST['photo_urls']) : [];

    // MODIFIED: Added category check
    if (!empty($title) && !empty($date) && !empty($description) && !empty($category)) {
        try {
            if (!filter_var($audio_url, FILTER_VALIDATE_URL)) {
                throw new Exception("Invalid audio URL");
            }

            $conn->beginTransaction();

            // MODIFIED: Add 'category' to the INSERT statement
            $stmt = $conn->prepare("
                INSERT INTO meetings (title, category, meeting_date, description, mp3_url) 
                VALUES (:title, :category, :meeting_date, :description, :mp3_url)
            ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':category', $category); // Bind the new category parameter
            $stmt->bindParam(':meeting_date', $date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':mp3_url', $audio_url);
            $stmt->execute();

            $meeting_id = $conn->lastInsertId();

            // Insert photo URLs (this part remains the same)
            if (!empty($photo_urls)) {
                $stmt = $conn->prepare("
                    INSERT INTO meeting_photos (meeting_id, photo_url) 
                    VALUES (:meeting_id, :photo_url)
                ");
                foreach ($photo_urls as $photo_url) {
                    if (!filter_var($photo_url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid photo URL: $photo_url");
                    }
                    $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
                    $stmt->bindParam(':photo_url', $photo_url);
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            
            $message = "មានកិច្ចប្រជុំថ្មីក្នុង Folder '$category': $title នៅថ្ងៃទី $date";
            notify_all_staff($conn, $message);

            $success_message = "Meeting posted successfully in '$category' folder!";
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error: " . $e->getMessage());
            $error_message = "An error occurred while posting the meeting.";
        }
    } else {
        // MODIFIED: Updated error message
        $error_message = "Please fill in all required fields, including the category.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Management - Post Meeting</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        <li><a href="logout.php" class="flex items-center space-x-2 hover:text-indigo-300"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</aside>

<main class="flex-1 p-6 overflow-y-auto">
    <header class="mb-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">
                Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!
            </h1>
        </div>
    </header>

    <section class="bg-white p-6 rounded-lg shadow-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Post a New Meeting</h2>

        <!-- Error/Success messages remain the same -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes($success_message); ?>',
                    confirmButtonColor: '#6366f1'
                }).then(() => {
                    window.location.href = 'meetings.php';
                });
            </script>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Meeting Title</label>
                <input type="text" id="title" name="title" required class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring-indigo-500">
            </div>

            <!-- *** NEW CATEGORY FIELD *** -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700">Meeting Folder / Category</label>
                <!-- Datalist provides autocomplete suggestions but also allows new entries -->
                <input type="text" id="category" name="category" list="category-list" required placeholder="Type or select a category" class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring-indigo-500">
                <datalist id="category-list">
                    <?php foreach ($existing_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>">
                    <?php endforeach; ?>
                </datalist>
                <p class="text-xs text-gray-500 mt-1">You can select an existing folder or type a new name to create a new one.</p>
            </div>

            <div>
                <label for="date" class="block text-sm font-medium text-gray-700">Meeting Date</label>
                <input type="date" id="date" name="date" required class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring-indigo-500">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="description" name="description" rows="4" required class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring-indigo-500"></textarea>
            </div>
            
            <!-- Other fields remain the same -->
            <div>
                <label for="audio_url" class="block text-sm font-medium text-gray-700">Audio URL</label>
                <input type="url" id="audio_url" name="audio_url" required placeholder="e.g. Google Drive link" class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Photos</label>
                <div id="photo-inputs" class="space-y-2">
                    <div class="flex items-center space-x-2">
                        <input type="url" name="photo_urls[]" class="w-full px-3 py-2 border rounded-md shadow-sm">
                        <button type="button" onclick="addPhotoInput()" class="text-indigo-700 hover:text-indigo-900"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
            </div>

            <div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">
                    Post Meeting
                </button>
            </div>
        </form>
    </section>
</main>

<!-- JavaScript remains the same -->
<script>
function addPhotoInput() {
    const container = document.getElementById('photo-inputs');
    const newInput = document.createElement('div');
    newInput.classList.add('flex', 'items-center', 'space-x-2');
    newInput.innerHTML = `
        <input type="url" name="photo_urls[]" class="w-full px-3 py-2 border rounded-md shadow-sm">
        <button type="button" onclick="removePhotoInput(this)" class="text-red-700 hover:text-red-900"><i class="fas fa-minus"></i></button>
    `;
    container.appendChild(newInput);
}

function removePhotoInput(button) {
    button.parentElement.remove();
}
</script>

</body>
</html>