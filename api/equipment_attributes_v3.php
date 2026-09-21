<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

function v3_json($ok, $data, $message, $status = 200) {
    http_response_code($status);
    echo json_encode(['success' => $ok, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $allowed = ['shoe_type', 'helmet_color'];

    if ($method === 'GET') {
        $group = isset($_GET['group']) ? trim($_GET['group']) : '';
        if ($group !== '' && !in_array($group, $allowed, true)) {
            v3_json(false, null, 'Nhóm danh mục không hợp lệ', 400);
        }
        $where = "active = 1";
        if ($group !== '') {
            $group = mysqli_real_escape_string($conn, $group);
            $where .= " AND nhom = '$group'";
        }
        $result = mysqli_query($conn, "SELECT id, nhom, ten, active
            FROM bhld_danhmuc_thuoctinh WHERE $where ORDER BY nhom ASC, ten ASC");
        if (!$result) {
            v3_json(true, [], 'Không đọc được danh mục: ' . mysqli_error($conn));
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        v3_json(true, $rows, 'Lấy danh mục thành công');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    if ($method === 'POST') {
        $group = trim((string)($input['nhom'] ?? ''));
        $name = trim((string)($input['ten'] ?? ''));
        if (!in_array($group, $allowed, true) || $name === '') {
            v3_json(false, null, 'Thiếu hoặc sai nhóm/tên danh mục', 400);
        }
        $group = mysqli_real_escape_string($conn, $group);
        $name = mysqli_real_escape_string($conn, $name);
        $found = mysqli_query($conn, "SELECT id FROM bhld_danhmuc_thuoctinh WHERE nhom='$group' AND ten='$name' LIMIT 1");
        if (!$found) v3_json(false, null, 'Lỗi kiểm tra: ' . mysqli_error($conn), 500);
        if (mysqli_num_rows($found) > 0) {
            $id = intval(mysqli_fetch_assoc($found)['id']);
            if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=1 WHERE id=$id")) {
                v3_json(false, null, 'Lỗi kích hoạt: ' . mysqli_error($conn), 500);
            }
        } else if (!mysqli_query($conn, "INSERT INTO bhld_danhmuc_thuoctinh (nhom, ten, active) VALUES ('$group', '$name', 1)")) {
            v3_json(false, null, 'Lỗi thêm: ' . mysqli_error($conn), 500);
        }
        v3_json(true, null, 'Đã thêm danh mục');
    }

    if ($method === 'PUT') {
        $id = intval($input['id'] ?? 0);
        $name = trim((string)($input['ten'] ?? ''));
        if ($id <= 0 || $name === '') v3_json(false, null, 'Thiếu id/tên danh mục', 400);
        $name = mysqli_real_escape_string($conn, $name);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET ten='$name', active=1 WHERE id=$id")) {
            v3_json(false, null, 'Lỗi cập nhật: ' . mysqli_error($conn), 500);
        }
        v3_json(true, null, 'Đã cập nhật danh mục');
    }

    if ($method === 'DELETE') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) v3_json(false, null, 'Thiếu id danh mục', 400);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=0 WHERE id=$id")) {
            v3_json(false, null, 'Lỗi ẩn danh mục: ' . mysqli_error($conn), 500);
        }
        v3_json(true, null, 'Đã ẩn danh mục');
    }

    v3_json(false, null, 'Method không được hỗ trợ', 405);
} catch (Exception $e) {
    v3_json(false, null, 'Lỗi PHP: ' . $e->getMessage(), 500);
}
