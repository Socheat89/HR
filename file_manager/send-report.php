<?php
// Telegram Bot Token and Chat ID
$botToken = '8132165664:AAE5sE2HBg6P0IyIoM8xYhSFuBzHumUWK5o'; // Replace with your bot token
$chatId = '-4757352988'; // Replace with your chat ID

// Check if Imagick is available
if (!extension_loaded('imagick')) {
    die("Imagick extension is not installed. Please install it to render Khmer text correctly.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $date = isset($_POST['date']) ? trim($_POST['date']) : date('d/m/Y');
    $service_section = [
        'total' => isset($_POST['service_total']) ? (int)$_POST['service_total'] : 0,
        'completed' => isset($_POST['service_completed']) ? (int)$_POST['service_completed'] : 0,
        'remaining' => isset($_POST['service_remaining']) ? (int)$_POST['service_remaining'] : 0,
    ];
    $spare_parts_section = [
        'total' => isset($_POST['spare_parts_total']) ? (int)$_POST['spare_parts_total'] : 0,
        'completed' => isset($_POST['spare_parts_completed']) ? (int)$_POST['spare_parts_completed'] : 0,
        'remaining' => isset($_POST['spare_parts_remaining']) ? (int)$_POST['spare_parts_remaining'] : 0,
    ];
    $repair_section = [
        'total' => isset($_POST['repair_total']) ? (int)$_POST['repair_total'] : 0,
        'ch1' => isset($_POST['repair_ch1']) ? (int)$_POST['repair_ch1'] : 0,
        'ckd' => isset($_POST['repair_ckd']) ? (int)$_POST['repair_ckd'] : 0,
        'st1' => isset($_POST['repair_st1']) ? (int)$_POST['repair_st1'] : 0,
        'psp' => isset($_POST['repair_psp']) ? (int)$_POST['repair_psp'] : 0,
    ];
    $total_amount_section = [
        'service' => $service_section['total'],
        'spare_parts' => $spare_parts_section['total'],
        'repair' => $repair_section['total'],
        'remaining' => isset($_POST['total_remaining']) ? (int)$_POST['total_remaining'] : 0,
        'grand_total' => isset($_POST['total_grand']) ? (int)$_POST['total_grand'] : 0,
    ];

    // Telegram API URL
    $telegramApi = "https://api.telegram.org/bot$botToken/";

    // Function to send photo to Telegram
    function sendPhoto($api, $chatId, $imagePath) {
        $url = $api . "sendPhoto";
        $postFields = [
            'chat_id' => $chatId,
            'photo' => new CURLFile($imagePath, 'image/png', 'report.png')
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    // Create image with Imagick
    $image = new Imagick();
    $image->newImage(800, 600, new ImagickPixel('white')); // White background
    $draw = new ImagickDraw();

    // Font setup
    $font = 'NotoSansKhmer-Regular.ttf'; // Place NotoSansKhmer-Regular.ttf in the same directory
    if (!file_exists($font)) {
        die("Khmer font file (NotoSansKhmer-Regular.ttf) not found. Please download it from https://fonts.google.com/noto/specimen/Noto+Sans+Khmer and place it in the same directory.");
    }
    $draw->setFont($font);
    $draw->setTextEncoding('UTF-8');

    // Colors
    $black = new ImagickPixel('black');
    $yellow = new ImagickPixel('rgb(255, 204, 0)'); // Yellow for headers
    $blue = new ImagickPixel('rgb(0, 102, 204)'); // Blue for sections
    $white = new ImagickPixel('white');

    // Draw title and date
    $draw->setFillColor($black);
    $draw->setFontSize(20);
    $image->annotateImage($draw, 20, 40, 0, "របាយការណ៍ប្រចាំថ្ងៃ ស្តីពីសកម្មភាព");
    $draw->setFontSize(14);
    $image->annotateImage($draw, 20, 70, 0, "ថ្ងៃទី {$date}");

    // Service Section
    $draw->setFillColor($yellow);
    $draw->rectangle(20, 90, 780, 120);
    $draw->setFillColor($black);
    $draw->setFontSize(14);
    $image->annotateImage($draw, 20, 110, 0, "ផ្នែកសេវាកម្ម");
    $draw->setFillColor($blue);
    $draw->rectangle(20, 130, 260, 160);
    $draw->rectangle(260, 130, 500, 160);
    $draw->rectangle(500, 130, 780, 160);
    $draw->setFillColor($white);
    $image->annotateImage($draw, 30, 150, 0, "សរុប {$service_section['total']} សំណើ");
    $image->annotateImage($draw, 270, 150, 0, "បានបញ្ចប់ {$service_section['completed']}");
    $image->annotateImage($draw, 510, 150, 0, "នៅសល់ {$service_section['remaining']}");

    // Spare Parts Section
    $draw->setFillColor($yellow);
    $draw->rectangle(20, 180, 780, 210);
    $draw->setFillColor($black);
    $draw->setFontSize(14);
    $image->annotateImage($draw, 20, 200, 0, "ផ្នែកគ្រឿងបន្លាស់");
    $draw->setFillColor($blue);
    $draw->rectangle(20, 220, 260, 250);
    $draw->rectangle(260, 220, 500, 250);
    $draw->rectangle(500, 220, 780, 250);
    $draw->setFillColor($white);
    $image->annotateImage($draw, 30, 240, 0, "សរុប {$spare_parts_section['total']} សំណើ");
    $image->annotateImage($draw, 270, 240, 0, "បានបញ្ចប់ {$spare_parts_section['completed']}");
    $image->annotateImage($draw, 510, 240, 0, "នៅសល់ {$spare_parts_section['remaining']}");

    // Repair Section
    $draw->setFillColor($yellow);
    $draw->rectangle(20, 270, 780, 300);
    $draw->setFillColor($black);
    $draw->setFontSize(14);
    $image->annotateImage($draw, 20, 290, 0, "ផ្នែកជួសជុល");
    $draw->setFillColor($blue);
    $draw->rectangle(20, 310, 156, 340);
    $draw->rectangle(156, 310, 292, 340);
    $draw->rectangle(292, 310, 428, 340);
    $draw->rectangle(428, 310, 564, 340);
    $draw->rectangle(564, 310, 780, 340);
    $draw->setFillColor($white);
    $image->annotateImage($draw, 30, 330, 0, "សរុប {$repair_section['total']} សំណើ");
    $image->annotateImage($draw, 166, 330, 0, "CH1 {$repair_section['ch1']}");
    $image->annotateImage($draw, 302, 330, 0, "CKD {$repair_section['ckd']}");
    $image->annotateImage($draw, 438, 330, 0, "ST1 {$repair_section['st1']}");
    $image->annotateImage($draw, 574, 330, 0, "PSP {$repair_section['psp']}");

    // Total Amount Section
    $y = 360;
    $draw->setFillColor($yellow);
    $draw->rectangle(20, $y, 780, $y + 30);
    $draw->setFillColor($black);
    $draw->setFontSize(14);
    $image->annotateImage($draw, 20, $y + 20, 0, "សរុប");
    $y += 40;
    $draw->setFillColor($blue);
    $draw->rectangle(20, $y, 156, $y + 30);
    $draw->rectangle(156, $y, 292, $y + 30);
    $draw->rectangle(292, $y, 428, $y + 30);
    $draw->rectangle(428, $y, 564, $y + 30);
    $draw->rectangle(564, $y, 780, $y + 30);
    $draw->setFillColor($white);
    $image->annotateImage($draw, 30, $y + 20, 0, "សេវាកម្ម {$total_amount_section['service']} សំណើ");
    $image->annotateImage($draw, 166, $y + 20, 0, "គ្រឿងបន្លាស់ {$total_amount_section['spare_parts']} សំណើ");
    $image->annotateImage($draw, 302, $y + 20, 0, "ជួសជុល {$total_amount_section['repair']} សំណើ");
    $image->annotateImage($draw, 438, $y + 20, 0, "នៅសល់ {$total_amount_section['remaining']} សំណើ");
    $image->annotateImage($draw, 574, $y + 20, 0, "សរុប {$total_amount_section['grand_total']} សំណើ");

    // Finalize image
    $image->setImageFormat('png');
    $tempImagePath = sys_get_temp_dir() . '/report.png';
    $image->writeImage($tempImagePath);
    $image->destroy();

    // Send image to Telegram
    if (file_exists($tempImagePath)) {
        sendPhoto($telegramApi, $chatId, $tempImagePath);
        unlink($tempImagePath); // Delete temporary file
        echo "<p>របាយការណ៍ត្រូវបានបញ្ជូនជារូបភាពទៅកាន់ Telegram ដោយជោគជ័យ!</p>";
    } else {
        echo "<p>កំហុសក្នុងការបង្កើតរូបភាព!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ជូនរបាយការណ៍ទៅ Telegram Bot</title>
    <style>
        body { font-family: 'Khmer OS', Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 20px; background-color: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        .sub-group { margin-left: 20px; }
    </style>
</head>
<body>
    <h2>បញ្ជូនរបាយការណ៍ទៅ Telegram Bot</h2>
    <form method="post">
        <div class="form-group">
            <label for="date">ថ្ងៃទី:</label>
            <input type="text" id="date" name="date" placeholder="ថ្ងៃទី (dd/mm/yyyy)" value="<?php echo date('d/m/Y'); ?>" required>
        </div>

        <div class="form-group">
            <label>ផ្នែកសេវាកម្ម:</label>
            <div class="sub-group">
                <label for="service_total">សរុប (សំណើ):</label>
                <input type="number" id="service_total" name="service_total" required>
            </div>
            <div class="sub-group">
                <label for="service_completed">បានបញ្ចប់:</label>
                <input type="number" id="service_completed" name="service_completed" required>
            </div>
            <div class="sub-group">
                <label for="service_remaining">នៅសល់:</label>
                <input type="number" id="service_remaining" name="service_remaining" required>
            </div>
        </div>

        <div class="form-group">
            <label>ផ្នែកគ្រឿងបន្លាស់:</label>
            <div class="sub-group">
                <label for="spare_parts_total">សរុប (សំណើ):</label>
                <input type="number" id="spare_parts_total" name="spare_parts_total" required>
            </div>
            <div class="sub-group">
                <label for="spare_parts_completed">បានបញ្ចប់:</label>
                <input type="number" id="spare_parts_completed" name="spare_parts_completed" required>
            </div>
            <div class="sub-group">
                <label for="spare_parts_remaining">នៅសល់:</label>
                <input type="number" id="spare_parts_remaining" name="spare_parts_remaining" required>
            </div>
        </div>

        <div class="form-group">
            <label>ផ្នែកជួសជុល:</label>
            <div class="sub-group">
                <label for="repair_total">សរុប (សំណើ):</label>
                <input type="number" id="repair_total" name="repair_total" required>
            </div>
            <div class="sub-group">
                <label for="repair_ch1">CH1:</label>
                <input type="number" id="repair_ch1" name="repair_ch1" required>
            </div>
            <div class="sub-group">
                <label for="repair_ckd">CKD:</label>
                <input type="number" id="repair_ckd" name="repair_ckd" required>
            </div>
            <div class="sub-group">
                <label for="repair_st1">ST1:</label>
                <input type="number" id="repair_st1" name="repair_st1" required>
            </div>
            <div class="sub-group">
                <label for="repair_psp">PSP:</label>
                <input type="number" id="repair_psp" name="repair_psp" required>
            </div>
        </div>

        <div class="form-group">
            <label>សរុប:</label>
            <div class="sub-group">
                <label for="total_remaining">នៅសល់ (សំណើ):</label>
                <input type="number" id="total_remaining" name="total_remaining" required>
            </div>
            <div class="sub-group">
                <label for="total_grand">សរុបសំណើ:</label>
                <input type="number" id="total_grand" name="total_grand" required>
            </div>
        </div>

        <button type="submit">បញ្ជូនរបាយការណ៍</button>
    </form>
</body>
</html>