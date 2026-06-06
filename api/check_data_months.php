<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "<h2>Debug: Kiểm tra dữ liệu thiết bị theo tháng</h2>";

// Tìm tháng có dữ liệu
$sql = "SELECT 
    DATE_FORMAT(ct.ngct, '%m/%Y') as thang,
    COUNT(DISTINCT ct.mact) as so_chungtu,
    COUNT(ctct.mavt) as so_thietbi,
    MIN(ct.ngct) as ngay_dau,
    MAX(ct.ngct) as ngay_cuoi
FROM bhld_ctu ct
INNER JOIN bhld_ctctu ctct ON ct.mact = ctct.mact
WHERE ctct.sl > 0
GROUP BY DATE_FORMAT(ct.ngct, '%Y-%m')
ORDER BY ct.ngct DESC
LIMIT 10";

$result = mysqli_query($conn, $sql);

echo "<h3>Top 10 tháng có nhiều dữ liệu nhất:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Tháng</th><th>Số chứng từ</th><th>Số thiết bị</th><th>Từ ngày</th><th>Đến ngày</th><th>Test</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    $testUrl = "monthly_report.php?month=" . urlencode($row['thang']);
    echo "<tr>";
    echo "<td>{$row['thang']}</td>";
    echo "<td>{$row['so_chungtu']}</td>";
    echo "<td>{$row['so_thietbi']}</td>";
    echo "<td>{$row['ngay_dau']}</td>";
    echo "<td>{$row['ngay_cuoi']}</td>";
    echo "<td><a href='$testUrl' target='_blank'>Test</a></td>";
    echo "</tr>";
}
echo "</table>";

// Sample thiết bị từ tháng gần nhất
echo "<h3>Mẫu dữ liệu thiết bị (10 record đầu tiên):</h3>";
$sql2 = "SELECT 
    ct.ngct,
    ct.mact,
    nv.manv,
    nv.tennhanvien,
    vt.tenvt,
    ctct.sl
FROM bhld_ctctu ctct
INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
INNER JOIN bhld_nhanvien nv ON ct.manv = nv.manv
LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
WHERE ctct.sl > 0
ORDER BY ct.ngct DESC
LIMIT 10";

$result2 = mysqli_query($conn, $sql2);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Ngày</th><th>Mã CT</th><th>Mã NV</th><th>Tên NV</th><th>Tên VT</th><th>SL</th></tr>";
while ($row = mysqli_fetch_assoc($result2)) {
    echo "<tr>";
    echo "<td>{$row['ngct']}</td>";
    echo "<td>{$row['mact']}</td>";
    echo "<td>{$row['manv']}</td>";
    echo "<td>{$row['tennhanvien']}</td>";
    echo "<td>{$row['tenvt']}</td>";
    echo "<td>{$row['sl']}</td>";
    echo "</tr>";
}
echo "</table>";
?>
