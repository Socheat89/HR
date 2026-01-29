<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetch projects based on user role
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    if ($role === 'admin') {
        $stmt = $pdo->query("
            SELECT p.*, u.username 
            FROM projects p 
            JOIN users u ON p.user_id = u.id 
            ORDER BY u.username, p.start_date ASC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT p.*, u.username 
            FROM projects p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.user_id = ? 
            ORDER BY p.start_date ASC
        ");
        $stmt->execute([$user_id]);
    }
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $timeline = [];
    foreach ($projects as $project) {
        $timeline[$project['username']][] = $project;
    }
} catch (PDOException $e) {
    echo "Error fetching projects: " . $e->getMessage();
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['project_id'], $_POST['new_status']) && $role !== 'admin') {
    $project_id = $_POST['project_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$new_status, $project_id, $user_id]);
    header("Location: index.php");
    exit();
}

// Calculate progress percentage
function calculateProgress($start_date, $end_date) {
    $today = strtotime('2025-04-01'); // Fixed syntax error here
    $start = strtotime($start_date);
    $end = strtotime($end_date);

    if ($today < $start) return 0;
    if ($today > $end) return 100;

    $total_duration = $end - $start;
    $elapsed = $today - $start;
    return $total_duration > 0 ? round(($elapsed / $total_duration) * 100) : 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Project Timeline</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #e8f1f5, #d1e0e8);
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .timeline {
            max-width: 1000px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 30px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        }
        .user-section h2 {
            font-size: 2em;
            color: #2980b9;
            margin: 25px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2980b9;
        }
        .project {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .project:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }
        .project h3 {
            font-size: 1.6em;
            color: #e74c3c;
            margin: 0 0 12px;
        }
        .project p {
            font-size: 1.2em;
            margin: 8px 0;
            color: #555;
            display: flex;
            align-items: center;
        }
        .project p i {
            margin-right: 12px;
            color: #3498db;
            font-size: 1.3em;
        }
        .process-container {
            display: flex;
            align-items: center;
            gap: 25px;
            margin: 15px 0;
            position: relative;
        }
        .process-line {
            position: absolute;
            top: 50%;
            left: 50px;
            right: 50px;
            height: 4px;
            background: linear-gradient(90deg, #f1c40f, #3498db, #2ecc71);
            z-index: 0;
            animation: lineGlow 2s infinite alternate;
        }
        @keyframes lineGlow {
            0% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .process-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
            width: 33%;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
            color: #fff;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: #ccc;
        }
        .step-circle.active {
            transform: scale(1.3);
            box-shadow: 0 0 12px rgba(0, 0, 0, 0.2);
        }
        .step-pending.active { background: linear-gradient(45deg, #f1c40f, #f39c12); }
        .step-in_progress.active { background: linear-gradient(45deg, #3498db, #2980b9); }
        .step-completed.active { background: linear-gradient(45deg, #2ecc71, #27ae60); }
        .step-circle:hover {
            transform: scale(1.1);
        }
        .step-text {
            font-size: 1em;
            margin-top: 8px;
            color: #777;
            transition: color 0.3s ease;
        }
        .step-text.active {
            font-weight: bold;
            color: #333;
        }
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e0e0e0;
            border-radius: 6px;
            margin: 15px 0;
            overflow: hidden;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3498db, #9b59b6);
            transition: width 0.5s ease;
        }
        .edit-form {
            margin-top: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }
        .edit-form select {
            padding: 10px;
            font-size: 1.1em;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f9f9f9;
            color: #333;
            flex: 1;
            transition: border-color 0.3s ease;
        }
        .edit-form select:focus {
            border-color: #3498db;
            outline: none;
        }
        .edit-form input[type="submit"] {
            padding: 10px 20px;
            font-size: 1.1em;
            border: none;
            border-radius: 6px;
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .edit-form input[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
        }
        .no-projects {
            text-align: center;
            font-size: 1.5em;
            color: #888;
            padding: 30px;
        }
        .logout {
            text-align: center;
            margin-top: 40px;
        }
        .logout a {
            font-size: 1.2em;
            color: #fff;
            text-decoration: none;
            padding: 12px 25px;
            background: linear-gradient(45deg, #2980b9, #3498db);
            border-radius: 8px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .logout a:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(41, 128, 185, 0.3);
        }
        @media (max-width: 600px) {
            .timeline { padding: 15px; }
            h1 { font-size: 2em; }
            .user-section h2 { font-size: 1.6em; }
            .project h3 { font-size: 1.4em; }
            .project p { font-size: 1em; }
            .process-container { flex-direction: column; gap: 20px; }
            .process-line { display: none; }
            .process-step { width: 100%; flex-direction: row; justify-content: space-between; align-items: center; }
            .step-circle { width: 35px; height: 35px; font-size: 1em; }
            .edit-form { flex-direction: column; gap: 10px; }
            .edit-form select, .edit-form input[type="submit"] { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="timeline">
        <h1>បញ្ជីគម្រោង</h1>
        <?php if (empty($timeline)): ?>
            <p class="no-projects">មិនមានគម្រោងទេ</p>
        <?php else: ?>
            <?php foreach ($timeline as $username => $user_projects): ?>
                <div class="user-section">
                    <h2><?php echo htmlspecialchars($username); ?></h2>
                    <?php foreach ($user_projects as $project): ?>
                        <div class="project">
                            <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                            <p><?php echo htmlspecialchars($project['description']); ?></p>
                            <p><i class="fas fa-calendar-alt"></i>ចាប់ផ្តើម: <?php echo $project['start_date']; ?></p>
                            <p><i class="fas fa-calendar-check"></i>បញ្ចប់: <?php echo $project['end_date']; ?></p>
                            <p><i class="fas fa-tasks"></i>ដំណើរការ:</p>
                            <div class="process-container">
                                <div class="process-line"></div>
                                <div class="process-step">
                                    <span class="step-circle step-pending <?php echo $project['status'] === 'pending' ? 'active' : ''; ?>">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <span class="step-text <?php echo $project['status'] === 'pending' ? 'active' : ''; ?>">Pending</span>
                                </div>
                                <div class="process-step">
                                    <span class="step-circle step-in_progress <?php echo $project['status'] === 'in_progress' ? 'active' : ''; ?>">
                                        <i class="fas fa-play"></i>
                                    </span>
                                    <span class="step-text <?php echo $project['status'] === 'in_progress' ? 'active' : ''; ?>">In Progress</span>
                                </div>
                                <div class="process-step">
                                    <span class="step-circle step-completed <?php echo $project['status'] === 'completed' ? 'active' : ''; ?>">
                                        <i class="fas fa-check"></i>
                                    </span>
                                    <span class="step-text <?php echo $project['status'] === 'completed' ? 'active' : ''; ?>">Completed</span>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo calculateProgress($project['start_date'], $project['end_date']); ?>%;"></div>
                            </div>
                            <?php if ($role !== 'admin' && $project['user_id'] == $user_id): ?>
                                <div class="edit-form">
                                    <form method="POST" action="">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                        <select name="new_status" required>
                                            <option value="pending" <?php echo $project['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="in_progress" <?php echo $project['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <input type="submit" value="កែស្ថានភាព">
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="logout">
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> ចាកចេញ</a>
        </div>
    </div>
</body>
</html>