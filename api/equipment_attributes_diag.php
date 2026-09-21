<?php
// Endpoint chan doan tam thoi. Xoa file nay sau khi debug xong.
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'stage' => 'php_fatal',
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

try {
    $stage = 'before_config';
    require_once __DIR__ . '/config.php';
    $stage = 'after_config';

    if (!isset($conn) || !$conn) {
        throw new Exception('Không có biến kết nối $conn');
    }

    $result = mysqli_query($conn, 'SELECT DATABASE() AS db_name, VERSION() AS db_version');
    if (!$result) throw new Exception('SELECT DATABASE thất bại: ' . mysqli_error($conn));
    $connection = mysqli_fetch_assoc($result);

    $stage = 'table_check';
    $result = mysqli_query($conn, "SHOW TABLES LIKE 'bhld_danhmuc_thuoctinh'");
    if (!$result) throw new Exception('SHOW TABLES thất bại: ' . mysqli_error($conn));
    $tableExists = mysqli_num_rows($result) > 0;

    $sample = [];
    if ($tableExists) {
        $stage = 'data_query';
        $result = mysqli_query($conn, "SELECT id, nhom, ten, active
            FROM bhld_danhmuc_thuoctinh WHERE active = 1 ORDER BY nhom, ten LIMIT 20");
        if (!$result) throw new Exception('SELECT danh mục thất bại: ' . mysqli_error($conn));
        while ($row = mysqli_fetch_assoc($result)) $sample[] = $row;
    }

    echo json_encode([
        'success' => true,
        'stage' => $stage,
        'database' => $connection,
        'table_exists' => $tableExists,
        'sample_count' => count($sample),
        'sample' => $sample,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'stage' => isset($stage) ? $stage : 'unknown',
        'message' => $e->getMessage(),
        'type' => get_class($e),
    ], JSON_UNESCAPED_UNICODE);
}
