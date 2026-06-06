<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Database connection - standalone, no config.php dependency
$servername = "localhost";
$username = "diavatly_ltd";
$password = "cntt2019";
$dbname = "diavatly_ltd";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Current Triggers on bhld_ctctu</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        .trigger-box { background: #252526; padding: 15px; margin: 20px 0; border-left: 4px solid #007acc; }
        .trigger-name { color: #dcdcaa; font-size: 18px; font-weight: bold; }
        .trigger-meta { color: #9cdcfe; margin: 10px 0; }
        pre { background: #1e1e1e; padding: 15px; border: 1px solid #3c3c3c; overflow-x: auto; color: #ce9178; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #3c3c3c; padding: 10px; text-align: left; }
        th { background: #252526; color: #569cd6; }
        tr:nth-child(even) { background: #252526; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007acc; color: white; text-decoration: none; border-radius: 4px; cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #005a9e; }
        .btn-success { background: #4ec9b0; }
        .btn-success:hover { background: #3a9d84; }
        .btn-danger { background: #f48771; }
        .btn-danger:hover { background: #d36b54; }
        .action-bar { margin: 20px 0; padding: 15px; background: #252526; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Current Triggers on BHLD Tables</h1>
    
    <div class="action-bar">
        <a href="backup_triggers.php" class="btn btn-success" target="_blank">Download Backup (SQL)</a>
        <a href="restore_triggers.php" class="btn btn-danger">Restore Triggers</a>
        <a href="test_triggers_simple.php" class="btn">Test Connection</a>
    </div>

<?php
try {
    // Get MySQL version first before any operations
    $mysqlVersion = $conn->server_info;
    echo "<p>MySQL Version: <strong>{$mysqlVersion}</strong></p>";
    
    // Get list of triggers for both tables
    $query = "SHOW TRIGGERS WHERE `Table` IN ('bhld_ctu', 'bhld_ctctu')";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    if ($result->num_rows == 0) {
        echo "<p class='warning'>WARNING: No triggers found on tables bhld_ctu and bhld_ctctu</p>";
    } else {
        echo "<h2>Trigger Summary ({$result->num_rows} triggers)</h2>";
        echo "<table>";
        echo "<tr><th>#</th><th>Table</th><th>Trigger Name</th><th>Timing</th><th>Event</th></tr>";
        
        $triggers = [];
        $index = 1;
        
        while ($row = $result->fetch_assoc()) {
            $triggers[] = array('name' => $row['Trigger'], 'table' => $row['Table']);
            echo "<tr>";
            echo "<td>{$index}</td>";
            echo "<td><strong>{$row['Table']}</strong></td>";
            echo "<td class='trigger-name'>{$row['Trigger']}</td>";
            echo "<td>{$row['Timing']}</td>";
            echo "<td>{$row['Event']}</td>";
            echo "</tr>";
            $index++;
        }
        echo "</table>";
        
        // Show detailed definition for each trigger
        echo "<h2>Detailed Trigger Definitions</h2>";
        
        foreach ($triggers as $trigger) {
            $triggerName = $trigger['name'];
            $tableName = $trigger['table'];
            echo "<div class='trigger-box'>";
            echo "<div class='trigger-name'>{$triggerName} (Table: {$tableName})</div>";
            
            $detailQuery = "SHOW CREATE TRIGGER `{$triggerName}`";
            $detailResult = $conn->query($detailQuery);
            
            if ($detailResult && $detailRow = $detailResult->fetch_assoc()) {
                // Try different column names based on MySQL version
                $sqlStatement = '';
                if (isset($detailRow['SQL Original Statement'])) {
                    $sqlStatement = $detailRow['SQL Original Statement'];
                } elseif (isset($detailRow['Create Trigger'])) {
                    $sqlStatement = $detailRow['Create Trigger'];
                } else {
                    $sqlStatement = print_r($detailRow, true);
                }
                
                echo "<pre>" . htmlspecialchars($sqlStatement) . "</pre>";
                
                // Check if uses JSON_OBJECT
                if (stripos($sqlStatement, 'JSON_OBJECT') !== false) {
                    echo "<p class='error'>WARNING: This trigger uses JSON_OBJECT() which is NOT compatible with MySQL 5.6!</p>";
                } else {
                    echo "<p class='success'>This trigger is MySQL 5.6 compatible (no JSON_OBJECT)</p>";
                }
            } else {
                echo "<p class='error'>Could not retrieve trigger definition</p>";
            }
            echo "</div>";
        }
    }
    
    // Show table structures
    foreach (array('bhld_ctu', 'bhld_ctctu') as $tableName) {
        echo "<h2>Table Structure: {$tableName}</h2>";
        $structQuery = "DESCRIBE {$tableName}";
        $structResult = $conn->query($structQuery);
        
        if ($structResult) {
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            
            while ($row = $structResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td><strong>{$row['Field']}</strong></td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "<td>" . (isset($row['Default']) ? $row['Default'] : 'NULL') . "</td>";
                echo "<td>{$row['Extra']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Store MySQL version before closing connection
$mysqlVersionInfo = isset($conn) ? mysqli_get_server_info($conn) : 'Unknown';
if (isset($conn)) {
    $conn->close();
}
?>

<p style="margin-top: 40px; color: #858585;">
    Generated on <?php echo date('Y-m-d H:i:s'); ?><br>
    MySQL Version: <?php echo $mysqlVersionInfo; ?><br>
    <a href="test_allocate_production.html" style="color: #569cd6;">← Back to Test Page</a>
</p>

</body>
</html>
