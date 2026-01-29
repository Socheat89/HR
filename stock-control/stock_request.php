<?php
require_once 'db_connect.php';

// Error and success message variables
$error_message = '';
$success_message = '';

// Handle user deduction request
if (isset($_POST['deduct'])) {
    try {
        $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
        $deduct_quantity = filter_var($_POST['deduct_quantity'], FILTER_VALIDATE_INT);

        if ($item_id === false || $item_id <= 0) {
            throw new Exception('Invalid item ID.');
        }
        if ($deduct_quantity === false || $deduct_quantity <= 0) {
            throw new Exception('Quantity must be at least 1.');
        }

        // Get current quantity of the item
        $stmt = $pdo->prepare("SELECT quantity FROM stock_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $current_quantity = $stmt->fetchColumn();

        if ($current_quantity === false || $current_quantity < $deduct_quantity) {
            throw new Exception('Insufficient stock available.');
        }

        // Update the stock items table to deduct quantity
        $stmt = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
        $stmt->execute([$deduct_quantity, $item_id]);

        // Insert into requests table (use 'qty' column name)
        $stmt = $pdo->prepare(
            "INSERT INTO stock_requests (item_id, qty, status, created_at)
             VALUES (?, ?, 'pending', NOW())"
        );
        $stmt->execute([$item_id, $deduct_quantity]);

        $success_message = 'Request submitted and awaiting approval.';
    } catch (Exception $e) {
        $error_message = 'Error submitting request: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Items</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        body { background: #f5f7fa; font-family: 'Kantumruy Pro', sans-serif; }
        .item-image { max-width: 60px; max-height: 60px; object-fit: cover; }
        .modal-content { max-width: 400px; }
    </style>
</head>
<body>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="bg-white p-4 shadow-sm" style="width:250px;">
        <!-- nav items here -->
    </nav>

    <!-- Main Content -->
    <div class="flex-grow-1 p-4">
        <h1 class="mb-4">Stock Management</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>

        <!-- Search Form -->
        <div class="mb-3">
            <form class="d-flex" method="GET">
                <input class="form-control me-2" type="search" name="search" placeholder="Search items..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
        </div>

        <!-- Items Table -->
        <table class="table table-bordered bg-white">
            <thead class="table-light">
                <tr>
                    <th>ID</th><th>Image</th><th>Name</th><th>Qty</th><th>Price</th><th>Category</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($items)): foreach ($items as $item): ?>
                <tr>
                    <td><?php echo $item['id']; ?></td>
                    <td><?php echo $item['image_path'] ? '<img src="'.$item['image_path'].'" class="item-image"/>' : 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['price'],2); ?></td>
                    <td><?php echo htmlspecialchars($item['category']); ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick="showDeductModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>)">Request Deduct</button>
                    </td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center">No items found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Deduct Request Modal -->
<div class="modal fade" id="deductModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content p-4">
      <h5 class="mb-3">Request Stock Deduction</h5>
      <form method="POST">
        <input type="hidden" name="item_id" id="deductItemId">
        <div class="mb-2">Item: <strong id="deductItemName"></strong></div>
        <div class="mb-3">Available: <span id="deductCurrentQty"></span></div>
        <div class="mb-3">
          <label for="deductQuantity" class="form-label">Quantity to Deduct</label>
          <input type="number" class="form-control" name="deduct_quantity" id="deductQuantity" min="1" required>
        </div>
        <div class="d-flex justify-content-end">
          <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="deduct" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showDeductModal(id, name, qty) {
    document.getElementById('deductItemId').value = id;
    document.getElementById('deductItemName').textContent = name;
    document.getElementById('deductCurrentQty').textContent = qty;
    document.getElementById('deductQuantity').max = qty;
    new bootstrap.Modal(document.getElementById('deductModal')).show();
}
</script>
</body>
</html>
