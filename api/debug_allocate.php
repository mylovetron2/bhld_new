<?php
/**
 * Debug allocate.php - test với dữ liệu thật
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

// Test 1: Kiểm tra có record nào có sl=0 không (chưa cấp phát)
$sql_check = "SELECT 
                ct.mact,
                ct.manv,
                nv.tennhanvien,
                ctd.mavt,
                vt.tenvt,
                ctd.sl,
                ctd.dmtg
              FROM bhld_ctctu ctd
              INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
              LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
              LEFT JOIN bhld_dmvattu vt ON ctd.mavt = vt.mavt
              WHERE ctd.sl = 0
              LIMIT 5";

$result = mysqli_query($conn, $sql_check);
$available = [];
while ($row = mysqli_fetch_assoc($result)) {
    $available[] = $row;
}

$tests = [
    'available_to_allocate' => [
        'count' => count($available),
        'sample' => $available
    ]
];

// Test 2: Simulate allocation
if (count($available) > 0) {
    $test_record = $available[0];
    $mact = mysqli_real_escape_string($conn, $test_record['mact']);
    $mavt = intval($test_record['mavt']);
    $ngnhan = date('Y-m-d'); // Hôm nay
    $dmtg = intval($test_record['dmtg']);
    
    // Calculate return date
    $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
    
    $tests['simulation'] = [
        'mact' => $mact,
        'mavt' => $mavt,
        'ngnhan' => $ngnhan,
        'dmtg' => $dmtg,
        'ngnhantt' => $ngnhantt,
        'sql' => "UPDATE bhld_ctctu SET sl = 1, ngnhan = '$ngnhan', ngnhantt = '$ngnhantt' WHERE mact = '$mact' AND mavt = $mavt"
    ];
    
    // Test 3: Check if update would work (dry run)
    $sql_test_update = "SELECT * FROM bhld_ctctu WHERE mact = '$mact' AND mavt = $mavt";
    $result_test = mysqli_query($conn, $sql_test_update);
    
    if ($result_test && mysqli_num_rows($result_test) > 0) {
        $tests['update_possible'] = true;
        $tests['current_record'] = mysqli_fetch_assoc($result_test);
    } else {
        $tests['update_possible'] = false;
        $tests['error'] = mysqli_error($conn);
    }
}

// Test 4: Test POST endpoint với curl
$tests['curl_command'] = 'curl -X POST http://diavatly.com/BHLD/api/allocate.php -H "Content-Type: application/json" -d \'{"mact":"'.$available[0]['mact'].'","mavt":'.$available[0]['mavt'].',"ngnhan":"'.date('Y-m-d').'"}\'';

echo json_encode([
    'success' => true,
    'message' => 'Debug allocate API',
    'tests' => $tests
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
