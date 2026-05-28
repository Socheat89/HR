<?php
// ../index.php
try {
    date_default_timezone_set('Asia/Phnom_Penh');
    // Set default date to today in Y-m-d format for date input
    $today = date('Y-m-d');
    // Keep tomorrow's format as Y-m-d
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
} catch (Exception $e) {
    error_log("Timezone error: " . $e->getMessage());
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Individual Daily Report Form</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <style>
        /* Import Khmer font */
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap');

        :root {
            --excel-green: #217346;
            --excel-light-green: #e6f2ec;
            --border-color: #d1d5db;
            --header-bg: #f3f4f6;
            --primary-blue: #2563eb;
        }

        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Kantumruy Pro', 'Segoe UI', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px;
            color: #1f2937;
        }

        /* Form container */
        .form-container {
            width: 100%;
            max-width: 1600px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 24px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
        }

        h2 {
            text-align: center;
            color: var(--excel-green);
            margin-bottom: 24px;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        h2::before {
            content: "\f1c3"; /* FontAwesome Excel icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
        }

        /* Header row */
        .header-section {
            background: var(--header-bg);
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .header-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .input-group label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
        }

        .header-row input, .header-row select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.9rem;
            background: #fff;
            width: 100%;
            transition: border-color 0.2s;
        }

        .header-row input:focus, .header-row select:focus {
            border-color: var(--excel-green);
            outline: none;
            box-shadow: 0 0 0 2px rgba(33, 115, 70, 0.1);
        }

        /* Table styles */
        .table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            table-layout: fixed; /* Excel style */
        }

        th {
            background: var(--excel-green);
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 10px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }

        td {
            padding: 0; /* Clear padding for inputs to fill cell */
            border: 1px solid var(--border-color);
            vertical-align: top;
        }

        /* Form elements in table */
        td input, td select, td textarea {
            width: 100%;
            border: none;
            padding: 10px;
            font-size: 0.85rem;
            background: transparent;
            display: block;
            resize: none;
            outline: none;
        }

        td input:focus, td textarea:focus {
            background: #fff;
            box-shadow: inset 0 0 0 2px var(--excel-green);
            z-index: 10;
            position: relative;
        }

        /* specific column widths */
        th:nth-child(1), td:nth-child(1) { width: 90px; } /* Time */
        th:nth-child(2), td:nth-child(2) { width: 250px; } /* Task */
        th:nth-child(3), td:nth-child(3) { width: 100px; } /* Status */
        th:nth-child(4), td:nth-child(4) { width: 130px; } /* Due Date */
        th:nth-child(8), td:nth-child(8) { width: 60px; text-align: center; } /* Action */

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        /* Next day plan section */
        .section-header {
            background: var(--excel-light-green);
            padding: 8px 16px;
            border-left: 4px solid var(--excel-green);
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--excel-green);
        }

        .plan-container {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 16px;
            background: #fff;
            padding: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 24px;
        }

        .plan-container label {
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
        }

        .plan-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            min-height: 120px;
            font-size: 0.9rem;
            outline: none;
        }

        .plan-container textarea:focus {
            border-color: var(--excel-green);
            box-shadow: 0 0 0 2px rgba(33, 115, 70, 0.1);
        }

        /* Action buttons */
        .action-bar {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add {
            background: #fff;
            color: var(--excel-green);
            border-color: var(--excel-green);
        }
        .btn-add:hover { background: var(--excel-light-green); }

        .btn-submit {
            background: var(--excel-green);
            color: white;
        }
        .btn-submit:hover { background: #1a5c38; }

        .btn-clear {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
        }
        .btn-clear:hover { background: #e5e7eb; }

        .btn-remove {
            color: #ef4444;
            background: transparent;
            border: none;
            padding: 8px;
            border-radius: 4px;
            width: 100%;
            height: 100%;
        }
        .btn-remove:hover { background: rgba(239, 68, 68, 0.1); }

        /* Popup messages */
        .popup {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 16px 24px;
            border-radius: 6px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid #ccc;
        }

        .popup.show { transform: translateX(0); }
        .popup.success { border-left-color: #10b981; }
        .popup.error { border-left-color: #ef4444; }

        /* Custom Confirmation Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.show {
            display: flex;
            opacity: 1;
        }
        .confirm-modal {
            background: white;
            padding: 24px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .modal-overlay.show .confirm-modal {
            transform: scale(1);
        }
        .modal-icon {
            width: 60px;
            height: 60px;
            background: #fee2e2;
            color: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px;
        }
        .modal-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }
        .modal-text {
            color: #4b5563;
            font-size: 0.95rem;
            margin-bottom: 24px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn-cancel {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #d1d5db;
        }
        .btn-cancel:hover { background: #e5e7eb; }
        .btn-confirm-delete {
            background: #ef4444;
            color: white;
        }
        .btn-confirm-delete:hover { background: #dc2626; }

        .btn-screenshot {
            background: #2563eb;
            color: white;
        }
        .btn-screenshot:hover { background: #1d4ed8; }

        /* Screenshot Mode - Hide elements we don't want in the photo */
        .screenshot-mode .form-container {
            background: #ffffff !important;
            box-shadow: none !important;
            border: none !important;
            padding: 30px !important;
        }

        .screenshot-mode .header-section {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important; /* Softer border */
            border-radius: 8px !important;
        }

        .screenshot-mode .header-row {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 15px !important;
        }

        .screenshot-mode .input-group label {
            color: #334155 !important; /* Slate color for professional look */
            font-weight: 700 !important;
            margin-bottom: 5px !important;
        }

        /* Hide interactive elements in screenshot */
        .screenshot-mode input,
        .screenshot-mode select,
        .screenshot-mode textarea,
        .screenshot-mode .btn-remove {
            display: none !important;
        }

        .screenshot-value {
            display: none;
            word-wrap: break-word;
            white-space: pre-wrap;
            color: #000000 !important;
            font-size: 0.9rem !important;
            line-height: 1.4 !important;
        }

        .screenshot-mode .screenshot-value {
            display: block !important;
        }

        .screenshot-mode .input-group .screenshot-value {
            padding: 10px;
            border: 1px solid #e2e8f0 !important; /* Very subtle border */
            background: #f8fafc !important; /* Extremely light blue-tinted bg */
            min-height: 42px;
            border-radius: 6px;
            font-weight: 600;
        }

        .screenshot-mode table,
        .screenshot-mode th,
        .screenshot-mode td {
            border: 1px solid #cbd5e1 !important; /* Unified slate border */
        }

        .screenshot-mode td .screenshot-value {
            padding: 12px 10px !important;
            min-height: 45px;
            width: 100%;
            text-align: left;
            border: none !important; /* Let table cell handle the border */
        }

        .screenshot-mode td:first-child .screenshot-value {
            text-align: center;
        }

        .screenshot-mode th {
            background-color: var(--excel-green) !important;
            color: #ffffff !important;
            padding: 12px 5px !important;
            font-weight: 700 !important;
        }

        .screenshot-mode .section-header {
            background-color: #f0fdf4 !important;
            color: #166534 !important;
            border-left: 5px solid #166534 !important;
            font-weight: 700 !important;
            padding: 12px 15px !important;
        }

        /* Preview Modal Styles */
        .preview-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 3000;
        }
        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 12px;
            max-width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            text-align: center;
        }
        #previewImageDisplay {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .preview-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .screenshot-mode .action-bar,
        .screenshot-mode .btn-remove,
        .screenshot-mode .btn-screenshot,
        .screenshot-mode #pullPreviousBtn,
        .screenshot-mode #messagePopup,
        .screenshot-mode .confirm-modal,
        .screenshot-mode .modal-overlay,
        .screenshot-mode #pull-previous-report {
            display: none !important;
        }

        /* Responsive design */
        @media screen and (max-width: 768px) {
            .plan-container {
                grid-template-columns: 1fr;
            }
            .action-bar {
                flex-wrap: wrap;
                justify-content: center;
            }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>
    <div class="form-container">
        <h2> Individual Daily Report</h2>
        
        <div class="header-section">
            <div class="header-row">
                <div class="input-group">
                    <label>កាលបរិច្ឆេទរបាយការណ៍</label>
                    <input type="date" id="reportDate" value="<?php echo $today; ?>">
                    <div class="screenshot-value" id="val-reportDate"></div>
                </div>
                <div class="input-group">
                    <label>ឈ្មោះបុគ្គលិក</label>
                    <input type="text" id="name" placeholder="ឈ្មោះ" required>
                    <div class="screenshot-value" id="val-name"></div>
                </div>
                <div class="input-group">
                    <label>តួនាទី</label>
                    <select id="position" required>
                        <option value="" disabled selected>ជ្រើសរើសតួនាទី</option>
                        <option value="IT SUPPORT">IT SUPPORT</option>
                        <option value="IT MANAGER">IT MANAGER</option>
                        <option value="Administration">រដ្ឋបាលទូទៅ</option>
                        <option value="Brand Supervisor">ប្រធានគ្រប់គ្រងម៉ាកផលិផល</option>
                    </select>
                    <div class="screenshot-value" id="val-position"></div>
                </div>
                <div class="input-group">
                    <label>ផ្នែក</label>
                    <select id="department" required>
                        <option value="" disabled selected>ជ្រើសរើសផ្នែក</option>
                        <option value="ព័ត៌មានវិទ្យា">ផ្នែកព័ត៌មានវិទ្យា</option>
                        <option value="ប្រធានផ្នែកព័ត៌មានវិទ្យា">ប្រធានផ្នែកព័ត៌មានវិទ្យា</option>
                        <option value="រដ្ឋបាលទូទៅ">រដ្ឋបាលទូទៅ</option>
                        <option value="លក់">ប្រធានគ្រប់គ្រងម៉ាកផលិផល</option>
                    </select>
                    <div class="screenshot-value" id="val-department"></div>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="taskTable">
                <thead>
                    <tr>
                        <th>ម៉ោង</th>
                        <th>កិច្ចការ / បញ្ហា</th>
                        <th>ស្ថានភាព</th>
                        <th>កាលបរិច្ឆេទកំណត់</th>
                        <th>ពិពណ៌នា</th>
                        <th>បញ្ហា</th>
                        <th>ដំណោះស្រាយ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="taskBody">
                    <!-- Initial rows populated by JS -->
                </tbody>
            </table>
        </div>

        <div class="section-header">
            <i class="fas fa-calendar-alt me-2"></i> ផែនការសម្រាប់ថ្ងៃបន្ទាប់
        </div>
        <div class="plan-container">
            <div class="input-group">
                <label>កាលបរិច្ឆេទផែនការ</label>
                <input type="date" id="nextPlanDate" value="<?php echo $tomorrow; ?>" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                <div class="screenshot-value" id="val-nextPlanDate"></div>
            </div>
            <div class="input-group">
                <label>ព័ត៌មានលម្អិតអំពីផែនការ</label>
                <textarea id="nextPlanDetails" placeholder="សរសេរផែនការរបស់អ្នកនៅទីនេះ..."></textarea>
                <div class="screenshot-value" id="val-nextPlanDetails" style="min-height: 100px;"></div>
            </div>
        </div>

        <div class="action-bar">
            <button type="button" class="btn btn-clear" onclick="clearFormData()">
                <i class="fas fa-undo"></i> សម្អាតទម្រង់
            </button>
            <button type="button" class="btn btn-add" onclick="addRow()">
                <i class="fas fa-plus"></i> បន្ថែមជួរ
            </button>
            <button type="button" class="btn btn-screenshot" id="screenshotBtn" onclick="takeScreenshot()">
                <i class="fas fa-camera"></i> ថតរូប (Screenshot)
            </button>
            <button type="submit" class="btn btn-submit" id="submitBtn" onclick="submitForm()">
                <i class="fab fa-telegram-plane"></i> ផ្ញើទៅ Telegram
            </button>
        </div>
    </div>

    <!-- Preview Modal -->
    <div id="previewOverlay" class="preview-overlay">
        <div class="preview-content">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-eye"></i> ពិនិត្យមើលរូបភាពរបាយការណ៍បច្ចុប្បន្ន</h3>
            <img id="previewImageDisplay" src="" alt="Report Preview">
            <div class="preview-actions">
                <button class="btn btn-cancel" onclick="closePreview()">បោះបង់</button>
                <button class="btn btn-screenshot" id="finalSubmitBtn">
                    <i class="fas fa-paper-plane"></i> បញ្ជូនទៅ Telegram
                </button>
            </div>
        </div>
    </div>

    <div id="messagePopup" class="popup">
        <div style="display: flex; align-items: center; gap: 12px;">
            <i id="popupIcon" class="fas" style="font-size: 1.25rem;"></i>
            <div>
                <div id="popupTitle" style="font-weight: 700; margin-bottom: 2px;"></div>
                <div id="popupMessage" style="font-size: 0.85rem; color: #4b5563;"></div>
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="confirmModal" class="modal-overlay">
        <div class="confirm-modal">
            <div class="modal-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="modal-title">ការបញ្ជាក់លុប</div>
            <div class="modal-text" id="confirmModalText">តើអ្នកប្រាកដជាចង់លុបជួរនេះមែនទេ?</div>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeConfirmModal()">បោះបង់</button>
                <button type="button" class="btn btn-confirm-delete" id="confirmDeleteBtn">សម្រេចចិត្តលុប</button>
            </div>
        </div>
    </div>

    <script>
        // Function to save form data to localStorage
        function saveFormData() {
            const formData = {
                reportDate: document.getElementById('reportDate').value,
                name: document.getElementById('name').value,
                position: document.getElementById('position').value,
                department: document.getElementById('department').value,
                nextPlanDate: document.getElementById('nextPlanDate').value,
                nextPlanDetails: document.getElementById('nextPlanDetails').value,
                tasks: []
            };

            const rows = document.getElementById('taskBody').getElementsByTagName('tr');
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                formData.tasks.push({
                    time: cells[0].querySelector('input, textarea').value,
                    task: cells[1].querySelector('input, textarea').value,
                    status: cells[2].querySelector('input, textarea').value,
                    dueDate: cells[3].querySelector('input, textarea').value,
                    description: cells[4].querySelector('textarea').value,
                    problem: cells[5].querySelector('textarea').value,
                    solution: cells[6].querySelector('textarea').value
                });
            }

            localStorage.setItem('dailyReportFormData', JSON.stringify(formData));
        }

        // Function to load form data from localStorage
        function loadFormData() {
            const savedData = localStorage.getItem('dailyReportFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);

                // Load header fields
                document.getElementById('name').value = formData.name || '';
                document.getElementById('position').value = formData.position || '';
                document.getElementById('department').value = formData.department || '';
                document.getElementById('nextPlanDetails').value = formData.nextPlanDetails || '';

                // ======================================================================
                // === កន្លែងដែលបានកែប្រែ៖ ចាប់ផ្តើមពិនិត្យ និងបម្លែងទម្រង់កាលបរិច្ឆេទ ===
                // ======================================================================
                
                // Function to convert date from M/D/YYYY to YYYY-MM-DD
                const reformatDate = (dateString) => {
                    if (!dateString) return null;
                    // If it's already in the correct format, return it
                    if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        return dateString;
                    }
                    // If it's in the old m/d/Y format, convert it
                    if (dateString.includes('/')) {
                        const parts = dateString.split('/');
                        if (parts.length === 3) {
                            const month = parts[0].padStart(2, '0');
                            const day = parts[1].padStart(2, '0');
                            const year = parts[2];
                            return `${year}-${month}-${day}`;
                        }
                    }
                    // If format is unrecognized, return null to use the default
                    return null;
                };

                const formattedReportDate = reformatDate(formData.reportDate);
                const formattedNextPlanDate = reformatDate(formData.nextPlanDate);

                document.getElementById('reportDate').value = formattedReportDate || '<?php echo $today; ?>';
                document.getElementById('nextPlanDate').value = formattedNextPlanDate || '<?php echo $tomorrow; ?>';

                // ======================================================================
                // === ចប់ការកែប្រែ ===
                // ======================================================================

                // Load tasks
                const tbody = document.getElementById('taskBody');
                tbody.innerHTML = ''; // Clear existing rows
                
                formData.tasks.forEach(task => {
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                        <td><input type="time" value="${task.time || ''}"><div class="screenshot-value"></div></td>
                        <td><textarea rows="1">${task.task || ''}</textarea><div class="screenshot-value"></div></td>
                        <td><input type="text" placeholder="100%" value="${task.status || ''}"><div class="screenshot-value"></div></td>
                        <td><input type="date" value="${task.dueDate || ''}"><div class="screenshot-value"></div></td>
                        <td><textarea rows="1">${task.description || ''}</textarea><div class="screenshot-value"></div></td>
                        <td><textarea rows="1">${task.problem || ''}</textarea><div class="screenshot-value"></div></td>
                        <td><textarea rows="1">${task.solution || ''}</textarea><div class="screenshot-value"></div></td>
                        <td><button class="btn-remove" onclick="removeRow(this)"><i class="fas fa-trash-alt"></i></button></td>
                    `;
                    tbody.appendChild(newRow);
                });

                // Ensure at least one row exists
                if (formData.tasks.length === 0) {
                    addRow();
                }
            } else {
                // If no saved data, add one initial row and set default nextPlanDetails with dash
                addRow();
                document.getElementById('nextPlanDetails').value = '- ';
            }
        }

        // Function to add a new row to the task table
        function addRow() {
            const tbody = document.getElementById('taskBody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="time"><div class="screenshot-value"></div></td>
                <td><textarea rows="1"></textarea><div class="screenshot-value"></div></td>
                <td><input type="text" placeholder="100%"><div class="screenshot-value"></div></td>
                <td><input type="date"><div class="screenshot-value"></div></td>
                <td><textarea rows="1"></textarea><div class="screenshot-value"></div></td>
                <td><textarea rows="1"></textarea><div class="screenshot-value"></div></td>
                <td><textarea rows="1"></textarea><div class="screenshot-value"></div></td>
                <td><button class="btn-remove" onclick="removeRow(this)"><i class="fas fa-trash-alt"></i></button></td>
            `;
            tbody.appendChild(newRow);
            saveFormData();
        }

        // Function to remove a row from the task table
        function removeRow(button) {
            const tbody = document.getElementById('taskBody');
            const rowCount = tbody.getElementsByTagName('tr').length;
            
            if (rowCount > 1) {
                showConfirmModal('តើអ្នកប្រាកដជាចង់លុបជួរនេះមែនទេ?', () => {
                    button.parentElement.parentElement.remove();
                    saveFormData();
                });
            } else {
                showPopup('មតិយោបល់', 'មិនអាចលុបជួរចុងក្រោយបានទេ!', false);
            }
        }

        // Custom Confirm Modal Logic
        let confirmAction = null;
        function showConfirmModal(message, onConfirm) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmModalText').textContent = message;
            modal.classList.add('show');
            confirmAction = onConfirm;
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('show');
            confirmAction = null;
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', () => {
            if (confirmAction) {
                confirmAction();
                closeConfirmModal();
            }
        });

        // Function to show popup messages
        function showPopup(title, message, isSuccess) {
            const popup = document.getElementById('messagePopup');
            const titleElement = document.getElementById('popupTitle');
            const messageElement = document.getElementById('popupMessage');
            const iconElement = document.getElementById('popupIcon');

            titleElement.textContent = title;
            messageElement.textContent = message;
            
            popup.className = 'popup';
            popup.classList.add(isSuccess ? 'success' : 'error');
            iconElement.className = isSuccess ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-circle text-danger';
            
            popup.classList.add('show');

            setTimeout(hidePopup, 3000);
        }

        // Function to hide popup
        function hidePopup() {
            const popup = document.getElementById('messagePopup');
            popup.classList.remove('show');
        }

        // Function to submit the form data to Telegram with Preview
        async function submitForm() {
            const reportDate = document.getElementById('reportDate').value;
            const name = document.getElementById('name').value;
            const position = document.getElementById('position').value;
            const department = document.getElementById('department').value;
            const nextPlanDate = document.getElementById('nextPlanDate').value;
            const nextPlanDetails = document.getElementById('nextPlanDetails').value;

            if (!reportDate || !name || !position || !department) {
                showPopup('បរាជ័យ', 'សូមបំពេញរាល់វាលដែលត្រូវការ!', false);
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            const originalContent = submitBtn.innerHTML;
            const container = document.querySelector('.form-container');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងថតរូបភាព...';

            prepareScreenshotValues();

            // 1. Take Screenshot for Preview
            container.classList.add('screenshot-mode');
            
            try {
                const canvas = await html2canvas(container, {
                    scale: 1.5, // Reduced scale for smaller payload
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    imageSmoothingEnabled: true
                });
                
                // Use JPEG for better compression
                const imageData = canvas.toDataURL('image/jpeg', 0.8);
                
                // Show Preview
                document.getElementById('previewImageDisplay').src = imageData;
                document.getElementById('previewOverlay').style.display = 'flex';
                
                // Prepare the final submission button
                const finalBtn = document.getElementById('finalSubmitBtn');
                finalBtn.onclick = async () => {
                    finalBtn.disabled = true;
                    finalBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងផ្ញើ...';
                    
                    const reportData = {
                        date: reportDate,
                        name: name,
                        position: position,
                        department: department,
                        next_plan_date: nextPlanDate,
                        next_plan_details: nextPlanDetails,
                        image: imageData,
                        tasks: []
                    };

                    const rows = document.getElementById('taskBody').getElementsByTagName('tr');
                    for (let row of rows) {
                        const cells = row.getElementsByTagName('td');
                        const timeElement = cells[0].querySelector('input, textarea');
                        const taskElement = cells[1].querySelector('input, textarea');
                        
                        if (taskElement && taskElement.value.trim() !== '') {
                            reportData.tasks.push({
                                time: timeElement ? timeElement.value : '-',
                                task: taskElement.value,
                                status: cells[2].querySelector('input, textarea').value,
                                dueDate: cells[3].querySelector('input, textarea').value,
                                description: cells[4].querySelector('textarea').value,
                                problem: cells[5].querySelector('textarea').value,
                                solution: cells[6].querySelector('textarea').value
                            });
                        }
                    }

                    try {
                        const response = await fetch('../system/send_to_telegram.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(reportData)
                        });
                        const result = await response.json();
                        
                        if (result.success) {
                            showPopup('ជោគជ័យ', 'បានផ្ញើទៅ Telegram រួចរាល់!', true);
                            closePreview();
                            saveFormData();
                        } else {
                            showPopup('បរាជ័យ', result.message, false);
                            finalBtn.disabled = false;
                            finalBtn.innerHTML = '<i class="fas fa-paper-plane"></i> បញ្ជូនទៅ Telegram';
                        }
                    } catch (err) {
                        showPopup('កំហុស', err.message, false);
                        finalBtn.disabled = false;
                    }
                };

            } catch (err) {
                console.error('Screenshot error:', err);
                showPopup('កំហុស', 'មិនអាចថតរូបភាពបានទេ!', false);
            } finally {
                container.classList.remove('screenshot-mode');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalContent;
            }
        }

        function closePreview() {
            document.getElementById('previewOverlay').style.display = 'none';
        }

        // Function to take screenshot and copy to clipboard
        async function takeScreenshot() {
            const btn = document.getElementById('screenshotBtn');
            const originalContent = btn.innerHTML;
            const container = document.querySelector('.form-container');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងថត...';

            prepareScreenshotValues();

            // Add screenshot mode to hide unwanted elements
            container.classList.add('screenshot-mode');

            try {
                const canvas = await html2canvas(container, {
                    scale: 3, // Increased scale for better clarity
                    useCORS: true,
                    backgroundColor: '#ffffff',
                    logging: false,
                    imageSmoothingEnabled: true,
                    onclone: (clonedDoc) => {
                        // Extra styling for clone if needed
                    }
                });

                canvas.toBlob(async (blob) => {
                    try {
                        const item = new ClipboardItem({ "image/png": blob });
                        await navigator.clipboard.write([item]);
                        showPopup('ជោគជ័យ', 'បានថតរូប និងចម្លងទៅ Clipboard រួចរាល់! អ្នកអាច Paste បានភ្លាមៗ។', true);
                    } catch (err) {
                        console.error('Clipboard Error:', err);
                        // Fallback: download if clipboard fails
                        const link = document.createElement('a');
                        link.download = `daily-report-${new Date().getTime()}.png`;
                        link.href = canvas.toDataURL();
                        link.click();
                        showPopup('ចំណាំ', 'មិនអាចចម្លងទៅ Clipboard បានទេ ទើបប្រព័ន្ធទាញយកជាឯកសារជំនួសវិញ។', true);
                    }
                }, 'image/png');

            } catch (error) {
                console.error('Screenshot Error:', error);
                showPopup('បរាជ័យ', 'មានបញ្ហាក្នុងការថតរូប៖ ' + error.message, false);
            } finally {
                container.classList.remove('screenshot-mode');
                btn.disabled = false;
                btn.innerHTML = originalContent;
            }
        }

        // Helper to format values for screenshot
        function prepareScreenshotValues() {
            // Header values
            const ids = ['reportDate', 'name', 'position', 'department', 'nextPlanDate', 'nextPlanDetails'];
            ids.forEach(id => {
                const input = document.getElementById(id);
                const display = document.getElementById('val-' + id);
                if (input && display) {
                    let val = input.value;
                    if (input.type === 'date' && val) {
                        const d = new Date(val);
                        val = String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
                    } else if (input.tagName === 'SELECT') {
                        val = input.options[input.selectedIndex].text;
                    }
                    display.innerText = val || '-';
                }
            });

            // Table values
            const rows = document.getElementById('taskBody').getElementsByTagName('tr');
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                for (let cell of cells) {
                    const input = cell.querySelector('input, textarea');
                    const display = cell.querySelector('.screenshot-value');
                    if (input && display) {
                        let val = input.value;
                        if (input.type === 'time' && val) {
                            const [h, m] = val.split(':');
                            let hour = parseInt(h);
                            const ampm = hour >= 12 ? 'PM' : 'AM';
                            hour = hour % 12 || 12;
                            val = `${String(hour).padStart(2, '0')}:${m} ${ampm}`;
                        } else if (input.type === 'date' && val) {
                            const d = new Date(val);
                            val = String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
                        }
                        display.innerText = val || (input.placeholder ? '' : '-');
                    }
                }
            }
        }

        // Function to clear form data
        function clearFormData() {
            if (confirm('តើអ្នកប្រាកដជាចង់សម្អាតទម្រង់ទាំងអស់មែនទេ?')) {
                localStorage.removeItem('dailyReportFormData');
                location.reload(); // Reload to reset form
            }
        }

        // Load saved data on page load and add input listeners
        document.addEventListener('DOMContentLoaded', function() {
            loadFormData();
            
            const formContainer = document.querySelector('.form-container');
            formContainer.addEventListener('input', saveFormData);

            // --- Auto-update Next Plan Date when Report Date changes ---
            const reportDateInput = document.getElementById('reportDate');
            const nextPlanDateInput = document.getElementById('nextPlanDate');

            reportDateInput.addEventListener('change', function() {
                const reportDateVal = new Date(this.value);
                if (!isNaN(reportDateVal.getTime())) {
                    const nextDate = new Date(reportDateVal);
                    nextDate.setDate(nextDate.getDate() + 1);
                    
                    const yyyy = nextDate.getFullYear();
                    const mm = String(nextDate.getMonth() + 1).padStart(2, '0');
                    const dd = String(nextDate.getDate()).padStart(2, '0');
                    
                    nextPlanDateInput.value = `${yyyy}-${mm}-${dd}`;
                    saveFormData();
                }
            });

            // --- Auto-resize textareas based on content ---
            formContainer.addEventListener('input', function(e) {
                if (e.target.tagName.toLowerCase() === 'textarea') {
                    autoResize(e.target);
                }
            });

            function autoResize(textarea) {
                textarea.style.height = 'auto'; // Reset height
                textarea.style.height = textarea.scrollHeight + 'px'; // Set to scrollHeight
            }

            // Initial resize for loaded data
            document.querySelectorAll('#taskBody textarea, #nextPlanDetails').forEach(autoResize);

            // --- Auto-Percentage for Status Field ---
            formContainer.addEventListener('change', function(e) {
                // Check if the changed element is in the 3rd column (Status)
                const cell = e.target.closest('td');
                if (cell && cell.cellIndex === 2) {
                    let value = e.target.value.trim();
                    // If it's a number and doesn't already have %, add it
                    if (value !== '' && !isNaN(value) && !value.includes('%')) {
                        e.target.value = value + '%';
                        saveFormData();
                    }
                }
            });

            // --- Auto-bulleting for Next Plan Details ---
            const nextPlanDetails = document.getElementById('nextPlanDetails');
            nextPlanDetails.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const cursorPosition = this.selectionStart;
                    const textBefore = this.value.substring(0, cursorPosition);
                    const textAfter = this.value.substring(cursorPosition);
                    const lines = textBefore.split('\n');
                    const lastLine = lines[lines.length - 1];

                    // Check if current line starts with a dash bullet
                    if (lastLine.trim().startsWith('-')) {
                        // If the line has content after the bullet, add a new bullet line
                        if (lastLine.trim() !== '-' && lastLine.trim() !== '- ') {
                            e.preventDefault();
                            const bullet = lastLine.startsWith('- ') ? '\n- ' : '\n-';
                            this.value = textBefore + bullet + textAfter;
                            this.selectionStart = this.selectionEnd = cursorPosition + bullet.length;
                        } else {
                            // If the line is JUST a bullet, pressing Enter should clear it (stop bulleting)
                            e.preventDefault();
                            const newTextBefore = textBefore.substring(0, textBefore.length - lastLine.length);
                            this.value = newTextBefore + '\n' + textAfter;
                            this.selectionStart = this.selectionEnd = newTextBefore.length + 1;
                        }
                        saveFormData();
                    }
                }
            });
        });
    </script>
</body>
</html>