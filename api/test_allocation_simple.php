<?php
/**
 * Simple test - check if allocation_history.php exists and returns data
 */

require_once 'config.php';

// Test 1: Check file exists
$file_path = __DIR__ . '/allocation_history.php';
$file_exists = file_exists($file_path);

// Test 2: Try to get data from endpoint
$url = 'http://diavatly.com/BHLD/api/allocation_history.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Test 3: Direct query
$sql = "SELECT 
            ct.mact,
            ct.manv,
            ct.ngct,
            nv.tennhanvien,
            pb.tenphong as tenphongban,
            ctd.mavt,
            vt.tenvt,
            vt.dvt,
            ctd.sl,
            ctd.ngnhan,
            ctd.ngnhantt,
            ctd.dmtg
        FROM bhld_ctctu ctd
        INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
        LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
        LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
        LEFT JOIN bhld_dmvattu vt ON ctd.mavt = vt.mavt
        WHERE ctd.sl = 1
        LIMIT 3";

$result = mysqli_query($conn, $sql);
$query_error = mysqli_error($conn);

$data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'test_results' => [
        'file_exists' => $file_exists,
        'file_path' => $file_path,
        'api_endpoint' => $url,
        'http_code' => $http_code,
        'curl_error' => $curl_error ?: 'None',
        'api_response_length' => strlen($response ?? ''),
        'direct_query_error' => $query_error ?: 'None',
        'direct_query_count' => count($data),
        'sample_data' => array_slice($data, 0, 2),
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
?>
