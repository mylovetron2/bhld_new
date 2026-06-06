<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (!isset($_GET['mact'])) {
            sendError('Thiếu mã chứng từ (mact)');
        }
        
        $mact = mysqli_real_escape_string($conn, $_GET['mact']);
        
        $sql = "SELECT 
                    ct.mact,
                    ct.mavt,
                    ct.dmtg,
                    ct.sl,
                    ct.ngnhan,
                    ct.ngnhantt,
                    vt.tenvt,
                    vt.dvt
                FROM bhld_ctctu ct
                LEFT JOIN bhld_dmvattu vt ON ct.mavt = vt.mavt
                WHERE ct.mact = '$mact'
                ORDER BY ct.mavt ASC";
        
        $result = mysqli_query($conn, $sql);
        $details = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $details[] = $row;
            }
        }
        
        sendSuccess($details, 'Lấy chi tiết chứng từ thành công');
    }
    else if ($method === 'POST') {
        // Create new detail
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['sl']) || 
            !isset($data['ngnhan']) || !isset($data['ngnhantt']) || !isset($data['dmtg'])) {
            sendError('Thiếu thông tin bắt buộc');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $sl = intval($data['sl']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        $ngnhantt = mysqli_real_escape_string($conn, $data['ngnhantt']);
        $dmtg = intval($data['dmtg']);
        
        // Check if already exists
        $check = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if (mysqli_num_rows($check) > 0) {
            sendError('Chi tiết chứng từ đã tồn tại', 409);
        }
        
        $sql = "INSERT INTO bhld_ctctu (mact, mavt, sl, ngnhan, ngnhantt, dmtg) 
                VALUES ('$mact', $mavt, $sl, '$ngnhan', '$ngnhantt', $dmtg)";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact, 'mavt' => $mavt], 'Tạo chi tiết thành công');
        } else {
            sendError('Lỗi tạo chi tiết: ' . mysqli_error($conn));
        }
    }
    else if ($method === 'PUT') {
        // Update detail
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt'])) {
            sendError('Thiếu mã chứng từ hoặc mã vật tư');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        
        // Check if exists
        $check = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if (mysqli_num_rows($check) === 0) {
            sendError('Không tìm thấy chi tiết chứng từ', 404);
        }
        
        $updates = [];
        if (isset($data['sl'])) {
            $sl = intval($data['sl']);
            $updates[] = "sl = $sl";
        }
        if (isset($data['ngnhan'])) {
            $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
            $updates[] = "ngnhan = '$ngnhan'";
        }
        if (isset($data['ngnhantt'])) {
            $ngnhantt = mysqli_real_escape_string($conn, $data['ngnhantt']);
            $updates[] = "ngnhantt = '$ngnhantt'";
        }
        if (isset($data['dmtg'])) {
            $dmtg = intval($data['dmtg']);
            $updates[] = "dmtg = $dmtg";
        }
        
        if (empty($updates)) {
            sendError('Không có thông tin cần cập nhật');
        }
        
        $sql = "UPDATE bhld_ctctu SET " . implode(', ', $updates) . " WHERE mact='$mact' AND mavt=$mavt";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact, 'mavt' => $mavt], 'Cập nhật chi tiết thành công');
        } else {
            sendError('Lỗi cập nhật chi tiết: ' . mysqli_error($conn));
        }
    }
    else if ($method === 'DELETE') {
        // Delete detail
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt'])) {
            sendError('Thiếu mã chứng từ hoặc mã vật tư');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        
        // Check if exists
        $check = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if (mysqli_num_rows($check) === 0) {
            sendError('Không tìm thấy chi tiết chứng từ', 404);
        }
        
        $sql = "DELETE FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt";
        
        if (mysqli_query($conn, $sql)) {
            sendSuccess(['mact' => $mact, 'mavt' => $mavt], 'Xóa chi tiết thành công');
        } else {
            sendError('Lỗi xóa chi tiết: ' . mysqli_error($conn));
        }
    }
    else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi server: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
