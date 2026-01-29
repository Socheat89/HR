<?php
// Database connection
require_once 'includes/db.php';

try {
    $conn = include 'includes/db.php';

    // Check if request ID is provided via GET
    if (!isset($_GET['id'])) {
        die("No request ID provided. Please specify an ID (e.g., ?id=1)");
    }

    $requestId = $_GET['id'];

    // Fetch the specific request from the database
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        die("Request not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>សំណើសុំសម្រាក - Van Cambodia</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');

        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
            color: #000;
            font-size: 14px;
            overflow: hidden; /* Prevent scrolling */
        }
        .container {
            position: relative;
            width: 800px; /* Match the PDF image width (adjust if needed) */
            height: 1123px; /* Match the PDF image height (A4 size, ~1123px for 8.5x11 inches at 72 DPI) */
            margin: 0 auto;
            overflow: hidden;
        }
        .form-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('path/to/your/pdf_image.png'); /* Replace with the actual path to your PDF image */
            background-size: contain;
            background-repeat: no-repeat;
            z-index: 1;
        }
        .field {
            position: absolute;
            background: transparent;
            border: none;
            font-size: 14px;
            color: #000;
            z-index: 2;
            padding: 2px;
            box-sizing: border-box;
        }
        /* Position fields based on PDF coordinates (adjust these values manually) */
        #request-type { top: 100px; left: 300px; width: 300px; height: 150px; }
        #requester-name { top: 250px; left: 300px; width: 300px; height: 20px; }
        #department-position { top: 280px; left: 300px; width: 300px; height: 20px; }
        #number-of-days { top: 310px; left: 300px; width: 300px; height: 20px; }
        #request-date { top: 340px; left: 300px; width: 300px; height: 20px; }
        #time-in-out { top: 370px; left: 300px; width: 300px; height: 40px; display: flex; gap: 10px; }
        #reason { top: 420px; left: 300px; width: 300px; height: 20px; }
        #assigned-to { top: 450px; left: 300px; width: 300px; height: 20px; }
        #signature { top: 500px; left: 300px; width: 300px; height: 60px; text-align: center; }

        .print-button {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            z-index: 3;
        }
        .print-button:hover {
            background-color: #0056b3;
        }
        .no-print {
            display: block;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                width: 100%;
                height: auto;
                border: none;
            }
            .form-background {
                position: absolute;
                z-index: 1;
            }
            .field {
                border: none;
                background: transparent;
                color: #000;
            }
            .print-button {
                display: none;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-background"></div>

        <!-- Dynamic Fields (Positioned over the image) -->
        <div class="field" id="request-type">
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)' ? 'checked' : ''; ?>> សម្រាកប្រចាំឆ្នាំ (Annual Leave)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'សម្រាកដោយជំងឺ (Sick Leave)' ? 'checked' : ''; ?>> សម្រាកដោយជំងឺ (Sick Leave)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'ភ្លេចស្កេនមេដៃ (Forgot FP)' ? 'checked' : ''; ?>> ភ្លេចស្កេនមេដៃ (Forgot FP)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'សម្រាកលំហែមាតុភាព (Maternity Leave)' ? 'checked' : ''; ?>> សម្រាកលំហែមាតុភាព (Maternity Leave)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'ថែមម៉ោង (OT)' ? 'checked' : ''; ?>> ថែមម៉ោង (OT)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'ចេញមុនម៉ោង (Early)' ? 'checked' : ''; ?>> ចេញមុនម៉ោង (Early)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'ប្តូរថ្ងៃសម្រាក (Changing day off)' ? 'checked' : ''; ?>> ប្តូរថ្ងៃសម្រាក (Changing day off)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'សម្រាកពិសេស (Special Leave)' ? 'checked' : ''; ?>> សម្រាកពិសេស (Special Leave)
            </div>
            <div class="checkbox-group">
                <input type="checkbox" <?php echo $request['request_type'] === 'មកយឺត (Late)' ? 'checked' : ''; ?>> មកយឺត (Late)
            </div>
        </div>

        <div class="field" id="requester-name"><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></div>
        <div class="field" id="department-position"><?php echo htmlspecialchars($request['department'] ?? 'N/A') . ' / ' . htmlspecialchars($request['position'] ?? 'N/A'); ?></div>
        <div class="field" id="number-of-days"><?php echo htmlspecialchars($request['number_of_days'] ?? 'N/A') . ' ថ្ងៃ'; ?></div>
        <div class="field" id="request-date"><?php echo htmlspecialchars($request['request_date'] ?? 'N/A'); ?></div>
        <div class="field" id="time-in-out">
            <div>ចូល: <?php echo htmlspecialchars($request['time_in'] ?? 'N/A'); ?></div>
            <div>ចេញ: <?php echo htmlspecialchars($request['time_out'] ?? 'N/A'); ?></div>
            (សរុប: <?php echo htmlspecialchars($request['total_hours'] ?? 'N/A'); ?>)
        </div>
        <div class="field" id="reason"><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></div>
        <div class="field" id="assigned-to"><?php echo htmlspecialchars($request['assigned_to'] ?? 'N/A'); ?></div>
        <div class="field" id="signature">
            <span class="line">_________________________</span><br>
            <?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?><br>
            <?php echo date('d/m/Y', strtotime($request['request_date'] ?? time())); ?>
        </div>

        <button onclick="window.print()" class="print-button no-print">បោះពុម្ព</button>
    </div>

    <script>
        // Ensure print styling works correctly
        window.addEventListener('DOMContentLoaded', (event) => {
            if (window.matchMedia) {
                const mediaQueryList = window.matchMedia('print');
                mediaQueryList.addEventListener('change', (mql) => {
                    if (mql.matches) {
                        document.querySelectorAll('.no-print').forEach(element => element.style.display = 'none');
                    } else {
                        document.querySelectorAll('.no-print').forEach(element => element.style.display = 'block');
                    }
                });
            }
        });
    </script>
</body>
</html>