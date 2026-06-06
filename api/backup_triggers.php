<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "diavatly_ltd", "cntt2019", "diavatly_ltd");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");

// Generate filename with timestamp
$filename = "bhld_triggers_backup_" . date('Y-m-d_His') . ".sql";

// Start building SQL content
$sqlContent = "-- ================================================================\n";
$sqlContent .= "-- BHLD Triggers Backup\n";
$sqlContent .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sqlContent .= "-- MySQL Version: " . $conn->server_info . "\n";
$sqlContent .= "-- ================================================================\n\n";

// Get all triggers for bhld_ctu and bhld_ctctu
$query = "SHOW TRIGGERS WHERE `Table` IN ('bhld_ctu', 'bhld_ctctu')";
$result = $conn->query($query);

if (!$result) {
    die("Error getting triggers: " . $conn->error);
}

if ($result->num_rows == 0) {
    die("No triggers found to backup");
}

$sqlContent .= "-- Drop existing triggers\n";
$sqlContent .= "-- ================================================================\n\n";

$triggers = array();

while ($row = $result->fetch_assoc()) {
    $triggers[] = $row['Trigger'];
    $sqlContent .= "DROP TRIGGER IF EXISTS `" . $row['Trigger'] . "`;\n";
}

$sqlContent .= "\n-- Create triggers\n";
$sqlContent .= "-- ================================================================\n\n";
$sqlContent .= "DELIMITER $$\n\n";

// Get detailed definition for each trigger
foreach ($triggers as $triggerName) {
    $detailQuery = "SHOW CREATE TRIGGER `$triggerName`";
    $detailResult = $conn->query($detailQuery);
    
    if ($detailResult && $detailRow = $detailResult->fetch_assoc()) {
        // Try different column names based on MySQL version
        $sqlStatement = '';
        if (isset($detailRow['SQL Original Statement'])) {
            $sqlStatement = $detailRow['SQL Original Statement'];
        } else if (isset($detailRow['Create Trigger'])) {
            $sqlStatement = $detailRow['Create Trigger'];
        }
        
        if (!empty($sqlStatement)) {
            $sqlContent .= "-- Trigger: $triggerName\n";
            $sqlContent .= $sqlStatement . "$$\n\n";
        }
    }
}

$sqlContent .= "DELIMITER ;\n\n";
$sqlContent .= "-- ================================================================\n";
$sqlContent .= "-- Backup completed\n";
$sqlContent .= "-- Total triggers: " . count($triggers) . "\n";
$sqlContent .= "-- ================================================================\n";

$conn->close();

// Send file as download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($sqlContent));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

echo $sqlContent;
exit;
?>
