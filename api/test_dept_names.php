<?php
/**
 * Kiểm tra tên phòng ban thực tế trong database
 */

require_once __DIR__ . '/db_connection.php';

echo "=== DANH SÁCH TÊN PHÒNG BAN TRONG DATABASE ===\n\n";

$sql = "SELECT mapb, tenphong FROM bhld_phongban ORDER BY mapb ASC";
$res = mysqli_query($conn, $sql);

if ($res) {
    $count = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $count++;
        $mapb = $row['mapb'];
        $tenphong = $row['tenphong'];
        $len = mb_strlen($tenphong, 'UTF-8');
        echo "$count. [$mapb] '$tenphong' (length: $len)\n";
        
        // Hiển thị hex để check ký tự đặc biệt
        $hex = bin2hex($tenphong);
        echo "   HEX: $hex\n";
        
        // Check với các tên cần gom
        $checkNames = [
            'Xưởng SC và CC ĐVL',
            'Xưởng SC cơ khí chuyên dụng',
            'Đội carota tổng hợp',
            'Đội công nghệ cao',
        ];
        
        foreach ($checkNames as $checkName) {
            if (trim($tenphong) === $checkName) {
                echo "   >>> MATCH: '$checkName'\n";
            } elseif (stripos($tenphong, $checkName) !== false) {
                echo "   >>> PARTIAL MATCH: '$checkName'\n";
            }
        }
        echo "\n";
    }
    echo "Tổng số: $count phòng ban\n";
} else {
    echo "Lỗi query: " . mysqli_error($conn) . "\n";
}

mysqli_close($conn);
?>
