<?php
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

function columnExists($conn, $tableName, $columnName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

try {
    if ($method === 'GET') {
        if (!isset($_GET['mact'])) {
            sendError('Thiếu mã chứng từ (mact)');
        }
        
        $mact = mysqli_real_escape_string($conn, $_GET['mact']);
        
        $extraCols = [];
        if (columnExists($conn, 'bhld_ctctu', 'so_luong_yeu_cau')) $extraCols[] = 'ct.so_luong_yeu_cau';
        if (columnExists($conn, 'bhld_ctctu', 'so_luong_cap')) $extraCols[] = 'ct.so_luong_cap';
        if (columnExists($conn, 'bhld_ctctu', 'size_label')) $extraCols[] = 'ct.size_label';
        if (columnExists($conn, 'bhld_ctctu', 'mau_label')) $extraCols[] = 'ct.mau_label';
        if (columnExists($conn, 'bhld_ctctu', 'loai_label')) $extraCols[] = 'ct.loai_label';
        if (columnExists($conn, 'bhld_ctctu', 'quycach_label')) $extraCols[] = 'ct.quycach_label';

        $selectExtra = empty($extraCols) ? '' : ', ' . implode(', ', $extraCols);

        $sql = "SELECT 
                    ct.mact,
                    ct.mavt,
                    ct.dmtg,
                    ct.sl,
                    ct.ngnhan,
                    ct.ngnhantt,
                    vt.tenvt,
                    vt.dvt
                    $selectExtra
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
        
        $hasQtyRequired = columnExists($conn, 'bhld_ctctu', 'so_luong_yeu_cau');
        $hasQtyIssued = columnExists($conn, 'bhld_ctctu', 'so_luong_cap');
        $hasSize = columnExists($conn, 'bhld_ctctu', 'size_label');
        $hasColor = columnExists($conn, 'bhld_ctctu', 'mau_label');
        $hasType = columnExists($conn, 'bhld_ctctu', 'loai_label');
        $hasSpec = columnExists($conn, 'bhld_ctctu', 'quycach_label');

        $cols = ['mact', 'mavt', 'sl', 'ngnhan', 'ngnhantt', 'dmtg'];
        $vals = ["'$mact'", $mavt, $sl, "'$ngnhan'", "'$ngnhantt'", $dmtg];

        if ($hasQtyRequired && isset($data['so_luong_yeu_cau'])) {
            $cols[] = 'so_luong_yeu_cau';
            $vals[] = max(1, intval($data['so_luong_yeu_cau']));
        }
        if ($hasQtyIssued && isset($data['so_luong_cap'])) {
            $cols[] = 'so_luong_cap';
            $vals[] = max(0, intval($data['so_luong_cap']));
        }
        if ($hasSize && array_key_exists('size_label', $data)) {
            $v = trim((string)$data['size_label']);
            $cols[] = 'size_label';
            $vals[] = $v === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if ($hasColor && array_key_exists('mau_label', $data)) {
            $v = trim((string)$data['mau_label']);
            $cols[] = 'mau_label';
            $vals[] = $v === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if ($hasType && array_key_exists('loai_label', $data)) {
            $v = trim((string)$data['loai_label']);
            $cols[] = 'loai_label';
            $vals[] = $v === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if ($hasSpec && array_key_exists('quycach_label', $data)) {
            $v = trim((string)$data['quycach_label']);
            $cols[] = 'quycach_label';
            $vals[] = $v === '' ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
        }

        $sql = "INSERT INTO bhld_ctctu (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        
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

        if (columnExists($conn, 'bhld_ctctu', 'so_luong_yeu_cau') && isset($data['so_luong_yeu_cau'])) {
            $qtyRequired = max(1, intval($data['so_luong_yeu_cau']));
            $updates[] = "so_luong_yeu_cau = $qtyRequired";
        }
        if (columnExists($conn, 'bhld_ctctu', 'so_luong_cap') && isset($data['so_luong_cap'])) {
            $qtyIssued = max(0, intval($data['so_luong_cap']));
            $updates[] = "so_luong_cap = $qtyIssued";
        }
        if (columnExists($conn, 'bhld_ctctu', 'size_label') && array_key_exists('size_label', $data)) {
            $v = trim((string)$data['size_label']);
            $updates[] = $v === '' ? "size_label = NULL" : "size_label = '" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if (columnExists($conn, 'bhld_ctctu', 'mau_label') && array_key_exists('mau_label', $data)) {
            $v = trim((string)$data['mau_label']);
            $updates[] = $v === '' ? "mau_label = NULL" : "mau_label = '" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if (columnExists($conn, 'bhld_ctctu', 'loai_label') && array_key_exists('loai_label', $data)) {
            $v = trim((string)$data['loai_label']);
            $updates[] = $v === '' ? "loai_label = NULL" : "loai_label = '" . mysqli_real_escape_string($conn, $v) . "'";
        }
        if (columnExists($conn, 'bhld_ctctu', 'quycach_label') && array_key_exists('quycach_label', $data)) {
            $v = trim((string)$data['quycach_label']);
            $updates[] = $v === '' ? "quycach_label = NULL" : "quycach_label = '" . mysqli_real_escape_string($conn, $v) . "'";
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
