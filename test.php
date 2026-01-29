<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'admin/includes/db.php'; // Database connection
$conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Handle filter by date or ID
$filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
$mission_id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;

if ($filter_date) {
    $stmt = $conn->prepare("SELECT * FROM mission_letters WHERE start_date = :date ORDER BY id DESC LIMIT 1");
    $stmt->bindValue(':date', $filter_date, PDO::PARAM_STR);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($mission_id) {
    $stmt = $conn->prepare("SELECT * FROM mission_letters WHERE id = :id");
    $stmt->bindParam(':id', $mission_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->query("SELECT * FROM mission_letters ORDER BY id DESC LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no data is found, set a default
if (!$data) {
    $data = [
        'location' => '............................',
        'purpose' => '............................',
        'person1' => '............................',
        'role1' => '............................',
        'person2' => '............................',
        'role2' => '............................',
        'start_date' => '............................',
        'start_time' => '............................',
        'end_date' => '............................',
        'end_time' => '............................',
        'transport' => '............................',
        'materials' => '............................',
        'date_khmer' => '............................'
    ];
}

// Function to format time to AM/PM
function formatTimeToAMPM($time) {
    if (!$time || $time === '............................') return "............................";
    $dateTime = new DateTime($time);
    return $dateTime->format('h:i A');
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>លិខិតបញ្ជាបេសកម្ម</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .a4 { width: 210mm; height: 297mm; margin: auto; padding: 12mm; position: relative; background: white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); box-sizing: border-box; }
        .controls { margin-bottom: 20px; text-align: center; }
        @media print { 
            body { margin: 0; padding: 0; background: none; }
            .a4 { box-shadow: none; width: 210mm; height: 297mm; margin: 0; padding: 12mm; page-break-after: always; }
            .controls { display: none; }
        }
        .header { display: flex; align-items: center; justify-content: space-between; width: 60%; margin-top: 10px; }
        .header img { width: 130px; }
        h5 { text-align: center; margin: 0; font-size: 18px; line-height: 1.5; color: #007AFF; }
        .main-content1 { margin-top: 15px; }
        .main-content1 .item { display: flex; align-items: flex-start; margin: 8px 0; font-family: 'Khmer OS Battambang'; font-size: 18px; }
        .main-content1 .label { width: 180px; font-weight: bold; }
        .main-content1 .value { margin-right: 1rem; }
        .tacteing-text { font-family: 'Tacteing', 'Khmer OS Battambang', Arial, sans-serif; text-align: center; color: goldenrod; font-size: 20px; margin: 8px 0; }
        .text1 { font-size: 16px; margin: 10px 0; line-height: 1.5; }
        .signature { text-align: right; margin-top: 20px; }
        .signature .inner { display: inline-block; text-align: center; }
        .signature p { margin: 5px 0; font-family: 'Koulen'; line-height: 1.5; }
        .signature .date { font-family: 'Khmer OS Battambang'; font-size: 14px; }
        .signature .name { font-family: 'kh Muol'; font-size: 20px; margin-top: 40px; font-weight: bold; }
        footer { position: absolute; bottom: 12mm; width: calc(100% - 24mm); text-align: center; font-size: 12px; color: #D4AF37; }
        footer hr { border: 1px solid gold; margin: 0; }
        footer p { font-family: 'Khmer OS Battambang'; margin: 5px 0 0; line-height: 1.2; }
        /* Font definitions */
        @font-face { font-family: 'Tacteing'; src: url('/font/Tacteing.ttf') format('truetype'); }
        @font-face { font-family: 'Koulen'; src: url('/font/Koulen.ttf') format('truetype'); }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('/font/KhmerOSBattambang.ttf') format('truetype'); }
        @font-face { font-family: 'kh Muol'; src: url('/font/KhMuol.ttf') format('truetype'); }
        @media print {
            .tacteing-text { font-family: 'Tacteing', 'Khmer OS Battambang', Arial, sans-serif; }
        }
    </style>
</head>
<body>
    <div class="controls">
        <form method="GET" action="" class="d-inline-block">
            <label for="filter_date">ស្វែងរកតាមកាលបរិច្ឆេទចាប់ផ្តើម:</label>
            <input type="date" id="filter_date" name="filter_date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <button type="submit" class="btn btn-primary btn-sm">ស្វែងរក</button>
        </form>
        <button onclick="window.print()" class="btn btn-success btn-sm ms-3">បោះពុម្ព</button>
    </div>

    <div class="a4">
        <h5>ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ</h5>
        <p class="tacteing-text">3</p>
        <div class="header">
            <div class="logo">
                <img src="https://i.ibb.co/hdy8JSv/Logo-Van-Van-1.png" alt="logo">
            </div>
            <div class="main-title">
                <h5 style="font-family: 'Koulen';">លិខិតបញ្ជាបេសកម្ម</h5>
                <p class="tacteing-text">3</p>
            </div>
        </div>
        <p class="text1">
            <strong style="font-family: 'Koulen';">អគ្គនាយិកា ក្រុមហ៊ុន វណ្ណ វណ្ច ខេមបូឌា</strong> បានសម្រេចចាត់តាំងបុគ្គលិកដែលមានរាយនាមដូចខាងក្រោម ចុះបំពេញបេសកកម្មនៅ៖ 
            <span id="showLocation"><?php echo htmlspecialchars($data['location']); ?></span> ដើម្បី <span id="showPurpose"><?php echo htmlspecialchars($data['purpose']); ?></span>
        </p>
        <div class="main-content1">
            <div class="item">
                <span class="label">១. លោក-លោកស្រី៖</span>
                <span class="value"><?php echo htmlspecialchars($data['person1']); ?></span>
                <div>
                <span class="lab" style="margin-left: 3.6rem;" >តួនាទី៖</span>
                <span style="margin-left:3rem;" ><?php echo htmlspecialchars($data['role1']); ?></span>
                </div>
            </div>
            <div class="item">
                <span class="label">២. លោក-លោកស្រី៖</span>
                <span class="value"><?php echo htmlspecialchars($data['person2']); ?></span>
                <div>
                <span class="lab" style="margin-left: 3.2rem;" >តួនាទី៖</span>
                <span style="margin-left: 3rem;"><?php echo htmlspecialchars($data['role2']); ?></span>
                </div>
            </div>
            <div class="item">
                <span class="label">៣. លោក-លោកស្រី៖</span>
                <span class="value">............................</span>
                <span class="label">តួនាទី៖</span>
                <span style="margin-left: -5rem;">............................</span>
            </div>
            <div class="item">
                <span class="label">៤. លោក-លោកស្រី៖</span>
                <span class="value">............................</span>
                <span class="label">តួនាទី៖</span>
                <span style="margin-left: -5rem;">............................</span>
            </div>
            <div class="item">
                <span class="label">៥. លោក-លោកស្រី៖</span>
                <span class="value">............................</span>
                <span class="label">តួនាទី៖</span>
                <span style="margin-left: -5rem;">............................</span>
            </div>
            <div class="item">
                <span class="label">ថ្ងៃចេញដំណើរ៖</span>
                <span class="value"><?php echo htmlspecialchars($data['start_date']); ?></span>
                <span class="label">ម៉ោងចេញដំណើរ៖</span>
                <span class="value"><?php echo htmlspecialchars(formatTimeToAMPM($data['start_time'])); ?></span>
            </div>
            <div class="item">
                <span class="label">ថ្ងៃត្រឡប់មកវិញ៖</span>
                <span class="value"><?php echo htmlspecialchars($data['end_date']); ?></span>
                <span class="label">ម៉ោងត្រឡប់មកវិញ៖</span>
                <span class="value"><?php echo htmlspecialchars(formatTimeToAMPM($data['end_time'])); ?></span>
            </div>
            <p style="font-family: 'Khmer OS Battambang'; margin: 8px 0;">
                <span class="label" style="display: inline-block; width: 180px;">មធ្យបាយធ្វើដំណើរ:</span>
                <span class="value"><?php echo htmlspecialchars($data['transport']); ?></span>
            </p>
            <p style="font-family: 'Khmer OS Battambang'; margin: 8px 0;">
                <span class="label" style="display: inline-block; width: 180px;">សម្ភារៈភ្ជាប់ជាមួយ:</span>
                <span class="value"><?php echo htmlspecialchars($data['materials']); ?></span>
            </p>
            <p style="font-family: 'Khmer OS Battambang'; margin: 8px 0;">អាស្រ័យដូចបានជម្រាបមកខាងលើ សូមបុគ្គលិកដែលពាក់ព័ន្ធទាំងអស់ជួយសម្រួលការចុះបេសកកម្មនេះ ដោយក្តីអនុគ្រោះ។</p>
          <div class="signature">
                <div class="inner">
                    <p class="date">
                        <?php
                        $date_khmer = htmlspecialchars($data['date_khmer']);
                        echo "<!-- Debug: Raw date_khmer = '$date_khmer' -->\n";
                        if ($date_khmer === '............................') {
                            echo $date_khmer;
                        } else {
                            if (strpos($date_khmer, 'br') !== false) {
                                $parts = explode('br', $date_khmer);
                                echo "<!-- Debug: Parts = '" . implode("', '", $parts) . "' -->\n";
                                echo trim($parts[0]) . '<br>' . trim($parts[1]);
                            } else {
                                echo $date_khmer . " <!-- Note: 'br' delimiter missing -->";
                            }
                        }
                        ?>
                    </p>
                    <p class="name">ទាំងនេះហើយ!<br>រួចតែម្តង</p>
                </div>
            </div>
    </div>
    <footer>
        <hr>
        <p>Copyright © 2025 - Company</p>
    </footer>
</body>
</html>
