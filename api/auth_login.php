<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method không được hỗ trợ', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if ($username === 'admin' && $password === '1234') {
    $_SESSION['bhld_auth'] = true;
    $_SESSION['bhld_user'] = 'admin';
    sendSuccess(['username' => 'admin'], 'Đăng nhập thành công');
}

sendError('Sai tài khoản hoặc mật khẩu', 401);
?>
