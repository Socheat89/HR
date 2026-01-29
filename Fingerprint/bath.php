<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Fingerprint Check-In/Out System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" 
          integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" 
          crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .card-body {
            box-shadow: 0 4px 8px 0 rgba(82, 82, 82, 0.2), 0 6px 20px 0 rgba(99, 99, 99, 0.19);
            border-radius: 20px;
            background-size: cover;
            border: none;
            background-attachment: fixed;
            background-color: antiquewhite;
            background-image: url(https://i.ibb.co/QVr6zvG/new-year.png);
            background-repeat: no-repeat;
        }
        .card { border-radius: 20px; }
        .form-label {
            font-size: 18px;
            color: #007bff;
            text-decoration: underline;
            font-weight: bold;
        }
        .action-info p { font-size: 1.2rem; }
        .action-info .label { font-weight: bold; }
        .action-info .value { color: blue; }
        .map-link { word-wrap: break-word; }
        .status-message { font-size: 1.3rem; font-weight: bold; color: #dc3545; }
        .modal-dialog { max-width: 450px; }
        .btn {
            width: 100%;
            background-color: #007bff;
            color: white;
        }
        .btn:disabled { opacity: 0.6; }
        #export-section {
            width: 100%;
            position: fixed;
            top: 0;
            bottom: 0;
            background-repeat: no-repeat;
            background-size: cover;
        }
        .main-header { font-family: "Arial", sans-serif; color: #343a40; }
        .select-user { font-size: 18px; font-weight: bold; }
        .scan { font-size: 18px; font-weight: bold; animation: scanAnimation 1s ease-in-out infinite; }
        @keyframes scanAnimation {
            0% { transform: scale(1); background-color: #007bff; }
            50% { transform: scale(1.01); background-color: #0056b3; }
            100% { transform: scale(1); background-color: #007bff; }
        }
        .alert {
            position: fixed;
            top: 20%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #4caf50;
            color: white;
            padding: 20px 30px;
            font-size: 1.2em;
            font-weight: bold;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.4s ease-out, transform 0.4s ease-out;
        }
        .alert.show { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        .alert.hidden { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
        .popup1 {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 18px;
            z-index: 999;
        }
        .popup-content { animation: fadeIn 0.5s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>
<body>
    <div id="autoPopup" class="alert hidden" style="background-color: #007bff; width: 100%; text-align: center; font-size: 14px; font-weight: bold; top: 3.2rem; position: relative; border-radius: 0px 0px 60px 60px;">
        🔄 អាប់ដេតមុខងារថ្មី! ឥឡូវនេះ, ប្រព័ន្ធស្កេននឹងជ្រើសរើសប្រភេទស្កេន (Check-In/Out) ដោយស្វ័យប្រវត្តិ 🕒 ដោយមិនចាំបាច់ជ្រើសរើសដោយដៃទៀតទេ។ យើងអាចស្កេនបានយ៉ាងងាយស្រួល និងរហ័សជាងមុន! 🚀
    </div>

    <h1 id="access-denied" class="text-center" style="display: none; padding: 100px 0; color: red; font-weight: bold;">
        អ្នកមិនអាចបើកស្កេនបានទេ!
    </h1>
    <p id="access-granted" style="color: green; font-weight: bold; display: none; text-align: center; position: absolute;">
        អ្នកអាចប្រើប្រព័ន្ធបានហើយ!
    </p>

    <section id="export-section" style="display: none; position: relative; top: -3.5rem;">
        <div class="container mt-5 main-header">
            <h1 class="text-center main-content" style="text-decoration: underline; color: rgb(0, 0, 0);">Fingerprint Check-In/Out</h1>
            <div class="card mt-4">
                <div class="card-body">
                    <!-- User Name Input -->
                    <div class="mb-3 mt-4">
                        <label for="userNameInput" class="form-label select-user">ជ្រើសរើសអ្នកប្រើប្រាស់</label>
                        <select id="userNameInput" class="form-select" required></select>
                    </div>

                    <!-- Action Selection Dropdown -->
                    <div class="mb-3">
                        <label for="actionSelect" class="form-label">ជ្រើសរើសប្រភេទស្កេន</label>
                        <select id="actionSelect" class="form-select" required>
                            <option value="Check-In">ស្កេនចូល</option>
                            <option value="Check-Out">ស្កេនចេញ</option>
                        </select>
                    </div>

                    <!-- Other Dropdowns -->
                    <div class="mb-3">
                        <label for="idSelect" class="form-label">ID</label>
                        <select id="idSelect" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="departmentSelect" class="form-label">នាយកដ្ឋាន</label>
                        <select id="departmentSelect" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="PositionSelect" class="form-label">តួនាទី</label>
                        <select id="PositionSelect" class="form-select" required></select>
                    </div>
                    <div class="mb-3">
                        <label for="BranchSelect" class="form-label">សាខា</label>
                        <select id="BranchSelect" class="form-select" required></select>
                    </div>

                    <button id="scanFingerprint" class="btn btn-primary mb-3 scan">ចាប់ផ្តើមស្កេន</button>
                    <p id="scanMessage" style="font-size: 1.1rem; text-align: center;"></p>

                    <!-- Popup Message -->
                    <div id="scanPopup" class="popup1">
                        <div class="popup-content">
                            <p>🔄 កំពុងរក្សារទុកទិន្នន័យ... សូមរង់ចាំ! <br>សូមកុំចាក់ចេញពី Browser របស់អ្នក! <br> សូមរងចាំពាក្យបញ្ជក់ថា<small>"បានរក្សាទុកទិន្នន័យរួចរាល់"</small></p>
                        </div>
                    </div>

                    <!-- Action Information -->
                    <div class="action-info">
                        <p><span class="label">ប្រភេទស្កេន៖</span> <span id="action" class="value">N/A</span></p>
                        <p><span class="label">ថ្ងៃខែឆ្នាំ/ម៉ោង៖</span> <span id="timestamp" class="value">N/A</span></p>
                        <p><span class="label">ការចាប់ទីតាំងរបស់អ្នក៖</span> <span id="location" class="value">កំពុងស្កេនទីតាំង</span></p>
                        <p><span class="label">ទីតាំងរបស់អ្នក៖</span> <a id="mapLink" class="map-link" href="#" target="_blank">View on Map</a></p>
                    </div>

                    <!-- Status Message -->
                    <p id="status" class="status-message text-muted">រងចាំការស្កេនរបស់អ្នក...</p>
                </div>
            </div>
        </div>

        <!-- Scan Log History -->
        <div class="container mt-4">
            <h2 class="text-center">📜 Scan Log History</h2>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="userFilter" class="form-label">ជ្រើសរើសអ្នកប្រើប្រាស់</label>
                    <select id="userFilter" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label for="startDate" class="form-label" style="font-size: 11px; text-decoration: none;">ថ្ងៃចាប់ផ្តើម</label>
                    <input type="date" id="startDate" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="endDate" class="form-label" style="font-size: 11px; text-decoration: none;">ថ្ងៃបញ្ចប់</label>
                    <input type="date" id="endDate" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="filterLogs" class="btn btn-primary w-100">🔍 Filter</button>
                </div>
            </div>
            <button id="refreshLogs" class="btn btn-secondary mb-3">🔄 Refresh History</button>
            <table class="table table-bordered table-striped" style="font-size: 10px;">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>ឈ្មោះ</th>
                        <th>ប្រភេទស្កេន</th>
                        <th>ថ្ងៃ/ម៉ោង</th>
                        <th>ទីតាំង</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr><td colspan="5" class="text-center">Loading logs...</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        window.addEventListener('load', () => {
            document.getElementById('export-section').style.display = 'block';
            populateDropdowns();
            checkAccess();
            setScanTypeAutomatically();
            fetchLogs();
            showAutoPopup();
            setInterval(fetchLogs, 30000); // Refresh logs every 30 seconds
            setInterval(setScanTypeAutomatically, 60000); // Update scan type every minute
        });

        function populateDropdowns() {
            fetch('manager_crud.php')
                .then(response => response.json())
                .then(data => {
                    const userSelect = document.getElementById('userNameInput');
                    const idSelect = document.getElementById('idSelect');
                    const positionSelect = document.getElementById('PositionSelect');
                    const deptSelect = document.getElementById('departmentSelect');
                    const branchSelect = document.getElementById('BranchSelect');
                    const userFilter = document.getElementById('userFilter');

                    userSelect.innerHTML = '<option value="">ជ្រើសរើសឈ្មោះ</option>';
                    idSelect.innerHTML = '<option value="">ជ្រើសរើស ID</option>';
                    positionSelect.innerHTML = '<option value="">ជ្រើសរើសតួនាទី</option>';
                    deptSelect.innerHTML = '<option value="">ជ្រើសរើសនាយកដ្ឋាន</option>';
                    branchSelect.innerHTML = '<option value="">ជ្រើសរើសសាខា</option>';
                    userFilter.innerHTML = '<option value="">ទាំងអស់</option>';

                    const uniqueIds = new Set();
                    const uniquePositions = new Set();
                    const uniqueDepts = new Set();
                    const uniqueBranches = new Set();

                    data.data.forEach(manager => {
                        userSelect.innerHTML += `<option value="${manager.username}">${manager.username} (${manager.employee_id})</option>`;
                        userFilter.innerHTML += `<option value="${manager.username}">${manager.username}</option>`;
                        if (!uniqueIds.has(manager.employee_id)) {
                            idSelect.innerHTML += `<option value="${manager.employee_id}">${manager.employee_id}</option>`;
                            uniqueIds.add(manager.employee_id);
                        }
                        if (!uniquePositions.has(manager.position)) {
                            positionSelect.innerHTML += `<option value="${manager.position}">${manager.position}</option>`;
                            uniquePositions.add(manager.position);
                        }
                        if (!uniqueDepts.has(manager.department)) {
                            deptSelect.innerHTML += `<option value="${manager.department}">${manager.department}</option>`;
                            uniqueDepts.add(manager.department);
                        }
                        if (!uniqueBranches.has(manager.branch)) {
                            branchSelect.innerHTML += `<option value="${manager.branch}">${manager.branch}</option>`;
                            uniqueBranches.add(manager.branch);
                        }
                    });

                    userSelect.addEventListener('change', () => {
                        const selectedUser = data.data.find(m => m.username === userSelect.value);
                        if (selectedUser) {
                            idSelect.value = selectedUser.employee_id;
                            positionSelect.value = selectedUser.position;
                            deptSelect.value = selectedUser.department;
                            branchSelect.value = selectedUser.branch;
                            checkAccess();
                            setScanTypeAutomatically();
                            fetchLogs();
                        }
                    });
                });
        }

        function generateToken() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = (Math.random() * 16) | 0;
                return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
            });
        }

        function checkAccess() {
            const browserToken = localStorage.getItem('browserToken') || generateToken();
            localStorage.setItem('browserToken', browserToken);

            const khmerUserName = document.getElementById('userNameInput').value.trim();
            if (!khmerUserName) {
                alert("សូមជ្រើសរើសឈ្មោះរបស់អ្នកមុននឹងប្រើប្រព័ន្ធ!");
                document.getElementById('access-denied').style.display = 'block';

                return;
            }

            document.getElementById('access-granted').style.display = 'block';
            document.getElementById('access-denied').style.display = 'none';
            document.getElementById('export-section').style.display = 'block';
        }

        function setScanTypeAutomatically() {
            const currentTime = new Date();
            const currentHour = currentTime.getHours();
            const currentMinutes = currentTime.getMinutes();
            const actionSelect = document.getElementById('actionSelect');
            const scanButton = document.getElementById('scanFingerprint');
            const message = document.getElementById('scanMessage');

            const checkInRanges = [
                { startHour: 6, startMinute: 0, endHour: 10, endMinute: 0 },
                { startHour: 12, startMinute: 30, endHour: 13, endMinute: 10 }
            ];
            const checkOutRanges = [
                { startHour: 12, startMinute: 0, endHour: 12, endMinute: 30 },
                { startHour: 13, startMinute: 10, endHour: 22, endMinute: 0 }
            ];

            const isWithinTimeRanges = ranges => ranges.some(range => 
                (currentHour > range.startHour || (currentHour === range.startHour && currentMinutes >= range.startMinute)) &&
                (currentHour < range.endHour || (currentHour === range.endHour && currentMinutes < range.endMinute))
            );

            if (isWithinTimeRanges(checkInRanges)) {
                actionSelect.value = 'Check-In';
                scanButton.disabled = false;
                message.textContent = "✅ You can Check-In now!";
                message.style.color = "green";
            } else if (isWithinTimeRanges(checkOutRanges)) {
                actionSelect.value = 'Check-Out';
                scanButton.disabled = false;
                message.textContent = "✅ You can Check-Out now!";
                message.style.color = "green";
            } else {
                actionSelect.value = '';
                scanButton.disabled = true;
                message.textContent = "⛔ Scanning is not allowed at this time.";
                message.style.color = "red";
            }
        }

        function saveData() {
            const userName = document.getElementById('userNameInput').value;
            const scanType = document.getElementById('actionSelect').value;
            const popup = document.getElementById('scanPopup');
            popup.style.display = 'block';

            navigator.geolocation.getCurrentPosition(position => {
                const data = {
                    user_name: userName,
                    scan_type: scanType,
                    scan_time: new Date().toISOString(),
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                };

                fetch('save_scan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(responseData => {
                    popup.style.display = 'none';
                    if (responseData.status === 'success') {
                        alert('បានរក្សាទុកទិន្នន័យរួចរាល់');
                        document.getElementById('action').textContent = scanType;
                        document.getElementById('timestamp').textContent = new Date().toLocaleString();
                        document.getElementById('location').textContent = `Lat: ${position.coords.latitude.toFixed(5)}, Long: ${position.coords.longitude.toFixed(5)}`;
                        document.getElementById('mapLink').href = `https://www.google.com/maps?q=${position.coords.latitude},${position.coords.longitude}`;
                        document.getElementById('status').textContent = `ស្កេនជោគជ័យ: ${userName}`;
                        fetchLogs();
                    } else {
                        alert('Error: ' + responseData.message);
                    }
                })
                .catch(error => {
                    popup.style.display = 'none';
                    console.error('Error:', error);
                    alert('មានបញ្ហាក្នុងការរក្សាទុកទិន្នន័យ');
                });
            }, () => {
                popup.style.display = 'none';
                alert('មិនអាចចាប់យកទីតាំងបានទេ!');
            });
        }

        function fetchLogs() {
            const userName = document.getElementById('userFilter').value;
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;

            let url = 'get_logs.php';
            const params = [];
            if (userName) params.push(`user_name=${encodeURIComponent(userName)}`);
            if (startDate) params.push(`start_date=${startDate}`);
            if (endDate) params.push(`end_date=${endDate}`);
            if (params.length) url += `?${params.join('&')}`;

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const logTable = document.getElementById('logTableBody');
                    logTable.innerHTML = '';

                    if (!data || data.length === 0) {
                        logTable.innerHTML = "<tr><td colspan='5' class='text-center'>គ្មានទិន្នន័យ!</td></tr>";
                        return;
                    }

                    data.forEach((log, index) => {
                        const logDate = new Date(log.scan_time);
                        const formattedTime = logDate.toLocaleString('en-US', {
                            timeZone: 'Asia/Phnom_Penh',
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        });

                        logTable.innerHTML += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${log.user_name || 'Unknown'}</td>
                                <td>${log.scan_type}</td>
                                <td>${formattedTime}</td>
                                <td><a href="https://www.google.com/maps?q=${log.latitude},${log.longitude}" target="_blank">📍 View</a></td>
                            </tr>`;
                    });
                })
                .catch(error => {
                    console.error('Error fetching logs:', error);
                    document.getElementById('logTableBody').innerHTML = 
                        "<tr><td colspan='5' class='text-center text-danger'>Failed to load logs</td></tr>";
                });
        }

        function showAutoPopup() {
            const popup = document.getElementById('autoPopup');
            popup.classList.remove('hidden');
            popup.classList.add('show');
            setTimeout(() => {
                popup.classList.remove('show');
                popup.classList.add('hidden');
            }, 6000);
        }

        document.getElementById('scanFingerprint').addEventListener('click', saveData);
        document.getElementById('filterLogs').addEventListener('click', fetchLogs);
        document.getElementById('refreshLogs').addEventListener('click', fetchLogs);
    </script>
</body>
</html>