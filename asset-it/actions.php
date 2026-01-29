<?php
// actions.php

require_once 'db.php'; // Make sure you have the database connection

if (isset($_POST['action'])) {
    
    // Use a switch statement to handle different actions
    switch ($_POST['action']) {

        // --- ACTION: CREATE A NEW ASSET ---
        case 'create':
            // Prepare data from POST request, handling empty optional fields
            $asset_tag = $_POST['asset_tag'];
            $model = $_POST['model'];
            $status = $_POST['status'];
            $serial_number = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
            $warranty_expiry_date = !empty($_POST['warranty_expiry_date']) ? $_POST['warranty_expiry_date'] : NULL;
            $notes = !empty($_POST['notes']) ? $_POST['notes'] : NULL;
            $asset_type_id = !empty($_POST['asset_type_id']) ? (int)$_POST['asset_type_id'] : NULL;
            $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : NULL;
            
            $sql = "INSERT INTO assets (asset_tag, model, status, serial_number, purchase_date, warranty_expiry_date, notes, asset_type_id, location_id, assigned_to_user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssiis", $asset_tag, $model, $status, $serial_number, $purchase_date, $warranty_expiry_date, $notes, $asset_type_id, $location_id, $assigned_to_user_id);

            if ($stmt->execute()) {
                header("Location: index.php?status=created");
            } else {
                die("Error creating asset: " . $stmt->error);
            }
            $stmt->close();
            break;

        // --- ACTION: UPDATE AN EXISTING ASSET ---
        case 'update':
            $id = $_POST['id'];
            $asset_tag = $_POST['asset_tag'];
            $model = $_POST['model'];
            $status = $_POST['status'];
            $serial_number = !empty($_POST['serial_number']) ? $_POST['serial_number'] : NULL;
            $purchase_date = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : NULL;
            $warranty_expiry_date = !empty($_POST['warranty_expiry_date']) ? $_POST['warranty_expiry_date'] : NULL;
            $notes = !empty($_POST['notes']) ? $_POST['notes'] : NULL;
            $asset_type_id = !empty($_POST['asset_type_id']) ? (int)$_POST['asset_type_id'] : NULL;
            $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : NULL;
            $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? (int)$_POST['assigned_to_user_id'] : NULL;
            
            $sql = "UPDATE assets SET asset_tag = ?, model = ?, status = ?, serial_number = ?, purchase_date = ?, warranty_expiry_date = ?, notes = ?, asset_type_id = ?, location_id = ?, assigned_to_user_id = ? 
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssiisi", $asset_tag, $model, $status, $serial_number, $purchase_date, $warranty_expiry_date, $notes, $asset_type_id, $location_id, $assigned_to_user_id, $id);

            if ($stmt->execute()) {
                header("Location: index.php?status=updated");
            } else {
                die("Error updating asset: " . $stmt->error);
            }
            $stmt->close();
            break;

        // --- ACTION: CREATE A NEW ASSET TYPE ---
        case 'create_type':
            if (empty(trim($_POST['name']))) {
                header("Location: add_type.php?error=empty");
                exit();
            }
            
            $name = trim($_POST['name']);
            $stmt = $conn->prepare("INSERT INTO asset_types (name) VALUES (?)");
            $stmt->bind_param("s", $name);

            if ($stmt->execute()) {
                header("Location: add_type.php?success=1");
            } else {
                if ($stmt->errno == 1062) {
                    header("Location: add_type.php?error=duplicate&name=" . urlencode($name));
                } else {
                    header("Location: add_type.php?error=db&name=" . urlencode($name));
                }
            }
            $stmt->close();
            exit();
            break;
            
        // --- START: NEW ACTION FOR CREATING A LOCATION ---
        case 'create_location':
            // Validate that the name is not empty
            if (empty(trim($_POST['name']))) {
                header("Location: add_location.php?error=empty");
                exit();
            }
            
            $name = trim($_POST['name']);
            // Handle the optional address field - store as NULL if empty
            $address = !empty(trim($_POST['address'])) ? trim($_POST['address']) : NULL;

            // Use a prepared statement to prevent SQL injection
            $stmt = $conn->prepare("INSERT INTO locations (name, address) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $address);

            // Execute and check for errors
            if ($stmt->execute()) {
                // Success: Redirect back to the same page with a success message
                header("Location: add_location.php?success=1");
                exit();
            } else {
                // Handle potential errors, like a duplicate entry
                if ($stmt->errno == 1062) {
                    // Redirect back with a 'duplicate' error and the data they tried
                    $redirect_url = "add_location.php?error=duplicate&name=" . urlencode($name) . "&address=" . urlencode($address);
                    header("Location: " . $redirect_url);
                } else {
                    // Redirect back for any other generic database error
                    $redirect_url = "add_location.php?error=db&name=" . urlencode($name) . "&address=" . urlencode($address);
                    header("Location: " . $redirect_url);
                }
                exit();
            }
            $stmt->close();
            break;
        // --- END: NEW ACTION FOR CREATING A LOCATION ---
            
    } // end switch

    $conn->close();

} else {
    // Redirect or show an error if no action is specified
    header("Location: index.php");
    exit();
}
?>