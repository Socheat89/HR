<?php
session_start();
include 'db_payroll.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$is_admin = false;
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $is_admin = ($row['role'] === 'admin');
}
$stmt->close();

// Handle user filter for admin
$selected_user_id = $user_id;
if ($is_admin && isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $selected_user_id = (int)$_GET['user_id'];
}

// Handle date filter
$today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format
$whereClause = $is_admin ? '' : 'WHERE user_id = ?';
$params = $is_admin ? [] : [$user_id];
if (!$is_admin || ($is_admin && $selected_user_id)) {
    $whereClause = $whereClause ? $whereClause . ' AND ' : 'WHERE ';
    $whereClause .= 'user_id = ?';
    $params[] = $selected_user_id;
}

// Check if date filters are provided; otherwise, use today's date
if (isset($_GET['start_date']) && isset($_GET['end_date']) && $_GET['start_date'] && $_GET['end_date']) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
    if (strtotime($start_date) && strtotime($end_date)) {
        $whereClause .= $whereClause ? " AND due_date BETWEEN ? AND ?" : "WHERE due_date BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
} else {
    // Default to today
    $start_date = $today;
    $end_date = $today;
    $whereClause .= $whereClause ? " AND due_date = ?" : "WHERE due_date = ?";
    $params[] = $today;
}

// Fetch tasks
$sql = "SELECT tasks.*, users.username FROM tasks LEFT JOIN users ON tasks.user_id = users.id $whereClause ORDER BY sort_order ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}
$stmt->close();

// Fetch all users for admin dropdown
$users = [];
if ($is_admin) {
    $stmt = $conn->prepare("SELECT id, username FROM users ORDER BY username");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>To-Do List with Drag & Drop and Dark Mode</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f0f2f5;
      color: #333;
      margin: 0;
      padding: 0;
      transition: background 0.3s, color 0.3s;
    }
    body.dark {
      background: #121212;
      color: #ddd;
    }
    header {
      background: #0d6efd;
      color: #fff;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      animation: fadeIn 0.5s ease-out;
    }
    body.dark header {
      background: #1a1a2e;
    }
    header a {
      color: white;
      text-decoration: none;
      font-size: 1.2rem;
      transition: transform 0.2s;
    }
    header a:hover {
      transform: scale(1.1);
    }
    #darkToggle {
      background: rgba(255,255,255,0.1);
      border: none;
      color: white;
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      cursor: pointer;
      font-size: 1rem;
      transition: transform 0.2s, background 0.2s;
    }
    #darkToggle:hover {
      transform: scale(1.1) rotate(5deg);
      background: rgba(255,255,255,0.2);
    }
    .header-buttons {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    main {
      max-width: 800px;
      margin: 2rem auto;
      padding: 1rem;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      animation: slideIn 0.5s ease-out;
    }
    body.dark main {
      background: #1e1e1e;
      color: #ddd;
    }
    .filter-form {
      margin-bottom: 1.5rem;
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      align-items: flex-start;
      animation: fadeIn 0.6s ease-out 0.2s both;
      position: relative;
    }
    .filter-form.invalid {
      animation: shake 0.3s ease;
    }
    .filter-form label {
      font-size: 0.9rem;
      color: #333;
      margin-bottom: 0.2rem;
    }
    body.dark .filter-form label {
      color: #ddd;
    }
    .filter-form input[type="date"], .filter-form select {
      padding: 0.5rem;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 0.9rem;
      transition: border-color 0.2s, transform 0.2s;
    }
    .filter-form input[type="date"]:focus, .filter-form select:focus {
      border-color: #0d6efd;
      transform: scale(1.02);
    }
    body.dark .filter-form input[type="date"], body.dark .filter-form select {
      background: #2a2a2a;
      color: #fff;
      border: 1px solid #444;
    }
    .filter-form button, .filter-form a {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.9rem;
      cursor: pointer;
      text-decoration: none;
      transition: transform 0.2s, background 0.2s;
      position: relative;
    }
    .filter-form button[type="submit"] {
      background: #0d6efd;
      color: white;
      border: none;
    }
    .filter-form button[type="submit"]:hover {
      transform: scale(1.05);
      background: #0056b3;
    }
    .filter-form button[type="submit"].loading::after {
      content: '';
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 2px solid #fff;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
      margin-left: 8px;
      vertical-align: middle;
    }
    body.dark .filter-form button[type="submit"] {
      background: #1a73e8;
    }
    .filter-form a.clear-filter {
      background: #dc3545;
      color: white;
      display: inline-block;
    }
    .filter-form a.clear-filter:hover {
      transform: scale(1.05);
      background: #a00;
    }
    body.dark .filter-form a.clear-filter {
      background: #a00;
    }
    ul#task-list {
      list-style: none;
      padding: 0;
      margin: 0;
      transition: height 0.3s ease;
    }
    li.task {
      background: #f9f9f9;
      margin-bottom: 1rem;
      padding: 1rem;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
      transition: background 0.2s, opacity 0.3s, transform 0.3s, box-shadow 0.2s;
      cursor: grab;
      animation: taskEnter 0.4s ease-out;
    }
    li.task:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    body.dark li.task:hover Gives {
      box-shadow: 0 4px 12px rgba(255,255,255,0.1);
    }
    body.dark li.task {
      background: #2a2a2a;
    }
    li.task.completed {
      text-decoration: line-through;
      opacity: 0.6;
    }
    li.task.removing {
      animation: taskExit 0.3s ease-out forwards;
    }
    li.task.toggling {
      animation: statusToggle 0.3s ease;
    }
    .task-info {
      flex: 1;
      text-align: left;
      transition: color 0.2s;
    }
    .task-info:hover {
      color: #0d6efd;
    }
    .task-info small {
      display: block;
      color: #666;
      font-size: 0.85rem;
    }
    body.dark .task-info small {
      color: #aaa;
    }
    .btn-delete {
      background: none;
      border: none;
      color: #dc3545;
      font-size: 1.5rem;
      cursor: pointer;
      transition: color 0.2s, transform 0.2s;
    }
    .btn-delete:hover {
      color: #a00;
      transform: scale(1.2);
    }
    #addTaskBtn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      width: 60px;
      height: 60px;
      background: #0d6efd;
      color: white;
      font-size: 2rem;
      border: none;
      border-radius: 50%;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.2);
      transition: transform 0.2s, background 0.2s;
    }
    #addTaskBtn:hover {
      transform: scale(1.1);
      background: #0056b3;
    }
    body.dark #addTaskBtn {
      background: #1a73e8;
    }
    #popupForm {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 1000;
      transition: opacity 0.3s ease;
    }
    #popupForm.active {
      display: flex;
      opacity: 1;
      animation: fadeIn 0.3s ease-out;
    }
    .form-container {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.2);
      position: relative;
      animation: scaleIn 0.3s ease-out;
    }
    body.dark .form-container {
      background: #1f1f1f;
      color: #ddd;
    }
    .form-container h2 {
      margin-top: 0;
    }
    .form-container label {
      display: block;
      margin-top: 1rem;
      margin-bottom: 0.2rem;
    }
    .form-container input,
    .form-container select,
    .form-container textarea {
      width: 100%;
      padding: 0.5rem;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 1rem;
      box-sizing: border-box;
      margin-top: 0.4rem;
      transition: border-color 0.2s, transform 0.2s;
    }
    .form-container input:focus,
    .form-container select:focus,
    .form-container textarea:focus {
      border-color: #0d6efd;
      transform: scale(1.01);
    }
    body.dark input,
    body.dark select,
    body.dark textarea {
      background: #2a2a2a;
      color: #fff;
      border: 1px solid #444;
    }
    .form-container button[type="submit"] {
      background: #0d6efd;
      color: white;
      border: none;
      margin-top: 1.5rem;
      padding: 0.6rem 1.2rem;
      border-radius: 6px;
      font-size: 1rem;
      cursor: pointer;
      transition: transform 0.2s, background 0.2s;
    }
    .form-container button[type="submit"]:hover {
      transform: scale(1.05);
      background: #0056b3;
    }
    .form-container button[type="submit"].loading::after {
      content: '';
      display: inline-block;
      width: 12px;
      height: 12px;
      border: 2px solid #fff;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
      margin-left: 8px;
      vertical-align: middle;
    }
    .close-btn {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 1.4rem;
      color: #999;
      cursor: pointer;
      transition: color 0.2s, transform 0.2s;
    }
    .close-btn:hover {
      color: #333;
      transform: rotate(90deg);
    }
    .status-label {
      padding: 0.3rem 0.6rem;
      border-radius: 5px;
      font-weight: bold;
      font-size: 0.75rem;
      white-space: nowrap;
      transition: transform 0.2s;
    }
    .status-label:hover {
      transform: scale(1.1);
    }
    .status-pending {
      background-color: #ffc107;
      color: #000;
    }
    .status-completed {
      background-color: #28a745;
      color: #fff;
    }
    li.no-tasks {
      text-align: center;
      padding: 1rem;
      color: #666;
      animation: fadeIn 0.5s ease-out;
    }
    body.dark li.no-tasks {
      color: #aaa;
    }
    .error-message {
      color: #dc3545;
      font-size: 0.85rem;
      margin-top: 0.3rem;
      opacity: 0;
      transform: translateY(-5px);
      animation: fadeInError 0.3s ease-out forwards;
      display: block;
    }
    body.dark .error-message {
      color: #ff6b6b;
    }
    .filter-form .error-message, .form-container .error-message {
      width: 100%;
    }
    input.error, select.error {
      border-color: #dc3545;
      box-shadow: 0 0 5px rgba(220, 53, 69, 0.3);
    }
    body.dark input.error, body.dark select.error {
      border-color: #ff6b6b;
    }
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes fadeInError {
      from { opacity: 0; transform: translateY(-5px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideIn {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    @keyframes scaleIn {
      from { transform: scale(0.8); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }
    @keyframes taskEnter {
      from { transform: translateX(-20px) scale(0.95); opacity: 0; }
      to { transform: translateX(0) scale(1); opacity: 1; }
    }
    @keyframes taskExit {
      from { transform: translateX(0); opacity: 1; }
      to { transform: translateX(20px); opacity: 0; }
    }
    @keyframes statusToggle {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.05); opacity: 0.7; }
      100% { transform: scale(1); opacity: 1; }
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(−5px); }
      40%, 80% { transform: translateX(5px); }
    }
    @media (prefers-reduced-motion: reduce) {
      header, main, .filter-form, li.task, #popupForm, .form-container, .btn-delete, #addTaskBtn, .filter-form button, .filter-form a, #darkToggle, li.no-tasks, .error-message {
        animation: none !important;
        transition: none !important;
      }
      .filter-form button[type="submit"].loading::after,
      .form-container button[type="submit"].loading::after {
        display: none !important;
      }
    }
    @media (max-width: 600px) {
      li.task {
        flex-direction: column;
        align-items: flex-start;
      }
      .btn-delete {
        align-self: flex-end;
      }
      header {
        flex-wrap: wrap;
        gap: 0.5rem;
      }
      .header-buttons {
        margin-left: 0;
      }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
</head>
<body>
<header>
  <a href="homes.php"><i class="fas fa-home"></i></a>
  <h1>To-Do List (<?= htmlspecialchars($_SESSION['username']) ?>)</h1>
  <div class="header-buttons">
    <button id="darkToggle" aria-label="Toggle dark mode">🌙</button>
  </div>
</header>

<main>
  <form class="filter-form" method="GET" action="" id="filterForm">
    <?php if ($is_admin): ?>
      <label for="user_id">User:</label>
      <select id="user_id" name="user_id">
        <option value="">All Users</option>
        <?php foreach ($users as $user): ?>
          <option value="<?= $user['id'] ?>" <?= $selected_user_id == $user['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($user['username']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="error-message" id="user_id_error" role="alert" aria-live="polite"></span>
    <?php endif; ?>
    <label for="start_date">Start Date:</label>
    <input type="date" id="start_date" name="start_date" value="<?= isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : date('Y-m-d') ?>" />
    <span class="error-message" id="start_date_error" role="alert" aria-live="polite"></span>
    <label for="end_date">End Date:</label>
    <input type="date" id="end_date" name="end_date" value="<?= isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : date('Y-m-d') ?>" />
    <span class="error-message" id="end_date_error" role="alert" aria-live="polite"></span>
    <button type="submit">Filter</button>
    <?php if (isset($_GET['start_date']) || isset($_GET['end_date']) || (isset($_GET['user_id']) && $_GET['user_id'] !== '')): ?>
      <a href="todo-list.php" class="clear-filter">Clear Filter</a>
    <?php endif; ?>
  </form>

  <ul id="task-list" aria-live="polite">
    <?php if (count($tasks) === 0): ?>
      <li class="no-tasks">No tasks found.</li>
    <?php else: ?>
      <?php foreach ($tasks as $index => $task): ?>
        <?php
          $statusClass = ($task['status'] === 'completed') ? 'completed' : '';
          $statusLabelClass = ($task['status'] === 'completed') ? 'status-completed' : 'status-pending';
        ?>
        <li class="task <?= htmlspecialchars($statusClass) ?>" data-id="<?= $task['id'] ?>" style="animation-delay: <?= $index * 0.05 ?>s;">
          <form action="update.php" method="POST" class="task-info-form" style="flex:1; margin:0;">
            <input type="hidden" name="id" value="<?= $task['id'] ?>" />
            <button type="submit" class="task-info" title="Click to toggle status" style="background:none; border:none; padding:0; text-align:left; width:100%; color:inherit; cursor:pointer;">
              <?= htmlspecialchars($task['task']) ?>
              <?php if ($task['description']): ?>
                <small><?= nl2br(htmlspecialchars($task['description'])) ?></small>
              <?php endif; ?>
              <?php if ($task['due_date']): ?>
                <small>Due: <?= htmlspecialchars($task['due_date']) ?></small>
              <?php endif; ?>
              <small>Priority: <?= htmlspecialchars($task['priority']) ?></small>
              <small>Category: <?= htmlspecialchars($task['category']) ?></small>
              <?php if ($is_admin): ?>
                <small>User: <?= htmlspecialchars($task['username']) ?></small>
              <?php endif; ?>
            </button>
          </form>
          <span class="status-label <?= $statusLabelClass ?>"><?= ucfirst($task['status']) ?></span>
          <form action="delete.php" method="GET" class="delete-form" style="margin-left: 10px;">
            <input type="hidden" name="id" value="<?= $task['id'] ?>" />
            <button class="btn-delete" type="submit" title="Delete task">×</button>
          </form>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
</main>

<button id="addTaskBtn" aria-label="Add new task">+</button>

<div id="popupForm" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="form-container">
    <button class="close-btn" aria-label="Close form">×</button>
    <h2>Add New Task</h2>
    <form id="taskForm" action="add.php" method="POST">
      <?php if ($is_admin): ?>
        <label for="user_id">User *</label>
        <select id="user_id" name="user_id" required>
          <?php foreach ($users as $user): ?>
            <option value="<?= $user['id'] ?>" <?= $user['id'] == $user_id ? 'selected' : '' ?>>
              <?= htmlspecialchars($user['username']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="error-message" id="user_id_error" role="alert" aria-live="polite"></span>
      <?php endif; ?>
      <label for="taskInput">Task Title *</label>
      <input type="text" id="taskInput" name="task" required maxlength="255" autocomplete="off" />
      <span class="error-message" id="taskInput_error" role="alert" aria-live="polite"></span>
      <label for="descInput">Description</label>
      <textarea id="descInput" name="description" rows="3" maxlength="1000"></textarea>
      <span class="error-message" id="descInput_error" role="alert" aria-live="polite"></span>
      <label for="dueInput">Due Date</label>
      <input type="date" id="dueInput" name="due_date" />
      <span class="error-message" id="dueInput_error" role="alert" aria-live="polite"></span>
      <label for="priorityInput">Priority *</label>
      <select id="priorityInput" name="priority" required>
        <option value="Low" selected>Low</option>
        <option value="Medium">Medium</option>
        <option value="High">High</option>
      </select>
      <span class="error-message" id="priorityInput_error" role="alert" aria-live="polite"></span>
      <label for="categoryInput">Category *</label>
      <select id="categoryInput" name="category" required>
        <option value="Work" selected>Work</option>
        <option value="Personal">Personal</option>
        <option value="Urgent">Urgent</option>
      </select>
      <span class="error-message" id="categoryInput_error" role="alert" aria-live="polite"></span>
      <button type="submit">Add Task</button>
    </form>
  </div>
</div>

<script>
  // Dark Mode toggle with localStorage
  const darkToggle = document.getElementById('darkToggle');
  darkToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark');
    if(document.body.classList.contains('dark')){
      localStorage.setItem('theme', 'dark');
      darkToggle.textContent = '☀️';
    } else {
      localStorage.removeItem('theme');
      darkToggle.textContent = '🌙';
    }
  });
  if(localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark');
    darkToggle.textContent = '☀️';
  }

  // Popup form open/close
  const addTaskBtn = document.getElementById('addTaskBtn');
  const popupForm = document.getElementById('popupForm');
  const closeBtn = popupForm.querySelector('.close-btn');

  addTaskBtn.addEventListener('click', () => {
    popupForm.classList.add('active');
    popupForm.setAttribute('aria-hidden', 'false');
    document.getElementById('taskInput').focus();
  });

  closeBtn.addEventListener('click', () => {
    popupForm.classList.remove('active');
    popupForm.setAttribute('aria-hidden', 'true');
    document.getElementById('taskForm').reset();
  });

  popupForm.addEventListener('click', (e) => {
    if (e.target === popupForm) {
      closeBtn.click();
    }
  });

  // Animate task deletion
  document.querySelectorAll('.delete-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (confirm('Delete this task?')) {
        const taskItem = form.closest('li.task');
        taskItem.classList.add('removing');
        taskItem.addEventListener('animationend', () => {
          form.submit();
        }, { once: true });
      }
    });
  });

  // Animate task status toggle
  document.querySelectorAll('.task-info-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      const taskItem = form.closest('li.task');
      taskItem.classList.add('toggling');
      taskItem.addEventListener('animationend', () => {
        taskItem.classList.remove('toggling');
      }, { once: true });
    });
  });

  // Animate filter form submission with validation
  const filterForm = document.querySelector('.filter-form');
  filterForm.addEventListener('submit', (e) => {
    let hasError = false;
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const startDateError = document.getElementById('start_date_error');
    const endDateError = document.getElementById('end_date_error');
    
    // Clear previous errors
    startDateError.textContent = '';
    endDateError.textContent = '';
    startDateInput.classList.remove('error');
    endDateInput.classList.remove('error');

    const startDate = startDateInput.value;
    const endDate = endDateInput.value;

    // Validate dates
    if (startDate && !endDate) {
      endDateError.textContent = 'End date is required if start date is set.';
      endDateInput.classList.add('error');
      hasError = true;
    } else if (!startDate && endDate) {
      startDateError.textContent = 'Start date is required if end date is set.';
      startDateInput.classList.add('error');
      hasError = true;
    } else if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
      endDateError.textContent = 'End date must be after or equal to start date.';
      endDateInput.classList.add('error');
      hasError = true;
    }

    if (hasError) {
      e.preventDefault();
      filterForm.classList.add('invalid');
      filterForm.addEventListener('animationend', () => {
        filterForm.classList.remove('invalid');
      }, { once: true });
      return;
    }

    const filterBtn = filterForm.querySelector('button[type="submit"]');
    filterBtn.classList.add('loading');
    taskList.style.opacity = '0.5';
    setTimeout(() => {
      filterBtn.classList.remove('loading');
      taskList.style.opacity = '1';
    }, 300);
  });

  // Task form client-side validation
  const taskForm = document.getElementById('taskForm');
  taskForm.addEventListener('submit', (e) => {
    let hasError = false;
    const taskInput = document.getElementById('taskInput');
    const descInput = document.getElementById('descInput');
    const dueInput = document.getElementById('dueInput');
    const taskError = document.getElementById('taskInput_error');
    const descError = document.getElementById('descInput_error');
    const dueError = document.getElementById('dueInput_error');

    // Clear previous errors
    taskError.textContent = '';
    descError.textContent = '';
    dueError.textContent = '';
    taskInput.classList.remove('error');
    descInput.classList.remove('error');
    dueInput.classList.remove('error');

    // Validate task title
    if (!taskInput.value.trim()) {
      taskError.textContent = 'Task title is required.';
      taskInput.classList.add('error');
      hasError = true;
    } else if (taskInput.value.length > 255) {
      taskError.textContent = 'Task title must be 255 characters or less.';
      taskInput.classList.add('error');
      hasError = true;
    }

    // Validate description
    if (descInput.value.length > 1000) {
      descError.textContent = 'Description must be 1000 characters or less.';
      descInput.classList.add('error');
      hasError = true;
    }

    // Validate due date
    if (dueInput.value && new Date(dueInput.value) < new Date().setHours(0, 0, 0, 0)) {
      dueError.textContent = 'Due date cannot be in the past.';
      dueInput.classList.add('error');
      hasError = true;
    }

    if (hasError) {
      e.preventDefault();
      taskForm.classList.add('invalid');
      taskForm.addEventListener('animationend', () => {
        taskForm.classList.remove('invalid');
      }, { once: true });
    }
  });

  // Drag & drop reorder with SortableJS
  const taskList = document.getElementById('task-list');
  Sortable.create(taskList, {
    animation: 150,
    handle: '.task-info',
    onEnd: () => {
      let order = [];
      taskList.querySelectorAll('li.task').forEach((li, i) => {
        order.push({id: li.getAttribute('data-id'), order: i});
      });
      fetch('reorder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: order }),
      })
      .then(res => res.json())
      .then(data => {
        if(data.success) {
          console.log('Order updated');
        } else {
          alert('Failed to update order');
        }
      })
      .catch(err => {
        alert('Error updating order');
        console.error(err);
      });
    }
  });
</script>
</body>
</html>