<?php
/**
 * API Cấp phát thiết bị
 * POST /allocate.php
 * Body: {mact, mavt, ngnhan}
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Debug: log request method and input
error_log("allocate_final.php - Method: $method");
error_log("allocate_final.php - Input: " . file_get_contents('php://input'));

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['ngnhan'])) {
            sendError('Thiếu thông tin bắt buộc (mact, mavt, ngnhan)', 400);
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        
        // Get dmtg and current sl from record
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
        
        // Calculate return date (MySQL 5.6 compatible - using PHP date functions)
        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Update record: sl = 1, update dates
        // Added sl = 0 condition to prevent race conditions
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
        
        // Step 2: Create next period certificate (if not exists)
        // Get master record info
        $sql_master = "SELECT manv, madm, mapb, ngct FROM bhld_ctu WHERE mact = '$mact'";
        $result_master = mysqli_query($conn, $sql_master);
        
        $next_period_info = [];
        
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
            
            $next_period_info['mact_next'] = $mact_next;
            $next_period_info['ngct_next'] = $ngct_next;
            
            // Check if master exists
            $sql_check_master = "SELECT mact FROM bhld_ctu WHERE mact = '$mact_next'";
            $result_check = mysqli_query($conn, $sql_check_master);
            
            $master_created = false;
            if (mysqli_num_rows($result_check) === 0) {
                // Create master record
                $sql_insert_master = "INSERT INTO bhld_ctu (mact, manv, madm, mapb, ngct) 
                                     VALUES ('$mact_next', '{$master['manv']}', '{$master['madm']}', 
                                             '{$master['mapb']}', '$ngct_next')";
                $insert_result = mysqli_query($conn, $sql_insert_master);
                
                if ($insert_result) {
                    $master_created = true;
                } else {
                    $next_period_info['master_error'] = mysqli_error($conn);
                }
            } else {
                $next_period_info['master_exists'] = true;
            }
            
            $next_period_info['master_created'] = $master_created;
            
            // Check if detail exists
            $sql_check_detail = "SELECT mact FROM bhld_ctctu WHERE mact = '$mact_next' AND mavt = $mavt";
            $result_check_detail = mysqli_query($conn, $sql_check_detail);
            
            $detail_created = false;
            if (mysqli_num_rows($result_check_detail) === 0) {
                // Create detail record for next period (sl=0, waiting to receive)
                $sql_insert_detail = "INSERT INTO bhld_ctctu (mact, mavt, sl, ngnhan, ngnhantt, dmtg) 
                                     VALUES ('$mact_next', $mavt, 0, '1911-11-11', '1911-11-11', $dmtg)";
                $insert_detail_result = mysqli_query($conn, $sql_insert_detail);
                
                if ($insert_detail_result) {
                    $detail_created = true;
                } else {
                    $next_period_info['detail_error'] = mysqli_error($conn);
                }
            } else {
                $next_period_info['detail_exists'] = true;
            }
            
            $next_period_info['detail_created'] = $detail_created;
        } else {
            $next_period_info['error'] = 'Không tìm thấy master record';
        }
        
        // Success
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt,
            'dmtg' => $dmtg,
            'next_period' => $next_period_info
        ], 'Cấp phát thiết bị thành công');
        
    } else {
        sendError('Method không được hỗ trợ', 405);
    }
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
