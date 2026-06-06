<?php
/**
 * Allocate API - MySQL 5.6 Compatible (No JSON functions)
 */

// Try to use existing config.php if available
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    // config.php should provide $conn variable
} else {
    // Fallback: direct connection (for local testing only)
    $servername = "localhost";
    $username = "root";
    $password = "";
    $database = "bhld_database";
    
    $conn = mysqli_connect($servername, $username, $password, $database);
    
    if (!$conn) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => 'Lỗi kết nối database: ' . mysqli_connect_error()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    mysqli_set_charset($conn, 'utf8');
}

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Check if $conn is available
if (!isset($conn) || !$conn) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database connection not available'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['ngnhan'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Thiếu thông tin bắt buộc (mact, mavt, ngnhan)'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        
        // Get dmtg from current record
        $sql_get = "SELECT dmtg, sl FROM bhld_ctctu WHERE mact = '$mact' AND mavt = $mavt";
        $result = mysqli_query($conn, $sql_get);
        
        if (!$result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi query: ' . mysqli_error($conn)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (mysqli_num_rows($result) === 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy thiết bị trong chứng từ'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $row = mysqli_fetch_assoc($result);
        $dmtg = intval($row['dmtg']);
        $current_sl = intval($row['sl']);
        
        // Check if already allocated
        if ($current_sl == 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Thiết bị đã được cấp phát trước đó'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Calculate return date
        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Update record
        $sql_update = "UPDATE bhld_ctctu 
                      SET sl = 1, 
                          ngnhan = '$ngnhan',
                          ngnhantt = '$ngnhantt'
                      WHERE mact = '$mact' AND mavt = $mavt AND sl = 0";
        
        $update_result = mysqli_query($conn, $sql_update);
        
        if (!$update_result) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi cập nhật: ' . mysqli_error($conn)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $affected = mysqli_affected_rows($conn);
        
        if ($affected === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Thiết bị đã được cấp phát hoặc không tồn tại'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Success
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Cấp phát thiết bị thành công',
            'data' => [
                'mact' => $mact,
                'mavt' => $mavt,
                'ngnhan' => $ngnhan,
                'ngnhantt' => $ngnhantt
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method không được hỗ trợ'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
