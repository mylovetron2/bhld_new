<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP OK<br>";

// Try to use config.php if exists
if (file_exists('config.php')) {
    require_once 'config.php';
    echo "Using config.php<br>";
    
    if (isset($conn) && $conn->ping()) {
        echo "DB Connected via config.php: " . $conn->server_info . "<br>";
    } else {
        die("Config.php loaded but no connection");
    }
} else {
    echo "config.php not found, trying direct connection...<br>";
    $conn = @new mysqli("localhost", "diavatly_ltd", "Huynh2017", "diavatly_ltd");
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "DB Connected: " . $conn->server_info . "<br>";
    $conn->set_charset("utf8");
}

$result = $conn->query("SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'");

if (!$result) {
    die("Query failed: " . $conn->error);
}

echo "Triggers found: " . $result->num_rows . "<br><br>";

while ($row = $result->fetch_assoc()) {
    echo $row['Trigger'] . " - " . $row['Timing'] . " " . $row['Event'] . "<br>";
}

$conn->close();
