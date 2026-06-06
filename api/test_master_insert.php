<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// Simple test to check master insert
$mact = '2013-04-P07-16655';
$mavt = 500120;
$dmtg = 6;

// Get master
$result = mysqli_query($conn, "SELECT manv, madm, mapb, ngct FROM bhld_ctu WHERE mact='$mact'");

if (!$result) {
    echo json_encode(['error' => 'Query failed: ' . mysqli_error($conn)]);
    exit;
}

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Master not found']);
    exit;
}

$master = mysqli_fetch_assoc($result);

// Get ngnhan from detail record (QUAN TRỌNG: dùng ngnhan, KHÔNG dùng ngct)
$detail_result = mysqli_query($conn, "SELECT ngnhan FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
if (!$detail_result || mysqli_num_rows($detail_result) === 0) {
    echo json_encode(['error' => 'Detail record not found']);
    exit;
}
$detail = mysqli_fetch_assoc($detail_result);
$ngnhan = $detail['ngnhan'];

// Calculate next period: ngct_next = ngnhan + dmtg (ĐÚNG)
$ngct_next = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
$year_month = date('Y-m', strtotime($ngct_next));
$manv_fmt = (is_numeric($master['manv']) && strlen($master['manv']) == 4) ? '0' . $master['manv'] : $master['manv'];
$mact_next = $year_month . '-' . $master['mapb'] . '-' . $manv_fmt;

// Check if exists
$check = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact='$mact_next'");
$exists = (mysqli_num_rows($check) > 0);

$result_data = [
    'master' => $master,
    'ngct_next' => $ngct_next,
    'mact_next' => $mact_next,
    'exists' => $exists
];

if (!$exists) {
    // Try to insert
    $sql = "INSERT INTO bhld_ctu (mact, manv, madm, mapb, ngct) VALUES ('$mact_next', '{$master['manv']}', '{$master['madm']}', '{$master['mapb']}', '$ngct_next')";
    $result_data['sql'] = $sql;
    
    $insert_result = mysqli_query($conn, $sql);
    
    if ($insert_result) {
        $result_data['insert_success'] = true;
        $result_data['affected_rows'] = mysqli_affected_rows($conn);
        $result_data['insert_id'] = mysqli_insert_id($conn);
    } else {
        $result_data['insert_success'] = false;
        $result_data['insert_error'] = mysqli_error($conn);
        $result_data['mysql_errno'] = mysqli_errno($conn);
    }
}

echo json_encode($result_data, JSON_PRETTY_PRINT);
?>
