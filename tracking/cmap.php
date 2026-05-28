<?php
// PHP block for fetching initial customers (remains the same)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

$customers = [];
$sql = "SELECT name, latitude, longitude FROM customers ORDER BY name ASC";
$result = $conn->query($sql);

if ($result === false) {
    die("SQL Error: " . $conn->error);
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $customers[] = [
            'name' => $row['name'],
            'coordinates' => [(float)$row['latitude'], (float)$row['longitude']]
        ];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <link rel="icon" href="https://i.ibb.co/0QFffT3/Head-2.png" type="image/png">
  <title>··∏·è·∂···¢·è·∑·ê·∑····∂···¢··</title>
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />
  <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />

  <style>
    body.modal-open { overflow: hidden; }
    .customer-label {
      background-color: rgba(255, 255, 255, 0.85); border: none; border-radius: 4px;
      box-shadow: none; color: #333; font-size: 10px; font-weight: bold;
      padding: 2px 5px; white-space: nowrap;
    }
    #listPanel { transition: height 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    body.dragging, body.dragging * {
      cursor: grabbing !important;
      user-select: none;
      -webkit-user-select: none;
    }
  </style>
</head>
<body class="overflow-hidden bg-gray-100">

  <div class="relative h-screen w-screen">
    <div id="map" class="h-full w-full z-0"></div>
    <div class="absolute top-4 left-1/2 -translate-x-1/2 z-10 flex items-center gap-2 bg-white p-2 rounded-lg shadow-lg w-11/12 max-w-md">
      <input type="text" id="searchBox" class="flex-1 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="········¢·è·∑·ê·∑··..." />
      <button id="showRoutingBtn" class="px-3 py-2 font-semibold text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
      </button>
      <button id="addLocationBtn" class="px-3 py-2 font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
      </button>
    </div>

    <div id="listPanel" class="fixed bottom-0 left-0 right-0 z-10 bg-white shadow-lg rounded-t-lg flex flex-col h-40 md:absolute md:top-24 md:left-4 md:bottom-auto md:right-auto md:w-full md:max-w-sm md:h-auto md:max-h-[calc(100vh-8rem)] md:rounded-lg">
        <div id="listHandle" class="w-full py-2 flex-shrink-0 flex justify-center items-center cursor-grab bg-gray-50 rounded-t-lg md:hidden">
            <div class="w-12 h-1.5 bg-gray-300 rounded-full"></div>
        </div>
        <h2 class="text-xl font-bold text-gray-800 mb-2 flex-shrink-0 px-4 pt-2">·····∏·¢·è·∑·ê·∑··</h2>
        <ul id="customerList" class="space-y-1 overflow-y-auto px-4 pb-4"></ul>
    </div>
  </div>

  <div id="addLocationModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-11/12 max-w-md">
      <h3 class="text-2xl font-bold mb-4">····ê····∏·è·∂···ê···∏</h3>
      <p id="modal-message" class="mb-4 text-center font-semibold"></p>
      <form id="addLocationForm">
        <div class="mb-4">
            <button type="button" id="getLocationBtn" class="w-full px-6 py-2 bg-green-600 text-white font-semibold rounded-md hover:bg-green-700 transition-colors flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" /></svg>
                ··∂······∏·è·∂·······ª······
            </button>
        </div>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div><label for="latitude" class="block text-sm font-semibold mb-1">······π· (Latitude)</label><input type="text" id="latitude" name="latitude" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly required></div>
          <div><label for="longitude" class="block text-sm font-semibold mb-1">········· (Longitude)</label><input type="text" id="longitude" name="longitude" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100" readonly required></div>
        </div>
        <div class="mb-6"><label for="customerName" class="block text-sm font-semibold mb-1">······¢·è·∑·ê·∑··</label><input type="text" id="customerName" name="name" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></div>
        <div class="flex justify-end gap-4"><button type="button" id="cancelAddBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">······</button><button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">·····∂··ª·</button></div>
      </form>
    </div>
  </div>

  <div id="routingModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white p-6 rounded-lg shadow-2xl w-11/12 max-w-md">
      <h3 class="text-2xl font-bold mb-4">··∂·····è·æ···∂·····æ····æ·</h3>
      <p id="routing-modal-message" class="mb-4 text-center font-semibold"></p>
      <form id="routingForm">
        <div class="mb-6"><label for="endPoint" class="block text-sm font-semibold mb-1">····∂····∏·è·∂·· (To)</label><select id="endPoint" class="w-full px-3 py-2 border border-gray-300 rounded-md" required></select></div>
        <div class="flex justify-end gap-4"><button type="button" id="cancelRoutingBtn" class="px-5 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">······</button><button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">··∂·····è·æ·</button></div>
      </form>
    </div>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
  
  <script>
    const customers = <?php echo json_encode($customers); ?>;
    const map = L.map('map', { center: [12.5, 104.9], zoom: 7, zoomControl: false });
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '¬© OpenStreetMap' }).addTo(map);
    let allMarkers = [];
    const markersCluster = L.markerClusterGroup();
    const bodyEl = document.body;
    const searchBox = document.getElementById('searchBox');
    const listPanel = document.getElementById('listPanel');
    const listHandle = document.getElementById('listHandle');
    const customerListEl = document.getElementById('customerList');
    const addLocationModal = document.getElementById('addLocationModal');
    const addLocationBtn = document.getElementById('addLocationBtn');
    const cancelAddBtn = document.getElementById('cancelAddBtn');
    const getLocationBtn = document.getElementById('getLocationBtn');
    const addLocationForm = document.getElementById('addLocationForm');
    const modalMessageEl = document.getElementById('modal-message');
    const routingModal = document.getElementById('routingModal');
    const showRoutingBtn = document.getElementById('showRoutingBtn');
    const cancelRoutingBtn = document.getElementById('cancelRoutingBtn');
    const routingForm = document.getElementById('routingForm');
    const endPointSelect = document.getElementById('endPoint');
    const routingModalMessageEl = document.getElementById('routing-modal-message');

    // NEW: Variable to hold the location tracking process ID
    let locationWatcher = null;

    // --- UTILITY FUNCTIONS ---
    function createMarker(customer) {
        const marker = L.marker(customer.coordinates).bindTooltip(customer.name, { permanent: true, direction: 'top', offset: [0, -10], className: 'customer-label' });
        marker.on('click', () => { map.setView(customer.coordinates, 17); });
        marker.options.customerName = customer.name;
        return marker;
    }

    function addCustomerToList(customer) {
        const li = document.createElement('li');
        li.className = 'p-3 border-b border-gray-200 cursor-pointer transition-all duration-300 hover:bg-blue-50';
        li.textContent = customer.name;
        li.addEventListener('click', () => {
            const googleMapsUrl = `https://www.google.com/maps?q=${customer.coordinates[0]},${customer.coordinates[1]}`;
            window.open(googleMapsUrl, '_blank');
        });
        customerListEl.prepend(li);
    }

    function refreshCustomerListAndMarkers(customerArray) {
        customerListEl.innerHTML = '';
        markersCluster.clearLayers();
        const sortedCustomers = [...customerArray].sort((a,b) => a.name.localeCompare(b.name));
        const currentMarkers = [];
        sortedCustomers.forEach(customer => {
            addCustomerToList(customer);
            const marker = allMarkers.find(m => m.options.customerName === customer.name);
            if (marker) currentMarkers.push(marker);
        });
        if (currentMarkers.length > 0) markersCluster.addLayers(currentMarkers);
    }

    function performSearch() {
        const searchTerm = searchBox.value.toLowerCase().trim();
        const filteredCustomers = customers.filter(c => c.name.toLowerCase().includes(searchTerm));
        refreshCustomerListAndMarkers(filteredCustomers);
    }
    
    // --- LIVE TRACKING FUNCTIONS ---
    function startLocationTracking(driverId) {
        stopLocationTracking(); // Stop any previous watcher
        console.log(`Starting location tracking for driver ID: ${driverId}`);

        if (!navigator.geolocation) {
            console.error("Geolocation is not supported by this browser.");
            return;
        }

        locationWatcher = navigator.geolocation.watchPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                console.log(`New position: ${lat}, ${lng}. Sending to server...`);
                
                const formData = new FormData();
                formData.append('driver_id', driverId);
                formData.append('lat', lat);
                formData.append('lng', lng);
                
                fetch('../tracking/update_location.php', { method: 'POST', body: formData })
                .then(response => {
                    if (!response.ok) console.warn("Server responded with an error.");
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        console.log("Location updated on server.");
                    } else {
                        console.warn("Server failed to update location:", data.message);
                    }
                })
                .catch(error => console.error('Network error while updating location:', error));
            },
            (error) => console.error(`Geolocation Error: ${error.message}`),
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
        );
    }

    function stopLocationTracking() {
        if (locationWatcher !== null) {
            navigator.geolocation.clearWatch(locationWatcher);
            locationWatcher = null;
            console.log("Location tracking stopped.");
        }
    }

    // --- MODAL AND FORM HANDLING ---
    function openRoutingModal() {
        endPointSelect.innerHTML = '';
        customers.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.coordinates.join(',');
            option.textContent = customer.name;
            endPointSelect.appendChild(option);
        });
        routingModalMessageEl.textContent = '';
        routingModal.classList.remove('hidden');
        bodyEl.classList.add('modal-open');
    }
    function closeRoutingModal() {
        routingModal.classList.add('hidden');
        bodyEl.classList.remove('modal-open');
    }
    function handleFindRoute(e) {
        e.preventDefault();
        routingModalMessageEl.textContent = '····ª·····æ··ª···∏·è·∂·······ª······...';
        routingModalMessageEl.className = 'mb-4 text-center font-semibold text-blue-600';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                routingModalMessageEl.textContent = '····ª······æ·è··∂·····æ····æ·...';
                
                const startLat = position.coords.latitude;
                const startLng = position.coords.longitude;
                const endCoords = endPointSelect.value.split(',').map(Number);
                const toName = endPointSelect.options[endPointSelect.selectedIndex].text;

                const formData = new FormData();
                formData.append('start_lat', startLat);
                formData.append('start_lng', startLng);
                formData.append('end_lat', endCoords[0]);
                formData.append('end_lng', endCoords[1]);
                formData.append('customer_name', toName);
                
                // IMPORTANT: Replace this with the actual logged-in driver's ID
                const driverId = 1; 
                formData.append('driver_id', driverId);

                fetch('../tracking/start_trip.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // CORRECTED LINE 1
                        routingModalMessageEl.textContent = '··∂·····æ····æ···∂···∂·····è·æ·! ····ª···æ······∏··∂·····º·...';
                        routingModalMessageEl.className = 'mb-4 text-center font-semibold text-green-600';
                        
                        const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&origin=${startLat},${startLng}&destination=${endCoords.join(',')}&travelmode=driving`;
                        window.open(googleMapsUrl, '_blank');
                        
                        // Start tracking location in the background
                        startLocationTracking(data.driver_id || driverId);

                        setTimeout(closeRoutingModal, 3000);
                    } else {
                        routingModalMessageEl.textContent = data.message || '··∂·····†·∂····ª···∂······æ·è··∂·····æ····æ··';
                        routingModalMessageEl.className = 'mb-4 text-center font-semibold text-red-600';
                    }
                })
                .catch(error => {
                    // CORRECTED LINE 2
                    routingModalMessageEl.textContent = '··∂·····†·∂····è·∂··';
                    routingModalMessageEl.className = 'mb-4 text-center font-semibold text-red-600';
                });
            },
            (error) => {
                // CORRECTED LINE 3
                routingModalMessageEl.textContent = "··∑··¢·∂·····è···∏·è·∂····∂···Å· ··º··¢··ª····∂·è·±········∑··∏··ª··· (Browser) ····æ····∂····∏·è·∂·······¢····";
                routingModalMessageEl.className = 'mb-4 text-center font-semibold text-red-600';
            }, 
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    function openAddModal() { addLocationForm.reset(); modalMessageEl.textContent = ''; addLocationModal.classList.remove('hidden'); bodyEl.classList.add('modal-open'); }
    function closeAddModal() { addLocationModal.classList.add('hidden'); bodyEl.classList.remove('modal-open'); }
    
    getLocationBtn.addEventListener('click', () => {
        if (!navigator.geolocation) { modalMessageEl.textContent = 'Geolocation is not supported.'; modalMessageEl.className = 'mb-4 text-center font-semibold text-red-600'; return; }
        modalMessageEl.textContent = '····ª·‚····æ··ª·‚··∏·è·∂··...'; modalMessageEl.className = 'mb-4 text-center font-semibold text-blue-600';
        navigator.geolocation.getCurrentPosition( (position) => {
                document.getElementById('latitude').value = position.coords.latitude.toFixed(8); 
                document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
                modalMessageEl.textContent = '··∂··‚··∂·‚··∏·è·∂··‚···‚·····ê·!'; modalMessageEl.className = 'mb-4 text-center font-semibold text-green-600';
                document.getElementById('customerName').focus();
            }, (error) => { modalMessageEl.textContent = "Error: " + (error.message || "Could not get location."); modalMessageEl.className = 'mb-4 text-center font-semibold text-red-600'; }, { enableHighAccuracy: true }
        );
    });

    addLocationForm.addEventListener('submit', function(e) {
        e.preventDefault(); const formData = new FormData(this);
        modalMessageEl.textContent = 'Saving...'; modalMessageEl.className = 'mb-4 text-center font-semibold text-blue-600';
        fetch('add_customer.php', { method: 'POST', body: formData }).then(r => r.json()).then(data => {
            if (data.success) {
                const newCustomer = data.newCustomer; customers.push(newCustomer);
                const newMarker = createMarker(newCustomer); allMarkers.push(newMarker);
                refreshCustomerListAndMarkers(customers); map.setView(newMarker.getLatLng(), 17);
                closeAddModal();
            } else { modalMessageEl.textContent = data.message || 'Error.'; modalMessageEl.className = 'mb-4 text-center font-semibold text-red-600'; }
        }).catch(error => { modalMessageEl.textContent = 'Network error.'; modalMessageEl.className = 'mb-4 text-center font-semibold text-red-600'; });
    });

    // --- EVENT LISTENERS & INITIALIZATION ---
    searchBox.addEventListener('keyup', performSearch);
    addLocationBtn.addEventListener('click', openAddModal);
    cancelAddBtn.addEventListener('click', closeAddModal);
    showRoutingBtn.addEventListener('click', openRoutingModal);
    cancelRoutingBtn.addEventListener('click', closeRoutingModal);
    routingForm.addEventListener('submit', handleFindRoute);

    const isMobile = window.innerWidth < 768;
    if (isMobile) {
        let isDragging = false; let startY, startHeight; const minHeight = 160; const maxHeight = window.innerHeight * 0.8;
        const dragStart = (e) => { isDragging = true; startY = e.pageY || e.touches[0].pageY; startHeight = listPanel.offsetHeight; listPanel.style.transition = 'none'; bodyEl.classList.add('dragging'); window.addEventListener('mousemove', dragging); window.addEventListener('touchmove', dragging, { passive: false }); window.addEventListener('mouseup', dragEnd); window.addEventListener('touchend', dragEnd); };
        const dragging = (e) => { if (!isDragging) return; e.preventDefault(); const currentY = e.pageY || e.touches[0].pageY; const deltaY = currentY - startY; let newHeight = startHeight - deltaY; newHeight = Math.max(minHeight, Math.min(newHeight, maxHeight)); listPanel.style.height = `${newHeight}px`; };
        const dragEnd = () => { if (!isDragging) return; isDragging = false; listPanel.style.transition = 'height 0.3s cubic-bezier(0.25, 0.8, 0.25, 1)'; bodyEl.classList.remove('dragging'); window.removeEventListener('mousemove', dragging); window.removeEventListener('touchmove', dragging); window.removeEventListener('mouseup', dragEnd); window.removeEventListener('touchend', dragEnd); const currentHeight = listPanel.offsetHeight; const threshold = (minHeight + maxHeight) / 2; listPanel.style.height = (currentHeight > threshold) ? `${maxHeight}px` : `${minHeight}px`; };
        listHandle.addEventListener('mousedown', dragStart); listHandle.addEventListener('touchstart', dragStart);
    }

    function initializeApp() {
        customers.forEach(customer => { const marker = createMarker(customer); allMarkers.push(marker); });
        map.addLayer(markersCluster); refreshCustomerListAndMarkers(customers);
        if (allMarkers.length > 0) { map.fitBounds(markersCluster.getBounds().pad(0.1)); }
    }
    
    initializeApp();
  </script>
</body>
</html>
