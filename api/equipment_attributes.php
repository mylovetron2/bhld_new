<?php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];

function attributeTableExists($conn) {
    $result = mysqli_query($conn, "SELECT 1 FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'bhld_danhmuc_thuoctinh' LIMIT 1");
    return $result && mysqli_num_rows($result) > 0;
}

function validGroup($group) {
    return in_array($group, ['shoe_type', 'helmet_color'], true);
}

function defaultAttributes() {
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

try {
    // GET chỉ đọc trực tiếp; không phụ thuộc information_schema hay quyền CREATE TABLE.
    if ($method === 'GET') {
        $group = isset($_GET['group']) ? trim($_GET['group']) : '';
        if ($group !== '' && !validGroup($group)) sendError('Nhóm danh mục không hợp lệ', 400);
        $where = "WHERE active = 1";
        if ($group !== '') {
            $groupEsc = mysqli_real_escape_string($conn, $group);
            $where .= " AND nhom = '$groupEsc'";
        }
        $result = mysqli_query($conn, "SELECT id, nhom, ten, active FROM bhld_danhmuc_thuoctinh $where ORDER BY nhom ASC, ten ASC");
        if (!$result) {
            sendSuccess(defaultAttributes(), 'Không đọc được bảng danh mục; đang dùng danh mục mặc định');
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
        sendSuccess($rows, 'Lấy danh mục thành công');
    }

    // Bảng được khởi tạo bằng api/equipment_attributes_init.sql;
    // endpoint không tự CREATE TABLE trong mỗi request vì hosting có thể cấm DDL.
    if (!attributeTableExists($conn)) {
        sendError('Chưa khởi tạo bảng bhld_danhmuc_thuoctinh. Hãy chạy api/equipment_attributes_init.sql trong phpMyAdmin.', 503);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    if ($method === 'POST') {
        $group = trim((string)($input['nhom'] ?? ''));
        $name = trim((string)($input['ten'] ?? ''));
        if (!validGroup($group) || $name === '') sendError('Thiếu hoặc sai nhóm/tên danh mục', 400);
        $groupEsc = mysqli_real_escape_string($conn, $group);
        $nameEsc = mysqli_real_escape_string($conn, $name);
        $sql = "INSERT INTO bhld_danhmuc_thuoctinh (nhom, ten, active) VALUES ('$groupEsc', '$nameEsc', 1)
            ON DUPLICATE KEY UPDATE active = 1, ten = VALUES(ten)";
        if (!mysqli_query($conn, $sql)) sendError('Lỗi thêm danh mục: ' . mysqli_error($conn), 500);
        sendSuccess(['nhom' => $group, 'ten' => $name], 'Đã thêm danh mục');
    }
    if ($method === 'PUT') {
        $id = intval($input['id'] ?? 0);
        $name = trim((string)($input['ten'] ?? ''));
        if ($id <= 0 || $name === '') sendError('Thiếu id/tên danh mục', 400);
        $nameEsc = mysqli_real_escape_string($conn, $name);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET ten='$nameEsc', active=1 WHERE id=$id")) sendError('Lỗi cập nhật danh mục: ' . mysqli_error($conn), 500);
        sendSuccess(['id' => $id, 'ten' => $name], 'Đã cập nhật danh mục');
    }
    if ($method === 'DELETE') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) sendError('Thiếu id danh mục', 400);
        if (!mysqli_query($conn, "UPDATE bhld_danhmuc_thuoctinh SET active=0 WHERE id=$id")) sendError('Lỗi xóa danh mục: ' . mysqli_error($conn), 500);
        sendSuccess(['id' => $id], 'Đã ẩn danh mục');
    }
    sendError('Method không được hỗ trợ', 405);
} catch (Throwable $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}
