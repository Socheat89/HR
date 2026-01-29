<?php
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'admin/includes/db.php'; // Database connection
$conn = include 'admin/includes/db.php';
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $location = $_POST['location'] ?? '';
        $purpose = $_POST['purpose'] ?? '';
        $person1 = $_POST['person1'] ?? '';
        $role1 = $_POST['role1'] ?? '';
        $person2 = $_POST['person2'] ?? '';
        $role2 = $_POST['role2'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $transport = $_POST['transport'] ?? '';
        $materials = $_POST['materials'] ?? '';
        $date_khmer_part1 = $_POST['date_khmer_part1'] ?? ''; // Khmer lunar date
        $date_khmer_part2 = $_POST['date_khmer_part2'] ?? ''; // Gregorian equivalent

        // Combine the two parts with 'br' as separator
        $date_khmer = trim($date_khmer_part1) . ' br ' . trim($date_khmer_part2);

        // Insert data into the database
        $sql = "INSERT INTO mission_letters (location, purpose, person1, role1, person2, role2, start_date, start_time, end_date, end_time, transport, materials, date_khmer) 
                VALUES (:location, :purpose, :person1, :role1, :person2, :role2, :start_date, :start_time, :end_date, :end_time, :transport, :materials, :date_khmer)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':location' => $location,
            ':purpose' => $purpose,
            ':person1' => $person1,
            ':role1' => $role1,
            ':person2' => $person2,
            ':role2' => $role2,
            ':start_date' => $start_date,
            ':start_time' => $start_time,
            ':end_date' => $end_date,
            ':end_time' => $end_time,
            ':transport' => $transport,
            ':materials' => $materials,
            ':date_khmer' => $date_khmer
        ]);

        // Redirect to the display page after insertion
        header("Location: mission.php");
        exit;
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ចូលទិន្នន័យបេសកកម្ម</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            background: linear-gradient(135deg, #f0f4f8, #d9e4f5);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .form-container {
            max-width: 900px;
            width: 100%;
            padding: 40px;
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e7ff;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        .form-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, #4a90e2, #50e3c2);
            border-radius: 15px 15px 0 0;
        }

        h3 {
            font-family: 'Koulen', sans-serif;
            color: #1a3c34;
            text-align: center;
            margin-bottom: 30px;
            font-size: 36px;
            font-weight: 700;
            text-transform: uppercase;
            position: relative;
        }

        h3::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: #4a90e2;
            margin: 10px auto;
            border-radius: 2px;
            transition: width 0.3s ease;
        }

        h3:hover::after {
            width: 100px;
        }

        label {
            font-family: 'Khmer OS Battambang', sans-serif;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 16px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 12px 15px;
            font-size: 16px;
            background: #f9fafb;
            transition: all 0.3s ease;
            width: 100%;
            outline: none;
        }

        .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 10px rgba(74, 144, 226, 0.3);
            background: #ffffff;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #6b7280;
            font-size: 18px;
            transition: color 0.3s ease;
        }

        .input-group .form-control {
            padding-left: 40px;
        }

        .input-group:focus-within i {
            color: #4a90e2;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4a90e2, #50e3c2);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Khmer OS Battambang', sans-serif;
            font-weight: 600;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #357abd, #2ec4b6);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Khmer OS Battambang', sans-serif;
            font-weight: 600;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #4b5563, #374151);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107, 114, 128, 0.4);
        }

        .row {
            margin-bottom: 25px;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            gap: 15px;
        }

        .form-section {
            border-bottom: 1px dashed #e0e7ff;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }

        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 20px;
                margin: 10px;
            }

            h3 {
                font-size: 28px;
            }

            .row {
                flex-direction: column;
            }

            .col-md-6 {
                width: 100%;
                margin-bottom: 15px;
            }

            .form-footer {
                flex-direction: column;
                gap: 10px;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }

        @font-face {
            font-family: 'Khmer OS Battambang';
            src: url('/font/KhmerOSBattambang.ttf') format('truetype');
        }

        @font-face {
            font-family: 'Koulen';
            src: url('/font/Koulen.ttf') format('truetype');
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h3>បញ្ចូលទិន្នន័យបេសកកម្ម</h3>
        <form method="POST" action="">
            <div class="form-section">
                <div class="row">
                    <div class="col-md-6">
                        <label for="location" class="form-label">ទីតាំង:</label>
                        <div class="input-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="purpose" class="form-label">គោលបំណង:</label>
                        <div class="input-group">
                            <i class="fas fa-bullseye"></i>
                            <input type="text" class="form-control" id="purpose" name="purpose" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="person1" class="form-label">អ្នកធ្វើបេសកកម្ម (១):</label>
                        <input type="text" class="form-control" id="person1" name="person1" required>
                    </div>
                    <div class="col-md-6">
                        <label for="role1" class="form-label">តួនាទី (១):</label>
                        <input type="text" class="form-control" id="role1" name="role1" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="person2" class="form-label">អ្នកធ្វើបេសកកម្ម (២):</label>
                        <input type="text" class="form-control" id="person2" name="person2" required>
                    </div>
                    <div class="col-md-6">
                        <label for="role2" class="form-label">តួនាទី (២):</label>
                        <input type="text" class="form-control" id="role2" name="role2" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="row">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">កាលបរិច្ឆេទចាប់ផ្តើម:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="start_time" class="form-label">ម៉ោងចាប់ផ្តើម:</label>
                        <input type="time" class="form-control" id="start_time" name="start_time" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">កាលបរិច្ឆេទបញ្ចប់:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-6">
                        <label for="end_time" class="form-label">ម៉ោងបញ្ចប់:</label>
                        <input type="time" class="form-control" id="end_time" name="end_time" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="row">
                    <div class="col-md-6">
                        <label for="transport" class="form-label">របៀបដំណើរ:</label>
                        <input type="text" class="form-control" id="transport" name="transport" required>
                    </div>
                    <div class="col-md-6">
                        <label for="materials" class="form-label">សម្ភារៈដែលត្រូវការដើម្បីធ្វើការបេសកកម្ម:</label>
                        <input type="text" class="form-control" id="materials" name="materials" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <label for="date_khmer_part1" class="form-label">កាលបរិច្ឆេទខ្មែរ:</label>
                <div class="row">
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="date_khmer_part1" name="date_khmer_part1" placeholder="ថ្ងៃខែឆ្នាំ" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="date_khmer_part2" name="date_khmer_part2" placeholder="ចូលឆ្នាំទំនើប" required>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn-primary">បញ្ចូល</button>
                <a href="mission.php" class="btn-secondary">សូមអភ័យទោស</a>
            </div>
        </form>
    </div>
</body>
</html>
