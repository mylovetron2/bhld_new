<?php
/**
 * Test version of allocate API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($method === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Validate input
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['ngnhan'])) {
            sendError('Thiếu thông tin bắt buộc (mact, mavt, ngnhan)', 400);
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        
        // Step 1: Get current record info
        $sql_get = "SELECT dmtg, sl FROM bhld_ctctu WHERE mact = '$mact' AND mavt = $mavt";
        $result = mysqli_query($conn, $sql_get);
        
        if (!$result) {
            sendError('Lỗi query: ' . mysqli_error($conn), 500);
        }
        
        if (mysqli_num_rows($result) === 0) {
            sendError('Không tìm thấy bản ghi', 404);
        }
        
        $row = mysqli_fetch_assoc($result);
        $dmtg = $row['dmtg'];
        $sl = $row['sl'];
        
        if ($sl != 0) {
            sendError('Thiết bị đã được cấp phát (sl=' . $sl . ')', 400);
        }
        
        // Calculate ngnhantt
        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Step 2: Update current record
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
            sendError('Không thể cập nhật (có thể đã bị cấp phát)', 400);
        }
        
        // Step 3: Create next period certificate
        $sql_master = "SELECT manv, madm, mapb, ngct FROM bhld_ctu WHERE mact = '$mact'";
        $result_master = mysqli_query($conn, $sql_master);
        
        $next_period_info = ['step' => 'get_master'];
        
        if ($result_master && mysqli_num_rows($result_master) > 0) {
            $master = mysqli_fetch_assoc($result_master);
            $next_period_info['master_found'] = true;
            
            // Calculate next period date - use ngnhan instead of ngct
            $ngct_next = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
            $year_month = date('Y-m', strtotime($ngct_next));
            
            // Format manv
            $manv_formatted = $master['manv'];
            if (is_numeric($master['manv']) && strlen($master['manv']) == 4) {
                $manv_formatted = '0' . $master['manv'];
            }
            
            // Build mact_next
            $mact_next = $year_month . '-' . $master['mapb'] . '-' . $manv_formatted;
            
            $next_period_info['mact_next'] = $mact_next;
            $next_period_info['ngct_next'] = $ngct_next;
            $next_period_info['step'] = 'check_master';
            
            // Check if master exists
            $sql_check_master = "SELECT mact FROM bhld_ctu WHERE mact = '$mact_next'";
            $result_check = mysqli_query($conn, $sql_check_master);
            
            if (mysqli_num_rows($result_check) === 0) {
                $next_period_info['step'] = 'create_master';
                
                // Create master
                $sql_insert_master = "INSERT INTO bhld_ctu (mact, manv, madm, mapb, ngct) 
                                     VALUES ('$mact_next', '{$master['manv']}', '{$master['madm']}', 
                                             '{$master['mapb']}', '$ngct_next')";
                $next_period_info['sql_master'] = $sql_insert_master;
                
                $insert_master = mysqli_query($conn, $sql_insert_master);
                
                if ($insert_master) {
                    $affected = mysqli_affected_rows($conn);
                    $next_period_info['master_created'] = ($affected > 0);
                    $next_period_info['master_affected_rows'] = $affected;
                    $next_period_info['master_insert_id'] = mysqli_insert_id($conn);
                    
                    if ($affected === 0) {
                        $next_period_info['master_warning'] = 'Query OK but 0 rows affected';
                    }
                } else {
                    $next_period_info['master_created'] = false;
                    $next_period_info['master_error'] = mysqli_error($conn);
                }
            } else {
                $next_period_info['master_exists'] = true;
            }
            
            // Check if detail exists
            $nextnext_period_info['sql_detail'] = $sql_insert_detail;
                
                $insert_detail = mysqli_query($conn, $sql_insert_detail);
                
                if ($insert_detail) {
                    $affected_detail = mysqli_affected_rows($conn);
                    $next_period_info['detail_created'] = ($affected_detail > 0);
                    $next_period_info['detail_affected_rows'] = $affected_detail;
                    
                    if ($affected_detail === 0) {
                        $next_period_info['detail_warning'] = 'Query OK but 0 rows affected';
                    }
                } else {
                    $next_period_info['detail_created'] = false;um_rows($result_check_detail) === 0) {
                $next_period_info['step'] = 'create_detail';
                
                // Create detail
                $sql_insert_detail = "INSERT INTO bhld_ctctu (mact, mavt, sl, ngnhan, ngnhantt, dmtg) 
                                     VALUES ('$mact_next', $mavt, 0, '1911-11-11', '1911-11-11', $dmtg)";
                $insert_detail = mysqli_query($conn, $sql_insert_detail);
                
                if ($insert_detail) {
                    $next_period_info['detail_created'] = true;
                } else {
                    $next_period_info['detail_error'] = mysqli_error($conn);
                }
            } else {
                $next_period_info['detail_exists'] = true;
            }
            
            $next_period_info['step'] = 'completed';
        } else {
            $next_period_info['master_found'] = false;
            $next_period_info['error'] = 'Không tìm thấy master record';
        }
        
        // Success
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt,
            'dmtg' => $dmtg,
            'affected_rows' => $affected,
            'next_period' => $next_period_info
        ], 'Cấp phát thiết bị thành công');
        
    } else {
        sendError('Method không được hỗ trợ: ' . $method, 405);
    }
} catch (Exception $e) {
    sendError('Exception: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
