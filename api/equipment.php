<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
        
        $sql = "SELECT 
                    mavt,
                    tenvt,
                    dvt,
                    ghichu
                FROM bhld_dmvattu
                WHERE 1=1";
        
        if (!empty($search)) {
            $sql .= " AND (tenvt LIKE '%$search%' OR mavt LIKE '%$search%')";
        }
        
        $sql .= " ORDER BY tenvt ASC LIMIT 100";
        
        $result = mysqli_query($conn, $sql);
        $equipment = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $equipment[] = $row;
            }
        }
        
        sendSuccess($equipment, 'Lấy danh sách thiết bị thành công');
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['mavt']) || !isset($input['tenvt'])) {
            sendError('Thiếu thông tin bắt buộc: mavt, tenvt', 400);
        }

        $mavt = intval($input['mavt']);
        $tenvt = mysqli_real_escape_string($conn, trim($input['tenvt']));
        $dvt = isset($input['dvt']) ? mysqli_real_escape_string($conn, trim($input['dvt'])) : '';
        $ghichu = isset($input['ghichu']) ? mysqli_real_escape_string($conn, trim($input['ghichu'])) : '';

        if ($mavt <= 0 || $tenvt === '') {
            sendError('Dữ liệu không hợp lệ', 400);
        }

        $checkSql = "SELECT mavt FROM bhld_dmvattu WHERE mavt = $mavt LIMIT 1";
        $checkResult = mysqli_query($conn, $checkSql);
        if ($checkResult && mysqli_num_rows($checkResult) > 0) {
            sendError('Mã vật tư đã tồn tại', 409);
        }

        $dvtSql = $dvt !== '' ? "'$dvt'" : 'NULL';
        $ghichuSql = $ghichu !== '' ? "'$ghichu'" : 'NULL';

        $sql = "INSERT INTO bhld_dmvattu (mavt, tenvt, dvt, ghichu)
                VALUES ($mavt, '$tenvt', $dvtSql, $ghichuSql)";

        if (!mysqli_query($conn, $sql)) {
            sendError('Lỗi thêm vật tư: ' . mysqli_error($conn), 500);
        }

        sendSuccess([
            'mavt' => $mavt,
            'tenvt' => $tenvt,
            'dvt' => $dvt,
            'ghichu' => $ghichu,
        ], 'Thêm vật tư thành công');
    } elseif ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['mavt'])) {
            sendError('Thiếu mavt', 400);
        }

        $mavt = intval($input['mavt']);
        if ($mavt <= 0) {
            sendError('mavt không hợp lệ', 400);
        }

        $sets = [];
        if (isset($input['tenvt'])) {
            $tenvt = mysqli_real_escape_string($conn, trim($input['tenvt']));
            if ($tenvt === '') sendError('tenvt không được rỗng', 400);
            $sets[] = "tenvt = '$tenvt'";
        }
        if (array_key_exists('dvt', $input)) {
            if ($input['dvt'] === null || trim((string)$input['dvt']) === '') {
                $sets[] = 'dvt = NULL';
            } else {
                $dvt = mysqli_real_escape_string($conn, trim((string)$input['dvt']));
                $sets[] = "dvt = '$dvt'";
            }
        }
        if (array_key_exists('ghichu', $input)) {
            if ($input['ghichu'] === null || trim((string)$input['ghichu']) === '') {
                $sets[] = 'ghichu = NULL';
            } else {
                $ghichu = mysqli_real_escape_string($conn, trim((string)$input['ghichu']));
                $sets[] = "ghichu = '$ghichu'";
            }
        }

        if (empty($sets)) {
            sendError('Không có trường nào để cập nhật', 400);
        }

        $sql = "UPDATE bhld_dmvattu SET " . implode(', ', $sets) . " WHERE mavt = $mavt";

        if (!mysqli_query($conn, $sql)) {
            sendError('Lỗi cập nhật vật tư: ' . mysqli_error($conn), 500);
        }

        if (mysqli_affected_rows($conn) < 1) {
            sendError('Không tìm thấy vật tư hoặc dữ liệu không đổi', 404);
        }

        sendSuccess(['mavt' => $mavt], 'Cập nhật vật tư thành công');
    } elseif ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['mavt'])) {
            sendError('Thiếu mavt', 400);
        }

        $mavt = intval($input['mavt']);
        if ($mavt <= 0) {
            sendError('mavt không hợp lệ', 400);
        }

        $checkCtu = mysqli_query($conn, "SELECT COUNT(*) as c FROM bhld_ctctu WHERE mavt = $mavt");
        if ($checkCtu) {
            $r = mysqli_fetch_assoc($checkCtu);
            if (intval($r['c']) > 0) {
                sendError('Không thể xóa vật tư vì đã phát sinh trong chứng từ', 400);
            }
        }

        $checkPolicy = mysqli_query($conn, "SELECT COUNT(*) as c FROM bhld_nhanvien_vattu_dm WHERE mavt = $mavt");
        if ($checkPolicy) {
            $r2 = mysqli_fetch_assoc($checkPolicy);
            if (intval($r2['c']) > 0) {
                sendError('Không thể xóa vật tư vì đang được dùng trong định mức nhân viên', 400);
            }
        }

        $sql = "DELETE FROM bhld_dmvattu WHERE mavt = $mavt";
        if (!mysqli_query($conn, $sql)) {
            sendError('Lỗi xóa vật tư: ' . mysqli_error($conn), 500);
        }

        if (mysqli_affected_rows($conn) < 1) {
            sendError('Không tìm thấy vật tư', 404);
        }

        sendSuccess(['mavt' => $mavt], 'Xóa vật tư thành công');
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
