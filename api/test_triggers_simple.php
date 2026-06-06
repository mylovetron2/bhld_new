<?php
// Simple trigger test with detailed error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Trigger Test</title></head><body>";
echo "<h1>Simple Trigger Test</h1>";

try {
    echo "<p>Step 1: Connecting to database...</p>";
    
    $conn = new mysqli("localhost", "diavatly_ltd", "cntt2019", "diavatly_ltd");
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p style='color:green;'>✓ Connected successfully</p>";
    
    $conn->set_charset("utf8");
    
    echo "<p>Step 2: Getting MySQL version...</p>";
    $version = $conn->server_info;
    echo "<p style='color:green;'>✓ MySQL Version: {$version}</p>";
    
    echo "<p>Step 3: Checking triggers on bhld_ctctu...</p>";
    $result = $conn->query("SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'");
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    $count = $result->num_rows;
    echo "<p style='color:green;'>✓ Found {$count} triggers</p>";
    
    if ($count > 0) {
        echo "<ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li><strong>{$row['Trigger']}</strong> - {$row['Timing']} {$row['Event']}</li>";
        }
        echo "</ul>";
    }
    
    $conn->close();
    echo "<p style='color:green;'>✓ All tests passed!</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
