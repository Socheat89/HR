<?php
// includes/sidebar.php

// Ensure DB connection is available
if (!isset($conn)) {
    $conn_path = __DIR__ . '/db.php'; 
    if (file_exists($conn_path)) {
        $conn = include $conn_path;
    } else {
        $conn = include 'includes/db.php';
    }
}

// Ensure auth functions are available
if (!function_exists('can_view_menu')) {
    // Attempt to include auth.php if functions missing
    $auth_path = __DIR__ . '/auth.php';
    if (file_exists($auth_path)) {
        include_once $auth_path; 
    } else {
        include_once 'includes/auth.php';
    }
}

// Get current page context
$current_page = basename($_SERVER['PHP_SELF']);
$current_view = isset($_GET['view']) ? $_GET['view'] : '';
$current_user_role = $_SESSION['role'] ?? 'employee';

// --- DATA FETCHING ---
$menu_items = [];
try {
    // Fetch all admin menus
    // Ensure table exists implicitly (dashboard logic handles creation, but sidebar might run elsewhere)
    $stmt = $conn->prepare("SELECT * FROM admin_menus ORDER BY menu_order ASC, id ASC");
    $stmt->execute();
    $all_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter by Permission
    foreach ($all_menus as $menu) {
        $key = $menu['menu_key'];
        // Default to TRUE if can_view_menu is not available (safety fallback? or FALSE?)
        // Better FALSE for security.
        $allowed = false;
        if (function_exists('can_view_menu')) {
            $allowed = can_view_menu($key, $current_user_role, $conn);
        } elseif ($current_user_role === 'admin') {
            $allowed = true;
        }

        if ($allowed) {
            $menu_items[] = $menu;
        }
    }
} catch (Exception $e) {
    error_log("Sidebar menu fetch error: " . $e->getMessage());
}

// Build Tree
// Note: admin_menus uses 'parent_key' which refers to 'menu_key' of parent.
// Old logic used ID map. New logic map by menu_key.
$sidebar_tree = [];
$menu_map = [];

// First pass: Index by menu_key
foreach ($menu_items as $menu) {
    $menu['children'] = [];
    $menu_map[$menu['menu_key']] = $menu;
}

// Second pass: Build hierarchy
foreach ($menu_items as $menu) {
    if (!empty($menu['parent_key']) && isset($menu_map[$menu['parent_key']])) {
        $menu_map[$menu['parent_key']]['children'][] = &$menu_map[$menu['menu_key']];
    } else {
        // Root item (or parent not found/visible)
        // Only add to tree if it is a root item (parent_key is null/empty) OR if parent is missing
        // If parent is missing but permissions say yes, maybe show at top level?
        // Let's stick to strict hierarchy: only if parent_key is empty showing at root.
        if (empty($menu['parent_key'])) {
            $sidebar_tree[] = &$menu_map[$menu['menu_key']];
        } else {
            // Orphaned child (permission yes, parent no permission?)
            // If parent is not visible, child should probably not be visible or moved to root.
            // Current login logic filters parent out first.
            // If we want to show it, we push to tree.
            if (!isset($menu_map[$menu['parent_key']])) {
                 $sidebar_tree[] = &$menu_map[$menu['menu_key']];
            }
        }
    }
}

// --- HELPER FUNCTION FOR ACTIVE STATE ---
function isMenuActive($menu) {
    global $current_page, $current_view;
    $link = $menu['menu_link'] ?? '#';
    
    // Normalize link
    if ($link === '#' || empty($link)) return false;

    // Parse link to separate path and query
    $parsed = parse_url($link);
    $link_path = isset($parsed['path']) ? basename($parsed['path']) : '';
    $link_query = isset($parsed['query']) ? $parsed['query'] : '';

    // Check Path (Simple check)
    // If the menu link is 'dashboard.php' and current page is 'dashboard.php'
    if ($link_path !== $current_page && !empty($link_path)) {
        return false;
    }

    // Check Query Params if exist
    if (!empty($link_query)) {
        parse_str($link_query, $link_params);
        if (isset($link_params['view'])) {
            if ($link_params['view'] !== $current_view) return false;
        }
    } else {
        // If menu link has no params (e.g. dashboard.php) but current url has ?view=xyz
        // Then this menu (Dashboard Home) should generally NOT be active if we are in a Sub-View, 
        // UNLESS the sub-view is not matched by any other menu?
        // Let's stick to strict matching:
        if ($current_page === 'dashboard.php' && !empty($current_view)) {
             // Exception: if link is exactly dashboard.php, and we are in a view, it's not active.
             return false;
        }
    }
    
    return true;
}

// Function to check if any child is active (to open dropdown)
function isChildActive($children) {
    foreach ($children as $child) {
        if (isMenuActive($child)) return true;
        if (!empty($child['children'])) {
            if (isChildActive($child['children'])) return true;
        }
    }
    return false;
}

// --- STYLES (Light Theme) ---
$baseLinkClass = 'group flex items-center p-3 rounded-xl transition-all duration-300 text-slate-600 hover:bg-amber-50 hover:text-amber-700 border border-transparent hover:border-amber-200 shadow-sm hover:shadow-amber-100 mb-1';
$activeLinkClass = 'bg-amber-50 text-amber-700 font-bold border-amber-200 shadow-md shadow-amber-100';
$iconClass = 'w-6 text-center text-lg transition-transform group-hover:scale-110';
$activeIconClass = 'text-amber-600';

?>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white text-slate-800 hidden <?php echo ($current_view !== '' && $current_view !== 'dashboard') ? 'md:block' : 'md:hidden'; ?> transition-all duration-300 ease-in-out h-screen custom-scrollbar overflow-y-auto border-r border-gray-100 md:relative md:translate-x-0 shadow-xl">
    
    <!-- BRAND / LOGO -->
    <div class="sticky top-0 z-10 bg-white/95 backdrop-blur-md pt-6 pb-6 px-4 border-b border-gray-100 flex flex-col items-center">
        <div class="relative group cursor-pointer">
            <div class="absolute -inset-1 bg-gradient-to-tr from-amber-400 via-yellow-200 to-amber-100 rounded-full blur opacity-40 group-hover:opacity-75 transition duration-1000 group-hover:duration-200"></div>
            <img src="https://i.ibb.co/Q3LXcgyX/Logo-Van-Van-1.png" alt="Logo" class="relative w-20 h-20 rounded-full shadow-lg bg-white object-contain border-2 border-gray-100 group-hover:border-amber-200 transition-all duration-300">
        </div>
        <div class="mt-4 text-center">
            <h2 class="text-xl font-black text-transparent bg-clip-text bg-gradient-to-r from-amber-600 via-amber-500 to-amber-400 tracking-tight uppercase drop-shadow-sm font-sans">HR System</h2>
            <p class="text-[10px] text-slate-400 uppercase tracking-[0.2em] mt-1 font-semibold">Management Panel</p>
        </div>
    </div>

    <!-- NAVIGATION -->
    <nav class="px-4 py-6 space-y-1">
        <?php foreach ($sidebar_tree as $menu): ?>
            <?php 
                $hasChildren = !empty($menu['children']);
                $isActive = isMenuActive($menu);
                $isChildOpen = $hasChildren && isChildActive($menu['children']); 
                $isParentActive = $isActive || $isChildOpen;
                
                $menuLink = $menu['menu_link'];
                $toggleId = 'menu-' . ($menu['id'] ?? uniqid());
            ?>

            <?php if ($hasChildren): ?>
                <!-- Dropdown Menu -->
                <div class="relative">
                    <button type="button" 
                            class="w-full justify-between <?php echo $baseLinkClass . ($isParentActive ? ' ' . $activeLinkClass : ''); ?>"
                            onclick="toggleSubmenu('<?php echo $toggleId; ?>', this)">
                        <div class="flex items-center gap-3">
                            <i class="<?php echo $menu['menu_icon'] ?? 'fas fa-circle'; ?> <?php echo $iconClass; ?> <?php echo $isParentActive ? $activeIconClass : 'text-slate-400'; ?>"></i>
                            <span class="font-medium tracking-wide"><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                        </div>
                        <i class="fas fa-chevron-right text-xs transition-transform duration-300 <?php echo $isChildOpen ? 'rotate-90 text-amber-600' : 'text-slate-400'; ?>"></i>
                    </button>
                    
                    <div id="<?php echo $toggleId; ?>" class="<?php echo $isChildOpen ? 'block' : 'hidden'; ?> ml-4 pl-4 border-l-2 border-slate-100 space-y-1 my-1 overflow-hidden transition-all duration-300">
                        <?php foreach ($menu['children'] as $child): ?>
                            <?php 
                                $isChildActive = isMenuActive($child);
                            ?>
                            <a href="<?php echo htmlspecialchars($child['menu_link']); ?>" 
                               class="flex items-center p-2.5 rounded-lg text-sm transition-all duration-200 <?php echo $isChildActive ? 'text-amber-700 bg-amber-50 font-medium translate-x-1' : 'text-slate-500 hover:text-slate-800 hover:bg-gray-50 hover:translate-x-1'; ?>">
                                <i class="<?php echo $child['menu_icon'] ?? 'fas fa-circle'; ?> w-5 text-center mr-2 text-xs opacity-70"></i>
                                <span><?php echo htmlspecialchars($child['menu_name']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Single Link -->
                <a href="<?php echo htmlspecialchars($menuLink); ?>" class="<?php echo $baseLinkClass . ($isActive ? ' ' . $activeLinkClass : ''); ?>">
                    <div class="flex items-center gap-3">
                        <i class="<?php echo $menu['menu_icon'] ?? 'fas fa-circle'; ?> <?php echo $iconClass; ?> <?php echo $isActive ? $activeIconClass : 'text-slate-400'; ?>"></i>
                        <span class="font-medium tracking-wide"><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                    </div>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Logout Link -->
        <div class="pt-6 mt-6 border-t border-gray-100">
            <a href="../../auth/logout.php" class="<?php echo $baseLinkClass; ?> text-red-500 hover:text-red-700 hover:bg-red-50 hover:border-red-100">
                <div class="flex items-center gap-3">
                    <i class="fas fa-sign-out-alt <?php echo $iconClass; ?>"></i>
                    <span class="font-medium">ចាកចេញ (Logout)</span>
                </div>
            </a>
        </div>
    </nav>
</aside>

<script>
function toggleSubmenu(elementId, btn) {
    const submenu = document.getElementById(elementId);
    const arrow = btn.querySelector('.fa-chevron-right');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        submenu.classList.add('block');
        arrow.classList.add('rotate-90', 'text-amber-600');
        arrow.classList.remove('text-slate-400');
    } else {
        submenu.classList.add('hidden');
        submenu.classList.remove('block');
        arrow.classList.remove('rotate-90', 'text-amber-600');
        arrow.classList.add('text-slate-400');
    }
}
</script>

<script>
function toggleSubmenu(elementId, btn) {
    const submenu = document.getElementById(elementId);
    const arrow = btn.querySelector('.fa-chevron-right');
    
    if (submenu.classList.contains('hidden')) {
        submenu.classList.remove('hidden');
        submenu.classList.add('block');
        arrow.classList.add('rotate-90', 'text-amber-600');
        arrow.classList.remove('text-slate-400');
    } else {
        submenu.classList.add('hidden');
        submenu.classList.remove('block');
        arrow.classList.remove('rotate-90', 'text-amber-600');
        arrow.classList.add('text-slate-400');
    }
}
</script>