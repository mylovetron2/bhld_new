<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

try {
    // Get trigger definitions
    $query = "SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'";
    $result = $conn->query($query);
    
    $triggers = [];
    
    while ($row = $result->fetch_assoc()) {
        $triggerName = $row['Trigger'];
        
        // Get detailed trigger definition
        $detailQuery = "SHOW CREATE TRIGGER `$triggerName`";
        $detailResult = $conn->query($detailQuery);
        $detailRow = $detailResult->fetch_assoc();
        
        $triggers[] = [
            'name' => $triggerName,
            'timing' => $row['Timing'],
            'event' => $row['Event'],
            'table' => $row['Table'],
            'statement' => $row['Statement'],
            'full_definition' => $detailRow['SQL Original Statement'] ?? $detailRow['Create Trigger'] ?? 'N/A'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($triggers),
        'triggers' => $triggers
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
