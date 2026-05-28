<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Meeting Records</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Kh Battambang', sans-serif;
            background-color: #f4f4f9;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .filter-section {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        .search-section {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
            align-items: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #050049;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .status-attended {
            color: green;
            font-weight: bold;
        }
        .status-absent {
            color: red;
            font-weight: bold;
        }
        .loading {
            text-align: center;
            font-size: 18px;
            color: #555;
        }
        .filter-select, .search-input {
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 14px;
        }
        .join .adsent{
            color:black;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>បញ្ជីការចុះឈ្មោះប្រជុំ</h1>
        <div class="filter-section">
            <button class="btn btn-primary" onclick="filterMeetings('all', '', '')">ទាំងអស់</button>
            <button class="btn btn-success join" onclick="filterMeetings('attended', document.getElementById('meetingTypeFilter').value, document.getElementById('searchInput').value)">អ្នកចូលរួម</button>
            <button class="btn btn-danger adsent" onclick="filterMeetings('absent', document.getElementById('meetingTypeFilter').value, document.getElementById('searchInput').value)">អ្នកមិនបានចូលរួម</button>
            <select id="meetingTypeFilter" class="filter-select" onchange="filterMeetings(document.querySelector('.filter-section button.active')?.dataset.status || 'all', this.value, document.getElementById('searchInput').value)">
                <option value="">ជ្រើសរើសប្រភេទប្រជុំ</option>
                <option value="ការប្រជុំប្រចាំខែ">ការប្រជុំប្រចាំខែ</option>
                <option value="ការប្រជុំផ្នែកស្តុក CH1">ការប្រជុំផ្នែកស្តុក CH1</option>
                <option value="ការប្រជុំផ្នែកស្តុក 318">ការប្រជុំផ្នែកស្តុក 318</option>
                <option value="ការប្រជុំបុគ្គលិកផ្នែកបើក CH1">ការប្រជុំបុគ្គលិកផ្នែកបើក CH1</option>
            </select>
        </div>
        <div class="search-section">
            <input type="text" id="searchInput" class="search-input" placeholder="ស្វែងរកតាមឈ្មោះ ឬ អត្តលេខ" oninput="filterMeetings(document.querySelector('.filter-section button.active')?.dataset.status || 'all', document.getElementById('meetingTypeFilter').value, this.value)">
            <button class="btn btn-info" onclick="filterMeetings(document.querySelector('.filter-section button.active')?.dataset.status || 'all', document.getElementById('meetingTypeFilter').value, document.getElementById('searchInput').value)">
                <i class="fas fa-search"></i> ស្វែងរក
            </button>
        </div>
        <div id="loading" class="loading">កំពុងផ្ទុកទិន្នន័យ...</div>
        <table id="meetingTable" style="display: none;">
            <thead>
                <tr>
                    <th>អត្តលេខ</th>
                    <th>ឈ្មោះ</th>
                    <th>ភេទ</th>
                    <th>ថ្ងៃខែឆ្នាំ</th>
                    <th>ម៉ោង</th>
                    <th>ទីតាំង</th>
                    <th>ប្រភេទប្រជុំ</th>
                    <th>មូលហេតុ</th>
                    <th>ស្ថានភាព</th>
                    <th>កាលបរិច្ឆេទបញ្ចូល</th>
                </tr>
            </thead>
            <tbody id="meetingBody"></tbody>
        </table>
    </div>

    <script>
        let allMeetings = [];

        // Fetch data from PHP API
        async function fetchMeetings() {
            try {
                const response = await fetch('../meetings/get_meetings.php'); // Replace with your API URL
                const result = await response.json();
                if (result.status === 'success') {
                    allMeetings = result.data;
                    displayMeetings(allMeetings);
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('meetingTable').style.display = 'table';
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                document.getElementById('loading').textContent = 'មានបញ្ហា: ' + error.message;
                document.getElementById('loading').style.color = 'red';
            }
        }

        // Display meetings in table
        function displayMeetings(meetings) {
            const tbody = document.getElementById('meetingBody');
            tbody.innerHTML = '';

            meetings.forEach(meeting => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${meeting.id_number}</td>
                    <td>${meeting.name}</td>
                    <td>${meeting.gender}</td>
                    <td>${meeting.date}</td>
                    <td>${meeting.time}</td>
                    <td>${meeting.location}</td>
                    <td>${meeting.meeting_type}</td>
                    <td>${meeting.reason || '-'}</td>
                    <td class="${meeting.status === 'attended' ? 'status-attended' : 'status-absent'}">
                        ${meeting.status === 'attended' ? 'ចូលរួម' : 'មិនបានចូលរួម'}
                    </td>
                    <td>${new Date(meeting.created_at).toLocaleString('km-KH')}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Filter meetings based on status, meeting type, and search query
        function filterMeetings(status, meetingType, searchQuery) {
            let filteredMeetings = allMeetings;

            // Filter by status
            if (status === 'attended') {
                filteredMeetings = allMeetings.filter(m => m.status === 'attended');
            } else if (status === 'absent') {
                filteredMeetings = allMeetings.filter(m => m.status === 'absent');
            }

            // Filter by meeting type if selected
            if (meetingType) {
                filteredMeetings = filteredMeetings.filter(m => m.meeting_type === meetingType);
            }

            // Filter by search query if provided
            if (searchQuery) {
                searchQuery = searchQuery.toLowerCase();
                filteredMeetings = filteredMeetings.filter(m => 
                    m.name.toLowerCase().includes(searchQuery) || 
                    m.id_number.toLowerCase().includes(searchQuery)
                );
            }

            // Update active button state
            document.querySelectorAll('.filter-section button').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('btn-' + (btn.dataset.status === status ? btn.dataset.status === 'all' ? 'primary' : btn.dataset.status === 'attended' ? 'success' : 'danger' : 'outline-' + (btn.dataset.status === 'all' ? 'primary' : btn.dataset.status === 'attended' ? 'success' : 'danger')));
            });
            const activeButton = document.querySelector(`.filter-section button[data-status="${status}"]`);
            if (activeButton) {
                activeButton.classList.add('active');
                activeButton.classList.remove('btn-' + (status === 'all' ? 'outline-primary' : status === 'attended' ? 'outline-success' : 'outline-danger'));
                activeButton.classList.add('btn-' + (status === 'all' ? 'primary' : status === 'attended' ? 'success' : 'danger'));
            }

            displayMeetings(filteredMeetings);
        }

        // Add data-status to buttons for tracking
        document.querySelectorAll('.filter-section button').forEach(btn => {
            btn.dataset.status = btn.textContent.includes('ទាំងអស់') ? 'all' : btn.textContent.includes('អ្នកចូលរួម') ? 'attended' : 'absent';
        });

        // Load data on page load
        window.onload = fetchMeetings;
    </script>
</body>
</html>