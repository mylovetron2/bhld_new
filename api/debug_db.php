<?php
/**
 * Debug kết nối DB - không dùng config.php
 * Truy cập: diavatly.cloud/projectBHLD/api/debug_db.php
 * XÓA SAU KHI KIỂM TRA XONG
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

$out = [];

// 1. Thông tin PHP & server
$out['php_version']    = PHP_VERSION;
$out['server_name']    = $_SERVER['SERVER_NAME'] ?? 'n/a';
$out['document_root']  = $_SERVER['DOCUMENT_ROOT'] ?? 'n/a';
$out['script_path']    = __FILE__;
$out['mysqli_loaded']  = extension_loaded('mysqli') ? 'yes' : 'NO - mysqli not loaded!';

// 2. Kiểm tra file db_connection.php có tồn tại không
$dbFile = __DIR__ . '/db_connection.php';
$out['db_connection_file_exists'] = file_exists($dbFile) ? 'yes' : 'NO - file not found at ' . $dbFile;

// 3. Lấy IP hiện tại của server này (diavatly.cloud)
$out['server_ip_outbound'] = file_get_contents('https://api.ipify.org') ?: 'không lấy được';
$out['server_ip_local']    = gethostbyname(gethostname());

// 4. Thử kết nối tới diavatly.com (remote MySQL)
$hosts = ['diavatly.com', 'localhost', '127.0.0.1'];
$username = 'diavatly_cntt';
$password = 'cntt2019';
$database = 'diavatly_ltd';

mysqli_report(MYSQLI_REPORT_OFF);

foreach ($hosts as $host) {
    $conn = mysqli_connect($host, $username, $password, $database);
    if ($conn) {
        $out['connect_success_host'] = $host;
        $out['mysql_version'] = mysqli_get_server_info($conn);
        // Test query
        $r = mysqli_query($conn, "SHOW TABLES");
        $tables = [];
        while ($row = mysqli_fetch_row($r)) $tables[] = $row[0];
        $out['tables'] = $tables;
        mysqli_close($conn);
        break;
    } else {
        $out['connect_fail_' . $host] = mysqli_connect_error() . ' (errno: ' . mysqli_connect_errno() . ')';
    }
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
