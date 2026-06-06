<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['ngnhan'])) {
            sendError('Thiếu thông tin bắt buộc (mact, mavt, ngnhan)');
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        
        // Get dmtg from current record
        $sql_get = "SELECT dmtg, sl FROM bhld_ctctu WHERE mact = '$mact' AND mavt = $mavt";
        $result = mysqli_query($conn, $sql_get);
        
        if (!$result) {
            sendError('Lỗi query: ' . mysqli_error($conn), 500);
        }
        
        if (mysqli_num_rows($result) === 0) {
            sendError('Không tìm thấy thiết bị trong chứng từ', 404);
        }
        
        $row = mysqli_fetch_assoc($result);
        $dmtg = intval($row['dmtg']);
        $current_sl = intval($row['sl']);
        
        // Check if already allocated
        if ($current_sl == 1) {
            sendError('Thiết bị đã được cấp phát trước đó', 400);
        }
        
        // Calculate return date
        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Simple UPDATE without JSON functions (MySQL 5.6 compatible)
        $sql_update = "UPDATE bhld_ctctu 
                      SET sl = 1, 
                          ngnhan = '$ngnhan',
                          ngnhantt = '$ngnhantt'
                      WHERE mact = '$mact' AND mavt = $mavt AND sl = 0";
        
        $update_result = mysqli_query($conn, $sql_update);
        
        if (!$update_result) {
            sendError('Lỗi cập nhật: ' . mysqli_error($conn), 500);
        }
        
        $affected = mysqli_affected_rows($conn);
        
        if ($affected === 0) {
            sendError('Thiết bị đã được cấp phát hoặc không tồn tại', 400);
        }
        
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt
        ], 'Cấp phát thiết bị thành công');
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi cấp phát: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
