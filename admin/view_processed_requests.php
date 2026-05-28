<?php
require_once 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'includes/db.php';
require_once 'includes/telegram.php';
$conn = include 'includes/db.php';

// Handle Delete Request
if (isset($_POST['delete_id'])) {
    try {
        $deleteId = $_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
        $stmt->execute([$deleteId]);
        sendTelegramMessage('-1002496391098', "🗑️ Request ID $deleteId deleted by admin.");
        header("Location: processed_requests.php"); // Refresh page
        exit();
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        sendTelegramMessage('-1002496391098', "❌ Error deleting request: " . $e->getMessage());
    }
}

// Handle Edit Request
if (isset($_POST['edit_id'])) {
    try {
        $editId = $_POST['edit_id'];
        $stmt = $conn->prepare("
            UPDATE requests SET 
                request_type = ?, requester_name = ?, department = ?, branch = ?, 
                request_date = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['request_type'],
            $_POST['requester_name'],
            $_POST['department'],
            $_POST['branch'],
            $_POST['request_date'],
            $_POST['status'],
            $editId
        ]);
        sendTelegramMessage('-1002496391098', "✏️ Request ID $editId updated by admin.");
        header("Location: processed_requests.php"); // Refresh page
        exit();
    } catch (PDOException $e) {
        error_log("Edit error: " . $e->getMessage());
        sendTelegramMessage('-1002496391098', "❌ Error updating request: " . $e->getMessage());
    }
}

try {
    $stmt = $conn->query("
        SELECT * 
        FROM requests 
        WHERE status IN ('approved', 'rejected')
        ORDER BY created_at DESC
    ");
    $processedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $message = "📋 <b>Processed Requests Viewed</b>\n";
    foreach ($processedRequests as $request) {
        $message .= sprintf(
            "ID: %s | Type: %s | Name: %s | Status: %s\n",
            $request['id'], $request['request_type'], $request['requester_name'], $request['status']
        );
    }
    sendTelegramMessage('-1002496391098', $message);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $processedRequests = [];
    sendTelegramMessage('-1002496391098', "❌ Error fetching processed requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processed Requests</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e1e2f 0%, #2a2a4a 100%); font-family: 'Poppins', sans-serif; color: #e0e0e0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        h1 { color: #ffd700; font-weight: 700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); text-align: center; margin-bottom: 20px; }
        .table-container { background: linear-gradient(145deg, #2a2a4a, #1e1e2f); border: 1px solid #ffd700; border-radius: 15px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ffd700; }
        th { background: linear-gradient(145deg, #ffd700, #d4af37); color: #1e1e2f; font-weight: 600; text-shadow: 0 0 5px rgba(255, 215, 0, 0.5); }
        tr:hover { background: rgba(255, 215, 0, 0.1); }
        .status-approved { color: #28a745; font-weight: bold; }
        .status-rejected { color: #dc3545; font-weight: bold; }
        .btn { background: linear-gradient(145deg, #ffd700, #d4af37); color: #1e1e2f; padding: 5px 10px; text-decoration: none; border-radius: 5px; font-weight: 600; transition: all 0.3s ease; }
        .btn:hover { background: linear-gradient(145deg, #d4af37, #ffd700); transform: scale(1.05); }
        .btn-edit { background: linear-gradient(145deg, #17a2b8, #138496); }
        .btn-edit:hover { background: linear-gradient(145deg, #138496, #17a2b8); }
        .btn-delete { background: linear-gradient(145deg, #dc3545, #c82333); }
        .btn-delete:hover { background: linear-gradient(145deg, #c82333, #dc3545); }
        .no-data { color: #ffd700; text-align: center; padding: 20px; font-weight: 600; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 1000; }
        .modal-content { background: #2a2a4a; margin: 5% auto; padding: 20px; width: 90%; max-width: 500px; border-radius: 10px; border: 1px solid #ffd700; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
        .modal-header { font-size: 1.5rem; color: #ffd700; margin-bottom: 20px; }
        .modal-body label { display: block; color: #e0e0e0; margin-bottom: 5px; }
        .modal-body input, .modal-body select { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ffd700; border-radius: 5px; background: #1e1e2f; color: #e0e0e0; }
        .modal-footer { text-align: right; }
        .modal-footer .btn { margin-left: 10px; }
        .close { float: right; color: #ffd700; font-size: 1.5rem; cursor: pointer; }
    </style>
</head>
<body>
    <nav class="flex m-6 mb-8 items-center gap-3 text-slate-300 font-bold text-[10px] uppercase tracking-widest animate-fade-in" aria-label="Breadcrumb">
        <a href="dashboard.php" class="flex items-center gap-2 hover:text-amber-400 transition-colors bg-white/5 px-4 py-2 rounded-xl shadow-sm border border-white/10 no-underline text-slate-300 backdrop-blur-md">
            <i class="fas fa-home text-amber-500"></i>
            <span>ផ្ទាំងគ្រប់គ្រង</span>
        </a>
        <i class="fas fa-chevron-right text-slate-500 text-[8px]"></i>
        <span class="bg-amber-500/10 text-amber-500 px-4 py-2 rounded-xl border border-amber-500/10">
            សំណើបានដំណើរការ
        </span>
    </nav>
    <div class="container">
        <h1>Processed Requests</h1>
        <?php if (empty($processedRequests)): ?>
            <p class="no-data">No processed requests found.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Request Type</th>
                            <th>Requester Name</th>
                            <th>Department</th>
                            <th>Branch</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($processedRequests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['branch']); ?></td>
                                <td><?php echo htmlspecialchars($request['request_date'] ?? 'N/A'); ?></td>
                                <td class="<?php echo $request['status'] === 'approved' ? 'status-approved' : 'status-rejected'; ?>">
                                    <?php echo htmlspecialchars($request['status']); ?>
                                </td>
                                <td>
    <button class="btn btn-edit" onclick='openEditModal(<?php echo json_encode($request); ?>)'>Edit</button>
    <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this request?');">
        <input type="hidden" name="delete_id" value="<?php echo $request['id']; ?>">
        <button type="submit" class="btn btn-delete">Delete</button>
    </form>
</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                Edit Request
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <label>Request Type</label>
                    <input type="text" name="request_type" id="edit_request_type" required>
                    <label>Requester Name</label>
                    <input type="text" name="requester_name" id="edit_requester_name" required>
                    <label>Department</label>
                    <input type="text" name="department" id="edit_department">
                    <label>Branch</label>
                    <input type="text" name="branch" id="edit_branch" required>
                    <label>Request Date</label>
                    <input type="date" name="request_date" id="edit_request_date">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-edit">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(request) {
            document.getElementById('editModal').style.display = 'block';
            document.getElementById('edit_id').value = request.id;
            document.getElementById('edit_request_type').value = request.request_type;
            document.getElementById('edit_requester_name').value = request.requester_name;
            document.getElementById('edit_department').value = request.department || '';
            document.getElementById('edit_branch').value = request.branch;
            document.getElementById('edit_request_date').value = request.request_date || '';
            document.getElementById('edit_status').value = request.status;
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>