<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    // Get table structure
    $query = "DESCRIBE bhld_ctctu";
    $result = $conn->query($query);
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row;
    }
    
    // Get primary key info
    $pkQuery = "SHOW KEYS FROM bhld_ctctu WHERE Key_name = 'PRIMARY'";
    $pkResult = $conn->query($pkQuery);
    
    $primaryKeys = [];
    while ($row = $pkResult->fetch_assoc()) {
        $primaryKeys[] = $row['Column_name'];
    }
    
    echo json_encode([
        'success' => true,
        'columns' => $columns,
        'primary_keys' => $primaryKeys
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
