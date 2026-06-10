<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method không được hỗ trợ', 405);
}

if (!isset($_POST['confirm']) || trim((string)$_POST['confirm']) !== 'RESTORE') {
    sendError('Xác nhận không hợp lệ. Vui lòng nhập RESTORE', 400);
}

if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
    sendError('Thiếu file SQL hợp lệ', 400);
}

$file = $_FILES['sql_file'];
$originalName = $file['name'] ?? 'backup.sql';
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    sendError('Chỉ chấp nhận file .sql', 400);
}

$maxSize = 30 * 1024 * 1024;
if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > $maxSize) {
    sendError('File SQL không hợp lệ hoặc vượt quá 30MB', 400);
}

$sqlContent = file_get_contents($file['tmp_name']);
if ($sqlContent === false || trim($sqlContent) === '') {
    sendError('Không đọc được nội dung file SQL', 400);
}

function normalizeSqlContent($sql) {
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = str_replace("\r\n", "\n", $sql);

    // Remove mysql-client delimiter directives.
    $sql = preg_replace('/^\s*DELIMITER\s+.+$/mi', '', $sql);

    // Convert custom trigger delimiter endings to semicolon.
    $sql = preg_replace('/\$\$\s*$/m', ';', $sql);

    return trim($sql);
}

$normalized = normalizeSqlContent($sqlContent);
if ($normalized === '') {
    sendError('Nội dung SQL rỗng sau khi chuẩn hóa', 400);
}

try {
    @set_time_limit(0);

    mysqli_begin_transaction($conn);

    if (!mysqli_multi_query($conn, $normalized)) {
        mysqli_rollback($conn);
        sendError('Lỗi chạy SQL: ' . mysqli_error($conn), 500);
    }

    do {
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
        if (mysqli_errno($conn)) {
            $err = mysqli_error($conn);
            mysqli_rollback($conn);
            sendError('Lỗi SQL trong quá trình restore: ' . $err, 500);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));

    mysqli_commit($conn);

    sendSuccess([
        'file' => $originalName,
        'size' => (int)$file['size'],
        'restored_at' => date('Y-m-d H:i:s'),
    ], 'Khôi phục database thành công');
} catch (Exception $e) {
    mysqli_rollback($conn);
    sendError('Lỗi restore: ' . $e->getMessage(), 500);
}
