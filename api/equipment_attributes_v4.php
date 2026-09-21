<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=UTF-8');

function v4_response($ok, $data, $message, $status) {
    http_response_code($status);
    echo json_encode(array('success' => $ok, 'message' => $message, 'data' => $data), JSON_UNESCAPED_UNICODE);
    exit;
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
$allowed = array('shoe_type', 'helmet_color');

if ($method === 'GET') {
    $group = isset($_GET['group']) ? trim($_GET['group']) : '';
    if ($group !== '' && !in_array($group, $allowed, true)) {
        v4_response(false, null, 'Nhóm danh mục không hợp lệ', 400);
    }
    $where = 'active = 1';
    if ($group !== '') {
        $group = mysqli_real_escape_string($conn, $group);
        $where .= " AND nhom = '$group'";
    }
    $result = mysqli_query($conn, "SELECT id, nhom, ten, active FROM bhld_danhmuc_thuoctinh WHERE $where ORDER BY nhom, ten");
    if (!$result) {
        v4_response(true, array(), 'Không đọc được danh mục: ' . mysqli_error($conn), 200);
    }
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    v4_response(true, $rows, 'Lấy danh mục thành công', 200);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = array();

if ($method === 'POST') {
    $group = isset($input['nhom']) ? trim((string)$input['nhom']) : '';
    $name = isset($input['ten']) ? trim((string)$input['ten']) : '';
    if (!in_array($group, $allowed, true) || $name === '') {
        v4_response(false, null, 'Thiếu hoặc sai nhóm/tên danh mục', 400);
    }
    $group = mysqli_real_escape_string($conn, $group);
    $name = mysqli_real_escape_string($conn, $name);
    $found = mysqli_query($conn, "SELECT id FROM bhld_danhmuc_thuoctinh WHERE nhom='$group' AND ten='$name' LIMIT 1");
    if (!$found) v4_response(false, null, 'Lỗi kiểm tra: ' . mysqli_error($conn), 500);
    if (mysqli_num_rows($found) > 0) {
        $id = intval(mysqli_fetch_assoc($found)['id']);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=1 WHERE id=$id")) {
            v4_response(false, null, 'Lỗi kích hoạt: ' . mysqli_error($conn), 500);
        }
    } else if (!mysqli_query($conn, "INSERT INTO bhld_danhmuc_thuoctinh (nhom, ten, active) VALUES ('$group', '$name', 1)")) {
        v4_response(false, null, 'Lỗi thêm: ' . mysqli_error($conn), 500);
    }
    v4_response(true, null, 'Đã thêm danh mục', 200);
}

if ($method === 'PUT') {
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $name = isset($input['ten']) ? trim((string)$input['ten']) : '';
    if ($id <= 0 || $name === '') v4_response(false, null, 'Thiếu id/tên danh mục', 400);
    $name = mysqli_real_escape_string($conn, $name);
    if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET ten='$name', active=1 WHERE id=$id")) {
        v4_response(false, null, 'Lỗi cập nhật: ' . mysqli_error($conn), 500);
    }
    v4_response(true, null, 'Đã cập nhật danh mục', 200);
}

if ($method === 'DELETE') {
    $id = isset($input['id']) ? intval($input['id']) : 0;
    if ($id <= 0) v4_response(false, null, 'Thiếu id danh mục', 400);
    if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=0 WHERE id=$id")) {
        v4_response(false, null, 'Lỗi ẩn danh mục: ' . mysqli_error($conn), 500);
    }
    v4_response(true, null, 'Đã ẩn danh mục', 200);
}

v4_response(false, null, 'Method không được hỗ trợ', 405);
?>
