<?php
// --- PHP block to handle messages from the URL ---
$errorMsg = '';
$successMsg = '';
$submittedName = '';

// Check for error messages
if (isset($_GET['error'])) {
    // If there was an error, keep the user's submitted value to re-populate the form
    if (isset($_GET['name'])) {
        $submittedName = htmlspecialchars($_GET['name']);
    }

    switch ($_GET['error']) {
        case 'duplicate':
            $errorMsg = '<strong>Error:</strong> This asset type name already exists. Please choose a different name.';
            break;
        case 'empty':
            $errorMsg = '<strong>Error:</strong> The asset type name cannot be empty.';
            break;
        case 'db':
            $errorMsg = '<strong>Error:</strong> A database error occurred. Please try again later.';
            break;
    }
}

// Check for success message
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $successMsg = '<strong>Success!</strong> The new asset type has been added. You can add another below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Asset Type</title>

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
                <li class="breadcrumb-item active" aria-current="page">Add Asset Type</li>
            </ol>
        </nav>

        <h1 class="mb-4">Add New Asset Type</h1>

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
            <input type="hidden" name="action" value="create_type">

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-sitemap me-2"></i>Type Details
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Asset Type Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required 
                               placeholder="e.g., Laptop, Monitor, Keyboard" 
                               value="<?php echo $submittedName; ?>">
                        <div class="form-text">Provide a unique name for the new asset category.</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-end">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Asset Type
                </button>
            </div>
        </form>
    </div>
</body>
</html>