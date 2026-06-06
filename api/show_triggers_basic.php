<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

$conn = new mysqli("localhost", "diavatly_ltd", "cntt2019", "diavatly_ltd");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Triggers on bhld_ctctu</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: white; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #4CAF50; color: white; }
        pre { background: #f4f4f4; padding: 15px; overflow-x: auto; border: 1px solid #ddd; }
        .info { background: #e7f3fe; padding: 10px; margin: 10px 0; border-left: 4px solid #2196F3; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Current Triggers on bhld_ctctu</h1>
    
<?php
$mysqlVersion = $conn->server_info;
echo "<div class='info'>MySQL Version: <strong>$mysqlVersion</strong></div>";

// Get triggers
$query = "SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'";
$result = $conn->query($query);

if (!$result) {
    echo "<p class='error'>Query Error: " . $conn->error . "</p>";
} else {
    $count = $result->num_rows;
    echo "<p>Found <strong>$count</strong> trigger(s)</p>";
    
    if ($count > 0) {
        echo "<table>";
        echo "<tr><th>No</th><th>Trigger Name</th><th>Timing</th><th>Event</th></tr>";
        
        $triggers = array();
        $index = 1;
        
        while ($row = $result->fetch_assoc()) {
            $triggers[] = $row['Trigger'];
            echo "<tr>";
            echo "<td>$index</td>";
            echo "<td><strong>" . $row['Trigger'] . "</strong></td>";
            echo "<td>" . $row['Timing'] . "</td>";
            echo "<td>" . $row['Event'] . "</td>";
            echo "</tr>";
            $index++;
        }
        echo "</table>";
        
        // Show each trigger definition
        echo "<h2>Trigger Definitions</h2>";
        
        foreach ($triggers as $triggerName) {
            echo "<h3>$triggerName</h3>";
            
            $detailQuery = "SHOW CREATE TRIGGER `$triggerName`";
            $detailResult = $conn->query($detailQuery);
            
            if ($detailResult) {
                $detailRow = $detailResult->fetch_assoc();
                
                $sqlStatement = '';
                if (isset($detailRow['SQL Original Statement'])) {
                    $sqlStatement = $detailRow['SQL Original Statement'];
                } else if (isset($detailRow['Create Trigger'])) {
                    $sqlStatement = $detailRow['Create Trigger'];
                } else {
                    $sqlStatement = print_r($detailRow, true);
                }
                
                echo "<pre>" . htmlspecialchars($sqlStatement, ENT_QUOTES, 'UTF-8') . "</pre>";
                
                if (stripos($sqlStatement, 'JSON_OBJECT') !== false) {
                    echo "<p class='error'>WARNING: Uses JSON_OBJECT (NOT MySQL 5.6 compatible)</p>";
                } else {
                    echo "<p class='success'>MySQL 5.6 compatible</p>";
                }
            } else {
                echo "<p class='error'>Could not retrieve trigger definition</p>";
            }
        }
    } else {
        echo "<p class='error'>No triggers found</p>";
    }
}

// Show table structure
echo "<h2>Table Structure: bhld_ctctu</h2>";
$structResult = $conn->query("DESCRIBE bhld_ctctu");

if ($structResult) {
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    while ($row = $structResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . (isset($row['Default']) ? $row['Default'] : 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

<p style="margin-top: 40px; color: #999;">
    Generated: <?php echo date('Y-m-d H:i:s'); ?>
</p>

</body>
</html>
