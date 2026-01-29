<?php
require_once 'db.php';

// --- PHP logic remains the same ---
$asset = [
    'id' => '', 'asset_tag' => '', 'serial_number' => '', 'model' => '', 'status' => 'In Stock',
    'purchase_date' => '', 'warranty_expiry_date' => '', 'notes' => '',
    'asset_type_id' => '', 'location_id' => '', 'assigned_to_user_id' => ''
];
$pageTitle = 'Add New Asset';
$action = 'create';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $asset = $result->fetch_assoc();
        $pageTitle = 'Edit Asset details';
        $action = 'update';
    }
    $stmt->close();
}

$types = $conn->query("SELECT id, name FROM asset_types ORDER BY name");
$locations = $conn->query("SELECT id, name FROM locations ORDER BY name");
$users = $conn->query("SELECT id, name FROM users ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Tom Select for searchable dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 960px;
        }
        .card-header {
            background-color: #e9ecef;
            font-weight: 500;
        }
        .card {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 500;
        }
        .breadcrumb-item a {
            text-decoration: none;
        }
        /* Style for the conditionally shown fields */
        #assignment-fields {
            display: <?php echo ($asset['status'] == 'Deployed') ? 'flex' : 'none'; ?>;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Asset Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo ($action == 'update') ? 'Edit Asset' : 'Add Asset'; ?></li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $pageTitle; ?></h1>
            <?php if ($action == 'update'): ?>
                <span class="badge bg-primary fs-6">ID: <?php echo htmlspecialchars($asset['id']); ?></span>
            <?php endif; ?>
        </div>

        <form action="actions.php" method="post">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">

            <!-- Card 1: Asset Identification -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tag me-2"></i>Asset Identification
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_tag" class="form-label">Asset Tag <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="asset_tag" name="asset_tag" value="<?php echo htmlspecialchars($asset['asset_tag']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($asset['model']); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="serial_number" class="form-label">Serial Number</label>
                            <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="asset_type_id" class="form-label">Asset Type <span class="text-danger">*</span></label>
                            <select id="asset_type_id" name="asset_type_id" required>
                                <option value="">Select a type...</option>
                                <?php while($type = $types->fetch_assoc()): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo ($asset['asset_type_id'] == $type['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($type['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Status & Assignment -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-check-circle me-2"></i>Status & Assignment
                </div>
                <div class="card-body">
                     <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach (['In Stock', 'Deployed', 'In Repair', 'Retired'] as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($asset['status'] == $status) ? 'selected' : ''; ?>><?php echo $status; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                     </div>
                     <!-- These fields will be shown/hidden by JavaScript -->
                     <div class="row" id="assignment-fields">
                        <div class="col-md-6 mb-3">
                            <label for="assigned_to_user_id" class="form-label">Assigned To</label>
                            <select id="assigned_to_user_id" name="assigned_to_user_id">
                                <option value="">Select a user...</option>
                                <?php while($user = $users->fetch_assoc()): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo ($asset['assigned_to_user_id'] == $user['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                         <div class="col-md-6 mb-3">
                            <label for="location_id" class="form-label">Location</label>
                            <select id="location_id" name="location_id">
                                <option value="">Select a location...</option>
                                <?php while($location = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo $location['id']; ?>" <?php echo ($asset['location_id'] == $location['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($location['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                     </div>
                </div>
            </div>

            <!-- Card 3: Purchase & Warranty -->
            <div class="card">
                 <div class="card-header">
                    <i class="fas fa-calendar-alt me-2"></i>Purchase & Warranty
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="purchase_date" class="form-label">Purchase Date</label>
                            <input type="date" class="form-control" id="purchase_date" name="purchase_date" value="<?php echo htmlspecialchars($asset['purchase_date']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="warranty_expiry_date" class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" id="warranty_expiry_date" name="warranty_expiry_date" value="<?php echo htmlspecialchars($asset['warranty_expiry_date']); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Notes -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-sticky-note me-2"></i>Notes
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Add any relevant notes here..."><?php echo htmlspecialchars($asset['notes']); ?></textarea>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 text-end">
                <a href="index.php" class="btn btn-secondary me-2"><i class="fas fa-times me-2"></i>Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo ($action == 'update') ? 'Update Asset' : 'Save Asset'; ?>
                </button>
            </div>
        </form>
    </div>

    <?php $conn->close(); ?>

    <!-- Tom Select JS -->
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- Initialize Tom Select for searchable dropdowns ---
        const tomSelectSettings = {
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            }
        };
        new TomSelect("#asset_type_id", tomSelectSettings);
        new TomSelect("#location_id", tomSelectSettings);
        new TomSelect("#assigned_to_user_id", tomSelectSettings);


        // --- Conditional logic for assignment fields ---
        const statusSelect = document.getElementById('status');
        const assignmentFields = document.getElementById('assignment-fields');
        const assignedToSelect = document.getElementById('assigned_to_user_id').tomselect;
        const locationSelect = document.getElementById('location_id').tomselect;

        function toggleAssignmentFields() {
            if (statusSelect.value === 'Deployed') {
                assignmentFields.style.display = 'flex'; // Use flex to match Bootstrap's .row
            } else {
                assignmentFields.style.display = 'none';
                // Also clear the values when hiding to avoid accidental submissions
                assignedToSelect.clear();
                locationSelect.clear();
            }
        }

        // Add event listener to the status dropdown
        statusSelect.addEventListener('change', toggleAssignmentFields);

        // Run on page load to set the initial state correctly for editing
        toggleAssignmentFields();
    });
    </script>
</body>
</html>