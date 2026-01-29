<?php
// tracker.php - Updated to manage 'is_live' status
date_default_timezone_set('Asia/Phnom_Penh');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'db.php';
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? null;

    if ($action === 'start') {
        if (empty($input['lat']) || empty($input['lon'])) { /* ... error handling ... */ }
        $start_time = date('Y-m-d H:i:s');
        try {
            $stmt = $pdo->prepare("INSERT INTO trips (start_latitude, start_longitude, start_time, is_live) VALUES (?, ?, ?, 1)"); // Set is_live to 1
            $stmt->execute([$input['lat'], $input['lon'], $start_time]);
            $tracking_id = $pdo->lastInsertId();
            echo json_encode(['status' => 'success', 'message' => 'Tracking started successfully.', 'tracking_id' => $tracking_id]);
        } catch (PDOException $e) { /* ... error handling ... */ }

    } elseif ($action === 'update') {
        if (empty($input['tracking_id']) || empty($input['lat']) || empty($input['lon'])) { /* ... error handling ... */ }
        $timestamp = date('Y-m-d H:i:s');
        try {
            $stmt = $pdo->prepare("INSERT INTO tracking_points (tracking_id, latitude, longitude, timestamp) VALUES (?, ?, ?, ?)");
            $stmt->execute([$input['tracking_id'], $input['lat'], $input['lon'], $timestamp]);
            echo json_encode(['status' => 'success', 'message' => 'Location updated.']);
        } catch (PDOException $e) { /* ... error handling ... */ }

    } elseif ($action === 'end') {
        if (empty($input['tracking_id']) || empty($input['lat']) || empty($input['lon'])) { /* ... error handling ... */ }
        $end_time_str = date('Y-m-d H:i:s');
        try {
            // Fetch start_time to calculate duration
            $stmt = $pdo->prepare("SELECT start_time FROM trips WHERE id = ?");
            $stmt->execute([$input['tracking_id']]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tracking) { /* ... error handling ... */ }
            $start_time = new DateTime($tracking['start_time']);
            $end_time = new DateTime($end_time_str);
            $travel_time_seconds = $end_time->getTimestamp() - $start_time->getTimestamp();
            
            // Update trip with end data and set is_live to 0
            $stmt = $pdo->prepare("UPDATE trips SET end_latitude = ?, end_longitude = ?, end_time = ?, travel_time_seconds = ?, is_live = 0 WHERE id = ?"); // Set is_live to 0
            $stmt->execute([$input['lat'], $input['lon'], $end_time_str, $travel_time_seconds, $input['tracking_id']]);
            echo json_encode(['status' => 'success', 'message' => 'Tracking ended successfully.', 'travel_time' => $travel_time_seconds]);
        } catch (Exception $e) { /* ... error handling ... */ }
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    }
    exit();
}
// The HTML part of tracker.php remains the same
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live GPS Tracker</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
            background-color: #f4f7fa;
        }
        .tracker-container {
            background-color: white;
            padding: 30px 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            width: 320px;
        }
        h1 { 
            color: #333; 
            font-size: 24px;
            margin-bottom: 20px;
        }
        .status-display {
            background-color: #f0f2f5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .status-display p {
            margin: 0;
            color: #555;
            font-size: 14px;
        }
        .status-display #timer {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        .live-coords {
            font-size: 12px;
            color: #888;
            margin-top: 10px;
            min-height: 16px;
        }
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        button {
            border: none;
            padding: 15px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        #startButton {
            background-color: #198754; /* Green */
            color: white;
        }
        #endButton {
            background-color: #dc3545; /* Red */
            color: white;
        }
        button:disabled {
            background-color: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
        }
        button:not(:disabled):hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .view-history {
            margin-top: 25px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <div class="tracker-container">
        <h1>Live GPS Tracker 🛰️</h1>
        
        <div class="status-display">
            <p id="statusMessage">Ready to start tracking</p>
            <div id="timer">00:00:00</div>
            <div class="live-coords" id="liveCoords">--</div>
        </div>

        <div class="button-group">
            <button id="startButton">Start</button>
            <button id="endButton" disabled>End</button>
        </div>
        
        <div class="view-history">
            <a href="view_trips.php">View Tracking History</a>
        </div>
    </div>

    <script>
        // UI Elements
        const startBtn = document.getElementById('startButton');
        const endBtn = document.getElementById('endButton');
        const statusMessage = document.getElementById('statusMessage');
        const timerDiv = document.getElementById('timer');
        const liveCoordsDiv = document.getElementById('liveCoords');

        // State Variables
        let currentTrackingId = null;
        let timerInterval = null;
        let seconds = 0;
        let watchId = null; // To store the ID of the location watcher
        
        const API_ENDPOINT = 'tracker.php';

        // Event Listeners
        startBtn.addEventListener('click', startTracking);
        endBtn.addEventListener('click', endTracking);

        function startTracking() {
            startBtn.disabled = true;
            statusMessage.textContent = 'Initializing...';

            // 1. Get initial position to start the session
            navigator.geolocation.getCurrentPosition(position => {
                const { latitude, longitude } = position.coords;
                
                fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'start', lat: latitude, lon: longitude })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success' && data.tracking_id) {
                        currentTrackingId = data.tracking_id;
                        statusMessage.textContent = `Tracking Session #${currentTrackingId}`;
                        endBtn.disabled = false;
                        startTimer();
                        
                        // 2. Start WATCHING for position changes
                        watchId = navigator.geolocation.watchPosition(
                            sendLocationUpdate, 
                            handleError, 
                            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                        );
                    } else {
                        throw new Error(data.message || 'Failed to start tracking.');
                    }
                })
                .catch(err => {
                    handleError(err);
                    startBtn.disabled = false;
                });
            }, handleError);
        }

        // This function is called every time the device's location changes
        function sendLocationUpdate(position) {
            const { latitude, longitude } = position.coords;
            liveCoordsDiv.textContent = `Lat: ${latitude.toFixed(5)}, Lon: ${longitude.toFixed(5)}`;

            if (!currentTrackingId) return;

            // Send update to the server
            fetch(API_ENDPOINT, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'update',
                    tracking_id: currentTrackingId,
                    lat: latitude,
                    lon: longitude
                })
            }).catch(console.error); // Log errors silently in the console
        }

        function endTracking() {
            if (!confirm('Are you sure you want to end this tracking session?')) {
                return;
            }

            endBtn.disabled = true;

            // 1. Stop watching for location changes
            if (watchId) {
                navigator.geolocation.clearWatch(watchId);
                watchId = null;
            }

            // 2. Get the final position and end the session
            navigator.geolocation.getCurrentPosition(position => {
                const { latitude, longitude } = position.coords;
                
                fetch(API_ENDPOINT, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'end',
                        tracking_id: currentTrackingId,
                        lat: latitude,
                        lon: longitude
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        stopTimer();
                        statusMessage.textContent = `Session #${currentTrackingId} ended.`;
                    } else {
                        throw new Error(data.message || 'Failed to end tracking.');
                    }
                })
                .catch(handleError)
                .finally(resetState);
            }, err => {
                handleError(err);
                resetState(); // Reset even if final location fails
            });
        }
        
        // --- Helper Functions ---
        function startTimer() {
            seconds = 0;
            timerInterval = setInterval(() => {
                seconds++;
                timerDiv.textContent = formatTime(seconds);
            }, 1000);
        }

        function stopTimer() {
            clearInterval(timerInterval);
        }

        function formatTime(totalSeconds) {
            const h = Math.floor(totalSeconds / 3600).toString().padStart(2, '0');
            const m = Math.floor((totalSeconds % 3600) / 60).toString().padStart(2, '0');
            const s = (totalSeconds % 60).toString().padStart(2, '0');
            return `${h}:${m}:${s}`;
        }
        
        function handleError(error) {
            console.error('Geolocation Error:', error);
            statusMessage.textContent = `Error: ${error.message}`;
            resetState();
        }

        function resetState() {
            startBtn.disabled = false;
            endBtn.disabled = true;
            currentTrackingId = null;
            liveCoordsDiv.textContent = '--';
        }
    </script>
</body>
</html>