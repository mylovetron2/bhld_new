<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$mact_next = '2013-10-P07-16655';
$mavt = 500120;

// Test different WHERE conditions
$sql1 = "DELETE FROM bhld_ctctu WHERE mact='$mact_next' AND mavt=$mavt";
$sql2 = "DELETE FROM bhld_ctctu WHERE mact='$mact_next' AND mavt='$mavt'";

// Check first
$check = mysqli_query($conn, "SELECT * FROM bhld_ctctu WHERE mact='$mact_next' AND mavt=$mavt");
$exists_int = mysqli_num_rows($check);

$check2 = mysqli_query($conn, "SELECT * FROM bhld_ctctu WHERE mact='$mact_next' AND mavt='$mavt'");
$exists_str = mysqli_num_rows($check2);

// Get actual data
$data = mysqli_query($conn, "SELECT mact, mavt, CAST(mavt AS CHAR) as mavt_str FROM bhld_ctctu WHERE mact='$mact_next'");
$records = [];
while ($row = mysqli_fetch_assoc($data)) {
    $records[] = $row;
}

echo json_encode([
    'mact_next' => $mact_next,
    'mavt_param' => $mavt,
    'mavt_type' => gettype($mavt),
    'check' => [
        'with_int_mavt' => $exists_int,
        'with_str_mavt' => $exists_str
    ],
    'sql' => [
        'int' => $sql1,
        'str' => $sql2
    ],
    'records_in_db' => $records,
    'note' => 'If both exists_int and exists_str = 1, then DELETE should work with either format'
], JSON_PRETTY_PRINT);
?>
