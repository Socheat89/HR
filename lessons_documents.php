<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $currentUrl = $_SERVER['REQUEST_URI'];
    header('Location: login.php?redirect=' . urlencode($currentUrl));
    exit();
}
// DB connection (adjust if needed)
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET CHARACTER SET utf8mb4");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

header('Content-Type: text/html; charset=utf-8');

$languages = ['km' => 'Khmer', 'en' => 'English'];
$default_lang = 'km';
$lang = isset($_GET['lang']) && array_key_exists($_GET['lang'], $languages) ? $_GET['lang'] : $default_lang;

// Translations
$translations = [
    'km' => [
        'title' => 'ឯកសារ - ទស្សនៃអ្នកប្រើ',
        'documents' => 'ឯកសារ',
        'search_placeholder' => 'ស្វែងរកឯកសារ...',
        'no_documents' => 'មិនមានឯកសារណាមួយក្នុងប្រភេទនេះទេ។',
        'language' => 'ភាសា',
        'select_language' => 'ជ្រើសរើសភាសា'
    ],
    'en' => [
        'title' => 'Documents - User View',
        'documents' => 'Documents',
        'search_placeholder' => 'Search documents...',
        'no_documents' => 'No documents found in this category.',
        'language' => 'Language',
        'select_language' => 'Select Language'
    ]
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $translations[$lang]['title'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;600&family=Roboto:wght@400;600&display=swap');

    /* Reset & base */
    body {
      font-family: <?= $lang === 'km' ? '"Noto Sans Khmer", sans-serif' : '"Roboto", sans-serif' ?>;
      background-color: #FFFFFF;
      margin: 0;
      padding: 0;
      color: #333;
      font-size: 16px;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* Sidebar */
    .sidebar {
      background-color: #FFFFFF;
      border-right: 1px solid #FFD700;
      width: 280px;
      min-height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      padding: 2rem 1.5rem;
      box-shadow: 2px 0 10px rgba(255, 215, 0, 0.1);
      display: flex;
      flex-direction: column;
      font-size: 16px;
      z-index: 2000;
    }

    .sidebar h6 {
      font-size: 16px;
      font-weight: 700;
      color: #FFD700;
      margin-bottom: 1.5rem;
      letter-spacing: 1px;
      background: linear-gradient(90deg, #FFD700, #FFC107);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .sidebar select.lang-select {
      border: 2px solid #FFD700;
      border-radius: 8px;
      background-color: #FFFFFF;
      color: #FFD700;
      font-weight: 600;
      padding: 0.5rem 0.75rem;
      margin-bottom: 2rem;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .sidebar select.lang-select:hover,
    .sidebar select.lang-select:focus {
      background-color: #FFF3CD;
      outline: none;
    }

    /* Sidebar Menu List */
    .menu-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .menu-list li {
      margin-bottom: 0.5rem;
    }

    .menu-list a {
      display: block;
      padding: 0.75rem 1rem;
      color: #333;
      text-decoration: none;
      border-left: 4px solid transparent;
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
      background-color: #FFFFFF;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .menu-list a:hover {
      color: #FFD700;
      background-color: #FFF3CD;
      border-left-color: #FFD700;
      transform: translateY(-2px);
      box-shadow: 0 4px 10px rgba(255, 215, 0, 0.2);
    }

    .menu-list a.active {
      font-weight: 700;
      color: #FFFFFF;
      background: linear-gradient(135deg, #FFD700, #FFC107);
      border-left-color: #FFCA28;
      box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
    }

    /* Content wrapper */
    .content {
      margin-left: 300px;
      padding: 2rem 3rem;
      flex-grow: 1;
      min-height: 100vh;
      background-color: #FFFFFF;
    }

    /* Page title */
    .doc-title {
      font-size: 20px;
      font-weight: 700;
      color: #FFD700;
      margin-bottom: 2rem;
      letter-spacing: 1.2px;
      background: linear-gradient(90deg, #FFD700, #FFC107);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* Search input */
    .search-bar {
      margin-bottom: 2rem;
      max-width: 480px;
    }

    .search-bar input {
      width: 100%;
      font-size: 20px;
      padding: 10px 16px;
      border: 2px solid #FFD700;
      border-radius: 10px;
      transition: all 0.3s ease;
      background-color: #FFFFFF;
      color: #333;
    }

    .search-bar input:focus {
      border-color: #FFC107;
      outline: none;
      background-color: #FFF3CD;
    }

    /* Document card */
    .doc-block {
      background-color: #FFFFFF;
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: 0 6px 14px rgba(255, 215, 0, 0.08);
      transition: all 0.3s ease;
    }

    .doc-block:hover {
      box-shadow: 0 10px 20px rgba(255, 215, 0, 0.16);
      transform: translateY(-5px);
    }

    .doc-block h5 {
      font-size: 20px;
      font-weight: 700;
      color: #333;
      margin-bottom: 0.3rem;
    }

    .doc-block small {
      color: #666;
      font-size: 18px;
      display: block;
      margin-bottom: 1.3rem;
    }

    .doc-block p {
      font-size: 16px;
      color: #444;
      white-space: pre-line;
      margin-bottom: 1.8rem;
    }

    /* Images grid */
    .doc-images {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 0.8rem;
    }

    .doc-images img {
      max-width: 220px;
      height: 160px;
      border-radius: 12px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      object-fit: cover;
      cursor: zoom-in;
      transition: all 0.3s ease;
      border: 2px solid #FFD700;
    }

    .doc-images img:hover {
      transform: scale(1.08);
      box-shadow: 0 8px 20px rgba(255, 215, 0, 0.3);
    }

    /* Zoom overlay */
    .img-zoom-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      cursor: zoom-out;
      padding: 2rem;
    }

    .img-zoom-overlay img {
      max-width: 90vw;
      max-height: 90vh;
      border-radius: 16px;
      box-shadow: 0 0 40px rgba(255, 255, 255, 0.9);
      border: 2px solid #FFD700;
      user-select: none;
    }

    /* Sidebar toggle button */
    .sidebar-toggle {
      display: none;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 2100;
      background: linear-gradient(135deg, #FFD700, #FFC107);
      color: #FFFFFF;
      border: none;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-size: 18px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
      transform: scale(1.1);
      box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
    }

    /* Overlay for mobile sidebar */
    .overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(255, 215, 0, 0.2);
      z-index: 1990;
    }

    .overlay.active {
      display: block;
    }

    /* Bottom Navigation */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #FFFFFF;
      display: flex;
      justify-content: space-around;
      padding: 15px 0;
      box-shadow: 0 -5px 15px rgba(255, 215, 0, 0.05);
      z-index: 1000;
      border-top: 1px solid #FFD700;
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: #666;
      font-size: 0.8rem;
      transition: all 0.3s ease;
    }

    .nav-item.active {
      color: #FFD700;
      transform: translateY(-5px);
    }

    .nav-icon {
      font-size: 1.5rem;
      margin-bottom: 5px;
      color: #FFD700;
    }

    /* Desktop (≥769px) */
    @media (min-width: 769px) {
      .sidebar {
        display: block !important;
        position: fixed;
        left: 0;
        width: 280px;
        padding: 2rem 1.5rem;
        font-size: 16px;
      }
      .content {
        margin-left: 300px;
        padding: 2rem 3rem;
      }
      .menu-list a {
        padding: 0.75rem 1rem;
      }
      .bottom-nav {
        display: none;
      }
    }

    /* Tablet (577px - 768px) */
    @media (min-width: 577px) and (max-width: 768px) {
      .sidebar {
        display: block;
        position: fixed;
        top: 0;
        left: -100%;
        width: 80%;
        max-width: 280px;
        height: 100vh;
        z-index: 2000;
        transition: left 0.3s ease;
        padding: 2rem 1.5rem;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
      }
      .sidebar.show {
        left: 0;
      }
      .content {
        margin-left: 0 !important;
        padding: 1.5rem 2rem;
      }
      .doc-block h5 {
        font-size: 22px;
      }
      .menu-list a {
        padding: 0.5rem 1rem;
      }
      .sidebar-toggle {
        display: block;
      }
      .bottom-nav {
        display: flex;
      }
    }

    /* Mobile (≤576px) */
    @media (max-width: 576px) {
      .sidebar {
        display: block;
        position: fixed;
        top: 0;
        left: -100%;
        width: 80%;
        max-width: 260px;
        height: 100vh;
        z-index: 2000;
        transition: left 0.3s ease;
        padding: 1.5rem 1rem;
        font-size: 18px;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        overflow-y: auto;
      }
      .sidebar.show {
        left: 0;
      }
      .content {
        margin-left: 0 !important;
        padding: 1rem 1.5rem;
      }
      .doc-block h5 {
        font-size: 20px;
      }
      .menu-list a {
        padding: 0.5rem 1rem;
      }
      .sidebar-toggle {
        display: block;
      }
      .bottom-nav {
        display: flex;
      }
    }
  </style>
</head>
<body>
  <button class="sidebar-toggle" aria-label="Toggle sidebar">☰</button>
  <div class="overlay" id="overlay"></div>

  <div class="sidebar" role="navigation" aria-label="Document categories and language selection">
    <h6><?= $translations[$lang]['documents'] ?></h6>
    <select class="lang-select" aria-label="<?= $translations[$lang]['language'] ?>" onchange="changeLanguage(this.value)">
      <option value=""><?= $translations[$lang]['select_language'] ?></option>
      <?php foreach ($languages as $code => $name): ?>
        <option value="<?= $code ?>" <?= $lang === $code ? 'selected' : '' ?>><?= $name ?></option>
      <?php endforeach; ?>
    </select>

    <ul class="menu-list">
      <?php
      $currentCategory = $_GET['category'] ?? '';
      $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
      while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $active = ($currentCategory == $cat['name']) ? 'active' : '';
          $catNameEncoded = htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8');
          echo "<li><a class='$active' href='?category={$catNameEncoded}&lang={$lang}'>{$catNameEncoded}</a></li>";
      }
      ?>
    </ul>
  </div>

  <div class="content" role="main">
    <h1 class="doc-title"><?= $translations[$lang]['documents'] ?></h1>
    <div class="search-bar">
      <input type="search" placeholder="<?= $translations[$lang]['search_placeholder'] ?>" aria-label="Search documents" disabled />
    </div>

    <?php
    $sql = "SELECT posts.*, categories.name AS category_name 
            FROM posts 
            LEFT JOIN categories ON posts.category_id = categories.id";
    $params = [];
    if ($currentCategory) {
        $sql .= " WHERE categories.name = :category";
        $params['category'] = $currentCategory;
    }
    $sql .= " ORDER BY posts.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$posts) {
        echo "<p>" . $translations[$lang]['no_documents'] . "</p>";
    }

    foreach ($posts as $post):
        $title = $lang === 'en' && !empty($post['title_en']) ? $post['title_en'] : $post['title'];
        $content = $lang === 'en' && !empty($post['content_en']) ? $post['content_en'] : $post['content'];
        $categoryName = $post['category_name'] ?? '';
        $dateFormatted = date('F d, Y', strtotime($post['created_at']));
        $images = json_decode($post['images'], true);
    ?>
      <article class="doc-block" tabindex="0" aria-label="<?= htmlspecialchars($title, ENT_QUOTES) ?>">
        <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
        <small><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?> • <?= $dateFormatted ?></small>
        <p><?= nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php if (is_array($images) && count($images) > 0): ?>
          <div class="doc-images">
            <?php foreach ($images as $imgUrl): ?>
              <?php if (!empty($imgUrl) && filter_var($imgUrl, FILTER_VALIDATE_URL)): ?>
                <img src="<?= htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES) ?> Image" tabindex="0" />
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="img-zoom-overlay" id="imgZoomOverlay" aria-hidden="true" role="dialog" aria-label="Zoomed image view" tabindex="-1">
    <img src="" alt="Zoomed Document Image" id="zoomedImg" />
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav d-lg-none">
    <a href="#" class="nav-item active">
      <i class="fas fa-home nav-icon"></i>
      <span>ទំព័រដើម</span>
    </a>
    <a href="#" class="nav-item">
      <i class="fas fa-calendar nav-icon"></i>
      <span>កាលវិភាគ</span>
    </a>
    <a href="#" class="nav-item">
      <i class="fas fa-tasks nav-icon"></i>
      <span>ការងារ</span>
    </a>
    <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
      <i class="fas fa-user nav-icon"></i>
      <span>គណនី</span>
    </a>
  </nav>

  <script>
    function changeLanguage(lang) {
      if (lang) {
        const url = new URL(window.location);
        url.searchParams.set('lang', lang);
        window.location.href = url.toString();
      }
    }

    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const overlay = document.querySelector('#overlay');

    toggleBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
      overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
      sidebar.classList.remove('show');
      overlay.classList.remove('active');
    });

    const zoomOverlay = document.getElementById('imgZoomOverlay');
    const zoomedImg = document.getElementById('zoomedImg');
    document.querySelectorAll('.doc-images img').forEach(img => {
      img.addEventListener('click', () => {
        zoomedImg.src = img.src;
        zoomOverlay.style.display = 'flex';
        zoomOverlay.setAttribute('aria-hidden', 'false');
        zoomOverlay.focus();
      });
      img.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          img.click();
        }
      });
    });
    zoomOverlay.addEventListener('click', () => {
      zoomOverlay.style.display = 'none';
      zoomOverlay.setAttribute('aria-hidden', 'true');
      zoomedImg.src = '';
    });
    zoomOverlay.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        zoomOverlay.style.display = 'none';
        zoomOverlay.setAttribute('aria-hidden', 'true');
        zoomedImg.src = '';
      }
    });
  </script>
</body>
</html>