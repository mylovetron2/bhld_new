<?php
require_once 'config.php';

function columnExists($conn, $tableName, $columnName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

function tableExists($conn, $tableName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$tableName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

function normalizeText($s) {
    $s = trim((string)$s);
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $map = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
        'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
        'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
        'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d'
    ];
    $s = strtr($s, $map);
    return preg_replace('/\s+/', ' ', $s);
}

function detectEquipmentBucket($mavt, $tenvt) {
    // Map trực tiếp mã vật tư → bucket (chính xác 100%, không cần đoán qua tên)
    $mavtMap = [
        '500120' => 'Giày',
        '500500' => 'Mũ',
        '500860' => 'Áo quần',
        '501545' => 'Kính',
        '501660' => 'Áo mưa',
        '60000'  => 'Áo phao cứu sinh',
        '10000'  => 'Nút tai',
        '20000'  => 'Phim',
        '30000'  => 'Găng tay',
        '40000'  => 'Khẩu trang',
        '70000'  => 'Găng tay da thợ hàn',
    ];
    $key = (string)$mavt;
    if (isset($mavtMap[$key])) return $mavtMap[$key];

    // Fallback: thử khớp tên nếu gặp mã vật tư mới chưa có trong map
    $name = normalizeText((string)$tenvt);
    $rules = [
        'Áo mưa'              => ['ao bat di mua', 'ao mua', 'bat mua'],
        'Áo phao cứu sinh'    => ['ao phao', 'cuu sinh'],
        'Áo quần'             => ['ao quan bao ho', 'quan ao bao ho', 'ao quan', 'quan ao'],
        'Găng tay da thợ hàn' => ['gang tay da'],
        'Găng tay'            => ['gang tay'],
        'Khẩu trang'          => ['khau trang'],
        'Kính'                => ['kinh bao ho', 'kinh'],
        'Giày'                => ['giay bao ho', 'giay'],
        'Mũ'                  => ['mu bao ho', 'mu'],
        'Nút tai'             => ['nut tai'],
        'Phim'                => ['phin loc', 'phin', 'phim'],
    ];
    foreach ($rules as $bucket => $keywords) {
        foreach ($keywords as $kw) {
            $normalizedKw = normalizeText($kw);
            $pattern = '/(?<![a-z])' . preg_quote($normalizedKw, '/') . '(?![a-z])/u';
            if (preg_match($pattern, $name)) return $bucket;
        }
    }
    return null;
}

try {
    // Lấy tháng từ request
    $month = isset($_GET['month']) ? $_GET['month'] : date('m/Y');
    
    // Parse month
    $monthParts = preg_split('/[\/\-]/', $month);
    if (count($monthParts) == 2) {
        if (strlen($monthParts[0]) == 4) {
            $year = $monthParts[0];
            $monthNum = str_pad($monthParts[1], 2, '0', STR_PAD_LEFT);
        } else {
            $monthNum = str_pad($monthParts[0], 2, '0', STR_PAD_LEFT);
            $year = $monthParts[1];
        }
    } else {
        sendError('Format tháng không hợp lệ', 400);
    }
    
    $startDate = "$year-$monthNum-01";
    $endDate = date("Y-m-t", strtotime($startDate));
    $nextMonthStart = date("Y-m-01", strtotime($startDate . " +1 month"));

    if (!tableExists($conn, 'bhld_view_chungtu_danhan_final')) {
        sendSuccess([
            'month' => "$monthNum/$year",
            'startDate' => $startDate,
            'endDate' => $endDate,
            'departments' => [],
            'summary' => [
                'items' => [],
                'totalQuantity' => 0
            ]
        ], 'Chưa có dữ liệu báo cáo trên host mới');
    }

    // Danh sách thiết bị chuẩn (mở rộng)
    $standardEquipment = ['Giày', 'Mũ', 'Áo quần', 'Kính', 'Áo mưa', 'Nút tai', 'Phim', 'Găng tay', 'Khẩu trang', 'Áo phao cứu sinh', 'Găng tay da thợ hàn'];

    // Dữ liệu báo cáo tháng dựa trên view đã nhận cuối cùng.
    $escStart = mysqli_real_escape_string($conn, $startDate);
    $escNext = mysqli_real_escape_string($conn, $nextMonthStart);

    $sql2 = "SELECT
                f.mapb,
                COALESCE(pb.tenphong, f.mapb) AS tenphongban,
                f.manv,
                COALESCE(nv.tennhanvien, f.manv) AS tennhanvien,
                f.mavt,
                vt.tenvt,
                vt.dvt,
                SUM(f.sl) AS sl_cap
             FROM bhld_view_chungtu_danhan_final f
             LEFT JOIN bhld_phongban pb ON pb.mapb = f.mapb
             LEFT JOIN bhld_nhanvien nv ON nv.manv = f.manv
             LEFT JOIN bhld_dmvattu vt ON vt.mavt = f.mavt
             WHERE f.ngnhan >= '$escStart'
               AND f.ngnhan < '$escNext'
               AND COALESCE(nv.trangthai, 1) = 1
             GROUP BY
                f.mapb, pb.tenphong,
                f.manv, nv.tennhanvien,
                f.mavt, vt.tenvt, vt.dvt
             ORDER BY f.mapb, f.manv, f.mavt";

    $departments = [];
    $employeeIndex = [];
    $result2 = mysqli_query($conn, $sql2);
    if (!$result2) {
        throw new Exception(mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($result2)) {
        $deptCode = (string)$row['mapb'];
        $deptName = (string)$row['tenphongban'];
        $empCode = (string)$row['manv'];
        $empName = (string)$row['tennhanvien'];
        $sl = (int)$row['sl_cap'];
        $bucket = detectEquipmentBucket($row['mavt'], isset($row['tenvt']) ? $row['tenvt'] : '');

        if (!isset($departments[$deptCode])) {
            $departments[$deptCode] = [
                'mapb' => $deptCode,
                'tenphongban' => $deptName,
                'employees' => []
            ];
            $employeeIndex[$deptCode] = [];
        }

        if (!isset($employeeIndex[$deptCode][$empCode])) {
            $equipment = [];
            foreach ($standardEquipment as $equipName) {
                $equipment[$equipName] = ['received' => 0, 'required' => 1, 'notes' => null];
            }
            $departments[$deptCode]['employees'][] = [
                'manv' => $empCode,
                'tennhanvien' => $empName,
                'equipment' => $equipment
            ];
            $employeeIndex[$deptCode][$empCode] = count($departments[$deptCode]['employees']) - 1;
        }

        if ($bucket !== null) {
            $idx = $employeeIndex[$deptCode][$empCode];
            if (isset($departments[$deptCode]['employees'][$idx]['equipment'][$bucket])) {
                $departments[$deptCode]['employees'][$idx]['equipment'][$bucket]['received'] += $sl;
            }
        }
    }

    $sql3 = "SELECT
                f.mavt,
                vt.tenvt,
                vt.dvt,
                SUM(f.sl) AS total_qty
             FROM bhld_view_chungtu_danhan_final f
             LEFT JOIN bhld_dmvattu vt ON vt.mavt = f.mavt
             WHERE f.ngnhan >= '$escStart'
               AND f.ngnhan < '$escNext'
             GROUP BY f.mavt, vt.tenvt, vt.dvt
             ORDER BY vt.tenvt";
    
    $summary = [];
    $totalQty = 0;
    $result3 = mysqli_query($conn, $sql3);
    if (!$result3) {
        throw new Exception(mysqli_error($conn));
    }
    while ($row = mysqli_fetch_assoc($result3)) {
        $summary[] = [
            'mavt' => $row['mavt'],
            'tenvt' => $row['tenvt'],
            'dvt' => $row['dvt'],
            'soluong' => (int)$row['total_qty']
        ];
        $totalQty += (int)$row['total_qty'];
    }
    
    sendSuccess([
        'month' => "$monthNum/$year",
        'startDate' => $startDate,
        'endDate' => $endDate,
        'departments' => array_values($departments),
        'summary' => [
            'items' => $summary,
            'totalQuantity' => $totalQty
        ]
    ], 'Lấy báo cáo thành công');
    
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}
?>
