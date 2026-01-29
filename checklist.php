<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php'); exit;
}
$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_category'])) {
    $stmt=$pdo->prepare("INSERT INTO category_list (user_id,name) VALUES (?,?)");
    $stmt->execute([$uid, trim($_POST['new_category'])]);
    header("Location: checklist.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['task'])) {
    $stmt=$pdo->prepare(
      "INSERT INTO work_checklist (user_id, task, due_date, category)
       VALUES (?,?,?,?)");
    $stmt->execute([$uid,$_POST['task'],$_POST['due_date'],$_POST['category']]);
    header("Location: checklist.php"); exit;
}

if (isset($_GET['done'])){
    $pdo->prepare("UPDATE work_checklist SET is_done=1 WHERE id=? AND user_id=?")
        ->execute([$_GET['done'],$uid]);
    header("Location: checklist.php"); exit;
}
if (isset($_GET['delete'])){
    $pdo->prepare("DELETE FROM work_checklist WHERE id=? AND user_id=?")
        ->execute([$_GET['delete'],$uid]);
    header("Location: checklist.php"); exit;
}

$cats=$pdo->prepare("SELECT name FROM category_list WHERE user_id=?");
$cats->execute([$uid]);
$categories=$cats->fetchAll(PDO::FETCH_COLUMN);

$filter=$_GET['filter']??'';
$sql="SELECT * FROM work_checklist WHERE user_id=?";
$params=[$uid];
if($filter){
  $sql.=" AND category=?";
  $params[]=$filter;
}
$sql.=" ORDER BY id DESC";
$tasks=$pdo->prepare($sql);
$tasks->execute($params);
$tasks=$tasks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Checklist</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
   <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
  <style>
    :root{--primary:#4f46e5;--light:#f8fafc}
    body{background:var(--light)}
    .card-task{border-left:5px solid var(--primary)}
    .card-task:hover{transform:translateY(-2px);transition:.2s}
    .done-text{text-decoration:line-through;color:#999}
    .bottom-nav{position:fixed;bottom:0;left:0;right:0;background:#fff;
      display:flex;justify-content:space-around;padding:10px 0;
      box-shadow:0 -2px 10px rgba(0,0,0,.05);z-index:999}
    .nav-item{font-size:.8rem;color:#64748b;text-align:center}
    .nav-item.active{color:var(--primary)}
    .nav-icon{font-size:1.2rem;margin-bottom:4px}
     a {
            text-decoration: none;
        }
  </style>
</head>
<body>
<div class="container py-4" style="padding-bottom:80px">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-primary mb-0">Welcome <?=htmlspecialchars($_SESSION['username'])?></h4>
    <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
  </div>

  <form method="GET" class="row g-2 align-items-center mb-4">
    <div class="col-auto">
      <select name="filter" class="form-select" onchange="this.form.submit()">
        <option value="">📋 All Categories</option>
        <?php foreach($categories as $c):?>
          <option value="<?=$c?>" <?=$c==$filter?'selected':''?>><?=$c?></option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="col-auto">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
        ➕ Add Task
      </button>
    </div>
  </form>

  <?php if(!$tasks):?>
    <div class="alert alert-secondary text-center">No tasks found.</div>
  <?php endif;?>

  <?php foreach($tasks as $t):?>
    <div class="card shadow-sm mb-3 card-task border-start
        <?=$t['is_done']?'border-success':'border-warning'?>">
      <div class="card-body d-flex justify-content-between align-items-start">
        <div>
          <div class="fw-semibold <?=$t['is_done']?'done-text':''?>">
            <?=htmlspecialchars($t['task'])?>
            <span class="badge bg-secondary"><?=htmlspecialchars($t['category'])?></span>
          </div>
          <small class="text-muted">Due: <?=$t['due_date']?></small>
        </div>
        <div class="text-end">
          <?php if(!$t['is_done']):?>
            <a href="?done=<?=$t['id']?>" class="btn btn-sm btn-outline-success me-1">
              <i class="fas fa-check"></i>
            </a>
          <?php endif;?>
          <a href="?delete=<?=$t['id']?>" class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Delete this task?')">
            <i class="fas fa-trash"></i>
          </a>
        </div>
      </div>
    </div>
  <?php endforeach;?>
</div>

<!-- Add Task Modal -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content shadow-lg rounded-4" method="POST">
      <div class="modal-header bg-primary text-white rounded-top-4">
        <h5 class="modal-title" id="taskModalLabel"><i class="fas fa-tasks me-2"></i>បន្ថែមការងារថ្មី</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <div class="mb-3">
          <label class="form-label fw-bold"><i class="fas fa-pencil-alt me-1"></i>ចំណងជើងការងារ</label>
          <input name="task" type="text" class="form-control form-control-lg" placeholder="វាយការងាររបស់អ្នក..." required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold"><i class="fas fa-calendar-alt me-1"></i>កាលបរិច្ឆេទសម្រេច</label>
          <input name="due_date" type="date" class="form-control form-control-lg" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold"><i class="fas fa-tags me-1"></i>ប្រភេទ</label>
          <div class="d-flex gap-2">
            <select name="category" class="form-select form-select-lg" required>
              <?php foreach($categories as $c):?>
                <option value="<?=$c?>"><?=$c?></option>
              <?php endforeach;?>
            </select>
            <button type="button" class="btn btn-outline-secondary rounded-circle" data-bs-target="#catModal" data-bs-toggle="modal">
              <i class="fas fa-plus"></i>
            </button>
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light border-0">
        <button class="btn btn-primary btn-lg w-100 shadow-sm"><i class="fas fa-save me-1"></i>រក្សាទុកការងារ</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content shadow rounded-4" method="POST">
      <div class="modal-header bg-secondary text-white rounded-top-4">
        <h5 class="modal-title"><i class="fas fa-tag me-1"></i>បន្ថែមប្រភេទ</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                data-bs-target="#taskModal" data-bs-toggle="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <input name="new_category" type="text" class="form-control form-control-lg" placeholder="ប្រភេទថ្មី..." required>
      </div>
      <div class="modal-footer bg-light border-0">
        <button class="btn btn-secondary btn-lg w-100"><i class="fas fa-save me-1"></i>រក្សាទុកប្រភេទ</button>
      </div>
    </form>
  </div>
</div>

<nav class="bottom-nav d-md-none">
  <a href="homes.php" class="nav-item">
    <i class="fas fa-home nav-icon"></i><div>ទំព័រដើម</div>
  </a>
  <a href="#" class="nav-item">
    <i class="fas fa-calendar nav-icon"></i><div>កាលវិភាគ</div>
  </a>
  <a href="checklist.php" class="nav-item active">
    <i class="fas fa-tasks nav-icon"></i><div>ការងារ</div>
  </a>
  <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
    <i class="fas fa-user nav-icon"></i><div>គណនី</div>
  </a>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
