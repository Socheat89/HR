self.addEventListener('install', event => {
    console.log('Service Worker installed');
});

self.addEventListener('activate', event => {
    console.log('Service Worker activated');
    // Periodic location update every 5 minutes
    setInterval(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(sendLocationToServer, error => {
                console.log('Background tracking error:', error);
            });
        }
    }, 300000); // 5 minutes
});

function sendLocationToServer(position) {
    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;
    // Assume tracking code is passed via postMessage or stored in IndexedDB
    const trackingCode = self.trackingCode || 'DEFAULT_CODE'; // Replace with actual mechanism

    fetch('/index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `tracking_code=${trackingCode}&latitude=${latitude}&longitude=${longitude}`
    })
    .then(response => response.json())
    .then(data => console.log('Background update:', data))
    .catch(error => console.error('Background fetch error:', error));
}

// Receive tracking code from main script
self.addEventListener('message', event => {
    self.trackingCode = event.data.trackingCode;
});