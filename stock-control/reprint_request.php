<?php
// FILE: reprint_request.php
// This script is for reprinting an existing stock request form. It is read-only.
// *** LOGIN CHECK HAS BEEN REMOVED ***

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
header('Content-Type: text/html; charset=utf-8');

/**
 * Converts a standard date string (e.g., YYYY-MM-DD) into a full Khmer date format.
 * @param string $dateString The date to convert.
 * @return string The formatted Khmer date.
 */
function convertToKhmerDate($dateString) {
    if (empty($dateString)) {
        return '';
    }

    $timestamp = strtotime($dateString);
    if ($timestamp === false) {
        return ''; // Return empty if the date is invalid
    }

    $year = date('Y', $timestamp);
    $month = (int)date('n', $timestamp);
    $day = date('d', $timestamp);

    $westernNumerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $khmerNumerals   = ['០', '១', '២', '៣', '៤', '៥', '៦', '៧', '៨', '៩'];

    $khmerMonths = [
        1 => 'មករា', 2 => 'កុម្ភៈ', 3 => 'មីនា', 4 => 'មេសា',
        5 => 'ឧសភា', 6 => 'មិថុនា', 7 => 'កក្កដា', 8 => 'សីហា',
        9 => 'កញ្ញា', 10 => 'តុលា', 11 => 'វិច្ឆិកា', 12 => 'ធ្នូ'
    ];

    $khmerDay = str_replace($westernNumerals, $khmerNumerals, $day);
    $khmerYear = str_replace($westernNumerals, $khmerNumerals, $year);
    $khmerMonthName = $khmerMonths[$month] ?? '';

    return "ថ្ងៃទី " . $khmerDay . " ខែ " . $khmerMonthName . " ឆ្នាំ " . $khmerYear;
}


// --- 1. Get and Validate the Request ID from the URL ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h1>Invalid Request</h1><p>A valid request ID was not provided in the URL.</p>");
}
$request_id = (int)$_GET['id'];


// --- 2. Fetch All Necessary Data from the Database ---
try {
    // A. Fetch the main request details and the name of the user who processed it.
    $stmt_req = $pdo->prepare("
        SELECT sr.request_no, sr.location, sr.processed_at, sr.created_at, u.full_name
        FROM stock_request sr
        LEFT JOIN users u ON sr.user_id = u.id
        WHERE sr.id = ?
    ");
    $stmt_req->execute([$request_id]);
    $request_data = $stmt_req->fetch(PDO::FETCH_ASSOC);

    if (!$request_data) {
        die("<h1>Record Not Found</h1><p>No request record exists for ID: " . htmlspecialchars($request_id) . "</p>");
    }

    // B. Assign fetched data to variables for use in the HTML.
    $request_no = $request_data['request_no'];
    $location = $request_data['location'];
    $display_date = $request_data['processed_at'] ?? $request_data['created_at'];
    $processed_by_name = $request_data['full_name'];

    // C. Fetch the list of items associated with this request.
    $stmt_items = $pdo->prepare("
        SELECT
            sri.requested_quantity,
            sri.offered_quantity,
            sri.notes,
            COALESCE(si.item_name, sri.item_name_custom) as item_name
        FROM stock_request_items sri
        LEFT JOIN stock_items si ON sri.item_id = si.id
        WHERE sri.stock_request_id = ?
        ORDER BY sri.id ASC
    ");
    $stmt_items->execute([$request_id]);
    $fetched_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    // D. Format the items for easy display in the HTML table.
    $submitted_items = [];
    foreach ($fetched_items as $item) {
        $submitted_items[] = [
            'name'          => htmlspecialchars($item['item_name'] ?? ''),
            'request_qty'   => (int)$item['requested_quantity'],
            'offer_qty'     => (int)$item['offered_quantity'],
            'notes'         => htmlspecialchars($item['notes'] ?? '')
        ];
    }

} catch (PDOException $e) {
    error_log("reprint_request.php - Database Error: " . $e->getMessage());
    die("<h1>Database Error</h1><p>Could not retrieve request details. Please check the error logs or contact support.</p>");
}

// --- 3. Prepare Final Variables for Display ---
$total_offer_qty = array_sum(array_column($submitted_items, 'offer_qty'));
// The minimum number of rows is now handled dynamically for printing,
// but we can still set a minimum for screen view if desired. Let's keep it simple.
$total_rows_to_display = count($submitted_items) > 0 ? count($submitted_items) : 5;


?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Reprint Form - <?php echo htmlspecialchars($request_no ?? ''); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Hanuman:wght@400;700&family=Koulen&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        /* CSS is optimized for A4 printing */
        body {
            background: #ccc;
            font-family: 'Kantumruy Pro', serif;
            color: #000;
            margin: 0;
        }
        .form-page {
            background: #fff;
            width: 210mm;
            min-height: 297mm;
            padding: 1cm;
            margin: 1cm auto;
            border: 1px solid #dcdcdc;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        .form-header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .company-info { text-align: center; }
        .company-logo {
            margin-top: -2rem;

        }
        .company-name { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
        .form-title-kh { font-family: 'Koulen', cursive; font-size: 22px; text-decoration: underline; margin-top: -1rem; }
        .address-box {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 11px;
            line-height: 1.4;
            max-width: 250px;
            text-align: right;
            margin-top: 25px;
            margin-left: auto;
        }

        .meta-info {
            padding: 15px 0;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .meta-info .meta-column div {
            margin-bottom: 5px; /* Add space between stacked items */
        }
        /* === START: ลบ CSS Rule ចេញពីទីនេះ === */
        /* .dotted-line { border-bottom: 1px dotted #000; padding: 0 10px; } */
        /* === END: ลบ CSS Rule ចេញ === */

        .form-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            table-layout: fixed;
        }
        .form-table th, .form-table td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
        }
        .form-table th { background-color: #e0e0e0; font-weight: bold; }
        .form-table .col-no { width: 6%; }
        .form-table .col-goods { width: 44%; text-align: left; padding-left: 8px; word-wrap: break-word; }
        .form-table .col-request, .form-table .col-offer { width: 12%; }
        .form-table .col-notes { width: 26%; text-align: left; padding-left: 8px; word-wrap: break-word; }
        .form-table .total-row td { font-weight: bold; text-align: right; padding-right: 10px; background: #e0e0e0; }
        .notes-section { margin-top: 15px; font-size: 14px; }
        .notes-section span { font-weight: bold; }
        .notes-section p { margin: 5px 0 0 10px; font-size: 13px; }
        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-around;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            padding-top: 20px;
        }
        .signature-box {
            padding-top: 60px;
            width: 30%;
            border-top: 1px dotted #000;
        }

        /* === PRINT STYLES === */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body {
                background: #fff;
                margin: 0;
            }
            .form-page {
                margin: 0;
                padding: 1cm;
                width: 210mm;
                min-height: 290mm; /* Adjust for print */
                box-shadow: none;
                border: none;
                min-height: initial;
            }

            /* --- NEW: Add faded background image for printing only --- */
            .form-table {
                background-image: linear-gradient(rgba(255, 255, 255, 0.85), rgba(255, 255, 255, 0.85)), url('https://i.ibb.co/3YFRv9KT/Logo-Van-Van-3.png');
                background-repeat: no-repeat;
                background-position: center bottom;
                background-size: 250px;
            }

            /* --- NEW: Rules for handling page breaks --- */
            .form-table thead { display: table-header-group; }
            .form-table tfoot { display: table-footer-group; }
            .form-table tbody tr { page-break-inside: avoid; }
            .notes-section, .signature-section { page-break-inside: avoid; }
            .signature-section { page-break-before: auto; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="form-page">
        <div class="form-header">
            <div class="company-info">
                <img src="https://i.ibb.co/NgxCQp0N/Logo-Van-Van-1.png" alt="Van Van Cambodia Logo" class="company-logo" style="height:150px;">
                <div class="form-title-kh">ប័ណ្ណបញ្ចេញទំនិញ</div>
            </div>
            <div class="address-box">Address: No.1AEo, St.318, Sangkat Tuol SvayPrey1, Khan Beong Keng korng, Phnom Penh, Cambodia. Tell: 0962458467</div>
        </div>

        <!-- === START: កែប្រែ HTML នៅទីនេះ === -->
        <div class="meta-info">
            <div class="meta-column">
                <div><span>លេខ: <?php echo htmlspecialchars($request_no ?? ''); ?></span></div>
                <div><span>Date/Request: <?php echo date('d/m/Y', strtotime($display_date)); ?></span></div>
                <div><span>ទីតាំងស្នើសុំ: <?php echo htmlspecialchars($location ?? ''); ?></span></div>
            </div>
            <div class="meta-column" style="text-align: right;">
                <div><span>By: <?php echo htmlspecialchars($processed_by_name ?? ''); ?></span></div>
                <div><span>ថ្ងៃចេញបុង: <?php echo convertToKhmerDate($display_date); ?></span></div>
            </div>
        </div>
        <!-- === END: កែប្រែ HTML === -->

        <table class="form-table">
            <thead>
                <tr>
                    <th class="col-no">ល.រ<br>N/o</th>
                    <th class="col-goods">ឈ្មោះទំនិញ<br>Goods</th>
                    <th class="col-request">ចំនួនស្នើសុំ<br>Request</th>
                    <th class="col-offer">ចំនួនផ្តល់ជូន<br>Offer amount</th>
                    <th class="col-notes">កំណត់សំគាល់<br>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Display the actual items from the database
                foreach ($submitted_items as $index => $item) {
                    echo '<tr>';
                    echo '<td>' . ($index + 1) . '</td>';
                    echo '<td class="col-goods">' . $item['name'] . '</td>';
                    echo '<td>' . $item['request_qty'] . '</td>';
                    echo '<td>' . $item['offer_qty'] . '</td>';
                    echo '<td class="col-notes">' . $item['notes'] . '</td>';
                    echo '</tr>';
                }

                // Add empty rows if the total is less than a certain number, for consistent layout on single pages
                $min_rows = 5;
                $current_rows = count($submitted_items);
                if ($current_rows < $min_rows) {
                    for ($i = $current_rows; $i < $min_rows; $i++) {
                        echo '<tr>';
                        echo '<td>' . ($i + 1) . '</td>';
                        echo '<td class="col-goods"></td>';
                        echo '<td></td>';
                        echo '<td></td>';
                        echo '<td class="col-notes"></td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Total</td>
                    <td><?php echo $total_offer_qty; ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div class="notes-section">
            <span>Note:</span>
            <p>សម្គាល់ៈ ដែលស្នើសុំសម្រាប់ប្រើប្រាស់ប្រចាំខែ</p>
            <p>សូមធ្វើការត្រួតពិនិត្យ សម្ភារៈ ដែលបានប្រគល់ជូនឲ្យបានត្រឹមត្រូវ និង សូមធ្វើការចុះហត្ថលេខាបញ្ជាក់</p>
        </div>

        <div class="signature-section">
            <div class="signature-box">អ្នកបញ្ចេញទំនិញ</div>
            <div class="signature-box">អ្នកដឹក</div>
            <div class="signature-box">អ្នកទទួលទំនិញ</div>
        </div>
    </div>

</body>
</html>