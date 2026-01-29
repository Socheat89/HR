<?php
session_start();
// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once 'admin/includes/db.php'; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $base_columns = ['location', 'purpose', 'start_date', 'start_time', 'end_date', 'end_time', 'transport', 'materials', 'date_khmer'];
        $params = [];

        // Build base parameters
        foreach ($base_columns as $col) {
            if ($col === 'date_khmer') {
                $date_khmer_part1 = trim($_POST['date_khmer_part1'] ?? '');
                $date_khmer_part2 = trim($_POST['date_khmer_part2'] ?? '');
                $params[':date_khmer'] = $date_khmer_part1 . 'br' . $date_khmer_part2;
            } else {
                $params[':' . $col] = trim($_POST[$col] ?? '');
            }
        }
        
        // Dynamically add personnel
        $personnel_columns = [];
        // Assuming a max of 10 people for safety. Adjust if needed.
        for ($i = 1; $i <= 10; $i++) {
            // FIXED: Use isset() to prevent "Undefined index" notice for persons not submitted.
            // This notice was causing the HTML error that broke the JSON response.
            if (isset($_POST["person$i"]) && !empty(trim($_POST["person$i"]))) {
                $personnel_columns[] = "person$i";
                $personnel_columns[] = "role$i";
                $params[":person$i"] = trim($_POST["person$i"]);
                $params[":role$i"] = trim($_POST["role$i"] ?? '');
            }
        }

        $all_columns = array_merge($base_columns, $personnel_columns);
        $sql_columns = implode(', ', $all_columns);
        $sql_placeholders = implode(', ', array_keys($params));

        $sql = "INSERT INTO mission_letters ($sql_columns) VALUES ($sql_placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Data inserted successfully.']);
        exit;
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database operation failed: ' . $e->getMessage()]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ចូលទិន្នន័យបេសកកម្ម</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Koulen&display=swap" rel="stylesheet">
    <!-- Bootstrap and Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --c-primary: #4A55A2;
            --c-accent: #7895CB;
            --c-text-dark: #1F2937;
            --c-text-light: #6B7280;
            --c-bg: #F3F4F6;
            --c-surface: #FFFFFF;
            --c-border: #D1D5DB;
            --c-shadow: rgba(74, 85, 162, 0.15);
            --c-secondary-start: #6B7280;
            --c-secondary-end: #4B5563;
            --c-success: #10B981; /* Green for add button */
        }
        
        body {
            font-family: 'Battambang', sans-serif;
            background-color: var(--c-bg);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .form-container {
            max-width: 900px;
            width: 100%;
            padding: 40px;
            background: var(--c-surface);
            border-radius: 16px;
            box-shadow: 0 10px 30px var(--c-shadow);
            border-top: 6px solid var(--c-primary);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-8px);
            box-shadow: 0 18px 45px var(--c-shadow);
        }

        h3 {
            font-family: 'Koulen', sans-serif;
            color: var(--c-primary);
            text-align: center;
            margin-bottom: 35px;
            font-size: 2.5rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        h3::after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background: var(--c-accent);
            margin: 10px auto 0;
            border-radius: 2px;
            transition: width 0.3s ease-in-out;
        }
        
        .form-container:hover h3::after {
            width: 120px;
        }

        label {
            color: var(--c-text-dark);
            font-weight: 700;
            margin-bottom: 8px;
            display: block;
            font-size: 1rem;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--c-border);
            padding: 12px 15px 12px 45px;
            font-size: 1rem;
            background-color: var(--c-bg);
            transition: all 0.3s ease;
            width: 100%;
            font-family: 'Battambang', sans-serif;
            color: var(--c-text-dark);
        }

        .form-control:focus {
            border-color: var(--c-primary);
            box-shadow: 0 0 0 4px rgba(74, 85, 162, 0.2);
            background-color: var(--c-surface);
            outline: none;
        }
        
        .form-control::placeholder {
            color: var(--c-text-light);
            opacity: 0.8;
        }

        .input-group i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: var(--c-text-light);
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .form-control:focus + i {
            color: var(--c-primary);
        }
        
        .form-section {
            border-bottom: 1px dashed #e0e7ff;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .form-section:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            gap: 1rem;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Battambang', sans-serif;
            font-weight: 700;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--c-primary), var(--c-accent));
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px var(--c-shadow);
            color: #fff;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--c-secondary-start), var(--c-secondary-end));
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(108, 117, 125, 0.25);
            color: #fff;
        }
        
        .btn-add {
            background: var(--c-success);
            padding: 8px 15px;
            font-size: 0.9rem;
        }
        .btn-add:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        .personnel-group {
            position: relative;
            padding-right: 50px;
        }
        .btn-remove {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            background: #EF4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-remove:hover {
            background: #DC2626;
        }
        
        #loading-popup {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        #loading-popup.show {
            display: flex;
            opacity: 1;
        }
        .popup-content {
            background-color: white;
            padding: 30px 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .popup-content .fa-spinner {
            font-size: 2rem;
            color: var(--c-primary);
        }
        .popup-content p {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--c-text-dark);
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h3>បញ្ចូលទិន្នន័យបេសកកម្ម</h3>
        <form id="mission-form" method="POST" action="">
            <div class="form-section">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="location" class="form-label">ទីតាំង</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="location" name="location" required>
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="purpose" class="form-label">គោលបំណង</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="purpose" name="purpose" required>
                            <i class="fas fa-bullseye"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                 <!-- Person 1 -->
                 <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="person1" class="form-label">លោក-លោកស្រី ១</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="person1" name="person1" required>
                             <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="role1" class="form-label">តួនាទី ១</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="role1" name="role1" required>
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>
                <!-- Person 2 -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="person2" class="form-label">លោក-លោកស្រី ២</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="person2" name="person2">
                             <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="role2" class="form-label">តួនាទី ២</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="role2" name="role2">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>
                <!-- Person 3 -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="person3" class="form-label">លោក-លោកស្រី ៣</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="person3" name="person3">
                             <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="role3" class="form-label">តួនាទី ៣</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="role3" name="role3">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>

                <!-- Container for additional personnel (person 4 onwards) -->
                <div id="personnel-container"></div>

                <!-- Add Person Button -->
                <div class="text-start mt-2">
                    <button type="button" id="add-person-btn" class="btn btn-add">
                        <i class="fas fa-plus"></i> បន្ថែមបុគ្គលិក
                    </button>
                </div>
            </div>

            <div class="form-section">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="start_date" class="form-label">កាលបរិច្ឆេទចេញដំណើរ</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="start_time" class="form-label">ម៉ោងចេញដំណើរ</label>
                        <div class="input-group">
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                             <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="end_date" class="form-label">កាលបរិច្ឆេទត្រឡប់មកវិញ</label>
                        <div class="input-group">
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="end_time" class="form-label">ម៉ោងត្រឡប់មកវិញ</label>
                        <div class="input-group">
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="transport" class="form-label">មធ្យោបាយធ្វើដំណើរ</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="transport" name="transport" required>
                             <i class="fas fa-car"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="materials" class="form-label">សម្ភារៈភ្ជាប់ជាមួយ</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="materials" name="materials">
                            <i class="fas fa-box"></i>
                        </div>
                    </div>
                </div>
                 <div class="row g-3">
                    <div class="col-md-6">
                        <label for="date_khmer_part1" class="form-label">កាលបរិច្ឆេទខ្មែរ (ផ្នែកទី១)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="date_khmer_part1" name="date_khmer_part1" placeholder="ឧ. ថ្ងៃពុធ ៧កើត ខែផល្គុន ឆ្នាំរោង..." required>
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="date_khmer_part2" class="form-label">កាលបរិច្ឆេទ (ផ្នែកទី២)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="date_khmer_part2" name="date_khmer_part2"
                                value="រាជធានីភ្នំពេញ​, ថ្ងៃទី​  ខែ   ឆ្នាំ២០២"
                                placeholder="ឧ. រាជធានីភ្នំពេញ​, ថ្ងៃទី៥ ខែមីនា ឆ្នាំ២០២៥" required>
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <a href="mission.php" class="btn btn-secondary"><i class="fas fa-list"></i> មើលបញ្ជីបេសកកម្ម</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> បញ្ចូលទិន្នន័យ</button>
            </div>
        </form>
    </div>

    <div id="loading-popup">
        <div class="popup-content">
            <i class="fas fa-spinner fa-spin"></i>
            <p>កំពុងបញ្ជូនទិន្នន័យ សូមរង់ចាំ...</p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addPersonBtn = document.getElementById('add-person-btn');
        const personnelContainer = document.getElementById('personnel-container');
        let personCount = 3; // Start count from 3 because we have 3 people by default

        addPersonBtn.addEventListener('click', function() {
            personCount++;
            
            const personnelGroup = document.createElement('div');
            personnelGroup.classList.add('row', 'g-3', 'mb-3', 'personnel-group');
            
            personnelGroup.innerHTML = `
                <div class="col-md-6">
                    <label for="person${personCount}" class="form-label">លោក-លោកស្រី ${personCount}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="person${personCount}" name="person${personCount}">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="role${personCount}" class="form-label">តួនាទី ${personCount}</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="role${personCount}" name="role${personCount}">
                        <i class="fas fa-briefcase"></i>
                    </div>
                </div>
            `;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-remove';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', function() {
                personnelGroup.remove();
            });
            
            personnelGroup.appendChild(removeBtn);
            
            personnelContainer.appendChild(personnelGroup);
        });

        const form = document.getElementById('mission-form');
        const popup = document.getElementById('loading-popup');

        form.addEventListener('submit', function(event) {
            event.preventDefault();
            popup.classList.add('show');
            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Try to parse error response as JSON, but have a fallback
                    return response.json().then(errData => {
                        throw new Error(errData.message || `Server error: ${response.statusText}`);
                    }).catch(() => {
                        // If the error response isn't JSON, throw a generic error
                        throw new Error(`An unexpected server error occurred (Status: ${response.status}). The response was not in a valid JSON format.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = 'mission.php';
                } else {
                    // This case handles JSON responses that have status: 'error'
                    alert('Error: ' + data.message);
                    popup.classList.remove('show');
                }
            })
            .catch(error => {
                console.error('Submission Error:', error);
                alert('មានបញ្ហាក្នុងការបញ្ជូនទិន្នន័យ។ ' + error.message);
                popup.classList.remove('show');
            });
        });
    });
    </script>
</body>
</html>