<?php
// Database connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed. Please contact the administrator.");
}

// Initialize variables
$request = null;
$items = [];
$message = null;
$advance_types = [];

// Fetch unique advance types
try {
    $stmt = $conn->prepare("SELECT DISTINCT advance_type FROM item_requests WHERE advance_type IS NOT NULL");
    $stmt->execute();
    $advance_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching advance types: " . $e->getMessage());
}

// Get search parameters
$ref_no = isset($_GET['ref_no']) ? trim($_GET['ref_no']) : '';
$pr_no = isset($_GET['pr_no']) ? trim($_GET['pr_no']) : '';
$advance_type = isset($_GET['advance_type']) ? trim($_GET['advance_type']) : '';

if (!empty($ref_no) || !empty($pr_no) || !empty($advance_type)) {
    try {
        $query = "SELECT * FROM item_requests WHERE 1=1";
        $params = [];
        if (!empty($ref_no)) {
            $query .= " AND number = ?";
            $params[] = $ref_no;
        }
        if (!empty($pr_no)) {
            $query .= " AND pr_no = ?";
            $params[] = $pr_no;
        }
        if (!empty($advance_type)) {
            $query .= " AND advance_type = ?";
            $params[] = $advance_type;
        }

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            $request_id = $request['id'];
            $stmt = $conn->prepare("SELECT item_name, quantity, price FROM request_items WHERE request_id = ?");
            $stmt->execute([$request_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $message = "Showing request for " . (!empty($ref_no) ? "Ref No: " . htmlspecialchars($ref_no) : "") .
                       (!empty($pr_no) ? " PR No: " . htmlspecialchars($pr_no) : "") .
                       (!empty($advance_type) ? " Advance Type: " . htmlspecialchars($advance_type) : "") . ".";
        } else {
            $stmt = $conn->prepare("SELECT * FROM item_requests ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($request) {
                $request_id = $request['id'];
                $stmt = $conn->prepare("SELECT item_name, quantity, price FROM request_items WHERE request_id = ?");
                $stmt->execute([$request_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $message = "No request found for the provided criteria. Showing the latest request instead.";
            } else {
                $message = "No requests found in the database. Please verify the input or contact support.";
            }
        }
    } catch (PDOException $e) {
        $message = "Error fetching data. Please contact the administrator.";
        error_log("Error fetching data: " . $e->getMessage());
    }
} else {
    try {
        $stmt = $conn->prepare("SELECT * FROM item_requests ORDER BY id DESC LIMIT 1");
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($request) {
            $request_id = $request['id'];
            $stmt = $conn->prepare("SELECT item_name, quantity, price FROM request_items WHERE request_id = ?");
            $stmt->execute([$request_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $message = "No search criteria provided. Showing the latest request.";
        } else {
            $message = "No requests found in the database. Please enter search criteria or contact support.";
        }
    } catch (PDOException $e) {
        $message = "Error fetching latest request. Please contact the administrator.";
        error_log("Error fetching latest request: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Request Form - VAN VAN CAMBODIA</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" />
    <style>
        body {
            font-family: 'Battambang', 'Khmer', sans-serif;
            margin: 0;
            padding: 15px;
            line-height: 1.3;
            font-size: 10px;
            color: #333;
            background-color: #f9f9f9;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .bordered, .bordered th, .bordered td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 10px;
        }

        .signature-block {
            border: 1px solid #333;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 5px;
        }

        .signature-block td {
            padding: 3px 5px;
            border: none;
            font-size: 8px;
        }

        .header {
            text-align: center;
            position: relative;
            margin-bottom: 10px;
        }

        .header img {
            width: 60px;
            position: absolute;
            left: 10px;
            top: 0;
        }

        .header h1 {
            margin: 0;
            font-size: 16px;
            line-height: 1.2;
            
        }

        .form-title {
            text-align: center;
            font-size: 14px;
            margin: 10px 0;
            font-weight: bold;
            color: #333;
        }

        .form-title2 {
            text-align: right;
            padding: 10px 20px;
            font-size: 10px;
            margin-bottom: 10px;
        }

        .checkmark {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1px solid #333;
            text-align: center;
            font-size: 8px;
            margin: 0 2px;
        }

        .checked {
            background-color: #333;
            color: #fff;
        }

        .label {
            width: 25%;
            font-weight: bold;
            background-color: #f1f1f1;
        }

        .right {
            text-align: right;
        }

        #printButton {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 15px;
            padding: 8px 16px;
            font-size: 12px;
            background-color: #005555;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
        }

        #printButton:hover {
            background-color: #003d3d;
        }

        #printButton:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .header-session {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: 97.5%;
            margin-bottom: 15px;
            gap: 15px;
            border: 1px solid #333;
            padding: 10px;
            background-color: #fff;
            border-radius: 4px;
        }

        .header-session table {
            width: 48%;
        }

        .error {
            color: #d32f2f;
            font-size: 10px;
            text-align: center;
            margin-bottom: 15px;
            background-color: #ffebee;
            padding: 8px;
            border-radius: 4px;
        }

        .search-form {
            margin-bottom: 25px;
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .search-form form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .search-form label {
            font-weight: bold;
            font-size: 10px;
            color: #333;
        }

        .search-form input[type="text"], .search-form select {
            padding: 6px;
            font-size: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            flex: 1;
            min-width: 150px;
            transition: border-color 0.3s;
        }

        .search-form input[type="text"]:focus, .search-form select:focus {
            border-color: #005555;
            outline: none;
        }

        .search-form button {
            padding: 8px 16px;
            font-size: 10px;
            background-color: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .search-form button:hover {
            background-color: #218838;
        }

        .search-form p {
            font-size: 8px;
            color: #666;
            margin-top: 8px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 5mm;
            }

            body {
                padding: 0;
                font-size: 12px;
                line-height: 1.2;
                background-color: #fff;
            }

            #printButton, .error, .search-form {
                display: none;
            }

            .form-title {
                font-size: 14px;
            }

            .form-title2 {
                text-align: right;
                padding: 5px 10px;
                font-size: 12px;
            }

            .bordered, .bordered th, .bordered td {
                padding: 4px 8px;
                font-size: 12px;
                border: 0.5px solid #333;
            }

            .signature-block td {
                padding: 2px 4px;
                font-size: 10px;
            }

            .header-session {
                border: 1px solid #333;
                padding: 8px;
                border-radius: 0;
                background-color: transparent;
            }

            .header img {
                width: 80px;
                height: 80px;
            }
        }
    </style>
</head>
<body>
    <button id="printButton" type="button"><i class="fa fa-print"></i> בבבב¸ב/Print</button>
    <script>
        document.getElementById('printButton').addEventListener('click', function () {
            try {
                window.print();
            } catch (e) {
                console.error('Print failed:', e);
                alert('בב·בב¢ב¶בבב¾בבב»בבב¶בבבבב¸בבב¶בב בב¼בבב·בב·בבבבב¶בבבבבבבבבבבב·בב¸בב»בבבבבבבב¢בבבב / Unable to open print dialog. Please check your browser settings.');
            }
        });
    </script>

    <div class="search-form">
        <form method="GET" action="">
            <label for="ref_no">בבבבבבבב/Ref No:</label>
            <input type="text" id="ref_no" name="ref_no" value="<?php echo htmlspecialchars($ref_no); ?>" placeholder="Enter Reference No" aria-label="Reference Number">
            <label for="pr_no">PR No:</label>
            <input type="text" id="pr_no" name="pr_no" value="<?php echo htmlspecialchars($pr_no); ?>" placeholder="Enter PR No" aria-label="PR Number">
            <label for="advance_type">בבבבבבבב»בבבבבבב¶ב/Type of Advance:</label>
            <select id="advance_type" name="advance_type" aria-label="Advance Type">
                <option value="">All Advance Types</option>
                <?php foreach ($advance_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($advance_type === $type) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><i class="fa fa-search"></i> בבבבבבב/Search</button>
        </form>
        <p>Note: Select 'All Advance Types' to view all available advance types or filter by a specific type.</p>
    </div>

    <?php if (isset($message)): ?>
        <div class="error"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($request): ?>
        <div class="header">
            <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Van Van Cambodia Logo" />
            <h1>בבבב»בב בב»ב בבבב בבבב בבבבב¼בב¶<br>VAN VAN CAMBODIA</h1>
            <div class="form-title">בבבבבבבבב¾בב»ב<br>Request Form</div>
        </div>
        <div class="form-title2">
            <span style="font-weight: bold;">בבבבבבבב/Ref No: </span><?php echo htmlspecialchars($request['number'] ?? ''); ?><br />
            <span style="font-weight: bold;">בבבבבבבב¾בב»ב/Request Date: </span><?php echo htmlspecialchars($request['request_date'] ?? ''); ?><br />
            <span style="font-weight: bold;">בבבבבבבבבבבב¾בב»ב/PR No: </span><?php echo htmlspecialchars($request['pr_no'] ?? ''); ?><br />
        </div>

        <div class="header-session">
            <table>
                <tr>
                    <td class="label">בבבבב/Name: <?php echo htmlspecialchars($request['request_person'] ?? ''); ?></td>
                   
                </tr>
                <tr>
                    <td class="label">בב»בבבבבב/Position: <?php echo htmlspecialchars($request['position'] ?? ''); ?></td>
                 
                </tr>
                <tr>
                    <td class="label">בבבבב/Department: <?php echo htmlspecialchars($request['department'] ?? ''); ?></td>
                    
                </tr>
                <tr>
                    <td class="label">בב¼בבבבבב/Project: <?php echo htmlspecialchars($request['project'] ?? ''); ?></td>
                  
                </tr>
                <tr>
                    <td class="label">בבבבבבבבב¶ב/Required Date: <?php echo htmlspecialchars($request['none_date'] ?? ''); ?></td>
               
                </tr>
            </table>
            <table>
                <tr>
                    <td class="label">בבבבבבבב»בבבבבבב¶ב/Type of Advance:<br><br>ג <?php echo htmlspecialchars($request['advance_type'] ?? ''); ?></td>
                </tr>
            </table>
        </div>
        <label>בבבבבב»בבבבבבבב¶בבב¼בב¶בבבב»בבבבבבב¶ב/Advance Clearance Deadline: <?php echo htmlspecialchars($request['deadline'] ?? ''); ?></label><br />

        <table class="bordered" style="margin-top: 5px;">
            <thead>
                <tr>
                    <th>ב.ב<br />No.</th>
                    <th>בבבבבבב¸בב·בבבבב¶ב¢בבב¸בב¶בבבבב¶בבבבבב¶בב<br />Purpose of Advance</th>
                    <th>בבב·בב¶ב<br />Qty</th>
                    <th>בבבבבב¯בבב¶<br />Unit Price</th>
                    <th>בבבב½בבב¹בבבבב¶בב<br />Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $total_amount = 0; ?>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo '$' . number_format($item['price'], 2); ?></td>
                        <td><?php echo '$' . number_format($item['quantity'] * $item['price'], 2); ?></td>
                        <?php $total_amount += $item['quantity'] * $item['price']; ?>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" class="right"><strong>בבב»בבבבב¶בבבבבב¶בב/Total Amount Advance</strong></td>
                    <td><strong>$<?php echo number_format($total_amount, 2); ?></strong></td>
                </tr>
            </tbody>
        </table>
        <label>בב¶ב¢בבבב/In Words: <?php echo htmlspecialchars($request['in_words'] ?? ''); ?></label><br />

        <table class="signature-block">
            <tr>
                <td class="label"></td>
                <td>בבבבב/Name:</td>
                <td>ב בבבבבבב¶/Signature:</td>
                <td>בב¶בבבב·בבבבב/Date:</td>
            </tr>
            <tr>
                <td class="label">ב¢בבבבבבב¾בב»ב/Requesting Person:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
                <td class="label">בבבבב¶ב/Manager:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
                <td class="label">בבבבב¶בבבבבבב ב·בבבבבבבבב»/Finance Manager:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
                <td class="label">בבבבבבב/Accountant:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
                <td class="label">ב¢בבבבב¶בב/Chief Executive Officer:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
            <tr>
                <td class="label">ב¢בבבבבב½ב/Received:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">_______________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
            <tr>
                <td class="label">ב¢בבבבבב½בבבבב¶בב/Cashier:</td>
                <td style="padding-top: 1.5rem;">____________________</td>
                <td style="padding-top: 1.5rem;">_______________________</td>
                <td style="padding-top: 1.5rem;">____________________</td>
            </tr>
        </table>

        <p style="margin-top: 15px;"><strong>בבבב¶בב Note:</strong> בבבב¶בבבבבבב בב¼בבבבב¶בבב¯בבב¶בבב¶בבבבבבבב For biz purpose, attach all relevant documents.<br />
            <em>בבבב¶בבבב»בבבבבבב¶בבבבב¶בבבבבב½ב בבבבב²בבבבבבבבבבב¶בבבב»בבב&ב ב·בבבבבבבבב»ב For Personal Advance, a copy to HR&Finance Dept for record</em></p>
        <?php endif; ?>
    </body>
</html>
