<?php
// Database connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

try {
    // Correct PDO connection with utf8mb4 charset
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch the latest request
try {
    $stmt = $conn->prepare("SELECT * FROM item_requests ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        $request_id = $request['id'];
        $stmt = $conn->prepare("SELECT item_name, quantity, price FROM request_items WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $request = null;
        $items = [];
        $message = "No requests found in the database.";
    }
} catch(PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
}

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="km"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Form Input - VAN VAN CAMBODIA</title>
    
    <!-- Google Fonts for Khmer -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <!-- Custom CSS Only -->
    <style>
        :root {
            --primary-gold: #d4af37;
            --primary-gold-dark: #b8972f;
            --secondary-blue: #4f46e5;
            --secondary-blue-dark: #4338ca;
            --danger-red: #ef4444;
            --danger-red-dark: #dc2626;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-800: #1f2937;
        }

        /* --- Global & Layout --- */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Hanuman', sans-serif;
            background: linear-gradient(180deg, #f9fafb 0%, var(--gray-100) 100%);
            color: var(--gray-700);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .container-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
            max-width: 64rem;
            width: 100%;
        }

        /* --- Header --- */
        .form-header {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .form-header img {
            height: 4rem;
            margin-right: 1rem;
        }
        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        /* --- Form Elements --- */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        .form-group label span {
            color: var(--danger-red);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid #d1d5db;
            background-color: #f9fafb;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--secondary-blue);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            background-color: white;
        }

        /* --- Buttons --- */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            padding: 0.75rem 1.25rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .btn i {
            margin-right: 0.25rem;
        }
        .btn-gold {
            color: white;
            background-image: linear-gradient(to right, var(--primary-gold), var(--primary-gold-dark));
        }
        .btn-gold:hover { background-image: linear-gradient(to right, var(--primary-gold-dark), var(--primary-gold)); }
        .btn-blue { color: white; background-color: var(--secondary-blue); }
        .btn-blue:hover { background-color: var(--secondary-blue-dark); }
        .btn-danger { color: white; background-color: var(--danger-red); padding: 0.375rem 0.75rem; }
        .btn-danger:hover { background-color: var(--danger-red-dark); }
        
        .submit-btn {
            width: 100%;
            font-size: 1.125rem;
            margin-top: 2rem;
        }

        /* --- Items Table Section --- */
        .items-section {
            margin-top: 2rem;
        }
        .items-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .items-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        .table-container {
            overflow-x: auto;
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            text-align: left;
        }
        table thead {
            background-color: #f9fafb;
        }
        table th {
            color: var(--gray-700);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
        }
        table tbody tr + tr {
            border-top: 1px solid var(--gray-200);
        }
        table tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        table tfoot {
            background-color: #f9fafb;
            font-weight: 700;
        }
        table tfoot td:first-child {
            text-align: right;
        }
        table .total-amount {
            font-size: 1.25rem;
            color: var(--gray-800);
        }
        table .action-cell {
            text-align: center;
        }

        /* --- Bottom Navigation --- */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.08);
            z-index: 1000;
            border-top: 1px solid var(--gray-200);
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray-500);
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .nav-item.active {
            color: var(--secondary-blue);
            transform: translateY(-5px);
        }
        .nav-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }

        /* --- Responsive Design --- */
        @media (min-width: 640px) {
            body { padding: 1.5rem; }
            .container-card { padding: 2rem; }
            .form-header h2 { font-size: 1.875rem; }
        }
        
        @media (min-width: 768px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-grid .md-col-span-2 {
                grid-column: span 2 / span 2;
            }
        }
        
        @media (max-width: 991.98px) {
            .btn-back { display: none; }
            .container-card { padding-bottom: 6rem; }
        }
        
        @media (min-width: 992px) {
            .bottom-nav { display: none; }
            .btn-back { display: inline-flex; margin-bottom: 1.5rem; }
        }

    </style>
</head>
<body>
    <div class="container-card">
        <div class="form-header">
            <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Van Van Cambodia Logo">
            <h2>សំណើសុំទំនិញ/សម្ភារៈ</h2>
        </div>
        
        <button class="btn btn-blue btn-back">
            <i class="fas fa-arrow-left"></i> ថយក្រោយ
        </button>

        <?php if (isset($message)): ?>
            <p class="error-message"><?= htmlspecialchars($message) ?></p>
        <?php endif; ?>

        <form action="submits_request.php" method="POST" accept-charset="UTF-8" onsubmit="return validateForm()">
            <!-- Main Details Section -->
            <div class="form-grid">
                <div class="form-group">
                    <label for="number">លេខរៀង (Reference No) <span>*</span></label>
                    <input type="text" name="number" id="number" value="<?= htmlspecialchars($request['number'] ?? '') ?>" placeholder="ឧ. VVC-2024-001" required>
                </div>
                <div class="form-group">
                    <label for="request_date">កាលបរិច្ឆេទស្នើសុំ <span>*</span></label>
                    <input type="date" name="request_date" id="request_date" value="<?= htmlspecialchars($request['request_date'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div class="form-group">
                    <label for="pr_no">PR No</label>
                    <input type="text" name="pr_no" id="pr_no" value="<?= htmlspecialchars($request['pr_no'] ?? '') ?>" placeholder="Enter PR No">
                </div>
                <div class="form-group">
                    <label for="request_person">ឈ្មោះអ្នកស្នើសុំ <span>*</span></label>
                    <input type="text" name="request_person" id="request_person" value="<?= htmlspecialchars($request['request_person'] ?? '') ?>" placeholder="បំពេញឈ្មោះ" required>
                </div>
                <div class="form-group">
                    <label for="position">តួនាទី</label>
                    <input type="text" name="position" id="position" value="<?= htmlspecialchars($request['position'] ?? '') ?>" placeholder="បំពេញតួនាទី">
                </div>
                <div class="form-group">
                    <label for="department">ផ្នែក</label>
                    <input type="text" name="department" id="department" value="<?= htmlspecialchars($request['department'] ?? '') ?>" placeholder="បំពេញឈ្មោះផ្នែក">
                </div>
                <div class="form-group">
                    <label for="project">គម្រោង</label>
                    <input type="text" name="project" id="project" value="<?= htmlspecialchars($request['project'] ?? '') ?>" placeholder="បំពេញឈ្មោះគម្រោង">
                </div>
                <div class="form-group">
                    <label for="none_date">កាលបរិច្ឆេទត្រូវការ</label>
                    <input type="date" name="none_date" id="none_date" value="<?= htmlspecialchars($request['none_date'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="advance_type">ប្រភេទប្រាក់កក់ <span>*</span></label>
                    <select name="advance_type" id="advance_type" required>
                        <option value="" <?= empty($request['advance_type']) ? 'selected' : '' ?>>-- សូមជ្រើសរើស --</option>
                        <option value="Project Advance" <?= ($request['advance_type'] ?? '') === 'Project Advance' ? 'selected' : '' ?>>ប្រាក់បុរេប្រទានសម្រាប់គំរោង</option>
                        <option value="Personal Advance" <?= ($request['advance_type'] ?? '') === 'Personal Advance' ? 'selected' : '' ?>>ប្រាក់បុរេប្រទានផ្ទាល់ខ្លួន</option>
                        <option value="Mission Advance" <?= ($request['advance_type'] ?? '') === 'Mission Advance' ? 'selected' : '' ?>>ប្រាក់បុរេប្រទានបេសកកម្ម</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="deadline">កាលបរិច្ឆេទទូទាត់</label>
                    <input type="date" name="deadline" id="deadline" value="<?= htmlspecialchars($request['deadline'] ?? '') ?>">
                </div>
                <div class="form-group md-col-span-2">
                    <label for="in_words">សរុបជាអក្សរ <span>*</span></label>
                    <input type="text" name="in_words" id="in_words" value="<?= htmlspecialchars($request['in_words'] ?? '') ?>" placeholder="ឧ. មួយរយដុល្លារគត់" required>
                </div>
            </div>

            <!-- Items Section -->
            <div class="items-section">
                <div class="items-header">
                    <h3>ទំនិញ/សម្ភារៈ</h3>
                    <button type="button" class="btn btn-gold" onclick="addItemRow()">
                        <i class="fa fa-plus"></i> បន្ថែម
                    </button>
                </div>
                <div class="table-container">
                    <table id="itemsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>ឈ្មោះទំនិញ</th>
                                <th>ចំនួន</th>
                                <th>តម្លៃរាយ</th>
                                <th>តម្លៃសរុប</th>
                                <th class="action-cell">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $index => $item): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><input type="text" name="items[<?= $index ?>][item_name]" value="<?= htmlspecialchars($item['item_name']) ?>" class="item-name" required></td>
                                    <td><input type="number" name="items[<?= $index ?>][quantity]" value="<?= htmlspecialchars($item['quantity']) ?>" class="item-quantity" min="1" step="1" required oninput="calculateTotal()"></td>
                                    <td><input type="number" name="items[<?= $index ?>][price]" step="0.01" value="<?= htmlspecialchars($item['price']) ?>" class="item-price" min="0" required oninput="calculateTotal()"></td>
                                    <td class="item-total">$<?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                                    <td class="action-cell"><button type="button" class="btn btn-danger" onclick="confirmDeleteItem(this)"><i class="fa fa-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="4">សរុបទឹកប្រាក់:</td>
                                <td class="total-amount">$0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <button type="submit" class="btn btn-gold submit-btn">
                <i class="fa fa-save"></i> បញ្ជូនសំណើ
            </button>
        </form>
    </div>

    <!-- Bottom Navigation for Small Screens -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item <?= $current_page === 'homes.php' ? 'active' : '' ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="#" class="nav-item <?= $current_page === 'calendar.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar nav-icon"></i>
            <span>កាលវិភាគ</span>
        </a>
        <a href="checklist.php" class="nav-item <?= $current_page === 'checklist.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>
    <script>
        function addItemRow() {
            const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
            const rowCount = table.rows.length;
            const row = table.insertRow();
            const index = rowCount;

            row.innerHTML = `
                <td>${index + 1}</td>
                <td><input type="text" name="items[${index}][item_name]" class="item-name" placeholder="ឈ្មោះទំនិញ" required></td>
                <td><input type="number" name="items[${index}][quantity]" value="1" class="item-quantity" min="1" step="1" required oninput="calculateTotal()"></td>
                <td><input type="number" name="items[${index}][price]" step="0.01" value="0.00" class="item-price" min="0" required oninput="calculateTotal()"></td>
                <td class="item-total">$0.00</td>
                <td class="action-cell"><button type="button" class="btn btn-danger" onclick="confirmDeleteItem(this)"><i class="fa fa-trash"></i></button></td>
            `;
            updateRowNumbers();
            calculateTotal();
        }

        function confirmDeleteItem(button) {
            if (confirm('តើអ្នកពិតជាចង់លុបรายการនេះមែនទេ?')) {
                const table = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
                if (table.rows.length > 1) {
                    button.closest('tr').remove();
                    updateRowNumbers();
                    calculateTotal();
                } else {
                    alert('ត្រូវមានรายการយ៉ាងហោចណាស់មួយ។');
                }
            }
        }

        function updateRowNumbers() {
            const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
            for (let i = 0; i < rows.length; i++) {
                rows[i].cells[0].textContent = i + 1;
                const inputs = rows[i].querySelectorAll('input[name^="items"]');
                if(inputs.length >= 3) {
                    inputs[0].name = `items[${i}][item_name]`;
                    inputs[1].name = `items[${i}][quantity]`;
                    inputs[2].name = `items[${i}][price]`;
                }
            }
        }

        function calculateTotal() {
            const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
            let total = 0;
            for (let row of rows) {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                const price = parseFloat(row.querySelector('.item-price').value) || 0;
                const rowTotal = quantity * price;
                row.querySelector('.item-total').textContent = `$${rowTotal.toFixed(2)}`;
                total += rowTotal;
            }
            document.querySelector('.total-amount').textContent = `$${total.toFixed(2)}`;
        }

        function validateForm() {
            const rows = document.getElementById('itemsTable').getElementsByTagName('tbody')[0].rows;
            if (rows.length === 0) {
                 alert('សូមបន្ថែមรายการទំនិញយ៉ាងហោចណាស់មួយ។');
                 return false;
            }
            for (let row of rows) {
                const itemName = row.querySelector('.item-name').value.trim();
                if (!itemName) {
                    alert('សូមបំពេញឈ្មោះទំនិញសម្រាប់គ្រប់รายการ។');
                    return false;
                }
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const tableBody = document.getElementById('itemsTable').getElementsByTagName('tbody')[0];
            if (tableBody.rows.length === 0) {
                addItemRow();
            } else {
                calculateTotal();
            }
            
            const backButton = document.querySelector('.btn-back');
            if (backButton) {
                backButton.addEventListener('click', () => {
                    history.back();
                });
            }
        });
    </script>
</body>
</html>