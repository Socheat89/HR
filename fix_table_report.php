<?php
$f = 'admin/table_report.php';
$c = file($f);

// Debug: check lines to be removed
// Handle potential BOM in output checks
$line51 = trim($c[51]);
$line52 = trim($c[52]);

echo "Line 52 (index 51): " . $line51 . "\n";
echo "Line 53 (index 52): " . $line52 . "\n";

// Expected: Line 52 is '?>', Line 53 contains 'currentUserFullName'
// We use loosely check for '?>'
if (strpos($line51, '?>') !== false && strpos($line52, 'currentUserFullName') !== false) {
    echo "Pattern matched. Proceeding with fix.\n";
    
    // Remove lines 52-58 (indices 51-57) - 7 lines
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
    
    // Replace lines 40-51 (indices 39-50) - 12 lines
    array_splice($c, 39, 12, [$headerLogic]);
    
    file_put_contents($f, implode("", $c));
    echo "File updated successfully.\n";
} else {
    echo "Pattern DID NOT match. Aborting.\n";
}
?>
