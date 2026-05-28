<?php
session_start();

// Load Theme Config
$themeConfigPath = __DIR__ . '/../admin/includes/theme_config.json';
$currentTheme = 'default';
$customImage = '';
if (file_exists($themeConfigPath)) {
    $configData = json_decode(file_get_contents($themeConfigPath), true);
    $currentTheme = $configData['theme'] ?? 'default';
    $customImage = $configData['custom_image'] ?? '';
}

// Default Background Images for each theme
$themeBackgrounds = [   
    'kny'  => 'https://i.ibb.co/RKMS4tb/khmer-new-year-bg-1770518313913.jpg',
    'pb'   => 'https://i.ibb.co/S4dYb35p/khmer-new-year-bg-1770518389358.jpg',
    'cny'  => 'https://i.ibb.co/4462998/khmer-new-year-bg-1770518448823.jpg',
    'wf'   => 'https://i.ibb.co/2611144/khmer-new-year-bg-1770518505378.jpg',
    'kb'   => 'https://images.unsplash.com/photo-1596701062351-be5f6a200a45?q=80&w=1600',
    'indy' => 'https://images.unsplash.com/photo-1629813289069-7c8704204d60?q=80&w=1600'
];

// Determine which image to use
$bgImage = !empty($customImage) ? $customImage : ($themeBackgrounds[$currentTheme] ?? '');


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  $currentUrl = $_SERVER['REQUEST_URI'];
  header('Location: ../auth/login.php?redirect=' . urlencode($currentUrl));
  exit();
}

// DB connection (adjust if needed)
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

try {
  $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
  die("Database connection failed: " . $e->getMessage());
}

// Get languages and current language
$languages = ['km' => 'Khmer', 'en' => 'English'];
$default_lang = 'km';
$lang = isset($_GET['lang']) && array_key_exists($_GET['lang'], $languages) ? $_GET['lang'] : $default_lang;

// Initialize $currentCategory to avoid "Undefined variable" warnings
$currentCategory = isset($_GET['category']) ? $_GET['category'] : null;

// Translations
$translations = [
  'km' => [
    'title' => 'ឯកសារ - ទិដ្ឋភាពអ្នកប្រើប្រាស់',
    'documents' => 'ឯកសារ',
    'search_placeholder' => 'ស្វែងរកឯកសារ...',
    'no_documents' => 'រកមិនឃើញឯកសារនៅក្នុងប្រភេទនេះទេ។',
    'language' => 'ភាសា',
    'categories' => 'ប្រភេទ',
    'select_language' => 'ជ្រើសរើសភាសា',
    'all_documents' => 'ឯកសារទាំងអស់',
    'home' => 'ទំព័រដើម',
    'requests' => 'សំណើ',
    'work' => 'ការងារ',
    'profile' => 'គណនី'
  ],
  'en' => [
    'title' => 'Documents - User View',
    'documents' => 'Documents',
    'search_placeholder' => 'Search documents...',
    'no_documents' => 'No documents found in this category.',
    'language' => 'Language',
    'categories' => 'Categories',
    'select_language' => 'Select Language',
    'all_documents' => 'All Documents',
    'home' => 'Home',
    'requests' => 'Requests',
    'work' => 'Task',
    'profile' => 'Profile'
  ]
];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= $translations[$lang]['title'] ?> - HR App</title>

  <!-- Frameworks & Libraries -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- Favicon & Theme Color -->
  <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
  <meta name="theme-color" content="#6366f1">

  <style>
    /* === SHARED DESIGN SYSTEM === */
    :root {
      --primary: #6366f1;
      --primary-light: #8b5cf6;
      --primary-dark: #4f46e5;
      --secondary: #06b6d4;
      --accent: #f59e0b;
      --success: #10b981;
      --danger: #ef4444;
      --dark: #0f172a;
      --light: #f8fafc;
      --gray-50: #f8fafc;
      --gray-100: #f1f5f9;
      --gray-200: #e2e8f0;
      --gray-300: #cbd5e1;
      --gray-500: #64748b;
      --gray-800: #1e293b;
      --gray-900: #0f172a;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --border-radius: 12px;
      --border-radius-lg: 16px;
      --border-radius-xl: 20px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      font-family: 'Kantumruy Pro', sans-serif;
      background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
      color: var(--gray-800);
      line-height: 1.6;
      margin: 0;
      padding: 0;
      min-height: 100vh;
    }

    @keyframes bgZoom {
      from { background-size: 100% 100%; }
      to { background-size: 110% 110%; }
    }

    /* Floating Animation for Theme Icons */
    @keyframes floatUpDown {
      0% { transform: translateY(0) rotate(-15deg); }
      50% { transform: translateY(-15px) rotate(-10deg); }
      100% { transform: translateY(0) rotate(-15deg); }
    }

    /* Season/Festival Theme Overrides */
    <?php if ($currentTheme === 'kny'): ?>
    :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; }
    .app-header { background: rgba(250, 204, 21, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
    .app-title { background: none !important; -webkit-text-fill-color: var(--primary-dark) !important; color: var(--primary-dark) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .btn-outline-primary { color: var(--primary-dark) !important; border-color: var(--primary-dark) !important; }
    .btn-outline-primary:hover { background: var(--primary-dark) !important; color: white !important; }
    .doc-card::after { 
        content: ""; position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px;
        background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
        background-size: contain; background-repeat: no-repeat;
        opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
    }
    /* Fireworks Overlay for KNY */
    body::after {
        content: "";
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
        background-size: cover; background-repeat: no-repeat;
        pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
    }
    
    <?php elseif ($currentTheme === 'pb'): ?>
    :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; }
    .app-header { background: rgba(234, 88, 12, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
    .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .btn-outline-primary { color: white !important; border-color: white !important; }
    .btn-outline-primary:hover { background: white !important; color: #ea580c !important; }
    .doc-card::after { 
        content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
        position: absolute; bottom: 5px; right: 5px; font-size: 50px;
        opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
    }

    <?php elseif ($currentTheme === 'cny'): ?>
    :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
    .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
    .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .btn-outline-primary { color: white !important; border-color: white !important; }
    .btn-outline-primary:hover { background: white !important; color: #dc2626 !important; }
    .doc-card::after { 
        content: ""; position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px;
        background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
        background-size: contain; background-repeat: no-repeat;
        opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
    }

    <?php elseif ($currentTheme === 'wf'): ?>
    :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; }
    .app-header { background: rgba(2, 132, 199, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
    .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 1px 2px rgba(0,0,0,0.1); }
    .btn-outline-primary { color: white !important; border-color: white !important; }
    .btn-outline-primary:hover { background: white !important; color: #0284c7 !important; }
    .doc-card::after { 
        content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
        position: absolute; bottom: 5px; right: 5px; font-size: 60px;
        opacity: 0.1; color: #0284c7; animation: floatUpDown 6s ease-in-out infinite;
    }
    <?php endif; ?>

    /* Apply Theme Background Image */
    <?php if (!empty($bgImage)): ?>
    body {
        background-image: url('<?php echo $bgImage; ?>') !important;
        background-size: cover !important;
        background-position: center !important;
        background-attachment: fixed !important;
        background-repeat: no-repeat !important;
        animation: bgZoom 20s ease-in-out infinite alternate;
    }

    /* Overlay to ensure readability */
    body::before {
        content: "";
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(2px);
        z-index: -2;
    }
    <?php endif; ?>

    /* Prevent horizontal scrolling */
    html,
    body {
      overflow-x: hidden;
      width: 100%;
    }

    .app-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 24px;
      padding-bottom: 90px;
      display: flex;
      gap: 24px;
    }

    /* === HEADER STYLES === */
    .app-header {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(20px);
      border-radius: var(--border-radius-xl);
      padding: 16px 24px;
      margin: 24px auto;
      max-width: 1200px;
      box-shadow: var(--shadow-lg);
      border: 1px solid rgba(255, 255, 255, 0.5);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 16px;
      text-decoration: none;
    }

    .logo-img {
      width: 48px;
      height: 48px;
      border-radius: var(--border-radius);
      object-fit: cover;
      box-shadow: var(--shadow);
    }

    .app-title {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    /* === SIDEBAR / NAVIGATION === */
    .doc-sidebar {
      width: 280px;
      flex-shrink: 0;
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(15px);
      border-radius: var(--border-radius-lg);
      padding: 24px;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255, 255, 255, 0.4);
      height: fit-content;
      position: sticky;
      top: 24px;
    }

    .sidebar-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .sidebar-title i {
      color: var(--primary);
    }

    .lang-select {
      width: 100%;
      padding: 10px;
      border-radius: 10px;
      border: 1px solid var(--gray-200);
      margin-bottom: 24px;
      font-weight: 600;
      cursor: pointer;
    }

    .category-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .category-link {
      display: block;
      padding: 10px 16px;
      border-radius: 10px;
      text-decoration: none;
      color: var(--gray-600);
      font-weight: 600;
      transition: all 0.2s;
      border: 1px solid transparent;
    }

    .category-link:hover {
      background: var(--gray-50);
      color: var(--primary);
    }

    .category-link.active {
      background: var(--primary);
      color: white;
      box-shadow: var(--shadow-sm);
    }

    /* === MAIN CONTENT === */
    .doc-content {
      flex-grow: 1;
    }

    .doc-card {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(15px);
      border-radius: var(--border-radius-xl);
      padding: 32px;
      margin-bottom: 24px;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255, 255, 255, 0.4);
      animation: fadeInUp 0.5s ease-out backwards;
      position: relative;
      overflow: hidden;
    }

    .doc-header {
      margin-bottom: 20px;
    }

    .doc-card-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 8px;
    }

    .doc-meta {
      font-size: 0.85rem;
      color: var(--gray-500);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .doc-body {
      font-size: 1rem;
      color: var(--gray-700);
      line-height: 1.8;
      margin-bottom: 24px;
    }

    .doc-images-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 16px;
    }

    .doc-img {
      width: 100%;
      height: 120px;
      object-fit: cover;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: var(--shadow-sm);
      border: 2px solid transparent;
    }

    .doc-img:hover {
      transform: scale(1.05);
      border-color: var(--primary);
      box-shadow: var(--shadow);
    }

    /* === IMAGE MODAL === */
    #imgZoomOverlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.9);
      backdrop-filter: blur(10px);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2000;
      cursor: pointer;
      padding: 24px;
    }

    #zoomedImg {
      max-width: 90%;
      max-height: 90vh;
      border-radius: 16px;
      box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      animation: zoomIn 0.3s ease-out;
    }

    /* === BOTTOM NAV === */
    .bottom-nav {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      justify-content: space-around;
      padding: 8px 0;
      box-shadow: 0 -2px 16px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      border-top-left-radius: var(--border-radius-lg);
      border-top-right-radius: var(--border-radius-lg);
      border: 1px solid rgba(255, 255, 255, 0.2);
      min-height: 64px;
    }

    .nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      color: var(--gray-500);
      font-size: 0.75rem;
      font-weight: 600;
      flex: 1;
      padding: 6px;
    }

    .nav-item.active {
      color: var(--primary);
    }

    .nav-icon {
      font-size: 1.3rem;
      margin-bottom: 2px;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes zoomIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }

      to {
        opacity: 1;
        transform: scale(1);
      }
    }

    @media (max-width: 992px) {
      .app-container {
        flex-direction: column;
        padding: 12px;
        padding-bottom: 90px;
      }

      .doc-sidebar {
        width: 100%;
        position: static;
        margin-bottom: 24px;
        padding: 16px;
      }

      .category-list {
        flex-direction: row;
        flex-wrap: wrap;
      }

      .category-link {
        padding: 8px 12px;
        font-size: 0.85rem;
      }

      .app-header {
        padding: 12px 16px;
        margin: 12px;
        margin-top: 12px;
      }

      .logo-img {
        width: 40px;
        height: 40px;
      }

      .app-title {
        font-size: 1.25rem;
      }

      .bottom-nav {
        display: flex;
      }

      .doc-card {
        padding: 20px;
      }
    }
  </style>
</head>

<body>

  <!-- Modern Header -->
  <header class="app-header animate__animated animate__fadeInDown">
    <a href="../homes.php" class="logo-container">
      <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
      <h1 class="app-title"><?= $translations[$lang]['documents'] ?></h1>
    </a>
    <a href="../homes.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
      <i class="fas fa-home me-1"></i> <?= $translations[$lang]['home'] ?>
    </a>
  </header>

  <div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="doc-sidebar animate__animated animate__fadeInLeft">
      <h3 class="sidebar-title"><i class="fas fa-language"></i> <?= $translations[$lang]['language'] ?></h3>
      <select class="form-select lang-select" onchange="changeLanguage(this.value)">
        <?php foreach ($languages as $code => $name): ?>
          <option value="<?= $code ?>" <?= $lang === $code ? 'selected' : '' ?>><?= $name ?></option>
        <?php endforeach; ?>
      </select>

      <h3 class="sidebar-title"><i class="fas fa-tags"></i> <?= $translations[$lang]['categories'] ?></h3>
      <ul class="category-list">
        <li>
          <a href="?lang=<?= $lang ?>" class="category-link <?= empty($currentCategory) ? 'active' : '' ?>">
            <?= $translations[$lang]['all_documents'] ?>
          </a>
        </li>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
        while ($cat = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $isActive = ($currentCategory == $cat['name']) ? 'active' : '';
          $catName = htmlspecialchars($cat['name']);
          echo "<li><a href='?category=" . urlencode($cat['name']) . "&lang=$lang' class='category-link $isActive'>$catName</a></li>";
        }
        ?>
      </ul>
    </aside>

    <!-- Main Content -->
    <main class="doc-content">
      <?php
      $sql = "SELECT posts.*, categories.name AS category_name FROM posts LEFT JOIN categories ON posts.category_id = categories.id";
      $params = [];
      if ($currentCategory) {
        $sql .= " WHERE categories.name = :category";
        $params['category'] = $currentCategory;
      }
      $sql .= " ORDER BY posts.created_at DESC";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (!$posts): ?>
        <div class="doc-card text-center py-5">
          <i class="fas fa-folder-open text-muted fs-1 mb-3"></i>
          <p><?= $translations[$lang]['no_documents'] ?></p>
        </div>
      <?php else:
        foreach ($posts as $index => $post):
          $title = $lang === 'en' && !empty($post['title_en']) ? $post['title_en'] : $post['title'];
          $content = $lang === 'en' && !empty($post['content_en']) ? $post['content_en'] : $post['content'];
          $categoryName = $post['category_name'] ?? 'General';
          $dateFormatted = date('d M Y', strtotime($post['created_at']));
          $images = json_decode($post['images'], true);
          ?>
          <article class="doc-card" style="animation-delay: <?= $index * 0.1 ?>s">
            <header class="doc-header">
              <h2 class="doc-card-title"><?= htmlspecialchars($title) ?></h2>
              <div class="doc-meta">
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                  <?= htmlspecialchars($categoryName) ?>
                </span>
                <span><i class="far fa-calendar-alt me-1"></i> <?= $dateFormatted ?></span>
              </div>
            </header>
            <div class="doc-body">
              <?= nl2br(htmlspecialchars($content)) ?>
            </div>
            <?php if (is_array($images) && count($images) > 0): ?>
              <div class="doc-images-grid">
                <?php foreach ($images as $imgUrl): ?>
                  <?php if (!empty($imgUrl)): ?>
                    <img src="<?= htmlspecialchars($imgUrl) ?>" class="doc-img" alt="Document image"
                      onclick="zoomImage(this.src)">
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; endif; ?>
    </main>
  </div>

  <!-- Image Zoom Overlay -->
  <div id="imgZoomOverlay" onclick="closeZoom()">
    <img src="" id="zoomedImg">
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="../homes.php" class="nav-item">
      <i class="fas fa-home nav-icon"></i>
      <span><?= $translations[$lang]['home'] ?></span>
    </a>
    <a href="../requests/requests_menu.php" class="nav-item">
      <i class="fas fa-clipboard-list nav-icon"></i>
      <span><?= $translations[$lang]['requests'] ?></span>
    </a>
    <a href="../system/checklist.php" class="nav-item">
      <i class="fas fa-tasks nav-icon"></i>
      <span><?= $translations[$lang]['work'] ?></span>
    </a>
    <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
      <i class="fas fa-user nav-icon"></i>
      <span><?= $translations[$lang]['profile'] ?></span>
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

    function zoomImage(src) {
      const overlay = document.getElementById('imgZoomOverlay');
      const zoomedImg = document.getElementById('zoomedImg');
      zoomedImg.src = src;
      overlay.style.display = 'flex';
    }

    function closeZoom() {
      document.getElementById('imgZoomOverlay').style.display = 'none';
    }

    // Close scale on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeZoom();
    });
  </script>
</body>

</html>
