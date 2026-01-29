<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ញើសារ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">ប្រព័ន្ធផ្ញើសារ</h4>
            </div>

            <div class="card-body">
                <!-- Message Display Area -->
                <div class="chat-box mb-3 p-3 bg-white rounded" style="height: 400px; overflow-y: auto;">
                    <!-- Messages will be displayed here -->
                </div>

                <!-- Message Input Form -->
                <form action="send.php" method="POST" enctype="multipart/form-data">
                    <div class="row g-2">
                        <!-- Photo Upload -->
                        <div class="col-md-4">
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>

                        <!-- SMS Input -->
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="sms" placeholder="វាយសារ..." required>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">ផ្ញើ</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>