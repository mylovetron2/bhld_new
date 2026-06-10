<?php
require_once 'config.php';

if (!empty($_SESSION['bhld_auth']) && $_SESSION['bhld_auth'] === true) {
    sendSuccess(['username' => $_SESSION['bhld_user'] ?? 'admin'], 'Đã đăng nhập');
}

sendError('Chưa đăng nhập', 401);
?>
