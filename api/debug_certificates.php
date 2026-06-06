<?php
/**
 * Debug certificates.php query
 * http://diavatly.com/BHLD/api/debug_certificates.php
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    $sql = "SELECT 
                ct.mact,
                ct.ngct,
                ct.mapb,
                ct.manv,
                ct.ghichu,
                ct.madm,
                nv.tennhanvien,
                pb.tenphong as tenphongban
            FROM bhld_ctu ct
            LEFT JOIN bhld_nhanvien nv ON ct.manv = nv.manv
            LEFT JOIN bhld_phongban pb ON ct.mapb = pb.mapb
            ORDER BY ct.ngct DESC 
            LIMIT 10";
    
    $result = mysqli_query($conn, $sql);
    
    if (!$result) {
        echo json_encode([
            'success' => false,
            'error' => mysqli_error($conn),
            'sql' => $sql
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    $certificates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $certificates[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Query thành công với JOIN',
        'total' => count($certificates),
        'sql' => $sql,
        'data' => $certificates
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
