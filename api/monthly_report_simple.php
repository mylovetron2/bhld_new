<?php
require_once 'config.php';

try {
    // Lấy tháng
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
    
    // Tạo ngày
    $startDate = "$year-$monthNum-01";
    $endDate = date("Y-m-t", strtotime($startDate));
    
    // Query đơn giản - chỉ đếm
    $sql = "
        SELECT 
            pb.mapb,
            pb.tenphong as tenphongban,
            COUNT(DISTINCT nv.manv) as total_employees
        FROM bhld_phongban pb
        LEFT JOIN bhld_nhanvien nv ON pb.mapb = nv.mapb
        WHERE nv.manv IS NOT NULL
        GROUP BY pb.mapb, pb.tenphong
        ORDER BY pb.mapb
        LIMIT 5
    ";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        throw new Exception('Query failed: ' . mysqli_error($conn));
    }
    
    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = [
            'mapb' => $row['mapb'],
            'tenphongban' => $row['tenphongban'],
            'employees' => [] // Simplified - empty for now
        ];
    }
    
    sendSuccess([
        'month' => "$monthNum/$year",
        'startDate' => $startDate,
        'endDate' => $endDate,
        'departments' => $departments,
        'test' => true
    ], 'Test thành công');
    
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}
?>
