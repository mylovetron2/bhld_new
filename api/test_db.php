<?php
/**
 * Test kết nối cơ sở dữ liệu BHLD
 * Truy cập: diavatly.cloud/projectBHLD/api/test_db.php
 * XÓA FILE NÀY SAU KHI KIỂM TRA XONG
 */
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$conn = mysqli_connect('diavatly.com', 'diavatly_ltd', '12345678', 'diavatly_ltd', 3306);

$results = [];

if (!$conn) {
    $results['connection'] = '❌ Kết nối thất bại: ' . mysqli_connect_error();
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
$results['connection'] = '✅ Kết nối thành công';
$results['mysql_version'] = mysqli_get_server_info($conn);

// Liệt kê tất cả bảng
$r = mysqli_query($conn, 'SHOW TABLES');
$allTables = [];
while ($row = mysqli_fetch_row($r)) $allTables[] = $row[0];
$results['all_tables'] = $allTables;

// Kiểm tra các bảng BHLD
$tables = ['bhld_nhanvien', 'bhld_phongban', 'bhld_chungtu', 'bhld_ctchungtu', 'bhld_vattu'];
foreach ($tables as $table) {
    $r2 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$table`");
    if ($r2) {
        $row2 = mysqli_fetch_assoc($r2);
        $results[$table] = '✅ ' . $row2['cnt'] . ' bản ghi';
    } else {
        $results[$table] = '❌ ' . mysqli_error($conn);
    }
}

mysqli_close($conn);
echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

