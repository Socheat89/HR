<?php
// db_payroll.php
include 'db_payroll.php'; // Database connection

// Query to retrieve payroll data
$sql = "SELECT * FROM payrolls";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if data is fetched correctly
if (!$rows) {
    die('No data found in the database.');
}

// Encode result into JSON
$payrollData = json_encode($rows, JSON_UNESCAPED_UNICODE);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Payroll PDF Khmer</title>

    <!-- Include pdfMake & Khmer Font -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="vfs_fonts_khmer.js"></script> <!-- Khmer font VFS you downloaded -->

    <style>
        body {
            font-family: 'Khmer OS Battambang', sans-serif;
            padding: 20px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        #debug {
            margin-top: 20px;
            color: green;
        }
    </style>
</head>
<body>

    <button onclick="generatePDF()">Generate Khmer Payroll PDF</button>
    <div id="debug"></div>

    <script>
        // Load payroll data from PHP
        const payrollData = <?php echo $payrollData; ?>;

        // Debugging: Log the data to ensure it's properly passed
        console.log(payrollData);

        function generatePDF() {
            try {
                if (payrollData.length === 0) {
                    throw new Error('No payroll data available.');
                }

                const docDefinition = {
                    pageSize: 'A4',
                    pageOrientation: 'landscape',
                    pageMargins: [20, 20, 20, 20],
                    defaultStyle: {
                        font: 'khmer',  // IMPORTANT! use Khmer font
                        fontSize: 10
                    },
                    content: [
                        { 
                            text: 'តារាងប្រាក់ខែ', 
                            style: 'header', 
                            alignment: 'center',
                            fontSize: 18,
                            bold: true,
                            margin: [0, 0, 0, 10]
                        },
                        {
                            table: {
                                widths: [ 50, '*', '*', '*',  '*',  '*',  '*'],
                                body: [
                                    // Table header
                                    [
                                        { text: 'លេខរៀង', style: 'tableHeader', alignment: 'center' },
                                        { text: 'ឈ្មោះបុគ្គលិក', style: 'tableHeader', alignment: 'center' },
                                        { text: 'ប្រាក់ខែ', style: 'tableHeader', alignment: 'center' },
                                        { text: 'លើសម៉ោង', style: 'tableHeader', alignment: 'center' },
                                        { text: 'ប្រាក់លើកទឹកចិត្ត', style: 'tableHeader', alignment: 'center' },
                                        { text: 'ការដកប្រាក់', style: 'tableHeader', alignment: 'center' },
                                        { text: 'ប្រាក់ចំណូលសុទ្ធ', style: 'tableHeader', alignment: 'center' }
                                    ],
                                    // Table rows
                                    ...payrollData.map(row => [
                                        { text: row.id.toString(), alignment: 'center' },
                                        { text: row.requester_name, alignment: 'left' },
                                        { text: parseFloat(row.base_salary).toFixed(2), alignment: 'right' },
                                        { text: parseFloat(row.overtime).toFixed(2), alignment: 'right' },
                                        { text: parseFloat(row.bonus).toFixed(2), alignment: 'right' },
                                        { text: parseFloat(row.deductions).toFixed(2), alignment: 'right' },
                                        { text: parseFloat(row.net_salary).toFixed(2), alignment: 'right' }
                                    ])
                                ]
                            },
                            layout: {
                                fillColor: function(rowIndex, node, columnIndex) {
                                    return rowIndex % 2 === 0 ? '#f2f2f2' : null;
                                },
                                hLineWidth: function(i) {
                                    return i === 0 || i === 1 ? 1 : 0.5;
                                },
                                vLineWidth: function(i) {
                                    return i === 0 ? 1 : 0.5;
                                },
                                hLineColor: function(i) {
                                    return '#cccccc';
                                },
                                vLineColor: function(i) {
                                    return '#cccccc';
                                }
                            }
                        }
                    ],
                    styles: {
                        header: {
                            fontSize: 18,
                            bold: true,
                            margin: [0, 0, 0, 10]
                        },
                        tableHeader: {
                            bold: true,
                            fontSize: 12,
                            fillColor: '#4CAF50',
                            color: 'white',
                            alignment: 'center',
                            padding: [10, 5] // Adjusted padding to increase the height of the header
                        }
                    }
                };

                // Open PDF
                pdfMake.createPdf(docDefinition).open();
                document.getElementById('debug').innerText = '✅ បង្កើត PDF បានជោគជ័យ!';
            } catch (error) {
                console.error('Error generating PDF:', error);
                document.getElementById('debug').innerText = '❌ បញ្ហាក្នុងការបង្កើត PDF: ' + error.message;
            }
        }
    </script>

</body>
</html>
