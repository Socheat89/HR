<?php
// FILE: view_transfer.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Default to Admin if not logged in
}

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid access method.");
}

// (PHP logic remains unchanged)
error_log("view_transfer.php - POST data: " . print_r($_POST, true));

$user_id = $_SESSION['user_id'];
$transfer_title = htmlspecialchars($_POST['transfer_title'] ?? 'Direct Transfer');
$location = htmlspecialchars($_POST['location'] ?? '');
$original_request_id = $_POST['stock_request_id'] ?? null;

$item_ids = $_POST['item_id'] ?? [];
$custom_item_names = $_POST['custom_item_name'] ?? [];
$offer_qtys = $_POST['offer_qty'] ?? [];
$notes_arr = $_POST['notes'] ?? []; 

$submitted_items = [];

try {
    $pdo->beginTransaction();

    $stmt_last_request = $pdo->prepare("SELECT request_no FROM stock_request WHERE request_no LIKE 'No%' ORDER BY CAST(SUBSTRING(request_no, 3) AS UNSIGNED) DESC LIMIT 1");
    $stmt_last_request->execute();
    $last_request_no = $stmt_last_request->fetchColumn();

    if ($last_request_no && preg_match('/^No\d{5}$/', $last_request_no)) {
        $last_number = (int)substr($last_request_no, 2);
        $next_number = $last_number + 1;
        $request_no = 'No' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
    } else {
        $request_no = 'No00001';
    }
    
    $stmt_req = $pdo->prepare("INSERT INTO stock_request (user_id, request_no, title, location, status, processed_at) VALUES (?, ?, ?, ?, 'processed', NOW())");
    $stmt_req->execute([$user_id, $request_no, $transfer_title, $location]);
    $new_stock_request_id = $pdo->lastInsertId();

    $stmt_save_item = $pdo->prepare(
        "INSERT INTO stock_request_items (stock_request_id, item_id, item_name_custom, requested_quantity, offered_quantity, price_at_request, notes) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    for ($i = 0; $i < count($offer_qtys); $i++) {
        $offered_quantity = (int)($offer_qtys[$i] ?? 0);
        if ($offered_quantity <= 0) {
            continue;
        }

        $note = htmlspecialchars($notes_arr[$i] ?? '');
        $item_id = !empty($item_ids[$i]) ? (int)$item_ids[$i] : null;
        $custom_name = !empty($custom_item_names[$i]) ? trim($custom_item_names[$i]) : null;

        if ($item_id !== null) {
            $stmt_check = $pdo->prepare("SELECT quantity, price, item_name FROM stock_items WHERE id = ? FOR UPDATE");
            $stmt_check->execute([$item_id]);
            $item_data = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$item_data) throw new Exception("Item with ID {$item_id} not found.");
            if ($item_data['quantity'] < $offered_quantity) throw new Exception("Not enough stock for '{$item_data['item_name']}'. Available: {$item_data['quantity']}, Offered: {$offered_quantity}.");

            $stmt_deduct = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
            $stmt_deduct->execute([$offered_quantity, $item_id]);

            $stmt_save_item->execute([$new_stock_request_id, $item_id, null, $offered_quantity, $offered_quantity, $item_data['price'], $note]);

            $submitted_items[] = [
                'name' => htmlspecialchars($item_data['item_name']),
                'request_qty' => $offered_quantity,
                'offer_qty' => $offered_quantity,
                'notes' => $note
            ];
        } 
        elseif ($custom_name !== null) {
            $stmt_save_item->execute([$new_stock_request_id, null, $custom_name, $offered_quantity, $offered_quantity, null, $note]);
            $submitted_items[] = [
                'name' => htmlspecialchars($custom_name),
                'request_qty' => $offered_quantity,
                'offer_qty' => $offered_quantity,
                'notes' => $note
            ];
        }
    }

    if ($original_request_id) {
        $stmt_update_status = $pdo->prepare("UPDATE stock_request SET status = 'processed' WHERE id = ?");
        $stmt_update_status->execute([$original_request_id]);
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("view_transfer.php - Error: " . $e->getMessage());
    die("<h1>Transaction Failed</h1><p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p><p>All changes have been reversed. No stock was changed.</p>");
}

$stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt_user->execute([$user_id]);
$processed_by_name = $stmt_user->fetchColumn() ?: 'Unknown Admin';

$total_offer_qty = array_sum(array_column($submitted_items, 'offer_qty'));
$total_rows_on_form = max(5, count($submitted_items));
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Transfer Form - <?php echo htmlspecialchars($request_no); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&family=Koulen&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #ccc;
            font-family: 'Kantumruy Pro', serif;
            color: #000;
            margin: 0;
        }
        .form-page {
            background: #fff;
            width: 210mm;
            height: 297mm;
            padding: 0.3cm;
            margin: 1cm auto;
            border: 1px solid #dcdcdc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        .form-header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .company-info {
            text-align: center;
        }
        .company-logo {
            font-family: 'Koulen', cursive;
            font-size: 28px;
            color: #f6ea00;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .form-title-kh {
            font-family: 'Koulen', cursive;
            font-size: 22px;
            text-decoration: underline;
            margin-top: 10px;
        }
        .address-box {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 11px;
            line-height: 1.4;
            max-width: 250px;
            text-align: right;
            margin-top: 10px;
            margin-left: auto;
        }
        .meta-info {
            padding: 15px 0;
            font-size: 14px;
        }
        .meta-info .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .meta-info .row-center {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .dotted-line {
            border-bottom: 1px dotted #000;
            padding: 0 10px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .form-table th, .form-table td {
            border: 1px solid #000;
            padding: 8px 4px;
            text-align: center;
            height: 15mm;
        }
        .form-table th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .form-table .col-no {
            width: 5%;
        }
        .form-table .col-goods {
            width: 45%;
            text-align: left;
            padding-left: 8px;
        }
        .form-table .col-request, .form-table .col-offer {
            width: 15%;
        }
        .form-table .col-notes {
            width: 20%;
        }
        .form-table .total-row td {
            font-weight: bold;
            text-align: right;
            padding-right: 10px;
            background: #e0e0e0;
        }
        .notes-section {
            margin-top: 5px;
            font-size: 14px;
        }
        .notes-section span {
            font-weight: bold;
        }
        .notes-section p {
            margin: 5px 0 0 10px;
            font-size: 13px;
        }
        .signature-section {
            margin-top: 40px; /* <<< THIS IS THE CHANGE */
            display: flex;
            justify-content: space-around;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        .signature-box {
            padding-top: 40px;
            border-top: 1px dotted #000;
        }
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: #fff;
                margin: 0;
            }
            .form-page {
                margin: 0;
                padding: 0.3cm;
                width: 210mm;
                height: 297mm;
                box-shadow: none;
                border: none;
            }
            .form-table th, .form-table td {
                height: 15mm;
            }
            .notes-section {
                margin-top: 5px;
            }
            .signature-section {
                margin-top: 40px; /* <<< And here for printing */
            }
            .signature-box {
                padding-top: 40px;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="form-page">
        <!-- The HTML body is unchanged -->
        <div class="form-header">
            <div class="company-info">
                <div class="company-logo">វណ្ណ វណ្ណ ខេមបូឌា</div>
                <div class="company-name">VAN VAN CAMBODIA</div>
                <div class="form-title-kh">ប័ណ្ណបញ្ចេញទំនិញ</div>
            </div>
            <div class="address-box">Address: No.1AEo, St.318, Sangkat Tuol SvayPrey1, Khan Beong Keng korng, Phnom Penh, Cambodia. Tell: 0962458467</div>
        </div>
        <div class="meta-info">
            <div class="row">
                <span>លេខ: <span class="dotted-line"><?php echo htmlspecialchars($request_no); ?></span></span>
                <span>ទីតាំងស្នើសុំ: <span class="dotted-line"><?php echo htmlspecialchars($location); ?></span></span>
            </div>
            <div class="row">
                <span>Date/Request: <span class="dotted-line"><?php echo date('d/m/Y'); ?></span></span>
                <span>ថ្ងៃចេញបុង: <span class="dotted-line"><?php echo date('d/m/Y'); ?></span></span>
                <span>By: <span class="dotted-line"><?php echo htmlspecialchars($processed_by_name); ?></span></span>
            </div>
        </div>
        <table class="form-table">
            <thead>
                <tr>
                    <th class="col-no">ល.រ<br>N/o</th>
                    <th class="col-goods">ឈ្មោះទំនិញ<br>Goods</th>
                    <th class="col-request">ចំនួនស្នើសុំ<br>Request</th>
                    <th class="col-offer">ចំនួនផ្តល់ជូន<br>Offer amount</th>
                    <th class="col-notes">កំណត់សំគាល់<br>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $total_rows_on_form; $i++): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td class="col-goods"><?php echo $submitted_items[$i]['name'] ?? ''; ?></td>
                    <td><?php echo $submitted_items[$i]['request_qty'] ?? ''; ?></td>
                    <td><?php echo $submitted_items[$i]['offer_qty'] ?? ''; ?></td>
                    <td><?php echo $submitted_items[$i]['notes'] ?? ''; ?></td>
                </tr>
                <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    <td><?php echo $total_offer_qty; ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div class="notes-section">
            <span>Note:</span>
            <p>សម្គាល់ៈ ដែលស្នើសុំសម្រាប់ប្រើប្រាស់ប្រចាំខែ</p>
            <p>សូមធ្វើការត្រួតពិនិត្យ សម្ភារៈ ដែលបានប្រគល់ជូនឲ្យបានត្រឹមត្រូវ និង សូមធ្វើការចុះហត្ថលេខាបញ្ជាក់</p>
        </div>
        <div class="signature-section">
            <div class="signature-box">អ្នកបញ្ចេញទំនិញ</div>
            <div class="signature-box">អ្នកដឹក</div>
            <div class="signature-box">អ្នកទទួលទំនិញ</div>
        </div>
    </div>
</body>
</html>