<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលទិន្នន័យ Excel</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .back-link { margin-top: 20px; }
    </style>
</head>
<body>
    <h1>ទិន្នន័យពីឯកសារ Excel</h1>

    <?php
    require 'vendor/autoload.php'; // ផ្ទុក PhpSpreadsheet
    use PhpOffice\PhpSpreadsheet\IOFactory;

    if (isset($_GET['file'])) {
        $fileName = urldecode($_GET['file']);
        $filePath = "uploads/" . $fileName;

        // ពិនិត្យប្រសិនបើឯកសារមាន
        if (file_exists($filePath)) {
            try {
                // អានឯកសារ Excel
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray();

                // បង្ហាញទិន្នន័យជាតារាង
                echo "<table>";
                $isHeader = true;

                foreach ($data as $row) {
                    echo "<tr>";
                    foreach ($row as $cell) {
                        if ($isHeader) {
                            echo "<th>" . htmlspecialchars($cell ?? '') . "</th>";
                        } else {
                            echo "<td>" . htmlspecialchars($cell ?? '') . "</td>";
                        }
                    }
                    echo "</tr>";
                    if ($isHeader) {
                        $isHeader = false; // ជួរដំបូងជាក្បាលតារាង
                    }
                }
                echo "</table>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>មានបញ្ហាក្នុងការអានឯកសារ: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: red;'>រកឯកសារមិនឃើញ!</p>";
        }
    } else {
        echo "<p style='color: red;'>សូមបង្ហោះឯកសារជាមុនសិន!</p>";
    }
    ?>

    <div class="back-link">
        <a href="index.php">ត្រឡប់ទៅបង្ហោះឯកសារថ្មី</a>
    </div>
</body>
</html>