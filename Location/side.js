// Driver side (simulation)
function sendLocation(driverId, latitude, longitude, status) {
    fetch('save_driver_location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            driver_id: driverId,
            latitude: latitude,
            longitude: longitude,
            status: status
        })
    });
}

// ឧទាហរណ៍៖ ផ្ញើទីតាំងរៀងរាល់ 5 វិនាទី
setInterval(() => {
    navigator.geolocation.getCurrentPosition(position => {
        sendLocation(1, position.coords.latitude, position.coords.longitude, 'delivering');
    });
}, 5000);