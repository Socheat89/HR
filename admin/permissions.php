<?php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// ============== INCLUDES & CONFIGURATION ==============
include 'includes/auth.php';
// Database connection
$conn = include 'includes/db.php';

// --- SCHEMA MIGRATION (AUTO-UPDATE) ---
// Checks if new columns exist, if not, adds them.
try {
    $columns = $conn->query("SHOW COLUMNS FROM menu_permissions")->fetchAll(PDO::FETCH_COLUMN);
    
    // Add menu_icon if missing
    if (!in_array('menu_icon', $columns)) {
        $conn->exec("ALTER TABLE menu_permissions ADD COLUMN menu_icon VARCHAR(255) DEFAULT 'fas fa-circle'");
    }
    // Add menu_link if missing
    if (!in_array('menu_link', $columns)) {
        $conn->exec("ALTER TABLE menu_permissions ADD COLUMN menu_link VARCHAR(255) DEFAULT '#'");
    }
    // Add is_visible if missing
    if (!in_array('is_visible', $columns)) {
        $conn->exec("ALTER TABLE menu_permissions ADD COLUMN is_visible TINYINT(1) DEFAULT 1");
    }

    // --- DATA MIGRATION (Populate known keys) ---
    $migrations = [
        'dashboard' => ['link' => 'dashboard.php', 'icon' => 'fas fa-home'],
        'manage_employees' => ['link' => 'dashboard.php?view=manage_employees', 'icon' => 'fas fa-users-cog'],
        'checklist' => ['link' => 'dashboard.php?view=checklist', 'icon' => 'fas fa-tasks'],
        'daily_reports' => ['link' => 'dashboard.php?view=reports', 'icon' => 'fas fa-file-alt'],
        'request_reports' => ['link' => 'dashboard.php?view=request_reports', 'icon' => 'fas fa-list-check'],
        'payroll' => ['link' => '#', 'icon' => 'fas fa-money-bill-wave'],
        'deductions_bonuses' => ['link' => 'dashboard.php?view=deductions_bonuses', 'icon' => 'fas fa-cogs'],
        'payroll_calculation' => ['link' => 'dashboard.php?view=payroll_calculation', 'icon' => 'fas fa-calculator'],
        'payroll_approval' => ['link' => 'dashboard.php?view=payroll_approval', 'icon' => 'fas fa-check-double'],
        'payroll_payslip' => ['link' => 'dashboard.php?view=payroll_payslip', 'icon' => 'fas fa-file-invoice-dollar'],
        'post_announcements' => ['link' => '../../posts/post_announcements.php', 'icon' => 'fas fa-bullhorn'],
        'inactive_users' => ['link' => 'dashboard.php?view=inactive_users', 'icon' => 'fas fa-user-slash'],
        'meetings' => ['link' => 'dashboard.php?view=meetings', 'icon' => 'fas fa-calendar-check'],
        'post_meeting' => ['link' => 'dashboard.php?view=post_meeting', 'icon' => 'fas fa-calendar-plus'],
        'post_lesson' => ['link' => '../../posts/post_lesson.php', 'icon' => 'fas fa-book'],
        'print_pdf' => ['link' => 'print_content.php', 'icon' => 'fas fa-print'],
        'upload_pdf' => ['link' => 'store_print_pdf.php', 'icon' => 'fas fa-file-pdf'],
        'pending_requests' => ['link' => 'pending_requests.php', 'icon' => 'fas fa-clock'],
        'processed_requests' => ['link' => 'view_processed_requests.php', 'icon' => 'fas fa-check-circle'],
        'view_lessons' => ['link' => 'lessons.php', 'icon' => 'fas fa-list'],
        'upload_lesson_docs' => ['link' => 'post_lesson_documents.php', 'icon' => 'fas fa-file-upload'],
        'permissions' => ['link' => 'dashboard.php?view=permissions', 'icon' => 'fas fa-shield-alt'],
        'settings' => ['link' => 'dashboard.php?view=settings', 'icon' => 'fas fa-cog'],
    ];

    foreach ($migrations as $key => $data) {
         // Using prepared statements for safety
         $sql = "UPDATE menu_permissions SET menu_link = ?, menu_icon = ? WHERE menu_key = ? AND (menu_icon = 'fas fa-circle' OR menu_icon IS NULL)";
         $stmt = $conn->prepare($sql);
         $stmt->execute([$data['link'], $data['icon'], $key]);
    }

} catch (Exception $e) {
    error_log("Schema/Data update failed: " . $e->getMessage());
}

// --- UNIFIED SECURITY CHECK ---
requirePermission('permissions', $conn);

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
            $menu_icon = $_POST['menu_icon'] ?? 'fas fa-circle';
            $menu_link = $_POST['menu_link'] ?? '#';
           
            $is_visible = (isset($_POST['is_visible']) && $_POST['is_visible'] == 'on') ? 1 : 0;
            // Also check for '1' just in case
            if (isset($_POST['is_visible']) && $_POST['is_visible'] == '1') $is_visible = 1;


            if ($id) { // Update existing item
                $stmt = $conn->prepare("UPDATE menu_permissions SET menu_key = ?, menu_name = ?, parent_key = ?, menu_order = ?, menu_icon = ?, menu_link = ?, is_visible = ? WHERE id = ?");
                $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, $menu_icon, $menu_link, $is_visible, $id]);
                $success_message = 'ម៉ឺនុយត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!';
            } else { // Insert new item
                $stmt = $conn->prepare("INSERT INTO menu_permissions (menu_key, menu_name, parent_key, menu_order, allowed_roles, menu_icon, menu_link, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                // Default new menus to be accessible by admin only.
                $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, 'admin', $menu_icon, $menu_link, $is_visible]);
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
    // Fetch all menu items
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

        if ($parent_id && isset($menu_map[$parent_id])) {
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
        $row_class = $is_child ? 'child-row bg-slate-50/50' : '';
        $padding_class = $is_child ? 'pl-5 md:pl-12' : 'pl-4';
        
        $icon_class = isset($menu['menu_icon']) ? $menu['menu_icon'] : 'fas fa-circle';
        // Fallback if null
        if(!$icon_class) $icon_class = 'fas fa-circle';
        
        $icon_display = '<i class="' . htmlspecialchars($icon_class) . ' w-6 text-center mr-3 text-amber-500 text-lg"></i>';
        
        $is_visible = isset($menu['is_visible']) && $menu['is_visible'];
        $menu_link = isset($menu['menu_link']) ? $menu['menu_link'] : '#';
        
        $opacity_class = $is_visible ? '' : 'opacity-60 bg-gray-100';
        ?>
        <tr class="<?php echo $row_class; ?> hover:bg-slate-50 transition-colors border-b border-gray-100 <?php echo $opacity_class; ?>">
            <!-- Menu Name & Key -->
            <td class="py-3 <?php echo $padding_class; ?> align-middle">
                <div class="flex items-center">
                    <?php echo $icon_display; ?>
                    <div class="flex flex-col">
                        <span class="font-medium text-slate-700 text-sm md:text-base"><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-[10px] uppercase tracking-wider text-slate-400 font-mono bg-slate-100 px-1 rounded border border-slate-200"><?php echo htmlspecialchars($menu['menu_key']); ?></span>
                            <?php if(!$is_visible): ?>
                                <span class="text-[10px] text-red-500 bg-red-50 px-1 rounded border border-red-100">Hidden</span>
                            <?php endif; ?>
                            <?php if ($is_child): ?>
                                <span class="text-[10px] text-slate-400"><i class="fas fa-level-up-alt rotate-90 ml-1"></i> Child</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </td>

            <!-- Link -->
            <td class="align-middle hidden md:table-cell">
                <span class="text-xs text-slate-500 font-mono truncate block max-w-[150px]" title="<?php echo htmlspecialchars($menu_link); ?>">
                    <?php echo htmlspecialchars($menu_link); ?>
                </span>
            </td>

            <!-- Permissions Checkboxes -->
            <td class="align-middle">
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($all_roles as $role): ?>
                        <label class="inline-flex items-center space-x-1.5 cursor-pointer bg-white hover:bg-slate-50 px-2 py-1 rounded border border-slate-200 hover:border-amber-300 transition-all select-none group shadow-sm">
                            <input type="checkbox" 
                                   class="form-checkbox w-3.5 h-3.5 text-amber-500 rounded border-slate-300 focus:ring-1 focus:ring-amber-500/50 bg-slate-50" 
                                   name="permissions[<?php echo htmlspecialchars($menu['menu_key']); ?>][]" 
                                   value="<?php echo htmlspecialchars($role); ?>" 
                                   <?php echo in_array($role, $current_roles) ? 'checked' : ''; ?>>
                            <span class="text-xs text-slate-600 group-hover:text-slate-800"><?php echo ucfirst($role); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </td>

            <!-- Action Buttons -->
            <td class="text-end pr-4 align-middle">
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-sm bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white border border-amber-100 transition-all shadow-sm" onclick='openMenuModal(<?php echo json_encode($menu); ?>)' title="កែប្រែ">
                        <i class="fas fa-edit"></i>
                    </button>
                    <form method="POST" action="permissions.php" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបម៉ឺនុយនេះមែនទេ?');" class="d-inline">
                        <input type="hidden" name="action" value="delete_menu_item">
                        <input type="hidden" name="menu_id" value="<?php echo $menu['id']; ?>">
                        <button type="submit" class="btn btn-sm bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 transition-all shadow-sm" title="លុប">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
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
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap (for Modals mostly, to keep compatibility) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-bg: #f8fafc; /* Slate 50 */
            --secondary-bg: #ffffff;
            --accent-color: #f59e0b;
        }
        body {
            background-color: var(--primary-bg);
            font-family: 'Noto Sans Khmer', sans-serif;
            color: #334155; /* Slate 700 */
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        /* Modal tweaks - Light Theme */
        .modal-content {
            background-color: #ffffff;
            color: #334155;
            border: 0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .modal-header {
            border-bottom: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .modal-footer {
            border-top: 1px solid #e2e8f0;
            background-color: #f8fafc;
        }
        .form-control, .form-select {
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
        }
        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            color: #0f172a;
            border-color: #f59e0b;
            box-shadow: 0 0 0 0.25rem rgba(245, 158, 11, 0.25);
        }
        .form-control::placeholder {
            color: #94a3b8;
        }
        .btn-close {
             filter: none; /* Reset distinct filter from dark mode */
        }
        input[type="checkbox"]:checked {
            background-color: #f59e0b;
            border-color: #f59e0b;
        }
    </style>
</head>
<body class="h-screen flex flex-col overflow-hidden text-sm bg-slate-50">
    
    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden p-4 md:p-6 relative">
        <!-- Background Accents -->
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-amber-50 to-transparent -z-10 pointer-events-none"></div>

        <!-- Header -->
        <header class="flex justify-between items-center mb-6 shrink-0">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-slate-800 mb-1 flex items-center">
                    <i class="fas fa-shield-alt mr-3 hidden md:inline text-amber-500"></i>
                    <?php echo htmlspecialchars($page_title); ?>
                </h1>
                <p class="text-slate-500 text-xs md:text-sm">គ្រប់គ្រងរចនាសម្ព័ន្ធ Sidebar និងសិទ្ធសម្រាប់អ្នកប្រើប្រាស់</p>
            </div>
            <div class="flex gap-3">
                <a href="dashboard.php" class="px-3 py-2 rounded-lg bg-white text-slate-600 hover:bg-slate-50 hover:text-amber-600 transition-colors border border-slate-200 shadow-sm flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i><span class="hidden md:inline">ត្រឡប់ក្រោយ</span>
                </a>
                <button onclick="openMenuModal()" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors shadow-lg shadow-emerald-600/20 flex items-center">
                    <i class="fas fa-plus mr-2"></i><span class="hidden md:inline">បន្ថែមម៉ឺនុយថ្មី</span>
                </button>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center shrink-0 shadow-sm">
                <i class="fas fa-check-circle mr-3 text-lg"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-600 flex items-center shrink-0 shadow-sm">
                <i class="fas fa-exclamation-circle mr-3 text-lg"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="flex-1 bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col shadow-xl">
            <form method="POST" action="permissions.php" class="flex flex-col h-full">
                <input type="hidden" name="action" value="save_permissions">
                
                <!-- Scrollable Table Area -->
                <div class="flex-1 overflow-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-600 sticky top-0 z-10 shadow-sm border-b border-slate-200">
                            <tr>
                                <th class="p-4 font-semibold w-[40%] md:w-[35%]">ឈ្មោះម៉ឺនុយ (Menu)</th>
                                <th class="p-4 font-semibold hidden md:table-cell w-[20%]">Link</th>
                                <th class="p-4 font-semibold">សិទ្ធិមើលឃើញ (Visible To)</th>
                                <th class="p-4 font-semibold text-right w-24">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($grouped_menus)): ?>
                                <tr>
                                    <td colspan="4" class="p-12 text-center text-slate-500 italic">
                                        <div class="flex flex-col items-center justify-center">
                                            <i class="fas fa-folder-open text-4xl mb-3 text-slate-300"></i>
                                            <p>មិនទាន់មានទិន្នន័យម៉ឺនុយទេ។</p> 
                                            <button type="button" onclick="openMenuModal()" class="mt-4 text-amber-500 hover:underline">ចាប់ផ្តើមបង្កើតម៉ឺនុយ</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php render_menu_rows($grouped_menus, $all_roles); ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Action -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end shrink-0 z-20">
                     <button type="submit" class="px-6 py-2.5 rounded-lg bg-amber-600 text-white hover:bg-amber-500 transition-colors font-semibold shadow-lg shadow-amber-600/20 flex items-center">
                        <i class="fas fa-save mr-2"></i>រក្សាទុកការផ្លាស់ប្តូរសិទ្ធ
                    </button>
                </div>
            </form>
        </div>
    </main>

    <!-- Modal for Add/Edit Menu Item -->
    <div class="modal fade" id="menuModal" tabindex="-1" aria-labelledby="menuModalLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-2xl border-0 ring-1 ring-slate-900/5">
                <form method="POST" action="permissions.php">
                    <input type="hidden" name="action" value="save_menu_item">
                    <input type="hidden" name="menu_id" id="menu_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title font-bold text-lg text-slate-800" id="menuModalLabel">
                            <i class="fas fa-bars mr-2 text-amber-500"></i>កំណត់ម៉ឺនុយ
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body space-y-4 p-6 bg-white">
                        <div class="grid grid-cols-2 gap-4">
                            <!-- Menu Key -->
                            <div class="col-span-2 md:col-span-1">
                                <label for="menu_key" class="block text-xs font-medium text-slate-500 mb-1">Key (e.g. 'dashboard')</label>
                                <input type="text" class="form-control text-sm" id="menu_key" name="menu_key" required placeholder="unique_key">
                            </div>
                            
                            <!-- Menu Name -->
                            <div class="col-span-2 md:col-span-1">
                                <label for="menu_name" class="block text-xs font-medium text-slate-500 mb-1">ឈ្មោះបង្ហាញ (Label)</label>
                                <input type="text" class="form-control text-sm" id="menu_name" name="menu_name" required placeholder="My Menu">
                            </div>
                        </div>

                        <!-- Icon & Link -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label for="menu_icon" class="block text-xs font-medium text-slate-500 mb-1">Icon Class (FontAwesome)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-slate-100 border-slate-300 text-slate-500 py-1"><i class="fas fa-icons text-xs"></i></span>
                                    <input type="text" class="form-control text-sm" id="menu_icon" name="menu_icon" value="fas fa-circle" placeholder="fas fa-home">
                                </div>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label for="menu_link" class="block text-xs font-medium text-slate-500 mb-1">Link URL</label>
                                <input type="text" class="form-control text-sm" id="menu_link" name="menu_link" value="#" placeholder="page.php">
                            </div>
                        </div>

                        <!-- Parent & Order -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2 md:col-span-1">
                                <label for="parent_key" class="block text-xs font-medium text-slate-500 mb-1">ជាម៉ឺនុយកូនរបស់ (Parent)</label>
                                <select class="form-select text-sm" id="parent_key" name="parent_key">
                                    <option value="">-- ជាម៉ឺនុយមេ (Root) --</option>
                                    <?php foreach ($all_menus as $parent_option): ?>
                                        <?php if (empty($parent_option['parent_key'])): ?>
                                        <option value="<?php echo htmlspecialchars($parent_option['menu_key']); ?>"><?php echo htmlspecialchars($parent_option['menu_name']); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-2 md:col-span-1">
                                <label for="menu_order" class="block text-xs font-medium text-slate-500 mb-1">លំដាប់ (Order)</label>
                                <input type="number" class="form-control text-sm" id="menu_order" name="menu_order" value="0">
                            </div>
                        </div>

                        <!-- Visibility Toggle -->
                        <div class="flex items-center space-x-3 bg-slate-50 p-3 rounded border border-slate-200">
                            <div class="form-check form-switch cursor-pointer">
                                <input class="form-check-input cursor-pointer" type="checkbox" id="is_visible" name="is_visible" checked>
                                <label class="form-check-label text-sm text-slate-600 cursor-pointer" for="is_visible">បង្ហាញក្នុង Sidebar (Visible)</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="px-4 py-2 rounded bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors text-sm font-medium" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="px-4 py-2 rounded bg-amber-600 text-white hover:bg-amber-500 transition-colors text-sm font-medium shadow-lg shadow-amber-600/20">រក្សាទុក</button>
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
        const menuIconInput = document.getElementById('menu_icon');
        const menuLinkInput = document.getElementById('menu_link');
        const parentKeySelect = document.getElementById('parent_key');
        const menuOrderInput = document.getElementById('menu_order');
        const isVisibleInput = document.getElementById('is_visible');
        
        function openMenuModal(data = null) {
            // Reset form
            menuIdInput.form.reset();
            
            if (data) { // Edit mode
                modalLabel.innerHTML = '<i class="fas fa-edit mr-2 text-amber-500"></i>កែប្រែម៉ឺនុយ';
                menuIdInput.value = data.id;
                menuNameInput.value = data.menu_name;
                menuKeyInput.value = data.menu_key;
                menuIconInput.value = data.menu_icon || 'fas fa-circle';
                menuLinkInput.value = data.menu_link || '#';
                parentKeySelect.value = data.parent_key || '';
                menuOrderInput.value = data.menu_order;
                isVisibleInput.checked = (data.is_visible == 1);
                
                // Disable selecting itself as a parent
                for (let option of parentKeySelect.options) {
                    option.disabled = (option.value === data.menu_key);
                }

            } else { // Add mode
                modalLabel.innerHTML = '<i class="fas fa-plus mr-2 text-amber-500"></i>បន្ថែមម៉ឺនុយថ្មី';
                isVisibleInput.checked = true;
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