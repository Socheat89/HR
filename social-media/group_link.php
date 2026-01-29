<?php
// ១. ភ្ជាប់ទៅកាន់មូលដ្ឋានទិន្នន័យ (Database)
include 'db_connect.php'; 

// ២. ទាញយកទិន្នន័យ Social Links ដោយតម្រៀបតាមលំដាប់
$links_result = $conn->query("SELECT * FROM social_links ORDER BY id ASC");

// ៣. (ថ្មី) ទាញយកព័ត៌មាន Profile
// សន្មត់ថាអ្នកមានតារាងឈ្មោះ 'site_profile' ដែលមាន id=1 សម្រាប់เก็บข้อมูล Profile
$profile_result = $conn->query("SELECT * FROM site_profile WHERE id = 1");
$profile = $profile_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile['name'] ?? 'Social Links'); ?> - Links</title>
    
    <style>
        /* ប្រើ Font ខ្មែរ */
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;700&display=swap');

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-image: url(https://media2.giphy.com/media/v1.Y2lkPTc5MGI3NjExMHVpODFrYm12ZW55cTBsOHNkaDVuYmprMDVrbGZsdWk3dnFhZ3NyZyZlcD12MV9pbnRlcm5hbF9naWZfYnlfaWQmY3Q9Zw/p6EDJOPtyd0DJSuDef/giphy.gif);
            color: #333;
            background-size: cover;
            background-attachment: fixed;
            background-repeat: no-repeat;
            display: flex;
            justify-content: center;
            align-items: flex-start; /* ប្តូរទៅជា flex-start ដើម្បីឱ្យមាតិកាចាប់ផ្តើមពីខាងលើ */
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        /* ផ្នែករបស់ Profile Card */
        .profile-card {
            background: #fff;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 4px solid #eee;
        }

        .profile-card h1 {
            margin: 10px 0 5px;
            font-size: 1.8em;
            font-weight: 700;
        }

        .profile-card p {
            font-size: 1em;
            color: #777;
            line-height: 1.5;
        }
        
        /* ផ្នែករបស់ Link Card */
        .link-card {
            background: #fff;
            margin-bottom: 15px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.07);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .link-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .link-info {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            font-size: 1.1em;
        }

        /* អាប់ដេត៖ ប្តូរពី Icon ទៅជារូបភាព */
        .link-icon {
            width: 40px;
            height: 40px;
            object-fit: contain; /* ដើម្បីឱ្យរូបភាពសមាមាត្រพอดีในกรอบ */
            margin-right: 15px;
            border-radius: 8px; /* ធ្វើឱ្យขอบมนสวยงาม */
        }

        .qr-code-container {
            width: 70px; /* បង្រួមទំហំបន្តិច */
            height: 70px;
            border: 1px solid #eee;
            padding: 4px;
            border-radius: 8px;
            background: #fff;
        }
        
        /* JavaScript នឹងបង្កើត img tag នៅខាងក្នុង div នេះ */
        .qr-code-container img {
            width: 100% !important;
            height: 100% !important;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="profile-card">
            <img src="<?php echo htmlspecialchars($profile['profile_image_url'] ?? 'https://via.placeholder.com/150'); ?>" alt="រូបភាព Profile" class="profile-pic">
            <h1><?php echo htmlspecialchars($profile['name'] ?? 'ដាក់ឈ្មោះរបស់អ្នក'); ?></h1>
            <p><?php echo htmlspecialchars($profile['description'] ?? 'ដាក់ការពិពណ៌នាខ្លីៗអំពីអ្នក'); ?></p>
        </div>

        <div class="social-links">
            <?php while($row = $links_result->fetch_assoc()): ?>
                <div class="link-card">
                    <a href="<?php echo htmlspecialchars($row['url']); ?>" target="_blank" class="link-info">
                        <img src="<?php echo htmlspecialchars($row['icon_class']); ?>" alt="<?php echo htmlspecialchars($row['name']); ?> icon" class="link-icon">
                        <span><?php echo htmlspecialchars($row['name']); ?></span>
                    </a>
                    
                    <div class="qr-code-container" id="qrcode-<?php echo $row['id']; ?>" data-url="<?php echo htmlspecialchars($row['url']); ?>"></div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const qrCodeContainers = document.querySelectorAll('.qr-code-container');

        qrCodeContainers.forEach(container => {
            // ទាញយក URL ពី data-attribute ដែលជាវិធីសាស្ត្រទំនើបជាង
            const url = container.dataset.url;
            
            if (url) {
                new QRCode(container, {
                    text: url,
                    width: 70,
                    height: 70,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        });
    });
    </script>
</body>
</html>
<?php 
// បិទការតភ្ជាប់ទៅ Database
$conn->close(); 
?>