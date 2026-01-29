<?php
// ======================================================================
// SECTION 1: SESSION, SECURITY & BACKEND LOGIC
// ======================================================================
session_start();
// NOTE: Composer autoloader is NOT needed here for the cPanel version.

ini_set('display_errors', 1);
error_reporting(E_ALL);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// SECURITY CHECK: If user is not logged in, redirect to login page.
if (!isset($_SESSION['user_id']) && $action !== 'login') {
    header('Location: login.php');
    exit();
}

require_once 'db.php'; 

// --- FORM & ACTION HANDLING ---
if (!empty($action)) {
    switch ($action) {
        // --- LOGIN ACTION ---
        case 'login':
            // ... (This logic is usually in login.php, but kept here for completeness) ...
            break;

        // --- CREATE/UPDATE/DELETE ACTIONS ---
        case 'create_asset':
            $asset_tag = $_POST['asset_tag']; $model = $_POST['model']; $status = $_POST['status'];
            $serial_number = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
            $warranty_expiry_date = !empty($_POST['warranty_expiry_date']) ? $_POST['warranty_expiry_date'] : NULL;
            $notes = !empty($_POST['notes']) ? $_POST['notes'] : NULL;
            $asset_type_id = !empty($_POST['asset_type_id']) ? (int)$_POST['asset_type_id'] : NULL;
            $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : NULL;
            $sql = "INSERT INTO assets (asset_tag, model, status, serial_number, purchase_date, warranty_expiry_date, notes, asset_type_id, location_id, assigned_to_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql); $stmt->bind_param("sssssssiii", $asset_tag, $model, $status, $serial_number, $purchase_date, $warranty_expiry_date, $notes, $asset_type_id, $location_id, $assigned_to_user_id);
            if ($stmt->execute()) { header("Location: index.php?status=created"); } else { header("Location: index.php?view=add_asset&error=db"); }
            $stmt->close(); exit();
        case 'update_asset':
            $id = $_POST['id']; $asset_tag = $_POST['asset_tag']; $model = $_POST['model']; $status = $_POST['status'];
            $serial_number = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
            $warranty_expiry_date = !empty($_POST['warranty_expiry_date']) ? $_POST['warranty_expiry_date'] : NULL;
            $notes = !empty($_POST['notes']) ? $_POST['notes'] : NULL;
            $asset_type_id = !empty($_POST['asset_type_id']) ? (int)$_POST['asset_type_id'] : NULL;
            $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : NULL;
            $sql = "UPDATE assets SET asset_tag = ?, model = ?, status = ?, serial_number = ?, purchase_date = ?, warranty_expiry_date = ?, notes = ?, asset_type_id = ?, location_id = ?, assigned_to_user_id = ? WHERE id = ?";
            $stmt = $conn->prepare($sql); $stmt->bind_param("sssssssiiii", $asset_tag, $model, $status, $serial_number, $purchase_date, $warranty_expiry_date, $notes, $asset_type_id, $location_id, $assigned_to_user_id, $id);
            if ($stmt->execute()) { header("Location: index.php?status=updated"); } else { header("Location: index.php?view=add_asset&id=$id&error=db"); }
            $stmt->close(); exit();
        case 'create_type':
            $name = trim($_POST['name']);
            if (empty($name)) { header("Location: index.php?view=manage_types&error=empty"); exit(); }
            $stmt = $conn->prepare("INSERT INTO asset_types (name) VALUES (?)"); $stmt->bind_param("s", $name);
            if (!$stmt->execute()) { $error_param = ($stmt->errno == 1062) ? "duplicate" : "db"; header("Location: index.php?view=manage_types&error=$error_param&name=" . urlencode($name));
            } else { header("Location: index.php?view=manage_types&success=1"); }
            $stmt->close(); exit();
        case 'create_location':
            $name = trim($_POST['name']); $address = !empty(trim($_POST['address'])) ? trim($_POST['address']) : NULL;
            if (empty($name)) { header("Location: index.php?view=manage_locations&error=empty"); exit(); }
            $stmt = $conn->prepare("INSERT INTO locations (name, address) VALUES (?, ?)"); $stmt->bind_param("ss", $name, $address);
            if (!$stmt->execute()) { $error_param = ($stmt->errno == 1062) ? "duplicate" : "db"; header("Location: index.php?view=manage_locations&error=$error_param&name=" . urlencode($name) . "&address=" . urlencode($address));
            } else { header("Location: index.php?view=manage_locations&success=1"); }
            $stmt->close(); exit();
        case 'delete_asset':
             if (isset($_GET['id'])) {
                $id = (int)$_GET['id'];
                $stmt = $conn->prepare("DELETE FROM assets WHERE id = ?"); $stmt->bind_param("i", $id);
                if ($stmt->execute()) { header("Location: index.php?status=deleted"); } else { header("Location: index.php?status=delete_error"); }
                $stmt->close(); exit();
            } break;

        // ======================================================================
        // ACTION FOR GOOGLE SHEET IMPORT
        // ======================================================================
        case 'import_from_sheet':
            $view = 'import_sheet'; // Ensure we render the import page
            $page_title = 'Import from Google Sheet';
            $import_step = $_POST['step'] ?? 'initial';
            $preview_data = ['valid' => [], 'invalid' => []];
            $import_error = '';

            // --- Step 1: Preview Data ---
            if ($import_step === 'preview' && !empty($_POST['sheet_url'])) {
                $sheet_url = filter_var($_POST['sheet_url'], FILTER_SANITIZE_URL);
                if (strpos($sheet_url, 'docs.google.com/spreadsheets/d/e/') === false || strpos($sheet_url, 'pub?output=csv') === false) {
                    $import_error = "Invalid Google Sheet URL. Please ensure you are using the 'Publish to the web' CSV link.";
                } else {
                    // Fetch data from URL
                    $csv_data = @file_get_contents($sheet_url);
                    if ($csv_data === false) {
                        $import_error = "Could not fetch data from the URL. Please check if the link is correct and public.";
                    } else {
                        // Helper function to create lookup maps
                        function create_lookup_map($conn, $table, $key_col, $val_col) {
                            $map = [];
                            $result = $conn->query("SELECT $key_col, $val_col FROM $table");
                            while ($row = $result->fetch_assoc()) {
                                $map[strtolower(trim($row[$val_col]))] = $row[$key_col];
                            }
                            return $map;
                        }

                        // Create lookup maps for foreign keys
                        $type_map = create_lookup_map($conn, 'asset_types', 'id', 'name');
                        $location_map = create_lookup_map($conn, 'locations', 'id', 'name');
                        $user_map = create_lookup_map($conn, 'users', 'id', 'name');

                        // Get existing asset tags to check for duplicates
                        $existing_tags_result = $conn->query("SELECT asset_tag FROM assets");
                        $existing_tags = [];
                        while($row = $existing_tags_result->fetch_assoc()) {
                            $existing_tags[] = $row['asset_tag'];
                        }

                        $lines = explode("\n", trim($csv_data));
                        $header = array_map('trim', str_getcsv(array_shift($lines)));
                        $required_headers = ['Asset Tag', 'Model', 'Status', 'Asset Type'];
                        $missing_headers = array_diff($required_headers, $header);

                        if (!empty($missing_headers)) {
                            $import_error = "CSV is missing required headers: " . implode(', ', $missing_headers);
                        } else {
                            $row_num = 1;
                            foreach ($lines as $line) {
                                if (empty(trim($line))) continue;
                                $row_num++;
                                $row_data = array_pad(str_getcsv($line), count($header), '');
                                $row = array_combine($header, $row_data);

                                $errors = [];
                                // --- VALIDATION ---
                                $asset_tag = trim($row['Asset Tag'] ?? '');
                                if (empty($asset_tag)) $errors[] = "Asset Tag is required.";
                                if (in_array($asset_tag, $existing_tags)) $errors[] = "Asset Tag already exists in DB (will be skipped).";
                                if (empty(trim($row['Model'] ?? ''))) $errors[] = "Model is required.";
                                if (empty(trim($row['Status'] ?? ''))) $errors[] = "Status is required.";
                                
                                $type_name = strtolower(trim($row['Asset Type'] ?? ''));
                                $location_name = strtolower(trim($row['Location'] ?? ''));
                                $user_name = strtolower(trim($row['Assigned To User'] ?? ''));

                                if (empty($type_name)) {
                                    $errors[] = "Asset Type is required.";
                                    $type_id = null;
                                } else {
                                    $type_id = $type_map[$type_name] ?? null;
                                    if ($type_id === null) $errors[] = "Asset Type '$type_name' not found.";
                                }

                                $location_id = !empty($location_name) ? ($location_map[$location_name] ?? null) : null;
                                if (!empty($location_name) && $location_id === null) $errors[] = "Location '$location_name' not found.";

                                $user_id = !empty($user_name) ? ($user_map[$user_name] ?? null) : null;
                                if (!empty($user_name) && $user_id === null) $errors[] = "User '$user_name' not found.";
                                
                                $purchase_date = !empty(trim($row['Purchase Date'] ?? '')) ? date('Y-m-d', strtotime(trim($row['Purchase Date']))) : null;
                                $warranty_date = !empty(trim($row['Warranty Expiry Date'] ?? '')) ? date('Y-m-d', strtotime(trim($row['Warranty Expiry Date']))) : null;

                                $processed_row = [
                                    'asset_tag' => $asset_tag,
                                    'model' => trim($row['Model'] ?? ''),
                                    'status' => trim($row['Status'] ?? 'In Stock'),
                                    'serial_number' => !empty(trim($row['Serial Number'] ?? '')) ? trim($row['Serial Number']) : null,
                                    'purchase_date' => $purchase_date,
                                    'warranty_expiry_date' => $warranty_date,
                                    'notes' => !empty(trim($row['Notes'] ?? '')) ? trim($row['Notes']) : null,
                                    'asset_type_id' => $type_id,
                                    'location_id' => $location_id,
                                    'assigned_to_user_id' => $user_id,
                                    'original' => $row // Keep original data for display
                                ];

                                if (empty($errors) || (count($errors) == 1 && strpos($errors[0], 'already exists'))) {
                                    $preview_data['valid'][] = $processed_row;
                                } else {
                                    $preview_data['invalid'][] = ['data' => $processed_row, 'errors' => $errors, 'row_num' => $row_num];
                                }
                            }
                        }
                    }
                }
            }
            // --- Step 2: Commit Data ---
            elseif ($import_step === 'commit' && !empty($_POST['import_data'])) {
                $data_to_import = json_decode($_POST['import_data'], true);
                $added = 0; $skipped = 0; $failed = 0;

                $sql = "INSERT INTO assets (asset_tag, model, status, serial_number, purchase_date, warranty_expiry_date, notes, asset_type_id, location_id, assigned_to_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                // Check for existing tags again just in case
                $existing_tags_result = $conn->query("SELECT asset_tag FROM assets");
                $existing_tags = [];
                while($row = $existing_tags_result->fetch_assoc()) {
                    $existing_tags[] = $row['asset_tag'];
                }

                foreach ($data_to_import as $row) {
                    if (in_array($row['asset_tag'], $existing_tags)) {
                        $skipped++;
                        continue;
                    }
                    $stmt->bind_param("sssssssiii", 
                        $row['asset_tag'], $row['model'], $row['status'], $row['serial_number'], 
                        $row['purchase_date'], $row['warranty_expiry_date'], $row['notes'], 
                        $row['asset_type_id'], $row['location_id'], $row['assigned_to_user_id']
                    );
                    if ($stmt->execute()) {
                        $added++;
                    } else {
                        $failed++;
                    }
                }
                $stmt->close();
                header("Location: index.php?status=import_result&added=$added&skipped=$skipped&failed=$failed");
                exit();
            }
            break; // End of 'import_from_sheet' case
        
        // ======================================================================
        // ACTION FOR PRINTING LABELS (WITH LOCATION & USER)
        // ======================================================================
        case 'print_labels':
            header('Content-Type: text/html; charset=utf-8');
            $assets_to_print = [];

            // Case 1: Multiple assets from POST
            if (isset($_POST['assets']) && is_array($_POST['assets'])) {
                foreach ($_POST['assets'] as $asset) {
                    if (isset($asset['tag'])) {
                        $assets_to_print[] = [
                            'tag'      => htmlspecialchars($asset['tag']),
                            'serial'   => isset($asset['serial']) ? htmlspecialchars($asset['serial']) : 'N/A',
                            'location' => isset($asset['location']) ? htmlspecialchars($asset['location']) : 'N/A',
                            'user'     => isset($asset['user']) ? htmlspecialchars($asset['user']) : 'N/A'
                        ];
                    }
                }
            } 
            // Case 2: Single asset from GET
            else if (isset($_GET['tag'])) {
                $assets_to_print[] = [
                    'tag'      => htmlspecialchars($_GET['tag']),
                    'serial'   => isset($_GET['serial']) ? htmlspecialchars($_GET['serial']) : 'N/A',
                    'location' => isset($_GET['location']) ? htmlspecialchars($_GET['location']) : 'N/A',
                    'user'     => isset($_GET['user']) ? htmlspecialchars($_GET['user']) : 'N/A'
                ];
            }

            if (empty($assets_to_print)) {
                echo "No assets selected for printing.";
                exit();
            }
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Print Asset Labels</title>
                <style>
                    @media screen {
                        body { font-family: sans-serif; text-align: center; padding-top: 20px; background-color: #f0f0f0; }
                        .controls { margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 15px;}
                        .page { display: inline-flex; flex-wrap: wrap; gap: 4mm; padding: 5mm; background-color: white; border: 1px solid #ccc; max-width: 210mm; }
                    }
                    @media print {
                        body { margin: 0; padding: 0; }
                        .controls { display: none; }
                        .page { display: flex; flex-wrap: wrap; gap: 4mm; padding: 0; margin: 0; border: none; width: 100%; height: 100%; page-break-after: always; }
                    }
                    .label {
                        box-sizing: border-box;
                        width: 60mm;
                        height: 35mm; /* Increased height for new info */
                        padding: 2mm;
                        border: 1px dashed #999;
                        overflow: hidden;
                        display: flex;
                        flex-direction: column;
                        justify-content: flex-start;
                        align-items: center;
                        text-align: center;
                        font-family: 'Khmer OS System', Arial, sans-serif;
                    }
                    .asset-tag {
                        font-size: 16pt;
                        font-weight: bold;
                        margin-bottom: 2px;
                        word-break: break-all;
                    }
                    .serial-number {
                        font-size: 9pt;
                        color: #333;
                        word-break: break-all;
                        margin-bottom: 3px;
                    }
                    .details-container {
                        margin-top: auto; /* Push to the bottom */
                        width: 100%;
                        border-top: 1px solid #eee;
                        padding-top: 2px;
                    }
                    .detail-item {
                        font-size: 8pt;
                        text-align: left;
                        line-height: 1.2;
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .detail-label {
                        font-weight: bold;
                    }
                </style>
            </head>
            <body>
                <div class="controls">
                    <h1>Print Preview</h1>
                    <p>Use your browser's print dialog (Ctrl+P or Cmd+P) to print.</p>
                    <button onclick="window.print()">Print Now</button>
                    <button onclick="window.close()">Close Window</button>
                </div>

                <div class="page">
                    <?php foreach ($assets_to_print as $asset): ?>
                    <div class="label">
                        <p class="asset-tag"><?php echo $asset['tag']; ?></p>
                        <?php if(!empty($asset['serial']) && $asset['serial'] !== 'N/A'): ?>
                        <p class="serial-number">S/N: <?php echo $asset['serial']; ?></p>
                        <?php endif; ?>
                        
                        <div class="details-container">
                            <?php if(!empty($asset['location']) && $asset['location'] !== 'N/A'): ?>
                            <div class="detail-item"><span class="detail-label">ទីតាំង:</span> <?php echo $asset['location']; ?></div>
                            <?php endif; ?>
                             <?php if(!empty($asset['user']) && $asset['user'] !== 'N/A'): ?>
                            <div class="detail-item"><span class="detail-label">អ្នកប្រើ:</span> <?php echo $asset['user']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <script>
                    window.onload = function() { window.print(); };
                </script>
            </body>
            </html>
            <?php
            exit(); // IMPORTANT: Stop script execution to only show the print page

        // --- SCANNER AJAX ACTIONS ---
        case 'get_asset_by_tag':
            header('Content-Type: application/json');
            $tag = $_GET['tag'] ?? '';
            if (empty($tag)) {
                echo json_encode(['error' => 'Asset tag is required.']);
                exit();
            }
            $stmt = $conn->prepare("SELECT id, asset_tag, model, status, location_id, assigned_to_user_id FROM assets WHERE asset_tag = ?");
            $stmt->bind_param("s", $tag);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($asset = $result->fetch_assoc()) {
                echo json_encode(['success' => true, 'asset' => $asset]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Asset not found.']);
            }
            $stmt->close();
            exit();

        case 'quick_update_asset':
            header('Content-Type: application/json');
            $id = $_POST['id'] ?? 0;
            $status = $_POST['status'] ?? '';
            $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : NULL;
            
            if (empty($id) || empty($status)) {
                echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
                exit();
            }
            
            $stmt = $conn->prepare("UPDATE assets SET status = ?, location_id = ?, assigned_to_user_id = ? WHERE id = ?");
            $stmt->bind_param("siii", $status, $location_id, $assigned_to_user_id, $id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Asset updated successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error.']);
            }
            $stmt->close();
            exit();
    }
}

// ======================================================================
// SECTION 2: VIEW ROUTING & DATA PREPARATION
// ======================================================================
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
// If an action was run that needs to render a specific view, it will have already set $view
if (empty($page_title)) {
    $page_title = 'Dashboard';
}


function get_status_badge($status) {
    $map = ['deployed' => 'bg-success', 'in stock' => 'bg-info', 'in repair' => 'bg-warning text-dark', 'retired' => 'bg-secondary'];
    $class = $map[strtolower($status)] ?? 'bg-light text-dark';
    return "<span class=\"badge rounded-pill $class\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}

switch ($view) {
    case 'add_asset':
        $asset = ['id' => '','asset_tag' => '','serial_number' => '','model' => '','status' => 'In Stock','purchase_date' => '','warranty_expiry_date' => '','notes' => '','asset_type_id' => '','location_id' => '','assigned_to_user_id' => ''];
        $page_title = 'Add New Asset'; $form_action = 'create_asset';
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM assets WHERE id = ?"); $stmt->bind_param("i", $id); $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) { $asset = $result->fetch_assoc(); $page_title = 'Edit Asset'; $form_action = 'update_asset'; }
            $stmt->close();
        }
        $types = $conn->query("SELECT id, name FROM asset_types ORDER BY name");
        $locations = $conn->query("SELECT id, name FROM locations ORDER BY name");
        $users = $conn->query("SELECT id, name FROM users ORDER BY name");
        break;

    case 'import_sheet':
        // The logic is handled in the action handler, this just ensures the view is routed correctly.
        $page_title = $page_title ?? 'Import from Google Sheet';
        break;

    case 'manage_types': $page_title = 'Manage Asset Types'; break;
    case 'manage_locations': $page_title = 'Manage Locations'; break;

    case 'scan_asset':
        $page_title = 'Scan Asset';
        $locations = $conn->query("SELECT id, name FROM locations ORDER BY name");
        $users = $conn->query("SELECT id, name FROM users ORDER BY name");
        break;
        
    case 'dashboard': default:
        $stats = ['total' => 0, 'deployed' => 0, 'in_stock' => 0, 'in_repair' => 0, 'retired' => 0];
        $result = $conn->query("SELECT status, COUNT(id) as count FROM assets GROUP BY status");
        if ($result) { while($row = $result->fetch_assoc()) { $key = str_replace(' ', '_', strtolower($row['status'])); if (array_key_exists($key, $stats)) $stats[$key] = $row['count']; $stats['total'] += $row['count']; } }
        $sql = "SELECT a.id, a.asset_tag, a.model, a.serial_number, a.status, t.name AS type_name, l.name AS location_name, u.name AS user_name FROM assets a LEFT JOIN asset_types t ON a.asset_type_id = t.id LEFT JOIN locations l ON a.location_id = l.id LEFT JOIN users u ON a.assigned_to_user_id = u.id ORDER BY a.updated_at DESC, a.id DESC";
        $all_assets_result = $conn->query($sql);
        break;
}

$alert_message = '';
if (isset($_GET['status'])) {
    $status_map = [
        'created' => ['type' => 'success', 'msg' => 'New asset has been added.'], 
        'updated' => ['type' => 'success', 'msg' => 'Asset details updated.'], 
        'deleted' => ['type' => 'info', 'msg' => 'Asset has been deleted.'], 
        'delete_error' => ['type' => 'danger', 'msg' => 'Error: Could not delete asset.']
    ];
    if (array_key_exists($_GET['status'], $status_map)) { 
        $s = $status_map[$_GET['status']]; 
        $alert_message = "<div class='alert alert-{$s['type']} alert-dismissible fade show' role='alert'>{$s['msg']}<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"; 
    } elseif ($_GET['status'] === 'import_result') {
        $added = (int)($_GET['added'] ?? 0);
        $skipped = (int)($_GET['skipped'] ?? 0);
        $failed = (int)($_GET['failed'] ?? 0);
        $alert_message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Import Complete!</strong><br>
            - <strong>{$added}</strong> assets successfully added.<br>
            - <strong>{$skipped}</strong> assets skipped (already existed).<br>
            - <strong>{$failed}</strong> assets failed to import.
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - IT Asset Manager</title>
    
    <!-- Libs -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/2.0.3/css/dataTables.bootstrap5.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/3.0.1/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Khmer+OS+System&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #212529;
            --sidebar-link-color: #adb5bd;
            --sidebar-link-hover: #fff;
            --sidebar-link-active: #fff;
            --sidebar-active-bg: #0d6efd;
        }
        body { background-color: #f8f9fa; font-family: 'Khmer OS System', sans-serif; }
        .wrapper { display: flex; width: 100%; }
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background-color: var(--sidebar-bg);
            color: white;
            transition: margin-left 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
        }
        #sidebar .sidebar-header { padding: 1.25rem; background-color: rgba(0,0,0,0.2); text-align: center; }
        #sidebar .sidebar-header h3 { font-size: 1.5rem; margin-bottom: 0; font-weight: 600; }
        #sidebar .nav-link { padding: 0.8rem 1.25rem; color: var(--sidebar-link-color); font-size: 1.05rem; transition: all 0.2s; border-left: 4px solid transparent; }
        #sidebar .nav-link:hover { background-color: rgba(255,255,255,0.05); color: var(--sidebar-link-hover); border-left-color: var(--sidebar-link-hover); }
        #sidebar .nav-link.active { background-color: var(--sidebar-active-bg); color: var(--sidebar-link-active); border-left-color: var(--sidebar-link-active); font-weight: 500; }
        #sidebar .nav-link .fa-fw { width: 1.5em; }
        .sidebar-footer { margin-top: auto; background-color: rgba(0,0,0,0.2); }
        #content { flex-grow: 1; padding: 1.5rem; transition: margin-left 0.3s ease-in-out; }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        #sidebar-toggle { display: none; }
        .stat-card a { text-decoration: none; }
        .stat-card .card { border: 0; border-left: 5px solid; transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card .card:hover { transform: translateY(-5px); box-shadow: 0 0.5rem 1rem rgba(0,0,0,.15)!important; }
        .stat-card .card-title { font-size: 2.25rem; font-weight: bold; }
        .action-icons a { margin: 0 5px; font-size: 1.1rem; }
        #reader { width: 100%; max-width: 500px; border-radius: 8px; margin: auto; overflow: hidden; }
        th.check-col { width: 1em; } /* Style for checkbox column */
        .import-instructions ul { padding-left: 20px; }
        .preview-table { font-size: 0.9rem; }
        .preview-table .error-cell { background-color: #f8d7da; }
        .preview-table .skip-cell { background-color: #fff3cd; }
        
        @media (min-width: 992px) {
            #content { padding: 2rem; }
        }
        @media (max-width: 991.98px) {
            #sidebar { margin-left: calc(-1 * var(--sidebar-width)); position: fixed; top: 0; left: 0; z-index: 1050; }
            #sidebar.active { margin-left: 0; }
            #content { width: 100%; margin-left: 0; }
            .content-header h1 { font-size: 1.75rem; }
            #sidebar-toggle { display: block !important; }
            .overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1040; }
            #sidebar.active ~ .overlay { display: block; }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- ======================================================================
    SECTION 3: SIDEBAR NAVIGATION
    ======================================================================= -->
    <aside id="sidebar">
        <div class="sidebar-header"><h3><i class="fas fa-server"></i> IT Asset Manager</h3></div>
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'dashboard') ? 'active' : ''; ?>" href="?view=dashboard"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'add_asset') ? 'active' : ''; ?>" href="?view=add_asset"><i class="fas fa-plus-circle fa-fw"></i> Add Asset</a></li>
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'import_sheet') ? 'active' : ''; ?>" href="?view=import_sheet"><i class="fas fa-file-import fa-fw"></i> Import Data</a></li>
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'scan_asset') ? 'active' : ''; ?>" href="?view=scan_asset"><i class="fas fa-qrcode fa-fw"></i> Scan Asset</a></li>
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'manage_types') ? 'active' : ''; ?>" href="?view=manage_types"><i class="fas fa-sitemap fa-fw"></i> Manage Types</a></li>
            <li class="nav-item"><a class="nav-link <?php echo ($view === 'manage_locations') ? 'active' : ''; ?>" href="?view=manage_locations"><i class="fas fa-map-marker-alt fa-fw"></i> Manage Locations</a></li>
        </ul>
        <div class="sidebar-footer p-3"><div class="d-flex align-items-center text-white"><i class="fas fa-user-circle fa-2x me-2"></i><div><strong class="d-block"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></strong><a href="logout.php" class="text-decoration-none" style="color: var(--sidebar-link-color);"><i class="fas fa-sign-out-alt fa-sm"></i> Logout</a></div></div></div>
    </aside>
    <div class="overlay"></div>

    <!-- ======================================================================
    SECTION 4: MAIN CONTENT AREA
    ======================================================================= -->
    <main id="content">
        <header class="content-header">
            <div class="d-flex align-items-center">
                 <button id="sidebar-toggle" class="btn btn-dark me-3"><i class="fas fa-bars"></i></button>
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
            </div>
            <?php if($view === 'dashboard'): ?>
                <div class="d-flex gap-2">
                    <button id="print-selected-btn" class="btn btn-info" disabled><i class="fas fa-print me-1"></i> Print Selected (<span id="selected-count">0</span>)</button>
                    <a href="?view=add_asset" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add New Asset</a>
                </div>
            <?php endif; ?>
        </header>

        <?php echo $alert_message; ?>

        <?php if ($view === 'dashboard'): ?>
            <!-- ... Dashboard content remains the same ... -->
            <div class="row g-3 g-lg-4 mb-4">
                <div class="col-6 col-lg-3 stat-card"><a href="#"><div class="card shadow-sm border-primary"><div class="card-body"><h5 class="card-title text-primary"><?php echo $stats['total']; ?></h5><p class="card-text text-muted">Total</p></div></div></a></div>
                <div class="col-6 col-lg-3 stat-card"><a href="#"><div class="card shadow-sm border-success"><div class="card-body"><h5 class="card-title text-success"><?php echo $stats['deployed']; ?></h5><p class="card-text text-muted">Deployed</p></div></div></a></div>
                <div class="col-6 col-lg-3 stat-card"><a href="#"><div class="card shadow-sm border-info"><div class="card-body"><h5 class="card-title text-info"><?php echo $stats['in_stock']; ?></h5><p class="card-text text-muted">In Stock</p></div></div></a></div>
                <div class="col-6 col-lg-3 stat-card"><a href="#"><div class="card shadow-sm border-warning"><div class="card-body"><h5 class="card-title text-warning"><?php echo $stats['in_repair']; ?></h5><p class="card-text text-muted">In Repair</p></div></div></a></div>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3"><h5 class="mb-0">All Assets</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="assetsTable" class="table table-hover align-middle" style="width:100%">
                            <thead>
                                <tr>
                                    <th class="check-col no-sort"><input class="form-check-input" type="checkbox" id="select-all-checkbox"></th>
                                    <th>Tag</th>
                                    <th>Type</th>
                                    <th>Model</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Assigned</th>
                                    <th class="text-center no-sort">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($all_assets_result && $all_assets_result->num_rows > 0): while($row = $all_assets_result->fetch_assoc()): ?>
                                <tr>
                                    <td><input class="form-check-input asset-checkbox" type="checkbox" 
                                               data-tag="<?php echo htmlspecialchars($row['asset_tag'] ?? ''); ?>" 
                                               data-serial="<?php echo htmlspecialchars($row['serial_number'] ?? ''); ?>"
                                               data-location="<?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?>"
                                               data-user="<?php echo htmlspecialchars($row['user_name'] ?? 'N/A'); ?>">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($row['asset_tag'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['type_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['model'] ?? ''); ?></td>
                                    <td><?php echo get_status_badge($row['status'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['location_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['user_name'] ?? 'N/A'); ?></td>
                                    <td class="text-center action-icons">
                                        <a href="?action=print_labels&tag=<?php echo urlencode($row['asset_tag'] ?? ''); ?>&serial=<?php echo urlencode($row['serial_number'] ?? 'N/A'); ?>&location=<?php echo urlencode($row['location_name'] ?? 'N/A'); ?>&user=<?php echo urlencode($row['user_name'] ?? 'N/A'); ?>" target="_blank" class="text-secondary" data-bs-toggle="tooltip" title="Print Label"><i class="fas fa-print"></i></a>
                                        <a href="?view=add_asset&id=<?php echo $row['id']; ?>" class="text-primary" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-pen-to-square"></i></a>
                                        <a href="?action=delete_asset&id=<?php echo $row['id']; ?>" class="text-danger" data-bs-toggle="tooltip" title="Delete" onclick="return confirm('Delete this asset?');"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($view === 'add_asset'): ?>
            <!-- ... Add Asset Form remains the same ... -->
            <form class="form-card" action="index.php" method="post">
                <input type="hidden" name="action" value="<?php echo $form_action; ?>">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($asset['id'] ?? ''); ?>">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-lg-4">
                        <div class="row g-4">
                            <div class="col-md-6"><label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label><input type="text" class="form-control" id="asset_tag" name="asset_tag" value="<?php echo htmlspecialchars($asset['asset_tag'] ?? ''); ?>" required></div>
                            <div class="col-md-6"><label for="model" class="form-label">Model <span class="text-danger">*</span></label><input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($asset['model'] ?? ''); ?>" required></div>
                            <div class="col-md-6"><label for="serial_number" class="form-label">Serial Number</label><input type="text" class="form-control" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($asset['serial_number'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label for="status" class="form-label">Status <span class="text-danger">*</span></label><select id="status" class="form-select" name="status" required><?php foreach (['In Stock', 'Deployed', 'In Repair', 'Retired'] as $s): ?><option value="<?php echo $s; ?>" <?php echo (($asset['status'] ?? 'In Stock') == $s) ? 'selected' : ''; ?>><?php echo $s; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6"><label for="asset_type_id" class="form-label">Asset Type <span class="text-danger">*</span></label><select id="asset_type_id" name="asset_type_id" required><option value="">Select a type...</option><?php if($types) $types->data_seek(0); while($type = $types->fetch_assoc()): ?><option value="<?php echo $type['id']; ?>" <?php echo (($asset['asset_type_id'] ?? '') == $type['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['name'] ?? ''); ?></option><?php endwhile; ?></select></div>
                            <div class="col-md-6"><label for="assigned_to_user_id" class="form-label">Assigned To</label><select id="assigned_to_user_id" name="assigned_to_user_id"><option value="">Select a user...</option><?php if($users) $users->data_seek(0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>" <?php echo (($asset['assigned_to_user_id'] ?? '') == $user['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['name'] ?? ''); ?></option><?php endwhile; ?></select></div>
                            <div class="col-md-6"><label for="location_id" class="form-label">Location</label><select id="location_id" name="location_id"><option value="">Select a location...</option><?php if($locations) $locations->data_seek(0); while($loc = $locations->fetch_assoc()): ?><option value="<?php echo $loc['id']; ?>" <?php echo (($asset['location_id'] ?? '') == $loc['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['name'] ?? ''); ?></option><?php endwhile; ?></select></div>
                            <div class="col-md-6"><label for="purchase_date" class="form-label">Purchase Date</label><input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($asset['purchase_date'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label for="warranty_expiry_date" class="form-label">Warranty Expiry</label><input type="date" class="form-control" id="warranty_expiry_date" name="warranty_expiry_date" value="<?php echo htmlspecialchars($asset['warranty_expiry_date'] ?? ''); ?>"></div>
                            <div class="col-12"><label for="notes" class="form-label">Notes</label><textarea class="form-control" id="notes" name="notes" rows="4"><?php echo htmlspecialchars($asset['notes'] ?? ''); ?></textarea></div>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <a href="index.php?view=dashboard" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Save Asset</button>
                    </div>
                </div>
            </form>

        <?php elseif ($view === 'import_sheet'): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="row">
                                <div class="col-lg-5">
                                    <h4>Instructions</h4>
                                    <div class="import-instructions">
                                        <p>Follow these steps to import assets from a Google Sheet:</p>
                                        <ol>
                                            <li><strong>Prepare Your Sheet:</strong> Create a Google Sheet with the exact headers below. The order matters!
                                                <br><small class="text-muted"><code>Asset Tag</code>, <code>Model</code>, <code>Status</code>, <code>Asset Type</code>, <code>Serial Number</code>, <code>Purchase Date</code>, <code>Warranty Expiry Date</code>, <code>Notes</code>, <code>Location</code>, <code>Assigned To User</code></small></li>
                                            <li><strong>Required Columns:</strong> <code>Asset Tag</code>, <code>Model</code>, <code>Status</code>, and <code>Asset Type</code> must be filled for every row.</li>
                                            <li><strong>Match Existing Data:</strong> Values for <code>Asset Type</code>, <code>Location</code>, and <code>Assigned To User</code> must exactly match names already in this system.</li>
                                            <li><strong>Publish as CSV:</strong> In Google Sheets, go to <code>File > Share > Publish to web</code>.</li>
                                            <li>In the popup, under the <strong>Link</strong> tab, select your prepared sheet, then choose <strong>"Comma-separated values (.csv)"</strong>.</li>
                                            <li>Click <strong>Publish</strong> and copy the generated URL.</li>
                                            <li>Paste the URL into the form and click "Preview".</li>
                                        </ol>
                                    </div>
                                </div>
                                <div class="col-lg-7">
                                    <h4>1. Provide Google Sheet URL</h4>
                                     <?php if(!empty($import_error)): ?>
                                        <div class="alert alert-danger"><?php echo htmlspecialchars($import_error); ?></div>
                                    <?php endif; ?>
                                    <form action="index.php" method="POST">
                                        <input type="hidden" name="action" value="import_from_sheet">
                                        <input type="hidden" name="step" value="preview">
                                        <div class="mb-3">
                                            <label for="sheet_url" class="form-label">Published Google Sheet CSV URL</label>
                                            <textarea name="sheet_url" id="sheet_url" class="form-control" rows="3" required><?php echo htmlspecialchars($_POST['sheet_url'] ?? ''); ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><i class="fas fa-eye me-2"></i>Preview Data</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($preview_data) && (count($preview_data['valid']) > 0 || count($preview_data['invalid']) > 0)): ?>
            <div class="row mt-4">
                <div class="col-12">
                     <div class="card shadow-sm border-0">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">2. Preview & Confirm Import</h4>
                             <div>
                                <span class="badge bg-success me-2">Valid: <?php echo count($preview_data['valid']); ?></span>
                                <span class="badge bg-danger">Invalid: <?php echo count($preview_data['invalid']); ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($preview_data['valid']) > 0): ?>
                                <p>The following <strong><?php echo count($preview_data['valid']); ?> rows</strong> appear valid and are ready for import. Rows with an Asset Tag that already exists will be skipped.</p>
                                <form action="index.php" method="POST" class="mb-4">
                                     <input type="hidden" name="action" value="import_from_sheet">
                                     <input type="hidden" name="step" value="commit">
                                     <textarea name="import_data" class="d-none"><?php echo htmlspecialchars(json_encode($preview_data['valid'])); ?></textarea>
                                     <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-check-circle me-2"></i>Confirm and Import Valid Rows</button>
                                     <a href="?view=import_sheet" class="btn btn-secondary btn-lg">Cancel</a>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-warning">No valid rows found to import. Please check the errors below and correct your sheet.</div>
                            <?php endif; ?>

                            <?php if (count($preview_data['invalid']) > 0): ?>
                                <h5 class="mt-4">Invalid Rows (will not be imported)</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm preview-table">
                                        <thead><tr><th>Row #</th><th>Asset Tag</th><th>Model</th><th>Reason for Failure</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($preview_data['invalid'] as $row): ?>
                                            <tr class="error-cell">
                                                <td><?php echo $row['row_num']; ?></td>
                                                <td><?php echo htmlspecialchars($row['data']['original']['Asset Tag'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($row['data']['original']['Model'] ?? 'N/A'); ?></td>
                                                <td><?php echo implode('<br>', array_map('htmlspecialchars', $row['errors'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                             <?php if (count($preview_data['valid']) > 0): ?>
                                <h5 class="mt-4">Valid Rows (for reference)</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm preview-table">
                                        <thead><tr><th>Asset Tag</th><th>Model</th><th>Status</th><th>Type</th><th>Location</th><th>User</th><th>Note</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($preview_data['valid'] as $row): 
                                                $is_skipped = in_array("Asset Tag already exists in DB (will be skipped).", $row['errors'] ?? []);
                                            ?>
                                            <tr class="<?php echo $is_skipped ? 'skip-cell' : ''; ?>">
                                                <td><?php echo htmlspecialchars($row['original']['Asset Tag']); ?></td>
                                                <td><?php echo htmlspecialchars($row['original']['Model']); ?></td>
                                                <td><?php echo htmlspecialchars($row['original']['Status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['original']['Asset Type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['original']['Location'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($row['original']['Assigned To User'] ?? ''); ?></td>
                                                <td><?php echo $is_skipped ? 'Will be skipped (tag exists)' : 'Ready to import'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                     </div>
                </div>
            </div>
            <?php endif; ?>

        <?php elseif ($view === 'scan_asset'): ?>
            <!-- ... Scan Asset content remains the same ... -->
            <div class="card shadow-sm border-0">
                <div class="card-body text-center p-4">
                    
                    <div id="scanner-initial-view">
                        <p class="text-muted">ចុចប៊ូតុងខាងក្រោមដើម្បីចាប់ផ្តើម Scan Barcode</p>
                        <button id="start-scan-btn" class="btn btn-primary btn-lg">
                            <i class="fas fa-camera me-2"></i> Start Camera
                        </button>
                    </div>

                    <div id="reader" style="display: none;"></div>
                    
                    <div id="scan-feedback" class="mt-3"></div>

                    <form id="asset-update-form" class="mt-4 text-start w-100 mx-auto" style="max-width: 500px; display: none;" onsubmit="handleQuickUpdate(event)">
                         <div class="alert alert-info" id="scanned-asset-info"></div>
                         <input type="hidden" id="asset-id-update" name="id">

                         <div class="mb-3">
                            <label for="status-update" class="form-label">New Status</label>
                            <select id="status-update" name="status" class="form-select">
                                <option value="Deployed">Deployed (Check Out)</option>
                                <option value="In Stock">In Stock (Check In)</option>
                                <option value="In Repair">In Repair</option>
                                <option value="Retired">Retired</option>
                            </select>
                         </div>
                         <div class="mb-3">
                            <label for="assigned-to-update" class="form-label">Assign To</label>
                            <select id="assigned-to-update" name="assigned_to_user_id">
                               <option value="">(Unassigned)</option>
                               <?php if($users) $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                               <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name'] ?? ''); ?></option>
                               <?php endwhile; ?>
                            </select>
                         </div>
                         <div class="mb-3">
                            <label for="location-update" class="form-label">Set Location</label>
                            <select id="location-update" name="location_id">
                               <option value="">(No Location)</option>
                               <?php if($locations) $locations->data_seek(0); ?>
                               <?php while($loc = $locations->fetch_assoc()): ?>
                               <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name'] ?? ''); ?></option>
                               <?php endwhile; ?>
                            </select>
                         </div>
                         <div class="d-grid gap-2 d-sm-flex justify-content-sm-end">
                            <button type="button" class="btn btn-secondary" id="scan-again-btn">Scan Again</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                         </div>
                    </form>
                </div>
            </div>
            
        <?php elseif ($view === 'manage_types' || $view === 'manage_locations'): 
            $is_type = ($view === 'manage_types');
            $title = $is_type ? 'Asset Type' : 'Location';
            $icon = $is_type ? 'fa-sitemap' : 'fa-map-marker-alt';
            $placeholder = $is_type ? 'e.g., Laptop, Monitor' : 'e.g., Main Office, Warehouse';
            $action_value = $is_type ? 'create_type' : 'create_location';
        ?>
            <!-- ... Manage Types/Locations content remains the same ... -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                         <div class="card-header bg-white py-3"><h5 class="mb-0"><i class="fas <?php echo $icon; ?> me-2"></i>Add New <?php echo $title; ?></h5></div>
                        <div class="card-body p-4">
                            <form action="index.php" method="post">
                                <input type="hidden" name="action" value="<?php echo $action_value; ?>">
                                <div class="mb-3">
                                    <label for="name" class="form-label"><?php echo $title; ?> Name</label>
                                    <input type="text" name="name" class="form-control" placeholder="<?php echo $placeholder; ?>" required>
                                </div>
                                <?php if (!$is_type): ?>
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" name="address" class="form-control">
                                </div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-primary">Save <?php echo $title; ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

    <!-- JS Libs -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.3/js/dataTables.js"></script>
    <script src="https://cdn.datatables.net/2.0.3/js/dataTables.bootstrap5.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/3.0.1/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <!-- Custom JS -->
    <script>
    // ... all existing Javascript remains the same ...
    let tomSelectAssigned, tomSelectLocation;
    let html5Qrcode;

    // --- General Setup ---
    $(document).ready(function() {
        // DataTable
        if ($('#assetsTable').length) {
            let table = $('#assetsTable').DataTable({
                responsive: true,
                pageLength: 10,
                lengthMenu: [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                language: { search: "_INPUT_", searchPlaceholder: "Search assets..." },
                "columnDefs": [ 
                    { "targets": 'no-sort', "orderable": false },
                    { "targets": 0, "className": "dt-body-center" } // Center checkbox column
                ],
                // Redraw callback to re-attach events to new elements after pagination/search
                "drawCallback": function( settings ) {
                    attachCheckboxEvents();
                }
            });

            // Initial event attachment
            attachCheckboxEvents();

            // Handle multi-print button click
            $('#print-selected-btn').on('click', function() {
                // Create a dynamic form that posts to index.php in a new tab
                const form = $('<form>', {
                    action: 'index.php',
                    method: 'post',
                    target: '_blank'
                }).hide();

                // Add a hidden input to specify the print action
                form.append($('<input>', { type: 'hidden', name: 'action', value: 'print_labels' }));

                // Append selected asset data as hidden inputs
                $('.asset-checkbox:checked').each(function(i) {
                    const tag = $(this).data('tag');
                    const serial = $(this).data('serial');
                    const location = $(this).data('location');
                    const user = $(this).data('user');
                    
                    form.append($('<input>', { type: 'hidden', name: `assets[${i}][tag]`, value: tag }));
                    form.append($('<input>', { type: 'hidden', name: `assets[${i}][serial]`, value: serial }));
                    form.append($('<input>', { type: 'hidden', name: `assets[${i}][location]`, value: location }));
                    form.append($('<input>', { type: 'hidden', name: `assets[${i}][user]`, value: user }));
                });

                // Submit the form
                $('body').append(form);
                form.submit();
                form.remove(); // Clean up the form from the DOM
            });
        }
        
        // Tooltip
        new bootstrap.Tooltip(document.body, { selector: "[data-bs-toggle='tooltip']" });
        
        // Sidebar
        const sidebar = $('#sidebar');
        const overlay = $('.overlay');
        $('#sidebar-toggle, .overlay').on('click', function () {
            sidebar.toggleClass('active');
            overlay.toggle(sidebar.hasClass('active'));
        });
        
        // TomSelect for standard forms
        const tomSelectConfig = { create: false, sortField: { field: "text", direction: "asc" } };
        document.querySelectorAll('form:not(#asset-update-form) select:not(#status)').forEach((el) => {
             new TomSelect(el, tomSelectConfig);
        });

        // --- SCANNER PAGE LOGIC ---
        if ($('#reader').length) {
            tomSelectAssigned = new TomSelect("#assigned-to-update", tomSelectConfig);
            tomSelectLocation = new TomSelect("#location-update", tomSelectConfig);

            $('#start-scan-btn').on('click', function() {
                startScanner();
            });
            
            $('#scan-again-btn').on('click', function() {
                resetScannerUI(true);
            });
        }
    });

    function attachCheckboxEvents() {
        // Detach previous events to avoid duplicates
        $('#select-all-checkbox, .asset-checkbox').off('change');

        // Select All checkbox logic
        $('#select-all-checkbox').on('change', function() {
            // Check/uncheck all checkboxes on the CURRENT page
            $('.asset-checkbox').prop('checked', this.checked).trigger('change');
        });

        // Individual checkbox logic
        $('.asset-checkbox').on('change', function() {
            updatePrintButtonState();
        });

        updatePrintButtonState(); // Initial check
    }

    function updatePrintButtonState() {
        const selectedCount = $('.asset-checkbox:checked').length;
        const printBtn = $('#print-selected-btn');
        const countSpan = $('#selected-count');
        
        countSpan.text(selectedCount);

        if (selectedCount > 0) {
            printBtn.prop('disabled', false);
        } else {
            printBtn.prop('disabled', true);
        }

        // Update the "Select All" checkbox state
        const totalCheckboxes = $('.asset-checkbox').length;
        if (totalCheckboxes > 0 && selectedCount === totalCheckboxes) {
            $('#select-all-checkbox').prop('checked', true);
        } else {
            $('#select-all-checkbox').prop('checked', false);
        }
    }
    
    // --- Scanner Functions ---
    function startScanner() {
        if (location.protocol !== 'https:') {
            $('#scan-feedback').html('<div class="alert alert-danger"><strong>Error:</strong> Camera access requires a secure connection (HTTPS). This site is not secure.</div>');
            $('#start-scan-btn').hide();
            return;
        }

        $('#scanner-initial-view').hide();
        $('#reader').show();
        $('#scan-feedback').html('<div class="alert alert-info">Please grant camera access to begin scanning...</div>');

        html5Qrcode = new Html5Qrcode("reader");
        const qrCodeSuccessCallback = (decodedText, decodedResult) => {
            onScanSuccess(decodedText, decodedResult);
        };
        const config = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            supportedScanTypes: [Html5Qrcode.SCAN_TYPE_CAMERA] 
        };

        html5Qrcode.start({ facingMode: "environment" }, config, qrCodeSuccessCallback)
            .catch(err => {
                onScanFailure(err);
            });
    }
    
    function stopScanner() {
        if (html5Qrcode && html5Qrcode.isScanning) {
            return html5Qrcode.stop().catch(err => {
                console.error("Failed to stop scanner cleanly:", err);
            });
        }
        return Promise.resolve();
    }

    function onScanSuccess(decodedText, decodedResult) {
        stopScanner().then(() => {
            const feedback = $('#scan-feedback');
            feedback.html('<div class="alert alert-info"><div class="spinner-border spinner-border-sm" role="status"></div> Fetching asset details...</div>');

            fetch(`index.php?action=get_asset_by_tag&tag=${encodeURIComponent(decodedText)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const asset = data.asset;
                        $('#scanned-asset-info').html(`<strong>Asset Found:</strong> ${asset.asset_tag} (${asset.model})`);
                        $('#asset-id-update').val(asset.id);
                        $('#status-update').val(asset.status);
                        tomSelectAssigned.setValue(asset.assigned_to_user_id || '');
                        tomSelectLocation.setValue(asset.location_id || '');
                        
                        $('#reader').hide();
                        $('#asset-update-form').show();
                        feedback.html('');
                    } else {
                        feedback.html(`<div class="alert alert-warning">${data.message}</div>`);
                        setTimeout(() => resetScannerUI(false), 2500);
                    }
                }).catch(error => {
                    feedback.html('<div class="alert alert-danger">Error connecting to the server. Please try again.</div>');
                    console.error("Fetch error:", error);
                    resetScannerUI(false);
                });
        });
    }

    function onScanFailure(error) {
        const feedback = $('#scan-feedback');
        let errorMessage = String(error);
        console.error("Scanner failed to start:", error);

        if (errorMessage.includes("NotAllowedError") || errorMessage.includes("Permission denied")) {
             feedback.html('<div class="alert alert-danger"><strong>Camera Access Denied.</strong> You must allow camera access in your browser settings for this site to scan.</div>');
        } else if (errorMessage.includes("NotFoundError") || errorMessage.includes("Requested device not found")) {
             feedback.html('<div class="alert alert-danger"><strong>No Camera Found.</strong> A camera could not be detected on this device.</div>');
        } else {
             feedback.html(`<div class="alert alert-danger"><strong>An unexpected error occurred.</strong> Please refresh the page. <br><small>${errorMessage}</small></div>`);
        }
        $('#reader').hide();
        $('#scanner-initial-view').show();
        $('#start-scan-btn').show();
    }
    
    function resetScannerUI(andStartAgain = false) {
        stopScanner().then(() => {
            $('#asset-update-form').hide();
            $('#scan-feedback').html('');
            $('#reader').hide();
            
            if (andStartAgain) {
                startScanner();
            } else {
                $('#scanner-initial-view').show();
            }
        });
    }

    function handleQuickUpdate(event) {
        event.preventDefault();
        const form = $('#asset-update-form');
        const formData = new FormData(form[0]);
        formData.append('action', 'quick_update_asset');
        const feedback = $('#scan-feedback');
        feedback.html('<div class="alert alert-info"><div class="spinner-border spinner-border-sm" role="status"></div> Saving changes...</div>');
        form.find('button').prop('disabled', true);

        fetch('index.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                feedback.html(`<div class="alert alert-success">${data.message}</div>`);
                form.hide();
                setTimeout(() => resetScannerUI(false), 2000);
            } else {
                feedback.html(`<div class="alert alert-danger">${data.message}</div>`);
                form.find('button').prop('disabled', false);
            }
        }).catch(error => {
            feedback.html('<div class="alert alert-danger">A network error occurred. Please try again.</div>');
            form.find('button').prop('disabled', false);
        });
    }
    </script>
</body>
</html>