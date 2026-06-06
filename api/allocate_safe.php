<?php
/**
 * API Cấp phát thiết bị - Version an toàn với error logging
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, only log
$error_log = [];

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
            sendError('Không tìm thấy thiết bị trong chứng từ (mact=' . $mact . ', mavt=' . $mavt . ')', 404);
        }
        
        $row = mysqli_fetch_assoc($result);
        $dmtg = intval($row['dmtg']);
        $current_sl = intval($row['sl']);
        
        // Check if already allocated
        if ($current_sl == 1) {
            sendError('Thiết bị đã được cấp phát trước đó', 400);
        }
        
        // Calculate return date (ngnhantt = ngnhan + dmtg months)
        try {
            $date = new DateTime($ngnhan);
            $date->add(new DateInterval('P' . $dmtg . 'M'));
            $ngnhantt = $date->format('Y-m-d');
        } catch (Exception $e) {
            sendError('Lỗi tính toán ngày trả: ' . $e->getMessage(), 500);
        }
        
        // Update record: sl = 1, update dates
        $sql_update = "UPDATE bhld_ctctu 
                      SET sl = 1, 
                          ngnhan = '$ngnhan',
                          ngnhantt = '$ngnhantt'
                      WHERE mact = '$mact' AND mavt = $mavt";
        
        $update_result = mysqli_query($conn, $sql_update);
        
        if (!$update_result) {
            sendError('Lỗi cập nhật: ' . mysqli_error($conn), 500);
        }
        
        if (mysqli_affected_rows($conn) === 0) {
            sendError('Không có dòng nào được cập nhật. Kiểm tra lại mact và mavt.', 500);
        }
        
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt,
            'dmtg' => $dmtg
        ], 'Cấp phát thiết bị thành công');
        
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi cấp phát: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
