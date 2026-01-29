<?php
// ចាប់ផ្ដើម Session ដើម្បីប្រើសម្រាប់ផ្ញើសារ (message feedback)
session_start();

// 1. បញ្ចូលไฟล์ Configuration ដើម្បីភ្ជាប់ទៅកាន់ Database
require_once 'config.php'; // ត្រូវប្រាកដថា path នេះត្រឹមត្រូវ

// 2. ពិនិត្យមើលសកម្មភាព (Action) ដែល User ចង់ធ្វើ (លុប ឬ លុបទាំងអស់)
if (isset($_GET['action'])) {

    // --- ក. ករណីចង់លុបទិន្នន័យតែមួយแถว (Delete a single entry) ---
    if ($_GET['action'] === 'delete' && isset($_GET['psid'])) {
        $psid_to_delete = $_GET['psid'];

        // ប្រើ Prepared Statement ដើម្បីការពារ SQL Injection
        $stmt = $conn->prepare("DELETE FROM conversation_tracker WHERE user_psid = ?");
        $stmt->bind_param("s", $psid_to_delete);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "ទិន្នន័យសម្រាប់ PSID: " . htmlspecialchars($psid_to_delete) . " ត្រូវបានលុបដោយជោគជ័យ។";
        } else {
            $_SESSION['message'] = "មានបញ្ហាក្នុងការលុបទិន្នន័យ។";
        }
        $stmt->close();

        // បញ្ជូនត្រឡប់ទៅទំព័រដើមវិញ ដើម្បីឲ្យបញ្ជីទិន្នន័យ update
        header("Location: manage_tracker.php");
        exit;
    }

    // --- ខ. ករណីចង់លុបទិន្នន័យទាំងអស់ (Delete all entries) ---
    if ($_GET['action'] === 'delete_all') {
        // TRUNCATE TABLE លឿនជាង DELETE FROM ហើយវា reset auto-increment (បើមាន)
        if ($conn->query("TRUNCATE TABLE conversation_tracker")) {
             $_SESSION['message'] = "ទិន្នន័យទាំងអស់នៅក្នុងតារាង conversation_tracker ត្រូវបានលុបចោល។";
        } else {
            $_SESSION['message'] = "មានបញ្ហាក្នុងការលុបទិន្នន័យទាំងអស់។";
        }
        
        // បញ្ជូនត្រឡប់ទៅទំព័រដើមវិញ
        header("Location: manage_tracker.php");
        exit;
    }
}

// 3. ទាញយកទិន្នន័យទាំងអស់ពីតារាងដើម្បីបង្ហាញ
// តម្រៀបតាមអន្តរកម្មចុងក្រោយគេឲ្យនៅខាងលើ
$result = $conn->query("SELECT user_psid, last_interaction FROM conversation_tracker ORDER BY last_interaction DESC");

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រង Conversation Tracker</title>
    <style>
        body { 
            font-family: 'Kantumruy Pro', sans-serif; 
            margin: 20px; 
            background-color: #f4f4f9;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0056b3;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #f2f2f2; 
        }
        tr:hover {
            background-color: #e9ecef;
        }
        .action-btn {
            display: inline-block;
            padding: 6px 12px;
            color: #fff;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
        }
        .delete-btn {
            background-color: #dc3545;
        }
        .delete-all-btn {
            background-color: #c82333;
            margin-bottom: 20px;
        }
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>ទំព័រគ្រប់គ្រង Conversation Tracker</h1>

    <?php
    // បង្ហាញសារ Feedback (Success/Error Message) បន្ទាប់ពីធ្វើសកម្មភាពរួច
    if (isset($_SESSION['message'])): 
    ?>
        <div class="message">
            <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']); // លុប message ចេញពី session ដើម្បីកុំឲ្យវាបង្ហាញទៀត
            ?>
        </div>
    <?php endif; ?>

    <!-- ប៊ូតុងសម្រាប់លុបទិន្នន័យទាំងអស់ -->
    <a href="manage_tracker.php?action=delete_all" 
       class="action-btn delete-all-btn" 
       onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យទាំងអស់មែនទេ? សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ!');">
       លុបទិន្នន័យទាំងអស់
    </a>

    <table>
        <thead>
            <tr>
                <th>លេខសម្គាល់អ្នកប្រើ (PSID)</th>
                <th>អន្តរកម្មចុងក្រោយ</th>
                <th>សកម្មភាព</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_psid']); ?></td>
                        <td><?php echo htmlspecialchars($row['last_interaction']); ?></td>
                        <td>
                            <a href="manage_tracker.php?action=delete&psid=<?php echo urlencode($row['user_psid']); ?>" 
                               class="action-btn delete-btn" 
                               onclick="return confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ?');">
                               លុប
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" style="text-align: center;">គ្មានទិន្នន័យក្នុងតារាងទេ។</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
// បិទការតភ្ជាប់ Database
$conn->close();
?>

</body>
</html>