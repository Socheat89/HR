<?php
// Database connection (replace with your actual database credentials)
try {
    $pdo = new PDO('mysql:host=localhost;dbname=admin_panel', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all requests from the database
    $stmt = $pdo->prepare("SELECT * FROM requests ORDER BY request_date DESC");
    $stmt->execute();
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .report-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .report-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e6f0;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f7fa;
        }
        .btn-detail {
            background-color: #17a2b8;
            border: none;
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 5px;
            color: white;
            transition: background-color 0.3s ease;
            margin-right: 5px;
        }
        .btn-detail:hover {
            background-color: #138496;
            color: white;
        }
        .btn-print {
            background-color: #28a745;
            border: none;
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 5px;
            color: white;
            transition: background-color 0.3s ease;
        }
        .btn-print:hover {
            background-color: #218838;
            color: white;
        }
        .btn-back {
            background-color: #7f8c8d;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 8px;
            transition: background-color 0.3s ease, transform 0.2s ease;
            color: white;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn-back:hover {
            background-color: #6c757d;
            transform: translateY(-2px);
        }
        /* Modal Styling */
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            background-color: #3498db;
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .modal-title {
            font-weight: 600;
        }
        .modal-body {
            padding: 2rem;
            background-color: #f8f9fa;
        }
        .section-header {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.3rem;
        }
        .detail-row {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .detail-item {
            flex: 1 1 45%;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
        }
        .detail-item i {
            color: #3498db;
            margin-right: 0.5rem;
        }
        .detail-item strong {
            color: #2c3e50;
            font-weight: 600;
        }
        .detail-item span {
            color: #34495e;
        }
        .modal-footer {
            border-top: none;
            padding: 1rem 2rem;
        }
        /* Print Styles for Table */
        @media print {
            body {
                background: #fff !important;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                max-width: 100%;
            }
            .report-title {
                color: #000;
                text-align: left;
                margin: 0;
                padding: 10px;
                background: #f8f9fa;
            }
            table {
                border: 1px solid #000;
            }
            th, td {
                border: 1px solid #000;
                color: #000;
            }
            th {
                background-color: #3498db;
                color: white;
            }
            .btn-detail, .btn-back, .btn-print {
                display: none;
            }
            .modal {
                display: none !important;
            }
        }
        /* Print Styles for Modal (Request Form) */
        @media print {
            .modal {
                display: block !important;
                position: relative;
            }
            .modal-content {
                box-shadow: none;
                border: 2px solid #000;
                border-radius: 0;
            }
            .modal-header {
                background-color: #3498db;
                color: white;
                border-top: none;
                border-bottom: 1px solid #000;
            }
            .modal-body {
                background: #fff;
                padding: 10px;
            }
            .section-header {
                border-bottom: 1px solid #000;
            }
            .detail-item {
                background: #fff;
                box-shadow: none;
                border: 1px solid #e0e0e0;
                border-radius: 0;
            }
            .print-request-form {
                display: block;
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: -1;
                opacity: 0;
            }
        }
        @media (max-width: 768px) {
            .report-container {
                padding: 1rem;
            }
            .report-title {
                font-size: 1.5rem;
            }
            th, td {
                font-size: 0.9rem;
                padding: 8px;
            }
            .detail-item {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h2 class="report-title">All Requests Report</h2>
        <div class="text-center mb-3">
            <button class="btn btn-print" onclick="printReport()">Print Report</button>
        </div>

        <?php if (empty($requests)): ?>
            <p class="text-center">No requests found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Request Type</th>
                        <th>Requester Name</th>
                        <th>Department</th>
                        <th>Request Date</th>
                        <th>Reason</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['request_type'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['request_date'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></td>
                            <td>
                                <button class="btn btn-detail" data-bs-toggle="modal" data-bs-target="#detailModal" 
                                    data-request='<?php echo json_encode($request); ?>'>
                                    View Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="text-center">
            <a href="../requests/submit_request.php" class="btn-back">Back to Submit Request</a>
        </div>
    </div>

    <!-- Modal for Request Details -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel"><i class="fas fa-info-circle"></i> Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Personal Info Section -->
                    <div class="section-header"><i class="fas fa-user"></i> Personal Info</div>
                    <div class="detail-row">
                        <div class="detail-item"><i class="fas fa-id-badge"></i> <strong>ID:</strong> <span id="modal-id"></span></div>
                        <div class="detail-item"><i class="fas fa-user"></i> <strong>Requester Name:</strong> <span id="modal-requester-name"></span></div>
                        <div class="detail-item"><i class="fas fa-building"></i> <strong>Department:</strong> <span id="modal-department"></span></div>
                        <div class="detail-item"><i class="fas fa-briefcase"></i> <strong>Position:</strong> <span id="modal-position"></span></div>
                        <div class="detail-item"><i class="fas fa-map-marker-alt"></i> <strong>Branch:</strong> <span id="modal-branch"></span></div>
                        <div class="detail-item"><i class="fas fa-phone"></i> <strong>Contact Number:</strong> <span id="modal-contact-number"></span></div>
                    </div>

                    <!-- Request Info Section -->
                    <div class="section-header"><i class="fas fa-file-alt"></i> Request Info</div>
                    <div class="detail-row">
                        <div class="detail-item"><i class="fas fa-clipboard-list"></i> <strong>Request Type:</strong> <span id="modal-request-type"></span></div>
                        <div class="detail-item"><i class="fas fa-calendar-day"></i> <strong>Request Date:</strong> <span id="modal-request-date"></span></div>
                        <div class="detail-item"><i class="fas fa-calendar-check"></i> <strong>Return Date:</strong> <span id="modal-return-date"></span></div>
                        <div class="detail-item"><i class="fas fa-clock"></i> <strong>Number of Days:</strong> <span id="modal-number-of-days"></span></div>
                        <div class="detail-item"><i class="fas fa-hourglass-half"></i> <strong>Remaining Days:</strong> <span id="modal-remaining-days"></span></div>
                        <div class="detail-item"><i class="fas fa-comment"></i> <strong>Reason:</strong> <span id="modal-reason"></span></div>
                        <div class="detail-item"><i class="fas fa-user-tie"></i> <strong>Assigned To:</strong> <span id="modal-assigned-to"></span></div>
                        <div class="detail-item"><i class="fas fa-map"></i> <strong>Location:</strong> <span id="modal-location"></span></div>
                    </div>

                    <!-- Time Details Section -->
                    <div class="section-header"><i class="fas fa-clock"></i> Time Details</div>
                    <div class="detail-row">
                        <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>Time In:</strong> <span id="modal-time-in"></span></div>
                        <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>Time Out:</strong> <span id="modal-time-out"></span></div>
                        <div class="detail-item"><i class="fas fa-hourglass"></i> <strong>Total Hours:</strong> <span id="modal-total-hours"></span></div>
                        <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>Repay Time In:</strong> <span id="modal-repay-time-in"></span></div>
                        <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>Repay Time Out:</strong> <span id="modal-repay-time-out"></span></div>
                        <div class="detail-item"><i class="fas fa-hourglass-end"></i> <strong>Repay Total Hours:</strong> <span id="modal-repay-total-hours"></span></div>
                        <div class="detail-item"><i class="fas fa-exclamation-triangle"></i> <strong>Late Hours:</strong> <span id="modal-late-hours"></span></div>
                        <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>Forgot Scan In:</strong> <span id="modal-forgot-scan-in"></span></div>
                        <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>Forgot Scan Out:</strong> <span id="modal-forgot-scan-out"></span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-print" onclick="printRequestForm()">Print Request Form</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Printable Form (Styled like print_request.php) -->
    <div class="print-request-form" id="printableForm" style="display: none;">
        <div class="container" style="max-width: 800px; margin: 0 auto; border: 2px solid #000; padding: 20px;">
            <div class="header" style="text-align: center; margin-bottom: 20px;">
                <img src="path/to/van_cambodia_logo.png" alt="Van Cambodia Logo" style="max-width: 200px; height: auto;">
                <h1 style="font-size: 18px; font-weight: bold; margin: 10px 0;">សំណើសុំសម្រាក</h1>
            </div>

            <table class="form-table" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ប្រភេទនៃសំណើសុំ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-annual" style="margin-right: 5px;"> សម្រាកប្រចាំឆ្នាំ (Annual Leave)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-sick" style="margin-right: 5px;"> សម្រាកដោយជំងឺ (Sick Leave)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-forgot-fp" style="margin-right: 5px;"> ភ្លេចស្កេនមេដៃ (Forgot FP)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-maternity" style="margin-right: 5px;"> សម្រាកលំហែមាតុភាព (Maternity Leave)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-ot" style="margin-right: 5px;"> ថែមម៉ោង (OT)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-early" style="margin-right: 5px;"> ចេញមុនម៉ោង (Early)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-changing-off" style="margin-right: 5px;"> ប្តូរថ្ងៃសម្រាក (Changing day off)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-special" style="margin-right: 5px;"> សមรាកពិសេស (Special Leave)
                        </div>
                        <div class="checkbox-group" style="margin: 5px 0;">
                            <input type="checkbox" id="print-late" style="margin-right: 5px;"> មកយឺត (Late)
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ឈ្មោះអ្នកស្នើសុំ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-requester-name"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ផ្នែក/មុខដំណែង:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-department-position"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ចំនួនថ្ងៃ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-number-of-days"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ថ្ងៃខែស្នើសុំ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-request-date"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ម៉ោងចូល/ចេញ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">
                        <div class="time-row" style="display: flex; gap: 10px;">
                            <div style="flex: 1;">ចូល: <span id="print-time-in"></span></div>
                            <div style="flex: 1;">ចេញ: <span id="print-time-out"></span></div>
                        </div>
                        (សរុប: <span id="print-total-hours"></span>)
                    </td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">មូលហេតុ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-reason"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ប្រគល់ការងារឱ្ល:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;" id="print-assigned-to"></td>
                </tr>
                <tr>
                    <td class="label" style="background-color: #f0f0f0; font-weight: bold; width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">ហត្ថលេខា/ថ្ងៃខែ:</td>
                    <td class="value" style="width: 50%; border: 1px solid #000; padding: 8px; vertical-align: top;">
                        <div class="signature-section" style="margin-top: 20px; text-align: center;">
                            <span style="border-bottom: 1px solid #000; padding: 10px 0; display: inline-block; width: 200px;">_________________________</span><br>
                            <span id="print-requester-name-signature"></span><br>
                            <span id="print-request-date-signature"></span>
                        </div>
                    </td>
                </tr>
            </table>

            <div class="signature-section no-print" style="margin-top: 20px; text-align: center;">
                <p>សូមបោះពុម្ព និងហត្ថលេខា:</p>
                <span style="border-bottom: 1px solid #000; padding: 10px 0; display: inline-block; width: 200px;">_________________________</span><br>
                <span>អ្នកស្នើសុំ</span><br>
                <span style="border-bottom: 1px solid #000; padding: 10px 0; display: inline-block; width: 200px;">_________________________</span><br>
                <span>អ្នកអនុម័ត</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript to populate modal with request details
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('detailModal');
            modal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const request = JSON.parse(button.getAttribute('data-request'));

                // Populate modal fields
                document.getElementById('modal-id').textContent = request.id || 'N/A';
                document.getElementById('modal-request-type').textContent = request.request_type || 'N/A';
                document.getElementById('modal-requester-name').textContent = request.requester_name || 'N/A';
                document.getElementById('modal-number-of-days').textContent = request.number_of_days || 'N/A';
                document.getElementById('modal-remaining-days').textContent = request.remaining_days || 'N/A';
                document.getElementById('modal-department').textContent = request.department || 'N/A';
                document.getElementById('modal-position').textContent = request.position || 'N/A';
                document.getElementById('modal-branch').textContent = request.branch || 'N/A';
                document.getElementById('modal-request-date').textContent = request.request_date || 'N/A';
                document.getElementById('modal-return-date').textContent = request.return_date || 'N/A';
                document.getElementById('modal-late-hours').textContent = request.late_hours || 'N/A';
                document.getElementById('modal-forgot-scan-in').textContent = request.forgot_scan_in || 'N/A';
                document.getElementById('modal-forgot-scan-out').textContent = request.forgot_scan_out || 'N/A';
                document.getElementById('modal-time-in').textContent = request.time_in || 'N/A';
                document.getElementById('modal-time-out').textContent = request.time_out || 'N/A';
                document.getElementById('modal-total-hours').textContent = request.total_hours || 'N/A';
                document.getElementById('modal-repay-time-in').textContent = request.repay_time_in || 'N/A';
                document.getElementById('modal-repay-time-out').textContent = request.repay_time_out || 'N/A';
                document.getElementById('modal-repay-total-hours').textContent = request.repay_total_hours || 'N/A';
                document.getElementById('modal-reason').textContent = request.reason || 'N/A';
                document.getElementById('modal-assigned-to').textContent = request.assigned_to || 'N/A';
                document.getElementById('modal-location').textContent = request.location || 'N/A';
                document.getElementById('modal-contact-number').textContent = request.contact_number || 'N/A';

                // Populate printable form fields
                document.getElementById('print-requester-name').textContent = request.requester_name || 'N/A';
                document.getElementById('print-department-position').textContent = (request.department || 'N/A') + ' / ' + (request.position || 'N/A');
                document.getElementById('print-number-of-days').textContent = (request.number_of_days || 'N/A') + ' ថ្ងៃ';
                document.getElementById('print-request-date').textContent = request.request_date || 'N/A';
                document.getElementById('print-time-in').textContent = request.time_in || 'N/A';
                document.getElementById('print-time-out').textContent = request.time_out || 'N/A';
                document.getElementById('print-total-hours').textContent = request.total_hours || 'N/A';
                document.getElementById('print-reason').textContent = request.reason || 'N/A';
                document.getElementById('print-assigned-to').textContent = request.assigned_to || 'N/A';
                document.getElementById('print-requester-name-signature').textContent = request.requester_name || 'N/A';
                document.getElementById('print-request-date-signature').textContent = request.request_date ? date('d/m/Y', strtotime(request.request_date)) : 'N/A';

                // Set checkbox for request type
                const requestType = request.request_type || '';
                document.getElementById('print-annual').checked = requestType === 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)';
                document.getElementById('print-sick').checked = requestType === 'សម្រាកដោយជំងឺ (Sick Leave)';
                document.getElementById('print-forgot-fp').checked = requestType === 'ភ្លេចស្កេនមេដៃ (Forgot FP)';
                document.getElementById('print-maternity').checked = requestType === 'សម្រាកលំហែមាតុភាព (Maternity Leave)';
                document.getElementById('print-ot').checked = requestType === 'ថែមម៉ោង (OT)';
                document.getElementById('print-early').checked = requestType === 'ចេញមុនម៉ោង (Early)';
                document.getElementById('print-changing-off').checked = requestType === 'ប្តូរថ្ងៃសម្រាក (Changing day off)';
                document.getElementById('print-special').checked = requestType === 'សម្រាកពិសេស (Special Leave)';
                document.getElementById('print-late').checked = requestType === 'មកយឺត (Late)';
            });
        });

        // Function to handle printing the report (table)
        function printReport() {
            window.print();
        }

        // Function to handle printing the request form
        function printRequestForm() {
            const printableForm = document.getElementById('printableForm');
            const originalContent = document.body.innerHTML;
            document.body.innerHTML = printableForm.innerHTML;
            window.print();
            document.body.innerHTML = originalContent; // Restore original content
        }
    </script>
</body>
</html>