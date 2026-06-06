<?php
/**
 * Kiểm tra cấu trúc bảng bhld_phongban
 */

require_once 'config.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    // Kiểm tra cấu trúc bảng
    $sql = "DESCRIBE bhld_phongban";
    $result = mysqli_query($conn, $sql);
    
    $columns = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $columns[] = $row;
        }
    }
    
    // Lấy mẫu dữ liệu
    $sql2 = "SELECT * FROM bhld_phongban LIMIT 3";
    $result2 = mysqli_query($conn, $sql2);
    
    $sample_data = [];
    if ($result2) {
        while ($row = mysqli_fetch_assoc($result2)) {
            $sample_data[] = $row;
        }
    }
    
    echo json_encode([
        'success' => true,
        'table' => 'bhld_phongban',
        'columns' => $columns,
        'sample_data' => $sample_data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

mysqli_close($conn);
?>
