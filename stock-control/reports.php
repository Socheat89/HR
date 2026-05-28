<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ហៅ Logic រួមសម្រាប់រាប់ចំនួន Notification ដែលនឹងហៅ db_connect.php ដោយស្វ័យប្រវត្តិ
// ត្រូវហៅវាមុនគេ ដើម្បីឱ្យមាន $pdo សម្រាប់ប្រើក្នុង Logic លុប
require_once 'nav_logic.php';

// --- START: LOGIC សម្រាប់លុបសំណើ (ពី delete_requests.php) ---
// ពិនិត្យមើលថាតើ request method ជា POST និងមាន ID ត្រូវបានផ្ញើមកដើម្បីលុបឬអត់
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_ids']) && is_array($_POST['request_ids'])) {
    
    $request_ids = $_POST['request_ids'];

    // ត្រង ID ទាំងអស់ដើម្បីធានាថាវាជាលេខ
    $sanitized_ids = array_filter($request_ids, 'is_numeric');

    if (empty($sanitized_ids)) {
        $_SESSION['error_message'] = "មិនមានសំណើត្រឹមត្រូវត្រូវបានជ្រើសរើសទេ។";
    } else {
        try {
            // ចាប់ផ្តើម Transaction ដើម្បីធានាថា query ទាំងពីរដំណើរការបានជោគជ័យ
            $pdo->beginTransaction();

            // បង្កើត placeholders សម្រាប់ IN clause (?, ?, ?)
            $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));

            // 1. លុបទិន្នន័យចេញពីตาราง `stock_request_items` ជាមុនសិន
            $sql_items = "DELETE FROM stock_request_items WHERE stock_request_id IN ($placeholders)";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute($sanitized_ids);

            // 2. បន្ទាប់មកលុបទិន្នន័យចេញពីตาราง `stock_request`
            $sql_requests = "DELETE FROM stock_request WHERE id IN ($placeholders)";
            $stmt_requests = $pdo->prepare($sql_requests);
            $stmt_requests->execute($sanitized_ids);

            // បញ្ចប់ Transaction
            $pdo->commit();

            $_SESSION['success_message'] = "បានលុបសំណើដែលបានជ្រើសរើសចំនួន " . count($sanitized_ids) . " បានជោគជ័យ។";

        } catch (PDOException $e) {
            // បើមានបញ្ហា Rollback transaction
            $pdo->rollBack();
            $_SESSION['error_message'] = "ការលុបបានបរាជ័យ: " . $e->getMessage();
        }
    }
    
    // បញ្ជូនអ្នកប្រើប្រាស់ត្រឡប់ទៅหน้า reports.php ដើម្បី refresh ទិន្នន័យ
    // ប្រើ GET parameter ដើម្បីធានាថា user នៅលើ tab ត្រឹមត្រូវ
    header('Location: reports.php?report_tab=requests');
    exit();
}
// --- END: LOGIC សម្រាប់លុបសំណើ ---


// ទទួលតម្លៃសម្រាប់ lọc តាមថ្ងៃ និងសម្រាប់បោះពុម្ព
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$print_all = isset($_GET['print_all']); // Flag ដើម្បីដឹងថាត្រូវបោះពុម្ពទាំងអស់ឬអត់

try {
    header('Content-Type: text/html; charset=utf-8');

    $current_page = basename($_SERVER['PHP_SELF']);

    // --- 1. MANAGE ACTIVE TAB ---
    $valid_tabs = ['requests', 'all_stock', 'low_stock', 'purchases', 'history'];
    // កែប្រែដើម្បីឱ្យ tab នៅតែជា 'requests' ក្រោយពីលុប
    $active_tab = $_GET['report_tab'] ?? $_SESSION['active_report_tab'] ?? 'requests';
    if (!in_array($active_tab, $valid_tabs)) {
        $active_tab = 'requests';
    }
    $_SESSION['active_report_tab'] = $active_tab;

    // --- 2. SETUP SEARCH AND SORTING PARAMETERS ---
    $req_search_term = ($active_tab == 'requests' && isset($_GET['search'])) ? $_GET['search'] : '';
    $req_sort_whitelist = ['request_no', 'full_name', 'status', 'created_at'];
    $req_sort_column = ($active_tab == 'requests' && isset($_GET['sort'])) ? $_GET['sort'] : 'created_at';
    if (!in_array($req_sort_column, $req_sort_whitelist)) $req_sort_column = 'created_at';
    $req_sort_order = ($active_tab == 'requests' && isset($_GET['order'])) ? $_GET['order'] : 'desc';
    if (!in_array(strtolower($req_sort_order), ['asc', 'desc'])) $req_sort_order = 'desc';

    $hist_search_term = ($active_tab == 'history' && isset($_GET['search'])) ? $_GET['search'] : '';
    $hist_sort_whitelist = ['item_name', 'to_location', 'quantity_transferred', 'transfer_date', 'full_name'];
    $hist_sort_column = ($active_tab == 'history' && isset($_GET['sort'])) ? $_GET['sort'] : 'transfer_date';
    if (!in_array($hist_sort_column, $hist_sort_whitelist)) $hist_sort_column = 'transfer_date';
    $hist_sort_order = ($active_tab == 'history' && isset($_GET['order'])) ? $_GET['order'] : 'desc';
    if (!in_array(strtolower($hist_sort_order), ['asc', 'desc'])) $hist_sort_order = 'desc';

    $stock_search_term = ($active_tab == 'all_stock' && isset($_GET['search'])) ? $_GET['search'] : '';

    // --- 3. FETCH ALL DATA FOR REPORTS ---
    $sql_items = "SELECT * FROM stock_items";
    $params_items = [];
    if (!empty($stock_search_term)) {
        $sql_items .= " WHERE (item_name LIKE ? OR DATE(last_updated) LIKE ?)";
        $like_term = '%' . $stock_search_term . '%';
        $params_items = [$like_term, $like_term];
    }
    $sql_items .= " ORDER BY quantity ASC";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute($params_items);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt_low = $pdo->prepare("SELECT * FROM stock_items WHERE quantity < 10 ORDER BY quantity ASC");
    $stmt_low->execute();
    $low_stock_items = $stmt_low->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $low_stock_count = count($low_stock_items);

    // កែប្រែ SQL Query សម្រាប់ Requests ដើម្បីបន្ថែមការ lọc តាមថ្ងៃ
    $sql_requests = "SELECT sr.id, sr.request_no, sr.title, COALESCE(sr.location, 'N/A') AS location, sr.status, sr.created_at, COALESCE(u.full_name, 'N/A') AS full_name FROM stock_request sr LEFT JOIN users u ON sr.user_id = u.id";
    $params_req = [];
    $where_clauses = [];

    if (!empty($req_search_term)) {
        $where_clauses[] = "(sr.request_no LIKE ? OR u.full_name LIKE ? OR sr.location LIKE ?)";
        $like_term = '%' . $req_search_term . '%';
        array_push($params_req, $like_term, $like_term, $like_term);
    }
    
    // បន្ថែមលក្ខខណ្ឌ lọc តាមថ្ងៃចាប់ផ្តើម
    if (!empty($start_date)) {
        $where_clauses[] = "DATE(sr.created_at) >= ?";
        $params_req[] = $start_date;
    }
    // បន្ថែមលក្ខខណ្ឌ lọc តាមថ្ងៃបញ្ចប់
    if (!empty($end_date)) {
        $where_clauses[] = "DATE(sr.created_at) <= ?";
        $params_req[] = $end_date;
    }

    if (!empty($where_clauses)) {
        $sql_requests .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $sql_requests .= " ORDER BY " . ($req_sort_column === 'full_name' ? 'u.full_name' : 'sr.' . $req_sort_column) . " " . $req_sort_order;
    $stmt_requests = $pdo->prepare($sql_requests);
    $stmt_requests->execute($params_req);
    $requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $total_requests_count = count($requests);

    $request_items = [];
    foreach ($requests as $request) {
        $stmt_req_items = $pdo->prepare("SELECT sri.requested_quantity, sri.offered_quantity, COALESCE(si.item_name, sri.item_name_custom, 'N/A') AS item_name FROM stock_request_items sri LEFT JOIN stock_items si ON sri.item_id = si.id WHERE sri.stock_request_id = ?");
        $stmt_req_items->execute([$request['id']]);
        $request_items[$request['id']] = $stmt_req_items->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // --- 4. PAGINATION LOGIC FOR REQUESTS TAB ---
    $requests_on_page = [];
    $total_pages = 0;
    $current_page_pagination = 1;
    if ($active_tab == 'requests') {
        if ($print_all) {
            $requests_on_page = $requests;
            $total_pages = 1;
        } else {
            $items_per_page = 15;
            $total_pages = ceil($total_requests_count / $items_per_page);
            $current_page_pagination = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($current_page_pagination < 1) $current_page_pagination = 1;
            if ($current_page_pagination > $total_pages && $total_pages > 0) $current_page_pagination = $total_pages;

            $offset = ($current_page_pagination - 1) * $items_per_page;
            $requests_on_page = array_slice($requests, $offset, $items_per_page);
        }
    }

    $stmt_purchases = $pdo->prepare("SELECT pt.id, pt.supplier, pt.invoice_number, pt.invoice_image_path, pt.notes AS transaction_notes, pt.transaction_date, SUM(pti.quantity_added * pti.price_at_purchase) AS total_cost FROM purchase_transactions pt LEFT JOIN purchase_transaction_items pti ON pt.id = pti.purchase_transaction_id GROUP BY pt.id ORDER BY pt.transaction_date DESC");
    $stmt_purchases->execute();
    $purchase_transactions = $stmt_purchases->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $purchase_items = [];
    foreach ($purchase_transactions as $purchase) {
        $stmt_purchase_items = $pdo->prepare("SELECT pti.quantity_added, pti.price_at_purchase, COALESCE(si.item_name, 'N/A') AS item_name FROM purchase_transaction_items pti LEFT JOIN stock_items si ON pti.stock_item_id = si.id WHERE pti.purchase_transaction_id = ?");
        $stmt_purchase_items->execute([$purchase['id']]);
        $purchase_items[$purchase['id']] = $stmt_purchase_items->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $all_transfers = [];
    if ($active_tab == 'history') {
        $sql_history = "SELECT st.id, si.item_name, st.quantity_transferred, st.to_location, sr.request_no, u_admin.full_name, st.transfer_date FROM stock_transfers st LEFT JOIN stock_items si ON st.stock_item_id = si.id LEFT JOIN stock_request sr ON st.stock_request_id = sr.id LEFT JOIN users u_admin ON st.transferred_by_user_id = u_admin.id";
        $params_hist = [];
        if (!empty($hist_search_term)) {
            $sql_history .= " WHERE (si.item_name LIKE ? OR st.to_location LIKE ? OR sr.request_no LIKE ? OR u_admin.full_name LIKE ?)";
            $like_term = '%' . $hist_search_term . '%';
            $params_hist = [$like_term, $like_term, $like_term, $like_term];
        }
        $sql_history .= " ORDER BY " . ($hist_sort_column === 'item_name' ? 'si.item_name' : ($hist_sort_column === 'full_name' ? 'u_admin.full_name' : 'st.' . $hist_sort_column)) . " " . $hist_sort_order;
        $stmt_history = $pdo->prepare($sql_history);
        $stmt_history->execute($params_hist);
        $all_transfers = $stmt_history->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    $total_items = $pdo->query("SELECT COUNT(*) FROM stock_items")->fetchColumn() ?: 0;
    $total_quantity = $pdo->query("SELECT SUM(quantity) FROM stock_items")->fetchColumn() ?: 0;
    $total_value = $pdo->query("SELECT SUM(quantity * price) FROM stock_items")->fetchColumn() ?: 0;

    $error_message = $_SESSION['error_message'] ?? '';
    unset($_SESSION['error_message']);
    $success_message = $_SESSION['success_message'] ?? '';
    unset($_SESSION['success_message']);

} catch (PDOException $e) {
    $error_message = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
    $success_message = '';
    $items = $low_stock_items = $requests = $requests_on_page = $request_items = $purchase_transactions = $purchase_items = $all_transfers = [];
    $total_items = $total_quantity = $total_value = $total_pages = $low_stock_count = $total_requests_count = 0;
    $current_page_pagination = 1;
    $active_tab = 'requests';
}

function get_sort_link_builder($tab_name, $current_active_tab) {
    return function($column_name, $display_text) use ($tab_name, $current_active_tab) {
        if ($current_active_tab != $tab_name) return $display_text;
        
        $current_sort_column = $_GET['sort'] ?? ($tab_name == 'history' ? 'transfer_date' : 'created_at');
        $current_sort_order = $_GET['order'] ?? 'desc';
        $current_search = $_GET['search'] ?? '';
        $order = ($current_sort_column === $column_name && $current_sort_order === 'asc') ? 'desc' : 'asc';
        $arrow = ($current_sort_column === $column_name) ? (($current_sort_order === 'asc') ? ' ▲' : ' ▼') : '';
        
        $query_params = http_build_query([
            'sort' => $column_name, 
            'order' => $order, 
            'search' => $current_search, 
            'report_tab' => $tab_name,
            'start_date' => $_GET['start_date'] ?? '',
            'end_date' => $_GET['end_date'] ?? ''
        ]);
        return "<a href=\"reports.php?{$query_params}\">{$display_text}{$arrow}</a>";
    };
}
$get_req_sort_link = get_sort_link_builder('requests', $active_tab);
$get_hist_sort_link = get_sort_link_builder('history', $active_tab);
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទាំងរបាយការណ៍ស្តុក</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #00b4db; 
            --primary-hover: #0083b0; 
            --light-gray: #f5f7fa; 
            --text-color: #2c3e50; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Kantumruy Pro', 'Poppins', sans-serif; }
        body { background: var(--light-gray); color: var(--text-color); line-height: 1.6; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; flex-shrink: 0; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.05); padding: 2rem 1rem; display: none; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-item { display: flex; align-items: center; padding: 1rem; color: #030303; text-decoration: none; font-size: 1rem; transition: all 0.2s ease; border-radius: 8px; margin-bottom: 0.5rem; position: relative; }
        .sidebar .nav-item:hover { background: #ecf0f1; transform: translateX(5px); }
        .sidebar .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .sidebar .nav-item i { margin-right: 0.85rem; font-size: 1.1rem; width: 20px; text-align: center; }
        .notification-badge { background-color: #e74c3c; color: white; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; margin-left: auto; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .notification-badge-info { background-color: var(--primary-color); color: white; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .main-content { flex: 1; padding: 1rem; width: 100%; }
        .header { background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: #fff; padding: 1.5rem; border-radius: 0 0 20px 20px; text-align: center; margin-bottom: 1.5rem; }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; padding: 1.25rem; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid var(--primary-color); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.07); }
        .stat-card h4 { color: #7f8c8d; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; }
        .stat-card p { font-size: 1.75rem; font-weight: 700; color: var(--text-color); }
        .tab-nav { display: flex; overflow-x: auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 1.5rem; white-space: nowrap; }
        .tab-nav button { flex: 1; padding: 1rem; background: none; border: none; font-size: 1rem; font-weight: 500; color: #7f8c8d; cursor: pointer; transition: all 0.2s ease; border-bottom: 3px solid transparent; text-align: center; }
        .tab-nav button:hover { background: #f9fafb; color: var(--primary-color); }
        .tab-nav button.active { color: var(--primary-color); border-bottom: 3px solid var(--primary-color); background: #f1f5f9; }
        .report-section { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; overflow: hidden; display: none; padding: 1.5rem; }
        .report-section.active { display: block; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        th { background: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; }
        th a { text-decoration: none; color: inherit; }
        td.wrap-text { white-space: normal; }
        .item-image, .invoice-image { max-width: 50px; max-height: 50px; object-fit: cover; border-radius: 4px; cursor: pointer; }
        .item-image.placeholder { background: #f0f0f0; color: #7f8c8d; text-align: center; line-height: 50px; }
        .no-data { text-align: center; color: #7f8c8d; padding: 2rem; }
        .reprint-btn, .detail-btn { display: inline-block; padding: 5px 10px; background-color: #3b82f6; color: white !important; text-decoration: none; border-radius: 5px; font-size: 0.8rem; cursor: pointer; }
        .reprint-btn:hover, .detail-btn:hover { background-color: #2563eb; }
        .status-badge { padding: 0.25em 0.6em; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; text-transform: capitalize; }
        .status-processed { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; padding: 1.5rem; width: 90%; max-width: 500px; border-radius: 12px; position: relative; }
        .modal-content h3 { margin-bottom: 1rem; font-size: 1.2rem; font-weight: 600; }
        .modal-content ul { margin: 0; padding-left: 20px; }
        .modal-content .close-btn { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; color: #7f8c8d; }
        .image-modal { z-index: 1051; }
        .image-modal-content { max-width: 90%; max-height: 90%; border-radius: 8px; }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 0.5rem 0; border-radius: 20px 20px 0 0; z-index: 999; }
        .bottom-nav .nav-item { text-align: center; padding: 0.5rem; color: #7f8c8d; text-decoration: none; font-size: 0.75rem; flex: 1; }
        .bottom-nav .nav-item.active { color: var(--primary-color); }
        .bottom-nav .nav-item i { display: block; font-size: 1.25rem; margin-bottom: 0.25rem; }
        @media (min-width: 769px) { .sidebar { display: block; } .main-content { padding: 2rem; margin-left: 250px; } .header { border-radius: 12px; } .tab-nav { overflow-x: hidden; } .tab-nav button { padding: 1rem 2rem; } .bottom-nav { display: none; } }
        .item-history-link { color: var(--primary-color); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .item-history-link:hover { text-decoration: underline; }
        .item-history-link .fa-history { font-size: 0.8em; color: #7f8c8d; transition: color 0.2s ease; }
        .item-history-link:hover .fa-history { color: var(--primary-hover); }
        .search-form { position: relative; }
        .search-form input[type="text"] { flex-grow: 1; padding: 12px 15px 12px 40px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 1rem; color: var(--text-color); transition: all 0.2s ease-in-out; width: 100%; }
        .search-form input[type="text"]::placeholder { color: #9ca3af; }
        .search-form input[type="text"]:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 180, 219, 0.2); }
        .search-form::before { font-family: "Font Awesome 6 Free"; content: "\f002"; font-weight: 900; position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.9rem; pointer-events: none; }
        .report-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
        .report-header .search-form { margin-bottom: 0; flex-grow: 1; min-width: 250px; }
        .print-btn { padding: 10px 15px; background-color: #16a34a; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; transition: background-color 0.2s ease; }
        .print-btn:hover { background-color: #15803d; }
        .pagination { display: flex; justify-content: center; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 0.5rem; }
        .pagination a { color: var(--primary-color); padding: 8px 16px; text-decoration: none; border: 1px solid #ddd; border-radius: 5px; transition: background-color 0.3s, color 0.3s; }
        .pagination a:hover { background-color: #f1f5f9; }
        .pagination a.active { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        @media print { body > .container > .sidebar, body > .container > .main-content > .header, body > .container > .main-content > .stats-grid, body > .container > .main-content > .tab-nav, body > .bottom-nav, .report-header, .modal, .no-print, .pagination { display: none !important; } body { background: #fff; } .container, .main-content { display: block !important; width: 100% !important; padding: 0 !important; margin: 0 !important; } .report-section { box-shadow: none; border: none; padding: 0; } .report-section { display: none !important; } #requests-report { display: block !important; } table { width: 100%; border: 1px solid #ddd; font-size: 10pt; } th, td { border: 1px solid #ddd; padding: 8px; } th { background-color: #f2f2f2 !important; } a { text-decoration: none; color: #000; } .status-badge { border: 1px solid #ccc; background-color: transparent !important; color: #000 !important; } }
        #itemHistoryModal .modal-content { max-width: 800px; width: 95%; }
        #history-filters { background-color: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #e9ecef; margin-bottom: 1.5rem; }
        #itemHistoryContent table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        #itemHistoryContent th, #itemHistoryContent td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        #itemHistoryContent th { background-color: #e9ecef; font-weight: 600; }
        #itemHistoryContent tr:nth-child(even) { background-color: #f8f9fa; }
        #itemHistoryContent tr:last-child td { border-bottom: none; }

        /* Style ថ្មីសម្រាប់ប៊ូតុងលុប */
        .delete-btn {
            padding: 10px 15px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.2s ease;
        }
        .delete-btn:hover { background-color: #c0392b; }
        .delete-btn:disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <div class="header"><h1>ផ្ទាំងរបាយការណ៍ស្តុក</h1></div>

            <?php if(!empty($success_message)): ?>
                <div style="padding: 1rem; background-color: #dcfce7; color: #166534; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <?php if(!empty($error_message)): ?>
                <div style="padding: 1rem; background-color: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><h4>ប្រភេទទំនិញ</h4><p><?php echo number_format($total_items); ?></p></div>
                <div class="stat-card"><h4>បរិមាណសរុប</h4><p><?php echo number_format($total_quantity); ?></p></div>
                <div class="stat-card"><h4>តម្លៃសរុប</h4><p>$<?php echo number_format($total_value, 2); ?></p></div>
            </div>

            <div class="tab-nav">
                <button class="tab-button <?php echo $active_tab == 'requests' ? 'active' : ''; ?>" data-tab="requests" style="display: inline-flex; align-items: center; gap: 8px;">
                    <span>សំណើទាំងអស់</span>
                    <?php if ($total_requests_count > 0): ?>
                        <span class="notification-badge-info"><?php echo number_format($total_requests_count); ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-button <?php echo $active_tab == 'all_stock' ? 'active' : ''; ?>" data-tab="all_stock" style="display: inline-flex; align-items: center; gap: 8px;">
                    <span>ស្តុកទាំងអស់</span>
                    <?php if ($total_items > 0): ?>
                        <span class="notification-badge-info"><?php echo number_format($total_items); ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-button <?php echo $active_tab == 'low_stock' ? 'active' : ''; ?>" data-tab="low_stock" style="display: inline-flex; align-items: center; gap: 8px;">
                    <span>ស្តុកទាប (&le; ១០)</span>
                    <?php if (isset($low_stock_count) && $low_stock_count > 0): ?>
                        <span class="notification-badge"><?php echo $low_stock_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-button <?php echo $active_tab == 'purchases' ? 'active' : ''; ?>" data-tab="purchases">ទិញចូលស្តុក</button>
                <button class="tab-button <?php echo $active_tab == 'history' ? 'active' : ''; ?>" data-tab="history">ប្រវត្តិផ្ទេរទាំងអស់</button>
            </div>

            <!-- Requests Report Tab -->
            <div id="requests-report" class="report-section <?php echo $active_tab == 'requests' ? 'active' : ''; ?>">
                <div class="report-header">
                     <form method="GET" action="reports.php" id="request-filter-form" style="display: contents; align-items: center; gap: 1rem; flex-grow: 1;">
                        <div class="search-form" style="flex-grow: 1; min-width: 250px;">
                            <!-- ការកែប្រែទី១៖ ប្តូរ onchange ទៅ oninput -->
                            <input type="text" name="search" placeholder="ស្វែងរកតាមលេខសំណើរ, អ្នកស្នើសុំ..." value="<?php echo htmlspecialchars($req_search_term); ?>" oninput="debounceSubmit(this.form)">
                        </div>
                        <input type="hidden" name="report_tab" value="requests">
                    </form>
                    
                    <div class="date-range-print no-print" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <label for="start_date">ពីថ្ងៃ:</label>
                        <input type="date" id="start_date" name="start_date" form="request-filter-form" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;" onchange="document.getElementById('request-filter-form').submit();">
                        
                        <label for="end_date">ដល់ថ្ងៃ:</label>
                        <input type="date" id="end_date" name="end_date" form="request-filter-form" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 8px; border: 1px solid #ccc; border-radius: 5px;" onchange="document.getElementById('request-filter-form').submit();">
                        
                        <button id="print-date-range-btn" class="print-btn"><i class="fa-solid fa-print"></i> បោះពុម្ព</button>
                        
                        <button type="submit" form="delete-requests-form" id="delete-selected-btn" class="delete-btn" disabled>
                            <i class="fa-solid fa-trash-can"></i> លុបអ្វីដែលបានជ្រើសរើស
                        </button>
                    </div>
                </div>
                
                <!-- Form action បានប្តូរមក reports.php -->
                <form action="reports.php" method="POST" id="delete-requests-form">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th class="no-print" style="width: 1%;"><input type="checkbox" id="select-all-requests"></th>
                                    <th><?php echo $get_req_sort_link('request_no', 'លេខសំណើរ'); ?></th>
                                    <th><?php echo $get_req_sort_link('full_name', 'អ្នកស្នើសុំ'); ?></th>
                                    <th><?php echo $get_req_sort_link('location', 'កន្លែង'); ?></th>
                                    <th><?php echo $get_req_sort_link('status', 'ស្ថានភាព'); ?></th>
                                    <th><?php echo $get_req_sort_link('created_at', 'ថ្ងៃស្នើសុំ'); ?></th>
                                    <th style="width: 35%;">ទំនិញស្នើសុំ</th>
                                    <th class="no-print">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($requests_on_page) > 0): foreach($requests_on_page as $request): ?>
                                <tr>
                                    <td class="no-print"><input type="checkbox" class="request-checkbox" name="request_ids[]" value="<?php echo $request['id']; ?>"></td>
                                    <td><?php echo htmlspecialchars($request['request_no'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($request['full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($request['location'] ?? 'N/A'); ?></td>
                                    <td><span class="status-badge status-<?php echo strtolower($request['status'] ?? 'unknown'); ?>"><?php echo htmlspecialchars($request['status'] ?? 'Unknown'); ?></span></td>
                                    <td><?php echo date('d M Y, g:ia', strtotime($request['created_at'] ?? 'now')); ?></td>
                                    <td class="wrap-text">
                                        <?php if (!empty($request_items[$request['id']])): ?>
                                            <ul style="margin: 0; padding: 0; list-style-type: none;">
                                                <?php foreach ($request_items[$request['id']] as $index => $item): 
                                                    $is_last_item = $index === count($request_items[$request['id']]) - 1;
                                                    $li_style = "padding-bottom: 8px; margin-bottom: 8px;";
                                                    if (!$is_last_item) {
                                                        $li_style .= " border-bottom: 1px solid #f0f0f0;";
                                                    }
                                                ?>
                                                    <li style="<?php echo $li_style; ?>">
                                                        <strong><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></strong>
                                                        <div style="font-size: 0.9em; color: #555; padding-left: 5px; margin-top: 4px;">
                                                            <span>ស្នើសុំ: <b style="color: #3b82f6;"><?php echo (int)($item['requested_quantity'] ?? 0); ?></b></span>
                                                            <span style="margin-left: 15px;">ផ្តល់ជូន: <b style="color: #16a34a;"><?php echo (int)($item['offered_quantity'] ?? 0); ?></b></span>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            គ្មានទំនិញស្នើសុំ
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print"><a href="reprint_request.php?id=<?php echo $request['id']; ?>" class="reprint-btn" target="_blank">Reprint</a></td>
                                </tr>
                                <?php endforeach; else: ?>
                                <tr><td colspan="8" class="no-data"><?php echo !empty($req_search_term) || !empty($start_date) || !empty($end_date) ? 'រកមិនឃើញលទ្ធផលដែលត្រូវនឹងការស្វែងរក' : 'មិនទាន់មានសំណើរនៅឡើយទេ'; ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <div class="pagination">
                    <?php
                    if ($active_tab == 'requests' && $total_pages > 1) {
                        $query_params = $_GET; unset($query_params['page']);
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $query_params['page'] = $i;
                            $is_active = ($i == $current_page_pagination) ? 'active' : '';
                            echo "<a href='reports.php?" . http_build_query($query_params) . "' class='{$is_active}'>{$i}</a>";
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- All Stock Report Tab -->
            <div id="all_stock-report" class="report-section <?php echo $active_tab == 'all_stock' ? 'active' : ''; ?>">
                <div class="report-header">
                    <form method="GET" action="reports.php" class="search-form">
                        <!-- ការកែប្រែទី២៖ បន្ថែម oninput សម្រាប់ Auto Search -->
                        <input type="text" name="search" placeholder="ស្វែងរកតាមឈ្មោះទំនិញ, ថ្ងៃខែ (YYYY-MM-DD)..." value="<?php echo htmlspecialchars($stock_search_term); ?>" oninput="debounceSubmit(this.form)">
                        <input type="hidden" name="report_tab" value="all_stock">
                    </form>
                </div>
                <div class="table-container">
                    <?php if(count($items) > 0): ?>
                        <table>
                            <thead><tr><th>ID</th><th>រូបភាព</th><th>ឈ្មោះ</th><th>បរិមាណ</th><th>តម្លៃ</th><th>តម្លៃសរុប</th><th>ប្រភេទ</th><th>ធ្វើបច្ចុប្បន្នភាព</th></tr></thead>
                            <tbody>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="item-image" alt="Item Image">
                                        <?php else: ?>
                                            <div class="item-image placeholder">គ្មានរូបភាព</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="item-history-link" data-item-id="<?php echo $item['id']; ?>" data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>">
                                            <span><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></span>
                                            <i class="fa-solid fa-history" title="មើលប្រវត្តិ"></i>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                    <td>$<?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                    <td>$<?php echo number_format(($item['quantity'] ?? 0) * ($item['price'] ?? 0), 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['last_updated'] ?? 'now')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">គ្មានទំនិញស្តុក</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Other Report Tabs... (low stock, purchases, history) -->
            <div id="low_stock-report" class="report-section <?php echo $active_tab == 'low_stock' ? 'active' : ''; ?>">
                 <div class="table-container">
                    <?php if(count($low_stock_items) > 0): ?>
                        <table>
                            <thead><tr><th>ID</th><th>ឈ្មោះ</th><th>បរិមាណ</th><th>តម្លៃ</th><th>ប្រភេទ</th></tr></thead>
                            <tbody>
                                <?php foreach($low_stock_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($item['quantity'] ?? 0); ?></td>
                                    <td>$<?php echo number_format($item['price'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">គ្មានទំនិញស្តុកទាបទេ</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="purchases-report" class="report-section <?php echo $active_tab == 'purchases' ? 'active' : ''; ?>">
                <div class="table-container">
                    <?php if(count($purchase_transactions) > 0): ?>
                        <table>
                            <thead><tr><th>លេខវិក្កយបត្រ</th><th>រូបភាព</th><th>អ្នកផ្គត់ផ្គង់</th><th>ថ្ងៃទិញ</th><th>តម្លៃសរុប</th><th>សកម្មភាព</th></tr></thead>
                            <tbody>
                                <?php foreach($purchase_transactions as $index => $purchase): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($purchase['invoice_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if(!empty($purchase['invoice_image_path']) && file_exists($purchase['invoice_image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($purchase['invoice_image_path']); ?>" class="invoice-image" alt="Invoice">
                                        <?php else: ?>
                                            <div class="item-image placeholder">គ្មានរូបភាព</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($purchase['supplier'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($purchase['transaction_date'] ?? 'now')); ?></td>
                                    <td>$<?php echo number_format($purchase['total_cost'] ?? 0, 2); ?></td>
                                    <td><button class="detail-btn" data-index="<?php echo $index; ?>" data-purchase-id="<?php echo $purchase['id']; ?>">បង្ហាញលម្អិត</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-data">មិនមានទិន្នន័យការទិញចូលស្តុកទេ</p>
                    <?php endif; ?>
                </div>
            </div>
            <div id="history-report" class="report-section <?php echo $active_tab == 'history' ? 'active' : ''; ?>">
                 <div class="report-header">
                    <form method="GET" action="reports.php" class="search-form">
                        <!-- ការកែប្រែទី៣៖ បន្ថែម oninput សម្រាប់ Auto Search -->
                        <input type="text" name="search" placeholder="ស្វែងរកតាមឈ្មោះទំនិញ, ទីតាំង, លេខសំណើរ..." value="<?php echo htmlspecialchars($hist_search_term); ?>" oninput="debounceSubmit(this.form)">
                        <input type="hidden" name="report_tab" value="history">
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><?php echo $get_hist_sort_link('item_name', 'ឈ្មោះទំនិញ'); ?></th>
                                <th><?php echo $get_hist_sort_link('quantity_transferred', 'ចំនួន'); ?></th>
                                <th><?php echo $get_hist_sort_link('to_location', 'ផ្ទេរទៅទីតាំង'); ?></th>
                                <th>ពីសំណើរលេខ</th>
                                <th><?php echo $get_hist_sort_link('full_name', 'អ្នកផ្ទេរ'); ?></th>
                                <th><?php echo $get_hist_sort_link('transfer_date', 'ថ្ងៃផ្ទេរ'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($all_transfers) > 0): foreach($all_transfers as $transfer): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transfer['item_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['quantity_transferred']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['to_location']); ?></td>
                                <td><?php echo htmlspecialchars($transfer['request_no'] ?? 'ផ្ទេរដោយផ្ទាល់'); ?></td>
                                <td><?php echo htmlspecialchars($transfer['full_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('d M Y, H:i', strtotime($transfer['transfer_date'])); ?></td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr><td colspan="6" class="no-data"><?php echo !empty($hist_search_term) ? 'រកមិនឃើញលទ្ធផលសម្រាប់ "' . htmlspecialchars($hist_search_term) . '"' : 'មិនមានប្រវត្តិផ្ទេរទំនិញទេ'; ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modals -->
            <div id="purchaseDetailModal" class="modal"><div class="modal-content"><span class="close-btn">&times;</span><h3>លម្អិតនៃការទិញចូលស្តុក</h3><div id="purchaseDetailContent"></div></div></div>
            <div id="itemHistoryModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn">&times;</span>
                    <h3>ប្រវត្តិផ្ទេរសម្រាប់៖ <span id="historyItemName" style="color: var(--primary-color);"></span></h3>
                    <div id="history-filters" style="display: flex; gap: 10px; margin-bottom: 1rem; align-items: center; flex-wrap: wrap;">
                        <select id="history-year-filter" style="padding: 5px; border-radius: 5px; border: 1px solid #ccc;"><option value="">គ្រប់ឆ្នាំ</option><?php $current_year = date('Y'); for ($i = $current_year; $i >= $current_year - 5; $i--): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?></select>
                        <select id="history-month-filter" style="padding: 5px; border-radius: 5px; border: 1px solid #ccc;"><option value="">គ្រប់ខែ</option><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo "ខែ " . $m; ?>"><?php echo "ខែ " . $m; ?></option><?php endfor; ?></select>
                        <select id="history-day-filter" style="padding: 5px; border-radius: 5px; border: 1px solid #ccc;"><option value="">គ្រប់ថ្ងៃ</option><?php for ($d = 1; $d <= 31; $d++): ?><option value="<?php echo $d; ?>"><?php echo $d; ?></option><?php endfor; ?></select>
                        <button id="clear-history-filter-btn" style="padding: 5px 10px; border: none; background-color: #7f8c8d; color: white; border-radius: 5px; cursor: pointer;">លុបតម្រង</button>
                    </div>
                    <div id="itemHistoryContent" style="margin-top: 1rem; max-height: 400px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="imageModal" class="modal image-modal"><img class="image-modal-content" id="modalImage"></div>
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង</a>
        <a href="../index.php" class="nav-item"><i class="fa-solid fa-box-archive"></i> ទំនិញ</a>
        <a href="reports.php" class="nav-item active"><i class="fa-solid fa-chart-simple"></i> របាយការណ៍</a>
        <a href="stock_counting.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i> ការរាប់ស្តុក</a>
        <a href="review_requests.php" class="nav-item"><i class="fa-solid fa-magnifying-glass-chart"></i> ពិនិត្យសំណើរ</a>
    </div>

    <script>
    // ការកែប្រែទី៤៖ បន្ថែមฟังก์ชัน debounceSubmit
    let debounceTimer;
    function debounceSubmit(form) {
        // លុប timer ដែលមានស្រាប់ចោល
        clearTimeout(debounceTimer);
        // បង្កើត timer ថ្មី
        debounceTimer = setTimeout(() => {
            form.submit();
        }, 500); // រង់ចាំ 500ms (កន្លះវិនាទី) ក្រោយពីuserឈប់វាយ
    }

    document.addEventListener('DOMContentLoaded', function() {
        // --- JAVASCRIPT សម្រាប់មុខងារលុប ---
        const selectAllCheckbox = document.getElementById('select-all-requests');
        const itemCheckboxes = document.querySelectorAll('.request-checkbox');
        const deleteBtn = document.getElementById('delete-selected-btn');
        const deleteForm = document.getElementById('delete-requests-form');

        function toggleDeleteButton() {
            const anyChecked = Array.from(itemCheckboxes).some(cb => cb.checked);
            deleteBtn.disabled = !anyChecked;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                itemCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                toggleDeleteButton();
            });
        }

        itemCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (!this.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                }
                toggleDeleteButton();
            });
        });

        if (deleteForm) {
            deleteForm.addEventListener('submit', function(e) {
                const checkedCount = document.querySelectorAll('.request-checkbox:checked').length;
                if (checkedCount === 0) {
                    e.preventDefault();
                    alert('សូមជ្រើសរើសសំណើដើម្បីលុបជាមុនសិន។');
                    return;
                }
                const confirmed = confirm(`តើអ្នកពិតជាចង់លុបសំណើដែលបានជ្រើសរើសចំនួន ${checkedCount} មែនទេ?`);
                if (!confirmed) {
                    e.preventDefault();
                }
            });
        }
        
        // កូដសម្រាប់ប្តូរ Tab
        const tabButtons = document.querySelectorAll('.tab-button');
        const activeTabName = '<?php echo $active_tab; ?>';
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.toggle('active', section.id === `${activeTabName}-report`);
        });
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = `reports.php?report_tab=${this.dataset.tab}&page=1`;
            });
        });

        // កូដសម្រាប់ប៊ូតុងបោះពុម្ព
        const printDateRangeBtn = document.getElementById('print-date-range-btn');
        if (printDateRangeBtn) {
            printDateRangeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const searchInput = document.querySelector('#request-filter-form input[name="search"]');
                const searchTerm = searchInput ? searchInput.value : '';

                const printParams = new URLSearchParams();
                printParams.set('report_tab', 'requests');
                printParams.set('print_all', '1'); 
                if (startDate) printParams.set('start_date', startDate);
                if (endDate) printParams.set('end_date', endDate);
                if (searchTerm) printParams.set('search', searchTerm);

                const printUrl = window.location.pathname + '?' + printParams.toString();
                window.open(printUrl, '_top');
            });
        }
        
        // --- ផ្នែកដែលនៅសល់នៃ JavaScript គឺដូចเดิม ---
        const purchaseDetailModal = document.getElementById('purchaseDetailModal');
        if (purchaseDetailModal) {
            const purchaseDetailContent = document.getElementById('purchaseDetailContent');
            const closeBtn = purchaseDetailModal.querySelector('.close-btn');
            const purchaseItemsData = <?php echo json_encode($purchase_items); ?>;
            document.querySelectorAll('.detail-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const purchaseId = this.dataset.purchaseId;
                    const items = purchaseItemsData[purchaseId] || [];
                    let content = (items.length > 0) ? '<ul style="margin: 0; padding-left: 20px;">' + items.map(item => `<li>${item.item_name || 'N/A'}: បរិមាណ ${parseInt(item.quantity_added) || 0}, តម្លៃ $${parseFloat(item.price_at_purchase).toFixed(2)}</li>`).join('') + '</ul>' : 'គ្មានទំនិញទិញចូល';
                    purchaseDetailContent.innerHTML = content;
                    purchaseDetailModal.classList.add('show');
                });
            });
            if (closeBtn) closeBtn.addEventListener('click', () => purchaseDetailModal.classList.remove('show'));
            purchaseDetailModal.addEventListener('click', e => { if (e.target === purchaseDetailModal) purchaseDetailModal.classList.remove('show'); });
        }

        const imageModal = document.getElementById('imageModal');
        if (imageModal) {
            const modalImg = document.getElementById('modalImage');
            document.querySelectorAll('.invoice-image, .item-image').forEach(img => {
                img.onclick = function() { imageModal.style.display = 'flex'; modalImg.src = this.src; };
            });
            imageModal.onclick = e => { if (e.target === imageModal) imageModal.style.display = 'none'; };
        }

        const itemHistoryModal = document.getElementById('itemHistoryModal');
        if (itemHistoryModal) {
            const historyItemName = document.getElementById('historyItemName');
            const itemHistoryContent = document.getElementById('itemHistoryContent');
            const closeHistoryBtn = itemHistoryModal.querySelector('.close-btn');
            const yearFilter = document.getElementById('history-year-filter');
            const monthFilter = document.getElementById('history-month-filter');
            const dayFilter = document.getElementById('history-day-filter');
            const clearFilterBtn = document.getElementById('clear-history-filter-btn');

            function fetchItemHistory(itemId, year = '', month = '', day = '') {
                itemHistoryContent.innerHTML = '<p>កំពុងទាញយកទិន្នន័យ...</p>';
                const params = new URLSearchParams({ item_id: itemId, year: year, month: month, day: day });
                fetch(`get_transfer_history.php?${params.toString()}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success && result.data.length > 0) {
                            let tableHTML = '<table style="width: 100%;"><thead><tr><th>ផ្ទេរទៅទីតាំង</th><th>អ្នកទទួល</th><th>ចំនួន</th><th>ថ្ងៃខែ</th></tr></thead><tbody>';
                            result.data.forEach(rec => {
                                const transferDate = new Date(rec.transfer_date);
                                const formattedDate = `${transferDate.getDate().toString().padStart(2, '0')}/${(transferDate.getMonth() + 1).toString().padStart(2, '0')}/${transferDate.getFullYear()} ${transferDate.getHours().toString().padStart(2, '0')}:${transferDate.getMinutes().toString().padStart(2, '0')}`;
                                tableHTML += `<tr>
                                    <td>${rec.to_location || 'N/A'}</td>
                                    <td>${rec.receiver_name || 'N/A'}</td> 
                                    <td>${rec.quantity_transferred || 0}</td>
                                    <td>${formattedDate}</td>
                                </tr>`;
                            });
                            tableHTML += '</tbody></table>';
                            itemHistoryContent.innerHTML = tableHTML;
                        } else {
                            itemHistoryContent.innerHTML = `<p style="text-align:center;">${result.message || 'មិនមានប្រវត្តិផ្ទេរសម្រាប់ទិន្នន័យដែលបានជ្រើសរើសទេ។'}</p>`;
                        }
                    }).catch(error => {
                        console.error('Fetch Error:', error);
                        itemHistoryContent.innerHTML = '<p style="text-align:center; color:red;">មានបញ្ហាក្នុងការទាញយកទិន្នន័យ</p>';
                    });
            }
            
            document.querySelectorAll('.item-history-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const itemId = this.dataset.itemId;
                    const itemName = this.dataset.itemName;
                    itemHistoryModal.dataset.currentItemId = itemId;
                    historyItemName.textContent = itemName;
                    yearFilter.value = ''; monthFilter.value = ''; dayFilter.value = '';
                    itemHistoryModal.classList.add('show');
                    fetchItemHistory(itemId);
                });
            });

            function applyFilters() {
                const itemId = itemHistoryModal.dataset.currentItemId;
                if (itemId) {
                    fetchItemHistory(itemId, yearFilter.value, monthFilter.value, dayFilter.value);
                }
            }

            yearFilter.addEventListener('change', applyFilters);
            monthFilter.addEventListener('change', applyFilters);
            dayFilter.addEventListener('change', applyFilters);
            clearFilterBtn.addEventListener('click', () => {
                yearFilter.value = ''; monthFilter.value = ''; dayFilter.value = '';
                applyFilters();
            });

            if (closeHistoryBtn) closeHistoryBtn.addEventListener('click', () => itemHistoryModal.classList.remove('show'));
            itemHistoryModal.addEventListener('click', e => { if (e.target === itemHistoryModal) itemHistoryModal.classList.remove('show'); });
        }
    });
    </script>
    
    <?php if ($print_all && $active_tab == 'requests'): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>
    <?php endif; ?>

</body>
</html>
