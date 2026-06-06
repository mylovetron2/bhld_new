<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$mact_current = '2013-04-P07-16655';
$mavt = 500120;

// Get dmtg and master info (QUAN TRỌNG: lấy ngnhan từ detail, không dùng ngct)
$sql = "SELECT c.dmtg, c.ngnhan, m.manv, m.mapb, m.ngct 
        FROM bhld_ctctu c 
        JOIN bhld_ctu m ON c.mact = m.mact 
        WHERE c.mact = '$mact_current' AND c.mavt = $mavt";

$result = mysqli_query($conn, $sql);
if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['error' => 'Record not found']);
    exit;
}

$row = mysqli_fetch_assoc($result);
$dmtg = $row['dmtg'];
$ngnhan = $row['ngnhan'];
$manv = $row['manv'];
$mapb = $row['mapb'];
$ngct = $row['ngct'];

// Calculate using ALLOCATE logic: ngct_next = ngnhan + dmtg (ĐÚNG)
$ngct_next_allocate = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
$year_month_allocate = date('Y-m', strtotime($ngct_next_allocate));
$manv_formatted_allocate = $manv;
if (is_numeric($manv) && strlen($manv) == 4) {
    $manv_formatted_allocate = '0' . $manv;
}
$mact_next_allocate = $year_month_allocate . '-' . $mapb . '-' . $manv_formatted_allocate;

// Calculate using DEALLOCATE logic: ngct_next = ngnhan + dmtg (ĐÚNG)
$ngct_next_deallocate = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
$year_month_deallocate = date('Y-m', strtotime($ngct_next_deallocate));
$manv_formatted_deallocate = $manv;
if (is_numeric($manv) && strlen($manv) == 4) {
    $manv_formatted_deallocate = '0' . $manv;
}
$mact_next_deallocate = $year_month_deallocate . '-' . $mapb . '-' . $manv_formatted_deallocate;

// Check what's in database
$check_master = mysqli_query($conn, "SELECT * FROM bhld_ctu WHERE mact LIKE '2013-10-P07-%'");
$masters_in_db = [];
while ($m = mysqli_fetch_assoc($check_master)) {
    $masters_in_db[] = $m;
}

$check_detail = mysqli_query($conn, "SELECT * FROM bhld_ctctu WHERE mavt = $mavt AND mact LIKE '2013-10-P07-%'");
$details_in_db = [];
while ($d = mysqli_fetch_assoc($check_detail)) {
    $details_in_db[] = $d;
}

echo json_encode([
    'current' => [
        'mact' => $mact_current,
        'manv' => $manv,
        'manv_length' => strlen($manv),
        'mapb' => $mapb,
        'ngct' => $ngct,
        'dmtg' => $dmtg
    ],
    'calculated' => [
        'allocate' => [
            'mact_next' => $mact_next_allocate,
            'manv_formatted' => $manv_formatted_allocate
        ],
        'deallocate' => [
            'mact_next' => $mact_next_deallocate,
            'manv_formatted' => $manv_formatted_deallocate
        ],
        'match' => ($mact_next_allocate === $mact_next_deallocate)
    ],
    'database' => [
        'masters_2013_10' => $masters_in_db,
        'details_2013_10_mavt_500120' => $details_in_db
    ]
], JSON_PRETTY_PRINT);
?>
