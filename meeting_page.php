<?php
session_start();
include 'admin/includes/db.php'; // Database connection
// $conn = include 'admin/includes/db.php'; // This line might be redundant

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$groupedMeetings = []; // Initialize an array to hold meetings grouped by category

try {
    // MODIFIED: Select the new 'category' column and order by category first
    $stmt = $conn->prepare("
        SELECT id, title, meeting_date, category 
        FROM meetings 
        ORDER BY category, meeting_date DESC
    ");
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NEW: Group the results by category
    foreach ($meetings as $meeting) {
        $category = !empty($meeting['category']) ? $meeting['category'] : 'General';
        $groupedMeetings[$category][] = $meeting;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the meetings.");
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ជីកិច្ចប្រជុំ (Meetings List)</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&family=Kantumruy+Pro:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-color: #f8f9fa;
            --text-color: #212529;
            --card-bg: #ffffff;
            --border-color: #dee2e6;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.07);
            --border-radius: 0.5rem;
        }

        body {
            background-color: var(--background-color);
            font-family: 'Poppins', 'Kantumruy Pro', sans-serif;
            color: var(--text-color);
            padding-top: 20px;
            padding-bottom: 40px;
        }

        .container {
            max-width: 960px;
        }

        .navbar {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            padding: 1rem;
            margin-bottom: 2.5rem;
        }
        .navbar-brand {
            font-weight: 600;
            color: var(--primary-color) !important;
        }

        h2 {
            font-weight: 600;
            margin-bottom: 2rem;
            text-align: center;
        }

        .search-bar {
            position: relative;
            margin-bottom: 2.5rem;
        }
        .search-bar .form-control {
            height: 52px;
            padding-left: 45px;
            border-radius: var(--border-radius);
        }
        .search-bar .fa-search {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary-color);
        }
        
        /* NEW STYLES for Category Groups */
        .meeting-group {
            margin-bottom: 2.5rem;
        }
        .category-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        .category-header .fa-folder-open {
            margin-right: 12px;
            color: var(--primary-color);
        }

        .meeting-list {
            list-style: none;
            padding: 0;
        }
        
        .meeting-item {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-left 0.2s ease;
            border-left: 4px solid #e9ecef;
        }
        .meeting-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary-color);
        }

        .meeting-info {
            display: flex;
            flex-direction: column;
        }

        .meeting-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        .meeting-date {
            font-size: 0.9rem;
            color: var(--secondary-color);
            display: flex;
            align-items: center;
        }
        .meeting-date .fa-calendar-alt {
             margin-right: 8px;
        }
        .meeting-arrow {
            color: #adb5bd;
            transition: color 0.2s ease;
        }
        .meeting-item:hover .meeting-arrow {
            color: var(--primary-color);
        }

        .no-data {
            text-align: center;
            color: var(--secondary-color);
            font-size: 1.1rem;
            margin-top: 3rem;
            padding: 2.5rem;
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px dashed var(--border-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php"><i class="fas fa-users-cog me-2"></i>HR App</a>
                <button onclick="history.back()" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>ត្រឡប់ក្រោយ</button>
            </div>
        </nav>

        <h2><i class="far fa-calendar-alt me-2"></i>បញ្ជីកិច្ចប្រជុំ</h2>

        <div class="search-bar">
            <i class="fa fa-search"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="ស្វែងរកតាមចំណងជើងកិច្ចប្រជុំ..."/>
        </div>

        <div id="meetingsContainer">
            <?php if (empty($groupedMeetings)): ?>
                <p class="no-data">
                    <i class="fas fa-info-circle mb-2" style="font-size: 1.5rem;"></i><br>
                    មិនមានទិន្នន័យកិច្ចប្រជុំទេ
                </p>
            <?php else: ?>
                <!-- NEW: Loop through each category (folder) -->
                <?php foreach ($groupedMeetings as $category => $meetingsInCategory): ?>
                    <div class="meeting-group">
                        <h4 class="category-header">
                            <i class="fas fa-folder-open"></i>
                            <?php echo htmlspecialchars($category); ?>
                        </h4>
                        <div class="meeting-list">
                            <!-- Loop through meetings inside this category -->
                            <?php foreach ($meetingsInCategory as $meeting): ?>
                                <a href="view_meeting_page.php?id=<?php echo htmlspecialchars($meeting['id']); ?>" 
                                   class="meeting-item" 
                                   data-title="<?php echo htmlspecialchars(strtolower($meeting['title'])); ?>">
                                    
                                    <div class="meeting-info">
                                        <span class="meeting-title"><?php echo htmlspecialchars($meeting['title']); ?></span>
                                        <span class="meeting-date">
                                            <i class="far fa-calendar-alt"></i><?php echo htmlspecialchars(date('d F Y', strtotime($meeting['meeting_date']))); ?>
                                        </span>
                                    </div>
                                    <div class="meeting-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODIFIED: JavaScript for search to handle groups -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const meetingGroups = document.querySelectorAll('.meeting-group');

            searchInput.addEventListener('input', (event) => {
                const searchTerm = event.target.value.toLowerCase();

                meetingGroups.forEach(group => {
                    const meetingsInGroup = group.querySelectorAll('.meeting-item');
                    let visibleMeetingsCount = 0;

                    // First, filter individual meetings in this group
                    meetingsInGroup.forEach(item => {
                        const title = item.getAttribute('data-title');
                        if (title.includes(searchTerm)) {
                            item.style.display = 'flex';
                            visibleMeetingsCount++;
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    // Then, hide the entire group if no meetings are visible
                    if (visibleMeetingsCount > 0) {
                        group.style.display = 'block';
                    } else {
                        group.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>