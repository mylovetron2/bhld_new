<?php
/**
 * Test query đơn giản - không có JOIN
 * http://diavatly.com/BHLD/api/test_simple_ctu.php
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Query đơn giản - chỉ lấy từ bhld_ctu, không JOIN
    $sql = "SELECT 
                mact,
                manv,
                ngct,
                mapb,
                ghichu,
                madm
            FROM bhld_ctu
            ORDER BY ngct DESC
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        sendError('Lỗi query: ' . mysqli_error($conn));
    }
    
    $certificates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $certificates[] = $row;
    }
    
    sendSuccess([
        'total_found' => count($certificates),
        'certificates' => $certificates
    ], 'Lấy chứng từ (không JOIN) thành công');
    
} catch (Exception $e) {
    sendError('Lỗi: ' . $e->getMessage());
}

mysqli_close($conn);
?>
