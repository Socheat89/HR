<?php
// view_trips.php - Updated with Live Path Drawing
require 'db.php';
date_default_timezone_set('Asia/Phnom_Penh');

try {
    // Fetch 'is_live' status along with other trip data
    $stmt = $pdo->query("SELECT * FROM trips ORDER BY start_time DESC");
    $trackings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ... error handling ... */ }

function format_duration($seconds) { /* ... same function ... */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Tracking Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* All your existing CSS from the previous version goes here */
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f4f7fa; margin: 0; height: 100vh; display: flex; flex-direction: column; }
        .dashboard-container { display: flex; flex-grow: 1; overflow: hidden; }
        .tracking-list-panel { width: 380px; background-color: #fff; border-right: 1px solid #e0e0e0; padding: 20px; overflow-y: auto; }
        .tracking-card { background-color: #fff; border: 2px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .tracking-card.selected { border-color: #0d6efd; }
        .tracking-card button { width: 100%; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .route-button { background-color: #198754; }
        .live-track-button { background-color: #dc3545; } /* Red for Live */
        .live-track-button:disabled { background-color: #6c757d; cursor: not-allowed; }
        .map-panel { flex-grow: 1; display: flex; flex-direction: column; padding: 20px; }
        #map { flex-grow: 1; border-radius: 8px; }
        .user-marker-icon { font-size: 28px; text-align: center; }
    </style>
</head>
<body>
    <header><h1>Tracking Dashboard</h1></header>
    <div class="dashboard-container">
        <div class="tracking-list-panel">
            <?php foreach ($trackings as $tracking): ?>
                <div class="tracking-card" id="tracking-card-<?php echo $tracking['id']; ?>">
                    <h3>Tracking #<?php echo htmlspecialchars($tracking['id']); ?></h3>
                    <p>Status: 
                        <strong style="color: <?php echo $tracking['is_live'] ? '#dc3545' : '#198754'; ?>">
                            <?php echo $tracking['is_live'] ? '🔴 Live' : '🟢 Finished'; ?>
                        </strong>
                    </p>
                    <button class="live-track-button" data-tracking-id="<?php echo $tracking['id']; ?>" <?php if (!$tracking['is_live']) echo 'disabled'; ?>>
                        Track Live Path
                    </button>
                    </div>
            <?php endforeach; ?>
        </div>
        <div class="map-panel">
            <h2 id="map-title">Select a live tracking session to view its path</h2>
            <div id="map"></div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const map = L.map('map').setView([11.5564, 104.9282], 13); // Default to Phnom Penh
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    let liveDataInterval = null;
    let liveUserMarker = null;
    let livePathPolyline = null;
    let activeTrackingId = null;

    document.querySelectorAll('.live-track-button').forEach(button => {
        button.addEventListener('click', function() {
            const trackingId = this.dataset.trackingId;

            // If clicking the same button again, stop tracking
            if (activeTrackingId === trackingId) {
                stopLiveTracking();
                return;
            }
            
            startLiveTracking(trackingId);
        });
    });

    function startLiveTracking(trackingId) {
        stopLiveTracking(); // Stop any previous tracking
        activeTrackingId = trackingId;

        document.getElementById(`tracking-card-${trackingId}`).classList.add('selected');
        document.querySelector(`[data-tracking-id='${trackingId}']`).textContent = 'Stop Tracking';

        // Fetch initial data and start polling
        fetchAndDrawPath(trackingId);
        liveDataInterval = setInterval(() => fetchAndDrawPath(trackingId), 5000); // Poll every 5 seconds
    }

    function stopLiveTracking() {
        if (!activeTrackingId) return;

        clearInterval(liveDataInterval);
        document.getElementById(`tracking-card-${activeTrackingId}`).classList.remove('selected');
        const button = document.querySelector(`[data-tracking-id='${activeTrackingId}']`);
        if (button) button.textContent = 'Track Live Path';

        liveDataInterval = null;
        activeTrackingId = null;
    }

    function fetchAndDrawPath(trackingId) {
        fetch(`get_live_data.php?id=${trackingId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    throw new Error(data.message || 'Failed to fetch data');
                }

                // If trip is no longer live, stop polling
                if (!data.is_live) {
                    document.querySelector(`[data-tracking-id='${trackingId}']`).disabled = true;
                    document.getElementById(`tracking-card-${trackingId}`).querySelector('strong').textContent = '🟢 Finished';
                    stopLiveTracking();
                }

                if (data.path && data.path.length > 0) {
                    // --- Draw or Update the Path ---
                    if (!livePathPolyline) {
                        livePathPolyline = L.polyline(data.path, { color: '#0d6efd', weight: 5 }).addTo(map);
                    } else {
                        livePathPolyline.setLatLngs(data.path);
                    }

                    // --- Create or Move the User Marker ---
                    const latestPoint = data.latest_point;
                    if (!liveUserMarker) {
                        const userIcon = L.divIcon({
                            html: '<div class="user-marker-icon">🚶</div>',
                            className: '', // remove default background
                            iconSize: [30, 30]
                        });
                        liveUserMarker = L.marker(latestPoint, { icon: userIcon }).addTo(map);
                    } else {
                        liveUserMarker.setLatLng(latestPoint);
                    }
                    
                    // Center the map on the latest point
                    map.setView(latestPoint, 16);
                }
                
                document.getElementById('map-title').textContent = `Now Tracking Live Session #${trackingId}`;
            })
            .catch(error => {
                console.error("Tracking Error:", error);
                stopLiveTracking();
            });
    }
});
</script>
</body>
</html>