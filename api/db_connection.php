<?php
/**
 * Database Connection for BHLD System
 * 
 * Cấu hình kết nối MySQL cho API
 * Upload file này lên server diavatly.com/BHLD/api/
 */

// ===== CẤU HÌNH DATABASE =====
// Thay đổi các thông số sau cho phù hợp với server
$db_config = [
    'host'     => 'diavatly.com',
    'username' => 'diavatly_ltd',
    'password' => '12345678',
    'database' => 'diavatly_ltd',
    'port'     => 3306,
    'charset'  => 'utf8mb4',
];

// Tắt exception mode để tự xử lý lỗi
mysqli_report(MYSQLI_REPORT_OFF);

// ===== KẾT NỐI MYSQL =====
$conn = mysqli_connect(
    $db_config['host'],
    $db_config['username'],
    $db_config['password'],
    $db_config['database'],
    $db_config['port']
);

// ===== KIỂM TRA KẾT NỐI =====
if (!$conn) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Lỗi kết nối MySQL: ' . mysqli_connect_error(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}

// ===== SET CHARSET UTF-8 =====
mysqli_set_charset($conn, $db_config['charset']);

// ===== TẮT HIỂN THỊ LỖI (CHO PRODUCTION) =====
// Uncomment dòng dưới khi deploy lên production
// error_reporting(0);
// ini_set('display_errors', 0);

// ===== CÁC HÀM HỖ TRỢ =====

/**
 * Thực thi SELECT query và trả về mảng kết quả
 */
function db_select($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception('Lỗi SQL: ' . mysqli_error($conn));
    }
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

/**
 * Thực thi INSERT/UPDATE/DELETE query
 */
function db_execute($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception('Lỗi SQL: ' . mysqli_error($conn));
    }
    return $result;
}

/**
 * Escape string để tránh SQL injection
 */
function db_escape($conn, $string) {
    return mysqli_real_escape_string($conn, $string);
}

/**
 * Lấy ID của record vừa insert
 */
function db_insert_id($conn) {
    return mysqli_insert_id($conn);
}

/**
 * Test kết nối database
 */
function db_test_connection($conn) {
    try {
        $result = mysqli_query($conn, "SELECT 1 as test");
        if ($result && mysqli_num_rows($result) > 0) {
            return [
                'success' => true,
                'message' => 'Kết nối MySQL thành công!',
                'server_info' => mysqli_get_server_info($conn),
                'client_info' => mysqli_get_client_info(),
            ];
        }
        return [
            'success' => false,
            'message' => 'Không thể query database',
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Lỗi: ' . $e->getMessage(),
        ];
    }
}

// ===== UNCOMMENT DÒNG DƯỚI ĐỂ TEST KẾT NỐI =====
// header('Content-Type: application/json; charset=UTF-8');
// echo json_encode(db_test_connection($conn), JSON_UNESCAPED_UNICODE);
// exit;
?>
