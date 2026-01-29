<?php
// Include the telegram.php file
require_once __DIR__ . '/includes/telegram.php'; // Ensure this path is correct

// Initialize variables
$success = '';
$errors = [];

// Only process form data if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Database connection (replace with your actual database credentials)
        $pdo = new PDO('mysql:host=localhost;dbname=samann1_admin_panel', 'samann1_admin_panel', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Handle request_type as an array from icons (via hidden inputs)
        $request_types = [];
        if (isset($_POST['request_type']) && is_array($_POST['request_type'])) {
            $request_types = array_map('trim', $_POST['request_type']);
        }
        $request_type_str = !empty($request_types) ? implode(', ', $request_types) : '';

        // Prepare the SQL statement
        $stmt = $pdo->prepare("
            INSERT INTO requests (
                request_type, requester_name, number_of_days, remaining_days, department, position, branch,
                request_date, return_date, late_hours, forgot_scan_in, forgot_scan_out, time_in, time_out,
                total_hours, repay_time_in, repay_time_out, repay_total_hours, reason, assigned_to, location,
                contact_number
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        // Execute with all parameters
        $stmt->execute([
            $request_type_str, // Store as a comma-separated string
            trim($_POST['requester_name'] ?? ''),
            isset($_POST['number_of_days']) && $_POST['number_of_days'] !== '' ? floatval($_POST['number_of_days']) : null,
            isset($_POST['remaining_days']) && $_POST['remaining_days'] !== '' ? floatval($_POST['remaining_days']) : null,
            trim($_POST['department'] ?? ''),
            trim($_POST['position'] ?? ''),
            trim($_POST['branch'] ?? ''),
            !empty($_POST['request_date']) ? $_POST['request_date'] : null,
            !empty($_POST['return_date']) ? $_POST['return_date'] : null,
            trim($_POST['late_hours'] ?? ''),
            trim($_POST['forgot_scan_in'] ?? ''),
            trim($_POST['forgot_scan_out'] ?? ''),
            !empty($_POST['time_in']) ? $_POST['time_in'] : null,
            !empty($_POST['time_out']) ? $_POST['time_out'] : null,
            trim($_POST['total_hours'] ?? ''),
            !empty($_POST['repay_time_in']) ? $_POST['repay_time_in'] : null,
            !empty($_POST['repay_time_out']) ? $_POST['repay_time_out'] : null,
            trim($_POST['repay_total_hours'] ?? ''),
            trim($_POST['reason'] ?? ''),
            trim($_POST['assigned_to'] ?? ''),
            trim($_POST['location'] ?? ''),
            !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null
        ]);

        // Success message
        $success = "Request submitted successfully!";

        // Send success notification to Telegram
        $chatId = '-4714007198'; // Your Chat ID
        $message = "សំណើថ្មី៖\n" .
                   "- ប្រភេទ៖ " . $request_type_str . "\n" .
                   "- ឈ្មោះ៖ " . ($_POST['requester_name'] ?? 'N/A') . "\n" .
                   "- ផ្នែក៖ " . ($_POST['department'] ?? 'N/A') . "\n" .
                   "- ថ្ងៃ៖ " . ($_POST['request_date'] ?? 'N/A') . "\n" .
                   "- មូលហេតុ៖ " . ($_POST['reason'] ?? 'N/A') . "\n" .
                   "- ម៉ោng៖ " . (isset($_POST['time_in']) && !empty($_POST['time_in']) ? $_POST['time_in'] : 'N/A');
        sendTelegramMessage($chatId, $message);

    } catch (PDOException $e) {
        // Handle database errors
        $errors[] = "Database error: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());

        // Send error notification to Telegram
        $chatId = '-4714007198'; // Your Chat ID
        $errorMessage = "Error submitting request:\n" . $e->getMessage();
        sendTelegramMessage($chatId, $errorMessage);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
  <title>Submit Request</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .request-form-container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      padding: 2rem 3rem;
      max-width: 600px;
      width: 100%;
      margin: 20px;
    }
    .form-title {
      color: #2c3e50;
      font-size: 2rem;
      font-weight: 700;
      text-align: center;
      margin-bottom: 1.5rem;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    .form-group {
      margin-bottom: 1.2rem;
    }
    label {
      color: #34495e;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: block;
    }
    .icon-group {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-bottom: 15px;
    }
    .request-icon {
      cursor: pointer;
      font-size: 1.5rem;
      color: #7f8c8d;
      transition: color 0.3s ease, transform 0.2s ease;
      display: flex;
      align-items: center;
    }
    .request-icon.active {
      color: #3498db;
      transform: scale(1.2);
    }
    .request-icon:hover {
      color: #2980b9;
      transform: scale(1.1);
    }
    .request-icon i {
      margin-right: 5px;
    }
    select, input[type="text"], input[type="number"], input[type="date"], input[type="time"] {
      width: 100%;
      padding: 10px 15px;
      border: 2px solid #e0e6f0;
      border-radius: 8px;
      font-size: 1rem;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    select:focus, input:focus {
      outline: none;
      border-color: #3498db;
      box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
    }
    .btn-primary {
      background-color: #3498db;
      border: none;
      padding: 12px 25px;
      font-size: 1.1rem;
      font-weight: 600;
      border-radius: 8px;
      transition: background-color 0.3s ease, transform 0.2s ease;
      width: 100%;
    }
    .btn-primary:hover {
      background-color: #2980b9;
      transform: translateY(-2px);
    }
    .btn-secondary {
      background-color: #7f8c8d;
      border: none;
      padding: 10px 20px;
      font-size: 1rem;
      border-radius: 8px;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }
    .btn-secondary:hover {
      background-color: #6c757d;
      transform: translateY(-2px);
    }
    .success, .error {
      text-align: center;
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 1rem;
    }
    .success { 
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    .error { 
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    @media (max-width: 768px) {
      .request-form-container {
        padding: 1.5rem 1.5rem;
        margin: 10px;
      }
      .form-title {
        font-size: 1.5rem;
      }
      .btn-primary, .btn-secondary {
        padding: 10px 15px;
        font-size: 1rem;
      }
      .icon-group {
        gap: 10px;
      }
      .request-icon {
        font-size: 1.2rem;
      }
    }
  </style>
</head>
<body>
  <div class="request-form-container">
    <h2 class="form-title">Submit Request</h2>
    
    <?php if ($success): ?>
      <p class="success"><?php echo $success; ?></p>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
      <p class="error"><?php echo $error; ?></p>
    <?php endforeach; ?>

    <form method="POST" action="submit_request.php" id="requestForm">
      <div class="form-group">
        <label>ប្រភេទនៃការស្នើសុំ</label>
        <div class="icon-group">
          <div class="request-icon" data-value="សម្រាកប្រចាំឆ្នាំ (Annual Leave)"><i class="fas fa-circle-check"></i> សម្រាកប្រចាំឆ្នាំ (Annual Leave)</div>
          <div class="request-icon" data-value="សម្រាកដោយជំងឺ (Sick Leave)"><i class="fas fa-circle-check"></i> សម្រាកដោយជំងឺ (Sick Leave)</div>
          <div class="request-icon" data-value="ភ្លេចស្កេនមេដៃ (Forgot FP)"><i class="fas fa-circle-check"></i> ភ្លេចស្កេនមេដៃ (Forgot FP)</div>
          <div class="request-icon" data-value="សម្រាកលំហែមាឡុភាព (Maternity Leave)"><i class="fas fa-circle-check"></i> សម្រាកលំហែមាឡុភាព (Maternity Leave)</div>
          <div class="request-icon" data-value="ថែមម៉ោង (OT)"><i class="fas fa-circle-check"></i> ថែមម៉ោង (OT)</div>
          <div class="request-icon" data-value="ចេញមុនម៉ោង (Early)"><i class="fas fa-circle-check"></i> ចេញមុនម៉ោង (Early)</div>
          <div class="request-icon" data-value="ប្តូរថ្ងៃសម្រាក (Changing day off)"><i class="fas fa-circle-check"></i> ប្តូរថ្ងៃសម្រាក (Changing day off)</div>
          <div class="request-icon" data-value="សម្រាកពិសេស (Special Leave)"><i class="fas fa-circle-check"></i> សម្រាកពិសេស (Special Leave)</div>
          <div class="request-icon" data-value="មកយឺត (Late)"><i class="fas fa-circle-check"></i> មកយឺត (Late)</div>
        </div>
        <!-- Hidden input to store selected request types -->
        <input type="hidden" name="request_type[]" id="selectedRequestTypes" value="">
      </div>
      <div class="form-group">
        <label>ឈ្មោះអ្នកសើ្នសុំ</label>
        <input type="text" name="requester_name" placeholder="ឈ្មោះរបស់អ្នក" required>
      </div>
      <div class="form-group">
        <label>ចំនួនថ្ងៃ</label>
        <select name="number_of_days">
          <option value="">សូមជ្រើសរើស</option>
          <?php for ($i = 0.5; $i <= 9; $i += 0.5): ?>
            <option value="<?php echo $i; ?> /ថ្ងៃ"><?php echo $i; ?> </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label>ចំនួនថ្ងៃនៅសល់</label>
        <select name="remaining_days">
          <option value="">សូមជ្រើសរើស</option>
          <?php for ($i = 0.5; $i <= 9; $i += 0.5): ?>
            <option value="<?php echo $i; ?> /ថ្ងៃ"><?php echo $i; ?> </option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label>បុគ្គលិកផ្នែក</label>
        <select name="department">
          <option value="">សូមជ្រើសរើស</option>
          <option value="IT">IT</option>
          <option value="Stock">Stock</option>
          <option value="Accountant">Accountant</option>
          <option value="Admin">Admin</option>
          <option value="Sale">Sale</option>
          <option value="Worker">Worker</option>
        </select>
      </div>
      <div class="form-group">
        <label>មុខដំណែង</label>
        <select name="position">
          <option value="">សូមជ្រើសរើស</option>
          <option value="ព័ត៌មានវិទ្យា">ព័ត៌មានវិទ្យា</option>
          <option value="គិតលុយ">គិតលុយ</option>
          <option value="រដ្ឋបាលទូទៅ">រដ្ឋបាលទូទៅ</option>
          <option value="បុគ្គលិកផ្នែកលក់">បុគ្គលិកផ្នែកលក់</option>
          <option value="បុគ្គលិកផ្នែកស្តុក318">បុគ្គលិកផ្នែកស្តុក318</option>
          <option value="ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ">ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ</option>
          <option value="ប្រធានឃ្លាំng៣១៨និngហាងទំនិញ">ប្រធានឃ្លាំng៣១៨និngហាងទំនិញ</option>
          <option value="បុគ្គលិកផ្នែកគណនេយ្យ">បុគ្គលិកផ្នែកគណនេយ្យ</option>
          <option value="ប្រមូលសាច់ប្រាក់">ប្រមូលសាច់ប្រាក់</option>
          <option value="ប្រធានឃ្លាំng CH1">ប្រធានឃ្លាំng CH1</option>
          <option value="រដ្ឋបាលឃ្លាំng CH1">រដ្ឋបាលឃ្លាំng CH1</option>
          <option value="ជំនូយការប្រធានឃ្លាំng CH1">ជំនូយការប្រធានឃ្លាំng CH1</option>
          <option value="ប្រធានឃ្លាំng CKD">ប្រធានឃ្លាំng CKD</option>
          <option value="ជំនួយការប្រធានឃ្លាង CKD">ជំនួយការប្រធានឃ្លាង CKD</option>
          <option value="ប្រធានរដ្ឋបាលឃ្លាង CKD">ប្រធានរដ្ឋបាលឃ្លាង CKD</option>
          <option value="ប្រធានឃ្លាង ST1">ប្រធានឃ្លាង ST1</option>
          <option value="ប្រធានឃ្លាង PSP">ប្រធានឃ្លាង PSP</option>
          <option value="លើកទំនិញ">លើកទំនិញ</option>
          <option value="បើកបរកngបី">បើកបរកngបី</option>
          <option value="បើកបរ�រថយន្ត">បើកបរ�រថយន្ត</option>
        </select>
      </div>
      <div class="form-group">
        <label>សាខា</label>
        <select name="branch" required>
          <option value="">សូមជ្រើសរើស</option>
          <option value="ហាងទំនិញ 318">ហាងទំនិញ 318</option>
          <option value="ការិយាល័យកណ្តាល">ការិយាល័យកណ្តាល</option>
          <option value="ឃ្លាង CH1">ឃ្លាង CH1</option>
          <option value="ឃ្លាង CKD">ឃ្លាង CKD</option>
          <option value="ឃ្លាង ST1">ឃ្លាង ST1</option>
          <option value="ឃ្លាងPSP">ឃ្លាង PSP</option>
        </select>
      </div>
      <div class="form-group">
        <label>ថ្ងៃខែឆ្នាំឈប់/OT/មកយឺត/ភ្លេចស្កេន</label>
        <input type="date" name="request_date">
      </div>
      <div class="form-group">
        <label>ថៃ្ងចូលសngវិញ</label>
        <input type="date" name="return_date">
      </div>
      <div class="form-group">
        <label>ចំនួngម៉ោngយឺត</label>
        <input type="text" name="late_hours" placeholder="បំពេញចំនួនម៉ោng">
      </div>
      <div class="form-group">
        <label>ភ្លេចស្កេនចូល</label>
        <select name="forgot_scan_in">
          <option value="">សូមជ្រើសរើស</option>
          <option value="ភ្លេចចូល 1ដng">ភ្លេចចូល 1ដng</option>
          <option value="ភ្លេចចូល 2ដng">ភ្លេចចូល 2ដng</option>
          <option value="ភ្លេចចូល 3ដng">ភ្លេចចូល 3ដng</option>
          <option value="ភ្លេចចូល 4ដng">ភ្លេចចូល 4ដng</option>
        </select>
      </div>
      <div class="form-group">
        <label>ភ្លេចស្កេនចេញ</label>
        <select name="forgot_scan_out">
          <option value="">សូមជ្រើសរើស</option>
          <option value="ភ្លេចចេញ 1ដng">ភ្លេចចេញ 1ដng</option>
          <option value="ភ្លេចចេញ 2ដng">ភ្លេចចេញ 2ដng</option>
          <option value="ភ្លេចចេញ 3ដng">ភ្លេចចេញ 3ដng</option>
          <option value="ភ្លេចចេញ 4ដng">ភ្លេចចេញ 4ដng</option>
        </select>
      </div>
      <div class="form-group">
        <label>ម៉ោngចូល</label>
        <input type="time" name="time_in">
      </div>
      <div class="form-group">
        <label>ម៉ោngចេញ</label>
        <input type="time" name="time_out">
      </div>
      <div class="form-group">
        <label>ចំនួនម៉ោngសរុប</label>
        <input type="text" name="total_hours" placeholder="បំពេញចំនួនម៉ោngសរុប(8h30mn)">
      </div>
      <div class="form-group">
        <label>ម៉ោngចូលសng</label>
        <input type="time" name="repay_time_in">
      </div>
      <div class="form-group">
        <label>ម៉ោngចេញសng</label>
        <input type="time" name="repay_time_out">
      </div>
      <div class="form-group">
        <label>ម៉ោngសngសរុប</label>
        <input type="text" name="repay_total_hours" placeholder="បំពេញចំនួនម៉ោngសរុប(8h30mn)">
      </div>
      <div class="form-group">
        <label>មូលហេតុ</label>
        <input type="text" name="reason" placeholder="បំពេញមូលហេតុរបស់អ្នក">
      </div>
      <div class="form-group">
        <label>ប្រគល់ការងារឱ្ល</label>
        <input type="text" name="assigned_to" placeholder="បំពេញអ្នកប្រគល់ការងារឱ្ល">
      </div>
      <div class="form-group">
        <label>ទីកន្លែង</label>
        <input type="text" name="location" placeholder="បំពេញទីកន្លែង">
      </div>
      <div class="form-group">
        <label>លេខទំនាក់ទំង</label>
        <input type="number" name="contact_number" placeholder="បំពេញលេខទំនាក់ទំងផ្ទាល់ខ្លួនរបស់អ្នក">
      </div>
      <button type="submit" class="btn btn-primary">បញ្ជូន</button>
    </form>
    <div class="text-center mt-3">
  <a href="https://app.vvc.asia/home.php" class="btn btn-secondary">
    <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ទៅទំព័រដើម
  </a>
</div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const icons = document.querySelectorAll('.request-icon');
      const hiddenInput = document.getElementById('selectedRequestTypes');

      // Handle icon clicks to toggle selection
      icons.forEach(icon => {
        icon.addEventListener('click', function () {
          this.classList.toggle('active');
          // Collect selected values
          const selectedValues = [];
          icons.forEach(i => {
            if (i.classList.contains('active')) {
              selectedValues.push(i.getAttribute('data-value'));
            }
          });
          // Update hidden input with selected values
          hiddenInput.value = selectedValues.join(', ');
        });
      });

      // Ensure at least one selection is required before submission
      document.getElementById('requestForm').addEventListener('submit', function (e) {
        if (!hiddenInput.value) {
          e.preventDefault();
          alert('សូមជ្រើសរើសយ៉ាងហ្មត់មួយប្រភេទនៃការស្នើសុំ៑');
        }
      });
    });
  </script>
</body>
</html>