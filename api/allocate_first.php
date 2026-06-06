<?php
/**
 * API Cấp phát lần đầu - tạo CT + chi tiết + cấp phát + CT kỳ tiếp
 * POST body: { mact, manv, ngct, mapb, madm, vattu: [{mavt, dmtg},...] }
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendError('Method not allowed', 405);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['mact'], $data['manv'], $data['ngct'], $data['mapb'], $data['madm'], $data['vattu'])) {
        sendError('Thiếu thông tin bắt buộc', 400);
    }

    $mact   = mysqli_real_escape_string($conn, $data['mact']);
    $manv   = mysqli_real_escape_string($conn, $data['manv']);
    $ngct   = mysqli_real_escape_string($conn, $data['ngct']);
    $mapb   = mysqli_real_escape_string($conn, $data['mapb']);
    $madm   = mysqli_real_escape_string($conn, $data['madm']);
    $vattu  = $data['vattu']; // [{mavt, dmtg}]

    mysqli_begin_transaction($conn);

    // 1. Tạo CT master (nếu chưa có)
    $chk = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact='$mact'");
    if (mysqli_num_rows($chk) === 0) {
        if (!mysqli_query($conn, "INSERT INTO bhld_ctu (mact,manv,mapb,madm,ngct) VALUES ('$mact','$manv','$mapb','$madm','$ngct')")) {
            mysqli_rollback($conn);
            sendError('Lỗi tạo CT: ' . mysqli_error($conn), 500);
        }
    }

    $created = 0;
    $next_cts = [];

    foreach ($vattu as $vt) {
        $mavt = intval($vt['mavt']);
        $dmtg = intval($vt['dmtg']);
        if ($mavt <= 0) continue;

        $ngnhantt = $dmtg > 0
            ? date('Y-m-d', strtotime($ngct . ' + ' . $dmtg . ' month'))
            : $ngct;

        // 2. Insert chi tiết sl=1 (cấp phát luôn)
        $chkd = mysqli_query($conn, "SELECT sl FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if ($chkd && mysqli_num_rows($chkd) > 0) {
            // đã có → update
            mysqli_query($conn, "UPDATE bhld_ctctu SET sl=1,ngnhan='$ngct',ngnhantt='$ngnhantt',dmtg=$dmtg WHERE mact='$mact' AND mavt=$mavt");
        } else {
            mysqli_query($conn, "INSERT INTO bhld_ctctu (mact,mavt,sl,ngnhan,ngnhantt,dmtg) VALUES ('$mact',$mavt,1,'$ngct','$ngnhantt',$dmtg)");
        }
        $created++;

        // 3. Tạo CT kỳ tiếp theo (chỉ nếu dmtg > 0)
        if ($dmtg > 0) {
            $ngct_next = $ngnhantt;
            $ym = date('Y-m', strtotime($ngct_next));
            $manv_fmt = (is_numeric($manv) && strlen($manv) == 4) ? '0'.$manv : $manv;
            $mact_next = $ym . '-' . $mapb . '-' . $manv_fmt;

            if ($mact_next !== $mact) {
                // Tạo CT master kỳ sau nếu chưa có
                $chkn = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact='$mact_next'");
                if (mysqli_num_rows($chkn) === 0) {
                    mysqli_query($conn, "INSERT INTO bhld_ctu (mact,manv,mapb,madm,ngct) VALUES ('$mact_next','$manv','$mapb','$madm','$ngct_next')");
                }
                // Tạo chi tiết kỳ sau sl=0 nếu chưa có
                $chknd = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact_next' AND mavt=$mavt");
                if (mysqli_num_rows($chknd) === 0) {
                    mysqli_query($conn, "INSERT INTO bhld_ctctu (mact,mavt,sl,ngnhan,ngnhantt,dmtg) VALUES ('$mact_next',$mavt,0,'1911-11-11','1911-11-11',$dmtg)");
                }
                $next_cts[$mact_next] = true;
            }
        }
    }

    mysqli_commit($conn);

    sendSuccess([
        'mact'      => $mact,
        'allocated' => $created,
        'next_cts'  => array_keys($next_cts),
    ], "Cấp phát lần đầu thành công: $created vật tư");

} catch (Exception $e) {
    mysqli_rollback($conn);
    sendError($e->getMessage(), 500);
}
?>
