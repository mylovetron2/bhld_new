<?php
/**
 * API Cấp phát lần đầu - tạo CT + chi tiết + cấp phát + CT kỳ tiếp
 * POST body: { mact, manv, ngct, mapb, madm, vattu: [{mavt, dmtg},...] }
 */
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

function columnExists($conn, $tableName, $columnName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

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

    $hasQtyRequired = columnExists($conn, 'bhld_ctctu', 'so_luong_yeu_cau');
    $hasQtyIssued = columnExists($conn, 'bhld_ctctu', 'so_luong_cap');
    $hasSize = columnExists($conn, 'bhld_ctctu', 'size_label');
    $hasColor = columnExists($conn, 'bhld_ctctu', 'mau_label');
    $hasType = columnExists($conn, 'bhld_ctctu', 'loai_label');
    $hasSpec = columnExists($conn, 'bhld_ctctu', 'quycach_label');

    $created = 0;
    $next_cts = [];

    foreach ($vattu as $vt) {
        $mavt = intval($vt['mavt']);
        $dmtg = intval($vt['dmtg']);
        $soLuong = isset($vt['so_luong']) ? max(1, intval($vt['so_luong'])) : 1;
        $sizeLabel = isset($vt['size']) ? mysqli_real_escape_string($conn, trim((string)$vt['size'])) : '';
        $mauLabel = isset($vt['mau']) ? mysqli_real_escape_string($conn, trim((string)$vt['mau'])) : '';
        $loaiLabel = isset($vt['loai']) ? mysqli_real_escape_string($conn, trim((string)$vt['loai'])) : '';
        $quyCachLabel = isset($vt['quycach']) ? mysqli_real_escape_string($conn, trim((string)$vt['quycach'])) : '';

        if ($quyCachLabel === '') {
            $parts = [];
            if ($sizeLabel !== '') $parts[] = 'Size ' . $sizeLabel;
            if ($mauLabel !== '') $parts[] = 'Mau ' . $mauLabel;
            if ($loaiLabel !== '') $parts[] = 'Loai ' . $loaiLabel;
            $quyCachLabel = implode(' - ', $parts);
        }

        if ($mavt <= 0) continue;

        $ngnhantt = $dmtg > 0
            ? date('Y-m-d', strtotime($ngct . ' + ' . $dmtg . ' month'))
            : $ngct;

        // 2. Insert chi tiết sl=1 (cấp phát luôn)
        $chkd = mysqli_query($conn, "SELECT sl FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if ($chkd && mysqli_num_rows($chkd) > 0) {
            $setParts = [
                "sl=1",
                "ngnhan='$ngct'",
                "ngnhantt='$ngnhantt'",
                "dmtg=$dmtg",
            ];
            if ($hasQtyRequired) $setParts[] = "so_luong_yeu_cau=$soLuong";
            if ($hasQtyIssued) $setParts[] = "so_luong_cap=$soLuong";
            if ($hasSize) $setParts[] = $sizeLabel === '' ? "size_label=NULL" : "size_label='$sizeLabel'";
            if ($hasColor) $setParts[] = $mauLabel === '' ? "mau_label=NULL" : "mau_label='$mauLabel'";
            if ($hasType) $setParts[] = $loaiLabel === '' ? "loai_label=NULL" : "loai_label='$loaiLabel'";
            if ($hasSpec) $setParts[] = $quyCachLabel === '' ? "quycach_label=NULL" : "quycach_label='$quyCachLabel'";

            mysqli_query($conn, "UPDATE bhld_ctctu SET " . implode(',', $setParts) . " WHERE mact='$mact' AND mavt=$mavt");
        } else {
            $cols = ['mact','mavt','sl','ngnhan','ngnhantt','dmtg'];
            $vals = ["'$mact'", $mavt, 1, "'$ngct'", "'$ngnhantt'", $dmtg];
            if ($hasQtyRequired) { $cols[] = 'so_luong_yeu_cau'; $vals[] = $soLuong; }
            if ($hasQtyIssued) { $cols[] = 'so_luong_cap'; $vals[] = $soLuong; }
            if ($hasSize) { $cols[] = 'size_label'; $vals[] = $sizeLabel === '' ? 'NULL' : "'$sizeLabel'"; }
            if ($hasColor) { $cols[] = 'mau_label'; $vals[] = $mauLabel === '' ? 'NULL' : "'$mauLabel'"; }
            if ($hasType) { $cols[] = 'loai_label'; $vals[] = $loaiLabel === '' ? 'NULL' : "'$loaiLabel'"; }
            if ($hasSpec) { $cols[] = 'quycach_label'; $vals[] = $quyCachLabel === '' ? 'NULL' : "'$quyCachLabel'"; }

            mysqli_query($conn, "INSERT INTO bhld_ctctu (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
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
                    $nCols = ['mact','mavt','sl','ngnhan','ngnhantt','dmtg'];
                    $nVals = ["'$mact_next'", $mavt, 0, "'1911-11-11'", "'1911-11-11'", $dmtg];
                    if ($hasQtyRequired) { $nCols[] = 'so_luong_yeu_cau'; $nVals[] = $soLuong; }
                    if ($hasQtyIssued) { $nCols[] = 'so_luong_cap'; $nVals[] = 0; }
                    if ($hasSize) { $nCols[] = 'size_label'; $nVals[] = $sizeLabel === '' ? 'NULL' : "'$sizeLabel'"; }
                    if ($hasColor) { $nCols[] = 'mau_label'; $nVals[] = $mauLabel === '' ? 'NULL' : "'$mauLabel'"; }
                    if ($hasType) { $nCols[] = 'loai_label'; $nVals[] = $loaiLabel === '' ? 'NULL' : "'$loaiLabel'"; }
                    if ($hasSpec) { $nCols[] = 'quycach_label'; $nVals[] = $quyCachLabel === '' ? 'NULL' : "'$quyCachLabel'"; }

                    mysqli_query($conn, "INSERT INTO bhld_ctctu (" . implode(',', $nCols) . ") VALUES (" . implode(',', $nVals) . ")");
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
