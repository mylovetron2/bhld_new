<?php
/**
 * API Thu hồi thiết bị (Deallocate) - v2.0
 * POST /deallocate.php
 * Body: {mact, mavt}
 * 
 * Logic theo MO_TA_TRA_THIET_BI_1_SANG_0.html:
 * 1. UPDATE record hiện tại: sl=0, ngnhan='1911-11-11', ngnhantt='1911-11-11'
 * 2. XÓA chứng từ kỳ sau (detail only)
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt'])) {
            sendError('Thiếu thông tin bắt buộc (mact, mavt)', 400);
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        
        // Get record info
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
        
        // Check if not allocated yet
        if ($current_sl == 0) {
            sendError('Thiết bị chưa được cấp phát', 400);
        }
        
        // Step 1: Update current record - reset to default values
        $sql_update = "UPDATE bhld_ctctu 
                      SET sl = 0, 
                          ngnhan = '1911-11-11',
                          ngnhantt = '1911-11-11'
                      WHERE mact = '$mact' AND mavt = $mavt AND sl = 1";
        
        $update_result = mysqli_query($conn, $sql_update);
        
        if (!$update_result) {
            sendError('Lỗi cập nhật: ' . mysqli_error($conn), 500);
        }
        
        $affected = mysqli_affected_rows($conn);
        
        if ($affected === 0) {
            sendError('Thiết bị chưa được cấp phát hoặc không tồn tại', 400);
        }
        
        // Step 2: DELETE next period detail record
        // Get master record info
        $sql_master = "SELECT manv, mapb, ngct FROM bhld_ctu WHERE mact = '$mact'";
        $result_master = mysqli_query($conn, $sql_master);
        
        $deleted_next_period = false;
        $mact_next = null;
        
        if ($result_master && mysqli_num_rows($result_master) > 0) {
            $master = mysqli_fetch_assoc($result_master);
            
            // Calculate next period date - use ngnhan instead of ngct
            $ngct_next = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
            $year_month = date('Y-m', strtotime($ngct_next));
            
            // Format manv (add 0 prefix if numeric 4-digit)
            $manv_formatted = $master['manv'];
            if (is_numeric($master['manv']) && strlen($master['manv']) == 4) {
                $manv_formatted = '0' . $master['manv'];
            }
            
            // Build mact for next period
            $mact_next = $year_month . '-' . $master['mapb'] . '-' . $manv_formatted;
            
            // DELETE detail record only (keep master as it may have other items)
            $sql_delete_detail = "DELETE FROM bhld_ctctu 
                                 WHERE mact = '$mact_next' AND mavt = $mavt";
            
            $delete_result = mysqli_query($conn, $sql_delete_detail);
            
            if ($delete_result && mysqli_affected_rows($conn) > 0) {
                $deleted_next_period = true;
            }
        }
        
        // Success
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'next_period_deleted' => $deleted_next_period,
            'next_period_mact' => $mact_next,
            'next_period_info' => isset($master) ? [
                'manv' => $master['manv'],
                'mapb' => $master['mapb'],
                'ngct_next' => isset($ngct_next) ? $ngct_next : null,
                'manv_formatted' => isset($manv_formatted) ? $manv_formatted : null
            ] : null
        ], 'Thu hồi thiết bị thành công - API v2.0');
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi trả thiết bị: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
