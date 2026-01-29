<?php
// --- PHP PART: Return JSON if fetch request ---
if (isset($_GET['get_locations'])) {
    header('Content-Type: application/json');

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "DB connection failed", "details" => $e->getMessage()]);
        exit;
    }

    // Get latest location per phone
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
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Live Employee Locations</title>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial; margin: 0; padding: 0; }
    h2 { padding: 10px; background: #4CAF50; color: white; margin: 0; }
    #map { height: 90vh; width: 100%; }
  </style>
</head>
<body>
  <h2>Live Employee Locations</h2>
  <div id="map"></div>

  <script>
    let map;

    function initMap() {
      fetch("admin_live_map.php?get_locations=1")
        .then(res => res.json())
        .then(data => {
          if (!data.length) {
            alert("No location data found.");
            return;
          }

          const center = { lat: parseFloat(data[0].latitude), lng: parseFloat(data[0].longitude) };
          map = new google.maps.Map(document.getElementById("map"), {
            zoom: 13,
            center: center,
          });

          data.forEach(loc => {
            new google.maps.Marker({
              position: { lat: parseFloat(loc.latitude), lng: parseFloat(loc.longitude) },
              map: map,
              label: loc.phone.slice(-3), // Last 3 digits
              title: `Phone: ${loc.phone}\nTime: ${loc.updated_at}`
            });
          });
        })
        .catch(err => {
          alert("Error loading location: " + err);
        });
    }
  </script>

  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCp8Q7SWE066QLsLSjVIH5Jc1YUV22jYqE=initMap" async defer></script>
</body>
</html>
