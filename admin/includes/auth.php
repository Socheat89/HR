<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if a user is currently logged in.
 *
 * @return bool True if the user is logged in, false otherwise.
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Attempts to log in a user with the given credentials.
 *
 * @param string $username The username.
 * @param string $password The password.
 * @param PDO $conn The database connection object.
 * @return bool True on successful login, false otherwise.
 */
function login($username, $password, $conn) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set session variables upon successful login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Destroys the current session to log the user out.
 */
function logout() {
    session_destroy();
}


/**
 * (កំណែដែលបានកែតម្រូវ) ពិនិត្យមើលថាតើអ្នកប្រើប្រាស់មានសិទ្ធិឬទេ
 *
 * @param string $menu_key The key of the menu/permission to check.
 * @param PDO $conn The database connection object.
 * @return bool True if the user has permission, false otherwise.
 */
/**
 * (កំណែដែលបានកែតម្រូវ) ពិនិត្យមើលថាតើអ្នកប្រើប្រាស់មានសិទ្ធិឬទេ
 *
 * @param string $menu_key The key of the menu/permission to check.
 * @param PDO $conn The database connection object.
 * @return bool True if the user has permission, false otherwise.
 */
function hasPermission($menu_key, $conn) {
    // 1. ពិនិត្យមើលថាតើបានឡុកអ៊ីន និងមាន role ឬទេ
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }

    $role = $_SESSION['role'];

    // 2. *** ច្បាប់ពិសេសសម្រាប់ទិន្នន័យ Payroll ***
    // សម្រាប់ key នេះ នឹងមិនផ្តល់សិទ្ធិ admin ដោយស្វ័យប្រវត្តិទេ ត្រូវតែពិនិត្យពីមូលដ្ឋានទិន្នន័យតែប៉ុណ្ណោះ
    if ($menu_key === 'manage_payroll_data') {
        try {
            $stmt = $conn->prepare("SELECT 1 FROM permissions WHERE role = ? AND permission_key = ? LIMIT 1");
            $stmt->execute([$role, $menu_key]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    // 3. *** ច្បាប់ទូទៅសម្រាប់សិទ្ធិផ្សេងទៀតទាំងអស់ ***
    // សម្រាប់សិទ្ធិផ្សេងទៀតទាំងអស់, role 'admin' នឹងត្រូវបានអនុញ្ញាតជានិច្ច
    if ($role === 'admin') {
        return true;
    }

    // 4. ពិនិត្យមើលនៅក្នុងមូលដ្ឋានទិន្នន័យ (Using new permissions table)
    try {
        $stmt = $conn->prepare("SELECT 1 FROM permissions WHERE role = ? AND permission_key = ? LIMIT 1");
        $stmt->execute([$role, $menu_key]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}


/**
 * អនុគមន៍សុវត្ថិភាព៖ ពិនិត្យសិទ្ធិ និង redirect ប្រសិនបើគ្មានសិទ្ធិ
 *
 * @param string $menu_key The key of the menu to check.
 * @param PDO $conn The database connection object.
 */
function requirePermission($menu_key, $conn) {
    if (!hasPermission($menu_key, $conn)) {
        $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិចូលទំព័រនេះទេ!';
        header("Location: dashboard.php");
        exit();
    }
}

/**
 * Checks if a user's role has permission to view a menu item.
 * Uses the new permissions table.
 */
function can_view_menu($menu_key, $user_role, $conn) {
    // 1. If admin, always true
    if ($user_role === 'admin') {
        return true;
    }

    // 2. specialized check for payroll data not needed here as this is for MENU visibility, 
    // but if the menu itself is 'manage_payroll_data' (unlikely for a menu item), we might want consistency.
    // However, usually menu items are like 'payroll', 'settings', etc.
    
    // 3. Check DB
    try {
        $stmt = $conn->prepare("SELECT 1 FROM permissions WHERE role = ? AND permission_key = ? LIMIT 1");
        $stmt->execute([$user_role, $menu_key]);
        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}
?>