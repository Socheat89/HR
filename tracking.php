<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - តាមដានការធ្វើដំណើរ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <style>
        /* Style សម្រាប់ Desktop */
        body { margin: 0; padding: 0; display: flex; height: 100vh; font-family: 'Kantumruy Pro', sans-serif; }
        #sidebar { width: 350px; background-color: #f8fafc; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; transition: height 0.3s ease; }
        #map-container { flex-grow: 1; }
        #map { height: 100%; width: 100%; }
        .trip-item { cursor: pointer; }
        .trip-item.active { background-color: #e0f2fe; border-left: 4px solid #0ea5e9; }
        .leaflet-routing-container { display: none; }

        /* === CSS សម្រាប់អេក្រង់ទូរស័ព្ទ (Mobile Screen) === */
        @media (max-width: 768px) {
            body {
                flex-direction: column; /* ប្តូរពីដាក់ក្បែរគ្នា ទៅដាក់ត្រួតលើគ្នា */
            }

            #sidebar {
                width: 100%;
                height: 45vh; /* កម្ពស់ដំបូងសម្រាប់បញ្ជី */
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                flex-shrink: 0; /* ការពារកុំឱ្យវារួមតូច */
            }

            #map-container {
                height: 55vh; /* កម្ពស់សម្រាប់ផែនទី */
            }

            /* CSS សម្រាប់ពេលបិទ/បើក Sidebar */
            #sidebar.collapsed #trip-list,
            #sidebar.collapsed .text-sm,
            #sidebar.collapsed .text-xs {
                display: none; /* លាក់បញ្ជី និងអក្សរតូចៗពេលចុចបិទ */
            }

            #sidebar.collapsed {
                height: auto !important; /* កម្ពស់នឹងសម្របតាម Header */
            }
            
            #toggle-sidebar-btn.collapsed svg {
                transform: rotate(180deg); /* បង្វិលព្រួញពេលចុច */
            }

            #toggle-sidebar-btn svg {
                transition: transform 0.3s ease;
            }
        }
    </style>
</head>
<body>
    <div id="sidebar">
        <div class="p-4 border-b">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-slate-800">ការធ្វើដំណើរសកម្ម</h1>
                <!-- ប៊ូតុងនេះនឹងបង្ហាញតែលើអេក្រង់ Mobile (md:hidden) -->
                <button id="toggle-sidebar-btn" class="p-2 rounded-md hover:bg-slate-200 md:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                </button>
            </div>
            <p class="text-sm text-slate-500">ចុចលើការធ្វើដំណើរដើម្បីមើលផ្លូវ</p>
        </div>
        <div id="trip-list" class="flex-grow overflow-y-auto">
            <p class="p-4 text-center text-slate-500">កំពុងទាញយកទិន្នន័យ...</p>
        </div>
        <div class="p-2 text-center text-xs text-slate-400">
            ធ្វើបច្ចុប្បន្នភាពរៀងរាល់ 1 វិនាទីម្តង
        </div>
    </div>
    <div id="map-container">
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const map = L.map('map').setView([12.5, 104.9], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
            
            let routingControl = null;
            const tripListEl = document.getElementById('trip-list');
            const driverMarkers = {};

            const remorkIcon = L.icon({
                iconUrl: 'https://cdn-icons-png.flaticon.com/512/5197/5197408.png',
                iconSize: [40, 40], iconAnchor: [20, 40], popupAnchor: [0, -42]
            });

            const toggleBtn = document.getElementById('toggle-sidebar-btn');
            const sidebar = document.getElementById('sidebar');

            if (window.innerWidth <= 768) {
                toggleBtn.addEventListener('click', () => {
                    sidebar.classList.toggle('collapsed');
                    toggleBtn.classList.toggle('collapsed');
                });
            }

            async function fetchTrips() {
                try {
                    const response = await fetch('get_trips.php');
                    const trips = await response.json();
                    displayTrips(trips);
                    updateDriverMarkers(trips);
                } catch (error) { console.error('Failed to fetch trips:', error); }
            }

            function displayTrips(trips) {
                const currentActiveId = document.querySelector('.trip-item.active')?.dataset.tripId;
                tripListEl.innerHTML = (trips.length === 0) ? '<p class="p-4 text-center text-slate-500">មិនមានការធ្វើដំណើរសកម្មទេ។</p>' : ''; 

                trips.forEach(trip => {
                    const tripItem = document.createElement('div');
                    tripItem.className = 'trip-item p-4 border-b hover:bg-slate-100 transition-colors';
                    tripItem.dataset.tripId = trip.id;
                    if (trip.id == currentActiveId) tripItem.classList.add('active');

                    // === ការកែប្រែនៅទីនេះ ===
                    const startDate = new Date(trip.start_time);
                    const options = {
                        timeZone: 'Asia/Phnom_Penh', // កំណត់តំបន់ម៉ោងនៅភ្នំពេញ
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true // ប្រើទម្រង់ 12 ម៉ោង (AM/PM)
                    };
                    // បង្កើតទ្រង់ទ្រាយសម្រាប់ locale ខ្មែរ (km-KH)
                    const formattedDateTime = startDate.toLocaleString('km-KH', options);

                    // ដោយសារឥឡូវមានទាំងថ្ងៃខែ យើងប្តូរឃ្លាពី "ចាប់ផ្តើមនៅម៉ោង" ទៅ "ចាប់ផ្តើម៖"
                    tripItem.innerHTML = `<div class="flex justify-between items-center"><div><h3 class="font-bold text-slate-700">ទៅកាន់៖ ${trip.customer_name}</h3><p class="text-sm text-slate-500">ចាប់ផ្តើម៖ ${formattedDateTime}</p></div><button data-trip-id="${trip.id}" class="clear-trip-btn px-3 py-1 bg-red-500 text-white text-xs font-bold rounded hover:bg-red-600">បញ្ចប់</button></div>`;
                    // === ចប់ការកែប្រែ ===
                    
                    tripItem.addEventListener('click', (e) => {
                        if (e.target.classList.contains('clear-trip-btn')) return;
                        document.querySelectorAll('.trip-item').forEach(el => el.classList.remove('active'));
                        tripItem.classList.add('active');
                        showRoute(trip);
                        if (trip.current_lat && trip.current_lng) map.panTo([trip.current_lat, trip.current_lng]);
                    });
                    tripListEl.appendChild(tripItem);
                });

                document.querySelectorAll('.clear-trip-btn').forEach(btn => btn.addEventListener('click', () => {
                    if (confirm('តើអ្នកប្រាកដទេថាចង់បញ្ចប់ការធ្វើដំណើរនេះ?')) clearTrip(btn.dataset.tripId);
                }));
            }

            function updateDriverMarkers(trips) {
                const activeTripIds = trips.map(t => String(t.id));
                trips.forEach(trip => {
                    if (trip.current_lat && trip.current_lng) {
                        const newLatLng = [trip.current_lat, trip.current_lng];
                        if (driverMarkers[trip.id]) {
                            driverMarkers[trip.id].setLatLng(newLatLng);
                        } else {
                            driverMarkers[trip.id] = L.marker(newLatLng, { icon: remorkIcon }).addTo(map).bindPopup(`<b>អ្នកបើកបរ:</b> ដំណើរទៅកាន់ ${trip.customer_name}`);
                        }
                    }
                });
                for (const tripId in driverMarkers) if (!activeTripIds.includes(tripId)) {
                    map.removeLayer(driverMarkers[tripId]);
                    delete driverMarkers[tripId];
                }
            }

            function showRoute(trip) {
                if (routingControl) map.removeControl(routingControl);
                routingControl = L.Routing.control({
                    waypoints: [L.latLng(trip.start_lat, trip.start_lng), L.latLng(trip.end_lat, trip.end_lng)],
                    routeWhileDragging: false,
                    createMarker: (i, wp) => L.marker(wp.latLng).bindPopup(i === 0 ? 'ទីតាំងចាប់ផ្តើម' : `គោលដៅ៖ ${trip.customer_name}`)
                }).addTo(map);
            }

            async function clearTrip(tripId) {
                const formData = new FormData();
                formData.append('trip_id', tripId);
                try {
                    const response = await fetch('clear_trip.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (data.success) {
                        fetchTrips();
                    } else { alert('Failed to clear trip: ' + data.message); }
                } catch (error) { console.error('Error clearing trip:', error); }
            }

            fetchTrips();
            setInterval(fetchTrips, 1000);
        });
    </script>
</body>
</html>