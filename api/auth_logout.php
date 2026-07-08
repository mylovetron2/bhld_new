<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method không được hỗ trợ', 405);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        isset($params['domain']) ? $params['domain'] : '',
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

$isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('bhld_auth', '', time() - 42000, '/', '', $isHttps, true);
setcookie('bhld_user', '', time() - 42000, '/', '', $isHttps, true);

sendSuccess(null, 'Đăng xuất thành công');
?>
