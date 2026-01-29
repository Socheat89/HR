<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'db_connect.php';

    // Log incoming POST data for debugging
    error_log("POST Data Received: " . print_r($_POST, true));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method. Only POST is allowed.");
    }

    if (empty($_POST)) {
        throw new Exception("No data received in POST request.");
    }

    $pdo->beginTransaction();

    // Update office_staff
    if (!empty($_POST['office_staff']) && is_array($_POST['office_staff'])) {
        $stmt_office = $pdo->prepare("
            UPDATE office_staff 
            SET total = ?, female = ?, male = ?
            WHERE id = ?
        ");
        if (!$stmt_office) {
            throw new Exception("Failed to prepare office_staff statement: " . implode(", ", $pdo->errorInfo()));
        }
        foreach ($_POST['office_staff'] as $id => $data) {
            if (!is_numeric($id)) {
                error_log("Skipping invalid office_staff ID: $id");
                continue;
            }
            $success = $stmt_office->execute([
                $data['total'] ?? null,
                $data['female'] ?? null,
                $data['male'] ?? null,
                $id
            ]);
            if (!$success) {
                throw new Exception("Failed to update office_staff ID $id: " . implode(", ", $stmt_office->errorInfo()));
            }
        }
    }

    // Update store_318_staff
    if (!empty($_POST['store_318_staff']) && is_array($_POST['store_318_staff'])) {
        $stmt_store = $pdo->prepare("
            UPDATE store_318_staff 
            SET total = ?, female = ?, male = ?
            WHERE id = ?
        ");
        if (!$stmt_store) {
            throw new Exception("Failed to prepare store_318_staff statement: " . implode(", ", $pdo->errorInfo()));
        }
        foreach ($_POST['store_318_staff'] as $id => $data) {
            if (!is_numeric($id)) {
                error_log("Skipping invalid store_318_staff ID: $id");
                continue;
            }
            $success = $stmt_store->execute([
                $data['total'] ?? null,
                $data['female'] ?? null,
                $data['male'] ?? null,
                $id
            ]);
            if (!$success) {
                throw new Exception("Failed to update store_318_staff ID $id: " . implode(", ", $stmt_store->errorInfo()));
            }
        }
    }

    // Update warehouse_staff
    if (!empty($_POST['warehouse_staff']) && is_array($_POST['warehouse_staff'])) {
        $stmt_warehouse = $pdo->prepare("
            UPDATE warehouse_staff 
            SET total = ?, ch1 = ?, ckd = ?, st1 = ?, psp = ?
            WHERE id = ?
        ");
        if (!$stmt_warehouse) {
            throw new Exception("Failed to prepare warehouse_staff statement: " . implode(", ", $pdo->errorInfo()));
        }
        foreach ($_POST['warehouse_staff'] as $id => $data) {
            if (!is_numeric($id)) {
                error_log("Skipping invalid warehouse_staff ID: $id");
                continue;
            }
            $success = $stmt_warehouse->execute([
                $data['total'] ?? null,
                $data['ch1'] ?? null,
                $data['ckd'] ?? null,
                $data['st1'] ?? null,
                $data['psp'] ?? null,
                $id
            ]);
            if (!$success) {
                throw new Exception("Failed to update warehouse_staff ID $id: " . implode(", ", $stmt_warehouse->errorInfo()));
            }
        }
    }

    // Update new_staff
    if (!empty($_POST['new_staff']) && is_array($_POST['new_staff'])) {
        $stmt_new = $pdo->prepare("
            UPDATE new_staff 
            SET number = ?, name = ?, role = ?, note = ?, office_central = ?, store_318 = ?, warehouse = ?
            WHERE id = ?
        ");
        if (!$stmt_new) {
            throw new Exception("Failed to prepare new_staff statement: " . implode(", ", $pdo->errorInfo()));
        }
        foreach ($_POST['new_staff'] as $id => $data) {
            if (!is_numeric($id)) {
                error_log("Skipping invalid new_staff ID: $id");
                continue;
            }
            $success = $stmt_new->execute([
                $data['number'] ?? null,
                $data['name'] ?? null,
                $data['role'] ?? null,
                $data['note'] ?? null,
                $data['office_central'] ?? null,
                $data['store_318'] ?? null,
                $data['warehouse'] ?? null,
                $id
            ]);
            if (!$success) {
                throw new Exception("Failed to update new_staff ID $id: " . implode(", ", $stmt_new->errorInfo()));
            }
        }
    }

    $pdo->commit();
    header("Location: staff_report.php?success=" . urlencode("ទិន្នន័យបានរក្សាទុកជោគជ័យ!"));
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save Staff Error: " . $e->getMessage());
    header("Location: staff_report.php?error=" . urlencode("កំហុសក្នុងការរក្សាទុក: " . $e->getMessage()));
    exit;
}
?>