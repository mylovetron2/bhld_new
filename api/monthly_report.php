<?php
require_once 'config.php';

function columnExists($conn, $tableName, $columnName) {
    $tableName = mysqli_real_escape_string($conn, $tableName);
    $columnName = mysqli_real_escape_string($conn, $columnName);
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = '$tableName' AND column_name = '$columnName' LIMIT 1";
    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}

function detectEquipmentBucket($name) {
    $name = mb_strtolower((string)$name, 'UTF-8');
    $rules = [
        'Giày' => ['giay'],
        'Mũ' => ['mu'],
        'Áo quần' => ['ao quan', 'quan ao'],
        'Kính' => ['kinh'],
        'Áo mưa' => ['ao mua'],
        'Nút tai' => ['nut tai'],
        'Phim' => ['phin', 'phim'],
        'Găng tay' => ['gang tay'],
        'Khẩu trang' => ['khau trang'],
        'Áo phao cứu sinh' => ['ao phao', 'cuu sinh'],
        'Găng tay da thợ hàn' => ['gang tay da', 'tho han'],
    ];

    foreach ($rules as $bucket => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($name, $kw, 0, 'UTF-8') !== false) {
                return $bucket;
            }
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
    
    // Danh sách thiết bị chuẩn (mở rộng)
    $standardEquipment = ['Giày', 'Mũ', 'Áo quần', 'Kính', 'Áo mưa', 'Nút tai', 'Phim', 'Găng tay', 'Khẩu trang', 'Áo phao cứu sinh', 'Găng tay da thợ hàn'];

    $qtyExpr = columnExists($conn, 'bhld_ctctu', 'so_luong_cap') ? 'IFNULL(ctct.so_luong_cap, ctct.sl)' : 'ctct.sl';
    
    // Lấy danh sách phòng ban và nhân viên
    $sql = "SELECT pb.mapb, pb.tenphong as tenphongban, nv.manv, nv.tennhanvien
            FROM bhld_phongban pb
            LEFT JOIN bhld_nhanvien nv ON pb.mapb = nv.mapb
            WHERE nv.manv IS NOT NULL
            ORDER BY pb.mapb, nv.manv";
    
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        throw new Exception(mysqli_error($conn));
    }
    
    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $deptCode = $row['mapb'];
        
        if (!isset($departments[$deptCode])) {
            $departments[$deptCode] = [
                'mapb' => $deptCode,
                'tenphongban' => $row['tenphongban'],
                'employees' => []
            ];
        }
        
        // Khởi tạo thiết bị cho nhân viên
        $equipment = [];
        foreach ($standardEquipment as $equipName) {
            $equipment[$equipName] = ['received' => 0, 'required' => 1, 'notes' => null];
        }
        
        $departments[$deptCode]['employees'][] = [
            'manv' => $row['manv'],
            'tennhanvien' => $row['tennhanvien'],
            'equipment' => $equipment
        ];
    }
    
    // Lấy thiết bị đã cấp trong tháng (theo ngày nhận thực tế)
    $sql2 = "SELECT nv.mapb, nv.manv, vt.tenvt, $qtyExpr as sl_cap
             FROM bhld_ctctu ctct
             INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
             INNER JOIN bhld_nhanvien nv ON ct.manv = nv.manv
             LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
             WHERE ctct.ngnhan BETWEEN '$startDate' AND '$endDate' AND $qtyExpr > 0";
    
    $equipmentCount = 0;
    $result2 = mysqli_query($conn, $sql2);
    if ($result2) {
        while ($row = mysqli_fetch_assoc($result2)) {
            $deptCode = $row['mapb'];
            $empCode = $row['manv'];
            $tenvt = $row['tenvt'];
            $sl = (int)$row['sl_cap'];
            
            if (isset($departments[$deptCode])) {
                // Dùng index thay vì reference
                for ($i = 0; $i < count($departments[$deptCode]['employees']); $i++) {
                    if ($departments[$deptCode]['employees'][$i]['manv'] == $empCode && $tenvt) {
                        $bucket = detectEquipmentBucket($tenvt);
                        if ($bucket !== null && isset($departments[$deptCode]['employees'][$i]['equipment'][$bucket])) {
                            $departments[$deptCode]['employees'][$i]['equipment'][$bucket]['received'] += $sl;
                            $equipmentCount += $sl;
                        }
                    }
                }
            }
        }
    }
    
    // Lọc chỉ giữ lại nhân viên có nhận thiết bị trong tháng
    foreach ($departments as $deptCode => &$dept) {
        $filteredEmployees = [];
        foreach ($dept['employees'] as $employee) {
            $hasEquipment = false;
            foreach ($employee['equipment'] as $equip) {
                if ($equip['received'] > 0) {
                    $hasEquipment = true;
                    break;
                }
            }
            if ($hasEquipment) {
                $filteredEmployees[] = $employee;
            }
        }
        $dept['employees'] = $filteredEmployees;
    }
    
    // Loại bỏ phòng ban không có nhân viên nhận thiết bị
    $departments = array_filter($departments, function($dept) {
        return count($dept['employees']) > 0;
    });
    
    // Lấy thống kê tổng hợp vật tư đã nhận trong tháng
    $sql3 = "SELECT vt.mavt, vt.tenvt, vt.dvt, SUM($qtyExpr) as total_qty
             FROM bhld_ctctu ctct
             INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
             LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
             WHERE ctct.ngnhan BETWEEN '$startDate' AND '$endDate' 
             AND $qtyExpr > 0
             GROUP BY vt.mavt, vt.tenvt, vt.dvt
             ORDER BY vt.tenvt";
    
    $summary = [];
    $totalQty = 0;
    $result3 = mysqli_query($conn, $sql3);
    if ($result3) {
        while ($row = mysqli_fetch_assoc($result3)) {
            $summary[] = [
                'mavt' => $row['mavt'],
                'tenvt' => $row['tenvt'],
                'dvt' => $row['dvt'],
                'soluong' => (int)$row['total_qty']
            ];
            $totalQty += (int)$row['total_qty'];
        }
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
