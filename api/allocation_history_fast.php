<?php
/**
 * API Lấy lịch sử cấp phát - Ultra Simple Version
 * Không JOIN, chỉ lấy dữ liệu từ bhld_ctctu và bhld_ctu
 */

require_once 'config.php';

try {
    $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
    
    // Query siêu đơn giản - chỉ 2 bảng, không LEFT JOIN
    $where = "1=1";
    if ($status === 'allocated') {
        $where = "ctd.sl = 1";
    } elseif ($status === 'returned') {
        $where = "ctd.sl = 0";
    }
    
    $sql = "SELECT 
                ct.mact,
                ct.manv,
                ct.ngct,
                '' as tennhanvien,
                '' as tenphongban,
                ctd.mavt,
                '' as tenvt,
                '' as dvt,
                ctd.sl,
                ctd.ngnhan,
                ctd.ngnhantt,
                ctd.dmtg
            FROM bhld_ctctu ctd
            INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
            WHERE $where
            ORDER BY ct.ngct DESC
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        sendError('Lỗi query: ' . mysqli_error($conn));
        exit;
    }
    
    $history = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $history[] = $row;
    }
    
    sendSuccess($history, 'Lấy ' . count($history) . ' records (ultra fast mode)');
    
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
