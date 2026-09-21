<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'stage' => 'php_fatal', 'message' => $error['message']], JSON_UNESCAPED_UNICODE);
    }
});
require_once __DIR__ . '/config.php';

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

function v2FallbackRows() {
    return [
        ['id' => 0, 'nhom' => 'shoe_type', 'ten' => 'Da', 'active' => 1],
        ['id' => 0, 'nhom' => 'shoe_type', 'ten' => 'Thể thao', 'active' => 1],
        ['id' => 0, 'nhom' => 'shoe_type', 'ten' => 'Cao cổ', 'active' => 1],
        ['id' => 0, 'nhom' => 'shoe_type', 'ten' => 'Thấp cổ', 'active' => 1],
        ['id' => 0, 'nhom' => 'helmet_color', 'ten' => 'Trắng', 'active' => 1],
        ['id' => 0, 'nhom' => 'helmet_color', 'ten' => 'Vàng', 'active' => 1],
        ['id' => 0, 'nhom' => 'helmet_color', 'ten' => 'Xanh', 'active' => 1],
        ['id' => 0, 'nhom' => 'helmet_color', 'ten' => 'Đỏ', 'active' => 1],
        ['id' => 0, 'nhom' => 'helmet_color', 'ten' => 'Cam', 'active' => 1],
    ];
}

function v2Response($success, $data, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

set_exception_handler(function ($e) {
    v2Response(false, null, 'equipment_attributes_v2.php: ' . $e->getMessage(), 500);
});

$method = $_SERVER['REQUEST_METHOD'];

// Endpoint v2: GET khong tao bang, khong dung information_schema.
if ($method === 'GET') {
    $group = isset($_GET['group']) ? trim($_GET['group']) : '';
    $allowed = ['shoe_type', 'helmet_color'];
    if ($group !== '' && !in_array($group, $allowed, true)) {
        v2Response(false, null, 'Nhóm danh mục không hợp lệ', 400);
    }

    $where = "WHERE active = 1";
    if ($group !== '') {
        $groupEsc = mysqli_real_escape_string($conn, $group);
        $where .= " AND nhom = '$groupEsc'";
    }

    $result = mysqli_query($conn, "SELECT id, nhom, ten, active
        FROM bhld_danhmuc_thuoctinh $where ORDER BY nhom, ten");
    if ($result) {
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        v2Response(true, $rows, 'Lấy danh mục thành công');
    }

    // Cho phép giao diện vẫn hoạt động nếu bảng chưa được khởi tạo.
    v2Response(true, v2FallbackRows(), 'Đang dùng danh mục mặc định; chưa đọc được bảng CSDL');
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$allowed = ['shoe_type', 'helmet_color'];

if ($method === 'POST') {
    $group = trim((string)($input['nhom'] ?? ''));
    $name = trim((string)($input['ten'] ?? ''));
    if (!in_array($group, $allowed, true) || $name === '') {
        v2Response(false, null, 'Thiếu hoặc sai nhóm/tên danh mục', 400);
    }
    $groupEsc = mysqli_real_escape_string($conn, $group);
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $existing = mysqli_query($conn, "SELECT id FROM bhld_danhmuc_thuoctinh
        WHERE nhom='$groupEsc' AND ten='$nameEsc' LIMIT 1");
    if (!$existing) v2Response(false, null, 'Lỗi kiểm tra danh mục: ' . mysqli_error($conn), 500);
    if (mysqli_num_rows($existing) > 0) {
        $row = mysqli_fetch_assoc($existing);
        $id = intval($row['id']);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=1 WHERE id=$id")) {
            v2Response(false, null, 'Lỗi kích hoạt danh mục: ' . mysqli_error($conn), 500);
        }
    } else {
        if (!mysqli_query($conn, "INSERT INTO bhld_danhmuc_thuoctinh (nhom, ten, active)
            VALUES ('$groupEsc', '$nameEsc', 1)")) {
            v2Response(false, null, 'Lỗi thêm danh mục: ' . mysqli_error($conn), 500);
        }
    }
    v2Response(true, ['nhom' => $group, 'ten' => $name], 'Đã thêm danh mục');
}

if ($method === 'PUT') {
    $id = intval($input['id'] ?? 0);
    $name = trim((string)($input['ten'] ?? ''));
    if ($id <= 0 || $name === '') v2Response(false, null, 'Thiếu id/tên danh mục', 400);
    $nameEsc = mysqli_real_escape_string($conn, $name);
    if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET ten='$nameEsc', active=1 WHERE id=$id")) {
        v2Response(false, null, 'Lỗi cập nhật danh mục: ' . mysqli_error($conn), 500);
    }
    v2Response(true, ['id' => $id, 'ten' => $name], 'Đã cập nhật danh mục');
}

if ($method === 'DELETE') {
    $id = intval($input['id'] ?? 0);
    if ($id <= 0) v2Response(false, null, 'Thiếu id danh mục', 400);
    if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=0 WHERE id=$id")) {
        v2Response(false, null, 'Lỗi ẩn danh mục: ' . mysqli_error($conn), 500);
    }
    v2Response(true, ['id' => $id], 'Đã ẩn danh mục');
}

v2Response(false, null, 'Method không được hỗ trợ', 405);
