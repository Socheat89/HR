<?php
include 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

// Create table for PDF posts if it doesn't exist
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS pdf_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_id INT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}

// Handle PDF upload
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    
    if (empty($title)) $errors[] = "Title is required";
    
    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_types = ['application/pdf'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($_FILES['pdf_file']['type'], $allowed_types)) {
            $errors[] = "Only PDF files are allowed";
        }
        if ($_FILES['pdf_file']['size'] > $max_size) {
            $errors[] = "File too large. Maximum 10MB allowed";
        }
        
        if (empty($errors)) {
            $upload_dir = 'uploads/pdfs/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_path = $upload_dir . uniqid() . '_' . $_FILES['pdf_file']['name'];
            
            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $file_path)) {
                try {
                    $user_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("INSERT INTO pdf_posts (title, file_path, user_id) 
                        VALUES (?, ?, ?)");
                    $stmt->execute([$title, $file_path, $user_id]);
                    $success = "PDF posted successfully";
                } catch (PDOException $e) {
                    error_log("Database error: " . $e->getMessage());
                    $errors[] = "Failed to save PDF to database";
                    unlink($file_path); // Remove file if DB insert fails
                }
            } else {
                $errors[] = "Failed to upload PDF file";
            }
        }
    } else {
        $errors[] = "Please select a PDF file";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post PDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e1e2f 0%, #2a2a4a 100%);
            font-family: 'Poppins', sans-serif;
            color: #e0e0e0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        .form-card {
            background: linear-gradient(145deg, #2a2a4a, #1e1e2f);
            border: 1px solid #ffd700;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .form-card input[type="text"],
        .form-card input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            background: #3a3a5a;
            border: 1px solid #ffd700;
            border-radius: 5px;
            color: #e0e0e0;
        }
        .submit-btn {
            background: linear-gradient(145deg, #ffd700, #d4af37);
            color: #1e1e2f;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .submit-btn:hover {
            background: linear-gradient(145deg, #d4af37, #ffd700);
            transform: scale(1.05);
        }
        .error { color: #ff6b6b; margin-bottom: 1rem; }
        .success { color: #ffd700; margin-bottom: 1rem; }
        .back-link {
            color: #ffd700;
            margin-bottom: 1rem;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .back-link:hover {
            transform: translateX(-5px);
            text-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left mr-2"></i>Back to Dashboard</a>
        <h1 class="text-3xl font-bold text-yellow-500 mb-6">Post PDF Document</h1>
        
        <div class="form-card">
            <?php 
            if ($success) echo "<p class='success'>$success</p>";
            foreach ($errors as $error) echo "<p class='error'>$error</p>";
            ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="text" name="title" placeholder="PDF Title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" required>
                <input type="file" name="pdf_file" accept=".pdf" required>
                <button type="submit" class="submit-btn">Post PDF</button>
            </form>
        </div>
    </div>
</body>
</html>