<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tableName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

function requirePolicyTables($conn) {
    if (!tableExists($conn, 'bhld_nhanvien_vattu_dm')) {
        sendError('Thiếu bảng bhld_nhanvien_vattu_dm. Hãy chạy file migration_employee_profile_policy.sql', 500);
    }
}

try {
    requirePolicyTables($conn);

    if ($method === 'GET') {
        $manv = isset($_GET['manv']) ? mysqli_real_escape_string($conn, trim($_GET['manv'])) : '';
        $includeInactive = isset($_GET['include_inactive']) && $_GET['include_inactive'] === '1';

        $where = "WHERE 1=1";
        if ($manv !== '') {
            $where .= " AND p.manv = '$manv'";
        }
        if (!$includeInactive) {
            $where .= " AND p.active = 1";
        }

        $sql = "SELECT
                    p.id,
                    p.manv,
                    p.mavt,
                    vt.tenvt,
                    vt.dvt,
                    p.dmuc_thang,
                    p.so_luong,
                    p.active,
                    p.source_madm,
                    p.ghi_chu,
                    p.updated_at
                FROM bhld_nhanvien_vattu_dm p
                LEFT JOIN bhld_dmvattu vt ON p.mavt = vt.mavt
                $where
                ORDER BY p.manv ASC, p.active DESC, p.mavt ASC";

        $result = mysqli_query($conn, $sql);
        if (!$result) {
            sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
        }

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        sendSuccess($rows, 'Lấy định mức vật tư theo nhân viên thành công');
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['manv']) || !isset($data['mavt']) || !isset($data['dmuc_thang'])) {
            sendError('Thiếu thông tin bắt buộc: manv, mavt, dmuc_thang', 400);
        }

        $manv = mysqli_real_escape_string($conn, trim($data['manv']));
        $mavt = intval($data['mavt']);
        $dmucThang = intval($data['dmuc_thang']);
        $soLuong = isset($data['so_luong']) ? max(1, intval($data['so_luong'])) : 1;
        $active = isset($data['active']) ? (intval($data['active']) ? 1 : 0) : 1;
        $sourceMadm = isset($data['source_madm']) && $data['source_madm'] !== ''
            ? "'" . mysqli_real_escape_string($conn, trim($data['source_madm'])) . "'"
            : 'NULL';
        $ghiChu = isset($data['ghi_chu']) && $data['ghi_chu'] !== ''
            ? "'" . mysqli_real_escape_string($conn, trim($data['ghi_chu'])) . "'"
            : 'NULL';

        if ($mavt <= 0 || $dmucThang < 0) {
            sendError('mavt hoặc dmuc_thang không hợp lệ', 400);
        }

        $sql = "INSERT INTO bhld_nhanvien_vattu_dm (manv, mavt, dmuc_thang, so_luong, active, source_madm, ghi_chu)
                VALUES ('$manv', $mavt, $dmucThang, $soLuong, $active, $sourceMadm, $ghiChu)
                ON DUPLICATE KEY UPDATE
                    dmuc_thang = VALUES(dmuc_thang),
                    so_luong = VALUES(so_luong),
                    active = VALUES(active),
                    source_madm = VALUES(source_madm),
                    ghi_chu = VALUES(ghi_chu)";

        if (!mysqli_query($conn, $sql)) {
            sendError('Lỗi lưu định mức: ' . mysqli_error($conn), 500);
        }

        sendSuccess(['manv' => $manv, 'mavt' => $mavt], 'Lưu định mức vật tư thành công');
    }
    elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['manv']) || !isset($data['items']) || !is_array($data['items'])) {
            sendError('Thiếu thông tin bắt buộc: manv, items[]', 400);
        }

        $manv = mysqli_real_escape_string($conn, trim($data['manv']));
        $sync = isset($data['sync']) ? (intval($data['sync']) ? 1 : 0) : 0;

        mysqli_begin_transaction($conn);

        if ($sync === 1) {
            $deactivateSql = "UPDATE bhld_nhanvien_vattu_dm SET active = 0 WHERE manv = '$manv'";
            if (!mysqli_query($conn, $deactivateSql)) {
                mysqli_rollback($conn);
                sendError('Lỗi đồng bộ định mức: ' . mysqli_error($conn), 500);
            }
        }

        foreach ($data['items'] as $item) {
            if (!isset($item['mavt']) || !isset($item['dmuc_thang'])) {
                continue;
            }

            $mavt = intval($item['mavt']);
            $dmucThang = intval($item['dmuc_thang']);
            $soLuong = isset($item['so_luong']) ? max(1, intval($item['so_luong'])) : 1;
            $active = isset($item['active']) ? (intval($item['active']) ? 1 : 0) : 1;
            $sourceMadm = isset($item['source_madm']) && $item['source_madm'] !== ''
                ? "'" . mysqli_real_escape_string($conn, trim($item['source_madm'])) . "'"
                : 'NULL';
            $ghiChu = isset($item['ghi_chu']) && $item['ghi_chu'] !== ''
                ? "'" . mysqli_real_escape_string($conn, trim($item['ghi_chu'])) . "'"
                : 'NULL';

            if ($mavt <= 0 || $dmucThang < 0) {
                continue;
            }

            $sql = "INSERT INTO bhld_nhanvien_vattu_dm (manv, mavt, dmuc_thang, so_luong, active, source_madm, ghi_chu)
                    VALUES ('$manv', $mavt, $dmucThang, $soLuong, $active, $sourceMadm, $ghiChu)
                    ON DUPLICATE KEY UPDATE
                        dmuc_thang = VALUES(dmuc_thang),
                        so_luong = VALUES(so_luong),
                        active = VALUES(active),
                        source_madm = VALUES(source_madm),
                        ghi_chu = VALUES(ghi_chu)";

            if (!mysqli_query($conn, $sql)) {
                mysqli_rollback($conn);
                sendError('Lỗi cập nhật danh sách định mức: ' . mysqli_error($conn), 500);
            }
        }

        mysqli_commit($conn);
        sendSuccess(['manv' => $manv, 'count' => count($data['items'])], 'Cập nhật danh sách định mức thành công');
    }
    elseif ($method === 'DELETE') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['manv']) || !isset($data['mavt'])) {
            sendError('Thiếu thông tin bắt buộc: manv, mavt', 400);
        }

        $manv = mysqli_real_escape_string($conn, trim($data['manv']));
        $mavt = intval($data['mavt']);

        $sql = "UPDATE bhld_nhanvien_vattu_dm SET active = 0 WHERE manv = '$manv' AND mavt = $mavt";
        if (!mysqli_query($conn, $sql)) {
            sendError('Lỗi xóa định mức: ' . mysqli_error($conn), 500);
        }

        sendSuccess(['manv' => $manv, 'mavt' => $mavt], 'Đã vô hiệu hóa định mức vật tư');
    }
    else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
