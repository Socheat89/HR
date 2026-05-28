<?php
session_start();
include '../system/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
  header('Location: ../auth/login.php');
  exit;
}
$uid = $_SESSION['user_id'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
  $stmt = $pdo->prepare("INSERT INTO category_list (user_id,name) VALUES (?,?)");
  $stmt->execute([$uid, trim($_POST['new_category'])]);
  header("Location: ../system/checklist.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task'])) {
  $stmt = $pdo->prepare(
    "INSERT INTO work_checklist (user_id, task, due_date, category)
       VALUES (?,?,?,?)"
  );
  $stmt->execute([$uid, $_POST['task'], $_POST['due_date'], $_POST['category']]);
  header("Location: ../system/checklist.php");
  exit;
}

if (isset($_GET['done'])) {
  $pdo->prepare("UPDATE work_checklist SET is_done=1 WHERE id=? AND user_id=?")
    ->execute([$_GET['done'], $uid]);
  header("Location: ../system/checklist.php");
  exit;
}
if (isset($_GET['delete'])) {
  $pdo->prepare("DELETE FROM work_checklist WHERE id=? AND user_id=?")
    ->execute([$_GET['delete'], $uid]);
  header("Location: ../system/checklist.php");
  exit;
}

$cats = $pdo->prepare("SELECT name FROM category_list WHERE user_id=?");
$cats->execute([$uid]);
$categories = $cats->fetchAll(PDO::FETCH_COLUMN);

$filter = $_GET['filter'] ?? '';
$sql = "SELECT * FROM work_checklist WHERE user_id=?";
$params = [$uid];
if ($filter) {
  $sql .= " AND category=?";
  $params[] = $filter;
}
$sql .= " ORDER BY id DESC";
$tasks = $pdo->prepare($sql);
$tasks->execute($params);
$tasks = $tasks->fetchAll();
?>
<!DOCTYPE html>
<html lang="km">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>ការងារ - HR App</title>

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
      --warning: #f59e0b;
      --dark: #0f172a;
      --light: #f8fafc;
      --gray-50: #f8fafc;
      --gray-100: #f1f5f9;
      --gray-200: #e2e8f0;
      --gray-300: #cbd5e1;
      --gray-500: #64748b;
      --gray-800: #1e293b;
      --gray-900: #0f172a;

      /* Season/Festival Theme Overrides */
      <?php if ($currentTheme === 'kny'): ?>
      --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899;
      <?php elseif ($currentTheme === 'pb'): ?>
      --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #4b5563;
      <?php elseif ($currentTheme === 'cny'): ?>
      --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #fbbf24;
      <?php elseif ($currentTheme === 'wf'): ?>
      --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #0ea5e9;
      <?php elseif ($currentTheme === 'kb'): ?>
      --primary: #d97706; --primary-light: #fbbf24; --primary-dark: #b45309; --secondary: #1e3a8a;
      <?php elseif ($currentTheme === 'indy'): ?>
      --primary: #7e22ce; --primary-light: #a855f7; --primary-dark: #581c87; --secondary: #1d4ed8;
      <?php endif; ?>
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
      <?php if (!empty($bgImage)): ?>
      background-image: url('<?php echo $bgImage; ?>') !important;
      background-size: cover !important;
      background-position: center !important;
      background-attachment: fixed !important;
      background-repeat: no-repeat !important;
      <?php endif; ?>
      color: var(--gray-800);
      line-height: 1.6;
      margin: 0;
      padding: 0;
      min-height: 100vh;
      animation: fadeIn 0.5s ease-out;
    }

    <?php if (!empty($bgImage)): ?>
    body::before {
        content: "";
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(5px);
        z-index: -1;
    }
    <?php endif; ?>

    /* Prevent horizontal scrolling */
    html,
    body {
      overflow-x: hidden;
      width: 100%;
    }

    .app-container {
      max-width: 800px;
      margin: 0 auto;
      padding: 24px;
      padding-bottom: 100px;
    }

    /* === HEADER STYLES === */
    .app-header {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: var(--border-radius-xl);
      padding: 16px 24px;
      margin-bottom: 32px;
      box-shadow: var(--shadow-lg);
      border: 1px solid rgba(255, 255, 255, 0.2);
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

    /* === ACTIONS === */
    .actions-bar {
      display: flex;
      gap: 12px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }

    .filter-select {
      flex: 1;
      min-width: 200px;
      border-radius: var(--border-radius);
      border: 1px solid var(--gray-200);
      padding: 10px 16px;
      background: white;
      box-shadow: var(--shadow-sm);
      font-weight: 500;
    }

    .btn-add {
      background: linear-gradient(135deg, var(--primary), var(--primary-light));
      color: white;
      border: none;
      padding: 10px 24px;
      border-radius: var(--border-radius);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: var(--shadow);
      transition: all 0.2s;
    }

    .btn-add:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
      color: white;
    }

    /* === TASK CARDS === */
    .task-card {
      background: white;
      border-radius: var(--border-radius-lg);
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: var(--shadow);
      border: 1px solid var(--gray-200);
      border-left: 6px solid var(--primary);
      transition: all 0.3s ease;
      display: flex;
      justify-content: space-between;
      align-items: center;
      animation: fadeInUp 0.5s ease backwards;
    }

    .task-card:hover {
      transform: scale(1.02);
      box-shadow: var(--shadow-lg);
    }

    .task-card.is-done {
      border-left-color: var(--success);
      opacity: 0.8;
    }

    .task-info {
      flex: 1;
    }

    .task-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--gray-900);
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .task-card.is-done .task-title {
      text-decoration: line-through;
      color: var(--gray-500);
    }

    .task-meta {
      font-size: 0.85rem;
      color: var(--gray-500);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .task-badge {
      background: var(--gray-100);
      color: var(--gray-600);
      padding: 2px 10px;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .task-actions {
      display: flex;
      gap: 8px;
    }

    .btn-task-action {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      transition: all 0.2s;
      text-decoration: none;
    }

    .btn-done {
      background: rgba(16, 185, 129, 0.1);
      color: var(--success);
    }

    .btn-done:hover {
      background: var(--success);
      color: white;
    }

    .btn-delete {
      background: rgba(239, 68, 68, 0.1);
      color: var(--danger);
    }

    .btn-delete:hover {
      background: var(--danger);
      color: white;
    }

    /* === BOTTOM NAV === */
    .bottom-nav {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 60px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      justify-content: space-around;
      align-items: center;
      padding: 0;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
      z-index: 1000;
      border-top: 1px solid rgba(0,0,0,0.05);
    }

    .nav-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 100%;
      color: var(--gray-500);
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
      transition: all 0.2s ease;
      border-radius: 0;
      margin: 0;
      padding: 0;
    }

    .nav-item.active {
      color: var(--primary);
      background: transparent;
      transform: none;
    }

    .nav-item.active .nav-icon {
      transform: translateY(-2px);
    }

    .nav-icon {
      font-size: 1.4rem;
      margin-bottom: 4px;
      transition: transform 0.2s ease;
    }
    
    .nav-item:active {
      background-color: rgba(0,0,0,0.02);
    }

    /* Modal Customization */
    .modal-content {
      border-radius: var(--border-radius-xl);
      border: none;
    }

    .modal-header {
      border-bottom: 1px solid var(--gray-100);
      padding: 24px;
    }

    .modal-body {
      padding: 24px;
    }

    .form-control-lg {
      border-radius: var(--border-radius);
      font-size: 1rem;
      padding: 12px 16px;
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

    @media (max-width: 768px) {
      .app-container {
        padding: 12px;
        padding-bottom: 90px;
      }

      .app-header {
        padding: 12px 16px;
        margin-bottom: 20px;
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

      .task-card {
        padding: 16px;
        margin-bottom: 12px;
      }

      .task-title {
        font-size: 1rem;
      }
    }
  </style>
</head>

<body>

  <div class="app-container">
    <!-- Modern Header -->
    <header class="app-header animate__animated animate__fadeInDown">
      <a href="homes.php" class="logo-container">
        <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
        <h1 class="app-title">ការងារ</h1>
      </a>
      <div class="d-none d-md-block">
        <span class="text-muted small">ស្វាគមន៍, </span>
        <span class="fw-bold"><?= htmlspecialchars($_SESSION['username']) ?></span>
      </div>
    </header>

    <!-- Actions Bar -->
    <div class="actions-bar animate__animated animate__fadeInUp">
      <form method="GET" class="flex-grow-1">
        <select name="filter" class="form-select filter-select" onchange="this.form.submit()">
          <option value="">📋 គ្រប់ប្រភេទការងារ</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c ?>" <?= $c == $filter ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button class="btn-add" data-bs-toggle="modal" data-bs-target="#taskModal">
        <i class="fas fa-plus"></i> បន្ថែមការងារ
      </button>
    </div>

    <!-- Task List -->
    <main>
      <?php if (!$tasks): ?>
        <div class="text-center py-5 bg-white rounded-4 shadow-sm animate__animated animate__fadeInUp">
          <img src="https://cdni.iconscout.com/illustration/premium/thumb/no-task-found-5207223-4351361.png"
            alt="No tasks" style="width: 200px; opacity: 0.8;">
          <h5 class="mt-3 text-secondary">មិនទាន់មានការងារនៅឡើយទេ</h5>
        </div>
      <?php else: ?>
        <?php foreach ($tasks as $index => $t): ?>
          <div class="task-card <?= $t['is_done'] ? 'is-done' : '' ?>" style="animation-delay: <?= $index * 0.05 ?>s">
            <div class="task-info">
              <div class="task-title">
                <?php if ($t['is_done']): ?>
                  <i class="fas fa-check-circle text-success"></i>
                <?php else: ?>
                  <i class="far fa-circle text-warning"></i>
                <?php endif; ?>
                <?= htmlspecialchars($t['task']) ?>
              </div>
              <div class="task-meta">
                <span class="task-badge"><?= htmlspecialchars($t['category']) ?></span>
                <span><i class="far fa-calendar-alt me-1"></i><?= $t['due_date'] ?></span>
              </div>
            </div>
            <div class="task-actions">
              <?php if (!$t['is_done']): ?>
                <a href="?done=<?= $t['id'] ?>" class="btn-task-action btn-done" title="Complete">
                  <i class="fas fa-check"></i>
                </a>
              <?php endif; ?>
              <a href="?delete=<?= $t['id'] ?>" class="btn-task-action btn-delete" title="Delete"
                onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបការងារនេះមែនទេ?')">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </main>
  </div>

  <!-- Add Task Modal -->
  <div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content shadow-lg" method="POST">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="fas fa-tasks me-2 text-primary"></i>បន្ថែមការងារថ្មី</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-4">
            <label class="form-label fw-bold">ចំណងជើងការងារ</label>
            <input name="task" type="text" class="form-control form-control-lg"
              placeholder="ឧៈ រៀបចំរបាយការណ៍ប្រចាំខែ..." required>
          </div>
          <div class="mb-4">
            <label class="form-label fw-bold">ថ្ងៃសម្រេច</label>
            <input name="due_date" type="date" class="form-control form-control-lg" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">ប្រភេទ</label>
            <div class="d-flex gap-2">
              <select name="category" class="form-select form-select-lg" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-primary" data-bs-target="#catModal" data-bs-toggle="modal">
                <i class="fas fa-plus"></i>
              </button>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="button" class="btn btn-light btn-lg flex-grow-1" data-bs-dismiss="modal">បោះបង់</button>
          <button type="submit" class="btn btn-primary btn-lg flex-grow-1">រក្សាទុក</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Category Modal -->
  <div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" method="POST">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="fas fa-tag me-2 text-secondary"></i>បន្ថែមប្រភេទថ្មី</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" data-bs-target="#taskModal"
            data-bs-toggle="modal"></button>
        </div>
        <div class="modal-body">
          <input name="new_category" type="text" class="form-control form-control-lg" placeholder="ឧៈ រដ្ឋបាល..."
            required>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" class="btn btn-secondary w-100">រក្សាទុកប្រភេទ</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav">
    <a href="../homes.php" class="nav-item">
      <i class="fas fa-home nav-icon"></i>
      <span>ទំព័រដើម</span>
    </a>
    <a href="checklist.php" class="nav-item active">
      <i class="fas fa-tasks nav-icon"></i>
      <span>ការងារ</span>
    </a>
    <a href="../posts/announcements.php" class="nav-item">
      <i class="fas fa-bell nav-icon"></i>
      <span>ដំណឹង</span>
    </a>
    <a href="../admin/profile.php" class="nav-item">
      <i class="fas fa-user nav-icon"></i>
      <span>គណនី</span>
    </a>
  </nav>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>