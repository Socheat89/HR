<?php

session_start();


include '../admin/includes/db.php';
$conn = include '../admin/includes/db.php';

// Get the meeting ID from the query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: meetings.php");
    exit();
}
$meeting_id = $_GET['id'];

// Fetch the meeting details from the database
try {
    $stmt = $conn->prepare("
        SELECT id, title, meeting_date, description, mp3_url 
        FROM meetings 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$meeting) {
        header("Location: meetings.php");
        exit();
    }

    // Fetch all photos associated with the meeting
    $stmt = $conn->prepare("
        SELECT photo_url 
        FROM meeting_photos 
        WHERE meeting_id = :meeting_id
    ");
    $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while fetching the meeting details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>HR Management - View Meeting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(90deg, #6b7280 0%, #4b5563 100%);
            padding: 2rem;
            color: white;
            position: relative;
        }
        .header-title {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .content {
            padding: 2rem;
        }
        .detail-item {
            margin-bottom: 1.5rem;
        }
        .label {
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .value {
            color: #1f2937;
            font-size: 1.1rem;
            line-height: 1.5;
        }
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .photo-item {
            position: relative;
            overflow: hidden;
            border-radius: 0.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .photo-item:hover {
            transform: scale(1.03);
        }
        .photo-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .audio-player {
            width: 100%;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            background: #f3f4f6;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: #4b5563;
            color: white;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background: #374151;
            transform: translateX(-2px);
        }
        .error-card {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            color: #991b1b;
        }
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            .header-title {
                font-size: 1.5rem;
            }
            .photo-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1 class="header-title">
                    <?php echo htmlspecialchars($meeting['title']); ?>
                </h1>
                <p class="text-sm opacity-80 mt-1">
                    <?php echo htmlspecialchars($_SESSION['username']); ?>'s Meeting Record
                </p>
            </div>
            
            <div class="content">
                <?php if (isset($error_message)): ?>
                    <div class="error-card">
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php else: ?>
                    <div class="detail-item">
                        <div class="label">Meeting Date</div>
                        <div class="value"><?php echo htmlspecialchars($meeting['meeting_date']); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="label">Description</div>
                        <div class="value"><?php echo nl2br(htmlspecialchars($meeting['description'])); ?></div>
                    </div>

                    <div class="detail-item">
                        <div class="label">Photos</div>
                        <?php if (!empty($photos)): ?>
                            <div class="photo-grid">
                                <?php foreach ($photos as $photo_url): ?>
                                    <div class="photo-item">
                                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="Meeting Photo">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="value text-gray-500">No photos available</div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-item">
                        <div class="label">Audio Recording</div>
                        <?php if ($meeting['mp3_url']): ?>
                            <audio controls class="audio-player">
                                <source src="<?php echo htmlspecialchars($meeting['mp3_url']); ?>" type="audio/mpeg">
                                Your browser does not support the audio element.
                            </audio>
                        <?php else: ?>
                            <div class="value text-gray-500">No audio available</div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-6">
                        <a href="meetings.php" class="back-btn">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Meetings
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
