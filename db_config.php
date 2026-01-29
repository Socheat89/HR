<?php
// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

// បង្កើតការភ្ជាប់
$conn = new mysqli($servername, $username, $password, $dbname);

// ពិនិត្យការភ្ជាប់
if ($conn->connect_error) {
    die("ការភ្ជាប់បរាជ័យ: " . $conn->connect_error);
}

// ដំណើរការទម្រង់នៅពេលដាក់ស្នើ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $employee_id = $_POST['employee-id'];
    $record_type = $_POST['record-type'];
    $record_date = $_POST['record-date'];
    $record_details = $_POST['record-details'];
    $record_value = $_POST['record-value'];
    
    // ការពារពី SQL injection
    $employee_id = mysqli_real_escape_string($conn, $employee_id);
    $record_type = mysqli_real_escape_string($conn, $record_type);
    $record_date = mysqli_real_escape_string($conn, $record_date);
    $record_details = mysqli_real_escape_string($conn, $record_details);
    $record_value = mysqli_real_escape_string($conn, $record_value);
    
    // សំណួរបញ្ចូលទិន្នន័យ
    $sql = "INSERT INTO hr_records (employee_id, record_type, record_date, details, value)
            VALUES ('$employee_id', '$record_type', '$record_date', '$record_details', '$record_value')";
    
    if ($conn->query($sql) === TRUE) {
        $success_message = "កំណត់ត្រាត្រូវបានរក្សាទុកដោយជោគជ័យ!";
    } else {
        $error_message = "កំហុស: " . $sql . "<br>" . $conn->error;
    }
}

// ទាញយកទិន្នន័យ HR records ដើម្បីបង្ហាញ
$sql = "SELECT hr.id, e.name, hr.record_type, hr.record_date, hr.details, hr.value 
        FROM hr_records hr
        JOIN employees e ON hr.employee_id = e.id
        ORDER BY hr.record_date DESC";
$result = $conn->query($sql);

// ទាញយកស្ថិតិ HR
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM hr_records WHERE record_type = 'leave' AND YEAR(record_date) = YEAR(CURDATE())) as leave_taken,
                (SELECT COUNT(*) FROM hr_records WHERE record_type = 'late' AND YEAR(record_date) = YEAR(CURDATE())) as late_count,
                (SELECT COUNT(*) FROM hr_records WHERE record_type = 'fingerprint' AND YEAR(record_date) = YEAR(CURDATE())) as fingerprint_miss,
                (SELECT SUM(REPLACE(value, '$', '')) FROM hr_records WHERE record_type = 'salary_adjustment' AND value LIKE '$%') as salary_cut";
$stats_result = $conn->query($stats_sql);
$hr_stats = $stats_result->fetch_assoc();

// កំណត់ចំនួនថ្ងៃច្បាប់នៅសល់ (សន្មត់ថា 14 ថ្ងៃក្នុងមួយឆ្នាំ)
$hr_stats['leave_left'] = 14 - ($hr_stats['leave_taken'] ?? 0);
?>