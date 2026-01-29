<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pagination parameters
    $limit = 50; // 50 locations per page
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;

    // Fetch total number of unique users with non-null coordinates
    $stmt = $pdo->query("SELECT COUNT(DISTINCT username) FROM scan_logs WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Fetch the most recent location for each user with pagination
    $stmt = $pdo->prepare("
        SELECT s1.id, s1.username, s1.branch, s1.folder, s1.timestamp, s1.latitude, s1.longitude, s1.status, s1.address 
        FROM scan_logs s1
        INNER JOIN (
            SELECT username, MAX(timestamp) as max_timestamp
            FROM scan_logs
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            GROUP BY username
        ) s2 ON s1.username = s2.username AND s1.timestamp = s2.max_timestamp
        ORDER BY s1.timestamp DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch unique usernames and branches for filters
    $stmt = $pdo->query("SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL ORDER BY username");
    $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT DISTINCT branch FROM scan_logs WHERE branch IS NOT NULL ORDER BY branch");
    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    ?>
    <div class="alert alert-danger">មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ។</div>
    <?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផែនទីទីតាំងស្កេន</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Battambang', sans-serif; }
        #map { height: 600px; width: 100%; margin-top: 20px; }
        .info-window { max-width: 300px; }
        .error-message { display: none; color: red; text-align: center; margin-top: 10px; }
        .error-message.show-error { display: block; }
        .loading-spinner { display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000; }
        .loading-spinner.show { display: block; }
        @media (max-width: 768px) { #map { height: 400px; } }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center my-4">ផែនទីទីតាំងស្កេន</h1>
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="usernameFilter" class="form-label">អ្នកប្រើប្រាស់:</label>
                <select id="usernameFilter" class="form-select">
                    <option value="">ទាំងអស់</option>
                    <?php foreach ($usernames as $username): ?>
                        <option value="<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($username); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="branchFilter" class="form-label">សាខា:</label>
                <select id="branchFilter" class="form-select">
                    <option value="">ទាំងអស់</option>
                    <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="startDate" class="form-label">កាលបរិច្ឆេទចាប់ផ្តើម:</label>
                <input type="date" id="startDate" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">កាលបរិច្ឆេទបញ្ចប់:</label>
                <input type="date" id="endDate" class="form-control">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="travelMode" class="form-label">របៀបធ្វើដំណើរ:</label>
                <select id="travelMode" class="form-select">
                    <option value="car">រថយន្ត</option>
                    <option value="foot">ថ្មើរជើង</option>
                </select>
            </div>
        </div>
        <div id="locationError" class="error-message"></div>
        <div id="map"></div>
        <div class="pagination mt-3 d-flex justify-content-center">
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">មុន</a>
                    </li>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">បន្ទាប់</a>
                    </li>
                </ul>
            </nav>
        </div>
        <div id="loadingSpinner" class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">កំពុងផ្ទុក...</span>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
/**
 * Geofence and Telegram Notification Script
 * Integrates with existing Leaflet map to send Telegram alerts for out-of-bounds locations
 */
const GEO_FENCE_CENTER = [11.562108, 104.888535]; // Phnom Penh center
const GEO_FENCE_RADIUS_KM = 10; // 10 km radius
const TELEGRAM_BOT_TOKEN = 'YOUR_TELEGRAM_BOT_TOKEN'; // Replace with your bot token
const TELEGRAM_CHAT_ID = 'YOUR_TELEGRAM_CHAT_ID'; // Replace with your chat ID
const notifiedLocations = new Set(); // Track notified locations to avoid duplicates

// Global variables
let map;
let markers = [];
let routingControl = null;
let userMarker = null;
let lastUpdateTimestamp = null;
let markerClusterGroup = null;
const allLocations = <?php echo json_encode($locations, JSON_UNESCAPED_UNICODE); ?>;
const GRAPHHOPPER_API_KEY = '7eccea67-a52c-46dd-84de-a71a46f81944';

// Calculate distance between two coordinates (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c; // Distance in km
}

// Check if a location is outside the geofence
function isOutsideGeofence(lat, lng) {
    const distance = calculateDistance(lat, lng, GEO_FENCE_CENTER[0], GEO_FENCE_CENTER[1]);
    return distance > GEO_FENCE_RADIUS_KM;
}

// Send Telegram notification
async function sendTelegramNotification(username, lat, lng, address) {
    try {
        const message = `⚠️ ការព្រមាន: អ្នកប្រើ ${username} នៅក្រៅតំបន់!\n` +
                       `📍 ទីតាំង: (${lat}, ${lng})\n` +
                       `🏠 អាសយដ្ឋាន: ${address || 'មិនមាន'}\n` +
                       `🕒 ពេលវេលា: ${new Date().toLocaleString('km-KH')}`;
        const url = `https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage`;
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                chat_id: TELEGRAM_CHAT_ID,
                text: message,
                parse_mode: 'Markdown'
            })
        });
        if (!response.ok) {
            throw new Error(`Telegram API error: ${response.statusText}`);
        }
        console.log(`Telegram notification sent for ${username}`);
    } catch (error) {
        console.error('Error sending Telegram notification:', error);
        showError('មិនអាចផ្ញើសារទៅ Telegram បានទេ។');
    }
}

// Initialize map
function initMap() {
    try {
        map = L.map('map').setView([11.562108, 104.888535], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        markerClusterGroup = L.markerClusterGroup({
            maxClusterRadius: 50,
            disableClusteringAtZoom: 18,
            spiderfyOnMaxZoom: false
        });
        map.addLayer(markerClusterGroup);

        updateMarkers();
        setInterval(fetchLatestLocations, 30000); // Poll every 30 seconds
        document.getElementById('usernameFilter').addEventListener('change', updateMarkers);
        document.getElementById('branchFilter').addEventListener('change', updateMarkers);
        document.getElementById('startDate').addEventListener('change', updateMarkers);
        document.getElementById('endDate').addEventListener('change', updateMarkers);

        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                position => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    updateUserLocation(lat, lng);
                },
                error => {
                    console.error('Geolocation error:', error.message);
                    showError('មិនអាចតាមដានទីតាំងបច្ចុប្បន្នបានទេ។');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }
    } catch (error) {
        console.error('Map initialization error:', error);
        showError('មានបញ្ហាក្នុងការផ្ទុកផែនទី។');
    }
}

// Utility function to show errors
function showError(message) {
    const errorDiv = document.getElementById('locationError');
    errorDiv.textContent = message;
    errorDiv.classList.add('show-error');
}

// Update markers based on filters
function updateMarkers() {
    try {
        markerClusterGroup.clearLayers();
        markers = markers.filter(m => m.locationId === 'user'); // Keep user marker

        const usernameFilter = document.getElementById('usernameFilter').value;
        const branchFilter = document.getElementById('branchFilter').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;

        const filteredLocations = allLocations.filter(location => {
            const timestamp = new Date(location.timestamp);
            return (
                (!usernameFilter || location.username === usernameFilter) &&
                (!branchFilter || location.branch === branchFilter) &&
                (!startDate || timestamp >= new Date(startDate)) &&
                (!endDate || timestamp <= new Date(endDate))
            );
        });

        filteredLocations.forEach(location => addOrUpdateMarker(location));

        if (filteredLocations.length > 0) {
            const bounds = L.latLngBounds(filteredLocations.map(loc => [parseFloat(loc.latitude), parseFloat(loc.longitude)]));
            map.fitBounds(bounds);
        }
    } catch (error) {
        console.error('Error updating markers:', error);
        showError('មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាពទីតាំង។');
    }
}

// Add or update a marker
function addOrUpdateMarker(location, isUser = false) {
    try {
        const lat = parseFloat(location.latitude);
        const lng = parseFloat(location.longitude);
        if (isNaN(lat) || isNaN(lng)) {
            console.warn(`Invalid coordinates for ${location.username || 'unknown'}: ${location.latitude}, ${location.longitude}`);
            return;
        }

        let marker = markers.find(m => m.locationId === location.id);
        const newLatLng = [lat, lng];

        // Check geofence for non-user markers
        if (!isUser && isOutsideGeofence(lat, lng)) {
            const notificationKey = `${location.username}-${lat}-${lng}`;
            if (!notifiedLocations.has(notificationKey)) {
                sendTelegramNotification(location.username, lat, lng, location.address);
                notifiedLocations.add(notificationKey);
            }
        }

        if (marker) {
            const currentLatLng = marker.getLatLng();
            if (currentLatLng.lat !== lat || currentLatLng.lng !== lng) {
                smoothMoveMarker(marker, newLatLng);
                marker.setPopupContent(`
                    <div class="info-window">
                        <h5>${location.username || 'មិនមានឈ្មោះ'}</h5>
                        <p><strong>សាខា:</strong> ${location.branch || 'មិនមាន'}</p>
                        <p><strong>ថតឯកសារ:</strong> ${location.folder || 'មិនមាន'}</p>
                        <p><strong>ពេលវេលា:</strong> ${new Date(location.timestamp).toLocaleString('km-KH')}</p>
                        <p><strong>ស្ថានភាព:</strong> ${location.status === 'Good' ? 'ល្អ' : 'យឺត'}</p>
                        <p><strong>អាសយដ្ឋាន:</strong> ${location.address || 'មិនមាន'}</p>
                        <a href="edit_log.php?id=${location.id}" class="btn btn-sm btn-warning">កែ</a>
                        <button class="btn btn-sm btn-primary" onclick="showDirections(${lat}, ${lng})">ណែនាំផ្លូវ</button>
                    </div>
                `);
            }
        } else {
            const markerIcon = L.divIcon({
                className: 'custom-marker',
                html: `
                    <div style="
                        background: ${location.status === 'Good' ? '#28a745' : '#dc3545'};
                        width: 20px;
                        height: 20px;
                        border-radius: 50%;
                        border: 2px solid white;
                    "></div>
                    <div style="
                        background: rgba(0,0,0,0.7);
                        color: white;
                        padding: 2px 5px;
                        border-radius: 3px;
                        font-size: 12px;
                        margin-top: 5px;
                        white-space: nowrap;
                    ">
                        ${location.username || 'មិនមានឈ្មោះ'}
                    </div>
                `,
                iconSize: [30, 50],
                iconAnchor: [15, 50],
                popupAnchor: [0, -50]
            });

            marker = L.marker(newLatLng, { icon: markerIcon })
                .bindPopup(`
                    <div class="info-window">
                        <h5>${location.username || 'មិនមានឈ្មោះ'}</h5>
                        <p><strong>សាខា:</strong> ${location.branch || 'មិនមាន'}</p>
                        <p><strong>ថតឯកសារ:</strong> ${location.folder || 'មិនមាន'}</p>
                        <p><strong>ពេលវេលា:</strong> ${new Date(location.timestamp).toLocaleString('km-KH')}</p>
                        <p><strong>ស្ថានភាព:</strong> ${location.status === 'Good' ? 'ល្អ' : 'យឺត'}</p>
                        <p><strong>អាសយដ្ឋាន:</strong> ${location.address || 'មិនមាន'}</p>
                        <a href="edit_log.php?id=${location.id}" class="btn btn-sm btn-warning">កែ</a>
                        <button class="btn btn-sm btn-primary" onclick="showDirections(${lat}, ${lng})">ណែនាំផ្លូវ</button>
                    </div>
                `);

            marker.locationId = location.id;
            marker.timestamp = new Date(location.timestamp).getTime();
            markers.push(marker);
            if (!isUser) {
                markerClusterGroup.addLayer(marker);
            } else {
                marker.addTo(map);
            }
        }

        if (!isUser && markers.length === 1) {
            map.panTo(newLatLng, { animate: true, duration: 1 });
        }
    } catch (error) {
        console.error('Error adding/updating marker:', error);
    }
}

// Smoothly move a marker to new coordinates
function smoothMoveMarker(marker, newLatLng, duration = 1000) {
    try {
        const startLatLng = marker.getLatLng();
        const startTime = performance.now();
        function animate(time) {
            const elapsed = time - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const lat = startLatLng.lat + (newLatLng[0] - startLatLng.lat) * progress;
            const lng = startLatLng.lng + (newLatLng[1] - startLatLng.lng) * progress;
            marker.setLatLng([lat, lng]);
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }
        requestAnimationFrame(animate);
    } catch (error) {
        console.error('Error moving marker:', error);
    }
}

// Fetch and update locations in real-time
async function fetchLatestLocations() {
    try {
        const loadingSpinner = document.getElementById('loadingSpinner');
        loadingSpinner.classList.add('show');

        const response = await fetch('get_latest_locations.php', {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
            console.error('Server error:', data.error);
            showError(`កំហុសពី server: ${data.error}`);
            return;
        }

        if (!Array.isArray(data)) {
            console.error('Invalid data format:', data);
            showError('ទម្រង់ទិន្នន័យមិនត្រឹមត្រូវ។');
            return;
        }

        const newLocationIds = new Set(data.map(loc => loc.id));
        const now = new Date().getTime();
        markers = markers.filter(marker => {
            if (marker.locationId === 'user') return true;
            if (!newLocationIds.has(marker.locationId)) {
                markerClusterGroup.removeLayer(marker);
                return false;
            }
            if ((now - marker.timestamp) / 1000 / 60 > 5) {
                markerClusterGroup.removeLayer(marker);
                return false;
            }
            return true;
        });

        for (const location of data) {
            try {
                if (!location.id || !location.timestamp || !location.latitude || !location.longitude) {
                    console.warn('Missing required fields:', location);
                    continue;
                }
                const timestamp = new Date(location.timestamp).getTime();
                if (isNaN(timestamp)) {
                    console.warn('Invalid timestamp:', location.timestamp);
                    continue;
                }
                if (!lastUpdateTimestamp || timestamp > lastUpdateTimestamp) {
                    lastUpdateTimestamp = timestamp;
                }
                addOrUpdateMarker(location);
            } catch (error) {
                console.error('Error processing location:', error, location);
            }
        }

        console.log(`Processed ${data.length} locations`);
    } catch (error) {
        console.error('Error fetching latest locations:', error);
        showError(`មានបញ្ហាក្នុងការទាញទីតាំងថ្មី: ${error.message}`);
    } finally {
        loadingSpinner.classList.remove('show');
    }
}

// Update user location
function updateUserLocation(lat, lng) {
    try {
        const userLocation = {
            id: 'user',
            username: 'អ្នកប្រើបច្ចុប្បន្ន',
            latitude: lat,
            longitude: lng,
            status: 'Good',
            timestamp: new Date().toISOString()
        };

        if (userMarker) {
            smoothMoveMarker(userMarker, [lat, lng]);
        } else {
            const userIcon = L.divIcon({
                className: 'custom-marker',
                html: `
                    <div style="
                        background: #007bff;
                        width: 20px;
                        height: 20px;
                        border-radius: 50%;
                        border: 2px solid white;
                    "></div>
                    <div style="
                        background: rgba(0,0,0,0.7);
                        color: white;
                        padding: 2px 5px;
                        border-radius: 3px;
                        font-size: 12px;
                        margin-top: 5px;
                        white-space: nowrap;
                    ">
                        អ្នកប្រើបច្ចុប្បន្ន
                    </div>
                `,
                iconSize: [30, 50],
                iconAnchor: [15, 50],
                popupAnchor: [0, -50]
            });

            userMarker = L.marker([lat, lng], { icon: userIcon })
                .addTo(map)
                .bindPopup('<div class="info-window"><h5>ទីតាំងរបស់អ្នក</h5></div>');
            userMarker.locationId = 'user';
            markers.push(userMarker);
        }
    } catch (error) {
        console.error('Error updating user location:', error);
    }
}

// Routing functions
function showDirections(lat, lng) {
    try {
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }

        const travelMode = document.getElementById('travelMode').value;
        const errorDiv = document.getElementById('locationError');
        errorDiv.textContent = '';
        errorDiv.classList.remove('show-error');

        getCurrentLocation(
            startLatLng => {
                setupRouting(startLatLng, [lat, lng], travelMode);
            },
            errorMessage => {
                errorDiv.textContent = errorMessage;
                errorDiv.classList.add('show-error');
            }
        );
    } catch (error) {
        console.error('Error showing directions:', error);
    }
}

function getCurrentLocation(callback, onError) {
    try {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                position => {
                    const currentLatLng = [position.coords.latitude, position.coords.longitude];
                    callback(currentLatLng);
                },
                error => {
                    console.error('Geolocation error:', error.message);
                    onError('មិនអាចចាប់ទីតាំងបច្ចុប្បន្នបានទេ។ សូមផ្លាស់ប្តូរទៅកម្មវិធីរុករកផ្សេង ឬពិនិត្យការអនុញ្ញាត។');
                }
            );
        } else {
            console.error('Geolocation not supported by this browser.');
            onError('កម្មវិធីរុករករបស់អ្នកមិនគាំទ្រការចាប់ទីតាំងទេ។ សូមប្រើកម្មវិធីរុករកផ្សេង។');
        }
    } catch (error) {
        console.error('Error getting current location:', error);
    }
}

function setupRouting(startLatLng, endLatLng, travelMode) {
    try {
        const errorDiv = document.getElementById('locationError');
        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(startLatLng),
                L.latLng(endLatLng)
            ],
            routeWhileDragging: true,
            lineOptions: {
                styles: [{ color: '#007bff', weight: 4 }]
            },
            router: new L.Routing.GraphHopper(GRAPHHOPPER_API_KEY, {
                serviceUrl: 'https://graphhopper.com/api/1/route',
                urlParameters: {
                    vehicle: travelMode
                }
            })
        }).on('routesfound', function(e) {
            console.log('Route found:', e.routes);
        }).on('routingerror', function(e) {
            console.error('Routing error:', e.error);
            errorDiv.textContent = 'មិនអាចគណនាផ្លូវបានទេ។ សូមពិនិត្យ API Key ឬព្យាយាមម្តងទៀត។';
            errorDiv.classList.add('show-error');
            if (routingControl) {
                map.removeControl(routingControl);
                routingControl = null;
            }
        }).addTo(map);
    } catch (error) {
        console.error('Error setting up routing:', error);
    }
}

initMap();
    </script>
</body>
</html>