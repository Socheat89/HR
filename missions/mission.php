<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../admin/includes/db.php';

// Filters and initial variables
$filter_date      = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';
$mission_id       = isset($_GET['id'])          ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$missions_on_date = []; // Variable to hold multiple missions for the dropdown
$data             = null;

// START -  lógica de búsqueda mejorada
// 1. Prioritize fetching by a specific ID (from the name dropdown or direct link)
if ($mission_id) {
    $stmt = $conn->prepare("SELECT * FROM mission_letters WHERE id = :id");
    $stmt->bindParam(':id', $mission_id);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. If no ID, search by date
} elseif ($filter_date) {
    // Fetch all missions on the selected date to check if there are multiple
    $stmt = $conn->prepare("SELECT id, person1, purpose FROM mission_letters WHERE start_date = :date ORDER BY person1 ASC");
    $stmt->bindValue(':date', $filter_date);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($results) === 1) {
        // If only one mission, get its data directly
        $stmt = $conn->prepare("SELECT * FROM mission_letters WHERE id = :id");
        $stmt->bindValue(':id', $results[0]['id']);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif (count($results) > 1) {
        // If multiple missions, prepare the data for the dropdown
        $missions_on_date = $results;
        // Don't load any specific mission data yet, wait for user to select from dropdown
    }
    // If count is 0, $data will remain null and the placeholder will be shown

// 3. If no filters, get the very last mission
} else {
    $stmt = $conn->query("SELECT * FROM mission_letters ORDER BY id DESC LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
}
// END - Lógica de búsqueda mejorada

// Fallback data if no record is found or selected
if (!$data) {
    $data = [
        'location'    => '.......................',
        'purpose'     => '.......................',
        'person1'     => '.......................',
        'role1'       => '.......................',
        'person2'     => '.......................',
        'role2'       => '.......................',
        'person3'     => '.......................',
        'role3'       => '.......................',
        'person4'     => '.......................',
        'role4'       => '.......................',
        'person5'     => '.......................',
        'role5'       => '.......................',
        'start_date'  => '.......................',
        'start_time'  => '.......................',
        'end_date'    => '.......................',
        'end_time'    => '.......................',
        'transport'   => '.......................',
        'materials'   => '.......................',
        'date_khmer'  => '.......................'
    ];
}

function formatTimeToAMPM($time) {
    if (empty($time) || $time === '.......................') return ".......................";
    $dt = new DateTime($time);
    return $dt->format('h:i A');
}

function formatDateToDMY($date) {
    if (empty($date) || $date === '.......................') {
        return ".......................";
    }
    try {
        $dt = new DateTime($date);
        return $dt->format('d-m-Y');
    } catch (Exception $e) {
        return $date;
    }
}
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <title>លិខិតបញ្ជាបេសកម្ម</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Moul&family=Khmer+OS+Battambang:wght@400;700&display=swap');
        
        :root {
            --primary: #4361ee;
            --secondary: #eef2ff;
            --success: #06d6a0;
            --text-main: #2b2d42;
            --text-light: #8d99ae;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --font-kh: 'Khmer OS Battambang', sans-serif;
            --font-moul: 'Moul', cursive;
        }

        body {
            background-color: #f1f5f9;
            font-family: var(--font-kh);
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        /* Control Panel Styling */
        .controls {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1.25rem 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 210mm;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            position: sticky;
            top: 20px;
            z-index: 100;
        }

        .search-group {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-grow: 1;
            flex-wrap: wrap;
        }

        .input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .input-wrapper label {
            font-family: var(--font-kh);
            font-weight: 600;
            color: var(--text-main);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .input-wrapper i {
            color: var(--primary);
        }

        .form-control-custom {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: var(--font-kh);
            color: var(--text-main);
            outline: none;
            transition: all 0.3s ease;
            background: white;
            min-width: 200px;
        }

        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 24px;
            border-radius: 10px;
            font-family: var(--font-kh);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-print {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.25);
        }

        .btn-print:hover {
            background: #3a56d4;
            transform: translateY(-2px);
            box-shadow: 0 8px 12px rgba(67, 97, 238, 0.3);
        }

        .btn-back {
            background: white;
            color: var(--text-main);
            border: 2px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        /* A4 Paper Styling */
        .a4 {
            width: 210mm;
            height: 297mm;
            background: white;
            box-shadow: var(--shadow-md);
            margin: 0 auto;
            padding: 12mm;
            position: relative;
            transform-origin: top center;
        }

        /* KEEPING USER'S ORIGINAL A4 STYLES EXACTLY AS IS BELOW */
        .Missionstatement{ text-align: center; position: relative; top: -7rem; }
        .sub-header { margin-top: -70px; }
        .header img { width: 130px; }
        h5 { text-align: center; font-size: 16px; margin: 0; line-height: 1.5; }
        .tacteing-text { font-family: 'Tacteing', 'Khmer OS Battambang'; text-align: center; color: goldenrod; font-size: 20px; margin: 8px 0; }
        .text1 { font-size: 16px; position: relative; margin: 10px 0; line-height: 1.5; }
        strong { margin-left: 50px; }
        .text1 span { font-size: 16px; position: relative; left: 0; }
        .main-content1 { margin-top: 15px; padding-bottom: 60px; }
        .main-content1 .item { display: flex; align-items: flex-start; margin: 8px 0; font-family: 'Khmer OS Battambang'; font-size: 17px; }
        .main-content1 .person, .main-content1 .role { min-width: 50%; }
        .main-content1 .label { font-weight: bold; }
        .signature { text-align: right; margin-top: 20px; }
        .signature .inner { display: inline-block; text-align: center; }
        .signature p { margin: 5px 0; font-family: 'Koulen'; line-height: 1.5; }
        .signature .date { font-family: 'Khmer OS Battambang'; }
        .signature .name { font-family: 'kh Muol'; font-size: 18px; margin-top: 80px; }
        footer { text-align: center; position: relative; top: 5rem; }
        footer hr { border: 1px solid gold; opacity: 100%; margin: 0; }
        footer span { font-size: 10px; color: gold; font-family: 'Khmer OS Battambang'; margin: 5px 0 0; font-weight: bold; }
        footer p { font-size: 8px; color: rgb(0, 0, 0); font-family: 'Khmer OS Battambang'; margin: 5px 0 0; font-weight: bold; }
        .item span { left: 50px; position: relative; }
        .p { position: relative; top: 20px; left: 60px; }
        .down { margin-top: 3rem; }
        .item .transport { position: relative; left: 100px; }
        .datetime-container { display: flex; align-items: baseline; flex-wrap: wrap; }
        .date-info { min-width: 280px; display: inline-block; }
        .time-info { display: inline-block; }
        .item.p { margin-bottom: 10px; }
        .label { font-weight: bold; margin-right: 8px; }

        @media print { 
            @page { size: A4; margin: 0; } 
            body { margin: 0; padding: 0; background-color: #fff; } 
            .controls { display: none !important; } 
            .a4 { width: 100%; height: 100%; margin: 0 auto; padding: 12mm; box-sizing: border-box; box-shadow: none; page-break-after: always; }
            footer { text-align: center; position: relative; top: 7rem; } 
            .footer-bottom { position: relative; top: 5rem; text-align: center; } 
        }
        @font-face { font-family: 'Tacteing'; src: url('/font/Tacteing.ttf'); } @font-face { font-family: 'Koulen'; src: url('/font/Koulen.ttf'); } @font-face { font-family: 'Khmer OS Battambang'; src: url('/font/KhmerOSBattambang.ttf'); } @font-face { font-family: 'kh Muol'; src: url('/font/KhMuol.ttf'); }
    </style>
</head>

<body>
    <div class="controls">
        <form method="GET" id="searchForm" class="search-group">
            <div class="input-wrapper">
                <label for="filter_date"><i class="far fa-calendar-alt"></i> កាលបរិច្ឆេទ:</label>
                <input type="date" id="filter_date" name="filter_date" class="form-control-custom" value="<?= htmlspecialchars($filter_date) ?>">
            </div>

            <?php if (!empty($missions_on_date)): ?>
                <div class="input-wrapper">
                    <label for="mission_id"><i class="fas fa-list"></i> បេសកកម្ម:</label>
                    <select name="id" id="mission_id" class="form-control-custom">
                        <option value="">-- សូមជ្រើសរើស --</option>
                        <?php foreach ($missions_on_date as $mission): ?>
                            <option value="<?= $mission['id'] ?>" <?= ($mission_id == $mission['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($mission['person1']) ?> (<?= htmlspecialchars($mission['purpose']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </form>

        <div class="action-buttons">
            <a href="input_mission.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
            </a>
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> បោះពុម្ពឯកសារ
            </button>
            <button onclick="takeScreenshot()" class="btn btn-print" style="background: #2a9d8f;">
                <i class="fas fa-copy"></i> ចម្លងរូបភាព
            </button>
        </div>
    </div>

    <div class="a4">
        <div class="header">
            <h5 style="font-family:'Moul'; color:#0531aa; font-size: 20px;">
                ព្រះរាជាណាចក្រកម្ពុជា<br>ជាតិ សាសនា ព្រះមហាក្សត្រ
            </h5>
            <p class="tacteing-text">v?v</p>
            <img src="https://i.ibb.co/hdy8JSv/Logo-Van-Van-1.png" alt="logo">
            <div class="Missionstatement">
                <h5 style="font-family:'Koulen';">លិខិតបញ្ជាបេសកម្ម</h5>
                <p class="tacteing-text">3</p>
            </div>
        </div>
        <div class="sub-header"> 
            <span class="text1">
                <strong style="font-family:'Koulen';">
                    អគ្គនាយិកា ក្រុមហ៊ុន វណ្ណ វណ្ច ខេមបូឌា
                </strong> បានសម្រេចចាត់តាំងបុគ្គលិកដែលមានរាយនាមដូចខាងក្រោម
                ចុះបំពេញ<span>បេសកកម្មនៅ៖
                    <?= htmlspecialchars($data['location']) ?>
                    ដើម្បី
                    <?= htmlspecialchars($data['purpose']) ?>
                </span>
            </span>

            <div class="main-content1">
                <!-- Persons & Roles -->
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php 
                    $person = !empty($data["person$i"]) ? $data["person$i"] : '.......................';
                    $role   = !empty($data["role$i"])   ? $data["role$i"]   : '.......................';
                ?>
                <div class="item">
                    <div class="person">
                        <span class="label">
                            <?= $i ?>. លោក-លោកស្រី៖
                        </span>
                        <span class="value">
                            <?= htmlspecialchars($person) ?>
                        </span>
                    </div>
                    <div class="role">
                        <span class="label">តួនាទី៖</span>
                        <span class="value">
                            <?= htmlspecialchars($role) ?>
                        </span>
                    </div>
                </div>
                <?php endfor; ?>

                <!-- Travel Info -->
                <div class="item p datetime-container">
                    <div class="date-info">
                        <span class="label">- ថ្ងៃចេញដំណើរ៖</span>
                        <span class="value">
                            <?= htmlspecialchars(formatDateToDMY($data['start_date'])) ?>
                        </span>
                    </div>
                    <div class="time-info">
                        <span class="label">- ម៉ោងចេញដំណើរ៖</span>
                        <span class="value">
                            <?= formatTimeToAMPM($data['start_time']) ?>
                        </span>
                    </div>
                </div>
                <div class="item p datetime-container">
                    <div class="date-info">
                        <span class="label">- ថ្ងៃត្រឡប់មកវិញ៖</span>
                        <span class="value">
                            <?= htmlspecialchars(formatDateToDMY($data['end_date'])) ?>
                        </span>
                    </div>
                    <div class="time-info">
                        <span class="label">- ម៉ោងត្រឡប់មកវិញ៖</span>
                        <span class="value">
                            <?= formatTimeToAMPM($data['end_time']) ?>
                        </span>
                    </div>
                </div>
                <div class="item p">
                    <span class="label">- មធ្យបាយធ្វើដំណើរ៖</span>
                    <span class="value">
                        <?= htmlspecialchars($data['transport']) ?>
                    </span>
                </div>
                <div class="item p">
                    <span class="label">- សម្ភារៈភ្ជាប់ជាមួយ៖</span>
                    <span class="value">
                        <?= htmlspecialchars($data['materials']) ?>
                    </span>
                </div>

                <div class="down">
                    <p>អាស្រ័យដូចបានជម្រាបមកខាងលើ សូមបុគ្គលិកដែលពាក់ព័ន្ធទាំងអស់ ជួយសម្រួលការចុះបេសកកម្មនេះ ដោយក្តី អនុគ្រោះ។</p>
                    <div class="signature">
                        <div class="inner">
                            <p class="date">
                                <?php
                                $date_khmer = htmlspecialchars($data['date_khmer']);
                                if ($date_khmer === '.......................') {
                                    echo $date_khmer;
                                } else {
                                    if (strpos($date_khmer, 'br') !== false) {
                                        $parts = explode('br', $date_khmer);
                                        echo trim($parts[0]) . '<br>' . trim($parts[1]);
                                    } else {
                                        echo $date_khmer;
                                    }
                                }
                                ?>
                            </p>
                            <p>ជ.អគ្គនាយិកា<br>ប្រធាននាយកដ្ឋានធនធានមនុស្ស និងរដ្ឋបាល</p>
                            <p class="name">ផល ស៊ាងឡេង</p>
                        </div>
                    </div>
                </div>

                <footer class="footer-bottom">
                    <hr>
                    <span>ផ្ទះលេខ 1 AEo ផ្លូវលេខ 318 សង្កាត់ ទួលស្វាយព្រៃ១ ខណ្ឌ បឹងកេងកង រាជធានីភ្នំពេញ
                        ព្រះរាជាណាចក្រកម្ពុជា ទូរស័ព្ទលេខ 015 971 961-085 971 961</span>
                    <p>No.IAEo, St.318, Sangkat Tuol Svay Prey!, Khan Beong Keng kong, Phnom Penh, Cambodia. Tell: 015 971
                        961-085 971 961 ទូរស័ព្ទលេខ 015 971 961-085 971 961</p>
                </footer>
            </div>
        </div>
    </div>

    <!-- START បន្ថែម៖ JavaScript សម្រាប់ស្វែងរកដោយស្វ័យប្រវត្តិ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.getElementById('searchForm');
            const dateInput = document.getElementById('filter_date');
            const missionSelect = document.getElementById('mission_id');

            // ពេលដែលកាលបរិច្ឆេទត្រូវបានផ្លាស់ប្តូរ, បញ្ជូន Form ដោយស្វ័យប្រវត្តិ
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    searchForm.submit();
                });
            }

            // ពេលដែលបេសកកម្មត្រូវបានជ្រើសរើស, បញ្ជូន Form ដោយស្វ័យប្រវត្តិ
            // យើងត្រូវពិនិត្យមើលថាតើ element នេះមានឬអត់ ព្រោះវាត្រូវបានបង្ហាញតាមលក្ខខណ្ឌ
            if (missionSelect) {
                missionSelect.addEventListener('change', function() {
                    searchForm.submit();
                });
            }
        });

        function takeScreenshot() {
            const element = document.querySelector('.a4');
            const originalTransform = element.style.transform;
            
            // Reset transform to ensure full capture
            element.style.transform = 'none';
            
            html2canvas(element, {
                scale: 4, // Ultra high quality
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                // Restore original style
                element.style.transform = originalTransform;
                
                canvas.toBlob(function(blob) {
                    if (blob) {
                        try {
                            const item = new ClipboardItem({ "image/png": blob });
                            navigator.clipboard.write([item]).then(() => {
                                alert('រូបភាពត្រូវបានចម្លងទុកក្នុង Clipboard! អ្នកអាច Paste វាបាន។');
                            }).catch(err => {
                                console.error('Clipboard write failed:', err);
                                alert('បរាជ័យក្នុងការចម្លងរូបភាព។ សូមព្យាយាមម្តងទៀត។');
                            });
                        } catch (error) {
                            console.error('Clipboard API error:', error);
                            alert('Browser របស់អ្នកមិនគាំទ្រការចម្លងរូបភាពដោយផ្ទាល់ទេ។');
                        }
                    }
                }, 'image/png');
            }).catch(err => {
                console.error('Screenshot failed:', err);
                alert('បរាជ័យក្នុងការថតរូបភាព។ សូមព្យាយាមម្តងទៀត។');
            });
        }
    </script>
    <!-- END បន្ថែម -->
    
</body>
</html>