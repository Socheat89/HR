<?php
// FILE: fulfill_transfer.php (Single File Logic)
session_start();
require_once 'db_connect.php';
header('Content-Type: text/html; charset=utf-8');

// Block 1: Handle Form Submission (POST Request)
// This part runs ONLY when the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $request_id = $_POST['request_id'];
    $request_location = $_POST['request_location'];
    $action = $_POST['action'];
    $items = $_POST['items'] ?? [];
    $admin_user_id = $_SESSION['user_id'] ?? 2; // Default Admin ID=2, change if needed

    $pdo->beginTransaction();

    try {
        if ($action === 'process') {
            if (empty($items)) {
                throw new Exception("មិនមានទំនិញក្នុងសំណើរទេ។");
            }
            
            foreach ($items as $request_item_id => $details) {
                $stock_item_id = $details['stock_item_id'];
                $offered_qty = (int)$details['offered_qty'];
                $notes = $details['notes'];

                if ($offered_qty > 0) {
                    // 1. Update stock in the stock_items table
                    $stmt_update_stock = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ? AND quantity >= ?");
                    $stmt_update_stock->execute([$offered_qty, $stock_item_id, $offered_qty]);
                    
                    if ($stmt_update_stock->rowCount() == 0) {
                        throw new Exception("ស្តុកមិនគ្រប់គ្រាន់សម្រាប់ទំនិញ ID: {$stock_item_id} ឬទំនិញមិនមាន។");
                    }

                    // 2. Insert into stock_transfers to log the history (CRITICAL PART)
                    $stmt_log = $pdo->prepare(
                        "INSERT INTO stock_transfers (stock_item_id, stock_request_id, quantity_transferred, to_location, transferred_by_user_id) VALUES (?, ?, ?, ?, ?)"
                    );
                    $stmt_log->execute([$stock_item_id, $request_id, $offered_qty, $request_location, $admin_user_id]);
                }

                // 3. Update the offered quantity and notes in the request item
                $stmt_update_req_item = $pdo->prepare("UPDATE stock_request_items SET offered_quantity = ?, notes = ? WHERE id = ?");
                $stmt_update_req_item->execute([$offered_qty, $notes, $request_item_id]);
            }

            // 4. Update the main request status to 'processed'
            $stmt_update_req = $pdo->prepare("UPDATE stock_request SET status = 'processed' WHERE id = ?");
            $stmt_update_req->execute([$request_id]);

            $_SESSION['success_message'] = "សំណើរលេខ #{$request_id} ត្រូវបានដំណើរការដោយជោគជ័យ។";

        } elseif ($action === 'reject') {
            // Update the main request status to 'rejected'
            $stmt_update_req = $pdo->prepare("UPDATE stock_request SET status = 'rejected' WHERE id = ?");
            $stmt_update_req->execute([$request_id]);
            $_SESSION['success_message'] = "សំណើរលេខ #{$request_id} ត្រូវបានបដិសេធ។";
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "ដំណើរការបរាជ័យ: " . $e->getMessage();
    }

    // After processing, redirect back to the review page
    header('Location: review_requests.php');
    exit;
}


// Block 2: Display Form (GET Request)
// This part runs when the page is first loaded
if (!isset($_GET['request_id'])) {
    header('Location: review_requests.php');
    exit;
}
$request_id = $_GET['request_id'];

try {
    // Fetch request and user info
    $stmt = $pdo->prepare("
        SELECT sr.*, u.full_name 
        FROM stock_request sr 
        JOIN users u ON sr.user_id = u.id 
        WHERE sr.id = ? AND sr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $_SESSION['error_message'] = "រកមិនឃើញសំណើរ ឬសំណើរនេះត្រូវបានដំណើរការរួចហើយ។";
        header('Location: review_requests.php');
        exit;
    }

    // Fetch items within the request
    $items_stmt = $pdo->prepare("
        SELECT 
            sri.id as request_item_id, 
            si.id as stock_item_id, 
            COALESCE(si.item_name, sri.item_name_custom) as item_name, 
            si.quantity as current_stock, 
            sri.requested_quantity 
        FROM stock_request_items sri 
        LEFT JOIN stock_items si ON sri.item_id = si.id 
        WHERE sri.stock_request_id = ?
    ");
    $items_stmt->execute([$request_id]);
    $request_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ដំណើរការសំណើរផ្ទេរ #<?php echo htmlspecialchars($request_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #f0ad4e;
            --light-gray: #f8f9fa;
            --medium-gray: #dee2e6;
            --dark-gray: #495057;
            --white-color: #ffffff;
            --text-color: #333;
        }
        body {
            font-family: 'Kantumruy Pro', 'Poppins', sans-serif;
            background-color: var(--light-gray);
            color: var(--text-color);
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 2rem auto;
            background: var(--white-color);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
        }
        h1, h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        h1 {
             padding-bottom: 0.5rem;
             border-bottom: 2px solid var(--primary-color);
        }
        .request-info {
             border-bottom: 1px solid #eee; 
             padding-bottom: 1rem; 
             margin-bottom: 1rem;
        }
        .request-info p { font-size: 1.1rem; color: #555; }
        .request-info strong { color: #333; }
        .table-responsive {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--medium-gray);
            margin-top: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 16px 14px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid var(--medium-gray);
        }
        thead th {
            background-color: #f1f3f5;
            color: var(--dark-gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.9rem;
        }
        tbody tr:nth-child(even) {
            background-color: var(--light-gray);
        }
        tbody tr:hover {
            background-color: #e9ecef;
            transition: background-color 0.2s ease-in-out;
        }
        td:nth-child(2), td:nth-child(3), th:nth-child(2), th:nth-child(3) {
            text-align: center;
        }
        input[type="number"], input[type="text"] {
            width: 100%;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input[type="number"]:focus, input[type="text"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
            outline: none;
        }
        input[type="number"] { text-align: center; }
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-process { background-color: var(--success-color); color: white; font-family: 'Kantumruy Pro', 'Poppins', sans-serif; }
        .btn-process:hover { background-color: #218838; }
        .btn-reject { background-color: var(--danger-color); color: white; font-family: 'Kantumruy Pro', 'Poppins', sans-serif; }
        .btn-reject:hover { background-color: #c82333; }
        .btn-back { background-color: #6c757d; color: white; }
        .btn-back:hover { background-color: #5a6268; }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: none; /* Hide by default */
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background: var(--white-color);
            padding: 2rem 2.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
            text-align: left;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        .modal-header {
            border-bottom: 1px solid var(--medium-gray);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .modal-header h2 {
            margin: 0;
            color: var(--warning-color);
            font-size: 1.5rem;
        }
        .modal-body {
            margin-bottom: 1.5rem;
        }
        .modal-body p {
            margin: 0 0 1rem 0;
        }
        .modal-body ul {
            padding-left: 20px;
            margin: 0;
            list-style-type: '⚠️'; /* Warning emoji as bullet point */
            padding-left: 25px;
        }
        .modal-body li {
            padding-left: 10px;
            margin-bottom: 0.5rem;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        .btn-confirm {
            background-color: var(--success-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>ដំណើរការសំណើរផ្ទេរ</h1>
        <div class="request-info">
            <h2>សំណើរលេខ: #<?php echo htmlspecialchars($request['request_no']); ?></h2>
            <p><strong>អ្នកស្នើសុំ:</strong> <?php echo htmlspecialchars($request['full_name']); ?></p>
            <p><strong>ទីតាំងស្នើសុំ:</strong> <?php echo htmlspecialchars($request['location']); ?></p>
        </div>

        <form action="fulfill_transfer.php" method="POST" id="transferForm">
            <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
            <input type="hidden" name="request_location" value="<?php echo htmlspecialchars($request['location']); ?>">
            <input type="hidden" name="action" value="" id="formAction">

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 35%;">ឈ្មោះទំនិញ</th>
                            <th style="width: 15%;">ស្តុកបច្ចុប្បន្ន</th>
                            <th style="width: 15%;">ចំនួនស្នើសុំ</th>
                            <th style="width: 15%;">ចំនួនផ្តល់ជូន</th>
                            <th style="width: 20%;">កំណត់ចំណាំ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($request_items)): ?>
                            <tr><td colspan="5" style="text-align: center;">មិនមានទំនិញក្នុងសំណើរនេះទេ។</td></tr>
                        <?php else: ?>
                            <?php foreach ($request_items as $item): ?>
                            <tr class="item-row">
                                <td class="item-name">
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                    <input type="hidden" name="items[<?php echo $item['request_item_id']; ?>][stock_item_id]" value="<?php echo $item['stock_item_id']; ?>">
                                </td>
                                <td class="current-stock"><?php echo (int)$item['current_stock']; ?></td>
                                <td><?php echo (int)$item['requested_quantity']; ?></td>
                                <td>
                                    <input type="number" name="items[<?php echo $item['request_item_id']; ?>][offered_qty]" min="0" max="<?php echo (int)$item['current_stock']; ?>" value="<?php echo (int)$item['requested_quantity']; ?>" required>
                                </td>
                                <td>
                                    <input type="text" name="items[<?php echo $item['request_item_id']; ?>][notes]" placeholder="សម្គាល់ (បើមាន)">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="btn-container">
                <a href="review_requests.php" class="btn btn-back">ត្រឡប់ក្រោយ</a>
                <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="document.getElementById('formAction').value = 'reject'; return confirm('តើអ្នកពិតជាចង់បដិសេធសំណើរនេះមែនទេ?')">បដិសេធសំណើរ</button>
                <button type="button" onclick="confirmProcess()" class="btn btn-process">ដំណើរការផ្ទេរ</button>
            </div>
        </form>
    </div>

    <!-- MODAL HTML STRUCTURE -->
    <div id="customModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>ការជូនដំណឹង</h2>
            </div>
            <div id="modalBody" class="modal-body">
                <!-- Dynamic content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" id="cancelBtn" class="btn btn-cancel">បោះបង់</button>
                <button type="button" id="confirmBtn" class="btn btn-confirm">បញ្ជាក់ការផ្ទេរ</button>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('transferForm');
        const actionInput = document.getElementById('formAction');
        const modal = document.getElementById('customModal');
        const modalBody = document.getElementById('modalBody');
        const confirmBtn = document.getElementById('confirmBtn');
        const cancelBtn = document.getElementById('cancelBtn');

        function showModal(itemsWithNoStock) {
            let messageHTML = '<p>រកឃើញទំនិញមួយចំនួនមិនមានក្នុងស្តុក (ស្តុក=0)៖</p><ul>';
            itemsWithNoStock.forEach(name => {
                messageHTML += `<li>${name}</li>`;
            });
            messageHTML += '</ul><p style="margin-top: 1rem;">តើអ្នកនៅតែចង់បន្តដំណើរការផ្ទេរដែរឬទេ?</p>';
            
            modalBody.innerHTML = messageHTML;
            modal.classList.add('active');
        }

        function hideModal() {
            modal.classList.remove('active');
        }
        
        function confirmProcess() {
            const itemRows = document.querySelectorAll('.item-row');
            let itemsWithNoStock = [];

            itemRows.forEach(row => {
                const stockCell = row.querySelector('.current-stock');
                const nameCell = row.querySelector('.item-name');
                const currentStock = parseInt(stockCell.textContent.trim(), 10);
                const itemName = nameCell.textContent.trim();

                if (currentStock === 0) {
                    itemsWithNoStock.push(itemName);
                }
            });
            
            actionInput.value = 'process';

            if (itemsWithNoStock.length > 0) {
                showModal(itemsWithNoStock);
            } else {
                form.submit();
            }
        }

        // Event Listeners for Modal
        confirmBtn.addEventListener('click', () => {
            form.submit();
        });

        cancelBtn.addEventListener('click', () => {
            hideModal();
        });

        // Optional: Close modal if user clicks on the overlay
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                hideModal();
            }
        });
    </script>
</body>
</html>