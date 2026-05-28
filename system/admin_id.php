<?php
// Include database configuration
require_once 'config.php';

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

$positionPrefixes = [
    "ព័ត៌មានវិទ្យា" => "IT",
    "គិតលុយ" => "CSH",
    "រដ្ឋបាលទូទៅ" => "ADM",
    "បុគ្គលិកផ្នែកលក់" => "SAL",
    "បុគ្គលិកផ្នែកស្តុក318" => "STK318",
    "ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ" => "MSTK",
    "ប្រធានឃ្លាំង៣១៨និងហាងទំនិញ" => "WH318",
    "បុគ្គលិកផ្នែកគណនេយ្យ" => "ACC",
    "ប្រមូលសាច់ប្រាក់" => "COL",
    "ប្រធានឃ្លាំង CH1" => "WHCH1",
    "រដ្ឋបាលឃ្លាំង CH1" => "ADMCH1",
    "ជំនួយការប្រធានឃ្លាំង CH1" => "ASCH1",
    "ប្រធានឃ្លាំង CKD" => "WHCKD",
    "ជំនួយការប្រធានឃ្លាំង CKD" => "ASCKD",
    "ប្រធានរដ្ឋបាលឃ្លាំង CKD" => "ADCKD",
    "ប្រធានឃ្លាំង ST1" => "WHST1",
    "ប្រធានឃ្លាំង PSP" => "WHPSP",
];

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Fetch all daily reports
    $stmt = $pdo->prepare("SELECT id, position, report_date FROM daily_reports");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reports as $report) {
        $oldId = $report['id'];
        $position = $report['position'];
        $reportDate = new DateTime($report['report_date']);
        $dateCode = $reportDate->format('Ymd');

        // Get the prefix for the position
        $prefix = $positionPrefixes[$position] ?? 'GEN';

        // Extract the sequence number from the old ID (e.g., OLD-ID-0001 -> 0001)
        $sequence = substr($oldId, -4);

        // Generate the new ID
        $newId = "{$prefix}-{$dateCode}-{$sequence}";

        // Update the ID in daily_reports
        $updateStmt = $pdo->prepare("UPDATE daily_reports SET id = :newId WHERE id = :oldId");
        $updateStmt->execute([':newId' => $newId, ':oldId' => $oldId]);

        // Update the corresponding report_id in report_tasks
        $updateTasksStmt = $pdo->prepare("UPDATE report_tasks SET report_id = :newId WHERE report_id = :oldId");
        $updateTasksStmt->execute([':newId' => $newId, ':oldId' => $oldId]);
    }

    echo "Migration completed successfully!";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>