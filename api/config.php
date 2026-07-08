<?php
// Tắt hiển thị lỗi PHP để không làm hỏng JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Bật session dùng cho đăng nhập
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // Dùng cú pháp legacy để tương thích host PHP cũ.
    session_set_cookie_params(0, '/', '', $isHttps, true);
    session_start();
}

// Fallback: khôi phục trạng thái đăng nhập từ cookie khi session không được giữ ổn định.
if ((empty($_SESSION['bhld_auth']) || $_SESSION['bhld_auth'] !== true)
    && isset($_COOKIE['bhld_auth'])
    && $_COOKIE['bhld_auth'] === '1') {
    $_SESSION['bhld_auth'] = true;
    $_SESSION['bhld_user'] = isset($_COOKIE['bhld_user']) ? $_COOKIE['bhld_user'] : 'admin';
}

// Fallback 2: xác thực bằng API key nội bộ khi session/cookie không ổn định trên host.
$bhldApiKey = 'BHLD_INTERNAL_2026';
$requestApiKey = isset($_SERVER['HTTP_X_BHLD_KEY']) ? trim($_SERVER['HTTP_X_BHLD_KEY']) : '';
if ($requestApiKey !== '' && hash_equals($bhldApiKey, $requestApiKey)) {
    $_SESSION['bhld_auth'] = true;
    if (empty($_SESSION['bhld_user'])) {
        $_SESSION['bhld_user'] = 'admin';
    }
}

// CORS: cần origin cụ thể khi dùng cookie session
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigins = [
    'https://diavatly.cloud',
    'https://diavatly.com',
    'https://www.diavatly.com',
    'http://localhost:5500',
    'http://127.0.0.1:5500'
];

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-BHLD-KEY');
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

// Middleware kiểm tra đăng nhập
$publicEndpoints = ['auth_login.php', 'auth_logout.php', 'auth_me.php'];
$currentEndpoint = basename(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '');

if (!in_array($currentEndpoint, $publicEndpoints, true)) {
    if (empty($_SESSION['bhld_auth']) || $_SESSION['bhld_auth'] !== true) {
        sendError('Chưa đăng nhập', 401);
    }
}
?>
