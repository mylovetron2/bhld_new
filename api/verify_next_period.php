<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$mact = '2013-10-P07-16655';
$mavt = 500120;

// Check master
$master_result = mysqli_query($conn, "SELECT * FROM bhld_ctu WHERE mact='$mact'");
$master_data = null;
if ($master_result && mysqli_num_rows($master_result) > 0) {
    $master_data = mysqli_fetch_assoc($master_result);
}

// Check detail
$detail_result = mysqli_query($conn, "SELECT * FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
$detail_data = null;
if ($detail_result && mysqli_num_rows($detail_result) > 0) {
    $detail_data = mysqli_fetch_assoc($detail_result);
}

// Count all records for this master
$count_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bhld_ctctu WHERE mact='$mact'");
$count_row = mysqli_fetch_assoc($count_result);

echo json_encode([
    'mact' => $mact,
    'master_found' => ($master_data !== null),
    'master_record' => $master_data,
    'detail_found' => ($detail_data !== null),
    'detail_record' => $detail_data,
    'total_items_in_master' => $count_row['cnt']
], JSON_PRETTY_PRINT);
?>
