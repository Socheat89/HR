<?php
// -- Save Location (for user input) --
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $phone = $_POST['phone'] ?? '';
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';

    if (!$phone || !$lat || !$lng) {
        echo json_encode(['status' => 'error', 'message' => 'Missing data']);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $pdo->prepare("INSERT INTO employee_locations (phone, latitude, longitude, updated_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$phone, $lat, $lng]);

        echo json_encode(['status' => 'success']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit;
}

// -- Get Locations --
if (isset($_GET['get_locations'])) {
    header('Content-Type: application/json');

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $stmt = $pdo->query("
            SELECT l.*
            FROM employee_locations l
            INNER JOIN (
                SELECT phone, MAX(updated_at) AS latest
                FROM employee_locations
                GROUP BY phone
            ) latest ON l.phone = latest.phone AND l.updated_at = latest.latest
            ORDER BY l.updated_at DESC
        ");

        echo json_encode($stmt->fetchAll());
    } catch (PDOException $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Live GPS Tracker</title>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial; margin: 0; padding: 0; }
    h2 { background: #4CAF50; color: white; padding: 10px; margin: 0; }
    #map { height: 60vh; width: 100%; }
    #tracker { padding: 10px; }
    input, button { padding: 8px; margin: 5px; }
  </style>
</head>
<body>
  <h2>Live GPS Tracking System</h2>

  <div id="map"></div>

  <div id="tracker">
    <label>📱 Phone Number:</label>
    <input type="text" id="phone" placeholder="Enter phone number">
    <button onclick="startTracking()">Start Tracking</button>
    <span id="status"></span>
  </div>

  <script>
    let map;
    let trackingInterval = null;

    function initMap() {
      fetch("live_map_with_user_input.php?get_locations=1")
        .then(res => res.json())
        .then(data => {
          if (!data.length) return alert("No location data.");

          const center = { lat: parseFloat(data[0].latitude), lng: parseFloat(data[0].longitude) };
          map = new google.maps.Map(document.getElementById("map"), {
            zoom: 13,
            center: center
          });

          data.forEach(loc => {
            new google.maps.Marker({
              position: { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) },
              map: map,
              label: loc.phone.slice(-3),
              title: `Phone: ${loc.phone}\nTime: ${loc.updated_at}`
            });
          });
        });
    }

    function startTracking() {
      const phone = document.getElementById("phone").value.trim();
      if (!phone) return alert("Please enter your phone number.");

      if (!navigator.geolocation) {
        alert("Geolocation is not supported.");
        return;
      }

      if (trackingInterval) clearInterval(trackingInterval);

      trackingInterval = setInterval(() => {
        navigator.geolocation.getCurrentPosition(pos => {
          const lat = pos.coords.latitude;
          const lng = pos.coords.longitude;

          fetch("live_map_with_user_input.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `phone=${encodeURIComponent(phone)}&lat=${lat}&lng=${lng}`
          })
          .then(res => res.json())
          .then(resp => {
            document.getElementById("status").innerText = resp.status === "success"
              ? "📡 Location sent!"
              : "❌ Error: " + resp.message;
          });
        }, () => {
          alert("Unable to get your location.");
        });
      }, 10000); // every 10 seconds
    }
  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCp8Q7SWE066QLsLSjVIH5Jc1YUV22jYq=initMap" async defer></script>
</body>
</html>
