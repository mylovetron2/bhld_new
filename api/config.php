<?php
// CORS Headers for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database connection
// Thử load từ thư mục cha trước, nếu không có thì dùng file local
if (file_exists(dirname(__DIR__) . '/db.php')) {
    require_once dirname(__DIR__) . '/db.php';
} else {
    require_once __DIR__ . '/db_connection.php';
}

// Response helper functions
function sendResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function sendError($message, $code = 400) {
    http_response_code($code);
    sendResponse(false, null, $message);
}

function sendSuccess($data = null, $message = 'Success') {
    sendResponse(true, $data, $message);
}
?>
