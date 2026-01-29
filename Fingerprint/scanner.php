<?php
// Handle POST request from the scanner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scanned_data'])) {
    $scanned_data = $_POST['scanned_data'] ?? '';
    
    if (!empty($scanned_data)) {
        // Log the scanned data to a file
        file_put_contents('scanned_links.txt', $scanned_data . PHP_EOL, FILE_APPEND);
        
        // Check if it's a URL and respond
        if (filter_var($scanned_data, FILTER_VALIDATE_URL)) {
            echo "Valid URL: " . htmlspecialchars($scanned_data);
        } else {
            echo "Data received: " . htmlspecialchars($scanned_data);
        }
    } else {
        echo "No data received.";
    }
    exit; // Stop here to prevent HTML output
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
        #reader { width: 100%; max-width: 400px; margin: 0 auto; }
        #result { color: green; font-weight: bold; margin-top: 10px; }
        button { padding: 10px 20px; margin: 10px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Scan a QR Code</h1>
    <div id="reader"></div>
    <p>Scanned Result: <span id="result"></span></p>
    <button id="startButton">Start Scanner</button>
    <button id="stopButton" disabled>Stop Scanner</button>

    <script>
        const html5QrCode = new Html5Qrcode("reader");
        let isScanning = false;

        // Start scanning
        document.getElementById('startButton').addEventListener('click', () => {
            if (!isScanning) {
                html5QrCode.start(
                    { facingMode: "environment" }, // Use rear camera if available
                    { fps: 10, qrbox: { width: 250, height: 250 } }, // Config: FPS and scan area
                    (decodedText) => {
                        // On successful scan
                        document.getElementById('result').textContent = decodedText;
                        sendToServer(decodedText);
                        stopScanner(); // Stop after one scan (optional)
                    },
                    (errorMessage) => {
                        // Handle errors silently or log them
                        console.log('Scan error:', errorMessage);
                    }
                ).then(() => {
                    isScanning = true;
                    toggleButtons();
                }).catch((err) => {
                    alert('Error starting scanner: ' + err);
                });
            }
        });

        // Stop scanning
        document.getElementById('stopButton').addEventListener('click', () => {
            stopScanner();
        });

        // Function to stop the scanner
        function stopScanner() {
            if (isScanning) {
                html5QrCode.stop().then(() => {
                    isScanning = false;
                    toggleButtons();
                }).catch((err) => {
                    alert('Error stopping scanner: ' + err);
                });
            }
        }

        // Toggle button states
        function toggleButtons() {
            document.getElementById('startButton').disabled = isScanning;
            document.getElementById('stopButton').disabled = !isScanning;
        }

        // Send scanned data to server
        function sendToServer(data) {
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'scanned_data=' + encodeURIComponent(data)
            })
            .then(response => response.text())
            .then(data => {
                console.log(data);
                alert('Server response: ' + data); // Show server response
            })
            .catch(error => console.error('Error:', error));
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (isScanning) html5QrCode.stop();
        });
    </script>
</body>
</html>