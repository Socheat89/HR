<?php
// --- PHP block to handle messages from the URL ---
$errorMsg = '';
$successMsg = '';
$submittedName = '';
$submittedAddress = '';

// Check for error messages from the URL
if (isset($_GET['error'])) {
    // Re-populate form fields on error to prevent data loss
    if (isset($_GET['name'])) {
        $submittedName = htmlspecialchars($_GET['name']);
    }
    if (isset($_GET['address'])) {
        $submittedAddress = htmlspecialchars($_GET['address']);
    }

    switch ($_GET['error']) {
        case 'duplicate':
            $errorMsg = '<strong>Error:</strong> This location name already exists. Please choose a different name.';
            break;
        case 'empty':
            $errorMsg = '<strong>Error:</strong> The location name cannot be empty.';
            break;
        case 'db':
            $errorMsg = '<strong>Error:</strong> A database error occurred. Please try again later.';
            break;
    }
}

// Check for a success message from the URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMsg = '<strong>Success!</strong> The new location has been added. You can add another below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Location</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 600px;
        }
        .card-header {
            background-color: #e9ecef;
            font-weight: 500;
        }
        .breadcrumb-item a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Asset Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Location</li>
            </ol>
        </nav>

        <h1 class="mb-4">Add New Location</h1>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <form action="actions.php" method="post">
            <input type="hidden" name="action" value="create_location">

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-map-marker-alt me-2"></i>Location Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g., Main Office, Warehouse, London Branch" 
                               value="<?php echo $submittedName; ?>">
                        <div class="form-text">Provide a unique name for the new location.</div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control" id="address" name="address" 
                               placeholder="e.g., 123 Main Street, Anytown, USA" 
                               value="<?php echo $submittedAddress; ?>">
                        <div class="form-text">Optional: The physical address of the location.</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Location
                </button>
            </div>
        </form>
    </div>
</body>
</html>