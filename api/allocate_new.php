<?php
/**
 * API Cấp phát thiết bị - NEW VERSION
 */

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['mact']) || !isset($data['mavt']) || !isset($data['ngnhan'])) {
            sendError('Thiếu thông tin', 400);
        }
        
        $mact = mysqli_real_escape_string($conn, $data['mact']);
        $mavt = intval($data['mavt']);
        $ngnhan = mysqli_real_escape_string($conn, $data['ngnhan']);
        
        // Get dmtg and sl
        $result = mysqli_query($conn, "SELECT dmtg, sl FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if (!$result || mysqli_num_rows($result) === 0) {
            sendError('Không tìm thấy', 404);
        }
        
        $row = mysqli_fetch_assoc($result);
        if ($row['sl'] != 0) {
            sendError('Đã cấp phát', 400);
        }
        
        $dmtg = $row['dmtg'];
        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Update current
        mysqli_query($conn, "UPDATE bhld_ctctu SET sl=1, ngnhan='$ngnhan', ngnhantt='$ngnhantt' WHERE mact='$mact' AND mavt=$mavt");
        
        // Get master
        $m_result = mysqli_query($conn, "SELECT manv, madm, mapb, ngct FROM bhld_ctu WHERE mact='$mact'");
        $next = ['created' => false];
        
        if ($m_result && mysqli_num_rows($m_result) > 0) {
            $m = mysqli_fetch_assoc($m_result);
            
            // Calculate next period date - use ngnhan instead of ngct
            $ngct_next = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
            $ym = date('Y-m', strtotime($ngct_next));
            $manv_fmt = (is_numeric($m['manv']) && strlen($m['manv'])==4) ? '0'.$m['manv'] : $m['manv'];
            $mact_next = $ym . '-' . $m['mapb'] . '-' . $manv_fmt;
            
            $next['mact_next'] = $mact_next;
            
            // Check master
            $check_m = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact='$mact_next'");
            if (mysqli_num_rows($check_m) === 0) {
                mysqli_query($conn, "INSERT INTO bhld_ctu (mact,manv,madm,mapb,ngct) VALUES ('$mact_next','{$m['manv']}','{$m['madm']}','{$m['mapb']}','$ngct_next')");
                $next['master_created'] = true;
            }
            
            // Check detail
            $check_d = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact_next' AND mavt=$mavt");
            if (mysqli_num_rows($check_d) === 0) {
                mysqli_query($conn, "INSERT INTO bhld_ctctu (mact,mavt,sl,ngnhan,ngnhantt,dmtg) VALUES ('$mact_next',$mavt,0,'1911-11-11','1911-11-11',$dmtg)");
                $next['detail_created'] = true;
            }
            
            $next['created'] = true;
        }
        
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt,
            'next_period' => $next
        ], '✅ Cấp phát thành công - NEW API 2025');
    } else {
        sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
