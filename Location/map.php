<?php
if (!isset($_GET['code'])) {
    die("No tracking code provided.");
}

$tracking_code = $_GET['code'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee GPS Tracker</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h2>Tracking Active</h2>
    <p>Tracking Code: <?php echo htmlspecialchars($tracking_code); ?></p>
    <p id="status">Starting tracking...</p>

    <script>
    const trackingCode = "<?php echo $tracking_code; ?>";

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/service-worker.js')
            .then(reg => console.log('Service Worker registered'))
            .catch(err => console.log('Service Worker registration failed: ', err));
    }

    function startTracking() {
        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(sendLocation, showError, {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            });
        } else {
            document.getElementById("status").innerHTML = "Geolocation not supported.";
        }
    }

    function sendLocation(position) {
        const latitude = position.coords.latitude;
        const longitude = position.coords.longitude;

        fetch('/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `tracking_code=${trackingCode}&latitude=${latitude}&longitude=${longitude}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById("status").innerHTML = 
                    `Tracking - Lat: ${latitude}, Lon: ${longitude} at ${new Date().toLocaleTimeString()}`;
            } else {
                document.getElementById("status").innerHTML = data.message;
            }
        })
        .catch(error => console.error('Error:', error));
    }

    function showError(error) {
        document.getElementById("status").innerHTML = "Error: " + error.message;
    }

    // Start tracking on page load
    window.onload = startTracking;
    </script>
</body>
</html>