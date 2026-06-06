<?php
/**
 * Kiểm tra và liệt kê triggers trên bảng bhld_ctctu
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

$results = [];

// 1. Kiểm tra triggers
$sql_triggers = "SHOW TRIGGERS WHERE `Table` = 'bhld_ctctu'";
$result = mysqli_query($conn, $sql_triggers);

$triggers = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $triggers[] = [
            'name' => $row['Trigger'],
            'event' => $row['Event'],
            'timing' => $row['Timing'],
            'statement' => $row['Statement']
        ];
    }
}

$results['triggers'] = $triggers;
$results['trigger_count'] = count($triggers);

// 2. Kiểm tra table engine
$sql_engine = "SHOW TABLE STATUS WHERE Name = 'bhld_ctctu'";
$result = mysqli_query($conn, $sql_engine);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $results['table_engine'] = $row['Engine'];
    $results['table_collation'] = $row['Collation'];
}

// 3. Thử UPDATE một record test (không thay đổi gì)
$sql_test = "SELECT mact, mavt, sl FROM bhld_ctctu WHERE sl = 0 LIMIT 1";
$result = mysqli_query($conn, $sql_test);

if ($result && mysqli_num_rows($result) > 0) {
    $test_row = mysqli_fetch_assoc($result);
    $results['test_record'] = $test_row;
    
    // Thử UPDATE với giá trị giống hệt
    $mact = mysqli_real_escape_string($conn, $test_row['mact']);
    $mavt = intval($test_row['mavt']);
    
    $sql_update_test = "UPDATE bhld_ctctu SET sl = 0 WHERE mact = '$mact' AND mavt = $mavt LIMIT 1";
    
    if (mysqli_query($conn, $sql_update_test)) {
        $results['test_update'] = 'SUCCESS - No trigger error';
        $results['affected_rows'] = mysqli_affected_rows($conn);
    } else {
        $results['test_update'] = 'FAILED';
        $results['test_error'] = mysqli_error($conn);
    }
}

// 4. Lệnh để DROP trigger (nếu cần)
if (count($triggers) > 0) {
    $results['drop_commands'] = [];
    foreach ($triggers as $trigger) {
        $results['drop_commands'][] = "DROP TRIGGER IF EXISTS `{$trigger['name']}`;";
    }
}

echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
