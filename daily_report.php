<?php
// index.php
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
    <style>
        /* Import Khmer font */
        @import url('https://fonts.googleapis.com/css2?family=Khmer&display=swap');

        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Khmer', 'Segoe UI', Arial, sans-serif;
        }

        /* Body styles */
        body {
            background: #f7f9fc;
            min-height: 100vh;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            color: #333;
        }

        /* Form container */
        .form-container {
            width: 100%;
            max-width: 2500px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 32px;
            margin: 0 auto;
        }

        /* Headings */
        h2, h3 {
            text-align: center;
            color: #1a202c;
            margin-bottom: 24px;
        }

        h2 {
            font-size: 1.75rem;
            font-weight: 700;
        }

        h3 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Header row */
        .header-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .header-row input, .header-row select {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            background: #fff;
            width: 100%;
            line-height: 1.5;
        }

        .header-row input:focus, .header-row select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        /* Specific styling for "កិច្ចការ / បញ្ហា" column (second column) */
        th:nth-child(2), td:nth-child(2) {
            width: 30%; /* Larger width for Task/Issue column */
            min-width: 300px; /* Ensure minimum width for readability */
        }

        td:nth-child(2) input {
            width: 100%;
            min-height: 48px; /* Taller input for more text */
            padding: 12px;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 6px;
            border: 1px solid #d1d5db;
        }

        td:nth-child(2) input:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        th {
            background: #fef3c7;
            color: #1f2937;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        td {
            background: #f9fafb;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        tbody tr:hover {
            background: #f1f5f9;
        }

        /* Form elements in table */
        td input, td select, td textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
            background: #fff;
            line-height: 1.5;
        }

        td input:focus, td select:focus, td textarea:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        td textarea {
            resize: vertical;
            min-height: 100px;
            max-height: 300px;
        }

        /* Next day plan section */
        .next-day-plan {
            margin: 32px 0;
            padding: 24px;
            background: #f1f5f9;
            border-radius: 8px;
        }

        .plan-container input[type="date"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #fff;
        }

        .plan-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            min-height: 150px;
            font-size: 0.875rem;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 16px;
            padding: 16px 0;
            background: #fff;
        }

        button {
            background: #f59e0b;
            color: #1f2937;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        button:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.3);
        }

        .remove-btn {
            background: #ef4444;
            padding: 8px 16px;
            border-radius: 6px;
        }

        .remove-btn:hover {
            background: #dc2626;
        }

        /* Popup styles */
        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            min-width: 300px;
            text-align: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .popup.show {
            opacity: 1;
            visibility: visible;
            transform: translate(-50%, -50%) scale(1);
        }

        .popup.success h3 {
            color: #10b981;
        }

        .popup.error h3 {
            color: #ef4444;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s ease;
        }

        .overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Responsive design */
        @media screen and (max-width: 1200px) {
            .header-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-container {
                padding: 24px;
            }
        }

        @media screen and (max-width: 768px) {
            .header-row {
                grid-template-columns: 1fr;
            }
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            th, td {
                min-width: 150px;
            }
            th:nth-child(2), td:nth-child(2) {
                min-width: 250px; /* Slightly smaller min-width for mobile */
            }
            td:nth-child(2) input {
                min-height: 40px; /* Adjust height for mobile */
            }
            .action-buttons {
                flex-direction: column;
                gap: 12px;
            }
            button {
                width: 100%;
            }
        }

        @media screen and (max-width: 480px) {
            .form-container {
                padding: 16px;
            }
            h2 {
                font-size: 1.5rem;
            }
            h3 {
                font-size: 1.125rem;
            }
            button {
                padding: 10px 16px;
                font-size: 0.875rem;
            }
            .popup {
                min-width: 90%;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Individual Daily Report</h2>
        <div class="header-row">
            <input type="date" id="reportDate" value="<?php echo $today; ?>">
            <input type="text" id="name" placeholder="ឈ្មោះ" required>
            <select id="position" required>
                <option value="" disabled selected>ជ្រើសរើសតួនាទី</option>
                <option value="IT SUPPORT">IT SUPPORT</option>
                <option value="IT MANAGER">IT MANAGER</option>
                <option value="Administration">រដ្ឋបាលទូទៅ</option>
                <option value="Brand Supervisor">ប្រធានគ្រប់គ្រងម៉ាកផលិផល</option>
            </select>
            <select id="department" required>
                <option value="" disabled selected>ជ្រើសរើសផ្នែក</option>
                <option value="ព័ត៌មានវិទ្យា">ផ្នែកព័ត៌មានវិទ្យា</option>
                <option value="ប្រធានផ្នែកព័ត៌មានវិទ្យា">ប្រធានផ្នែកព័ត៌មានវិទ្យា</option>
                <option value="រដ្ឋបាលទូទៅ">រដ្ឋបាលទូទៅ</option>
                <option value="លក់">ប្រធានគ្រប់គ្រងម៉ាកផលិផល</option>
            </select>
        </div>

        <table id="taskTable">
            <thead>
                <tr>
                    <th>ម៉ោង</th>
                    <th>កិច្ចការ / បញ្ហា</th>
                    <th>ស្ថានភាព</th>
                    <th>កាលបរិច្ឆេទផុតកំណត់</th>
                    <th>ពិពណ៌នា (សម្រាប់ការងារនៅសល់)</th>
                    <th>បញ្ហា</th>
                    <th>ដំណោះស្រាយ</th>
                    <th>សកម្មភាព</th>
                </tr>
            </thead>
            <tbody id="taskBody">
                <!-- Initial rows will be populated by JavaScript -->
            </tbody>
        </table>

        <div class="next-day-plan">
            <h3>ផែនការសម្រាប់ថ្ងៃបន្ទាប់</h3>
            <span>*សូមបំពេញថ្ងៃខែឆ្នាំ</span>
            <div class="plan-container">
                <input type="date" id="nextPlanDate" value="<?php echo $tomorrow; ?>">
                <textarea id="nextPlanDetails" placeholder="ព័ត៌មានលម្អិតអំពីផែនការ" style="width: 100%; min-height: 100px;"></textarea>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" onclick="addRow()">បន្ថែមជួរ</button>
            <button type="submit" onclick="submitForm()">ផ្ញើទៅ Telegram</button>
            <button type="button" onclick="clearFormData()">សម្អាតទម្រង់</button>
        </div>
    </div>

    <div class="overlay" id="popupOverlay"></div>
    <div class="popup" id="messagePopup">
        <h3 id="popupTitle"></h3>
        <p id="popupMessage"></p>
        <button onclick="hidePopup()">បិទ</button>
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
                    time: cells[0].getElementsByTagName('input')[0].value,
                    task: cells[1].getElementsByTagName('input')[0].value,
                    status: cells[2].getElementsByTagName('input')[0].value,
                    dueDate: cells[3].getElementsByTagName('input')[0].value,
                    description: cells[4].getElementsByTagName('textarea')[0].value,
                    problem: cells[5].getElementsByTagName('textarea')[0].value,
                    solution: cells[6].getElementsByTagName('textarea')[0].value
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
                        <td><input type="time" value="${task.time || ''}"></td>
                        <td><input type="text" value="${task.task || ''}"></td>
                        <td><input type="text" placeholder="ស្ថានភាព (ឧ. 100%, 50%)" value="${task.status || ''}"></td>
                        <td><input type="date" value="${task.dueDate || ''}"></td>
                        <td><textarea placeholder="ពិពណ៌នា">${task.description || ''}</textarea></td>
                        <td><textarea placeholder="បញ្ហា">${task.problem || ''}</textarea></td>
                        <td><textarea placeholder="ដំណោះស្រាយ">${task.solution || ''}</textarea></td>
                        <td><button class="remove-btn" onclick="removeRow(this)">លុប</button></td>
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
                <td><input type="time"></td>
                <td><input type="text"></td>
                <td><input type="text" placeholder="ស្ថានភាព (ឧ. 100%, 50%)"></td>
                <td><input type="date"></td>
                <td><textarea placeholder="ពិពណ៌នា"></textarea></td>
                <td><textarea placeholder="បញ្ហា"></textarea></td>
                <td><textarea placeholder="ដំណោះស្រាយ"></textarea></td>
                <td><button class="remove-btn" onclick="removeRow(this)">លុប</button></td>
            `;
            tbody.appendChild(newRow);
            saveFormData();
        }

        // Function to remove a row from the task table
        function removeRow(button) {
            const tbody = document.getElementById('taskBody');
            const rowCount = tbody.getElementsByTagName('tr').length;
            
            if (rowCount > 1) {
                if (confirm('តើអ្នកប្រាកដជាចង់លុបជួរនេះមែនទេ?')) {
                    button.parentElement.parentElement.remove();
                    saveFormData();
                }
            } else {
                alert("មិនអាចលុបជួរចុងក្រោយបានទេ!");
            }
        }

        // Function to show popup messages
        function showPopup(title, message, isSuccess) {
            const popup = document.getElementById('messagePopup');
            const overlay = document.getElementById('popupOverlay');
            const titleElement = document.getElementById('popupTitle');
            const messageElement = document.getElementById('popupMessage');

            titleElement.textContent = title;
            messageElement.textContent = message;
            
            popup.className = 'popup';
            popup.classList.add(isSuccess ? 'success' : 'error');
            popup.classList.add('show');
            overlay.classList.add('show');

            setTimeout(hidePopup, 5000);
        }

        // Function to hide popup
        function hidePopup() {
            const popup = document.getElementById('messagePopup');
            const overlay = document.getElementById('popupOverlay');
            
            popup.classList.remove('show');
            overlay.classList.remove('show');
        }

        // Function to submit the form data to Telegram
        function submitForm() {
            const reportDate = document.getElementById('reportDate').value;
            const name = document.getElementById('name').value;
            const position = document.getElementById('position').value;
            const department = document.getElementById('department').value;
            const nextPlanDate = document.getElementById('nextPlanDate').value;
            const nextPlanDetails = document.getElementById('nextPlanDetails').value;

            if (!reportDate || !name || !position || !department) {
                showPopup('បរាជ័យ', 'សូមបំពេញរាល់វាលដែលត្រូវការ (កាលបរិច្ឆេទ, ឈ្មោះ, តួនាទី, ផ្នែក)!', false);
                return;
            }

            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'កំពុងផ្ញើ...';

            const reportData = {
                date: reportDate,
                name: name,
                position: position,
                department: department,
                next_plan_date: nextPlanDate,
                next_plan_details: nextPlanDetails,
                tasks: []
            };

            const rows = document.getElementById('taskBody').getElementsByTagName('tr');
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                const timeValue = cells[0].getElementsByTagName('input')[0].value || '-'; // ជំនួសតម្លៃទទេជា "-"
                reportData.tasks.push({
                    time: timeValue,
                    task: cells[1].getElementsByTagName('input')[0].value,
                    status: cells[2].getElementsByTagName('input')[0].value,
                    dueDate: cells[3].getElementsByTagName('input')[0].value,
                    description: cells[4].getElementsByTagName('textarea')[0].value,
                    problem: cells[5].getElementsByTagName('textarea')[0].value,
                    solution: cells[6].getElementsByTagName('textarea')[0].value
                });
            }

            fetch('send_to_telegram.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(reportData)
            })
            .then(response => response.json())
            .then(result => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ផ្ញើទៅ Telegram';
                if (result.success) {
                    showPopup('ជោគជ័យ', 'បានផ្ញើរបាយការណ៍ទៅ Telegram ដោយជោគជ័យ!', true);
                    saveFormData();
                } else {
                    showPopup('បរាជ័យ', result.message || 'មានបញ្ហាក្នុងការផ្ញើ!', false);
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ផ្ញើទៅ Telegram';
                showPopup('កំហុស', 'កំហុសបច្ចេកទេស: ' + error.message, false);
            });
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
        });
    </script>
</body>
</html>