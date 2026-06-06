<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$servername = "localhost";
$username = "diavatly_ltd";
$password = "Huynh2017";
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
    <title>Current Triggers</title>
    <style>
        body { font-family: 'Courier New', monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        h2 { color: #569cd6; margin-top: 30px; }
        .box { background: #252526; padding: 15px; margin: 20px 0; border-left: 4px solid #007acc; }
        pre { background: #1e1e1e; padding: 15px; border: 1px solid #3c3c3c; overflow-x: auto; color: #ce9178; font-size: 12px; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #3c3c3c; padding: 10px; text-align: left; }
        th { background: #252526; color: #569cd6; }
    </style>
</head>
<body>
    <h1>📋 Triggers on bhld_ctctu</h1>

<?php
try {
    $mysqlVersion = $conn->server_info;
    echo "<p>MySQL: <strong>{$mysqlVersion}</strong></p>";
    
    $query = "SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }
    
    if ($result->num_rows == 0) {
        echo "<p class='warning'>⚠️ No triggers found</p>";
    } else {
        echo "<h2>Triggers ({$result->num_rows})</h2>";
        echo "<table><tr><th>#</th><th>Name</th><th>Timing</th><th>Event</th></tr>";
        
        $triggers = [];
        $i = 1;
        
        while ($row = $result->fetch_assoc()) {
            $triggers[] = $row['Trigger'];
            echo "<tr><td>{$i}</td><td>{$row['Trigger']}</td><td>{$row['Timing']}</td><td>{$row['Event']}</td></tr>";
            $i++;
        }
        echo "</table>";
        
        echo "<h2>Details</h2>";
        
        foreach ($triggers as $name) {
            echo "<div class='box'><strong>{$name}</strong>";
            
            $q = "SHOW CREATE TRIGGER `{$name}`";
            $r = $conn->query($q);
            
            if ($r && $d = $r->fetch_assoc()) {
                $sql = $d['SQL Original Statement'] ?? $d['Create Trigger'] ?? print_r($d, true);
                echo "<pre>" . htmlspecialchars($sql) . "</pre>";
                
                if (stripos($sql, 'JSON_OBJECT') !== false) {
                    echo "<p class='error'>❌ Uses JSON_OBJECT (MySQL 5.6 incompatible)</p>";
                } else {
                    echo "<p class='success'>✅ MySQL 5.6 compatible</p>";
                }
            }
            echo "</div>";
        }
    }
    
    echo "<h2>Table Structure</h2>";
    $r = $conn->query("DESCRIBE bhld_ctctu");
    
    if ($r) {
        echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $r->fetch_assoc()) {
            echo "<tr><td><strong>{$row['Field']}</strong></td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<p style="margin-top: 40px; color: #858585;">
    <?php echo date('Y-m-d H:i:s'); ?><br>
    <a href="test_allocate_production.html" style="color: #569cd6;">← Back</a>
</p>

</body>
</html>
