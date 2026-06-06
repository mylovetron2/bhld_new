<?php
/**
 * API Lấy lịch sử cấp phát - Version đơn giản
 * Chỉ lấy 10 records gần nhất, không filter phức tạp
 */

require_once 'config.php';

try {
    // Query đơn giản nhất - chỉ lấy 10 records
    $sql = "SELECT 
                ct.mact,
                ct.manv,
                ct.ngct,
                COALESCE(nv.tennhanvien, 'N/A') as tennhanvien,
                COALESCE(pb.tenphong, 'N/A') as tenphongban,
                ctd.mavt,
                COALESCE(vt.tenvt, 'N/A') as tenvt,
                COALESCE(vt.dvt, '') as dvt,
                ctd.sl,
                ctd.ngnhan,
                ctd.ngnhantt,
                ctd.dmtg
            FROM bhld_ctctu ctd
            INNER JOIN bhld_ctu ct ON ctd.mact = ct.mact
            LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
            LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
            LEFT JOIN bhld_dmvattu vt ON ctd.mavt = vt.mavt
            WHERE ctd.sl = 1
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
    
    sendSuccess($history, 'Lấy ' . count($history) . ' records');
    
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage(), 500);
}

mysqli_close($conn);
?>
