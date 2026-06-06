<?php
/**
 * Test kết nối DB với credentials mới
 * Truy cập: diavatly.cloud/projectBHLD/api/test_db2.php
 * XÓA SAU KHI KIỂM TRA XONG
 */
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

define('DB_HOST',    'diavatly.com');
define('DB_USER',    'diavatly_master');
define('DB_PASS',    '12345678');
define('DB_NAME',    'diavatly_db');
define('DB_PORT',    '3306');
define('DB_CHARSET', 'latin1');

$out = [];
$out['host'] = DB_HOST;
$out['user'] = DB_USER;
$out['database'] = DB_NAME;

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);

if (!$conn) {
    $out['status'] = 'FAILED';
    $out['error']  = mysqli_connect_error();
    $out['errno']  = mysqli_connect_errno();
} else {
    mysqli_set_charset($conn, DB_CHARSET);
    $out['status']        = 'OK - Kết nối thành công!';
    $out['mysql_version'] = mysqli_get_server_info($conn);
    $out['charset']       = DB_CHARSET;

    // Liệt kê các bảng
    $r = mysqli_query($conn, 'SHOW TABLES');
    $tables = [];
    while ($row = mysqli_fetch_row($r)) $tables[] = $row[0];
    $out['tables'] = $tables;
    $out['table_count'] = count($tables);

    // Kiểm tra bảng BHLD
    $bhldTables = ['bhld_nhanvien', 'bhld_phongban', 'bhld_chungtu', 'bhld_ctchungtu', 'bhld_vattu'];
    foreach ($bhldTables as $t) {
        $r2 = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$t`");
        if ($r2) {
            $row2 = mysqli_fetch_assoc($r2);
            $out['bhld'][$t] = (int)$row2['cnt'] . ' bản ghi';
        } else {
            $out['bhld'][$t] = 'Không tìm thấy bảng';
        }
    }

    mysqli_close($conn);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
