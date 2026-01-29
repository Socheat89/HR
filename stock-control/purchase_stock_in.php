<?php
// FILE: purchase_stock_in.php (All-in-One)
session_start();
require_once 'nav_logic.php'; // ហៅ Logic រួម និងភ្ជាប់ Database

// --- AJAX REQUEST HANDLER ---
// ពិនិត្យមើលថាតើ Request នេះជា AJAX submission ដែរឬទេ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']) && $_POST['action'] === 'process_purchase') {
    
    // កំណត់ Header សម្រាប់ JSON response
    header('Content-Type: application/json; charset=utf-8'); 

    // ចាប់ផ្តើមប្រមូលទិន្នន័យពី POST
    $supplier = trim($_POST['supplier'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $item_ids = $_POST['item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];

    if (empty($item_ids) || count(array_filter($quantities)) === 0) {
        echo json_encode(['status' => 'error', 'message' => 'សូមបន្ថែមសម្ភារៈយ៉ាងហោចណាស់មួយមុខ។']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $invoice_image_path = '';
        if (isset($_FILES['invoice_image']) && $_FILES['invoice_image']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = 'Uploads/invoices/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $image_name = uniqid('invoice_') . '_' . basename($_FILES['invoice_image']['name']);
            $invoice_image_path = $upload_dir . $image_name;

            if (!move_uploaded_file($_FILES['invoice_image']['tmp_name'], $invoice_image_path)) {
                throw new Exception("បរាជ័យក្នុងការផ្ទុកឡើងរូបភាពវិក្កយបត្រ។");
            }
        }

        $stmt_transaction = $pdo->prepare("INSERT INTO purchase_transactions (supplier, invoice_number, invoice_image_path, notes) VALUES (?, ?, ?, ?)");
        $stmt_transaction->execute([$supplier, $invoice_number, $invoice_image_path, $notes]);
        $purchase_id = $pdo->lastInsertId();
        
        $stmt_item_insert = $pdo->prepare("INSERT INTO purchase_transaction_items (purchase_transaction_id, stock_item_id, quantity_added, price_at_purchase) VALUES (?, ?, ?, ?)");
        $stmt_stock_update = $pdo->prepare("UPDATE stock_items SET quantity = quantity + ?, price = ? WHERE id = ?");

        $items_processed = 0;
        for ($i = 0; $i < count($item_ids); $i++) {
            $item_id = filter_var($item_ids[$i], FILTER_VALIDATE_INT);
            $quantity = filter_var($quantities[$i], FILTER_VALIDATE_INT);
            $price = filter_var($prices[$i], FILTER_VALIDATE_FLOAT);

            if (!$item_id || $quantity <= 0 || $price === false || $price < 0) continue;

            $stmt_item_insert->execute([$purchase_id, $item_id, $quantity, $price]);
            $stmt_stock_update->execute([$quantity, $price, $item_id]);
            $items_processed++;
        }
        
        if ($items_processed === 0) {
            throw new Exception("មិនមានសម្ភារៈត្រឹមត្រូវណាមួយត្រូវបានដំណើរការឡើយ។");
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'ប្រតិបត្តិការទិញចូលស្តុកត្រូវបានកត់ត្រាដោយជោគជ័យ!']);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'ប្រតិបត្តិការបរាជ័យ៖ ' . $e->getMessage()]);
    }
    
    exit; // សំខាន់มาก! បញ្ឈប់ Script មិនให้បន្តไปแสดง HTML
}

// --- NORMAL PAGE LOAD LOGIC ---
// កូដខាងក្រោមនេះនឹងដំណើរការតែពេលបើកទំព័រធម្មតា
header('Content-Type: text/html; charset=utf-8');
$current_page = basename($_SERVER['PHP_SELF']);

$available_items = [];
try {
    $stmt = $pdo->query("SELECT id, item_name FROM stock_items ORDER BY item_name ASC");
    $available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $page_error = "មិនអាចទាញយកទិន្នន័យសម្ភារៈពីប្រព័ន្ធបានទេ: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ទិញចូលស្តុក</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', 'Kantumruy Pro', sans-serif;
        }
        body { background: var(--light-gray); color: var(--text-color); line-height: 1.5; overflow-x: hidden; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.8) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; }
        .modal.show { display: flex; animation: fadeInScale 0.3s forwards; }
        .modal-content { background: #fff; padding: 1.5rem; width: 90%; max-width: 400px; border-radius: 12px; position: relative; }
        .modal-close { position: absolute; top: 10px; right: 15px; font-size: 1.5rem; cursor: pointer; color: #aaa; }

        .container { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 1rem;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-item { display: flex; align-items: center; padding: 1rem; color: #030303; text-decoration: none; font-size: 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s ease; position: relative; }
        .sidebar .nav-item:hover { background: #ecf0f1; transform: translateX(5px); }
        .sidebar .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); }
        .sidebar .nav-item i { margin-right: 0.85rem; font-size: 1.1rem; width: 20px; text-align: center; }

        .notification-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .main-content { flex: 1; padding: 1rem; width: 100%; }
        .header { background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: #fff; padding: 1.5rem 1rem; border-radius: 0 0 20px 20px; text-align: center; margin-bottom: 1.5rem; animation: slideIn 0.5s ease-out; }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .header p { opacity: 0.9; margin-top: 0.25rem; font-size: 0.9rem;}

        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; overflow: hidden; }
        .add-header { padding: 1.25rem; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e9ecef; }
        .add-header h2 { font-size: 1.1rem; font-weight: 600; }
        .add-content { padding: 1.5rem; }
        
        .form-row { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) { .form-row { grid-template-columns: 1fr 1fr; } .form-row .col-span-2 { grid-column: span 2; } }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; color: #4a5568; margin-bottom: 0.5rem; }
        .form-control { display: block; width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9rem; transition: all 0.2s ease; }
        .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(0,180,219,0.2); }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background: #f8fafc; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; }
        td.actions { text-align: center; }

        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; transition: all 0.2s ease; text-decoration: none; }
        .btn[disabled] { background: #bdc3c7; cursor: not-allowed; transform: translateY(0); box-shadow: none; }
        .btn-primary { background: var(--primary-color); color: #fff; }
        .btn-primary:hover:not([disabled]) { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-secondary { background: #e2e8f0; color: var(--text-color); }
        .btn-secondary:hover { background: #cbd5e0; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-danger:hover { background: #c0392b; }
        .btn-lg { padding: 12px 25px; font-size: 1rem; }
        .btn i { margin-right: 8px; }

        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 0.5rem 0; z-index: 999; border-radius: 20px 20px 0 0; }
        .bottom-nav .nav-item { text-align: center; padding: 0.5rem; color: #7f8c8d; text-decoration: none; font-size: 0.75rem; flex: 1; }
        .bottom-nav .nav-item.active { color: var(--primary-color); }
        .bottom-nav .nav-item i { display: block; font-size: 1.25rem; margin-bottom: 0.25rem; }

        .select2-container .select2-selection--single { height: 42px !important; border-radius: 6px !important; border: 1px solid #e2e8f0 !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 40px !important; padding-left: 10px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: var(--primary-color); }

        @media (min-width: 769px) {
            .sidebar { display: block; }
            .main-content { padding: 2rem; margin-left: 250px; }
            .header { border-radius: 12px; }
            .bottom-nav { display: none; }
        }
        @media (max-width: 768px) {
            .main-content { padding-bottom: 6rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <header class="header">
                <h1>ទិញសម្ភារៈចូលស្តុក</h1>
                <p>កត់ត្រាការទិញសម្ភារៈពីអ្នកផ្គត់ផ្គង់ខាងក្រៅ</p>
            </header>

            <div id="notificationModal" class="modal">
                <div class="modal-content">
                     <span class="modal-close" onclick="hideModal('notificationModal')">&times;</span>
                    <h3 id="notificationTitle"></h3>
                    <p id="notificationMessage"></p>
                </div>
            </div>
            
            <form id="purchaseForm" method="POST" enctype="multipart/form-data">
                <!-- បន្ថែម Hidden Input ដើម្បីសម្គាល់ថាជា AJAX request -->
                <input type="hidden" name="action" value="process_purchase">
                
                <div class="card">
                    <div class="add-header"><h2>ព័ត៌មានប្រតិបត្តិការ</h2></div>
                    <div class="add-content">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="supplier">ឈ្មោះអ្នកផ្គត់ផ្គង់</label>
                                <input type="text" name="supplier" id="supplier" class="form-control" placeholder="ឧ. ហាងលក់សម្ភារៈ ABC">
                            </div>
                            <div class="form-group">
                                <label for="invoice_number">លេខរៀងវិក្កយបត្រ</label>
                                <input type="text" name="invoice_number" id="invoice_number" class="form-control" placeholder="ឧ. INV-00123">
                            </div>
                            <div class="form-group col-span-2">
                                <label for="notes">កំណត់ចំណាំ</label>
                                <textarea name="notes" id="notes" rows="3" class="form-control" placeholder="ព័ត៌មានបន្ថែម (បើមាន)..."></textarea>
                            </div>
                            <!-- START: Invoice Image Upload Section -->
                            <div class="form-group">
                                <label for="invoice_image">រូបភាពវិក្កយបត្រ (បើមាន)</label>
                                <input type="file" name="invoice_image" id="invoice_image" class="form-control" accept="image/*">
                                
                                <!-- Container for image preview -->
                                <div id="image_preview_container" style="margin-top: 10px; display: none; position: relative; max-width: 200px;">
                                    <img id="invoice_preview" src="#" alt="Invoice Preview" style="max-width: 100%; height: auto; border-radius: 6px; border: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                    <button type="button" id="remove_image_btn" style="position: absolute; top: 5px; right: 5px; background: rgba(255, 255, 255, 0.9); border: none; border-radius: 50%; width: 25px; height: 25px; cursor: pointer; font-size: 16px; line-height: 1; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #333; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">&times;</button>
                                </div>
                            </div>
                            <!-- END: Invoice Image Upload Section -->
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="add-header"><h2>បញ្ជីសម្ភារៈដែលបានទិញ</h2></div>
                    <div class="add-content">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 45%;">សម្ភារៈ</th>
                                        <th>បរិមាណ</th>
                                        <th>តម្លៃទិញចូល (ឯកតា)</th>
                                        <th class="actions">សកម្មភាព</th>
                                    </tr>
                                </thead>
                                <tbody id="item-list"></tbody>
                            </table>
                        </div>
                        <div style="margin-top: 1.5rem;">
                            <button type="button" id="add-item-btn" class="btn btn-secondary">
                                <i class="fa-solid fa-plus"></i> បន្ថែមសម្ភារៈ
                            </button>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" name="submit_purchase" id="submitBtn" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-save"></i> <span id="submitBtnText">រក្សាទុកការទិញចូល</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង</a>
        <a href="index.php" class="nav-item"><i class="fa-solid fa-box-archive"></i> ទំនិញ</a>
        <a href="reports.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i> របាយការណ៍</a>
        <a href="purchase_stock_in.php" class="nav-item <?php echo $current_page == 'purchase_stock_in.php' ? 'active' : ''; ?>"><i class="fa-solid fa-truck-fast"></i> ទិញចូល</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const availableItems = <?php echo json_encode($available_items); ?>;
        
        function showNotification(status, message) {
            const modal = document.getElementById('notificationModal');
            const title = document.getElementById('notificationTitle');
            const msg = document.getElementById('notificationMessage');
            
            if (status === 'success') {
                title.style.color = '#27ae60';
                title.textContent = 'ជោគជ័យ';
            } else {
                title.style.color = '#c0392b';
                title.textContent = 'កំហុស';
            }
            msg.textContent = message;
            modal.classList.add('show');
        }

        window.hideModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if(modal) modal.classList.remove('show');
        }
        
        function initializeSelect2(selector) {
            $(selector).select2({
                placeholder: '-- ជ្រើសរើសសម្ភារៈ --',
                width: '100%'
            });
        }

        function createItemRow() {
            const tableBody = document.getElementById('item-list');
            const newRow = document.createElement('tr');
            newRow.className = 'item-row';

            let optionsHTML = '<option></option>';
            availableItems.forEach(item => {
                optionsHTML += `<option value="${item.id}">${item.item_name}</option>`;
            });

            newRow.innerHTML = `
                <td><select name="item_id[]" class="item-select" required>${optionsHTML}</select></td>
                <td><input type="number" name="quantity[]" class="form-control" min="1" placeholder="0" required></td>
                <td><input type="number" name="price[]" class="form-control" step="0.01" min="0" placeholder="0.00" required></td>
                <td class="actions"><button type="button" class="btn btn-danger remove-item-btn">លុប</button></td>
            `;
            tableBody.appendChild(newRow);
            initializeSelect2(newRow.querySelector('.item-select'));
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            createItemRow();

            document.getElementById('add-item-btn').addEventListener('click', createItemRow);
            
            document.getElementById('item-list').addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-item-btn')) {
                    const row = e.target.closest('.item-row');
                    $(row).find('.item-select').select2('destroy');
                    row.remove();
                }
            });

            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    hideModal(event.target.id);
                }
            }

            const purchaseForm = document.getElementById('purchaseForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitBtnText = document.getElementById('submitBtnText');

            purchaseForm.addEventListener('submit', function(e) {
                e.preventDefault(); 

                const formData = new FormData(purchaseForm);
                const originalBtnText = submitBtnText.innerHTML;

                const itemCount = document.querySelectorAll('.item-row').length;
                if (itemCount === 0 || !document.querySelector('.item-select').value) {
                    showNotification('error', 'សូមបន្ថែម និងជ្រើសរើសសម្ភារៈយ៉ាងហោចណាស់មួយមុខ។');
                    return;
                }

                submitBtn.disabled = true;
                submitBtnText.innerHTML = 'កំពុងដំណើរការ...';
                submitBtn.querySelector('i').classList.add('fa-spin');

                fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showNotification('success', data.message);
                        purchaseForm.reset(); 
                        $('#item-list').empty(); 
                        $('.item-select').select2('destroy');
                        createItemRow();
                        // Reset image preview as well
                        document.getElementById('remove_image_btn').click(); 
                    } else {
                        showNotification('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('error', 'មានបញ្ហាក្នុងការភ្ជាប់ទៅកាន់ Server។ សូមពិនិត្យ Console សម្រាប់ព័ត៌មានបន្ថែម។');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtnText.innerHTML = originalBtnText;
                    submitBtn.querySelector('i').classList.remove('fa-spin');
                });
            });

            // START: Logic for Image Preview
            const invoiceInput = document.getElementById('invoice_image');
            const previewContainer = document.getElementById('image_preview_container');
            const imagePreview = document.getElementById('invoice_preview');
            const removeBtn = document.getElementById('remove_image_btn');

            invoiceInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                }
            });

            removeBtn.addEventListener('click', function() {
                invoiceInput.value = ''; // This is crucial to clear the selected file
                imagePreview.src = '#';
                previewContainer.style.display = 'none';
            });
            // END: Logic for Image Preview
        });
    </script>
</body>
</html>