<?php
// FILE: create_transfer_direct.php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Simulate Admin login
}
require_once 'db_connect.php';
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch all available items for the dropdown
try {
    $stmt = $pdo->prepare("SELECT id, item_name, quantity FROM stock_items WHERE quantity > 0 ORDER BY item_name ASC");
    $stmt->execute();
    $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_items = [];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Direct Stock Transfer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Poppins', sans-serif; }
        .container { display: flex; }
        .sidebar { width: 250px; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.1); padding: 2rem 1rem; }
        .sidebar .nav-item { display: block; padding: 1rem; color: #7f8c8d; text-decoration: none; font-size: 1rem; border-radius: 8px; margin-bottom: 0.5rem;}
        .sidebar .nav-item.active { color: #00b4db; background: #e6f3f8; font-weight: 600; }
        .main-content { flex: 1; padding: 2rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); padding: 1.5rem; }
        .card h1 { margin-bottom: 1.5rem; text-align: center; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .form-group label { font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .items-table th, .items-table td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        .items-table th { background-color: #f8f9fa; }
        .items-table select, .items-table input { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
        .remove-row-btn, .add-item-btn { background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        .add-item-btn { background: #2ecc71; margin-top: 1rem; }
        .submit-btn { display: block; width: 100%; padding: 12px; background: #00b4db; color: #fff; border: none; font-weight: 600; cursor: pointer; margin-top: 1.5rem; }
    </style>
</head>
<body>
<div class="container">
    <nav class="sidebar">
        <a href="user_request_form.php" class="nav-item">Request Items</a>
        <a href="review_requests.php" class="nav-item">Admin Review</a>
        <a href="create_transfer_direct.php" class="nav-item active">Direct Transfer</a>
    </nav>
    <div class="main-content">
        <div class="card">
            <h1>Create Direct Stock Transfer</h1>
            <p style="text-align:center; margin-bottom:1rem; color:#555;">Use this form for internal transfers that don't need a user request. Stock will be deducted immediately.</p>
            
            <!-- This form now submits to a new processing file: process_direct_transfer.php -->
            <form action="process_direct_transfer.php" method="POST" target="_blank">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="transfer_title">Transfer Title</label>
                        <input type="text" id="transfer_title" name="transfer_title" value="Internal Stock Movement" required>
                    </div>
                    <div class="form-group">
                        <label for="request_no">Reference / Slip No.</label>
                        <input type="text" id="request_no" name="request_no">
                    </div>
                </div>

                <h3>Items to Transfer</h3>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Item</th>
                            <th>Quantity to Transfer</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="item-list"></tbody>
                </table>
                <button type="button" class="add-item-btn" onclick="addItemRow()">+ Add Item</button>

                <button type="submit" class="submit-btn">Process & Generate Form</button>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript logic to dynamically add/remove rows
const itemOptions = `<option value="">-- Select an item --</option><?php foreach ($available_items as $item): ?><option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars(addslashes($item['item_name'])); ?> (In Stock: <?php echo $item['quantity']; ?>)</option><?php endforeach; ?>`;
function addItemRow() {
    const tableBody = document.getElementById('item-list');
    const newRow = tableBody.insertRow();
    newRow.innerHTML = `
        <td>
            <select name="item_id[]" required onchange="updateMaxQty(this)">${itemOptions}</select>
        </td>
        <td>
            <input type="number" name="offer_qty[]" min="1" required>
        </td>
        <td>
            <input type="text" name="notes[]">
        </td>
        <td>
            <button type="button" class="remove-row-btn" onclick="this.closest('tr').remove()">Remove</button>
        </td>
    `;
}
// Optional: A helper function to set the max attribute based on stock levels
function updateMaxQty(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const stockText = selectedOption.text;
    const stockMatch = stockText.match(/In Stock: (\d+)/);
    const qtyInput = selectElement.closest('tr').querySelector('input[name="offer_qty[]"]');
    if (stockMatch) {
        qtyInput.max = stockMatch[1];
    } else {
        qtyInput.removeAttribute('max');
    }
}
document.addEventListener('DOMContentLoaded', addItemRow);
</script>
</body>
</html>