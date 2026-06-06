<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$month = isset($_GET['month']) ? $_GET['month'] : '12/2025';
$manv = isset($_GET['manv']) ? $_GET['manv'] : '21445';

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
}

$startDate = "$year-$monthNum-01";
$endDate = date("Y-m-t", strtotime($startDate));

echo "<h3>Test Monthly Data - NV: $manv - Tháng: $month</h3>";
echo "<p>Start: $startDate | End: $endDate</p>";

// Test query với ngnhan
echo "<h4>Dữ liệu theo ctct.ngnhan:</h4>";
$sql = "SELECT ct.mact, ct.ngct, vt.tenvt, ctct.sl, ctct.ngnhan
        FROM bhld_ctctu ctct
        INNER JOIN bhld_ctu ct ON ctct.mact = ct.mact
        LEFT JOIN bhld_dmvattu vt ON ctct.mavt = vt.mavt
        WHERE ct.manv = '$manv' 
        AND ctct.ngnhan BETWEEN '$startDate' AND '$endDate' 
        AND ctct.sl > 0
        ORDER BY ctct.ngnhan";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo "<p style='color:red'>Query Error: " . mysqli_error($conn) . "</p>";
} else {
    $count = mysqli_num_rows($result);
    echo "<p>Số dòng tìm thấy: $count</p>";
    
    if ($count > 0) {
        echo "<table border='1' style='border-collapse:collapse'>";
        echo "<tr><th>Mã CT</th><th>Ngày CT</th><th>Vật tư</th><th>SL</th><th>Ngày nhận</th></tr>";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>{$row['mact']}</td>";
            echo "<td>{$row['ngct']}</td>";
            echo "<td>{$row['tenvt']}</td>";
            echo "<td>{$row['sl']}</td>";
            echo "<td>{$row['ngnhan']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
?>
