<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Test Database Connection</h3>";

// Test 1: config.php
echo "<h4>Test 1: Using config.php</h4>";
try {
    require_once 'config.php';
    if (isset($conn) && $conn) {
        echo "✓ Connected via config.php<br>";
        echo "Host: " . mysqli_get_host_info($conn) . "<br>";
        
        // Test query
        $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bhld_nhanvien");
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            echo "✓ Query OK - Total employees: " . $row['cnt'] . "<br>";
        }
    } else {
        echo "✗ Connection failed via config.php<br>";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test 2: Check if db.php exists in parent
echo "<h4>Test 2: Check parent db.php</h4>";
$parentDb = dirname(__DIR__) . '/db.php';
if (file_exists($parentDb)) {
    echo "✓ File exists: $parentDb<br>";
} else {
    echo "✗ File NOT exists: $parentDb<br>";
}

// Test 3: Check local db_connection.php
echo "<h4>Test 3: Check local db_connection.php</h4>";
$localDb = __DIR__ . '/db_connection.php';
if (file_exists($localDb)) {
    echo "✓ File exists: $localDb<br>";
} else {
    echo "✗ File NOT exists: $localDb<br>";
}
?>
