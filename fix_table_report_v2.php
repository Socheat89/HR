<?php
$f = 'admin/table_report.php';
$c = file($f);

// Remove lines 52-58 (indices 51-57)
array_splice($c, 51, 7);

// Header logic to replace lines 40-51 (indices 39-50)
$headerLogic = <<<'EOD'
// Fetch current user details
$currentUserFullName = 'ភ្ញៀវ';
$currentUserId = null;
$isAdmin = false;
$editableUserIds = [];

if (isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
    $stmtUser = $pdo->prepare("SELECT full_name, role, manager_id FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$currentUserId]);
    $user = $stmtUser->fetch();
    if ($user) {
        $currentUserFullName = $user['full_name'];
        // Determine admin status
        $role = $_SESSION['role'] ?? $user['role'];
        $isAdmin = ($role === 'admin');
    }
} else {
    header("Location: ../auth/login.php");
    exit;
}

EOD;

// Replace lines 40-51 (indices 39-50)
array_splice($c, 39, 12, [$headerLogic]);

file_put_contents($f, implode("", $c));
echo "Fixed successfully.\n";
?>
