<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// ============== INCLUDES & CONFIGURATION ==============
include 'includes/auth.php';
$conn = include 'includes/db.php';

// --- UNIFIED SECURITY CHECK ---
// This uses your permission system to check if the current user's role
// has access to the 'permissions' menu key itself.
// Make sure this entry exists in your `menu_permissions` table and your role has access.
requirePermission('permissions', $conn);

// --- Ensure core invisible permission keys exist (e.g., manage_payroll_data) ---
// These keys are used for feature gating, not for sidebar links.
try {
    // Check if 'manage_payroll_data' exists; if not, create it with admin allowed by default
    $stmt = $conn->prepare("SELECT COUNT(*) FROM menu_permissions WHERE menu_key = ?");
    $stmt->execute(['manage_payroll_data']);
    $exists = (int)$stmt->fetchColumn() > 0;
    if (!$exists) {
        $insert = $conn->prepare(
            "INSERT INTO menu_permissions (menu_key, menu_name, parent_key, menu_order, allowed_roles) VALUES (?, ?, ?, ?, ?)"
        );
        // Note: parent_key is NULL because this is not a visible sidebar menu; it gates data visibility.
        $insert->execute(['manage_payroll_data', 'Manage Payroll Data', null, 0, 'admin']);
    }
} catch (Exception $e) {
    // Do not block the page; just log for admins to review
    error_log('Failed to ensure core permission key manage_payroll_data: ' . $e->getMessage());
}


// ============== INITIAL VARIABLES ==============
$page_title = "គ្រប់គ្រង Sidebar និងការកំណត់សិទ្ធ";
$success_message = '';
$error_message = '';
$all_roles = ['admin', 'employee', 'accounting', 'hr', 'administration']; // Define all possible roles


// ============== POST REQUEST HANDLING (CRUD & PERMISSIONS) ==============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        $action = $_POST['action'] ?? '';

        // --- ACTION: Save Menu Item (Add or Edit) ---
        if ($action === 'save_menu_item') {
            $id = $_POST['menu_id'] ?? null;
            $menu_key = $_POST['menu_key'];
            $menu_name = $_POST['menu_name'];
            $parent_key = empty($_POST['parent_key']) ? null : $_POST['parent_key'];
            $menu_order = $_POST['menu_order'] ?? 0;

            if ($id) { // Update existing item
                $stmt = $conn->prepare("UPDATE menu_permissions SET menu_key = ?, menu_name = ?, parent_key = ?, menu_order = ? WHERE id = ?");
                $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, $id]);
                $success_message = 'ម៉ឺនុយត្រូវបានធ្វើបច្ចុប្បន្នភាពโดยជោគជ័យ!';
            } else { // Insert new item
                $stmt = $conn->prepare("INSERT INTO menu_permissions (menu_key, menu_name, parent_key, menu_order, allowed_roles) VALUES (?, ?, ?, ?, ?)");
                // Default new menus to be accessible by admin only. Can be changed later in the UI.
                $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, 'admin']);
                $success_message = 'ម៉ឺនុយថ្មីត្រូវបានបន្ថែមដោយជោគជ័យ!';
            }
        }

        // --- ACTION: Delete Menu Item ---
        elseif ($action === 'delete_menu_item') {
            $id = $_POST['menu_id'];

            // First, get the menu_key of the item to be deleted
            $stmt_get_key = $conn->prepare("SELECT menu_key FROM menu_permissions WHERE id = ?");
            $stmt_get_key->execute([$id]);
            $menu_key_to_delete = $stmt_get_key->fetchColumn();

            // Check if it's a parent to any other item
            $stmt_check_children = $conn->prepare("SELECT COUNT(*) FROM menu_permissions WHERE parent_key = ?");
            $stmt_check_children->execute([$menu_key_to_delete]);
            if ($stmt_check_children->fetchColumn() > 0) {
                throw new Exception('មិនអាចលុបបានទេ ព្រោះម៉ឺនុយនេះមានម៉ឺនុយកូន។ សូមលុបម៉ឺនុយកូនជាមុនសិន។');
            }

            // Proceed with deletion
            $stmt_delete = $conn->prepare("DELETE FROM menu_permissions WHERE id = ?");
            $stmt_delete->execute([$id]);
            $success_message = 'ម៉ឺនុយត្រូវបានលុបដោយជោគជ័យ!';
        }
        
        // --- ACTION: Save All Permissions ---
        elseif ($action === 'save_permissions') {
            $stmt_get_keys = $conn->query("SELECT menu_key FROM menu_permissions");
            $all_menu_keys = $stmt_get_keys->fetchAll(PDO::FETCH_COLUMN);

            foreach ($all_menu_keys as $menu_key) {
                $allowed_roles = isset($_POST['permissions'][$menu_key]) ? $_POST['permissions'][$menu_key] : [];
                $roles_string = implode(',', $allowed_roles);
                $stmt_update = $conn->prepare("UPDATE menu_permissions SET allowed_roles = ? WHERE menu_key = ?");
                $stmt_update->execute([$roles_string, $menu_key]);
            }
            $success_message = 'ការកំណត់សិទ្ធត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!';
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = 'មានបញ្ហា! ' . $e->getMessage();
    }
}


// ============== DATA FETCHING FOR DISPLAY ==============
try {
    // Fetch all menu items, ordered to make grouping easier
    $stmt_menus = $conn->query("SELECT * FROM menu_permissions ORDER BY parent_key ASC, menu_order ASC, menu_name ASC");
    $all_menus = $stmt_menus->fetchAll(PDO::FETCH_ASSOC);

    // Organize menus into a parent-child structure
    $grouped_menus = [];
    $menu_map = []; // Helper map for quick access
    foreach ($all_menus as $menu) {
        $menu['children'] = [];
        $menu_map[$menu['id']] = $menu;
    }
    
    $tree = [];
    foreach ($menu_map as $id => &$node) {
        // Find parent key from menu_key
        $parent_id = null;
        if ($node['parent_key']) {
            foreach($menu_map as $potential_parent) {
                if ($potential_parent['menu_key'] === $node['parent_key']) {
                    $parent_id = $potential_parent['id'];
                    break;
                }
            }
        }

        if ($parent_id) {
            $menu_map[$parent_id]['children'][] =& $node;
        } else {
            $tree[] =& $node;
        }
    }
    unset($node);
    $grouped_menus = $tree;

} catch (Exception $e) {
    $error_message = 'មិនអាចទាញយកទិន្នន័យបានទេ: ' . $e->getMessage();
    $grouped_menus = [];
}


// ============== HELPER FUNCTION TO RENDER MENU ROWS RECURSIVELY ==============
function render_menu_rows($menus, $all_roles, $is_child = false) {
    foreach ($menus as $menu) {
        $current_roles = !empty($menu['allowed_roles']) ? explode(',', $menu['allowed_roles']) : [];
        $row_class = $is_child ? 'child-row' : '';
        $icon = !empty($menu['children']) ? '<i class="fas fa-folder text-accent-color me-2"></i>' : ($is_child ? '<i class="fas fa-file-alt me-3 text-secondary"></i>' : '<i class="fas fa-bars me-2"></i>');
        ?>
        <tr class="<?php echo $row_class; ?>">
            <!-- Menu Name & Key -->
            <td class="font-semibold align-middle">
                <?php echo $icon; ?>
                <span><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                <span class="d-block text-xs text-secondary ms-4">(key: <?php echo htmlspecialchars($menu['menu_key']); ?>)</span>
            </td>

            <!-- Permissions Checkboxes -->
            <td>
                <div class="d-flex flex-wrap gap-4">
                    <?php foreach ($all_roles as $role): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="permissions[<?php echo htmlspecialchars($menu['menu_key']); ?>][]" value="<?php echo htmlspecialchars($role); ?>" id="perm_<?php echo $menu['menu_key'] . '_' . $role; ?>" <?php echo in_array($role, $current_roles) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="perm_<?php echo $menu['menu_key'] . '_' . $role; ?>"><?php echo ucfirst($role); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </td>

            <!-- Action Buttons -->
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-warning me-2" onclick='openMenuModal(<?php echo json_encode($menu); ?>)'>
                    <i class="fas fa-edit"></i> កែប្រែ
                </button>
                <form method="POST" action="permissions.php" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបម៉ឺនុយនេះមែនទេ?');" class="d-inline">
                    <input type="hidden" name="action" value="delete_menu_item">
                    <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> លុប
                    </button>
                </form>
            </td>
        </tr>
        <?php
        // If this menu has children, render them recursively
        if (!empty($menu['children'])) {
            render_menu_rows($menu['children'], $all_roles, true);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-bg: #161b22; --secondary-bg: #0d1117; --card-bg: rgba(22, 27, 34, 0.9); --border-color: rgba(255, 255, 255, 0.1); --accent-color: #ffd700; --accent-hover: #ffea70; --text-primary: #f0f6fc; --text-secondary: #c9d1d9; --success: #2ea043; --danger: #da3633; }
        body { background-color: var(--primary-bg); font-family: 'Noto Sans Khmer', sans-serif; color: var(--text-primary); }
        .card-base { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; }
        .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); border: none; padding: 10px 20px; border-radius: 8px; font-weight: 700; }
        .form-check-input:checked { background-color: var(--accent-color); border-color: var(--accent-color); }
        .modal-content { background-color: var(--secondary-bg); color: var(--text-primary); }
        .table-dark { --bs-table-bg: transparent; }
        .child-row td { padding-left: 2.5rem !important; background-color: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
    </style>
</head>
<body class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <header class="d-flex justify-content-between align-items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-accent-hover"><?php echo htmlspecialchars($page_title); ?></h1>
                <p class="text-text-secondary mt-2">នៅទីនេះអ្នកអាចគ្រប់គ្រងโครงสร้าง Sidebar និងកំណត់សិទ្ធសម្រាប់តួនាទីនីមួយៗ។</p>
            </div>
            <button class="btn btn-success" onclick="openMenuModal()"><i class="fas fa-plus mr-2"></i> បន្ថែមម៉ឺនុយថ្មី</button>
        </header>

        <!-- Display Success/Error Messages -->
        <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

        <div class="card-base p-6">
            <form method="POST" action="permissions.php">
                <input type="hidden" name="action" value="save_permissions">
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle">
                        <thead>
                            <tr class="text-accent-color">
                                <th style="width: 30%;">ឈ្មោះម៉ឺនុយ</th>
                                <th>តួនាទីដែលអាចមើលឃើញ</th>
                                <th class="text-end">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php render_menu_rows($grouped_menus, $all_roles); ?>
                        </tbody>
                    </table>
                </div>
                <div class="mt-6 text-end">
                    <button type="submit" class="btn-primary"><i class="fas fa-save mr-2"></i> រក្សាទុកការផ្លាស់ប្តូរសិទ្ធ</button>
                </div>
            </form>
        </div>
    </main>

    <!-- Modal for Add/Edit Menu Item -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="permissions.php">
                    <input type="hidden" name="action" value="save_menu_item">
                    <input type="hidden" name="menu_id" id="menu_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="menuModalLabel"></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="menu_name" class="form-label">ឈ្មោះម៉ឺនុយ (Menu Name)</label>
                            <input type="text" class="form-control bg-dark text-white" id="menu_name" name="menu_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="menu_key" class="form-label">Key (សម្រាប់សម្គាល់, e.g., 'dashboard', 'report_sales')</label>
                            <input type="text" class="form-control bg-dark text-white" id="menu_key" name="menu_key" required>
                        </div>
                        <div class="mb-3">
                            <label for="parent_key" class="form-label">ជាម៉ឺនុយកូនរបស់ (Parent Menu)</label>
                            <select class="form-select bg-dark text-white" id="parent_key" name="parent_key">
                                <option value="">-- គ្មាន (ជាម៉ឺនុយមេ) --</option>
                                <?php foreach ($all_menus as $parent_option): ?>
                                    <?php if (empty($parent_option['parent_key'])): // Only top-level items can be parents ?>
                                    <option value="<?php echo htmlspecialchars($parent_option['menu_key']); ?>"><?php echo htmlspecialchars($parent_option['menu_name']); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                         <div class="mb-3">
                            <label for="menu_order" class="form-label">លេខរៀង (Menu Order)</label>
                            <input type="number" class="form-control bg-dark text-white" id="menu_order" name="menu_order" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn btn-primary">រក្សាទុក</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const menuModalEl = document.getElementById('menuModal');
        const menuModal = new bootstrap.Modal(menuModalEl);

        const modalLabel = document.getElementById('menuModalLabel');
        const menuIdInput = document.getElementById('menu_id');
        const menuNameInput = document.getElementById('menu_name');
        const menuKeyInput = document.getElementById('menu_key');
        const parentKeySelect = document.getElementById('parent_key');
        const menuOrderInput = document.getElementById('menu_order');
        
        function openMenuModal(data = null) {
            // Reset form
            menuIdInput.form.reset();
            
            if (data) { // Edit mode
                modalLabel.innerText = 'កែប្រែម៉ឺនុយ';
                menuIdInput.value = data.id;
                menuNameInput.value = data.menu_name;
                menuKeyInput.value = data.menu_key;
                parentKeySelect.value = data.parent_key || '';
                menuOrderInput.value = data.menu_order;
                
                // Disable selecting itself as a parent
                for (let option of parentKeySelect.options) {
                    option.disabled = (option.value === data.menu_key);
                }

            } else { // Add mode
                modalLabel.innerText = 'បន្ថែមម៉ឺនុយថ្មី';
                 // Enable all options
                for (let option of parentKeySelect.options) {
                    option.disabled = false;
                }
            }
            
            menuModal.show();
        }
    </script>
</body>
</html>