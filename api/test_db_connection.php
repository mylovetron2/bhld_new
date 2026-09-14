<?php
/**
 * Kiểm tra kết nối cơ sở dữ liệu BHLD.
 * Truy cập: /api/test_db_connection.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/db_connection.php';

$result = db_test_connection($conn);

if (!$result['success']) {
    http_response_code(500);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

mysqli_close($conn);
