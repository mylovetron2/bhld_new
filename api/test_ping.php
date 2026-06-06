<?php
// Test đơn giản - chỉ kiểm tra PHP chạy được không
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    'status' => 'OK',
    'message' => 'PHP is working',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
