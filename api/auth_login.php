<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method không được hỗ trợ', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim(isset($input['username']) ? $input['username'] : '');
$password = trim(isset($input['password']) ? $input['password'] : '');

if ($username === 'admin' && $password === '1234') {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $_SESSION['bhld_auth'] = true;
    $_SESSION['bhld_user'] = 'admin';

    setcookie('bhld_auth', '1', time() + 86400, '/', '', $isHttps, true);
    setcookie('bhld_user', 'admin', time() + 86400, '/', '', $isHttps, true);

    sendSuccess(['username' => 'admin'], 'Đăng nhập thành công');
}

sendError('Sai tài khoản hoặc mật khẩu', 401);
?>
