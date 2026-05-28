<?php
// ===============================================================
// 1. ‡∏Å‡∏≤‡∏£‡∏Å‡∏≥‡∏´‡∏‡∏‡∏‡π‡∏≤‡∏Å‡∏≤‡∏£‡π‡∏‡∏∑‡π‡∏≠‡∏°‡∏‡π‡∏≠ DATABASE (DATABASE CONNECTION CONFIGURATION)
// ===============================================================
$servername = 'localhost';
$dbname     = 'samann1_admin_panel';
$username = 'root';
$password   = '';

// ===============================================================
// 2. ‡π‡∏‡π‡∏ PHP ‡∏™‡∏≥‡∏´‡∏£‡∏±‡∏‡∏‡∏∂‡∏‡∏‡π‡∏≠‡∏°‡∏π‡∏• (PHP DATA FETCHING LOGIC)
// ===============================================================
$users = [];
$errorMessage = "";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- ‡∏Å‡∏≤‡∏£‡πÅ‡∏Å‡π‡π‡∏‡∏‡∏µ‡π‡∏™‡∏≥‡∏‡∏±‡∏ç ---
    // ‡π‡∏‡∏•‡∏µ‡π‡∏¢‡∏ SELECT ‡π‡∏´‡π‡∏‡∏£‡∏‡∏Å‡∏±‡∏‡∏‡∏∑‡π‡∏≠‡∏‡∏≠‡∏•‡∏±‡∏°‡∏‡π‡∏‡∏≠‡∏‡∏‡∏∏‡∏: username, email, created_at, role, image_url
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at, image_url FROM users ORDER BY id DESC");
    $stmt->execute();

    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    $users = $stmt->fetchAll();

} catch(PDOException $e) {
    $errorMessage = "‡π‡∏Å‡∏¥‡∏‡∏‡π‡∏≠‡∏‡∏¥‡∏‡∏‡∏•‡∏≤‡∏ (Error): " . $e->getMessage();
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>·····∏······¢·······æ····∂··</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- =============================================================== -->
    <!-- 3. ‡π‡∏‡π‡∏ CSS ‡∏™‡∏≥‡∏´‡∏£‡∏±‡∏‡∏Å‡∏≤‡∏£‡∏≠‡∏≠‡∏Å‡πÅ‡∏‡∏ UI (CSS STYLING)                      -->
    <!-- =============================================================== -->
    <style>
        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: #f4f7f6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header h1 {
            color: #0056b3;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .user-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 25px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-body {
            padding: 20px;
            text-align: center;
        }
        .avatar-container {
            margin-bottom: 15px;
        }
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #007bff;
            object-fit: cover;
            background-color: #e9ecef;
        }
        .card-username {
            font-size: 1.5em;
            font-weight: 700;
            color: #0056b3;
            margin: 10px 0 5px 0;
        }
        .card-role {
            display: inline-block;
            background-color: #e7f1ff;
            color: #0056b3;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .card-info {
            text-align: left;
            font-size: 0.95em;
            color: #555;
        }
        .card-info p {
            margin: 0 0 10px;
            display: flex;
            align-items: center;
        }
        .card-info i {
            color: #007bff;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .card-info a {
            color: #333;
            text-decoration: none;
            word-break: break-all;
        }
        .card-info a:hover {
            color: #0056b3;
            text-decoration: underline;
        }
        .message-box {
            text-align: center;
            padding: 40px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            font-size: 1.2em;
            color: #777;
        }
        .error-message {
            color: #d9534f;
            font-family: monospace;
            text-align: left;
            background-color: #f2dede;
            border: 1px solid #ebccd1;
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <h1>·····∏······¢·······æ····∂·· (User List)</h1>
        </header>

        <!-- =============================================================== -->
        <!-- 4. ‡π‡∏‡π‡∏ HTML ‡∏™‡∏≥‡∏´‡∏£‡∏±‡∏‡πÅ‡∏™‡∏‡∏‡∏‡π‡∏≠‡∏°‡∏π‡∏• (HTML DATA DISPLAY)         -->
        <!-- =============================================================== -->
        <main>
            <?php if (!empty($errorMessage)): ?>
                <div class="message-box error-message">
                    <strong>Error:</strong>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
                
            <?php elseif (count($users) > 0): ?>
                <div class="user-cards">
                    <?php foreach ($users as $user): ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="avatar-container">
                                    <!-- ‡∏‡∏£‡∏ß‡∏‡∏™‡∏≠‡∏‡∏ß‡π‡∏≤‡∏°‡∏µ image_url ‡∏´‡∏£‡∏∑‡∏≠‡π‡∏°‡π ‡∏‡π‡∏≤‡π‡∏°‡π‡∏°‡∏µ‡π‡∏´‡π‡π‡∏‡π‡∏£‡∏π‡∏ Default -->
                                    <?php 
                                        $imageUrl = !empty($user['image_url']) ? htmlspecialchars($user['image_url']) : 'https://via.placeholder.com/100'; // URL ‡∏£‡∏π‡∏‡∏†‡∏≤‡∏‡∏™‡∏≥‡∏£‡∏≠‡∏
                                    ?>
                                    <img src="<?php echo $imageUrl; ?>" alt="User Avatar" class="avatar">
                                </div>
                                <!-- --- ‡∏Å‡∏≤‡∏£‡πÅ‡∏Å‡π‡π‡∏ --- ‡π‡∏‡π 'username' ‡πÅ‡∏‡∏ 'firstname' + 'lastname' -->
                                <h3 class="card-username"><?php echo htmlspecialchars($user['username']); ?></h3>
                                
                                <!-- ‡πÅ‡∏™‡∏‡∏ 'role' -->
                                <p class="card-role"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                                
                                <div class="card-info">
                                    <p><i class="fas fa-envelope"></i> <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a></p>
                                    <!-- --- ‡∏Å‡∏≤‡∏£‡πÅ‡∏Å‡π‡π‡∏ --- ‡π‡∏‡π 'created_at' ‡πÅ‡∏‡∏ 'registration_date' -->
                                    <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('d F Y', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
            <?php else: ?>
                <div class="message-box">
                    <p>··∑···∂····∂···∑·····ê··¢·······æ····∂·····°·æ···Å·</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

</body>
</html>
