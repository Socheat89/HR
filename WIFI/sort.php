<?php
/**
 * Simple PHP URL Shortener with Custom Alias Support
 * -------------------------------------------------
 * Features:
 * - Shorten links automatically (random slug)
 * - Allow user to choose custom slug (if not taken)
 * - Redirect short links to long URLs
 * - Track clicks
 */

// ===================== CONFIG ===================== //
$host     = "localhost";
$dbname   = "samann1_facebook-bot";
$username = "samann1_facebook-bot";
$password = "facebook-bot!@#";

// -- ការកំណត់សម្រាប់បង្ហាញ Link --
// ដាក់ true ប្រសិនបើអ្នកចង់ឱ្យ Link បង្ហាញ domain របស់អ្នក (e.g., yoursite.com/xyz)
// ដាក់ false ប្រសិនបើអ្នកចង់បាន Link ទម្រង់ចាស់ (e.g., yoursite.com/index.php?r=xyz)
// **ចំណាំ:** ដើម្បីប្រើ `true` អ្នកត្រូវមាន .htaccess file សម្រាប់ URL rewriting។
$use_clean_urls = false; 


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // សម្រាប់ Production, អ្នកគួរតែលាក់ Error លម្អិត
    // error_log("DB Connection Failed: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការភ្ជាប់ទៅកាន់មូលដ្ឋានទិន្នន័យ។");
}

// ===================== CREATE TABLE (សម្រាប់ជាឯកសារយោង) ===================== //
/*
CREATE TABLE links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    url TEXT NOT NULL,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
*/

// ===================== FUNCTIONS ===================== //
function generateSlug($length = 6) {
    // បង្កើតតួអក្សរខ្លីៗແບບចៃដន្យ
    return substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, $length);
}

// ===================== HANDLE REDIRECT ===================== //
// កូដនេះនឹងដំណើរការនៅពេលមាន parameter `r` នៅក្នុង URL
if (isset($_GET['r'])) {
    $slug = trim($_GET['r']);
    $stmt = $pdo->prepare("SELECT url FROM links WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($link) {
        // បូកចំនួន Click មួយ
        $pdo->prepare("UPDATE links SET clicks = clicks + 1 WHERE slug = ?")->execute([$slug]);
        // បញ្ជូនទៅកាន់ URL ដើម
        header("Location: " . $link['url']);
        exit;
    } else {
        // បើរកមិនឃើញ slug
        http_response_code(404);
        die("រកមិនឃើញតំណខ្លីនេះទេ!");
    }
}

// ===================== HANDLE FORM SUBMIT ===================== //
$error = null;
$shortUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url  = trim($_POST['url'] ?? '');
    $slug = trim($_POST['custom_slug'] ?? '');

    // ពិនិត្យថា URL ត្រឹមត្រូវឬអត់
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = "URL ដែលអ្នកបានបញ្ចូលមិនត្រឹមត្រូវទេ។";
    } else {
        // បើអ្នកប្រើមិនបានដាក់ custom slug ទេ បង្កើតវាដោយស្វ័យប្រវត្តិ
        if (empty($slug)) {
            do {
                $slug = generateSlug();
                $stmt = $pdo->prepare("SELECT id FROM links WHERE slug = ?");
                $stmt->execute([$slug]);
            } while ($stmt->fetch()); // បង្កើតម្ដងទៀតបើ slug នោះមានគេប្រើហើយ
        } else {
            // ពិនិត្យមើល custom slug អាចប្រើបានឬអត់
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
                 $error = "តំណផ្ទាល់ខ្លួនអាចមានតែតួអក្សរ (a-z, A-Z), លេខ (0-9), សញ្ញា - និង _ ប៉ុណ្ណោះ។";
            } else {
                 // ពិនិត្យថា slug នេះមានគេប្រើហើយឬនៅ
                $stmt = $pdo->prepare("SELECT id FROM links WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    $error = "តំណផ្ទាល់ខ្លួន '" . htmlspecialchars($slug) . "' នេះត្រូវបានប្រើរួចហើយ!";
                }
            }
        }
        
        // បើគ្មាន Error ទេ បញ្ចូលទៅក្នុង Database
        if ($error === null) {
            $stmt = $pdo->prepare("INSERT INTO links (slug, url) VALUES (?, ?)");
            $stmt->execute([$slug, $url]);

            $baseUrl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
            if($use_clean_urls) {
                // សម្រាប់ Clean URL (e.g. yoursite.com/slug)
                // ត្រូវការ .htaccess
                 $shortUrl = dirname($baseUrl) . "/" . $slug;
            } else {
                // សម្រាប់ URL ធម្មតា (e.g. yoursite.com/index.php?r=slug)
                 $shortUrl = $baseUrl . "?r=" . $slug;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កម្មវិធីបង្រួម URL</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff;
            --primary-hover: #0056b3;
            --success-color: #28a745;
            --error-color: #dc3545;
            --light-bg: #f8f9fa;
            --dark-text: #343a40;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Kantumruy Pro', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        h1 {
            font-size: 2rem;
            margin-bottom: 25px;
            color: var(--dark-text);
        }
        
        h1 .icon {
            display: inline-block;
            transform: rotate(-45deg);
            font-size: 2.2rem;
            margin-right: 10px;
            color: var(--primary-color);
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            text-align: left;
            border: 1px solid transparent;
        }

        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .success a {
            color: #0d4a1b;
            font-weight: 600;
            text-decoration: none;
            word-break: break-all;
        }
        .success a:hover {
            text-decoration: underline;
        }
        .success strong {
            display: block;
            margin-bottom: 5px;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        input[type="url"],
        input[type="text"] {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: border-color 0.3s, box-shadow 0.3s;
            font-family: 'Poppins', sans-serif;
        }

        input[type="url"]:focus,
        input[type="text"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }
        
        input::placeholder {
            color: #999;
        }

        button {
            padding: 15px 20px;
            font-size: 1.1rem;
            font-weight: 500;
            background-color: var(--primary-color);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }

        button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

    </style>
</head>
<body>
    <div class="container">
        <h1><span class="icon">🔗</span>កម្មវិធីបង្រួម URL</h1>

        <?php if (!empty($error)) : ?>
            <p class="message error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <?php if (!empty($shortUrl)) : ?>
            <div class="message success">
                <strong>តំណរបស់អ្នករួចរាល់ហើយ៖</strong>
                <a href="<?php echo htmlspecialchars($shortUrl); ?>" target="_blank">
                    <?php echo htmlspecialchars($shortUrl); ?>
                </a>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="url" name="url" placeholder="បញ្ចូល URL វែងរបស់អ្នកនៅទីនេះ..." required>
            <input type="text" name="custom_slug" placeholder="តំណផ្ទាល់ខ្លួន (ស្រេចចិត្ត), vd: my-link">
            <button type="submit">បង្រួមតំណ</button>
        </form>
    </div>
</body>
</html>