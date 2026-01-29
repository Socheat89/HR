<?php
// FILE: sidebar.php
// ត្រូវប្រាកដថា $current_page និង $pending_request_count_nav មានតម្លៃពីไฟล์ដែល include វា
?>
<nav class="sidebar">
    <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង
    </a>
    <a href="index.php" class="nav-item <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-box-archive"></i> ទំនិញ
    </a>
<a href="reports.php" class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-chart-simple"></i>
    <span>របាយការណ៍</span>
    <?php if (isset($low_stock_count) && $low_stock_count > 0): ?>
        <span class="notification-badge"><?php echo $low_stock_count; ?></span>
    <?php endif; ?>
</a>
    <a href="stock_counting.php" class="nav-item <?php echo ($current_page == 'stock_counting.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-clipboard-list"></i> ការរាប់ស្តុក
    </a>
    <a href="user_request_form.php" class="nav-item <?php echo ($current_page == 'user_request_form.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-file-pen"></i> ស្នើសុំទំនិញ
    </a>
    <a href="review_requests.php" class="nav-item <?php echo ($current_page == 'review_requests.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-magnifying-glass-chart"></i> ពិនិត្យសំណើរ
        
        <?php if (isset($pending_request_count_nav) && $pending_request_count_nav > 0): ?>
            <span class="notification-badge"><?php echo $pending_request_count_nav; ?></span>
        <?php endif; ?>
    </a>
    <a href="purchase_stock_in.php" class="nav-item <?php echo ($current_page == 'purchase_stock_in.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-truck-fast"></i> ផ្ទេរដោយផ្ទាល់
    </a>
</nav>