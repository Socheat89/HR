<?php
// includes/sidebar.php

// Ensure DB connection is available
if (!isset($conn)) {
    // This path might need adjustment based on your file structure
    // Assuming db.php is in the 'includes' folder as well.
    $conn_path = __DIR__ . '/db.php'; 
    if (file_exists($conn_path)) {
        $conn = include $conn_path;
    } else {
        // Fallback for when sidebar is included from root directory
        $conn = include 'includes/db.php';
    }
}

/**
 * Checks if a user's role has permission to view a menu item.
 * Caches the permissions on the first call to improve performance.
 */
function can_view_menu($menu_key, $user_role, $conn) {
    // --- START OF MODIFICATION ---
    // The special check for 'admin' has been removed.
    /* 
    // OLD CODE:
    if ($user_role === 'admin') {
        return true;
    }
    */
    // --- END OF MODIFICATION ---

    // Use a static variable to cache permissions to avoid multiple DB calls per page load.
    static $permissions = null;
    if ($permissions === null) {
        $permissions = []; // Initialize as an empty array
        try {
            $stmt = $conn->prepare("SELECT menu_key, allowed_roles FROM menu_permissions");
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                // Store roles as an array for easy lookup
                $permissions[$row['menu_key']] = !empty($row['allowed_roles']) ? explode(',', $row['allowed_roles']) : [];
            }
        } catch (Exception $e) {
            // Log error if possible, and continue with empty permissions for security.
            error_log('Failed to fetch menu permissions: ' . $e->getMessage());
        }
    }

    // Check if the menu key exists and if the user's role is in the allowed list.
    if (isset($permissions[$menu_key])) {
        // Now this check applies to ALL roles, including 'admin'
        return in_array($user_role, $permissions[$menu_key]);
    }
    
    // Default to false if the menu key is not found in the permissions table.
    return false;
}

// Get current page context
$current_page = basename($_SERVER['PHP_SELF']);
$current_view = isset($_GET['view']) ? $_GET['view'] : '';
$current_user_role = $_SESSION['role'] ?? 'employee';

// Function to check if a navigation link should be marked as active
function isActive($page, $view = '') {
    global $current_page, $current_view;
    if ($current_page === 'dashboard.php' && !empty($view)) {
        return $current_view === $view;
    }
    if ($current_page === 'dashboard.php' && empty($current_view) && $page === 'dashboard.php' && empty($view)) {
        return true;
    }
    return $current_page === $page;
}

// --- START: NEW LOGIC TO CORRECTLY CHECK PERMISSIONS ---

// Define the keys for all payroll sub-menus
$payroll_child_keys = [
    'deductions_bonuses',
    'payroll_calculation',
    'payroll_approval',
    'payroll_payslip'
];

// Check if the user can see AT LEAST ONE of the payroll sub-menus
$can_view_any_payroll_child = false;
foreach ($payroll_child_keys as $key) {
    if (can_view_menu($key, $current_user_role, $conn)) {
        $can_view_any_payroll_child = true;
        break; // Stop checking once we find one permission
    }
}

// The main payroll dropdown should appear if the user has permission for the parent OR for any child menu
$show_payroll_dropdown = can_view_menu('payroll', $current_user_role, $conn) || $can_view_any_payroll_child;

// Determine if the current page is a payroll page to make the menu active
$isPayrollPageActive = in_array($current_view, $payroll_child_keys);

// --- END: NEW LOGIC ---

// Standardized classes for sidebar links so all pages render the same
$baseLinkClass = 'flex items-center p-3 rounded-lg transition-all duration-200 text-white hover:bg-white/10 hover:text-cyan-400 border-l-4 border-transparent hover:border-cyan-400';
$activeLinkClass = 'bg-white/20 text-cyan-400 font-bold border-l-4 border-cyan-400';

// Icon classes
$baseIconClass = 'w-5 text-center';
$activeIconClass = 'w-5 text-center';

// Payroll submenu classes (show/hide based on active state)
$payrollSubmenuClass = $isPayrollPageActive ? 'ml-4 mt-2 space-y-1' : 'hidden ml-4 mt-2 space-y-1';


?>

<aside class="w-64 bg-gradient-to-br from-slate-800 via-slate-900 to-indigo-900 text-white p-4 hidden md:block transition-transform duration-300 ease-in-out h-screen custom-scrollbar overflow-y-auto shadow-2xl border-r border-slate-700">
    <div class="flex flex-col items-center mb-6">
        <img src="https://i.ibb.co/Q3LXcgyX/Logo-Van-Van-1.png" alt="Logo" class="w-20 h-20 mb-2 rounded-full shadow-lg bg-white object-contain border-2 border-cyan-400">
        <h2 class="text-2xl font-bold text-yellow-400 text-center">HR System</h2>
    </div>
    <nav>
        <ul class="space-y-2">
        <?php if (can_view_menu('dashboard', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php" class="<?php echo $baseLinkClass . (isActive('dashboard.php','') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-home <?php echo isActive('dashboard.php','') ? $activeIconClass : $baseIconClass; ?>"></i>
                <span>ផ្ទាំងគ្រប់គ្រង</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- START: NEW MENU ITEM FOR MANAGE EMPLOYEES -->
        <?php if (can_view_menu('manage_employees', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php?view=manage_employees" class="<?php echo $baseLinkClass . (isActive('dashboard.php','manage_employees') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-users-cog <?php echo isActive('dashboard.php','manage_employees') ? $activeIconClass : $baseIconClass; ?>"></i>
                <span>គ្រប់គ្រងបុគ្គលិក</span>
            </a>
        </li>
        <?php endif; ?>
        <!-- END: NEW MENU ITEM -->
        
        <?php if (can_view_menu('checklist', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php?view=checklist" class="<?php echo $baseLinkClass . (isActive('dashboard.php','checklist') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-tasks <?php echo $baseIconClass; ?>"></i>
                <span>បញ្ជីការងារ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('daily_reports', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php?view=reports" class="<?php echo $baseLinkClass . (isActive('dashboard.php','reports') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-file-alt <?php echo $baseIconClass; ?>"></i>
                <span>របាយការណ៍ប្រចាំថ្ងៃ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('request_reports', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php?view=request_reports" class="<?php echo $baseLinkClass . ' justify-between' . (isActive('dashboard.php','request_reports') ? ' ' . $activeLinkClass : ''); ?>">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-list-check w-5 text-center"></i>
                    <span>របាយការណ៍សំណើ</span>
                </div>
                <?php if (isset($pendingRequestsCount) && $pendingRequestsCount > 0): ?>
                    <span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1 ml-auto"><?php echo $pendingRequestsCount; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        
        <?php // --- CORRECTED PAYROLL SECTION --- ?>
        <?php if ($show_payroll_dropdown): ?>
        <li>
            <button id="payroll-toggle" class="<?php echo $baseLinkClass . ' w-full justify-between' . ($isPayrollPageActive ? ' ' . $activeLinkClass : ''); ?>">
                <div class="flex items-center space-x-3">
                    <i class="fas fa-money-bill-wave w-5 text-center"></i>
                    <span>បើកប្រាក់បៀវត្ស</span>
                </div>
                <i id="payroll-arrow" class="fas fa-chevron-down transform transition-transform duration-200"></i>
            </button>
            <ul id="payroll-submenu" class="<?php echo $payrollSubmenuClass; ?>">
                <?php if (can_view_menu('deductions_bonuses', $current_user_role, $conn)): ?>
                <li>
                    <a href="dashboard.php?view=deductions_bonuses" class="<?php echo $baseLinkClass . ' text-sm' . (isActive('dashboard.php','deductions_bonuses') ? ' ' . $activeLinkClass : ''); ?>">
                        <i class="fas fa-cogs fa-xs mr-3 text-gray-300"></i>
                        <span>គ្រប់គ្រងកាត់ប្រាក់ & OT</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_view_menu('payroll_calculation', $current_user_role, $conn)): ?>
                <li>
                    <a href="dashboard.php?view=payroll_calculation" class="<?php echo $baseLinkClass . ' text-sm' . (isActive('dashboard.php','payroll_calculation') ? ' ' . $activeLinkClass : ''); ?>">
                        <i class="fas fa-calculator fa-xs mr-3 text-gray-300"></i>
                        <span>ការគណនាបៀវត្ស</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (can_view_menu('payroll_approval', $current_user_role, $conn)): ?>
                <li>
                    <a href="dashboard.php?view=payroll_approval" class="<?php echo $baseLinkClass . ' text-sm' . (isActive('dashboard.php','payroll_approval') ? ' ' . $activeLinkClass : ''); ?>">
                        <i class="fas fa-check-double fa-xs mr-3 text-gray-300"></i>
                        <span>ដំណើរការអនុម័ត</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (can_view_menu('payroll_payslip', $current_user_role, $conn)): ?>
                <li>
                    <a href="dashboard.php?view=payroll_payslip" class="<?php echo $baseLinkClass . ' text-sm' . (isActive('dashboard.php','payroll_payslip') ? ' ' . $activeLinkClass : ''); ?>">
                        <i class="fas fa-file-invoice-dollar fa-xs mr-3 text-gray-300"></i>
                        <span>បង្កើត Payslip</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        
        <?php if (can_view_menu('post_announcements', $current_user_role, $conn)): ?>
        <li>
            <a href="post_announcements.php" class="<?php echo $baseLinkClass . (isActive('post_announcements.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-bullhorn w-5 text-center"></i>
                <span>បង្ហោះការជូនដំណឹង</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('inactive_users', $current_user_role, $conn)): ?>
        <li>
            <a href="dashboard.php?view=inactive_users" class="<?php echo $baseLinkClass . (isActive('dashboard.php','inactive_users') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-user-slash w-5 text-center"></i>
                <span>គណនីបានបិទ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('post_meeting', $current_user_role, $conn)): ?>
        <li>
            <a href="post_meeting.php" class="<?php echo $baseLinkClass . (isActive('post_meeting.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-calendar-plus w-5 text-center"></i>
                <span>បង្ហោះការប្រជុំ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('post_lesson', $current_user_role, $conn)): ?>
        <li>
            <a href="post_lesson.php" class="<?php echo $baseLinkClass . (isActive('post_lesson.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-book w-5 text-center"></i>
                <span>បង្ហោះមេរៀន</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('print_pdf', $current_user_role, $conn)): ?>
        <li>
            <a href="print_content.php" class="<?php echo $baseLinkClass . (isActive('print_content.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-print w-5 text-center"></i>
                <span>បោះពុម្ព PDF</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (can_view_menu('upload_pdf', $current_user_role, $conn)): ?>
        <li>
            <a href="store_print_pdf.php" class="<?php echo $baseLinkClass . (isActive('store_print_pdf.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-file-pdf w-5 text-center"></i>
                <span>បង្ហោះ PDF</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('pending_requests', $current_user_role, $conn)): ?>
        <li>
            <a href="pending_requests.php" class="<?php echo $baseLinkClass . (isActive('pending_requests.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-clock w-5 text-center"></i>
                <span>សំណើរង់ចាំ</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (can_view_menu('processed_requests', $current_user_role, $conn)): ?>
        <li>
            <a href="view_processed_requests.php" class="<?php echo $baseLinkClass . (isActive('view_processed_requests.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-check-circle w-5 text-center"></i>
                <span>សំណើបានដំណើរការ</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('view_lessons', $current_user_role, $conn)): ?>
        <li>
            <a href="lessons.php" class="<?php echo $baseLinkClass . (isActive('lessons.php') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-list w-5 text-center"></i>
                <span>មើលមេរៀន</span>
            </a>
        </li>
        <?php endif; ?>

        <?php if (can_view_menu('upload_lesson_docs', $current_user_role, $conn)): ?>
        <li>
            <a href="post_lesson_documents.php" class="<?php echo $baseLinkClass . (isActive('post_lesson_documents.php') ? ' ' . $activeLinkClass : ''); ?>">
                 <i class="fas fa-file-upload w-5 text-center"></i>
                <span>បង្ហោះឯកសារមេរៀន</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (can_view_menu('permissions', $current_user_role, $conn)): ?>
         <li>
            <a href="dashboard.php?view=permissions" class="<?php echo $baseLinkClass . (isActive('dashboard.php','permissions') ? ' ' . $activeLinkClass : ''); ?>">
                <i class="fas fa-shield-alt w-5 text-center"></i>
                <span>ការកំណត់សិទ្ធ</span>
            </a>
        </li>
        <?php endif; ?>

        <li class="pt-4">
            <a href="logout.php" class="<?php echo $baseLinkClass; ?>">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>ចាកចេញ</span>
            </a>
        </li>
        </ul>
    </nav>
</aside>