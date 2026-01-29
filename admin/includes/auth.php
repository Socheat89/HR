<?php
session_start();

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
function hasPermission($menu_key, $conn) {
    // 1. ពិនិត្យមើលថាតើបានឡុកអ៊ីន និងមាន role ឬទេ
    if (!isLoggedIn() || !isset($_SESSION['role'])) {
        return false;
    }

    // 2. ប្រើ static variable ដើម្បី cache តម្លៃ permission, ការពារការ query ដដែលៗនៅក្នុងទំព័រតែមួយ
    static $permissions = null;
    if ($permissions === null) {
        $permissions = [];
        try {
            $stmt = $conn->prepare("SELECT menu_key, allowed_roles FROM menu_permissions");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $permissions[$row['menu_key']] = !empty($row['allowed_roles']) ? explode(',', $row['allowed_roles']) : [];
            }
        } catch (Exception $e) {
            error_log('Failed to fetch menu permissions: ' . $e->getMessage());
        }
    }

    // 3. *** ច្បាប់ពិសេសសម្រាប់ទិន្នន័យ Payroll ***
    // សម្រាប់ key នេះ នឹងមិនផ្តល់សិទ្ធិ admin ដោយស្វ័យប្រវត្តិទេ ត្រូវតែពិនិត្យពីមូលដ្ឋានទិន្នន័យតែប៉ុណ្ណោះ
    if ($menu_key === 'manage_payroll_data') {
        // ពិនិត្យមើលថា key នេះមានពិតមែន ហើយ role របស់អ្នកប្រើប្រាស់មាននៅក្នុងបញ្ជីដែលបានអនុញ្ញាតឬទេ
        if (isset($permissions[$menu_key])) {
            return in_array($_SESSION['role'], $permissions[$menu_key]);
        }
        // ប្រសិនបើ key 'manage_payroll_data' មិនមាននៅក្នុងតារាងទាល់តែសោះ ត្រូវបដិសេធដើម្បីសុវត្ថិភាព
        return false;
    }

    // 4. *** ច្បាប់ទូទៅសម្រាប់សិទ្ធិផ្សេងទៀតទាំងអស់ ***
    // សម្រាប់សិទ្ធិផ្សេងទៀតទាំងអស់, role 'admin' នឹងត្រូវបានអនុញ្ញាតជានិច្ច
    if ($_SESSION['role'] === 'admin') {
        return true;
    }

    // 5. សម្រាប់ role ផ្សេងទៀតដែលមិនមែនជា admin, ត្រូវពិនិត្យមើលតាមអ្វីដែលបានកំណត់នៅក្នុងមូលដ្ឋានទិន្នន័យ
    if (isset($permissions[$menu_key])) {
        return in_array($_SESSION['role'], $permissions[$menu_key]);
    }

    // 6. ប្រសិនបើ key មិនត្រូវបានកំណត់នៅក្នុងមូលដ្ឋានទិន្នន័យ ហើយអ្នកប្រើប្រាស់មិនមែនជា admin, ត្រូវបដិសេធការចូលប្រើ
    return false;
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
?>