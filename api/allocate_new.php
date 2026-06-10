<?php
/**
 * API Cấp phát thiết bị - NEW VERSION
 */

require_once 'config.php';

function columnExists($conn, $tableName, $columnName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

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

        $hasQtyRequired = columnExists($conn, 'bhld_ctctu', 'so_luong_yeu_cau');
        $hasQtyIssued = columnExists($conn, 'bhld_ctctu', 'so_luong_cap');
        $hasSize = columnExists($conn, 'bhld_ctctu', 'size_label');
        $hasColor = columnExists($conn, 'bhld_ctctu', 'mau_label');
        $hasType = columnExists($conn, 'bhld_ctctu', 'loai_label');
        $hasSpec = columnExists($conn, 'bhld_ctctu', 'quycach_label');

        $sizeLabel = isset($data['size']) ? mysqli_real_escape_string($conn, trim((string)$data['size'])) : '';
        $mauLabel = isset($data['mau']) ? mysqli_real_escape_string($conn, trim((string)$data['mau'])) : '';
        $loaiLabel = isset($data['loai']) ? mysqli_real_escape_string($conn, trim((string)$data['loai'])) : '';
        $quyCachLabel = isset($data['quycach']) ? mysqli_real_escape_string($conn, trim((string)$data['quycach'])) : '';
        
        $selectParts = ['dmtg', 'sl'];
        if ($hasQtyRequired) $selectParts[] = 'so_luong_yeu_cau';
        if ($hasQtyIssued) $selectParts[] = 'so_luong_cap';
        if ($hasSize) $selectParts[] = 'size_label';
        if ($hasColor) $selectParts[] = 'mau_label';
        if ($hasType) $selectParts[] = 'loai_label';
        if ($hasSpec) $selectParts[] = 'quycach_label';

        $result = mysqli_query($conn, "SELECT " . implode(',', $selectParts) . " FROM bhld_ctctu WHERE mact='$mact' AND mavt=$mavt");
        if (!$result || mysqli_num_rows($result) === 0) {
            sendError('Không tìm thấy', 404);
        }
        
        $row = mysqli_fetch_assoc($result);
        if ($row['sl'] != 0) {
            sendError('Đã cấp phát', 400);
        }
        
        $dmtg = $row['dmtg'];
        $qtyRequired = $hasQtyRequired ? max(1, intval($row['so_luong_yeu_cau'])) : 1;
        $qtyIssued = isset($data['so_luong_cap']) ? max(1, intval($data['so_luong_cap'])) : $qtyRequired;

        if ($sizeLabel === '' && $hasSize && !empty($row['size_label'])) $sizeLabel = mysqli_real_escape_string($conn, $row['size_label']);
        if ($mauLabel === '' && $hasColor && !empty($row['mau_label'])) $mauLabel = mysqli_real_escape_string($conn, $row['mau_label']);
        if ($loaiLabel === '' && $hasType && !empty($row['loai_label'])) $loaiLabel = mysqli_real_escape_string($conn, $row['loai_label']);
        if ($quyCachLabel === '' && $hasSpec && !empty($row['quycach_label'])) $quyCachLabel = mysqli_real_escape_string($conn, $row['quycach_label']);

        if ($quyCachLabel === '') {
            $parts = [];
            if ($sizeLabel !== '') $parts[] = 'Size ' . $sizeLabel;
            if ($mauLabel !== '') $parts[] = 'Mau ' . $mauLabel;
            if ($loaiLabel !== '') $parts[] = 'Loai ' . $loaiLabel;
            $quyCachLabel = implode(' - ', $parts);
        }

        $ngnhantt = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
        
        // Update current
        $setParts = [
            "sl=1",
            "ngnhan='$ngnhan'",
            "ngnhantt='$ngnhantt'",
        ];
        if ($hasQtyRequired) $setParts[] = "so_luong_yeu_cau=$qtyRequired";
        if ($hasQtyIssued) $setParts[] = "so_luong_cap=$qtyIssued";
        if ($hasSize) $setParts[] = $sizeLabel === '' ? "size_label=NULL" : "size_label='$sizeLabel'";
        if ($hasColor) $setParts[] = $mauLabel === '' ? "mau_label=NULL" : "mau_label='$mauLabel'";
        if ($hasType) $setParts[] = $loaiLabel === '' ? "loai_label=NULL" : "loai_label='$loaiLabel'";
        if ($hasSpec) $setParts[] = $quyCachLabel === '' ? "quycach_label=NULL" : "quycach_label='$quyCachLabel'";

        mysqli_query($conn, "UPDATE bhld_ctctu SET " . implode(', ', $setParts) . " WHERE mact='$mact' AND mavt=$mavt");
        
        // Get master
        $m_result = mysqli_query($conn, "SELECT manv, madm, mapb, ngct FROM bhld_ctu WHERE mact='$mact'");
        $next = ['created' => false];
        
        if ($dmtg > 0 && $m_result && mysqli_num_rows($m_result) > 0) {
            $m = mysqli_fetch_assoc($m_result);
            
            // Calculate next period date - use ngnhan instead of ngct
            $ngct_next = date('Y-m-d', strtotime($ngnhan . ' + ' . $dmtg . ' month'));
            $ym = date('Y-m', strtotime($ngct_next));
            $manv_fmt = (is_numeric($m['manv']) && strlen($m['manv'])==4) ? '0'.$m['manv'] : $m['manv'];
            $mact_next = $ym . '-' . $m['mapb'] . '-' . $manv_fmt;
            
            $next['mact_next'] = $mact_next;
            
            // Bảo vệ: không tạo nếu mact_next trùng mact hiện tại
            if ($mact_next === $mact) {
                $next['skipped'] = 'mact_next trùng mact hiện tại (dmtg quá nhỏ)';
            } else {
            // Check master
            $check_m = mysqli_query($conn, "SELECT mact FROM bhld_ctu WHERE mact='$mact_next'");
            if (mysqli_num_rows($check_m) === 0) {
                mysqli_query($conn, "INSERT INTO bhld_ctu (mact,manv,madm,mapb,ngct) VALUES ('$mact_next','{$m['manv']}','{$m['madm']}','{$m['mapb']}','$ngct_next')");
                $next['master_created'] = true;
            }
            
            // Check detail
            $check_d = mysqli_query($conn, "SELECT mact FROM bhld_ctctu WHERE mact='$mact_next' AND mavt=$mavt");
            if (mysqli_num_rows($check_d) === 0) {
                $nCols = ['mact','mavt','sl','ngnhan','ngnhantt','dmtg'];
                $nVals = ["'$mact_next'", $mavt, 0, "'1911-11-11'", "'1911-11-11'", $dmtg];
                if ($hasQtyRequired) { $nCols[] = 'so_luong_yeu_cau'; $nVals[] = $qtyRequired; }
                if ($hasQtyIssued) { $nCols[] = 'so_luong_cap'; $nVals[] = 0; }
                if ($hasSize) { $nCols[] = 'size_label'; $nVals[] = $sizeLabel === '' ? 'NULL' : "'$sizeLabel'"; }
                if ($hasColor) { $nCols[] = 'mau_label'; $nVals[] = $mauLabel === '' ? 'NULL' : "'$mauLabel'"; }
                if ($hasType) { $nCols[] = 'loai_label'; $nVals[] = $loaiLabel === '' ? 'NULL' : "'$loaiLabel'"; }
                if ($hasSpec) { $nCols[] = 'quycach_label'; $nVals[] = $quyCachLabel === '' ? 'NULL' : "'$quyCachLabel'"; }

                mysqli_query($conn, "INSERT INTO bhld_ctctu (" . implode(',', $nCols) . ") VALUES (" . implode(',', $nVals) . ")");
                $next['detail_created'] = true;
            }
            
            $next['created'] = true;
            }
        }
        
        sendSuccess([
            'mact' => $mact,
            'mavt' => $mavt,
            'so_luong_cap' => $qtyIssued,
            'ngnhan' => $ngnhan,
            'ngnhantt' => $ngnhantt,
            'quycach' => $quyCachLabel,
            'next_period' => $next
        ], '✅ Cấp phát thành công - NEW API 2025');
    } else {
        sendError('Method not allowed', 405);
    }
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
?>
