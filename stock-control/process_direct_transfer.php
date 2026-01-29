<?php
// FILE: process_direct_transfer.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Default to Admin if not logged in
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid access method.");

// Get data from the direct transfer form
$user_id = $_SESSION['user_id'];
$transfer_title = htmlspecialchars($_POST['transfer_title'] ?? 'Direct Transfer');
$request_no = !empty($_POST['request_no']) ? htmlspecialchars($_POST['request_no']) : 'DIRECT-' . time();

$item_ids = $_POST['item_id'] ?? [];
$offer_qtys = $_POST['offer_qty'] ?? [];
$notes_arr = $_POST['notes'] ?? [];

$submitted_items = [];

try {
    $pdo->beginTransaction();

    // 1. CREATE A NEW record in stock_request with 'processed' status
    $stmt_req = $pdo->prepare("INSERT INTO stock_request (user_id, request_no, title, status, processed_at) VALUES (?, ?, ?, 'processed', NOW())");
    $stmt_req->execute([$user_id, $request_no, $transfer_title]);
    $stock_request_id = $pdo->lastInsertId();

    // 2. PROCESS EACH ITEM LINE
    for ($i = 0; $i < count($item_ids); $i++) {
        if (!empty($item_ids[$i]) && !empty($offer_qtys[$i]) && $offer_qtys[$i] > 0) {
            $item_id = (int)$item_ids[$i];
            $offered_quantity = (int)$offer_qtys[$i];

            // A. Check stock and lock row
            $stmt_check = $pdo->prepare("SELECT quantity, price, item_name FROM stock_items WHERE id = ? FOR UPDATE");
            $stmt_check->execute([$item_id]);
            $item_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$item_data) throw new Exception("Item with ID {$item_id} not found.");
            if ($item_data['quantity'] < $offered_quantity) throw new Exception("Not enough stock for '{$item_data['item_name']}'. Available: {$item_data['quantity']}, Offered: {$offered_quantity}.");

            // B. DEDUCT STOCK
            $stmt_deduct = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
            $stmt_deduct->execute([$offered_quantity, $item_id]);

            // C. CREATE the item line in stock_request_items
            $stmt_save_item = $pdo->prepare(
                "INSERT INTO stock_request_items (stock_request_id, item_id, requested_quantity, offered_quantity, price_at_request, notes) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $note = htmlspecialchars($notes_arr[$i] ?? '');
            // For direct transfers, requested_quantity is the same as offered_quantity
            $stmt_save_item->execute([$stock_request_id, $item_id, $offered_quantity, $offered_quantity, $item_data['price'], $note]);

            // D. Prepare data for display
            $submitted_items[] = [
                'name' => htmlspecialchars($item_data['item_name']),
                'request_qty' => $offered_quantity,
                'offer_qty' => $offered_quantity,
                'notes' => $note
            ];
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("<h1>Transaction Failed</h1><p style='color:red;'>" . $e->getMessage() . "</p><p>All changes have been reversed. No stock was changed.</p>");
}

// Get the full name of the user who processed this
$stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$processed_by_name = $stmt_user->fetchColumn();

$total_rows_on_form = 13;
?>
<!DOCTYPE html>
<!-- This part is the same A4 printable form as view_transfer.php -->
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Transfer Form - <?php echo $request_no; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&family=Koulen&display=swap" rel="stylesheet">
    <style> /* Your A4 form styles here */ 
    body { background: #ccc; font-family: 'Hanuman', serif; color: #000; } .form-page { background: #fff; width: 210mm; min-height: 297mm; padding: 2cm 1.5cm; margin: 1cm auto; border: 1px solid #dcdcdc; box-shadow: 0 0 10px rgba(0,0,0,0.1); } .form-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #000; padding-bottom: 10px; } .company-info { text-align: center; flex-grow: 1; } .company-logo { font-family: 'Koulen', cursive; font-size: 28px; color: #d4a017; } .company-name { font-size: 14px; font-weight: bold; letter-spacing: 1px; } .form-title-kh { font-family: 'Koulen', cursive; font-size: 22px; text-decoration: underline; margin-top: 10px; } .address-box { border: 1px solid #000; padding: 5px 8px; font-size: 11px; line-height: 1.4; max-width: 250px; } .meta-info { padding: 15px 0; font-size: 14px; } .meta-info .row { display: flex; justify-content: space-between; margin-bottom: 5px; } .meta-info .row-center { text-align: center; font-weight: bold; font-size: 16px; margin-bottom: 10px; } .dotted-line { border-bottom: 1px dotted #000; padding: 0 10px; } .form-table { width: 100%; border-collapse: collapse; font-size: 14px; } .form-table th, .form-table td { border: 1px solid #000; padding: 8px 4px; text-align: center; height: 30px; } .form-table th { background-color: #e0e0e0; font-weight: bold; } .form-table .col-no { width: 5%; } .form-table .col-goods { width: 45%; text-align: left; padding-left: 8px; } .form-table .col-request, .form-table .col-offer { width: 15%; } .form-table .col-notes { width: 20%; } .form-table .total-row td { font-weight: bold; text-align: right; padding-right: 10px; background: #e0e0e0; } .notes-section { margin-top: 20px; font-size: 14px; } .notes-section span { font-weight: bold; } .notes-section p { margin: 5px 0 0 10px; font-size: 13px; } .signature-section { margin-top: 50px; display: flex; justify-content: space-around; text-align: center; font-weight: bold; font-size: 14px; } .signature-box { padding-top: 60px; border-top: 1px dotted #000; } @media print { body, .form-page { background: #fff; margin: 0; box-shadow: none; border: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="form-page">
        <div class="form-header"><div class="company-info"><div class="company-logo">វណ្ណ វណ្ណ ខេមបូឌា</div><div class="company-name">VAN VAN CAMBODIA</div><div class="form-title-kh">ប័ណ្ណបញ្ចេញទំនិញ</div></div><div class="address-box">Address:No.1AEo, St.318, Sangkat Tuol SvayPrey1, Khan Beong Keng korng, Phnom Penh, Cambodia. Tell: 0962458467</div></div>
        <div class="meta-info">
            <div class="row"><span>លេខ........................................</span><span>ថ្ងៃ<span class="dotted-line"><?php echo date('d'); ?></span>ខែ<span class="dotted-line"><?php echo date('m'); ?></span>ឆ្នាំ<span class="dotted-line"><?php echo date('Y'); ?></span></span></div>
            <div class="row-center"><?php echo $transfer_title; ?></div>
            <div class="row"><span>No-/Request: <span class="dotted-line"><?php echo $request_no; ?></span></span><span>Date/Request <span class="dotted-line"><?php echo date('d/m/Y'); ?></span></span><span>By: <span class="dotted-line"><?php echo htmlspecialchars($processed_by_name); ?></span></span></div>
        </div>
        <table class="form-table">
            <thead><tr><th class="col-no">ល.រ<br>N/o</th><th class="col-goods">ឈ្មោះទំនិញ<br>Goods</th><th class="col-request">ចំនួនស្នើសុំ<br>Request</th><th class="col-offer">ចំនួនផ្តល់ជូន<br>Offer amount</th><th class="col-notes">កំណត់សំគាល់<br>Notes</th></tr></thead>
            <tbody>
                <?php for ($i = 0; $i < $total_rows_on_form; $i++): ?>
                <tr><td><?php echo $i + 1; ?></td><td class="col-goods"><?php echo $submitted_items[$i]['name'] ?? ''; ?></td><td><?php echo $submitted_items[$i]['request_qty'] ?? ''; ?></td><td><?php echo $submitted_items[$i]['offer_qty'] ?? ''; ?></td><td><?php echo $submitted_items[$i]['notes'] ?? ''; ?></td></tr>
                <?php endfor; ?>
            </tbody>
            <tfoot><tr class="total-row"><td colspan="4">Total</td><td></td></tr></tfoot>
        </table>
        <div class="notes-section"><span>Note:</span><p>សម្គាល់ៈ ដែលស្នើសុំសម្រាប់ប្រើប្រាស់ប្រចាំខែ</p><p>សូមធ្វើការត្រួតពិនិត្យ សម្ភារៈ ដែលបានប្រគល់ជូនឲ្យបានត្រឹមត្រូវ និង សូមធ្វើការចុះហត្ថលេខាបញ្ជាក់</p></div>
        <div class="signature-section"><div class="signature-box">អ្នកបញ្ចេញទំនិញ</div><div class="signature-box">អ្នកដឹក</div><div class="signature-box">អ្នកទទួលទំនិញ</div></div>
    </div>
</body>
</html>