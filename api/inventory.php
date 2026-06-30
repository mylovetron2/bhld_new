<?php
/**
 * API Quản lý Tồn Kho
 *
 * GET    /inventory.php                  – Danh sách tồn kho (join bhld_dmvattu)
 * GET    /inventory.php?mavt=X           – Tồn kho + lịch sử nhập của 1 vật tư
 * POST   /inventory.php  action=receive  – Nhập kho
 * DELETE /inventory.php                  – Xóa bản ghi nhập kho (theo id)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($method === 'GET') {
        $mavt = isset($_GET['mavt']) ? intval($_GET['mavt']) : 0;

        if ($mavt > 0) {
            // Chi tiết tồn kho 1 vật tư + lịch sử nhập
            $sql = "SELECT
                        t.mavt,
                        d.tenvt,
                        d.dvt,
                        COALESCE(t.so_luong_nhap, 0)     AS so_luong_nhap,
                        COALESCE(t.so_luong_cap_phat, 0) AS so_luong_cap_phat,
                        COALESCE(t.so_luong_nhap, 0) - COALESCE(t.so_luong_cap_phat, 0) AS ton,
                        t.ngay_cap_nhat,
                        t.ghi_chu
                    FROM bhld_dmvattu d
                    LEFT JOIN bhld_tonkho t ON d.mavt = t.mavt
                    WHERE d.mavt = $mavt
                    LIMIT 1";
            $res = mysqli_query($conn, $sql);
            if (!$res) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);
            $item = mysqli_fetch_assoc($res);
            if (!$item) sendError('Không tìm thấy vật tư', 404);

            // Lịch sử nhập kho
            $sqlH = "SELECT id, so_luong, ngay_nhap, nguon_nhap, nguoi_nhap, ghi_chu, created_at
                     FROM bhld_nhapkho WHERE mavt = $mavt ORDER BY ngay_nhap DESC, id DESC LIMIT 100";
            $resH = mysqli_query($conn, $sqlH);
            $history = [];
            if ($resH) {
                while ($r = mysqli_fetch_assoc($resH)) $history[] = $r;
            }

            sendSuccess(['tonkho' => $item, 'history' => $history], 'OK');

        } else {
            // Danh sách tất cả tồn kho
            $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

            $sql = "SELECT
                        d.mavt,
                        d.tenvt,
                        d.dvt,
                        COALESCE(t.so_luong_nhap, 0)     AS so_luong_nhap,
                        COALESCE(t.so_luong_cap_phat, 0) AS so_luong_cap_phat,
                        COALESCE(t.so_luong_nhap, 0) - COALESCE(t.so_luong_cap_phat, 0) AS ton,
                        t.ngay_cap_nhat
                    FROM bhld_dmvattu d
                    LEFT JOIN bhld_tonkho t ON d.mavt = t.mavt";

            if (!empty($search)) {
                $sql .= " WHERE d.tenvt LIKE '%$search%' OR d.mavt LIKE '%$search%'";
            }

            $sql .= " ORDER BY d.tenvt ASC LIMIT 500";

            $res = mysqli_query($conn, $sql);
            if (!$res) sendError('Lỗi truy vấn: ' . mysqli_error($conn), 500);

            $list = [];
            while ($r = mysqli_fetch_assoc($res)) $list[] = $r;

            sendSuccess($list, 'Lấy danh sách tồn kho thành công');
        }

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = isset($input['action']) ? $input['action'] : '';

        if ($action === 'receive') {
            // Nhập kho
            if (!isset($input['mavt']) || !isset($input['so_luong']) || !isset($input['ngay_nhap'])) {
                sendError('Thiếu thông tin: mavt, so_luong, ngay_nhap', 400);
            }

            $mavt      = intval($input['mavt']);
            $so_luong  = intval($input['so_luong']);
            $ngay_nhap = mysqli_real_escape_string($conn, $input['ngay_nhap']);
            $nguon_nhap = isset($input['nguon_nhap']) ? mysqli_real_escape_string($conn, trim($input['nguon_nhap'])) : '';
            $nguoi_nhap = isset($input['nguoi_nhap']) ? mysqli_real_escape_string($conn, trim($input['nguoi_nhap'])) : '';
            $ghi_chu    = isset($input['ghi_chu'])    ? mysqli_real_escape_string($conn, trim($input['ghi_chu']))    : '';

            if ($mavt <= 0)    sendError('mavt không hợp lệ', 400);
            if ($so_luong <= 0) sendError('Số lượng phải lớn hơn 0', 400);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ngay_nhap)) sendError('Ngày nhập không hợp lệ', 400);

            // Kiểm tra vật tư tồn tại
            $check = mysqli_query($conn, "SELECT mavt FROM bhld_dmvattu WHERE mavt = $mavt LIMIT 1");
            if (!$check || mysqli_num_rows($check) === 0) sendError('Vật tư không tồn tại', 404);

            // Ghi vào lịch sử nhập
            $nguonSql  = $nguon_nhap !== '' ? "'$nguon_nhap'" : 'NULL';
            $nguoiSql  = $nguoi_nhap !== '' ? "'$nguoi_nhap'" : 'NULL';
            $ghichuSql = $ghi_chu    !== '' ? "'$ghi_chu'"    : 'NULL';

            $sqlIns = "INSERT INTO bhld_nhapkho (mavt, so_luong, ngay_nhap, nguon_nhap, nguoi_nhap, ghi_chu)
                       VALUES ($mavt, $so_luong, '$ngay_nhap', $nguonSql, $nguoiSql, $ghichuSql)";
            if (!mysqli_query($conn, $sqlIns)) {
                sendError('Lỗi ghi lịch sử nhập: ' . mysqli_error($conn), 500);
            }
            $newId = mysqli_insert_id($conn);

            // Cập nhật bảng tồn kho (INSERT ... ON DUPLICATE KEY UPDATE)
            $sqlUpd = "INSERT INTO bhld_tonkho (mavt, so_luong_nhap, so_luong_cap_phat)
                       VALUES ($mavt, $so_luong, 0)
                       ON DUPLICATE KEY UPDATE so_luong_nhap = so_luong_nhap + $so_luong";
            if (!mysqli_query($conn, $sqlUpd)) {
                sendError('Lỗi cập nhật tồn kho: ' . mysqli_error($conn), 500);
            }

            sendSuccess(['id' => $newId, 'mavt' => $mavt, 'so_luong' => $so_luong], 'Nhập kho thành công');

        } else {
            sendError('action không hợp lệ', 400);
        }

    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['id'])) sendError('Thiếu id', 400);
        $id = intval($input['id']);
        if ($id <= 0) sendError('id không hợp lệ', 400);

        // Lấy thông tin trước khi xóa để hoàn lại số lượng
        $resRow = mysqli_query($conn, "SELECT mavt, so_luong FROM bhld_nhapkho WHERE id = $id LIMIT 1");
        if (!$resRow || mysqli_num_rows($resRow) === 0) sendError('Không tìm thấy bản ghi nhập kho', 404);
        $row = mysqli_fetch_assoc($resRow);

        // Xóa lịch sử nhập
        if (!mysqli_query($conn, "DELETE FROM bhld_nhapkho WHERE id = $id")) {
            sendError('Lỗi xóa bản ghi: ' . mysqli_error($conn), 500);
        }

        // Hoàn lại số lượng nhập trong tồn kho
        $mavt     = intval($row['mavt']);
        $so_luong = intval($row['so_luong']);
        mysqli_query($conn, "UPDATE bhld_tonkho
                             SET so_luong_nhap = GREATEST(0, so_luong_nhap - $so_luong)
                             WHERE mavt = $mavt");

        sendSuccess(['id' => $id], 'Xóa bản ghi nhập kho thành công');

    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
